<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El sub-sidebar del POS lista las zonas del salón como pestañas.
 *
 * Al "eliminar" una zona que ya tuvo pedidos no se borra físicamente (se
 * perdería el historial de ventas de sus mesas): se marca active = false.
 * Esas zonas no deben aparecer en el POS.
 */
class PosZonesSidebarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_solo_se_listan_las_zonas_activas(): void
    {
        $activa = Area::create(['name' => 'Salón Principal', 'active' => true]);
        $inactiva = Area::create(['name' => 'Terraza Cerrada', 'active' => false]);

        $r = $this->actingAs($this->user)->get(route('pos.index'));

        $r->assertOk();
        $r->assertSee('Salón Principal');
        $r->assertDontSee('Terraza Cerrada');

        // Tampoco debe quedar la pestaña apuntando al panel de la zona inactiva.
        $r->assertSee('#area-' . $activa->id, false);
        $r->assertDontSee('#area-' . $inactiva->id, false);
    }

    /**
     * La pestaña activa por defecto es la primera; el panel visible también.
     * Si el layout y el controlador no filtraran/ordenaran igual, la pestaña
     * marcada no correspondería al panel mostrado.
     */
    public function test_la_primera_pestania_corresponde_al_primer_panel(): void
    {
        // La zona inactiva se crea PRIMERO: si no se filtrara, sería la que
        // quedaría marcada como pestaña activa.
        $inactiva = Area::create(['name' => 'Zona Vieja', 'active' => false]);
        $primeraActiva = Area::create(['name' => 'Salón A', 'active' => true]);
        Area::create(['name' => 'Salón B', 'active' => true]);

        $html = $this->actingAs($this->user)->get(route('pos.index'))->getContent();

        // Primera pestaña del sub-sidebar (botón con clase active)
        preg_match('/pos-zone-nav-link active"[^>]*data-bs-target="#area-(\d+)"/', $html, $tab);
        // Primer panel visible (tab-pane show active)
        preg_match('/tab-pane fade show active" id="area-(\d+)"/', $html, $pane);

        $this->assertNotEmpty($tab, 'No se encontró la pestaña activa');
        $this->assertNotEmpty($pane, 'No se encontró el panel activo');
        $this->assertSame($tab[1], $pane[1], 'La pestaña activa no coincide con el panel visible');
        $this->assertSame((string) $primeraActiva->id, $tab[1]);
        $this->assertNotSame((string) $inactiva->id, $tab[1]);
    }

    /** Las mesas desactivadas tampoco se muestran dentro de una zona activa. */
    public function test_no_se_muestran_mesas_desactivadas(): void
    {
        $area = Area::create(['name' => 'Salón Principal', 'active' => true]);
        Table::create(['name' => 'Mesa Activa', 'area_id' => $area->id, 'active' => true]);
        Table::create(['name' => 'Mesa Retirada', 'area_id' => $area->id, 'active' => false]);

        $r = $this->actingAs($this->user)->get(route('pos.index'));

        $r->assertOk();
        $r->assertSee('Mesa Activa');
        $r->assertDontSee('Mesa Retirada');
    }

    /** Sin zonas activas la pantalla sigue cargando (no revienta el layout). */
    public function test_sin_zonas_activas_la_pantalla_carga(): void
    {
        Area::create(['name' => 'Zona Vieja', 'active' => false]);

        $this->actingAs($this->user)->get(route('pos.index'))->assertOk();
    }
}
