<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pie corporativo del proveedor del sistema. */
class AppFooterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_el_pie_aparece_en_las_pantallas_normales(): void
    {
        $r = $this->actingAs($this->user)->get(route('pos.index'));

        $r->assertOk();
        $r->assertSee('KaYu SAC');
        $r->assertSee('Software ' . date('Y'));
        $r->assertSee('Restaurant');
        $r->assertSee('Perú');
    }

    /**
     * El año se calcula solo: no queda un "2026" quemado que envejezca.
     * Se comprueba que el HTML no traiga un año fijo distinto del actual.
     */
    public function test_el_anio_es_dinamico(): void
    {
        $html = $this->actingAs($this->user)->get(route('pos.index'))->getContent();

        $this->assertStringContainsString('Software ' . date('Y'), $html);
        $this->assertMatchesRegularExpression(
            '/KaYu SAC.*Software ' . date('Y') . '/s',
            $html
        );
    }

    /**
     * La venta de mesa ocupa el 100% del alto con overflow hidden: ahí el pie
     * no cabe y no debe renderizarse.
     */
    public function test_el_pie_no_aparece_en_la_venta_de_mesa(): void
    {
        $area = Area::create(['name' => 'Salón', 'active' => true]);
        $table = Table::create(['name' => 'Mesa 1', 'area_id' => $area->id, 'active' => true]);

        $this->actingAs($this->user)->get(route('pos.order', $table->id))
            ->assertOk()
            ->assertDontSee('KaYu SAC');
    }
}
