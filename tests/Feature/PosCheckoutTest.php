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

/**
 * Regresiones de los dos fallos reportados en el POS de mesas:
 *
 *  1. "Agrega al menos un producto a la mesa antes de continuar" saltaba al
 *     pulsar COBRAR aunque la mesa tuviera productos.
 *  2. "Orden cerrada" al cobrar una cuenta que todavía no se había cobrado.
 *
 * Ambos venían de lo mismo: el panel de acciones (con las rutas y el id de la
 * orden) solo se re-enviaba al navegador cuando la orden se acababa de crear,
 * así que quedaba desincronizado con el estado real de la mesa.
 */
class PosCheckoutTest extends TestCase
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
        $this->table = Table::create([
            'name' => 'Mesa 1',
            'area_id' => $area->id,
            'active' => true,
        ]);

        $category = Category::create(['name' => 'Bebidas', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'Gaseosa',
            'category_id' => $category->id,
            'price' => 10.00,
            'is_active' => true,
            'is_saleable' => true,
        ]);
    }

    private function addProduct(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->postJson(
            route('pos.add', $this->table->id),
            ['product_id' => $this->product->id]
        );
    }

    /**
     * BUG 1 — el caso que fallaba: dos productos agregados seguidos.
     *
     * El primer add creaba la orden y era el ÚNICO que devolvía el panel. El
     * segundo add ya no creaba nada y respondía solo el carrito, así que el
     * panel del navegador se quedaba con data-has-order="0" y formularios sin
     * `action`: al pulsar COBRAR saltaba el aviso con la mesa llena.
     *
     * Ahora TODA respuesta trae el panel, y siempre con la orden vigente.
     */
    public function test_cada_agregado_devuelve_el_panel_con_la_orden_vigente(): void
    {
        $first = $this->addProduct();
        $first->assertOk()->assertJsonStructure(['cartHtml', 'panelHtml']);

        // El segundo agregado NO crea la orden (ya existe): es exactamente el
        // que antes respondía sin panel.
        $second = $this->addProduct();
        $second->assertOk()->assertJsonStructure(['cartHtml', 'panelHtml']);

        $order = Order::where('table_id', $this->table->id)->where('status', 'pending')->firstOrFail();

        $panel = $second->json('panelHtml');
        $this->assertStringContainsString('data-has-order="1"', $panel);
        $this->assertStringContainsString(route('pos.checkout', $order->id), $panel);
    }

    /**
     * BUG 1 (variante) — vaciar la mesa y volver a llenarla.
     *
     * closeIfEmpty() borra la orden al quitar el último producto. El panel debe
     * volver a data-has-order="0", y al agregar de nuevo debe traer el id de la
     * NUEVA orden, no el de la borrada.
     */
    public function test_vaciar_y_volver_a_llenar_mantiene_el_panel_sincronizado(): void
    {
        $this->addProduct();
        $order = Order::where('table_id', $this->table->id)->where('status', 'pending')->firstOrFail();
        $detail = $order->details()->firstOrFail();

        $emptied = $this->actingAs($this->user)
            ->deleteJson(route('pos.remove', $detail->id));

        $emptied->assertOk();
        $this->assertStringContainsString('data-has-order="0"', $emptied->json('panelHtml'));
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);

        // Se vuelve a llenar: el panel debe apuntar a la nueva orden.
        $refilled = $this->addProduct();
        $newOrder = Order::where('table_id', $this->table->id)->where('status', 'pending')->firstOrFail();

        $this->assertNotSame($order->id, $newOrder->id);
        $panel = $refilled->json('panelHtml');
        $this->assertStringContainsString('data-has-order="1"', $panel);
        $this->assertStringContainsString(route('pos.checkout', $newOrder->id), $panel);
        $this->assertStringNotContainsString(route('pos.checkout', $order->id), $panel);
    }

    /** El refresco del carrito devuelve el mismo payload que las mutaciones. */
    public function test_refresh_del_carrito_devuelve_carrito_y_panel(): void
    {
        $this->addProduct();

        $this->actingAs($this->user)
            ->getJson(route('pos.cart.refresh', $this->table->id))
            ->assertOk()
            ->assertJsonStructure(['cartHtml', 'panelHtml'])
            ->assertJsonFragment([]);
    }

    /** BUG 2 — una cuenta pendiente se cobra sin decir "Orden cerrada". */
    public function test_se_puede_cobrar_una_cuenta_pendiente(): void
    {
        $this->addProduct();
        $order = Order::where('table_id', $this->table->id)->where('status', 'pending')->firstOrFail();

        $response = $this->actingAs($this->user)->post(route('pos.checkout', $order->id), [
            'payment_method' => 'cash',
            'received_amount' => 20,
            'document_type' => 'Ticket',
        ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHas('success', 'Venta registrada.');
        $response->assertSessionMissing('error');

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertEquals(10.00, (float) $order->total);
        $this->assertEquals(10.00, (float) $order->change_amount);
    }

    /**
     * BUG 2 — doble envío del formulario de cobro.
     *
     * Antes el segundo envío contestaba "Orden cerrada", que el usuario leía
     * como un fallo pese a que la venta SÍ se había registrado. Ahora se le
     * devuelve su ticket con un mensaje claro, y el inventario no se descuenta
     * dos veces.
     */
    public function test_cobrar_dos_veces_no_reporta_error_ni_duplica_el_descuento(): void
    {
        $this->product->update(['stock' => 5]);

        $this->addProduct();
        $order = Order::where('table_id', $this->table->id)->where('status', 'pending')->firstOrFail();

        $payload = [
            'payment_method' => 'cash',
            'received_amount' => 10,
            'document_type' => 'Ticket',
        ];

        $this->actingAs($this->user)->post(route('pos.checkout', $order->id), $payload)
            ->assertSessionHas('success', 'Venta registrada.');

        $this->assertEquals(4, (int) $this->product->fresh()->stock);

        $second = $this->actingAs($this->user)->post(route('pos.checkout', $order->id), $payload);

        $second->assertRedirect(route('pos.index'));
        $second->assertSessionMissing('error');
        $second->assertSessionHas('success', 'Esta cuenta ya estaba cobrada.');
        $second->assertSessionHas('printOrderId', $order->id);

        // El stock NO se descuenta una segunda vez.
        $this->assertEquals(4, (int) $this->product->fresh()->stock);
    }
}
