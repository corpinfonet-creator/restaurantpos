@extends('layouts.app')

@section('content')
<div class="container-fluid client-profile-page">

    <div class="d-flex align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0">Perfil de Cliente</h2>
            <p class="text-muted mb-0">Historial, preferencias y valor del cliente</p>
        </div>
    </div>

    <div class="row g-4">

        {{-- ===== TARJETA PRINCIPAL ===== --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 client-hero-card">
                <div class="card-body text-center">
                    <div class="client-avatar-xl mx-auto mb-3">
                        {{ substr($client->name, 0, 1) }}
                        <span class="badge rounded-pill bg-{{ $badgeColor }} client-rank-badge">{{ $rank }}</span>
                    </div>

                    <h4 class="fw-bold mb-1">{{ $client->name }}</h4>
                    <p class="text-muted small mb-3">
                        <i class="bi bi-geo-alt-fill text-danger"></i> {{ $client->address ?? 'Sin dirección registrada' }}
                    </p>

                    @if($nextTier)
                        <div class="mb-4 text-start">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted fw-bold">Progreso a {{ $nextTier }}</span>
                                <span class="fw-bold text-{{ $badgeColor }}">{{ $tierProgress }}%</span>
                            </div>
                            <div class="client-tier-progress">
                                <div class="client-tier-progress-bar bg-{{ $badgeColor }}" style="width: {{ $tierProgress }}%"></div>
                            </div>
                            <small class="text-muted">Faltan {{ $currency }}{{ number_format(max(0, $nextTierMin - $totalSpent), 2) }} para el siguiente nivel</small>
                        </div>
                    @else
                        <div class="alert alert-warning py-2 small mb-4">
                            <i class="bi bi-trophy-fill me-1"></i> ¡Nivel máximo alcanzado!
                        </div>
                    @endif

                    <div class="client-info-list text-start">
                        <div class="client-info-row">
                            <span><i class="bi bi-credit-card-2-front text-muted me-2"></i>Documento</span>
                            <strong>{{ $client->document_number ?? '—' }}</strong>
                        </div>
                        <div class="client-info-row">
                            <span><i class="bi bi-telephone text-muted me-2"></i>Teléfono</span>
                            <strong>{{ $client->phone ?? '—' }}</strong>
                        </div>
                        <div class="client-info-row">
                            <span><i class="bi bi-envelope text-muted me-2"></i>Email</span>
                            <strong class="text-truncate" style="max-width: 160px;" title="{{ $client->email }}">{{ $client->email ?? '—' }}</strong>
                        </div>
                        <div class="client-info-row">
                            <span><i class="bi bi-calendar-check text-muted me-2"></i>Cliente desde</span>
                            <strong>{{ $client->created_at->format('d/m/Y') }}</strong>
                        </div>
                        <div class="client-info-row">
                            <span><i class="bi bi-clock-history text-muted me-2"></i>Última visita</span>
                            <strong>{{ $lastVisit ? $lastVisit->diffForHumans() : 'Sin visitas' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">

            {{-- ===== KPIs ===== --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="dash-kpi">
                        <i class="bi bi-cash-stack dash-kpi-watermark text-primary"></i>
                        <div class="dash-kpi-icon bg-primary-soft text-primary mb-2"><i class="bi bi-cash-stack"></i></div>
                        <span class="dash-kpi-label">Total Gastado</span>
                        <span class="dash-kpi-value fs-4">{{ $currency }}{{ number_format($totalSpent, 2) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="dash-kpi">
                        <i class="bi bi-receipt dash-kpi-watermark text-success"></i>
                        <div class="dash-kpi-icon bg-success-soft text-success mb-2"><i class="bi bi-receipt"></i></div>
                        <span class="dash-kpi-label">Visitas</span>
                        <span class="dash-kpi-value fs-4">{{ $visitCount }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="dash-kpi">
                        <i class="bi bi-ticket-perforated dash-kpi-watermark text-warning"></i>
                        <div class="dash-kpi-icon bg-warning-soft text-warning mb-2"><i class="bi bi-ticket-perforated"></i></div>
                        <span class="dash-kpi-label">Ticket Promedio</span>
                        <span class="dash-kpi-value fs-4">{{ $currency }}{{ number_format($avgTicket, 2) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="dash-kpi">
                        <i class="bi bi-egg-fried dash-kpi-watermark text-info"></i>
                        <div class="dash-kpi-icon bg-info-soft text-info mb-2"><i class="bi bi-egg-fried"></i></div>
                        <span class="dash-kpi-label">Plato Favorito</span>
                        @if($favoriteProduct)
                            <span class="fw-bold text-truncate d-block" title="{{ $favoriteProduct }}">{{ $favoriteProduct }}</span>
                            <small class="text-muted">{{ $favoriteProductCount }} veces</small>
                        @else
                            <span class="text-muted small">Aún sin datos</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===== TENDENCIA DE GASTO ===== --}}
            <div class="card border-0 shadow-sm dash-card mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Tendencia de Consumo (6 meses)</h6>
                </div>
                <div class="card-body">
                    <div style="height: 180px;">
                        <canvas id="clientTrendChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- ===== HISTORIAL DE PEDIDOS ===== --}}
            <div class="card border-0 shadow-sm dash-card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Historial de Pedidos</h6>
                    <span class="badge bg-light text-dark border">{{ $visitCount }} en total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Fecha</th>
                                    <th>Folio</th>
                                    <th>Mesa</th>
                                    <th class="text-end pe-4">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                    <tr>
                                        <td class="ps-4">
                                            {{ $order->created_at->format('d/m/Y') }} <br>
                                            <small class="text-muted">{{ $order->created_at->format('H:i') }}</small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</span></td>
                                        <td>{{ $order->table->name ?? 'Barra' }}</td>
                                        <td class="text-end pe-4 fw-bold text-success">{{ $currency }}{{ number_format($order->total, 2) }}</td>
                                        <td class="text-end pe-2">
                                            <button type="button" class="btn btn-sm btn-link text-dark" title="Ver / Imprimir ticket" onclick="openTicketModal({{ $order->id }})">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="dash-empty-state">
                                                <i class="bi bi-receipt"></i>
                                                <h6 class="fw-bold text-dark mb-1 mt-3">Sin pedidos aún</h6>
                                                <small class="text-muted">Este cliente todavía no tiene historial de compras.</small>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($orders->hasPages())
                        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                            <small class="text-muted">
                                Mostrando {{ $orders->firstItem() }}–{{ $orders->lastItem() }} de {{ $orders->total() }}
                            </small>
                            {{ $orders->onEachSide(1)->links('pagination.client-orders') }}
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

@push('modals')
<div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable ticket-modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Ticket de Venta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 ticket-modal-body">
                <div class="ticket-modal-loading">
                    <div class="spinner-border text-primary" role="status"></div>
                    <small class="text-muted mt-2">Cargando ticket...</small>
                </div>
                <iframe id="ticketFrame" class="ticket-modal-iframe" title="Ticket de venta"></iframe>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary fw-bold" onclick="printTicketModal()"><i class="bi bi-printer-fill me-1"></i> Imprimir</button>
            </div>
        </div>
    </div>
</div>

<script>
    const ticketUrlBase = @json(url('/sales'));

    window.openTicketModal = function (orderId) {
        const modalEl = document.getElementById('ticketModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const frame = document.getElementById('ticketFrame');
        const loading = modalEl.querySelector('.ticket-modal-loading');

        loading.style.display = 'flex';
        frame.style.opacity = '0';
        frame.src = `${ticketUrlBase}/${orderId}/ticket`;

        frame.onload = function () {
            loading.style.display = 'none';
            frame.style.opacity = '1';
        };

        modal.show();
    };

    window.printTicketModal = function () {
        const frame = document.getElementById('ticketFrame');
        if (frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        }
    };

    document.getElementById('ticketModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('ticketFrame').src = 'about:blank';
    });
</script>

<style>
    .ticket-modal-dialog { max-width: 380px; }
    .ticket-modal-body {
        position: relative;
        min-height: 260px;
        background: #f1f3f5;
        display: flex;
        justify-content: center;
        overflow-y: auto;
    }
    .ticket-modal-iframe {
        width: 100%;
        border: none;
        min-height: 260px;
        transition: opacity .15s ease;
        background: #fff;
    }
    .ticket-modal-loading {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #f1f3f5;
        z-index: 1;
    }
</style>
@endpush

<style>
    .client-profile-page .client-hero-card { border-radius: 18px; }

    .client-profile-page .client-avatar-xl {
        width: 96px; height: 96px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0d6efd, #3d8bfd);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 2.4rem; font-weight: 800;
        box-shadow: 0 8px 20px rgba(13,110,253,0.3);
        position: relative;
    }
    .client-profile-page .client-rank-badge {
        position: absolute;
        bottom: -6px; right: -10px;
        font-size: 0.7rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        border: 2px solid #fff;
    }

    .client-profile-page .client-tier-progress {
        height: 8px;
        background: #f1f3f5;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 4px;
    }
    .client-profile-page .client-tier-progress-bar {
        height: 100%;
        border-radius: 6px;
        transition: width .3s ease;
    }

    .client-profile-page .client-info-list { border-top: 1px solid #f0f1f3; padding-top: 12px; }
    .client-profile-page .client-info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f4f5f7;
        font-size: 0.85rem;
    }
    .client-profile-page .client-info-row:last-child { border-bottom: none; }
    .client-profile-page .client-info-row span { color: #6c757d; }

    .client-profile-page .dash-kpi {
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 14px;
        padding: 16px;
        height: 100%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        position: relative;
        overflow: hidden;
        isolation: isolate;
        display: flex;
        flex-direction: column;
    }
    .client-profile-page .dash-kpi-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
    }
    .client-profile-page .bg-primary-soft { background: rgba(13,110,253,0.1); }
    .client-profile-page .bg-success-soft { background: rgba(25,135,84,0.1); }
    .client-profile-page .bg-warning-soft { background: rgba(255,193,7,0.15); }
    .client-profile-page .bg-info-soft { background: rgba(13,202,240,0.12); }
    .client-profile-page .dash-kpi-watermark {
        position: absolute;
        top: 50%; right: -14px;
        transform: translateY(-50%);
        font-size: 4.5rem;
        opacity: 0.07;
        z-index: -1;
        pointer-events: none;
    }
    .client-profile-page .dash-kpi-label {
        font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        color: #8891a0; letter-spacing: .3px;
    }
    .client-profile-page .dash-kpi-value { font-weight: 800; color: #1c2333; }

    .client-profile-page .dash-card .card-header { border-bottom: 1px solid #f0f1f3; }

    .client-profile-page .dash-empty-state {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .client-profile-page .dash-empty-state i {
        font-size: 2.25rem; color: #adb5bd; background: #f1f3f5;
        width: 64px; height: 64px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const currency = @json($currency);
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#6c757d';

    const ctx = document.getElementById('clientTrendChart');
    if (ctx) {
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 180);
        gradient.addColorStop(0, 'rgba(13, 110, 253, 0.3)');
        gradient.addColorStop(1, 'rgba(13, 110, 253, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($trendLabels),
                datasets: [{
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
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1c2333',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: { label: (c) => ' ' + currency + Number(c.parsed.y).toLocaleString('es-PE', {minimumFractionDigits:2}) }
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
