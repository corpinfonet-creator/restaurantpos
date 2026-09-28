<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        // Listamos clientes con conteo de órdenes, con búsqueda y paginación
        $clients = Client::withCount('orders')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('document_number', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('clients.index', compact('clients', 'search'));
    }

    public function create()
    {
        return view('clients.create');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required', 'document_number' => 'nullable|unique:clients']);
        Client::create($request->all());
        return redirect()->route('clients.index')->with('success', 'Cliente registrado.');
    }

    // --- NUEVA FUNCIÓN: PERFIL 360 ---
    public function show(Client $client)
    {
        // 1. Historial de Órdenes (Completadas) — paginado para la tabla
        $orders = $client->orders()
                         ->with('table')
                         ->where('status', 'completed')
                         ->orderBy('created_at', 'desc')
                         ->paginate(8)
                         ->withQueryString();

        // 2. Estadísticas Financieras (sobre el total de órdenes, no solo la página actual)
        $totalSpent = $client->orders()->where('status', 'completed')->sum('total');
        $visitCount = $client->orders()->where('status', 'completed')->count();
        $lastVisitRaw = $client->orders()->where('status', 'completed')->orderByDesc('created_at')->value('created_at');
        $lastVisit = $lastVisitRaw ? \Carbon\Carbon::parse($lastVisitRaw) : null;
        $avgTicket = $visitCount > 0 ? $totalSpent / $visitCount : 0;

        // 3. Calcular Nivel VIP (con progreso hacia el siguiente nivel)
        $tiers = [
            'Nuevo'      => ['min' => 0,    'color' => 'secondary'],
            'Bronce'     => ['min' => 100,  'color' => 'danger'],
            'Plata'      => ['min' => 500,  'color' => 'secondary'],
            'Oro (VIP)'  => ['min' => 1000, 'color' => 'warning'],
        ];

        $rank = 'Nuevo';
        $badgeColor = 'secondary';
        foreach ($tiers as $name => $data) {
            if ($totalSpent >= $data['min']) {
                $rank = $name;
                $badgeColor = $data['color'];
            }
        }

        $tierNames = array_keys($tiers);
        $currentIndex = array_search($rank, $tierNames);
        $nextTier = $tierNames[$currentIndex + 1] ?? null;
        $nextTierMin = $nextTier ? $tiers[$nextTier]['min'] : null;
        $currentTierMin = $tiers[$rank]['min'];
        $tierProgress = $nextTierMin
            ? min(100, round((($totalSpent - $currentTierMin) / ($nextTierMin - $currentTierMin)) * 100))
            : 100;

        // 4. Plato Favorito (Query avanzada)
        $favoriteDish = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(order_details.quantity) as total_qty'))
            ->where('orders.client_id', $client->id)
            ->groupBy('products.name')
            ->orderByDesc('total_qty')
            ->first();

        $favoriteProduct = $favoriteDish ? $favoriteDish->name : null;
        $favoriteProductCount = $favoriteDish ? $favoriteDish->total_qty : 0;

        // 5. Gasto mensual (últimos 6 meses) para la mini-gráfica de tendencia
        $monthlySpend = $client->orders()
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as ym, SUM(total) as total')
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $trendLabels = [];
        $trendValues = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');
            $trendLabels[] = ucfirst($month->locale('es')->isoFormat('MMM'));
            $trendValues[] = $monthlySpend->has($key) ? (float) $monthlySpend[$key]->total : 0;
        }

        // 6. Moneda
        $currency = \App\Models\Setting::get('currency_symbol', 'S/');

        return view('clients.show', compact(
            'client', 'orders', 'totalSpent', 'visitCount', 'lastVisit', 'avgTicket',
            'rank', 'badgeColor', 'nextTier', 'nextTierMin', 'tierProgress',
            'favoriteProduct', 'favoriteProductCount',
            'trendLabels', 'trendValues', 'currency'
        ));
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $request->validate(['name' => 'required', 'document_number' => 'nullable|unique:clients,document_number,'.$client->id]);
        $client->update($request->all());
        return redirect()->route('clients.index')->with('success', 'Datos actualizados.');
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('clients.index')->with('success', 'Cliente eliminado.');
    }
}