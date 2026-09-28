<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Filtro de Fechas (Default: Inicio de mes hasta hoy)
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $completedOrders = Order::where('status', 'completed')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        // ===== KPIs GENERALES =====
        $kpiOrdersQuery = clone $completedOrders;
        $totalRevenue = (clone $kpiOrdersQuery)->sum('total');
        $totalOrders = (clone $kpiOrdersQuery)->count();
        $avgTicket = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $itemsSoldQuery = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->whereDate('orders.created_at', '>=', $startDate)
            ->whereDate('orders.created_at', '<=', $endDate);
        $totalItemsSold = (clone $itemsSoldQuery)->sum('order_details.quantity');

        // Comparativa contra el periodo anterior (mismo rango de días, inmediatamente antes)
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $prevStart = Carbon::parse($startDate)->subDays($periodDays)->format('Y-m-d');
        $prevEnd = Carbon::parse($startDate)->subDay()->format('Y-m-d');

        $prevRevenue = Order::where('status', 'completed')
            ->whereDate('created_at', '>=', $prevStart)
            ->whereDate('created_at', '<=', $prevEnd)
            ->sum('total');

        $revenueGrowth = $prevRevenue > 0
            ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : ($totalRevenue > 0 ? 100 : 0);

        // ===== 1. VENTAS POR CATEGORÍA (Gráfico de Dona) =====
        $salesByCategory = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('SUM(order_details.quantity * order_details.price) as total'))
            ->where('orders.status', 'completed')
            ->whereDate('orders.created_at', '>=', $startDate)
            ->whereDate('orders.created_at', '<=', $endDate)
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        $catLabels = $salesByCategory->pluck('name');
        $catValues = $salesByCategory->pluck('total');

        // ===== 2. RENDIMIENTO DE PERSONAL (Gráfico de Barras) =====
        $salesByWaiter = Order::select('users.name', DB::raw('SUM(total) as total_sales'), DB::raw('COUNT(orders.id) as orders_count'))
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->where('orders.status', 'completed')
            ->whereDate('orders.created_at', '>=', $startDate)
            ->whereDate('orders.created_at', '<=', $endDate)
            ->groupBy('users.name')
            ->orderByDesc('total_sales')
            ->get();

        $waiterLabels = $salesByWaiter->pluck('name');
        $waiterValues = $salesByWaiter->pluck('total_sales');

        // ===== 3. TENDENCIA DE VENTAS (Línea, por día en el rango) =====
        $dailySales = Order::select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(id) as orders_count')
            )
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $trendLabels = [];
        $trendValues = [];
        $trendOrders = [];
        $cursor = Carbon::parse($startDate);
        $limitDate = Carbon::parse($endDate);
        while ($cursor->lte($limitDate)) {
            $key = $cursor->format('Y-m-d');
            $trendLabels[] = $cursor->format('d/m');
            $trendValues[] = $dailySales->has($key) ? (float) $dailySales[$key]->total : 0;
            $trendOrders[] = $dailySales->has($key) ? (int) $dailySales[$key]->orders_count : 0;
            $cursor->addDay();
        }

        // ===== 4. MÉTODOS DE PAGO =====
        $paymentBreakdown = Order::select('payment_method', DB::raw('SUM(total) as total'), DB::raw('COUNT(id) as count'))
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('payment_method')
            ->get();

        $paymentLabels = $paymentBreakdown->map(function ($p) {
            return $p->payment_method === 'cash' ? 'Efectivo' : 'Tarjeta';
        });
        $paymentValues = $paymentBreakdown->pluck('total');

        // ===== 5. VENTAS POR HORA (Heatmap simplificado - barras) =====
        $salesByHour = Order::select(DB::raw('HOUR(created_at) as hour'), DB::raw('SUM(total) as total'), DB::raw('COUNT(id) as count'))
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $hourLabels = [];
        $hourValues = [];
        for ($h = 8; $h <= 23; $h++) {
            $hourLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $hourValues[] = $salesByHour->has($h) ? (float) $salesByHour[$h]->total : 0;
        }

        // ===== 6. TOP 5 PLATOS MÁS VENDIDOS =====
        $topProducts = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->select('products.name', DB::raw('SUM(order_details.quantity) as qty'), DB::raw('SUM(order_details.quantity * order_details.price) as revenue'))
            ->where('orders.status', 'completed')
            ->whereDate('orders.created_at', '>=', $startDate)
            ->whereDate('orders.created_at', '<=', $endDate)
            ->groupBy('products.name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        // ===== 7. TOP 5 PLATOS MENOS VENDIDOS =====
        $worstProducts = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->select('products.name', DB::raw('SUM(order_details.quantity) as qty'))
            ->where('orders.status', 'completed')
            ->whereDate('orders.created_at', '>=', $startDate)
            ->whereDate('orders.created_at', '<=', $endDate)
            ->groupBy('products.name')
            ->orderBy('qty', 'asc')
            ->limit(5)
            ->get();

        // Moneda
        $currency = \App\Models\Setting::get('currency_symbol', 'S/');

        return view('reports.index', compact(
            'startDate', 'endDate',
            'catLabels', 'catValues',
            'waiterLabels', 'waiterValues',
            'topProducts', 'worstProducts', 'salesByWaiter', 'currency',
            'totalRevenue', 'totalOrders', 'avgTicket', 'totalItemsSold', 'revenueGrowth',
            'trendLabels', 'trendValues', 'trendOrders',
            'paymentLabels', 'paymentValues',
            'hourLabels', 'hourValues'
        ));
    }
}
