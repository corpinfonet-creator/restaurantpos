<div class="col-md-6 col-lg-4 col-xl-3" data-order-wrapper="{{ $order->id }}">
    <div class="card h-100 shadow-sm border-0 kds-card urgency-normal" data-order-id="{{ $order->id }}" data-created-at="{{ $order->created_at->toIso8601String() }}">
        <div class="card-header d-flex justify-content-between align-items-center py-3 {{ $order->details->contains('status', 'cooking') ? 'bg-warning text-dark' : 'bg-danger text-white' }}">
            <div>
                <h5 class="fw-bold mb-0">Mesa: {{ $order->table->name ?? 'Mesa' }}</h5>
                <small>Folio #{{ $order->id }}</small>
            </div>
            <div class="text-end">
                <i class="bi bi-clock-history"></i>
                <span class="d-block fw-bold kds-timer">0:00</span>
            </div>
        </div>

        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @foreach($order->details as $detail)
                    <li class="list-group-item kds-item py-3 {{ $detail->status == 'served' ? 'is-served' : '' }}" data-detail-id="{{ $detail->id }}">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-secondary rounded-pill me-2 fs-6">{{ $detail->quantity }}</span>
                            <span class="fw-bold {{ $detail->status == 'served' ? 'text-decoration-line-through text-muted' : '' }}">
                                {{ $detail->product->name }}
                            </span>
                        </div>

                        @if($detail->note)
                            <div class="mt-1">
                                <span class="badge bg-warning text-dark border border-dark-subtle note-badge">
                                    <i class="bi bi-exclamation-circle-fill"></i> {{ $detail->note }}
                                </span>
                            </div>
                        @endif

                        <div class="mt-2">
                            @if($detail->status == 'pending')
                                <button type="button" class="btn btn-sm btn-danger w-100" data-action="advance" data-detail-id="{{ $detail->id }}">
                                    <i class="bi bi-play-fill"></i> Empezar
                                </button>
                            @elseif($detail->status == 'cooking')
                                <button type="button" class="btn btn-sm btn-warning w-100" data-action="advance" data-detail-id="{{ $detail->id }}">
                                    <i class="bi bi-check-lg"></i> Listo
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="revert" data-detail-id="{{ $detail->id }}" title="Deshacer">
                                    <i class="bi bi-arrow-counterclockwise"></i> Deshacer
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
