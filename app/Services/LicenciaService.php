<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Cliente del servidor de licencias.
 *
 * Este sistema corre en la infraestructura del cliente, así que nada de lo
 * que se guarde aquí es confiable por sí solo: cualquiera con acceso a la
 * base de datos puede editar la tabla `settings`. Lo que hace fiable al
 * conjunto es que el servidor firma su respuesta con RSA y aquí sólo se
 * guarda ese token firmado, que se verifica con la clave pública antes de
 * creer nada de su contenido.
 *
 * Principios de diseño:
 *
 *  - NO se llama a la red en cada petición. El middleware sólo verifica el
 *    token local. La revalidación es un trabajo diario programado.
 *  - Un fallo de red NO bloquea el sistema: existe un periodo de gracia. Un
 *    restaurante no puede quedarse sin cobrar porque falle una conexión.
 *  - Sólo se bloquea cuando hay certeza: licencia vencida o revocada, o
 *    gracia agotada sin lograr contacto.
 */
class LicenciaService
{
    /** Claves usadas en la tabla `settings`. */
    private const K_TOKEN        = 'licencia_token';
    private const K_INSTALACION  = 'licencia_instalacion_id';
    private const K_ULTIMO_OK    = 'licencia_ultima_validacion';
    /** Último motivo informado por el servidor al rechazar la licencia. */
    private const K_MOTIVO       = 'licencia_motivo_rechazo';
    /**
     * Código de licencia, guardado aparte del token.
     *
     * Es deliberado: cuando el servidor revoca una licencia se descarta el
     * token, y si el código viviera sólo dentro de él el sistema perdería la
     * capacidad de volver a preguntar. Al conservarlo, la revalidación diaria
     * sigue funcionando y el cliente se recupera solo en cuanto el proveedor
     * reactiva la licencia, sin reintroducir nada ni llamar a soporte.
     *
     * No es un dato sensible: sin la firma del servidor, un código no
     * autoriza nada.
     */
    private const K_CODIGO       = 'licencia_codigo';

    /**
     * Identificador único y estable de esta instalación.
     *
     * Se genera una sola vez y persiste. Se usa el UUID y no el dominio
     * porque los clientes migran de servidor y cambian de dominio; atarlo al
     * dominio convertiría cada mudanza en una incidencia de soporte.
     */
    public function instalacionId(): string
    {
        // El UUID se crea una vez y no cambia nunca más, pero firstOrCreate
        // consultaba la tabla en cada llamada, y la verificación del token lo
        // pide en cada petición (comparación con el campo 'instalacion' del
        // token). Memorizarlo por petición ahorra ese viaje sin alterar la
        // comprobación, que sigue haciéndose igual.
        if ($this->instalacionMemo === null) {
            $registro = Setting::firstOrCreate(
                ['key' => self::K_INSTALACION],
                ['value' => (string) Str::uuid()]
            );

            $this->instalacionMemo = $registro->value;
        }

        return $this->instalacionMemo;
    }

    /** UUID de instalación, recordado durante esta petición. */
    private ?string $instalacionMemo = null;

    /**
     * Memoria del token durante ESTA petición, y sólo durante ésta.
     *
     * No se usa la caché persistente a propósito: un token revocado no debe
     * poder sobrevivir a su revocación escondido en la caché. Dentro de una
     * misma petición, en cambio, el valor no puede haber cambiado, así que
     * repetir el SELECT no aporta nada. Antes se leía varias veces por
     * petición, también en /kitchen/poll, que corre cada pocos segundos.
     *
     * La firma se sigue verificando en cada llamada a leerToken(): esto sólo
     * evita releer la misma fila, no salta ninguna comprobación.
     */
    private ?string $tokenMemo = null;
    private bool $tokenLeido = false;

    /** Token firmado guardado localmente, si existe. */
    public function token(): ?string
    {
        if (!$this->tokenLeido) {
            $this->tokenMemo = Setting::where('key', self::K_TOKEN)->value('value');
            $this->tokenLeido = true;
        }

        return $this->tokenMemo;
    }

