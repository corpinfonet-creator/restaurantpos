@if($order)
    {{-- data-item-count lo lee el JS para el contador y el botón "Limpiar" --}}
    <div class="cart-lines" data-item-count="{{ $order->details->count() }}">
        @foreach($order->details as $detail)
            <div class="cart-line">
                <div class="cart-line-main">
                    <div class="cart-line-name">
                        <span class="text-truncate">{{ $detail->product->name }}</span>
                        <button type="button" class="cart-note-btn {{ $detail->note ? 'has-note' : '' }}" title="Nota de cocina"
                                data-bs-toggle="modal" data-bs-target="#noteModal"
                                data-detail-id="{{ $detail->id }}"
                                data-note-content="{{ $detail->note }}">
                            <i class="bi bi-sticky{{ $detail->note ? '-fill' : '' }}"></i>
                        </button>
                    </div>
                    <div class="cart-line-unit">{{ $currency ?? 'S/' }}{{ number_format($detail->price, 2) }} c/u</div>
                    @if($detail->note)
                        <div class="cart-line-note"><i class="bi bi-chat-left-text"></i> {{ $detail->note }}</div>
                    @endif
                </div>

                <div class="cart-line-side">
                    <div class="cart-line-amount">
                        {{ $currency ?? 'S/' }}{{ number_format($detail->price * $detail->quantity, 2) }}
                    </div>

                    <div class="cart-qty">
                        {{-- Los botones -/+ escriben en el mismo input de siempre y
                             disparan el submit del form existente: la lógica no cambia. --}}
                        <button type="button" class="cart-qty-btn js-qty-step" data-step="-1" aria-label="Quitar uno">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                        <form action="{{ route('pos.update', $detail->id) }}" method="POST" class="js-update-qty-form">
                            @csrf
                            <input type="number" name="quantity" value="{{ $detail->quantity }}"
                                   class="cart-qty-input" min="0" inputmode="numeric"
                                   onchange="this.form.requestSubmit()">
                        </form>
                        <button type="button" class="cart-qty-btn js-qty-step" data-step="1" aria-label="Agregar uno">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>

                    <form action="{{ route('pos.remove', $detail->id) }}" method="POST" class="js-remove-item-form">
                        @csrf
                        <button class="cart-remove-btn" title="Quitar producto">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <div class="cart-foot">
        <div class="cart-summary">
            <div class="cart-sum-row">
                <span>Subtotal</span>
                <span>{{ $currency ?? 'S/' }}{{ number_format($order->details->sum(fn($d) => $d->price * $d->quantity), 2) }}</span>
            </div>

            @if($order->discount > 0)
                <div class="cart-sum-row text-success">
                    <span><i class="bi bi-tag"></i> Descuento</span>
                    <span>-{{ $currency ?? 'S/' }}{{ number_format($order->discount, 2) }}</span>
                </div>
            @endif

            @if($order->tip > 0)
                <div class="cart-sum-row text-info">
                    <span><i class="bi bi-emoji-smile"></i> Propina</span>
                    <span>+{{ $currency ?? 'S/' }}{{ number_format($order->tip, 2) }}</span>
                </div>
            @endif

            <div class="cart-total" id="cartTotalRow" data-order-total="{{ $order->total }}">
                <span>Total</span>
                <span class="cart-total-value">{{ $currency ?? 'S/' }}{{ number_format($order->total, 2) }}</span>
            </div>
        </div>

        <div class="cart-actions">
            <button type="button" class="cart-mini-btn" onclick="openActionPanel('options')">
                <i class="bi bi-sliders"></i><span>Opciones</span>
            </button>
            <button type="button" class="cart-mini-btn" onclick="openActionPanel('split')">
                <i class="bi bi-arrows-angle-expand"></i><span>Dividir</span>
            </button>
            <button type="button" class="cart-mini-btn accent-fire" onclick="openPrintModal('{{ route('pos.kitchen', $order->id) }}', 'Comanda de Cocina')">
                <i class="bi bi-fire"></i><span>Comanda</span>
            </button>
            <button type="button" class="cart-mini-btn" onclick="openPrintModal('{{ route('pos.precheck', $order->id) }}', 'Pre-Cuenta')">
                <i class="bi bi-receipt"></i><span>Pre-Cuenta</span>
            </button>
        </div>

        <button type="button" class="cart-checkout-btn" onclick="openActionPanel('checkout')">
            <span class="cart-checkout-label"><i class="bi bi-wallet2"></i> Cobrar</span>
            <span class="cart-checkout-total">{{ $currency ?? 'S/' }}{{ number_format($order->total, 2) }}</span>
        </button>
    </div>

@else
    <div class="cart-lines" data-item-count="0"></div>
    <div class="cart-empty">
        <span class="cart-empty-icon"><i class="bi bi-basket3"></i></span>
        <p class="cart-empty-title">Mesa vacía</p>
        <small class="cart-empty-hint">Toca un producto del catálogo<br>para empezar la cuenta</small>
    </div>
@endif
