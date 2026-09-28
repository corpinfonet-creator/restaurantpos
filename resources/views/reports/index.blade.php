@extends('layouts.app')

@section('content')
<div class="container-fluid reports-page">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Reportes Gerenciales</h2>
            <p class="text-muted mb-0">Análisis dinámico de rendimiento del negocio</p>
        </div>

        <form action="{{ route('reports.index') }}" method="GET" class="d-flex align-items-end gap-2 bg-white p-2 rounded-3 shadow-sm border">
            <div>
                <label class="small text-muted fw-bold mb-1 d-block">Desde</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
            </div>
            <div>
                <label class="small text-muted fw-bold mb-1 d-block">Hasta</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
            </div>
            <button class="btn btn-primary btn-sm fw-bold px-3">
                <i class="bi bi-filter-circle me-1"></i> Analizar
            </button>
        </form>
    </div>

    {{-- ===== KPI CARDS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="kpi-card">
                <div class="kpi-icon bg-primary-soft text-primary"><i class="bi bi-cash-stack"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Ingresos Totales</span>
                    <span class="kpi-value">{{ $currency }}{{ number_format($totalRevenue, 2) }}</span>
                    <span class="kpi-trend {{ $revenueGrowth >= 0 ? 'text-success' : 'text-danger' }}">
                        <i class="bi bi-arrow-{{ $revenueGrowth >= 0 ? 'up' : 'down' }}-short"></i>
                        {{ abs($revenueGrowth) }}% vs periodo anterior
                    </span>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi-card">
                <div class="kpi-icon bg-success-soft text-success"><i class="bi bi-receipt"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Órdenes Completadas</span>
                    <span class="kpi-value">{{ number_format($totalOrders) }}</span>
                    <span class="kpi-trend text-muted"><i class="bi bi-check2-circle"></i> en el periodo</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi-card">
                <div class="kpi-icon bg-warning-soft text-warning"><i class="bi bi-ticket-perforated"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Ticket Promedio</span>
                    <span class="kpi-value">{{ $currency }}{{ number_format($avgTicket, 2) }}</span>
                    <span class="kpi-trend text-muted"><i class="bi bi-calculator"></i> por orden</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi-card">
                <div class="kpi-icon bg-info-soft text-info"><i class="bi bi-basket3"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Platos Vendidos</span>
                    <span class="kpi-value">{{ number_format($totalItemsSold) }}</span>
                    <span class="kpi-trend text-muted"><i class="bi bi-egg-fried"></i> unidades</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TENDENCIA DE VENTAS ===== --}}
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm chart-card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold mb-0"><i class="bi bi-graph-up text-primary me-2"></i>Tendencia de Ventas</h5>
                    <span class="badge bg-light text-dark border">{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</span>
                </div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm chart-card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Ingresos por Categoría</h5>
                </div>
                <div class="card-body">
                    <div style="height: 280px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm chart-card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-credit-card-2-front-fill text-primary me-2"></i>Métodos de Pago</h5>
                </div>
                <div class="card-body">
                    <div style="height: 280px;">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm chart-card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Ranking de Personal</h5>
                </div>
                <div class="card-body">
                    <div style="height: 280px;">
                        <canvas id="waiterChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm chart-card">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Ventas por Hora del Día</h5>
                </div>
                <div class="card-body">
                    <div style="height: 260px;">
                        <canvas id="hourChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white py-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-trophy-fill me-2"></i> Top 5: Platos Estrella</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Producto</th>
                                <th class="text-center">Cant. Vendida</th>
                                <th class="text-end pe-3">Ingresos Generados</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topProducts as $prod)
                                <tr>
                                    <td class="ps-3 fw-bold">{{ $prod->name }}</td>
                                    <td class="text-center"><span class="badge bg-success rounded-pill">{{ $prod->qty }}</span></td>
                                    <td class="text-end pe-3 text-success fw-bold">{{ $currency }}{{ number_format($prod->revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Sin datos</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark py-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-exclamation-circle-fill me-2"></i> Ojo: Menos Vendidos</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Producto</th>
                                <th class="text-end pe-3">Cant. Vendida</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($worstProducts as $prod)
                                <tr>
                                    <td class="ps-3 text-secondary">{{ $prod->name }}</td>
                                    <td class="text-end pe-3 fw-bold">{{ $prod->qty }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">Sin datos</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .reports-page .kpi-card {
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 14px;
        padding: 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        height: 100%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .reports-page .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.07);
    }
    .reports-page .kpi-icon {
        width: 52px; height: 52px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .reports-page .bg-primary-soft { background: rgba(13,110,253,0.1); }
    .reports-page .bg-success-soft { background: rgba(25,135,84,0.1); }
    .reports-page .bg-warning-soft { background: rgba(255,193,7,0.15); }
    .reports-page .bg-info-soft { background: rgba(13,202,240,0.12); }
    .reports-page .kpi-body { display: flex; flex-direction: column; min-width: 0; }
    .reports-page .kpi-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #8891a0; letter-spacing: .3px; }
    .reports-page .kpi-value { font-size: 1.5rem; font-weight: 800; color: #1c2333; line-height: 1.25; }
    .reports-page .kpi-trend { font-size: 0.75rem; font-weight: 600; }
    .reports-page .chart-card .card-header { border-bottom: 1px solid #f0f1f3; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const currency = @json($currency);
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = '#6c757d';

    function fmtMoney(v) {
        return currency + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ===== 1. TENDENCIA DE VENTAS (Línea con gradiente) =====
    const ctxTrend = document.getElementById('trendChart');
    if (ctxTrend) {
        const gradient = ctxTrend.getContext('2d').createLinearGradient(0, 0, 0, 320);
        gradient.addColorStop(0, 'rgba(13, 110, 253, 0.35)');
        gradient.addColorStop(1, 'rgba(13, 110, 253, 0)');

        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: @json($trendLabels),
                datasets: [{
                    label: 'Ventas (' + currency + ')',
                    data: @json($trendValues),
                    borderColor: '#0d6efd',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#0d6efd',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1c2333',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (ctx) => 'Ventas: ' + fmtMoney(ctx.parsed.y)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f1f3' },
                        ticks: { callback: (v) => currency + v }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // ===== 2. Gráfico de Categorías (Dona) =====
    const ctxCat = document.getElementById('categoryChart');
    if (ctxCat) {
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: @json($catLabels),
                datasets: [{
                    data: @json($catValues),
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6610f2', '#0dcaf0', '#fd7e14', '#20c997'],
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
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 } } },
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

    // ===== 3. Métodos de Pago (Dona) =====
    const ctxPay = document.getElementById('paymentChart');
    if (ctxPay) {
        new Chart(ctxPay, {
            type: 'doughnut',
            data: {
                labels: @json($paymentLabels),
                datasets: [{
                    data: @json($paymentValues),
                    backgroundColor: ['#198754', '#0d6efd'],
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
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } },
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

    // ===== 4. Gráfico de Mozos (Barras horizontales) =====
    const ctxWait = document.getElementById('waiterChart');
    if (ctxWait) {
        new Chart(ctxWait, {
            type: 'bar',
            data: {
                labels: @json($waiterLabels),
                datasets: [{
                    label: 'Ventas Totales',
                    data: @json($waiterValues),
                    backgroundColor: 'rgba(25, 135, 84, 0.85)',
                    borderRadius: 6,
                    maxBarThickness: 28
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1c2333',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: { label: (ctx) => ' ' + fmtMoney(ctx.parsed.x) }
                    }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#f0f1f3' }, ticks: { callback: (v) => currency + v } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // ===== 5. Ventas por Hora (Barras) =====
    const ctxHour = document.getElementById('hourChart');
    if (ctxHour) {
        const hourValues = @json($hourValues);
        const maxVal = Math.max(...hourValues, 1);
        const bg = hourValues.map(v => {
            const intensity = v / maxVal;
            return `rgba(13, 110, 253, ${0.25 + intensity * 0.65})`;
        });

        new Chart(ctxHour, {
            type: 'bar',
            data: {
                labels: @json($hourLabels),
                datasets: [{
                    label: 'Ventas',
                    data: hourValues,
                    backgroundColor: bg,
                    borderRadius: 6,
                    maxBarThickness: 36
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
@endsection
