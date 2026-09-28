<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    // Pantalla principal del KDS
    public function index()
    {
        $orders = $this->pendingOrdersQuery()->get();

        return view('kitchen.index', compact('orders'));
    }

    // Polling AJAX: devuelve solo el estado de los pedidos (sin recargar la página)
    public function poll()
    {
        $orders = $this->pendingOrdersQuery()->get();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'orders' => $orders->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'table' => $order->table->name ?? 'Mesa',
                    'created_at' => $order->created_at->toIso8601String(),
                    'details' => $order->details->map(fn (OrderDetail $d) => [
                        'id' => $d->id,
                        'product' => $d->product->name,
                        'quantity' => $d->quantity,
                        'note' => $d->note,
                        'status' => $d->status,
                    ]),
                ];
            }),
        ]);
    }

    // Avanzar estado del plato: Pendiente -> Cocinando -> Servido
    public function updateStatus(Request $request, OrderDetail $detail)
    {
        $next = [
            'pending' => 'cooking',
            'cooking' => 'served',
        ][$detail->status] ?? null;

        if ($next) {
            $detail->update(['status' => $next]);
        }

        if ($request->wantsJson()) {
            return response()->json(['id' => $detail->id, 'status' => $detail->status]);
        }

        return redirect()->route('kitchen.index');
    }

    // Retroceder estado por error del cocinero (Servido -> Cocinando -> Pendiente)
    public function revertStatus(Request $request, OrderDetail $detail)
    {
        $prev = [
            'served' => 'cooking',
            'cooking' => 'pending',
        ][$detail->status] ?? null;

        if ($prev) {
            $detail->update(['status' => $prev]);
        }

        if ($request->wantsJson()) {
            return response()->json(['id' => $detail->id, 'status' => $detail->status]);
        }

        return redirect()->route('kitchen.index');
    }

    private function pendingOrdersQuery()
    {
        // Solo órdenes abiertas (pending): una orden ya cobrada o cancelada
        // nunca debe volver a aparecer en la pantalla de cocina.
        // La tarjeta se retira del tablero solo cuando TODOS sus platos están servidos,
        // pero mientras tanto muestra también los ya servidos (tachados, con opción
        // de deshacer) para que el cocinero vea el progreso completo del pedido.
        return Order::where('status', 'pending')
            ->whereHas('details', function ($q) {
                $q->whereIn('status', ['pending', 'cooking']);
            })
            ->with(['table', 'details' => function ($q) {
                $q->with('product')->orderByRaw("FIELD(status, 'pending', 'cooking', 'served')");
            }])
            ->orderBy('created_at', 'asc');
    }
}
