<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La pantalla de venta en mesa renderiza y conserva los hooks del JS. */
class PosOrderPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Table $table;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        $area = Area::create(['name' => 'Salón', 'active' => true]);
        $this->table = Table::create(['name' => 'Mesa 37', 'area_id' => $area->id, 'active' => true]);
        $cat = Category::create(['name' => 'Bebidas', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'Inca Kola', 'category_id' => $cat->id, 'price' => 8.50,
            'is_active' => true, 'is_saleable' => true,
        ]);
    }

    public function test_la_pantalla_de_mesa_renderiza_con_buscador(): void
    {
        $r = $this->actingAs($this->user)->get(route('pos.order', $this->table->id));
        $r->assertOk();
        $r->assertSee('productSearch', false);          // buscador
        $r->assertSee('btnClearCart', false);           // botón limpiar
        $r->assertSee('Mesa 37');
        $r->assertSee('Inca Kola');
        $r->assertSee('data-name="inca kola"', false);  // índice de búsqueda
        $r->assertSee('cart-empty', false);             // estado vacío
    }

    public function test_los_hooks_del_carrito_siguen_presentes(): void
    {
        $this->actingAs($this->user)->postJson(
            route('pos.add', $this->table->id), ['product_id' => $this->product->id]
        )->assertOk();

        $r = $this->actingAs($this->user)->get(route('pos.order', $this->table->id));
        $r->assertOk();
        // Clases/ids de los que depende la lógica JS existente
        $r->assertSee('js-remove-item-form', false);
        $r->assertSee('js-update-qty-form', false);
        $r->assertSee('cart-line', false);
        $r->assertSee('cartTotalRow', false);
        $r->assertSee('data-order-total', false);
        $r->assertSee('data-item-count="1"', false);
    }

    public function test_limpiar_vacia_la_mesa_completa(): void
    {
        $this->actingAs($this->user)->postJson(route('pos.add', $this->table->id), ['product_id' => $this->product->id]);
        $this->actingAs($this->user)->postJson(route('pos.add', $this->table->id), ['product_id' => $this->product->id]);

        $order = Order::where('table_id', $this->table->id)->where('status', 'pending')->firstOrFail();
        $this->assertSame(1, $order->details()->count());

        $r = $this->actingAs($this->user)->deleteJson(route('pos.clear', $this->table->id));
        $r->assertOk()->assertJsonStructure(['cartHtml', 'panelHtml']);

        // La orden vacía se elimina, igual que al quitar el último item a mano.
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_details', ['order_id' => $order->id]);
        $this->assertStringContainsString('data-has-order="0"', $r->json('panelHtml'));
        $this->assertStringContainsString('data-item-count="0"', $r->json('cartHtml'));
    }

    public function test_limpiar_una_mesa_ya_vacia_no_falla(): void
    {
        $this->actingAs($this->user)
            ->deleteJson(route('pos.clear', $this->table->id))
            ->assertOk()
            ->assertJsonStructure(['cartHtml', 'panelHtml']);
    }

    /**
     * Nota de cocina: guardarla debe persistir y devolver el carrito ya con la
     * nota pintada. El fallo estaba en el front (el listener del modal nunca
     * se enganchaba porque el script corría antes de que existiera #noteModal),
     * así que aquí se cubre el contrato del endpoint que consume ese modal.
     */
    public function test_se_puede_guardar_una_nota_de_cocina(): void
    {
        $this->actingAs($this->user)->postJson(
            route('pos.add', $this->table->id), ['product_id' => $this->product->id]
        );

        $order = Order::where('table_id', $this->table->id)->where('status', 'pending')->firstOrFail();
        $detail = $order->details()->firstOrFail();

        $r = $this->actingAs($this->user)->postJson(
            route('pos.note', $detail->id), ['note' => 'Sin hielo, por favor']
        );

        $r->assertOk()->assertJsonStructure(['cartHtml', 'panelHtml']);

        $this->assertDatabaseHas('order_details', [
            'id' => $detail->id,
            'note' => 'Sin hielo, por favor',
        ]);

        // El carrito devuelto ya muestra la nota y marca el botón como "con nota".
        $this->assertStringContainsString('Sin hielo, por favor', $r->json('cartHtml'));
        $this->assertStringContainsString('has-note', $r->json('cartHtml'));
    }

    /** El botón de nota debe llevar el id del detalle que el modal necesita. */
    public function test_el_boton_de_nota_expone_el_id_del_detalle(): void
    {
        $this->actingAs($this->user)->postJson(
            route('pos.add', $this->table->id), ['product_id' => $this->product->id]
        );
        $detail = Order::where('table_id', $this->table->id)
            ->where('status', 'pending')->firstOrFail()->details()->firstOrFail();

        $r = $this->actingAs($this->user)->get(route('pos.order', $this->table->id));

        $r->assertOk();
        $r->assertSee('data-detail-id="' . $detail->id . '"', false);
        $r->assertSee('data-bs-target="#noteModal"', false);
        $r->assertSee('noteDetailId', false);
    }

    /** Se puede borrar una nota existente dejándola vacía. */
    public function test_se_puede_borrar_una_nota(): void
    {
        $this->actingAs($this->user)->postJson(
            route('pos.add', $this->table->id), ['product_id' => $this->product->id]
        );
        $detail = Order::where('table_id', $this->table->id)
            ->where('status', 'pending')->firstOrFail()->details()->firstOrFail();

        $this->actingAs($this->user)->postJson(route('pos.note', $detail->id), ['note' => 'Con ají']);
        $this->assertDatabaseHas('order_details', ['id' => $detail->id, 'note' => 'Con ají']);

        $this->actingAs($this->user)->postJson(route('pos.note', $detail->id), ['note' => ''])
            ->assertOk();

        $this->assertSame('', (string) $detail->fresh()->note);
    }

    /** El panel de cobro trae los 3 pasos y el campo único de DNI/RUC. */
    public function test_el_panel_de_cobro_esta_organizado_en_pasos(): void
    {
        $this->actingAs($this->user)->postJson(
            route('pos.add', $this->table->id), ['product_id' => $this->product->id]
        );

        $r = $this->actingAs($this->user)->get(route('pos.order', $this->table->id));
        $r->assertOk();

        // Paso 1: comprobante
        $r->assertSee('docTicket', false);
        $r->assertSee('docBoleta', false);
        $r->assertSee('docFactura', false);
        // Paso 2: documento único (DNI o RUC, hasta 11 dígitos) + consulta
        $r->assertSee('clientDoc', false);
        $r->assertSee('maxlength="11"', false);
        $r->assertSee('posDniSearchBtn', false);
        $r->assertSee('docKindBadge', false);
        $r->assertSee('clientAddressBox', false);
        // Paso 3: pago
        $r->assertSee('receivedAmount', false);
        $r->assertSee('cashQuickRow', false);
        $r->assertSee('changeAmount', false);

        // Los campos que consume el checkout no cambiaron de nombre
        $r->assertSee('name="document_type"', false);
        $r->assertSee('name="client_document"', false);
        $r->assertSee('name="client_name"', false);
        $r->assertSee('name="client_id"', false);
        $r->assertSee('name="payment_method"', false);
        $r->assertSee('name="received_amount"', false);
    }
}
