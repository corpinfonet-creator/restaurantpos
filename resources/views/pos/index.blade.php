@extends('layouts.app')

@section('content')
<div class="tab-content pos-zones-content">
    @foreach($areas as $index => $area)
        <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" id="area-{{ $area->id }}" role="tabpanel">

            <div class="position-relative border rounded-3 shadow-sm bg-light"
                 style="height: 650px; overflow: auto; background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px;">

                    @foreach($area->tables as $table)
                        @php
                            $order = $table->orders->first(); // Orden activa
                            $reservations = $table->reservations; // TODAS las reservas confirmadas de hoy

                            $isBusy = $order ? true : false;
                            $hasReservations = $reservations->count() > 0;

                            // Estado de cocina de la orden activa, en 3 fases:
                            // - waiting:  todos los platos siguen "pending" (enviado, cocina aún no empezó)
                            // - cooking:  al menos un plato está "cooking" (preparando)
                            // - ready:    todos los platos están "served" (listo para servir/cobrar)
                            $kitchenState = null;
                            if ($isBusy && $order->details->isNotEmpty()) {
                                if ($order->details->every(fn($d) => $d->status === 'served')) {
                                    $kitchenState = 'ready';
                                } elseif ($order->details->contains('status', 'cooking')) {
                                    $kitchenState = 'cooking';
                                } else {
                                    $kitchenState = 'waiting';
                                }
                            }

                            // Estilo del estado (halo detrás de la foto de la mesa)
                            $statusClass = $isBusy ? 'is-busy' : ($hasReservations ? 'is-reserved' : 'is-free');
                        @endphp

                        <a href="{{ route('pos.order', $table->id) }}" class="text-decoration-none text-dark">
                            <div class="pos-table-card position-absolute d-flex flex-column align-items-center justify-content-between {{ $statusClass }}"
                                 style="width: 132px; height: 196px;
                                        left: {{ $table->x_pos }}px;
                                        top: {{ $table->y_pos }}px;
                                        transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);">

                                <div class="pos-table-name text-center">
                                    <span class="fw-bold text-uppercase">{{ $table->name }}</span>
                                </div>

                                <div class="flex-grow-1 d-flex align-items-center justify-content-center position-relative w-100">
                                    <div class="monitor-wrap {{ $isBusy && $kitchenState ? 'has-kitchen-state' : '' }}">
                                        <img src="{{ $table->image ? asset('storage/'.$table->image) : asset('images/meza.webp') }}" alt="Mesa" class="pos-table-img" draggable="false">

                                        @if($isBusy && $kitchenState)
                                            <div class="kitchen-state kitchen-state-{{ $kitchenState }}" title="{{ ['waiting' => 'Enviado a cocina · en espera', 'cooking' => 'Cocina preparando', 'ready' => 'Listo para servir'][$kitchenState] }}">
                                                @if($kitchenState === 'waiting')
                                                    <svg class="kitchen-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M6 3h12M6 21h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                        <path d="M7 3c0 4 3.2 5.6 3.2 9s-3.2 5-3.2 9M17 3c0 4-3.2 5.6-3.2 9s3.2 5 3.2 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <circle class="kitchen-icon-grain" cx="12" cy="12.4" r="1.3" fill="currentColor"/>
                                                    </svg>
                                                @elseif($kitchenState === 'cooking')
                                                    <svg class="kitchen-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <ellipse cx="10.5" cy="14.5" rx="6.5" ry="3.4" stroke="currentColor" stroke-width="1.8"/>
                                                        <path d="M17 12.5L21.5 10.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                        <path class="kitchen-icon-steam steam-1" d="M8.5 8.5c0-1.2 1.4-1.4 1.4-2.6S8.5 3.8 8.5 2.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path class="kitchen-icon-steam steam-2" d="M12.5 8.5c0-1.2 1.4-1.4 1.4-2.6S12.5 3.8 12.5 2.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                @else
                                                    <i class="bi bi-check-circle-fill kitchen-icon-static"></i>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    @if($hasReservations && !$isBusy)
                                        <div class="position-absolute top-50 start-50 translate-middle badge bg-warning text-dark border border-dark shadow-sm" 
                                             style="font-size: 0.6rem; width: 100%; white-space: normal; line-height: 1.1; z-index: 2; max-height: 60px; overflow-y: auto;">
                                            
                                            @foreach($reservations as $res)
                                                <div class="{{ !$loop->last ? 'border-bottom border-dark pb-1 mb-1' : '' }}">
                                                    <i class="bi bi-clock-fill"></i> <strong>{{ $res->reservation_time->format('H:i') }}</strong>
                                                    <br>{{ Str::limit($res->client_name, 9) }}
                                                </div>
                                            @endforeach

                                        </div>
                                    @endif
                                </div>

                                <div class="w-100 text-center mt-1">
                                    @if($isBusy)
                                        <div class="badge bg-danger w-100 py-1 shadow-sm">
                                            <small style="font-size: 0.65rem;">CONSUMO</small><br>
                                            <span class="fs-6 fw-bold">{{ $currency ?? 'S/' }}{{ number_format($order->total, 2) }}</span>
                                        </div>
                                    @else
                                        @if($hasReservations)
                                            <div class="badge bg-warning text-dark w-100 py-2 shadow-sm border border-warning">
                                                {{ $reservations->count() }} RESERVA(S)
                                            </div>
                                        @else
                                            <div class="badge bg-success w-100 py-2 shadow-sm">
                                                LIBRE
                                            </div>
                                        @endif
                                    @endif
                                </div>

                            </div>
                        </a>
                    @endforeach

                </div>
            </div>
        @endforeach
