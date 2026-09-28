<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Consulta de documento contra api.json.pe (DNI y RUC).
 *
 * Las respuestas se simulan con Http::fake() usando el formato REAL que
 * devuelve el servicio (comprobado contra la API en vivo), así los tests no
 * dependen de la red ni del token.
 */
class DocumentLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        config()->set('services.jsonpe.token', 'token-de-prueba');
        config()->set('services.jsonpe.url', 'https://api.json.pe/api/dni');
        config()->set('services.jsonpe.ruc_url', 'https://api.json.pe/api/ruc');
    }

    public function test_consulta_de_dni_devuelve_el_nombre_reordenado(): void
    {
        Http::fake(['api.json.pe/api/dni' => Http::response([
            'success' => true,
            'message' => 'exito',
            'data' => [
                'numero' => '27427864',
                'nombre_completo' => 'CASTILLO TERRONES, JOSE PEDRO',
                'nombres' => 'JOSE PEDRO',
                'apellido_paterno' => 'CASTILLO',
                'apellido_materno' => 'TERRONES',
            ],
        ])]);

        $r = $this->actingAs($this->user)->getJson('/dni-lookup/27427864');

        $r->assertOk();
        $r->assertJson([
            'found' => true,
            'type' => 'dni',
            'document' => '27427864',
            'name' => 'JOSE PEDRO CASTILLO TERRONES',
        ]);
    }

    public function test_consulta_de_ruc_devuelve_razon_social_y_domicilio(): void
    {
        Http::fake(['api.json.pe/api/ruc' => Http::response([
            'success' => true,
            'message' => 'exito',
            'data' => [
                'ruc' => '20552103816',
                'nombre_o_razon_social' => 'AGROLIGHT PERU S.A.C.',
                'estado' => 'SUSPENSION TEMPORAL',
                'condicion' => 'HABIDO',
                'direccion_completa' => 'PJ. JORGE BASADRE NRO. 158, LIMA - LIMA - SANTA ANITA',
            ],
        ])]);

        $r = $this->actingAs($this->user)->getJson('/dni-lookup/20552103816');

        $r->assertOk();
        $r->assertJson([
            'found' => true,
            'type' => 'ruc',
            'document' => '20552103816',
            'name' => 'AGROLIGHT PERU S.A.C.',
            'status' => 'SUSPENSION TEMPORAL',
            'condition' => 'HABIDO',
            'address' => 'PJ. JORGE BASADRE NRO. 158, LIMA - LIMA - SANTA ANITA',
        ]);
    }

    /** El endpoint elige DNI o RUC por la longitud del número. */
    public function test_el_endpoint_elige_la_api_segun_la_longitud(): void
    {
        Http::fake([
            'api.json.pe/api/dni' => Http::response(['success' => true, 'data' => ['numero' => '27427864', 'nombres' => 'ANA', 'apellido_paterno' => 'PEREZ']]),
            'api.json.pe/api/ruc' => Http::response(['success' => true, 'data' => ['ruc' => '20552103816', 'nombre_o_razon_social' => 'EMPRESA SAC']]),
        ]);

        $this->actingAs($this->user)->getJson('/dni-lookup/27427864')->assertJsonPath('type', 'dni');
        $this->actingAs($this->user)->getJson('/dni-lookup/20552103816')->assertJsonPath('type', 'ruc');

        Http::assertSent(fn ($req) => $req->url() === 'https://api.json.pe/api/dni' && $req['dni'] === '27427864');
        Http::assertSent(fn ($req) => $req->url() === 'https://api.json.pe/api/ruc' && $req['ruc'] === '20552103816');
    }

    public function test_documento_con_longitud_invalida_es_rechazado(): void
    {
        Http::fake();

        $this->actingAs($this->user)->getJson('/dni-lookup/123')
            ->assertStatus(422)
            ->assertJson(['found' => false]);

        // No se gasta una llamada a la API con un número que no puede existir.
        Http::assertNothingSent();
    }

    public function test_documento_no_encontrado_responde_404(): void
    {
        Http::fake(['api.json.pe/api/ruc' => Http::response(['success' => false, 'message' => 'no existe'])]);

        $this->actingAs($this->user)->getJson('/dni-lookup/20552103816')
            ->assertStatus(404)
            ->assertJson(['found' => false])
            ->assertJsonFragment(['message' => 'No se encontró información para ese RUC.']);
    }

    /** Un fallo de la API no debe romper el cobro: responde 404 controlado. */
    public function test_error_de_la_api_no_lanza_excepcion(): void
    {
        Http::fake(['api.json.pe/api/dni' => Http::response('boom', 500)]);

        $this->actingAs($this->user)->getJson('/dni-lookup/27427864')
            ->assertStatus(404)
            ->assertJson(['found' => false]);
    }

    /** La pantalla de Clientes lee data.name y data.dni: no deben desaparecer. */
    public function test_se_mantiene_la_compatibilidad_con_la_vista_de_clientes(): void
    {
        Http::fake(['api.json.pe/api/dni' => Http::response([
            'success' => true,
            'data' => ['numero' => '27427864', 'nombres' => 'ANA', 'apellido_paterno' => 'PEREZ'],
        ])]);

        $this->actingAs($this->user)->getJson('/dni-lookup/27427864')
            ->assertOk()
            ->assertJsonStructure(['found', 'dni', 'name'])
            ->assertJsonPath('dni', '27427864');
    }
}
