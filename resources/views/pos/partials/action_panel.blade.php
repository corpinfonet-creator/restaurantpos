<div class="offcanvas offcanvas-end action-panel" tabindex="-1" id="actionPanel" aria-labelledby="actionPanelLabel" data-bs-scroll="false" data-has-order="{{ $order ? '1' : '0' }}">
    <div class="offcanvas-header border-bottom">
        <div>
            <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;" id="actionPanelEyebrow">Acción</span>
            <h4 class="offcanvas-title fw-bold mb-0" id="actionPanelLabel">Panel</h4>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">

        {{-- SECCIÓN: OPCIONES DE CUENTA (descuento / propina) --}}
        <div class="action-section" data-section="options">
            <form action="{{ $order ? route('pos.discount', $order->id) : '' }}" method="POST" class="d-flex flex-column h-100 js-action-form">
                @csrf
                <div class="p-4 flex-grow-1">
                    <p class="text-muted mb-4">Ajusta el descuento o la propina aplicados al total de esta cuenta.</p>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">Descuento Global</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white">{{ $currency ?? 'S/' }}</span>
                            <input type="number" step="0.01" name="discount" class="form-control fw-bold"
                                   value="{{ number_format($order->discount ?? 0, 2, '.', '') }}" placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase text-muted">Propina</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white">{{ $currency ?? 'S/' }}</span>
                            <input type="number" step="0.01" name="tip" class="form-control fw-bold"
                                   value="{{ number_format($order->tip ?? 0, 2, '.', '') }}" placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="p-3 border-top bg-light">
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                        <i class="bi bi-check-lg me-1"></i> Aplicar Cambios
                    </button>
                </div>
            </form>
        </div>

        {{-- SECCIÓN: MOVER MESA --}}
        <div class="action-section" data-section="move">
            <form action="{{ $order ? route('pos.move', $order->id) : '' }}" method="POST" class="d-flex flex-column h-100 js-action-form">
                @csrf
                <div class="p-4 flex-grow-1">
                    <p class="text-muted mb-4">Selecciona la mesa de destino. La cuenta actual se trasladará por completo.</p>
                    <label class="form-label fw-bold small text-uppercase text-muted">Mesa de destino</label>
                    <select name="target_table_id" class="form-select form-select-lg" required>
                        <option value="" selected disabled>-- Elegir mesa --</option>
                        @foreach($freeTables as $ft)
                            <option value="{{ $ft->id }}">{{ $ft->name }} ({{ $ft->area->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="p-3 border-top bg-light">
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                        <i class="bi bi-arrow-left-right me-1"></i> Mover Cuenta
                    </button>
                </div>
            </form>
        </div>

        {{-- SECCIÓN: DIVIDIR CUENTA --}}
        <div class="action-section" data-section="split">
            <form action="{{ $order ? route('pos.split', $order->id) : '' }}" method="POST" class="d-flex flex-column h-100 js-action-form">
                @csrf
                <div class="p-4 flex-grow-1 overflow-auto">
                    <p class="text-muted mb-3">Selecciona los productos que deseas cobrar por separado.</p>

                    <div class="split-select-all d-flex align-items-center justify-content-between px-3 py-2 mb-2 rounded">
                        <label class="form-check-label fw-bold small text-uppercase text-muted mb-0" for="splitCheckAll">
                            Seleccionar todo
                        </label>
                        <input type="checkbox" class="form-check-input" id="splitCheckAll" onclick="toggleAllSplit(this)">
                    </div>

                    <div class="split-item-list">
                        @if($order)
                            @foreach($order->details as $detail)
                                <label class="split-item d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
                                    <div class="d-flex align-items-center" style="min-width: 0;">
                                        <input type="checkbox" name="selected_items[]" value="{{ $detail->id }}"
                                               class="form-check-input split-item-check me-3"
                                               data-price="{{ $detail->price * $detail->quantity }}"
                                               onchange="calculateSplitTotal()">
                                        <div class="min-w-0">
                                            <div class="fw-bold text-dark text-truncate">{{ $detail->product->name }}</div>
                                            <div class="small text-muted">
                                                {{ $detail->quantity }} x {{ $currency ?? 'S/' }}{{ number_format($detail->price, 2) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="fw-bold text-end flex-shrink-0 ms-2">
                                        {{ $currency ?? 'S/' }}{{ number_format($detail->price * $detail->quantity, 2) }}
                                    </div>
                                </label>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="p-3 border-top bg-light">
                    <label class="form-label fw-bold small text-uppercase text-muted mb-1">Método de Pago para esta parte</label>
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="payment_method" id="splitCash" value="cash" checked>
                        <label class="btn btn-outline-success" for="splitCash"><i class="bi bi-cash"></i> Efectivo</label>

                        <input type="radio" class="btn-check" name="payment_method" id="splitCard" value="card">
                        <label class="btn btn-outline-primary" for="splitCard"><i class="bi bi-credit-card"></i> Tarjeta</label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted text-uppercase small fw-bold">Total a cobrar</span>
                        <span class="fs-3 fw-bold text-primary" id="splitTotalDisplay">{{ $currency ?? 'S/' }}0.00</span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" id="btnSplitSubmit" disabled>
                        <i class="bi bi-check-circle-fill me-1"></i> Cobrar Selección
                    </button>
                </div>
            </form>
        </div>

        {{-- SECCIÓN: COBRAR --}}
        <div class="action-section" data-section="checkout">
            <form action="{{ $order ? route('pos.checkout', $order->id) : '' }}" method="POST" class="d-flex flex-column h-100 js-action-form">
                @csrf
                <div class="checkout-scroll flex-grow-1 overflow-auto">

                    {{-- PASO 1 · Comprobante. Va primero porque determina qué
                         documento del cliente hace falta (Boleta→DNI, Factura→RUC). --}}
                    <section class="checkout-step">
                        <div class="doc-type-group" role="group">
                            <input type="radio" class="btn-check" name="document_type" id="docTicket" value="Ticket" checked>
                            <label class="doc-type-btn" for="docTicket">
                                <i class="bi bi-receipt"></i><span>Ticket</span>
                            </label>

                            <input type="radio" class="btn-check" name="document_type" id="docBoleta" value="Boleta">
                            <label class="doc-type-btn" for="docBoleta">
                                <i class="bi bi-file-text"></i><span>Boleta</span>
                            </label>

                            <input type="radio" class="btn-check" name="document_type" id="docFactura" value="Factura">
                            <label class="doc-type-btn" for="docFactura">
                                <i class="bi bi-building"></i><span>Factura</span>
                            </label>
                        </div>
                    </section>

                    {{-- CLIENTE --}}
                    <section class="checkout-step">
                        {{-- Documento + botón de consulta a SUNAT/RENIEC.
                             Un solo campo: 8 dígitos = DNI, 11 = RUC. --}}
                        <div class="doc-lookup">
                            <div class="doc-lookup-field">
                                <span class="doc-lookup-badge" id="docKindBadge">DOC</span>
                                <input type="text" name="client_document" id="clientDoc"
                                       class="doc-lookup-input" placeholder="Nº de DNI o RUC"
                                       maxlength="11" inputmode="numeric" autocomplete="off">
                            </div>
                            <button type="button" class="doc-lookup-btn" id="posDniSearchBtn" title="Buscar en RENIEC / SUNAT" aria-label="Buscar documento">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div class="doc-lookup-status" id="posDniLookupStatus"></div>

                        {{-- Nombre / razón social --}}
                        <div class="checkout-field">
                            <label class="checkout-label" for="clientSearch">Nombre o razón social</label>
                            <div class="checkout-input-wrap">
                                <input type="text" list="clientsList" id="clientSearch" name="client_name"
                                       class="checkout-input" placeholder="Público General" autocomplete="off">
                                <input type="hidden" name="client_id" id="clientId">
                                <button type="button" class="checkout-input-clear" id="clientClearBtn" title="Limpiar cliente">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <datalist id="clientsList">
                                @foreach($clients as $client)
                                    <option data-id="{{ $client->id }}" value="{{ $client->name }}">{{ $client->document_number }}</option>
                                @endforeach
                            </datalist>
                        </div>

                        {{-- Solo se muestra si la consulta devolvió domicilio fiscal --}}
                        <div class="doc-address" id="clientAddressBox" hidden>
                            <i class="bi bi-geo-alt"></i>
                            <span id="clientAddressText"></span>
                        </div>
                    </section>

                    {{-- PASO 3 · Pago --}}
                    <section class="checkout-step">
                        <div class="pay-method-group" role="group">
                            <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked onchange="toggleCashInput(true)">
                            <label class="pay-method-btn" for="payCash">
                                <i class="bi bi-cash-stack"></i><span>Efectivo</span>
                            </label>

                            <input type="radio" class="btn-check" name="payment_method" id="payCard" value="card" onchange="toggleCashInput(false)">
                            <label class="pay-method-btn" for="payCard">
                                <i class="bi bi-credit-card-2-front"></i><span>Tarjeta</span>
                            </label>
                        </div>

                        <div id="cashInputGroup">
                            <label class="checkout-label mt-3" for="receivedAmount">Monto recibido</label>
                            <div class="cash-input-wrap">
                                <span class="cash-currency">{{ $currency ?? 'S/' }}</span>
                                <input type="number" step="0.01" min="0" name="received_amount" id="receivedAmount"
                                       class="cash-input" value="{{ $order->total ?? 0 }}" oninput="calculateChange()">
                            </div>

                            {{-- Atajos de billetes: rellenan el monto recibido de un toque --}}
                            <div class="cash-quick" id="cashQuickRow">
                                <button type="button" class="cash-quick-btn" data-exact="1">Exacto</button>
                                <button type="button" class="cash-quick-btn" data-amount="10">10</button>
                                <button type="button" class="cash-quick-btn" data-amount="20">20</button>
                                <button type="button" class="cash-quick-btn" data-amount="50">50</button>
                                <button type="button" class="cash-quick-btn" data-amount="100">100</button>
                                <button type="button" class="cash-quick-btn" data-amount="200">200</button>
                            </div>

                            <div class="change-row" id="changeRow">
                                <span class="change-label">Vuelto</span>
                                <span class="change-value" id="changeAmount">0.00</span>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="checkout-footer">
                    <button type="submit" class="checkout-submit" id="checkoutSubmitBtn">
                        <span class="checkout-submit-main"><i class="bi bi-check2-circle"></i> Confirmar pago</span>
                        <span class="checkout-submit-total" id="checkoutSubmitTotal">{{ $currency ?? 'S/' }}{{ number_format($order->total ?? 0, 2) }}</span>
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
