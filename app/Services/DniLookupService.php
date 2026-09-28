<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consultas a api.json.pe para autocompletar los datos del cliente:
 *
 *   - DNI (8 dígitos)  -> nombre de la persona           POST /api/dni
 *   - RUC (11 dígitos) -> razón social y domicilio fiscal POST /api/ruc
 *
 * Ningún método lanza excepción: el llamador siempre recibe un array
 * normalizado o null, de forma que un fallo de red nunca rompe el cobro.
 */
class DniLookupService
{
    /** Consulta un DNI. Devuelve ['document' => ..., 'name' => ...] o null. */
    public function lookup(string $dni): ?array
    {
        if (!preg_match('/^\d{8}$/', $dni)) {
            return null;
        }

        $data = $this->request(config('services.jsonpe.url'), ['dni' => $dni]);

        return $this->normalizeDni($data);
    }

    /** Consulta un RUC. Devuelve razón social, dirección y estado, o null. */
    public function lookupRuc(string $ruc): ?array
    {
        if (!preg_match('/^\d{11}$/', $ruc)) {
            return null;
        }

        $data = $this->request(config('services.jsonpe.ruc_url'), ['ruc' => $ruc]);

        return $this->normalizeRuc($data);
    }

    /**
     * Enrutador por longitud: 8 dígitos = DNI, 11 = RUC. Es lo que usa el POS,
     * donde el cajero escribe el documento en un único campo sin tener que
     * elegir antes de qué tipo es.
     */
    public function lookupDocument(string $document): ?array
    {
        $document = trim($document);

        if (preg_match('/^\d{8}$/', $document)) {
            return $this->lookup($document);
        }

        if (preg_match('/^\d{11}$/', $document)) {
            return $this->lookupRuc($document);
        }

        return null;
    }

    /** POST autenticado contra api.json.pe. Devuelve el JSON decodificado o null. */
    private function request(string $url, array $payload): ?array
    {
        $token = config('services.jsonpe.token');

        if (empty($token)) {
            Log::warning('DniLookupService: JSONPE_API_TOKEN no configurado en .env');
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->acceptJson()
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::warning('DniLookupService: respuesta no exitosa', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('DniLookupService: error al consultar la API', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * La API envuelve el resultado en "data", pero según la versión del
     * servicio puede venir en "resultado" o en la raíz. Se aceptan las tres.
     */
    private function payloadOf(?array $data): ?array
    {
        if (!$data) {
            return null;
        }

        // success:false es un "no encontrado" explícito de la API.
        if (array_key_exists('success', $data) && $data['success'] === false) {
            return null;
        }

        $payload = $data['data'] ?? $data['resultado'] ?? $data;

        return is_array($payload) ? $payload : null;
    }

    /** Respuesta real de /api/dni: nombre_completo, nombres, apellido_*, numero. */
    private function normalizeDni(?array $data): ?array
    {
        $payload = $this->payloadOf($data);

        if (!$payload) {
            return null;
        }

        $nombres = $payload['nombres'] ?? $payload['nombre'] ?? null;
        $apellidoPaterno = $payload['apellido_paterno'] ?? $payload['apellidoPaterno'] ?? null;
        $apellidoMaterno = $payload['apellido_materno'] ?? $payload['apellidoMaterno'] ?? null;
        $nombreCompleto = $payload['nombre_completo'] ?? $payload['nombreCompleto'] ?? null;

        // La API devuelve "APELLIDOS, NOMBRES". Se reordena a "NOMBRES APELLIDOS",
        // que es como se espera leer el nombre en una boleta.
        $name = trim(implode(' ', array_filter([$nombres, $apellidoPaterno, $apellidoMaterno])));

        if ($name === '') {
            $name = $this->flipCommaName($nombreCompleto);
        }

        if ($name === '') {
            return null;
        }

        return [
            'type' => 'dni',
            'document' => $payload['numero'] ?? $payload['dni'] ?? null,
            'name' => $name,
            'address' => $this->cleanText($payload['direccion_completa'] ?? $payload['direccion'] ?? null),
        ];
    }

    /** Respuesta real de /api/ruc: nombre_o_razon_social, direccion_completa, estado, condicion. */
    private function normalizeRuc(?array $data): ?array
    {
        $payload = $this->payloadOf($data);

        if (!$payload) {
            return null;
        }

        $name = $payload['nombre_o_razon_social']
            ?? $payload['razon_social']
            ?? $payload['razonSocial']
            ?? $payload['nombre']
            ?? null;

        $name = $this->cleanText($name);

        if ($name === '') {
            return null;
        }

        return [
            'type' => 'ruc',
            'document' => $payload['ruc'] ?? null,
            'name' => $name,
            'address' => $this->cleanText($payload['direccion_completa'] ?? $payload['direccion'] ?? null),
            'status' => $this->cleanText($payload['estado'] ?? null),
            'condition' => $this->cleanText($payload['condicion'] ?? null),
        ];
    }

    /** "CASTILLO TERRONES, JOSE PEDRO" -> "JOSE PEDRO CASTILLO TERRONES" */
    private function flipCommaName(?string $value): string
    {
        $value = $this->cleanText($value);

        if ($value === '' || !str_contains($value, ',')) {
            return $value;
        }

        [$apellidos, $nombres] = array_map('trim', explode(',', $value, 2));

        return trim($nombres . ' ' . $apellidos);
    }

    private function cleanText(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }
}
