@extends('layouts.app')

@section('content')
<div class="container-fluid dash-page">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <span class="dash-eyebrow" id="dashGreeting">Buen día</span>
            <h2 class="fw-bold text-dark mb-0">Panel de Control</h2>
            <p class="text-muted mb-0">{{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd D [de] MMMM, YYYY') }}</p>
        </div>
        <a href="{{ route('pos.index') }}" class="btn btn-primary btn-lg shadow-sm fw-bold px-4">
            <i class="bi bi-lightning-charge-fill me-2"></i> Ir al POS
        </a>
    </div>

    {{-- ===== KPI CARDS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="dash-kpi">
                <i class="bi bi-cash-stack dash-kpi-watermark text-primary"></i>
                <div class="dash-kpi-top">
                    <div class="dash-kpi-icon bg-primary-soft text-primary"><i class="bi bi-cash-stack"></i></div>
                    <span class="dash-kpi-chip {{ $salesGrowth >= 0 ? 'chip-up' : 'chip-down' }}">
                        <i class="bi bi-arrow-{{ $salesGrowth >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($salesGrowth) }}%
                    </span>
                </div>
                <span class="dash-kpi-label">Venta de Hoy</span>
                <span class="dash-kpi-value">{{ $currency }}{{ number_format($totalSalesToday, 2) }}</span>
                <span class="dash-kpi-sub">{{ $ordersCountToday }} órdenes cerradas</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="dash-kpi">
                <i class="bi bi-shop dash-kpi-watermark text-success"></i>
                <div class="dash-kpi-top">
                    <div class="dash-kpi-icon bg-success-soft text-success"><i class="bi bi-shop"></i></div>
                    <span class="dash-kpi-chip chip-neutral">{{ $totalTables }} mesas</span>
                </div>
                <span class="dash-kpi-label">Mesas Atendiendo</span>
                <span class="dash-kpi-value">{{ $activeTables }}</span>
                <span class="dash-kpi-sub">Clientes activos ahora</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="dash-kpi">
                <i class="bi bi-ticket-perforated dash-kpi-watermark text-warning"></i>
                <div class="dash-kpi-top">
                    <div class="dash-kpi-icon bg-warning-soft text-warning"><i class="bi bi-ticket-perforated"></i></div>
                    <span class="dash-kpi-chip {{ $ordersGrowth >= 0 ? 'chip-up' : 'chip-down' }}">
                        <i class="bi bi-arrow-{{ $ordersGrowth >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($ordersGrowth) }}%
                    </span>
                </div>
                <span class="dash-kpi-label">Ticket Promedio</span>
                <span class="dash-kpi-value">{{ $currency }}{{ number_format($avgTicketToday, 2) }}</span>
                <span class="dash-kpi-sub">por orden, hoy</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="dash-kpi">
                <i class="bi bi-person-plus dash-kpi-watermark text-info"></i>
                <div class="dash-kpi-top">
                    <div class="dash-kpi-icon bg-info-soft text-info"><i class="bi bi-person-plus"></i></div>
                    <span class="dash-kpi-chip chip-neutral"><i class="bi bi-calendar3"></i> mes</span>
                </div>
                <span class="dash-kpi-label">Clientes Nuevos</span>
                <span class="dash-kpi-value">{{ $newClients }}</span>
                <span class="dash-kpi-sub">registrados este mes</span>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- ===== TENDENCIA DE VENTAS ===== --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm dash-card h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Evolución de Ventas</h5>
                    <span class="badge bg-light text-dark border">Últimos 14 días</span>
                </div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== VENTAS POR CATEGORÍA HOY ===== --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm dash-card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Categorías Hoy</h5>
                </div>
                <div class="card-body d-flex flex-column">
                    <div style="height: 220px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    @if($categoryToday->isEmpty())
                        <div class="text-center text-muted py-4 flex-grow-1 d-flex flex-column justify-content-center">
                            <i class="bi bi-clipboard-x fs-2 opacity-50"></i>
                            <small class="mt-2">Aún no hay ventas hoy</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- ===== ACTIVIDAD POR HORA ===== --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm dash-card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-activity text-primary me-2"></i>Actividad de Hoy por Hora</h5>
                </div>
                <div class="card-body">
                    <div style="height: 260px;">
                        <canvas id="hourChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== TOP PRODUCTOS ===== --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm dash-card h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Platos Más Vendidos</h5>
                    <span class="badge bg-light text-dark border">30 días</span>
                </div>
                <div class="card-body">
                    @forelse($topProducts as $product)
                        <div class="dash-product-row">
                            @if($product->image)
                                <img src="{{ asset('storage/'.$product->image) }}" class="dash-product-img" alt="">
                            @else
                                <div class="dash-product-img d-flex align-items-center justify-content-center bg-light text-muted"><i class="bi bi-egg-fried"></i></div>
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-truncate">{{ $product->name }}</span>
                                    <span class="text-success fw-bold small ms-2">{{ $currency }}{{ number_format($product->revenue, 0) }}</span>
                                </div>
                                <div class="dash-progress mt-1">
                                    <div class="dash-progress-bar" style="width: {{ $maxQty > 0 ? ($product->total_qty / $maxQty * 100) : 0 }}%"></div>
                                </div>
                                <small class="text-muted">{{ $product->total_qty }} unidades vendidas</small>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">Sin datos suficientes</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- ===== STOCK CRÍTICO ===== --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm dash-card">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-box-seam text-danger me-2"></i> Reponer Inventario Urgente</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Producto</th>
                                    <th>Categoría</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lowStockProducts as $prod)
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark">{{ $prod->name }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ $prod->category->name ?? '-' }}</span></td>
                                        <td class="text-center">
                                            <span class="badge bg-danger fs-6">{{ $prod->stock }}</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form action="{{ route('products.adjust', $prod->id) }}" method="POST" class="d-flex justify-content-end gap-1">
                                                @csrf
                                                <input type="hidden" name="type" value="add">
                                                <input type="number" name="quantity" class="form-control form-control-sm" style="width: 70px;" placeholder="Cant." required>
                                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-plus-lg"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="dash-empty-state">
                                                <i class="bi bi-box-seam"></i>
                                                <h6 class="fw-bold text-dark mb-1 mt-3">Inventario al día</h6>
                                                <small class="text-muted">No hay productos por reponer en este momento.</small>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== ACTIVIDAD RECIENTE ===== --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm dash-card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Actividad Reciente</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentOrders as $order)
                            <li class="list-group-item d-flex align-items-center py-3 border-0 border-bottom">
                                <div class="dash-timeline-dot"></div>
                                <div class="flex-grow-1 ms-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold small">{{ $order->table->name ?? 'Barra' }}</span>
                                        <span class="text-success fw-bold small">{{ $currency }}{{ number_format($order->total, 2) }}</span>
                                    </div>
                                    <small class="text-muted">{{ $order->user->name ?? '—' }} · {{ $order->created_at->diffForHumans() }}</small>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted py-4 border-0">Sin actividad reciente</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .dash-page .dash-eyebrow {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #0d6efd;
    }

    .dash-page .dash-kpi {
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 16px;
        padding: 18px 20px;
        height: 100%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        transition: transform .15s ease, box-shadow .15s ease;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
        isolation: isolate;
    }
    .dash-page .dash-kpi:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 24px rgba(0,0,0,0.08);
    }
    .dash-page .dash-kpi-watermark {
        position: absolute;
        top: 50%;
        right: -18px;
        transform: translateY(-50%);
        font-size: 6.5rem;
        line-height: 1;
        opacity: 0.08;
        z-index: -1;
        pointer-events: none;
    }
    .dash-page .dash-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .dash-page .dash-kpi-icon {
        width: 46px; height: 46px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
    }
    .dash-page .bg-primary-soft { background: rgba(13,110,253,0.1); }
    .dash-page .bg-success-soft { background: rgba(25,135,84,0.1); }
    .dash-page .bg-warning-soft { background: rgba(255,193,7,0.15); }
    .dash-page .bg-info-soft { background: rgba(13,202,240,0.12); }
    .dash-page .dash-kpi-chip {
        font-size: 0.72rem;
        font-weight: 800;
        padding: 3px 9px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }
    .dash-page .chip-up { background: rgba(25,135,84,0.12); color: #198754; }
    .dash-page .chip-down { background: rgba(220,53,69,0.12); color: #dc3545; }
    .dash-page .chip-neutral { background: #f1f3f5; color: #6c757d; }
    .dash-page .dash-kpi-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #8891a0;
        letter-spacing: .3px;
    }
    .dash-page .dash-kpi-value {
        font-size: 1.65rem;
        font-weight: 800;
        color: #1c2333;
        line-height: 1.3;
    }
    .dash-page .dash-kpi-sub { font-size: 0.78rem; color: #a3aab5; }

    .dash-page .dash-card .card-header { border-bottom: 1px solid #f0f1f3; }

    .dash-page .dash-product-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f4f5f7;
    }
    .dash-page .dash-product-row:last-child { border-bottom: none; }
    .dash-page .dash-product-img {
        width: 44px; height: 44px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
        font-size: 1.1rem;
    }
    .dash-page .dash-progress {
        height: 5px;
        background: #f1f3f5;
        border-radius: 4px;
        overflow: hidden;
    }
    .dash-page .dash-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #0d6efd, #4dabf7);
        border-radius: 4px;
    }
    .dash-page .min-w-0 { min-width: 0; }

    .dash-page .dash-timeline-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        background: #0d6efd;
        flex-shrink: 0;
        box-shadow: 0 0 0 4px rgba(13,110,253,0.12);
    }

    .dash-page .dash-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .dash-page .dash-empty-state i {
        font-size: 2.75rem;
        color: #adb5bd;
        background: #f1f3f5;
        width: 76px; height: 76px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const currency = @json($currency);
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#6c757d';

    function fmtMoney(v) {
        return currency + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Saludo dinámico según la hora
    const hour = new Date().getHours();
    const greetingEl = document.getElementById('dashGreeting');
    if (greetingEl) {
        greetingEl.textContent = hour < 12 ? 'Buenos días' : (hour < 19 ? 'Buenas tardes' : 'Buenas noches');
    }

    // ===== TENDENCIA DE VENTAS (línea con gradiente + órdenes en eje secundario) =====
    const ctxSales = document.getElementById('salesChart');
    if (ctxSales) {
        const gradient = ctxSales.getContext('2d').createLinearGradient(0, 0, 0, 320);
        gradient.addColorStop(0, 'rgba(13, 110, 253, 0.35)');
        gradient.addColorStop(1, 'rgba(13, 110, 253, 0)');

        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: @json($chartLabels),
                datasets: [
                    {
                        label: 'Ventas (' + currency + ')',
                        data: @json($chartValues),
                        borderColor: '#0d6efd',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0d6efd',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Órdenes',
                        data: @json($chartOrders),
                        borderColor: '#adb5bd',
                        borderDash: [4, 4],
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        pointRadius: 0,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 10, usePointStyle: true } },
                    tooltip: {
                        backgroundColor: '#1c2333',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (ctx) => ctx.dataset.label.includes('Órdenes')
                                ? ' Órdenes: ' + ctx.parsed.y
                                : ' Ventas: ' + fmtMoney(ctx.parsed.y)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f1f3' },
                        ticks: { callback: (v) => currency + v }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { display: false },
                        ticks: { precision: 0 }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // ===== CATEGORÍAS HOY (dona) =====
    const ctxCat = document.getElementById('categoryChart');
    if (ctxCat) {
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: @json($categoryToday->pluck('name')),
                datasets: [{
                    data: @json($categoryToday->pluck('total')),
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6610f2', '#0dcaf0', '#fd7e14'],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 11 } } },
                    tooltip: {
                        backgroundColor: '#1c2333',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + fmtMoney(ctx.parsed) }
                    }
                }
            }
        });
    }

    // ===== ACTIVIDAD POR HORA (barras con intensidad) =====
    const ctxHour = document.getElementById('hourChart');
    if (ctxHour) {
        const hourValues = @json($hourValues);
        const maxVal = Math.max(...hourValues, 1);
        const bg = hourValues.map(v => `rgba(13, 110, 253, ${0.25 + (v / maxVal) * 0.65})`);

        new Chart(ctxHour, {
            type: 'bar',
            data: {
                labels: @json($hourLabels),
                datasets: [{
                    label: 'Ventas',
                    data: hourValues,
                    backgroundColor: bg,
                    borderRadius: 6,
                    maxBarThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1c2333',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: { label: (ctx) => ' ' + fmtMoney(ctx.parsed.y) }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f0f1f3' }, ticks: { callback: (v) => currency + v } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
})();
</script>

@includeIf('products.create_modal')

@endsection
