<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\Client;
use App\Models\Table;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ===== 1. KPIs DE HOY (con comparativa vs. ayer) =====
        $totalSalesToday = Order::whereDate('created_at', $today)
                                ->where('status', 'completed')
                                ->sum('total');

        $totalSalesYesterday = Order::whereDate('created_at', $yesterday)
                                ->where('status', 'completed')
                                ->sum('total');

        $salesGrowth = $totalSalesYesterday > 0
            ? round((($totalSalesToday - $totalSalesYesterday) / $totalSalesYesterday) * 100, 1)
            : ($totalSalesToday > 0 ? 100 : 0);

        $ordersCountToday = Order::whereDate('created_at', $today)
                                 ->where('status', 'completed')
                                 ->count();

        $ordersCountYesterday = Order::whereDate('created_at', $yesterday)
                                 ->where('status', 'completed')
                                 ->count();

        $ordersGrowth = $ordersCountYesterday > 0
            ? round((($ordersCountToday - $ordersCountYesterday) / $ordersCountYesterday) * 100, 1)
            : ($ordersCountToday > 0 ? 100 : 0);

        $avgTicketToday = $ordersCountToday > 0 ? $totalSalesToday / $ordersCountToday : 0;

        $activeTables = Order::where('status', 'pending')->count();
        $totalTables = Table::where('active', true)->count();

        $newClients = Client::whereMonth('created_at', Carbon::now()->month)->count();

        // ===== 2. GRÁFICO DE VENTAS (Últimos 14 días, hoy vs. mismos días de la semana previa referencia visual) =====
        $startDate = Carbon::now()->subDays(13)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $salesData = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(id) as orders_count')
            )
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('date');

        $chartLabels = [];
        $chartValues = [];
        $chartOrders = [];

        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i);
            $dateString = $day->format('Y-m-d');
            $chartLabels[] = ucfirst($day->locale('es')->isoFormat('D MMM'));
            $chartValues[] = $salesData->has($dateString) ? (float) $salesData[$dateString]->total : 0;
            $chartOrders[] = $salesData->has($dateString) ? (int) $salesData[$dateString]->orders_count : 0;
        }

        // ===== 3. ACTIVIDAD POR HORA (HOY) =====
        $hourlyToday = Order::select(DB::raw('HOUR(created_at) as hour'), DB::raw('SUM(total) as total'))
            ->where('status', 'completed')
            ->whereDate('created_at', $today)
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $hourLabels = [];
        $hourValues = [];
        for ($h = 8; $h <= 23; $h++) {
            $hourLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $hourValues[] = $hourlyToday->has($h) ? (float) $hourlyToday[$h]->total : 0;
        }

        // ===== 4. VENTAS POR CATEGORÍA (HOY) =====
        $categoryToday = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('SUM(order_details.quantity * order_details.price) as total'))
            ->where('orders.status', 'completed')
            ->whereDate('orders.created_at', $today)
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        // ===== 5. PRODUCTOS MÁS VENDIDOS (TOP 5, últimos 30 días) =====
        $topProducts = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->select('products.id', 'products.name', 'products.image', DB::raw('SUM(order_details.quantity) as total_qty'), DB::raw('SUM(order_details.quantity * order_details.price) as revenue'))
            ->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('products.id', 'products.name', 'products.image')
            ->orderByDesc('total_qty')
            ->limit(4)
            ->get();

        $maxQty = $topProducts->max('total_qty') ?: 1;

        // ===== 6. ALERTAS DE STOCK CRÍTICO =====
        // with('category'): la tabla de alertas muestra la categoría de cada
        // producto. Sin esto Eloquent la pedía de una en una (N+1): con diez
        // productos en alerta eran diez consultas extra en cada carga del panel.
        $lowStockProducts = Product::with('category')
            ->whereNotNull('stock')
            ->where('stock', '<=', 10)
            ->where('is_active', true)
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get();

        // ===== 7. ÚLTIMAS ÓRDENES (actividad reciente) =====
        $recentOrders = Order::with(['table', 'user'])
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        // ===== 8. Moneda =====
        $currency = \App\Models\Setting::get('currency_symbol', 'S/');

        return view('dashboard', compact(
            'totalSalesToday', 'ordersCountToday', 'newClients',
            'salesGrowth', 'ordersGrowth', 'avgTicketToday',
            'activeTables', 'totalTables',
            'chartLabels', 'chartValues', 'chartOrders',
            'hourLabels', 'hourValues',
            'categoryToday',
            'topProducts', 'maxQty',
            'lowStockProducts', 'recentOrders', 'currency'
        ));
    }
}