</div>

<style>
    /* --- El canvas de mesas ocupa el ancho/alto completo de main-content,
       cancelando su padding para llegar hasta los bordes reales del panel.
       El scroll vive solo en el canvas interno: main-content no debe scrollear
       (evita el doble scroll anidado). --- */
    .main-content:has(> .pos-zones-content) { overflow: hidden; }
    .pos-zones-content { margin: -30px; }
    .pos-zones-content .tab-pane > div { border-radius: 0; border-width: 0 0 1px; height: calc(100vh - var(--topbar-height) - 24px) !important; }

    @media (max-width: 575px) {
        .pos-zones-content { margin: -16px; }
    }

    /* --- Tarjeta de mesa: foto real recortada visualmente sobre el fondo
       del canvas mediante mix-blend-mode, con halo de estado detrás. --- */
    .pos-table-card {
        z-index: 10;
        transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .pos-table-card:hover {
        transform: scale(1.08) translateY(-4px);
        z-index: 100 !important;
        cursor: pointer;
    }
    .pos-table-name {
        font-size: 0.75rem; color: #495057; margin-bottom: 2px;
    }
    /* Scrollbar invisible para el badge de reservas */
    .badge::-webkit-scrollbar { width: 0px; background: transparent; }

    /* --- Indicador de estado de cocina, incrustado sobre la foto de la mesa --- */
    .monitor-wrap {
        position: relative;
        width: 132px; height: 132px;
        display: flex; align-items: center; justify-content: center;
    }

    .pos-table-img {
        width: 100%; height: 100%; object-fit: contain;
        mix-blend-mode: multiply; pointer-events: none;
    }

    .kitchen-state {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        display: flex; align-items: center; justify-content: center;
        width: 26px; height: 26px;
        border-radius: 50%;
    }
    .pos-table-card.is-busy .kitchen-state { background: rgba(220,53,69,0.85); }
    .kitchen-icon { width: 15px; height: 15px; filter: drop-shadow(0 0 1.5px rgba(0,0,0,0.35)); }
    .kitchen-icon-static { font-size: 15px; line-height: 1; text-shadow: 0 0 1.5px rgba(0,0,0,0.35); }

    /* Los 3 estados usan ícono blanco sólido para máximo contraste sobre el rojo de la mesa ocupada */
    .kitchen-state-waiting,
    .kitchen-state-cooking,
    .kitchen-state-ready { color: #fff; }

    /* Fase 1: enviado, en espera — reloj de arena con leve balanceo */
    .kitchen-state-waiting .kitchen-icon {
        animation: kitchen-tilt 2.2s ease-in-out infinite;
        transform-origin: 50% 50%;
    }
    .kitchen-state-waiting .kitchen-icon-grain {
        animation: kitchen-grain-fall 1.6s ease-in-out infinite;
    }

    /* Fase 2: cocina preparando — vapor animado */
    .kitchen-state-cooking .kitchen-icon-steam { animation: kitchen-steam 1.4s ease-in-out infinite; }
    .kitchen-state-cooking .steam-2 { animation-delay: .35s; }

    @keyframes kitchen-tilt {
        0%, 100% { transform: rotate(0deg); }
        50%      { transform: rotate(8deg); }
    }
    @keyframes kitchen-grain-fall {
        0%   { opacity: 0; transform: translateY(-2px); }
        50%  { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(2px); }
    }
    @keyframes kitchen-steam {
        0%   { opacity: 0;   transform: translateY(2px) scaleY(0.8); }
        40%  { opacity: 1;   transform: translateY(-1px) scaleY(1); }
        100% { opacity: 0;   transform: translateY(-4px) scaleY(1.1); }
    }

    @media (prefers-reduced-motion: reduce) {
        .kitchen-state-waiting .kitchen-icon,
        .kitchen-state-waiting .kitchen-icon-grain,
        .kitchen-state-cooking .kitchen-icon-steam { animation: none; }
    }
</style>

@push('modals')
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable print-modal-dialog-narrow">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold mb-0" id="printModalTitle"><i class="bi bi-receipt me-2"></i>Documento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 print-modal-body">
                <div class="print-modal-loading">
                    <div class="spinner-border text-primary" role="status"></div>
                    <small class="text-muted mt-2">Cargando documento...</small>
                </div>
                <iframe id="printFrame" class="print-modal-iframe" title="Documento a imprimir" scrolling="no"></iframe>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary fw-bold" onclick="printModalFrame()"><i class="bi bi-printer-fill me-1"></i> Imprimir</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.openPrintModal = function (url, title) {
        const modalEl = document.getElementById('printModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const frame = document.getElementById('printFrame');
        const loading = modalEl.querySelector('.print-modal-loading');

        document.getElementById('printModalTitle').innerHTML = '<i class="bi bi-receipt me-2"></i>' + title;

        frame.style.height = '260px';
        loading.style.display = 'flex';
        frame.style.opacity = '0';
        frame.src = url;

        frame.onload = function () {
            loading.style.display = 'none';
            frame.style.opacity = '1';
            resizePrintFrame(frame);
        };

        modal.show();
    };

    // Ajusta el iframe a la altura REAL y COMPLETA de su contenido (nunca
    // scrollea por sí mismo). Si el ticket es largo, es el modal-body
    // (.print-modal-body, con overflow-y: auto) el único que scrollea —
    // así nunca hay dos scrollbars anidados.
    function resizePrintFrame(frame) {
        try {
            const doc = frame.contentDocument || frame.contentWindow.document;
            frame.style.height = doc.documentElement.scrollHeight + 'px';
        } catch (e) {
            // Si por algún motivo no se puede leer el documento (origen distinto), se deja el alto por defecto.
        }
    }

    window.printModalFrame = function () {
        const frame = document.getElementById('printFrame');
        if (frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        }
    };

    document.getElementById('printModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('printFrame').src = 'about:blank';
    });

    @if(session('printOrderId'))
        document.addEventListener('DOMContentLoaded', function () {
            openPrintModal("{{ route('sales.ticket', session('printOrderId')) }}", 'Ticket de Venta');
        });
    @endif
</script>

<style>
    .print-modal-dialog-narrow { max-width: 380px; }
    .print-modal-body {
        position: relative;
        min-height: 260px;
        max-height: 75vh;
        background: #f1f3f5;
        display: flex;
        justify-content: center;
        overflow-y: auto;
    }
    .print-modal-iframe {
        width: 100%;
        border: none;
        min-height: 260px;
        overflow: hidden;
        transition: opacity .15s ease;
        background: #fff;
    }
    .print-modal-loading {
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
@endsection