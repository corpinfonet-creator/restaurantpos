<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Table;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\InventoryLog;
use App\Models\Client;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PosController extends Controller
{
    public function index()
    {
        // El mismo filtro y orden que usa el sub-sidebar del layout para pintar
        // las pestañas. Si ambos no coincidieran, la pestaña activa por defecto
        // (la primera) no correspondería al panel de mesas mostrado.
        $areas = Area::where('active', true)->orderBy('id')->with(['tables' => function($q) {
            $q->where('active', true);
            $q->with(['orders' => function($q) {
                $q->where('status', 'pending')->with('details');
            }, 'reservations' => function($q) {
                $q->where('status', 'confirmed')
                  ->whereDate('reservation_time', Carbon::today())
                  ->where('reservation_time', '>=', Carbon::now()->subHours(2))
                  ->orderBy('reservation_time', 'asc');
            }]);
        }])->get();

        $currency = Setting::get('currency_symbol', 'S/');
        return view('pos.index', compact('areas', 'currency'));
    }

    public function order(Table $table)
    {
        // --- AQUÍ ESTÁ EL FILTRO MÁGICO ---
        // Solo traemos productos que estén ACTIVOS y SEAN VENDIBLES (is_saleable = true)
        $categories = Category::with(['products' => function($q) {
            $q->where('is_active', true)
              ->where('is_saleable', true); // <--- ESTO OCULTA LA CARNE
        }])->where('is_active', true)->get();

        $order = Order::where('table_id', $table->id)->where('status', 'pending')->with('details.product')->first();
        $occupiedTableIds = Order::where('status', 'pending')->pluck('table_id');
        $freeTables = Table::where('active', true)->whereNotIn('id', $occupiedTableIds)->where('id', '!=', $table->id)->with('area')->get();
        $clients = Client::select('id', 'name', 'document_number')->orderBy('name')->get();
        $currency = Setting::get('currency_symbol', 'S/');

        return view('pos.order', compact('table', 'categories', 'order', 'freeTables', 'clients', 'currency'));
    }

    public function refreshCart(Table $table)
    {
        return $this->cartStateResponse($table);
    }

    public function addToOrder(Request $request, Table $table)
    {
        $product = Product::findOrFail($request->product_id);

        DB::transaction(function() use ($table, $product) {
            $order = Order::firstOrCreate(['table_id' => $table->id, 'status' => 'pending'], ['user_id' => auth()->id() ?? 1, 'total' => 0]);
            $detail = $order->details()->where('product_id', $product->id)->first();
            if ($detail) $detail->increment('quantity');
            else $order->details()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => $product->price, 'status' => 'pending']);
            $this->recalculateTotal($order);
        });

        return $this->cartStateResponse($table);
    }

    public function updateQuantity(Request $request, OrderDetail $detail)
    {
        $request->validate(['quantity' => 'required|integer']);
        $table = $detail->order->table;
        $newQty = $request->quantity;
        if ($newQty < 1) {
            $order = $detail->order;
            $detail->delete();
            $this->recalculateTotal($order);
            $this->closeIfEmpty($order);
        } else {
            $detail->update(['quantity' => $newQty]);
            $this->recalculateTotal($detail->order);
        }
        return $this->cartStateResponse($table);
    }

    public function updateNote(Request $request, OrderDetail $detail)
    {
        $table = $detail->order->table;
        $detail->update(['note' => $request->note]);
        return $this->cartStateResponse($table);
    }

    public function removeItem(OrderDetail $detail)
    {
        $order = $detail->order;
        $table = $order->table;
        $detail->delete();
        $this->recalculateTotal($order);
        $this->closeIfEmpty($order);
        return $this->cartStateResponse($table);
    }

    // Vacía la cuenta pendiente de la mesa de una sola vez ("Limpiar"). Es el
    // equivalente a borrar los items uno por uno: se apoya en los mismos
    // helpers (recalculateTotal + closeIfEmpty), así que la orden vacía se
    // elimina igual que al quitar el último producto a mano.
    public function clearOrder(Table $table)
    {
        $order = Order::where('table_id', $table->id)->where('status', 'pending')->first();

        if ($order) {
            DB::transaction(function () use ($order) {
                $order->details()->delete();
                $this->recalculateTotal($order);
                $this->closeIfEmpty($order);
            });
        }

        return $this->cartStateResponse($table);
    }

    public function applyDiscount(Request $request, Order $order)
    {
        // Solo cuentas abiertas: un descuento sobre una venta ya cobrada
        // alteraría el total de una operación cerrada y descuadraría la caja.
        if ($order->status !== 'pending') {
            return redirect()->back()->with('error', 'La cuenta ya está cerrada.');
        }

        // El subtotal real manda: sin este techo un descuento mayor que la
        // cuenta dejaba el total en 0 (recalculateTotal aplica max(0, ...)),
        // es decir, permitía regalar la venta desde el formulario.
        $subtotal = $order->details->sum(fn ($d) => $d->price * $d->quantity);

        $data = $request->validate([
            'discount' => ['nullable', 'numeric', 'min:0', 'max:' . $subtotal],
            'tip'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $order->discount = $data['discount'] ?? 0;
        $order->tip      = $data['tip'] ?? 0;
        $order->save();

        $this->recalculateTotal($order);

        return redirect()->back();
    }

    public function moveTable(Request $request, Order $order) {
        $request->validate(['target_table_id' => 'required|exists:tables,id']);
        if (Order::where('table_id', $request->target_table_id)->where('status', 'pending')->exists()) return redirect()->back()->with('error', 'Ocupada.');
        $order->table_id = $request->target_table_id; $order->save();
        return redirect()->route('pos.order', $request->target_table_id);
    }

    public function getSplitContent(Order $order) { return view('pos.partials.split_content', compact('order')); }

    public function processSplit(Request $request, Order $order)
    {
        $request->validate([
            'selected_items' => 'required|array|min:1',
            'selected_items.*' => 'exists:order_details,id',
            'payment_method' => 'required|in:cash,card',
        ]);

        if ($order->status !== 'pending') {
            return redirect()->route('pos.index')->with('error', 'Orden cerrada.');
        }

        $details = $order->details()->whereIn('id', $request->selected_items)->with('product')->get();
        if ($details->isEmpty()) {
            return redirect()->back()->with('error', 'No se encontraron los items seleccionados.');
        }

        $method = $request->input('payment_method', 'cash');
        $splitTotal = $details->sum(fn($d) => $d->price * $d->quantity);

        $splitOrder = null;

        DB::transaction(function () use ($order, $details, $method, $splitTotal, &$splitOrder) {
            $splitOrder = Order::create([
                'table_id' => $order->table_id,
                'user_id' => auth()->id() ?? 1,
                'client_id' => $order->client_id,
                'client_name' => $order->client_name,
                'client_document' => $order->client_document,
                'document_type' => $order->document_type ?? 'Ticket',
                'status' => 'completed',
                'total' => $splitTotal,
                'payment_method' => $method,
                'received_amount' => $splitTotal,
                'change_amount' => 0,
                'discount' => 0,
                'tip' => 0,
            ]);

            foreach ($details as $detail) {
                $splitOrder->details()->create([
                    'product_id' => $detail->product_id,
                    'quantity' => $detail->quantity,
                    'price' => $detail->price,
                    'status' => 'served',
                    'note' => $detail->note,
                ]);
                $this->deductInventory($detail->product, $detail->quantity, $splitOrder);
                $detail->delete();
            }

            $this->recalculateTotal($order);
            $this->closeIfEmpty($order);
        });

        return redirect()->route('pos.order', $order->table_id)->with('success', 'Cobro parcial registrado.')->with('printOrderId', $splitOrder->id);
    }

    public function precheck(Order $order) { $settings = Setting::all_cached(); return view('sales.ticket', compact('order', 'settings')); }
    public function kitchenTicket(Order $order) { return view('sales.kitchen_ticket', compact('order')); }

    public function checkout(Request $request, Order $order)
    {
        // Si la orden ya fue cobrada, esto casi siempre es un doble envío del
        // formulario (doble clic en "Confirmar Pago", o una pestaña con el
        // panel viejo). No es un error del usuario y la venta SÍ se registró:
        // se le devuelve su ticket en vez del confuso "Orden cerrada".
        if ($order->status === 'completed') {
            return redirect()->route('pos.index')
                ->with('success', 'Esta cuenta ya estaba cobrada.')
                ->with('printOrderId', $order->id);
        }

        if ($order->status !== 'pending') {
            return redirect()->route('pos.index')->with('error', 'Orden cerrada.');
        }

        $data = $request->validate([
            'payment_method'  => ['nullable', 'in:cash,card'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'document_type'   => ['nullable', 'string', 'max:50'],
            'client_id'       => ['nullable', 'exists:clients,id'],
            'client_name'     => ['nullable', 'string', 'max:255'],
            'client_document' => ['nullable', 'string', 'max:20'],
        ]);

        $method = $data['payment_method'] ?? 'cash';
        $received = $method === 'cash' ? ($data['received_amount'] ?? 0) : $order->total;
        $change = max(0, $received - $order->total);
        $clientId = $request->input('client_id');
        $client = $clientId ? Client::find($clientId) : null;
        $clientName = $client ? $client->name : ($request->input('client_name') ?: 'Público');

        $wasCharged = false;

        DB::transaction(function() use ($order, $method, $received, $change, $request, $client, $clientName, &$wasCharged) {
            // El paso a 'completed' se hace condicionado a que la fila siga en
            // 'pending'. Si dos peticiones simultáneas intentan cobrar la misma
            // cuenta, solo una actualiza filas (>0) y por tanto solo una
            // descuenta inventario: nunca se duplica el descuento de stock.
            $affected = Order::where('id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'payment_method' => $method,
                    'received_amount' => $received,
                    'change_amount' => $change,
                    'document_type' => $request->input('document_type', 'Ticket'),
                    'client_id' => $client?->id,
                    'client_name' => $clientName,
                    'client_document' => $request->input('client_document'),
                ]);

            if ($affected === 0) return;

            $wasCharged = true;
            $order->refresh();

            foreach($order->details as $detail) {
                $this->deductInventory($detail->product, $detail->quantity, $order);
                $detail->update(['status' => 'served']);
            }
        });

        return redirect()->route('pos.index')
            ->with('success', $wasCharged ? 'Venta registrada.' : 'Esta cuenta ya estaba cobrada.')
            ->with('printOrderId', $order->id);
    }

    private function deductInventory(Product $product, int $quantitySold, Order $order): void
    {
        $ingredients = $product->ingredients;

        if ($ingredients->count() > 0) {
            foreach ($ingredients as $ingredient) {
                $qtyToDeduct = $ingredient->pivot->quantity * $quantitySold;
                $oldStock = $ingredient->stock;
                $ingredient->decrement('stock', $qtyToDeduct);
                InventoryLog::create([
                    'product_id' => $ingredient->id,
                    'user_id' => Auth::id(),
                    'type' => 'sale',
                    'quantity' => -$qtyToDeduct,
                    'old_stock' => $oldStock,
                    'new_stock' => $oldStock - $qtyToDeduct,
                    'note' => 'Venta: ' . $product->name . ' (Orden #' . $order->id . ')'
                ]);
            }
        } elseif (!is_null($product->stock)) {
            $oldStock = $product->stock;
            $product->decrement('stock', $quantitySold);
            InventoryLog::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => 'sale',
                'quantity' => -$quantitySold,
                'old_stock' => $oldStock,
                'new_stock' => $oldStock - $quantitySold,
                'note' => 'Venta POS #' . $order->id
            ]);
        }
    }

    private function recalculateTotal(Order $order)
    {
        $order->refresh();
        $subtotal = $order->details->sum(fn($d) => $d->price * $d->quantity);
        $total = ($subtotal - ($order->discount ?? 0)) + ($order->tip ?? 0);
        $order->update(['total' => max(0, $total)]);
    }

    private function closeIfEmpty(Order $order): void
    {
        if ($order->status === 'pending' && $order->details()->count() === 0) {
            $order->delete();
        }
    }

    // Toda acción que muta el carrito responde SIEMPRE con el mismo payload:
    // el HTML del carrito y el del panel de acciones, ambos generados en la
    // misma petición y por lo tanto siempre coherentes entre sí.
    //
    // Antes el panel solo se mandaba cuando la orden se acababa de crear
    // ($order->wasRecentlyCreated). Eso dejaba el panel desincronizado en dos
    // casos muy frecuentes:
    //
    //   a) Dos clics rápidos sobre productos: el primero creaba la orden y su
    //      respuesta (la única que traía el panel) era ABORTADA en el cliente
    //      por el segundo clic. El segundo ya no creaba nada, así que respondía
    //      sin panel — el carrito mostraba los productos, pero el panel seguía
    //      con data-has-order="0" y sus formularios sin `action`: al pulsar
    //      "Cobrar" saltaba "Agrega al menos un producto...".
    //   b) Vaciar la mesa (closeIfEmpty borra la orden) y volver a agregar: el
    //      panel conservaba en su `action` el id de la orden ya eliminada o ya
    //      cobrada, y el checkout respondía "Orden cerrada".
    //
    // Mandando siempre ambos fragmentos, gane la carrera la petición que gane,
    // el DOM queda con el estado real y vigente de la mesa.
    private function cartStateResponse(Table $table)
    {
        return response()->json([
            'cartHtml' => $this->getCartHtml($table),
            'panelHtml' => $this->getActionPanelHtml($table),
        ]);
    }

    private function getCartHtml(Table $table)
    {
        $order = Order::where('table_id', $table->id)->where('status', 'pending')->with('details.product')->first();
        $clients = Client::select('id', 'name', 'document_number')->orderBy('name')->get();
        $currency = Setting::get('currency_symbol', 'S/');
        return view('pos.partials.cart', compact('order', 'clients', 'currency'))->render();
    }

    private function getActionPanelHtml(Table $table)
    {
        $order = Order::where('table_id', $table->id)->where('status', 'pending')->with('details.product')->first();
        $occupiedTableIds = Order::where('status', 'pending')->pluck('table_id');
        $freeTables = Table::where('active', true)->whereNotIn('id', $occupiedTableIds)->where('id', '!=', $table->id)->with('area')->get();
        $clients = Client::select('id', 'name', 'document_number')->orderBy('name')->get();
        $currency = Setting::get('currency_symbol', 'S/');
        return view('pos.partials.action_panel', compact('order', 'freeTables', 'clients', 'currency'))->render();
    }
}