    /** Se llama tras guardar o borrar el token para no servir el valor viejo. */
    private function olvidarTokenMemo(): void
    {
        $this->tokenMemo = null;
        $this->tokenLeido = false;
    }

    /**
     * Verifica la firma de un token y devuelve su contenido.
     *
     * Devuelve null si la firma no es válida, la clave pública no está
     * configurada o el formato es incorrecto. Un token que no verifica se
     * trata exactamente igual que uno inexistente.
     */
    public function leerToken(?string $token = null): ?array
    {
        $token ??= $this->token();
        if (!$token || !str_contains($token, '.')) {
            return null;
        }

        $publicKey = config('licencia.public_key');
        if (!$publicKey) {
            Log::error('LICENCIA_PUBLIC_KEY no configurada: no se puede verificar la licencia.');
            return null;
        }

        [$b64Body, $b64Sig] = explode('.', $token, 2);
        $body = $this->base64UrlDecode($b64Body);
        $sig  = $this->base64UrlDecode($b64Sig);

        if ($body === '' || $sig === '') {
            return null;
        }

        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            Log::error('LICENCIA_PUBLIC_KEY no es una clave pública válida.');
            return null;
        }

        // openssl_verify: 1 correcta, 0 incorrecta, -1 error.
        if (openssl_verify($body, $sig, $key, OPENSSL_ALGO_SHA256) !== 1) {
            Log::warning('Token de licencia con firma inválida. Se descarta.');
            return null;
        }

        $datos = json_decode($body, true);
        if (!is_array($datos)) {
            return null;
        }

        // El token debe haberse emitido para ESTA instalación: así un token
        // copiado de otro cliente no sirve aquí.
        if (($datos['instalacion'] ?? null) !== $this->instalacionId()) {
            Log::warning('Token de licencia emitido para otra instalación.');
            return null;
        }

