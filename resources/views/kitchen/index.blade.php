@extends('layouts.app')

@section('content')
<div class="container-fluid kds">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-fire text-danger me-2"></i>Monitor de Cocina (KDS)</h2>
            <p class="text-muted mb-0">Pedidos pendientes de preparación</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-white text-dark border legend-badge"><i class="bi bi-circle-fill text-danger me-1"></i> Pendiente</span>
            <span class="badge bg-white text-dark border legend-badge"><i class="bi bi-circle-fill text-warning me-1"></i> Preparando</span>
            <span id="conn-indicator" class="badge bg-success-subtle text-success-emphasis border border-success-subtle" title="Conectado al servidor">
                <i class="bi bi-wifi"></i> En línea
            </span>
            <div id="reloj" class="fw-bold fs-5 ms-2">00:00:00</div>
        </div>
    </div>

    <div id="kds-board" class="row g-3">
        @forelse($orders as $order)
            @include('kitchen.partials.order-card', ['order' => $order])
        @empty
            <div class="col-12 text-center py-5" id="kds-empty-state">
                <div class="opacity-50">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    <h2 class="mt-3 text-muted">Todo en orden, Chef.</h2>
                    <p>No hay pedidos pendientes en este momento.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>

<style>
    .kds-card { border-radius: 12px; overflow: hidden; transition: box-shadow .2s; }
    .kds-card .card-header { border: 0; }

    /* Urgencia por tiempo de espera, aplicada al borde izquierdo de la tarjeta */
    .kds-card.urgency-normal   { box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .kds-card.urgency-warning  { box-shadow: 0 0 0 2px #ffc107; }
    .kds-card.urgency-critical { box-shadow: 0 0 0 2px #dc3545; animation: kds-pulse 1.4s ease-in-out infinite; }

    @keyframes kds-pulse {
        0%, 100% { box-shadow: 0 0 0 2px #dc3545; }
        50%      { box-shadow: 0 0 0 4px rgba(220,53,69,.35); }
    }

    .kds-item { transition: background-color .3s; }
    .kds-item.is-served { background-color: #f1f8f2; }
    .kds-item .note-badge { max-width: 100%; white-space: normal; word-break: break-word; display: inline-block; text-align: left; }

    .kds-timer { font-variant-numeric: tabular-nums; }

    .legend-badge { font-weight: 500; }

    @media (prefers-reduced-motion: reduce) {
        .kds-card.urgency-critical { animation: none; }
    }
</style>

<script>
(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const board = document.getElementById('kds-board');
    const connIndicator = document.getElementById('conn-indicator');
    const pollUrl = @json(route('kitchen.poll'));
    const POLL_INTERVAL_MS = 6000;
    const WARNING_AFTER_MIN = 10;
    const CRITICAL_AFTER_MIN = 18;

    let knownOrderIds = new Set(
        Array.from(document.querySelectorAll('[data-order-id]')).map(el => el.dataset.orderId)
    );
    let serverTimeOffsetMs = 0; // server_time - client_now, para evitar depender del reloj local

    // --- Reloj digital ---
    setInterval(() => {
        const now = new Date(Date.now() + serverTimeOffsetMs);
        document.getElementById('reloj').innerText = now.toLocaleTimeString('es-PE', { hour12: false });
    }, 1000);

    // --- Cronómetros de cada tarjeta + nivel de urgencia ---
    function refreshTimers() {
        document.querySelectorAll('[data-created-at]').forEach(card => {
            const createdAt = new Date(card.dataset.createdAt);
            const now = new Date(Date.now() + serverTimeOffsetMs);
            const diffMs = Math.max(0, now - createdAt);
            const totalMinutes = Math.floor(diffMs / 60000);
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            const seconds = Math.floor((diffMs % 60000) / 1000);

            const timerEl = card.querySelector('.kds-timer');
            if (timerEl) {
                timerEl.textContent = hours > 0
                    ? `${hours}h ${String(minutes).padStart(2, '0')}m`
                    : `${minutes}:${String(seconds).padStart(2, '0')}`;
            }

            card.classList.remove('urgency-normal', 'urgency-warning', 'urgency-critical');
            if (totalMinutes >= CRITICAL_AFTER_MIN) {
                card.classList.add('urgency-critical');
            } else if (totalMinutes >= WARNING_AFTER_MIN) {
                card.classList.add('urgency-warning');
            } else {
                card.classList.add('urgency-normal');
            }
        });
    }
    setInterval(refreshTimers, 1000);
    refreshTimers();

    // --- Sonido de nuevo pedido ---
    function playNewOrderChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [880, 1175].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.001, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + i * 0.15 + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.15 + 0.35);
                osc.connect(gain).connect(ctx.destination);
                osc.start(ctx.currentTime + i * 0.15);
                osc.stop(ctx.currentTime + i * 0.15 + 0.4);
            });
        } catch (e) { /* Audio no soportado o bloqueado: silencioso, no crítico */ }
    }

    // --- Construcción de tarjeta desde JSON (para pedidos nuevos vía polling) ---
    function statusBadgeClass(order) {
        const hasCooking = order.details.some(d => d.status === 'cooking');
        return hasCooking ? 'bg-warning text-dark' : 'bg-danger text-white';
    }

    function itemRowHtml(orderId, item) {
        const isServed = item.status === 'served';
        const noteHtml = item.note ? `
            <div class="mt-1">
                <span class="badge bg-warning text-dark border border-dark-subtle note-badge">
                    <i class="bi bi-exclamation-circle-fill"></i> ${escapeHtml(item.note)}
                </span>
            </div>` : '';

        let actionHtml = '';
        if (item.status === 'pending') {
            actionHtml = `<button type="button" class="btn btn-sm btn-danger w-100" data-action="advance" data-detail-id="${item.id}"><i class="bi bi-play-fill"></i> Empezar</button>`;
        } else if (item.status === 'cooking') {
            actionHtml = `<button type="button" class="btn btn-sm btn-warning w-100" data-action="advance" data-detail-id="${item.id}"><i class="bi bi-check-lg"></i> Listo</button>`;
        } else {
            actionHtml = `<button type="button" class="btn btn-sm btn-outline-secondary" data-action="revert" data-detail-id="${item.id}" title="Deshacer"><i class="bi bi-arrow-counterclockwise"></i> Deshacer</button>`;
        }

        return `
        <li class="list-group-item kds-item py-3 ${isServed ? 'is-served' : ''}" data-detail-id="${item.id}">
            <div class="d-flex align-items-center">
                <span class="badge bg-secondary rounded-pill me-2 fs-6">${item.quantity}</span>
                <span class="fw-bold ${isServed ? 'text-decoration-line-through text-muted' : ''}">${escapeHtml(item.product)}</span>
            </div>
            ${noteHtml}
            <div class="mt-2">${actionHtml}</div>
        </li>`;
    }

    function orderCardHtml(order) {
        const itemsHtml = order.details.map(d => itemRowHtml(order.id, d)).join('');
        return `
        <div class="col-md-6 col-lg-4 col-xl-3" data-order-wrapper="${order.id}">
            <div class="card h-100 shadow-sm border-0 kds-card urgency-normal" data-order-id="${order.id}" data-created-at="${order.created_at}">
                <div class="card-header d-flex justify-content-between align-items-center py-3 ${statusBadgeClass(order)}">
                    <div>
                        <h5 class="fw-bold mb-0">Mesa: ${escapeHtml(order.table)}</h5>
                        <small>Folio #${order.id}</small>
                    </div>
                    <div class="text-end">
                        <i class="bi bi-clock-history"></i>
                        <span class="d-block fw-bold kds-timer">0:00</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">${itemsHtml}</ul>
                </div>
            </div>
        </div>`;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    // --- Acciones: avanzar / revertir estado de un plato (AJAX, sin recargar) ---
    function sendStatusChange(detailId, action) {
        const url = action === 'advance'
            ? `{{ url('/kitchen') }}/${detailId}/status`
            : `{{ url('/kitchen') }}/${detailId}/revert`;

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        }).then(r => {
            if (!r.ok) throw new Error('Request failed');
            return r.json();
        });
    }

    board.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const detailId = btn.dataset.detailId;
        const action = btn.dataset.action;
        btn.disabled = true;

        sendStatusChange(detailId, action)
            .then(() => fetchAndRender())
            .catch(() => { btn.disabled = false; });
    });

    // --- Polling: refresca el tablero completo sin parpadeo de página ---
    function fetchAndRender() {
        return fetch(pollUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => {
                if (!r.ok) throw new Error('poll failed');
                return r.json();
            })
            .then(data => {
                setConnected(true);
                serverTimeOffsetMs = new Date(data.server_time) - Date.now();

                const incomingIds = new Set(data.orders.map(o => String(o.id)));
                const isNewOrder = [...incomingIds].some(id => !knownOrderIds.has(id));
                if (isNewOrder && knownOrderIds.size > 0) {
                    playNewOrderChime();
                }
                knownOrderIds = incomingIds;

                if (data.orders.length === 0) {
                    board.innerHTML = `
                        <div class="col-12 text-center py-5" id="kds-empty-state">
                            <div class="opacity-50">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                                <h2 class="mt-3 text-muted">Todo en orden, Chef.</h2>
                                <p>No hay pedidos pendientes en este momento.</p>
                            </div>
                        </div>`;
                } else {
                    board.innerHTML = data.orders.map(orderCardHtml).join('');
                }
                refreshTimers();
            })
            .catch(() => setConnected(false));
    }

    function setConnected(ok) {
        connIndicator.className = ok
            ? 'badge bg-success-subtle text-success-emphasis border border-success-subtle'
            : 'badge bg-danger-subtle text-danger-emphasis border border-danger-subtle';
        connIndicator.innerHTML = ok
            ? '<i class="bi bi-wifi"></i> En línea'
            : '<i class="bi bi-wifi-off"></i> Sin conexión';
    }

    setInterval(fetchAndRender, POLL_INTERVAL_MS);
})();
</script>
@endsection