        return $datos;
    }

    /**
     * Estado actual de la licencia, ya resuelto.
     *
     * Devuelve un array con:
     *   activa    bool    si el sistema puede operar con normalidad
     *   motivo    string  sin_licencia|vencida|revocada|gracia|ok
     *   mensaje   string  texto para mostrar al usuario
     *   vence     ?Carbon fecha de vencimiento de la licencia
     *   dias      ?int    días restantes (negativo si ya venció)
     *   avisar    bool    si conviene mostrar preaviso de renovación
     */
    public function estado(): array
    {
        $datos = $this->leerToken();

        if (!$datos) {
            // Sin token puede ser una instalación nueva o una licencia que el
            // servidor acaba de rechazar. El motivo guardado distingue ambos
            // casos, para no decirle "no tiene licencia" a quien la tiene
            // suspendida por impago.
            $rechazo = Setting::where('key', self::K_MOTIVO)->value('value');

            // Si el código sigue guardado, el sistema puede recuperarse solo
            // en la próxima revalidación: conviene decírselo al cliente para
            // que no crea que debe reinstalar o reintroducir nada.
            $recuperable = (bool) Setting::where('key', self::K_CODIGO)->value('value');
            $cola = $recuperable
                ? ' Se reactivará automáticamente al regularizarse.'
                : '';

            if ($rechazo === 'suspendida') {
                return [
                    'activa'  => false,
                    'motivo'  => 'revocada',
                    'mensaje' => 'La licencia fue suspendida. Contacte con su proveedor.' . $cola,
                    'vence'   => null,
                    'dias'    => null,
                    'avisar'  => false,
                ];
            }

            if (in_array($rechazo, ['expirada', 'cancelada'], true)) {
                return [
                    'activa'  => false,
                    'motivo'  => 'vencida',
                    'mensaje' => 'La licencia ha expirado. Renuévela para seguir operando.' . $cola,
                    'vence'   => null,
                    'dias'    => null,
                    'avisar'  => false,
                ];
            }

            return [
                'activa'  => false,
                'motivo'  => 'sin_licencia',
                'mensaje' => 'El sistema no tiene una licencia activada.',
                'vence'   => null,
                'dias'    => null,
                'avisar'  => false,
            ];
        }

        // El servidor pudo haber suspendido la licencia; el estado viaja
        // firmado dentro del token.
        if (($datos['estado'] ?? '') !== 'activa') {
            return [
                'activa'  => false,
                'motivo'  => 'revocada',
                'mensaje' => 'La licencia fue suspendida. Contacte con su proveedor.',
                'vence'   => null,
                'dias'    => null,
                'avisar'  => false,
            ];
        }

        $ahora = Carbon::now();
        $vence = !empty($datos['vence']) ? Carbon::parse($datos['vence']) : null;

        // Licencia vencida: es un hecho firmado, no una suposición.
        if ($vence && $vence->isPast()) {
            return [
                'activa'  => false,
                'motivo'  => 'vencida',
                'mensaje' => 'La licencia venció el ' . $vence->format('d/m/Y') . '.',
                'vence'   => $vence,
                'dias'    => (int) $ahora->diffInDays($vence, false),
                'avisar'  => false,
            ];
        }

        // El token caduca antes que la licencia: obliga a revalidar de forma
        // periódica. Si caducó, se entra en gracia en lugar de bloquear:
        // puede ser un simple corte de red.
        $expiraToken = !empty($datos['expira']) ? Carbon::parse($datos['expira']) : null;
        if ($expiraToken && $expiraToken->isPast()) {
            $gracia = (int) ($datos['gracia'] ?? 7);
            $limite = $expiraToken->copy()->addDays($gracia);

            if ($ahora->lessThan($limite)) {
                return [
                    'activa'  => true, // sigue operando
                    'motivo'  => 'gracia',
                    'mensaje' => 'No se pudo verificar la licencia. Quedan '
                        . ((int) $ahora->diffInDays($limite, false))
                        . ' día(s) de margen.',
                    'vence'   => $vence,
                    'dias'    => $vence ? (int) $ahora->diffInDays($vence, false) : null,
                    'avisar'  => true,
                ];
            }

            return [
                'activa'  => false,
                'motivo'  => 'sin_contacto',
                'mensaje' => 'No se pudo verificar la licencia dentro del plazo. '
                    . 'Compruebe la conexión a internet.',
                'vence'   => $vence,
                'dias'    => null,
                'avisar'  => false,
            ];
        }

        // Todo en orden: sólo queda decidir si toca preavisar.
        $dias     = $vence ? (int) $ahora->diffInDays($vence, false) : null;
        $preaviso = (int) ($datos['preaviso'] ?? 15);

        return [
            'activa'  => true,
            'motivo'  => 'ok',
            'mensaje' => '',
            'vence'   => $vence,
            'dias'    => $dias,
            'avisar'  => $dias !== null && $dias <= $preaviso,
        ];
    }

    /** Atajo legible para el middleware. */
    public function puedeOperar(): bool
    {
        return $this->estado()['activa'];
    }

    /**
     * Activa la licencia contra el servidor. Se llama una sola vez, desde la
     * pantalla de activación.
     *
     * Devuelve [ok, mensaje].
     */
    public function activar(string $codigo): array
    {
        try {
            $respuesta = Http::timeout(config('licencia.timeout', 15))
                ->acceptJson()
                ->post(config('licencia.servidor') . '/api/licencias/activar', [
                    'codigo'      => $codigo,
                    'instalacion' => $this->instalacionId(),
                    'dominio'     => request()->getHost(),
                ]);
        } catch (\Throwable $e) {
            Log::error('Error de red activando licencia: ' . $e->getMessage());
            return [false, 'No se pudo contactar con el servidor de licencias.'];
        }

        $datos = $respuesta->json();

        if (!$respuesta->successful() || !($datos['ok'] ?? false)) {
            return [false, $datos['error'] ?? 'La activación fue rechazada.'];
        }

        // Nunca se guarda un token sin verificar su firma antes.
        if (!$this->leerToken($datos['token'] ?? null)) {
            return [false, 'El servidor devolvió una licencia que no se pudo verificar.'];
        }

        // El código se guarda aparte para que la revalidación diaria pueda
        // seguir preguntando aunque el token se descarte (ver K_CODIGO).
        Setting::updateOrCreate(['key' => self::K_CODIGO], ['value' => $codigo]);

        $this->guardarToken($datos['token']);

        return [true, 'Licencia activada correctamente.'];
    }

    /**
     * Revalida contra el servidor. La ejecuta el comando programado.
     *
     * Devuelve [ok, mensaje]. Un `false` por fallo de red NO debe interpretarse
     * como licencia inválida: para eso está el periodo de gracia.
     */
    public function revalidar(): array
    {
        // Se usa el código guardado, no el del token: así la revalidación
        // sigue funcionando después de que el servidor revoque la licencia y
        // el sistema se recupere por sí solo cuando el cliente pague.
        $codigo = Setting::where('key', self::K_CODIGO)->value('value')
            ?: ($this->leerToken()['codigo'] ?? null);

        if (!$codigo) {
            return [false, 'No hay una licencia activada.'];
        }

        try {
            $respuesta = Http::timeout(config('licencia.timeout', 15))
                ->acceptJson()
                ->post(config('licencia.servidor') . '/api/licencias/validar', [
                    'codigo'      => $codigo,
                    'instalacion' => $this->instalacionId(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo revalidar la licencia: ' . $e->getMessage());
            return [false, 'Sin conexión con el servidor de licencias.'];
        }

        $cuerpo = $respuesta->json();

        if (!$respuesta->successful() || !($cuerpo['ok'] ?? false)) {
            // Un 403 es un rechazo definitivo del servidor (instalación
            // bloqueada, o código que ya no corresponde a esta instalación).
            // A diferencia de una suspensión, esto no se va a arreglar solo,
            // así que se descarta el código para no reintentar indefinidamente
            // y el cliente tendrá que activar de nuevo.
            if ($respuesta->status() === 403) {
                $this->olvidarToken();
                Setting::where('key', self::K_CODIGO)->delete();
                Setting::where('key', self::K_MOTIVO)->delete();
            }

            return [false, $cuerpo['error'] ?? 'La validación fue rechazada.'];
        }

        // El servidor confirma que la licencia ya no está vigente. Se borra
        // el token: el sistema pasará a estado bloqueado de inmediato.
        //
        // Se conserva el estado informado (suspendida, expirada…) para poder
        // decir al cliente POR QUÉ está bloqueado. Sin esto, al perder el
        // token el sistema diría "no tiene licencia activada", que confunde
        // a quien sí la tiene y lo que ocurre es que dejó de pagar.
        if (!($cuerpo['vigente'] ?? false)) {
            $this->olvidarToken();
            Setting::updateOrCreate(
                ['key' => self::K_MOTIVO],
                ['value' => (string) ($cuerpo['estado'] ?? 'no_vigente')]
            );
            return [false, $cuerpo['error'] ?? 'La licencia ya no está vigente.'];
        }

        if (!$this->leerToken($cuerpo['token'] ?? null)) {
            return [false, 'El servidor devolvió una licencia que no se pudo verificar.'];
        }

        $this->guardarToken($cuerpo['token']);

        return [true, 'Licencia revalidada.'];
    }

    private function guardarToken(string $token): void
    {
        $this->olvidarTokenMemo();
        Setting::updateOrCreate(['key' => self::K_TOKEN], ['value' => $token]);
        Setting::updateOrCreate(
            ['key' => self::K_ULTIMO_OK],
            ['value' => Carbon::now()->toIso8601String()]
        );
        // Un token nuevo y válido deja obsoleto cualquier rechazo anterior.
        Setting::where('key', self::K_MOTIVO)->delete();
    }

    private function olvidarToken(): void
    {
        $this->olvidarTokenMemo();
        Setting::where('key', self::K_TOKEN)->delete();
    }

    /** PHP no trae decodificador base64url nativo. */
    private function base64UrlDecode(string $valor): string
    {
        $padding = (4 - strlen($valor) % 4) % 4;
        $decoded = base64_decode(strtr($valor, '-_', '+/') . str_repeat('=', $padding), true);

        return $decoded === false ? '' : $decoded;
    }
}
