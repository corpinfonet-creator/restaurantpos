@extends('layouts.app')

@section('content')
<div class="pos-screen">

    {{-- ================= BARRA SUPERIOR ================= --}}
    <header class="pos-topbar">
        <div class="pos-topbar-left">
            <a href="{{ route('pos.index') }}" class="pos-back" title="Volver al salón">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="pos-table-id">
                <span class="pos-table-name">{{ $table->name }}</span>
                <span class="pos-table-area"><i class="bi bi-geo-alt-fill"></i> {{ $table->area->name }}</span>
            </div>
        </div>

        {{-- Buscador de productos: filtra el catálogo en vivo, sin recargar --}}
        <div class="pos-search">
            <i class="bi bi-search pos-search-icon"></i>
            <input type="text" id="productSearch" class="pos-search-input"
                   placeholder="Buscar producto por nombre..." autocomplete="off" spellcheck="false">
            <button type="button" class="pos-search-clear" id="productSearchClear" title="Limpiar búsqueda" hidden>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        {{-- "Limpiar" vacía la mesa entera. Va junto al buscador, en la misma
             línea. Solo aparece cuando hay productos que quitar (lo controla
             syncCartHeader()). --}}
        <button type="button" class="pos-clear-btn" id="btnClearCart" onclick="clearCart()" hidden>
            <i class="bi bi-trash3"></i> <span>Limpiar</span>
        </button>

        <div class="pos-topbar-right">
            <button type="button" class="pos-ghost-btn" onclick="openActionPanel('move')">
                <i class="bi bi-arrow-left-right"></i> <span>Mover</span>
            </button>
        </div>
    </header>

    <div class="pos-body">

        {{-- ================= CATEGORÍAS ================= --}}
        <nav class="pos-categories" aria-label="Categorías">
            <button type="button" onclick="filterProducts('all')" class="pos-cat active" id="cat-btn-all">
                <span class="pos-cat-icon"><i class="bi bi-grid-fill"></i></span>
                <span class="pos-cat-name">Todo</span>
            </button>
            @foreach($categories as $category)
                <button type="button" onclick="filterProducts('cat-{{ $category->id }}')" class="pos-cat" id="cat-btn-{{ $category->id }}">
                    <span class="pos-cat-icon">
                        @if($category->image)
                            <img src="{{ asset('storage/'.$category->image) }}" alt="">
                        @else
                            <i class="bi bi-tag-fill"></i>
                        @endif
                    </span>
                    <span class="pos-cat-name">{{ $category->name }}</span>
                </button>
            @endforeach
        </nav>

        {{-- ================= CATÁLOGO ================= --}}
        <main class="pos-catalog" id="products-container">
            <div class="pos-grid">
                @foreach($categories as $category)
                    @foreach($category->products as $product)
                        @php $agotado = !is_null($product->stock) && $product->stock <= 0; @endphp
                        <article class="product-item cat-{{ $category->id }}" data-name="{{ Str::lower($product->name) }}">
                            <button type="button"
                                    class="product-card {{ $agotado ? 'is-out' : '' }}"
                                    onclick="addToOrder({{ $product->id }})"
                                    {{ $agotado ? 'disabled' : '' }}>
                                <div class="product-thumb">
                                    @if($product->image)
                                        <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" loading="lazy">
                                    @else
                                        <span class="product-thumb-empty"><i class="bi bi-cup-hot"></i></span>
                                    @endif

                                    @if($agotado)
                                        <span class="product-flag flag-out">Agotado</span>
                                    @elseif(!is_null($product->stock) && $product->stock <= 5)
                                        <span class="product-flag flag-low">Quedan {{ $product->stock }}</span>
                                    @endif

                                    <span class="product-add"><i class="bi bi-plus-lg"></i></span>
                                </div>
                                <div class="product-info">
                                    <span class="product-name">{{ $product->name }}</span>
                                    <span class="product-price">{{ $currency ?? 'S/' }}{{ number_format($product->price, 2) }}</span>
                                </div>
                            </button>
                        </article>
                    @endforeach
                @endforeach
            </div>

            {{-- Se muestra solo cuando el buscador no encuentra coincidencias --}}
            <div class="pos-empty-search" id="noResults" hidden>
                <i class="bi bi-search"></i>
                <p class="mb-1 fw-semibold">Sin coincidencias</p>
                <small>No hay productos que coincidan con <b id="noResultsTerm"></b></small>
            </div>
        </main>

        {{-- ================= CUENTA ================= --}}
        <aside class="pos-cart">
            <div class="pos-cart-head">
                <div>
                    <span class="pos-cart-title">Cuenta actual</span>
                    <span class="pos-cart-sub" id="cartItemCount">Mesa vacía</span>
                </div>
            </div>
            <div id="cart-container" class="pos-cart-body">
                @include('pos.partials.cart', ['order' => $order])
            </div>
        </aside>
    </div>
</div>

@push('modals')
<div class="modal fade" id="noteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 bg-warning">
                <h6 class="modal-title fw-bold text-dark">Nota Cocina</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="noteDetailId">
                <textarea id="noteText" class="form-control" rows="3" placeholder="Ej: Sin sal"></textarea>
            </div>
            <div class="modal-footer p-1">
                <button type="button" class="btn btn-warning w-100 btn-sm text-dark fw-bold" onclick="saveNote()">Guardar Nota</button>
            </div>
        </div>
    </div>
</div>

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

<div id="actionPanelContainer">
    @include('pos.partials.action_panel', ['order' => $order, 'freeTables' => $freeTables, 'clients' => $clients, 'currency' => $currency])
</div>
@endpush

<script>
    const tableId = {{ $table->id }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // --- CONFIGURACIÓN MODALES ---
    // OJO: este <script> se imprime dentro de la sección de contenido, que el
    // layout renderiza ANTES que la pila de modales — donde vive #noteModal. Al
    // ejecutarse, el modal todavía no existe en el DOM, así que engancharle el
    // listener directamente no funcionaba: getElementById devolvía null, el
    // guardia lo saltaba en silencio y #noteDetailId nunca se rellenaba. Al
    // guardar se hacía POST a /pos/detail//note (sin id) y la nota se perdía.
    //
    // La solución es delegar en `document`, que siempre existe: el evento
    // show.bs.modal burbujea desde el modal hasta aquí.
    document.addEventListener('show.bs.modal', function (event) {
        if (!event.target || event.target.id !== 'noteModal') return;

        var button = event.relatedTarget;
        if (!button) return;

        document.getElementById('noteDetailId').value = button.getAttribute('data-detail-id') || '';
        document.getElementById('noteText').value = button.getAttribute('data-note-content') || '';
    });

    // El foco se pone cuando el modal ya terminó de abrirse (antes se usaba un
    // setTimeout de 500ms, que competía con la animación de Bootstrap).
    document.addEventListener('shown.bs.modal', function (event) {
        if (!event.target || event.target.id !== 'noteModal') return;
        var textarea = document.getElementById('noteText');
        if (textarea) textarea.focus();
    });

    function forceCloseModal(modalId) {
        var el = document.getElementById(modalId);
        if (typeof bootstrap !== 'undefined') {
            var instance = bootstrap.Modal.getInstance(el);
            if (instance) instance.hide();
        }
        el.classList.remove('show');
        el.style.display = 'none';
        document.body.classList.remove('modal-open');
        document.body.style = '';
        var backdrops = document.getElementsByClassName('modal-backdrop');
        if(backdrops[0]) backdrops[0].remove();
    }

    // --- FUNCIONES AJAX DE GUARDADO ---
    window.saveNote = function() {
        var detailId = document.getElementById('noteDetailId').value;
        var note = document.getElementById('noteText').value;

        // Sin id no hay a qué producto asociar la nota: se avisa en vez de
        // mandar un POST a /pos/detail//note que devolvería 404 en silencio.
        if (!detailId) {
            alert('No se pudo identificar el producto. Cierra la nota y vuelve a intentarlo.');
            return;
        }

        forceCloseModal('noteModal');
        cartAjax(`{{ url('/pos/detail') }}/${detailId}/note`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ note: note })
        });
    };

    // --- CARRITO: agregar / eliminar / cambiar cantidad, 100% AJAX (sin recargar página) ---
    // Cada acción se envía por fetch en cuanto se hace click/change, sin esperar a
    // que termine una petición anterior. Un AbortController compartido cancela
    // cualquier fetch del carrito todavía en vuelo apenas se dispara uno nuevo, así
    // que aunque el usuario elimine varios ítems muy rápido seguido, solo la
    // respuesta de la última petición termina pintando el carrito — nunca se
    // renderiza un HTML "viejo" que pise al más reciente, y nunca se navega la
    // página (que es lo que producía el 404 al hacer doble-submit sobre un
    // <form> cuyo detalle ya había sido borrado por el clic anterior).
    var cartRequestController = null;
    var cartRefreshUrl = `{{ url('/pos/table') }}/${tableId}/cart`;

    // Si la acción falla (p. ej. 404 porque el ítem ya fue borrado por un clic
    // anterior que ganó la carrera), no pintamos la página de error de Laravel
    // dentro del carrito: simplemente volvemos a pedir el estado real y vigente
    // del carrito por tableId (que nunca queda obsoleto) y lo mostramos.
    function paintCart(html) {
        document.getElementById('cart-container').innerHTML = html;
    }

    // El panel de acciones (Cobrar/Mover/Dividir/Opciones) se repinta SIEMPRE
    // junto con el carrito. Si solo se repintara el carrito, el panel quedaría
    // con un estado viejo: sin `action` en sus formularios (mesa que estaba
    // vacía al cargar la página) o con el id de una orden ya borrada o ya
    // cobrada. De ahí salían los dos errores clásicos: "Agrega al menos un
    // producto a la mesa antes de continuar" con la mesa llena, y "Orden
    // cerrada" al intentar cobrar.
    //
    // OJO: el offcanvas de Bootstrap guarda una instancia asociada al elemento
    // DOM. Al reemplazar el HTML hay que descartar la instancia vieja, o
    // Bootstrap seguiría manejando un nodo que ya no está en el documento.
    function paintActionPanel(html) {
        var container = document.getElementById('actionPanelContainer');
        if (!container) return;

        var current = document.getElementById('actionPanel');
        var wasOpen = current ? current.classList.contains('show') : false;
        var openSection = null;

        if (wasOpen) {
            var visible = current.querySelector('.action-section.d-block');
            openSection = visible ? visible.getAttribute('data-section') : null;
            var oldInstance = bootstrap.Offcanvas.getInstance(current);
            if (oldInstance) oldInstance.dispose();
        }

        container.innerHTML = html;

        // Si el panel estaba abierto mientras se repintaba (p. ej. el usuario
        // agregó un producto con el panel de cobro visible), se vuelve a abrir
        // en la misma sección, ya con los importes y las rutas actualizados.
        if (wasOpen && openSection) openActionPanel(openSection);
    }

    // Todas las acciones del carrito responden un JSON { cartHtml, panelHtml }.
    function paintCartResponse(response) {
        return response.json().then(function (data) {
            paintCart(data.cartHtml);
            paintActionPanel(data.panelHtml);
            syncCartHeader();
        });
    }

    // Cabecera de la cuenta: contador de líneas y visibilidad de "Limpiar".
    // Se recalcula tras cada repintado porque el carrito se reemplaza entero.
    function syncCartHeader() {
        var lines = document.querySelector('#cart-container .cart-lines');
        var count = lines ? parseInt(lines.getAttribute('data-item-count'), 10) || 0 : 0;

        var label = document.getElementById('cartItemCount');
        if (label) {
            label.textContent = count === 0
                ? 'Mesa vacía'
                : count + (count === 1 ? ' producto' : ' productos');
        }

        var clearBtn = document.getElementById('btnClearCart');
        if (clearBtn) clearBtn.hidden = count === 0;
    }

    function reloadCartState() {
        return fetch(cartRefreshUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(paintCartResponse)
            .catch(() => {});
    }

    function refreshCart(fetchPromise) {
        return fetchPromise
            .then(function (r) { return r.ok ? paintCartResponse(r) : reloadCartState(); })
            .catch(err => { if (err.name !== 'AbortError') reloadCartState(); });
    }

    function cartAjax(url, options) {
        if (cartRequestController) cartRequestController.abort();
        cartRequestController = new AbortController();
        return refreshCart(fetch(url, Object.assign({}, options, { signal: cartRequestController.signal })));
    }

    // --- POS: PRODUCTOS ---
    window.addToOrder = function(productId) {
        cartAjax(`{{ url('/pos/order') }}/${tableId}/add`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ product_id: productId })
        });
    };

    // Vaciar la mesa completa. Pide confirmación porque borra todo de golpe.
    window.clearCart = function() {
        var lines = document.querySelector('#cart-container .cart-lines');
        var count = lines ? parseInt(lines.getAttribute('data-item-count'), 10) || 0 : 0;
        if (count === 0) return;
        if (!confirm('¿Quitar los ' + count + ' producto(s) de esta mesa?')) return;

        cartAjax(`{{ url('/pos/table') }}/${tableId}/clear`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
    };

    var cartContainerEl = document.getElementById('cart-container');
    if (cartContainerEl) {
        // Botones -/+ : escriben la nueva cantidad en el input existente y
        // envían el mismo formulario de siempre (misma ruta, misma lógica).
        cartContainerEl.addEventListener('click', function (e) {
            var step = e.target.closest('.js-qty-step');
            if (!step) return;

            var row = step.closest('.cart-qty');
            var input = row ? row.querySelector('[name="quantity"]') : null;
            if (!input) return;

            var next = (parseInt(input.value, 10) || 0) + parseInt(step.getAttribute('data-step'), 10);
            if (next < 0) next = 0;
            input.value = next;
            input.form.requestSubmit();
        });

        cartContainerEl.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form.matches('.js-remove-item-form, .js-update-qty-form')) return;
            e.preventDefault();

            if (form.classList.contains('js-remove-item-form')) {
                cartAjax(form.action, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                });
            } else {
                var qty = form.querySelector('[name="quantity"]').value;
                cartAjax(form.action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ quantity: qty }),
                });
            }
        });
    }

    // --- FILTROS: categoría + buscador ---
    // Ambos filtros se aplican juntos en una sola pasada: un producto se ve si
    // pertenece a la categoría activa Y coincide con el texto buscado.
    var activeCategory = 'all';
    var searchTerm = '';

    function applyProductFilters() {
        var visible = 0;

        document.querySelectorAll('.product-item').forEach(function (item) {
            var matchCat = activeCategory === 'all' || item.classList.contains(activeCategory);
            var matchText = searchTerm === '' || (item.getAttribute('data-name') || '').indexOf(searchTerm) !== -1;
            var show = matchCat && matchText;
            item.hidden = !show;
            if (show) visible++;
        });

        var noResults = document.getElementById('noResults');
        if (noResults) {
            noResults.hidden = visible > 0;
            var term = document.getElementById('noResultsTerm');
            if (term) term.textContent = searchTerm ? '"' + searchTerm + '"' : 'esta categoría';
        }
    }

    window.filterProducts = function(cat) {
        activeCategory = cat;
        document.querySelectorAll('.pos-cat').forEach(function (btn) { btn.classList.remove('active'); });
        var btn = document.getElementById(cat === 'all' ? 'cat-btn-all' : 'cat-btn-' + cat.replace('cat-', ''));
        if (btn) btn.classList.add('active');
        applyProductFilters();
    };

    (function () {
        var input = document.getElementById('productSearch');
        var clearBtn = document.getElementById('productSearchClear');
        if (!input) return;

        function runSearch() {
            searchTerm = input.value.trim().toLowerCase();
            if (clearBtn) clearBtn.hidden = searchTerm === '';
            applyProductFilters();
        }

        input.addEventListener('input', runSearch);

        // Escape limpia la búsqueda; Enter agrega el único resultado visible
        // (atajo cómodo para teclado o lector de código de barras).
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                input.value = '';
                runSearch();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                var shown = Array.prototype.filter.call(
                    document.querySelectorAll('.product-item'),
                    function (el) { return !el.hidden; }
                );
                if (shown.length === 1) {
                    var card = shown[0].querySelector('.product-card');
                    if (card && !card.disabled) card.click();
                }
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                runSearch();
                input.focus();
            });
        }
    })();

    syncCartHeader();

    // --- PANEL LATERAL DE ACCIONES (Opciones / Mover Mesa / Dividir / Cobrar) ---
    const actionPanelLabels = {
        options:  { eyebrow: 'Ajustes de cuenta', title: 'Descuento y Propina', icon: 'bi-sliders' },
        move:     { eyebrow: 'Reubicar', title: 'Mover a otra Mesa', icon: 'bi-arrow-left-right' },
        split:    { eyebrow: 'Cobro parcial', title: 'Dividir Cuenta', icon: 'bi-arrows-angle-expand' },
        checkout: { eyebrow: 'Finalizar venta', title: 'Cobrar Cuenta', icon: 'bi-wallet2' },
    };

    // El carrito (#cart-container) se refresca vía AJAX de forma independiente al
    // panel de acciones, así que el total mostrado en el offcanvas puede quedar
    // desactualizado. #cartTotalRow siempre trae el total real vigente.
    function getCurrentOrderTotal() {
        var row = document.getElementById('cartTotalRow');
        var val = row ? parseFloat(row.getAttribute('data-order-total')) : NaN;
        return isNaN(val) ? 0 : val;
    }

    // ¿Hay realmente productos en la mesa? La fuente de verdad es el carrito,
    // que es además lo que el usuario está viendo: si hay líneas de detalle,
    // hay pedido. El atributo data-has-order del panel es solo un espejo.
    function cartHasItems() {
        return document.querySelectorAll('#cart-container .cart-line').length > 0;
    }

    window.openActionPanel = function(section) {
        var panelEl = document.getElementById('actionPanel');
        if (!panelEl) return;

        var panelHasOrder = panelEl.getAttribute('data-has-order') === '1';

        // Mesa realmente vacía: no hay nada que cobrar, mover, dividir ni
        // ajustar. Este es el único caso en que el aviso es correcto.
        if (!panelHasOrder && !cartHasItems()) {
            alert('Agrega al menos un producto a la mesa antes de continuar.');
            return;
        }

        // El carrito muestra productos pero el panel todavía no se enteró
        // (una respuesta abortada, una petición que se cruzó...). Antes esto
        // disparaba el aviso "Agrega al menos un producto" con la mesa llena.
        // Ahora se resincroniza contra el servidor y se abre el panel ya
        // correcto: el usuario no ve ningún error, solo el panel abriéndose.
        if (!panelHasOrder) {
            reloadCartState().then(function () {
                var fresh = document.getElementById('actionPanel');
                if (fresh && fresh.getAttribute('data-has-order') === '1') {
                    openActionPanel(section);
                } else {
                    alert('Agrega al menos un producto a la mesa antes de continuar.');
                }
            });
            return;
        }

        document.querySelectorAll('.action-section').forEach(function (el) {
            el.classList.toggle('d-block', el.getAttribute('data-section') === section);
        });

        var meta = actionPanelLabels[section];
        if (meta) {
            document.getElementById('actionPanelEyebrow').textContent = meta.eyebrow;
            document.getElementById('actionPanelLabel').innerHTML = '<i class="bi ' + meta.icon + ' me-2"></i>' + meta.title;
        }

        if (section === 'checkout') {
            var total = getCurrentOrderTotal();
            var currency = @json($currency ?? 'S/');

            // El total se muestra en un solo sitio: el botón "Confirmar pago".
            var submitTotal = document.getElementById('checkoutSubmitTotal');
            if (submitTotal) submitTotal.textContent = currency + total.toFixed(2);

            var receivedEl = document.getElementById('receivedAmount');
            if (receivedEl) receivedEl.value = total.toFixed(2);
            calculateChange();
            if (window.refreshCheckoutClientUI) window.refreshCheckoutClientUI();
        }

        if (section === 'split') {
            resetSplitForm();
        }

        var instance = bootstrap.Offcanvas.getOrCreateInstance(panelEl);
        instance.show();

        if (section === 'checkout') {
            panelEl.addEventListener('shown.bs.offcanvas', function focusReceived() {
                document.getElementById('receivedAmount')?.focus();
                panelEl.removeEventListener('shown.bs.offcanvas', focusReceived);
            });
        }
    };

    // --- COBRAR: método de pago y cambio ---
    window.toggleCashInput = function(show) {
        var el = document.getElementById('cashInputGroup');
        if (el) el.style.display = show ? 'block' : 'none';
    };

    window.calculateChange = function() {
        var total = getCurrentOrderTotal();
        var received = parseFloat(document.getElementById('receivedAmount')?.value) || 0;
        var change = received - total;
        var el = document.getElementById('changeAmount');
        if (!el) return;

        var currency = @json($currency ?? 'S/');
        var falta = change < 0;

        // Si el efectivo recibido no alcanza, se muestra cuánto FALTA en vez de
        // un vuelto de 0.00, que no le dice nada al cajero.
        el.textContent = currency + Math.abs(change).toFixed(2);

        var row = document.getElementById('changeRow');
        if (row) {
            row.classList.toggle('is-short', falta);
            var label = row.querySelector('.change-label');
            if (label) label.textContent = falta ? 'Falta' : 'Vuelto';
        }
    };

    // --- DIVIDIR CUENTA: selección de items y total parcial ---
    function resetSplitForm() {
        document.querySelectorAll('.split-item-check').forEach(function (chk) { chk.checked = false; });
        var checkAll = document.getElementById('splitCheckAll');
        if (checkAll) checkAll.checked = false;
        calculateSplitTotal();
    }

    window.toggleAllSplit = function(source) {
        document.querySelectorAll('.split-item-check').forEach(function (chk) { chk.checked = source.checked; });
        calculateSplitTotal();
    };

    window.calculateSplitTotal = function() {
        var total = 0;
        var checks = document.querySelectorAll('.split-item-check:checked');
        checks.forEach(function (chk) { total += parseFloat(chk.getAttribute('data-price')); });

        var currency = @json($currency ?? 'S/');
        var display = document.getElementById('splitTotalDisplay');
        var btn = document.getElementById('btnSplitSubmit');
        if (display) display.textContent = currency + total.toFixed(2);
        if (btn) btn.disabled = total <= 0;

        var allCount = document.querySelectorAll('.split-item-check').length;
        var checkAll = document.getElementById('splitCheckAll');
        if (checkAll) checkAll.checked = allCount > 0 && checks.length === allCount;
    };

    // El panel de acciones (#actionPanelContainer) puede reemplazarse por
    // completo por AJAX la primera vez que una mesa vacía recibe un producto
    // (ver paintActionPanel). Los listeners de sus campos internos se
    // registran aquí por DELEGACIÓN sobre `document` (no sobre el propio
    // contenedor: este script se imprime en el HTML ANTES que el offcanvas,
    // que vive en el stack de modales renderizado más abajo en el
    // layout, así que #actionPanelContainer todavía no existe cuando este
    // script corre) — `document` siempre existe, y la delegación hace que
    // los listeners sigan funcionando aunque el HTML interno se reemplace.
    var dniLookupUrlBase = `{{ url('/dni-lookup') }}`;

    (function () {
        // Mientras la mesa no tenga pedido, los formularios del panel se
        // renderizan sin `action` (no existe todavía el id de la orden). Sin
        // este guardia el navegador enviaría el POST a la URL actual
        // (/pos/table/{id}, que solo acepta GET) y reventaría con un
        // MethodNotAllowed. Cuando se crea la orden, el panel se repinta por
        // AJAX ya con las rutas reales y estos envíos pasan con normalidad.
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form.classList || !form.classList.contains('js-action-form')) return;
            if (!form.getAttribute('action')) {
                e.preventDefault();
                alert('Agrega al menos un producto a la mesa antes de continuar.');
            }
        });

        document.addEventListener('input', function (e) {
            if (e.target.id !== 'clientSearch') return;
            var input = e.target;
            var options = document.getElementById('clientsList').options;
            document.getElementById('clientId').value = '';
            for (var i = 0; i < options.length; i++) {
                if (options[i].value === input.value) {
                    document.getElementById('clientId').value = options[i].getAttribute('data-id');
                    break;
                }
            }
        });

        // ---------------------------------------------------------------
        // CONSULTA DE DOCUMENTO (api.json.pe): DNI (8) y RUC (11)
        // ---------------------------------------------------------------
        // El cajero escribe el número en un solo campo; el tipo se deduce por
        // la longitud, igual que en el backend. Así no hay que elegir antes
        // "es DNI" o "es RUC", que es el paso donde más se equivoca la gente.

        function setLookupStatus(msg, kind) {
            var el = document.getElementById('posDniLookupStatus');
            if (!el) return;
            el.textContent = msg || '';
            el.className = 'doc-lookup-status' + (kind ? ' is-' + kind : '');
        }

        // Badge que indica en vivo qué se detectó según lo tecleado.
        function refreshDocKind() {
            var input = document.getElementById('clientDoc');
            var badge = document.getElementById('docKindBadge');
            if (!input || !badge) return;

            var v = input.value.replace(/\D/g, '');
            var kind = v.length === 8 ? 'DNI' : (v.length === 11 ? 'RUC' : 'DOC');
            badge.textContent = kind;
            badge.className = 'doc-lookup-badge' + (kind === 'DOC' ? '' : ' is-valid');
        }

        function showClientAddress(address) {
            var box = document.getElementById('clientAddressBox');
            var text = document.getElementById('clientAddressText');
            if (!box || !text) return;

            if (address) {
                text.textContent = address;
                box.hidden = false;
            } else {
                text.textContent = '';
                box.hidden = true;
            }
        }

        // Factura exige RUC y Boleta se emite con DNI. En vez de una etiqueta
        // aparte, el requisito se comunica en el propio placeholder del campo:
        // ocupa cero espacio extra y se lee justo donde hay que escribir.
        function refreshClientRequirement() {
            var input = document.getElementById('clientDoc');
            var checked = document.querySelector('[name="document_type"]:checked');
            if (!input || !checked) return;

            var type = checked.value;
            if (type === 'Factura') {
                input.placeholder = 'RUC (11 dígitos) — obligatorio';
            } else if (type === 'Boleta') {
                input.placeholder = 'DNI (8 dígitos)';
            } else {
                input.placeholder = 'Nº de DNI o RUC';
            }
        }

        function runDocumentLookup() {
            var btn = document.getElementById('posDniSearchBtn');
            var docInput = document.getElementById('clientDoc');
            var nameInput = document.getElementById('clientSearch');
            if (!btn || !docInput || !nameInput) return;

            var doc = docInput.value.replace(/\D/g, '');
            docInput.value = doc;
            refreshDocKind();

            if (!/^\d{8}$|^\d{11}$/.test(doc)) {
                setLookupStatus('Ingresa un DNI de 8 dígitos o un RUC de 11.', 'error');
                docInput.focus();
                return;
            }

            var esRuc = doc.length === 11;
            btn.disabled = true;
            var originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            setLookupStatus(esRuc ? 'Consultando SUNAT...' : 'Consultando RENIEC...', 'loading');

            fetch(`${dniLookupUrlBase}/${doc}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok || !data.found) {
                        showClientAddress(null);
                        setLookupStatus(data.message || 'No se encontró información para ese documento.', 'error');
                        return;
                    }

                    nameInput.value = data.name;
                    // Es un cliente traído de la API, no uno del listado local:
                    // se limpia el id para que el checkout guarde el nombre tal cual.
                    document.getElementById('clientId').value = '';
                    showClientAddress(data.address);

                    if (data.type === 'ruc') {
                        // Con RUC lo natural es emitir Factura: se preselecciona.
                        var factura = document.getElementById('docFactura');
                        if (factura && !factura.checked) {
                            factura.checked = true;
                            refreshClientRequirement();
                        }
                        var extra = data.condition && data.condition !== 'HABIDO'
                            ? ' · ' + data.condition
                            : '';
                        setLookupStatus('RUC encontrado: ' + (data.status || 'ACTIVO') + extra,
                                        data.condition === 'NO HABIDO' ? 'warn' : 'ok');
                    } else {
                        var boleta = document.getElementById('docBoleta');
                        if (boleta && document.getElementById('docTicket').checked) {
                            boleta.checked = true;
                            refreshClientRequirement();
                        }
                        setLookupStatus('DNI encontrado. Datos completados.', 'ok');
                    }
                })
                .catch(() => {
                    showClientAddress(null);
                    setLookupStatus('No se pudo consultar el servicio. Intenta de nuevo.', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                });
        }

        document.addEventListener('click', function (e) {
            if (e.target.closest('#posDniSearchBtn')) {
                runDocumentLookup();
                return;
            }

            // Limpiar cliente
            if (e.target.closest('#clientClearBtn')) {
                var name = document.getElementById('clientSearch');
                var id = document.getElementById('clientId');
                var doc = document.getElementById('clientDoc');
                if (name) name.value = '';
                if (id) id.value = '';
                if (doc) doc.value = '';
                showClientAddress(null);
                setLookupStatus('');
                refreshDocKind();
                if (name) name.focus();
                return;
            }

            // Atajos de efectivo (Exacto / 10 / 20 / 50 / 100 / 200)
            var quick = e.target.closest('.cash-quick-btn');
            if (quick) {
                var received = document.getElementById('receivedAmount');
                if (!received) return;
                var total = getCurrentOrderTotal();
                received.value = quick.hasAttribute('data-exact')
                    ? total.toFixed(2)
                    : parseFloat(quick.getAttribute('data-amount')).toFixed(2);
                calculateChange();
                return;
            }
        });

        // Solo dígitos en el campo de documento, y badge en vivo.
        document.addEventListener('input', function (e) {
            if (e.target.id !== 'clientDoc') return;
            e.target.value = e.target.value.replace(/\D/g, '');
            refreshDocKind();
        });

        // Enter en el documento dispara la consulta (no envía el formulario).
        document.addEventListener('keydown', function (e) {
            if (e.target.id === 'clientDoc' && e.key === 'Enter') {
                e.preventDefault();
                runDocumentLookup();
            }
        });

        // Cambio de tipo de comprobante -> se actualiza el aviso del paso 2.
        document.addEventListener('change', function (e) {
            if (e.target.name === 'document_type') refreshClientRequirement();
        });

        // El panel se repinta por AJAX (paintActionPanel), así que openActionPanel
        // necesita poder resincronizar estos indicadores desde fuera del IIFE.
        window.refreshCheckoutClientUI = function () {
            refreshDocKind();
            refreshClientRequirement();
            showClientAddress(null);
            setLookupStatus('');
        };

        // Factura sin RUC válido: se bloquea antes de enviar, porque el
        // comprobante saldría inválido y ya no se puede corregir tras cobrar.
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form.classList || !form.classList.contains('js-action-form')) return;
            if (!form.querySelector('#checkoutSubmitBtn')) return;

            var type = form.querySelector('[name="document_type"]:checked');
            if (!type || type.value !== 'Factura') return;

            var doc = (form.querySelector('#clientDoc') || {}).value || '';
            if (!/^\d{11}$/.test(doc.replace(/\D/g, ''))) {
                e.preventDefault();
                setLookupStatus('Para emitir Factura necesitas un RUC de 11 dígitos.', 'error');
                var el = document.getElementById('clientDoc');
                if (el) el.focus();
            }
        }, true);
    })();
</script>

<style>
    /* ============================================================
       POS · Pantalla de venta en mesa
       Layout de 3 columnas a pantalla completa:
       categorías | catálogo | cuenta
       ============================================================ */
    .pos-screen {
        --pos-line: #e6e9ef;
        --pos-ink: #16202e;
        --pos-muted: #7d8797;
        --pos-brand: #0d6efd;
        --pos-surface: #f6f7f9;

        position: absolute; inset: 0;
        display: flex; flex-direction: column;
        background: var(--pos-surface);
        overflow: hidden;
    }

    /* ---------- Barra superior ---------- */
    .pos-topbar {
        flex-shrink: 0;
        display: flex; align-items: center; gap: 18px;
        height: 64px; padding: 0 18px;
        background: #fff;
        border-bottom: 1px solid var(--pos-line);
    }
    .pos-topbar-left { display: flex; align-items: center; gap: 12px; min-width: 0; }

    .pos-back {
        width: 38px; height: 38px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 10px; text-decoration: none;
        color: #52607a; background: #eef1f6;
        font-size: 1.05rem; transition: background-color .15s, color .15s;
    }
    .pos-back:hover { background: #e2e7f0; color: var(--pos-brand); }

    .pos-table-id { display: flex; flex-direction: column; line-height: 1.15; min-width: 0; }
    .pos-table-name { font-weight: 800; font-size: 1.05rem; color: var(--pos-ink); letter-spacing: -.2px; }
    .pos-table-area { font-size: .74rem; font-weight: 600; color: var(--pos-muted); text-transform: uppercase; letter-spacing: .04em; }
    .pos-table-area i { color: #adb5c2; }

    /* ---------- Buscador ---------- */
    /* Desplazado a la izquierda (no centrado): queda cerca del nombre de la
       mesa. El espacio libre lo absorbe el botón "Limpiar" que va a su lado
       (margin-right:auto), de modo que ambos quedan juntos a la izquierda. */
    .pos-search { position: relative; flex: 1; max-width: 520px; }
    .pos-search-icon {
        position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
        color: #9aa4b4; font-size: .95rem; pointer-events: none;
    }
    .pos-search-input {
        width: 100%; height: 42px;
        padding: 0 40px 0 40px;
        border: 1.5px solid var(--pos-line); border-radius: 11px;
        background: var(--pos-surface);
        font-size: .93rem; font-weight: 500; color: var(--pos-ink);
        transition: border-color .15s, background-color .15s, box-shadow .15s;
    }
    .pos-search-input::placeholder { color: #a7b0bf; font-weight: 400; }
    .pos-search-input:focus {
        outline: none; background: #fff;
        border-color: var(--pos-brand);
        box-shadow: 0 0 0 3.5px rgba(13,110,253,.12);
    }
    .pos-search-clear {
        position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
        width: 26px; height: 26px; border: none; border-radius: 7px;
        background: #dfe4ec; color: #5b6677; font-size: .7rem;
        display: flex; align-items: center; justify-content: center; cursor: pointer;
    }
    .pos-search-clear:hover { background: #cfd6e2; color: #16202e; }

    /* margin-left:auto empuja este bloque al extremo derecho, así el buscador
       y "Limpiar" quedan juntos a la izquierda tanto si el botón se ve como
       si está oculto (mesa vacía). */
    .pos-topbar-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-left: auto; }
    .pos-ghost-btn {
        display: inline-flex; align-items: center; gap: 7px;
        height: 38px; padding: 0 14px;
        border: 1.5px solid var(--pos-line); border-radius: 10px;
        background: #fff; color: #52607a;
        font-size: .86rem; font-weight: 600; cursor: pointer;
        transition: border-color .15s, color .15s, background-color .15s;
    }
    .pos-ghost-btn:hover { border-color: var(--pos-brand); color: var(--pos-brand); background: #f5f9ff; }

    /* ---------- Cuerpo de 3 columnas ---------- */
    .pos-body {
        flex: 1; min-height: 0;
        display: grid;
        grid-template-columns: 104px minmax(0, 1fr) 396px;
    }

    /* ---------- Categorías ---------- */
    .pos-categories {
        background: #fff; border-right: 1px solid var(--pos-line);
        overflow-y: auto; padding: 10px 8px;
        display: flex; flex-direction: column; gap: 4px;
    }
    .pos-cat {
        border: none; background: transparent; cursor: pointer;
        border-radius: 12px; padding: 11px 4px;
        display: flex; flex-direction: column; align-items: center; gap: 6px;
        color: var(--pos-muted); transition: background-color .15s, color .15s;
    }
    .pos-cat:hover { background: #f2f5fa; color: var(--pos-ink); }
    .pos-cat-icon {
        width: 42px; height: 42px; border-radius: 12px;
        background: #f0f3f8; color: #8a94a4;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; overflow: hidden;
        transition: background-color .15s, color .15s;
    }
    .pos-cat-icon img { width: 100%; height: 100%; object-fit: cover; }
    .pos-cat-name {
        font-size: .7rem; font-weight: 700; line-height: 1.15;
        text-align: center; word-break: break-word;
    }
    .pos-cat.active { background: #eaf2ff; color: var(--pos-brand); }
    .pos-cat.active .pos-cat-icon { background: var(--pos-brand); color: #fff; }

    /* ---------- Catálogo ---------- */
    .pos-catalog { overflow-y: auto; padding: 16px; }
    .pos-grid {
        display: grid; gap: 12px;
        grid-template-columns: repeat(auto-fill, minmax(146px, 1fr));
    }
    .product-item[hidden] { display: none; }

    .product-card {
        width: 100%; padding: 0; cursor: pointer; text-align: left;
        border: 1px solid var(--pos-line); border-radius: 14px;
        background: #fff; overflow: hidden;
        display: flex; flex-direction: column;
        transition: transform .12s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .product-card:hover {
        border-color: #c3d6f7;
        box-shadow: 0 8px 20px -8px rgba(20,40,80,.22);
        transform: translateY(-2px);
    }
    .product-card:active { transform: translateY(0) scale(.975); }

    .product-thumb { position: relative; aspect-ratio: 4 / 3; background: #f0f3f8; }
    .product-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .product-thumb-empty {
        position: absolute; inset: 0;
        display: flex; align-items: center; justify-content: center;
        color: #c2cad6; font-size: 1.9rem;
    }

    .product-flag {
        position: absolute; left: 8px; top: 8px;
        padding: 3px 8px; border-radius: 6px;
        font-size: .66rem; font-weight: 800; letter-spacing: .02em;
        color: #fff; text-transform: uppercase;
    }
    .flag-low { background: #f59f00; }
    .flag-out { background: #495057; }

    /* Botón "+" que aparece al pasar el mouse: refuerza que la tarjeta agrega */
    .product-add {
        position: absolute; right: 8px; bottom: 8px;
        width: 30px; height: 30px; border-radius: 9px;
        background: var(--pos-brand); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: .8rem; opacity: 0; transform: scale(.7);
        transition: opacity .16s, transform .16s;
        box-shadow: 0 4px 10px rgba(13,110,253,.4);
    }
    .product-card:hover .product-add { opacity: 1; transform: scale(1); }

    .product-info {
        padding: 9px 11px 11px;
        display: flex; flex-direction: column; gap: 2px;
    }
    .product-name {
        font-size: .82rem; font-weight: 600; color: var(--pos-ink); line-height: 1.25;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden; min-height: 2.05em;
    }
    .product-price { font-size: .95rem; font-weight: 800; color: var(--pos-brand); }

    .product-card.is-out { opacity: .5; cursor: not-allowed; }
    .product-card.is-out:hover { transform: none; box-shadow: none; border-color: var(--pos-line); }
    .product-card.is-out .product-add { display: none; }

    .pos-empty-search {
        text-align: center; padding: 56px 20px; color: var(--pos-muted);
    }
    .pos-empty-search i { font-size: 2.6rem; opacity: .25; display: block; margin-bottom: 12px; }

    /* ---------- Cuenta ---------- */
    .pos-cart {
        background: #fff; border-left: 1px solid var(--pos-line);
        display: flex; flex-direction: column; min-height: 0;
    }
    .pos-cart-head {
        flex-shrink: 0; padding: 14px 16px;
        border-bottom: 1px solid var(--pos-line);
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
    }
    .pos-cart-title { display: block; font-weight: 800; font-size: .98rem; color: var(--pos-ink); }
    .pos-cart-sub { display: block; font-size: .76rem; font-weight: 600; color: var(--pos-muted); }
    /* Va en la barra superior, pegado al buscador: misma altura (42px) para
       que ambos queden alineados en la misma línea. */
    .pos-clear-btn {
        flex-shrink: 0;
        display: inline-flex; align-items: center; gap: 7px;
        height: 42px; padding: 0 15px; border-radius: 11px;
        border: 1.5px solid #f1d3d6; background: #fff5f5; color: #d6336c;
        font-size: .86rem; font-weight: 700; cursor: pointer;
        transition: background-color .15s, border-color .15s, color .15s;
    }
    .pos-clear-btn:hover { background: #ffe3e6; border-color: #e5a8b0; color: #c02a5c; }
    .pos-clear-btn:active { transform: scale(.97); }
    .pos-clear-btn[hidden] { display: none; }

    .pos-cart-body { flex: 1; min-height: 0; display: flex; flex-direction: column; }

    .cart-lines { flex: 1; min-height: 0; overflow-y: auto; }
    .cart-lines:empty { flex: 0; }

    .cart-line {
        display: flex; gap: 10px; align-items: flex-start;
        padding: 12px 16px; border-bottom: 1px solid #f1f3f7;
    }
    .cart-line:hover { background: #fbfcfe; }
    .cart-line-main { flex: 1; min-width: 0; }
    .cart-line-name {
        display: flex; align-items: center; gap: 6px;
        font-weight: 700; font-size: .88rem; color: var(--pos-ink);
    }
    .cart-line-unit { font-size: .75rem; color: var(--pos-muted); margin-top: 2px; }
    .cart-line-note {
        margin-top: 5px; padding: 4px 8px; border-radius: 7px;
        background: #fff8e6; color: #a97706;
        font-size: .73rem; font-style: italic;
    }
    .cart-note-btn {
        border: none; background: transparent; padding: 0; cursor: pointer;
        color: #c2cad6; font-size: .85rem; flex-shrink: 0; line-height: 1;
    }
    .cart-note-btn:hover { color: #f59f00; }
    .cart-note-btn.has-note { color: #f59f00; }

    .cart-line-side { display: flex; flex-direction: column; align-items: flex-end; gap: 7px; flex-shrink: 0; }
    .cart-line-amount { font-weight: 800; font-size: .92rem; color: var(--pos-ink); }

    .cart-qty {
        display: flex; align-items: center;
        border: 1px solid var(--pos-line); border-radius: 9px; overflow: hidden;
        background: #fff;
    }
    .cart-qty form { display: flex; margin: 0; }
    .cart-qty-btn {
        width: 27px; height: 28px; border: none; background: #f4f6fa;
        color: #52607a; font-size: .68rem; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background-color .13s, color .13s;
    }
    .cart-qty-btn:hover { background: var(--pos-brand); color: #fff; }
    .cart-qty-input {
        width: 38px; height: 28px; border: none;
        border-left: 1px solid var(--pos-line); border-right: 1px solid var(--pos-line);
        text-align: center; font-weight: 800; font-size: .82rem; color: var(--pos-ink);
        background: #fff; -moz-appearance: textfield;
    }
    .cart-qty-input:focus { outline: none; background: #f5f9ff; }
    .cart-qty-input::-webkit-outer-spin-button,
    .cart-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .cart-remove-btn {
        border: none; background: transparent; padding: 0; cursor: pointer;
        color: #c9d0da; font-size: .82rem; transition: color .15s;
    }
    .cart-remove-btn:hover { color: #e03131; }

    /* Mesa vacía */
    .cart-empty {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center; text-align: center;
        color: var(--pos-muted); padding: 24px;
    }
    .cart-empty-icon {
        width: 74px; height: 74px; border-radius: 50%;
        background: var(--pos-surface); color: #c5cdd9;
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; margin-bottom: 14px;
    }
    .cart-empty-title { font-weight: 800; font-size: 1.02rem; color: #5b6677; margin: 0 0 3px; }
    .cart-empty-hint { font-size: .8rem; line-height: 1.5; }

    /* Pie de la cuenta */
    .cart-foot { flex-shrink: 0; border-top: 1px solid var(--pos-line); background: #fff; }
    .cart-summary { padding: 13px 16px 11px; }
    .cart-sum-row {
        display: flex; justify-content: space-between;
        font-size: .83rem; color: var(--pos-muted); margin-bottom: 5px;
    }
    .cart-total {
        display: flex; justify-content: space-between; align-items: baseline;
        margin-top: 9px; padding-top: 11px; border-top: 1.5px dashed #e2e6ee;
        font-weight: 800; font-size: .95rem; color: var(--pos-ink);
    }
    .cart-total-value { font-size: 1.7rem; font-weight: 800; color: var(--pos-brand); letter-spacing: -.5px; }

    .cart-actions {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
        padding: 0 16px 11px;
    }
    .cart-mini-btn {
        display: flex; flex-direction: column; align-items: center; gap: 4px;
        padding: 9px 2px; cursor: pointer;
        border: 1.5px solid var(--pos-line); border-radius: 10px;
        background: #fff; color: #52607a;
        font-size: .68rem; font-weight: 700;
        transition: border-color .15s, color .15s, background-color .15s;
    }
    .cart-mini-btn i { font-size: 1rem; }
    .cart-mini-btn:hover { border-color: var(--pos-brand); color: var(--pos-brand); background: #f5f9ff; }
    .cart-mini-btn.accent-fire:hover { border-color: #fd7e14; color: #fd7e14; background: #fff7f0; }

    .cart-checkout-btn {
        display: flex; align-items: center; justify-content: space-between;
        width: calc(100% - 32px); margin: 0 16px 16px;
        padding: 15px 18px; cursor: pointer;
        border: none; border-radius: 13px;
        background: linear-gradient(135deg, #17a55f, #12864d);
        color: #fff; font-size: 1.02rem; font-weight: 800;
        box-shadow: 0 8px 18px -6px rgba(18,134,77,.55);
        transition: transform .12s, box-shadow .18s, filter .15s;
    }
    .cart-checkout-btn:hover { filter: brightness(1.06); box-shadow: 0 11px 22px -6px rgba(18,134,77,.6); }
    .cart-checkout-btn:active { transform: scale(.985); }
    .cart-checkout-label { display: inline-flex; align-items: center; gap: 8px; }
    .cart-checkout-total { font-size: 1.15rem; letter-spacing: -.3px; }

    /* ---------- Pantallas medianas: la cuenta se estrecha ---------- */
    @media (max-width: 1400px) {
        .pos-body { grid-template-columns: 96px minmax(0, 1fr) 350px; }
        .cart-checkout-btn { font-size: .95rem; }
    }
    @media (max-width: 1150px) {
        .pos-body { grid-template-columns: 88px minmax(0, 1fr) 318px; }
        .pos-grid { grid-template-columns: repeat(auto-fill, minmax(128px, 1fr)); }
        .pos-search { max-width: 320px; }
        .pos-ghost-btn span, .pos-clear-btn span { display: none; }
        .pos-clear-btn { padding: 0 12px; }
    }

    /* --- Panel de acciones (offcanvas) --- */
    /* Entra desde la derecha: se redondean solo las esquinas izquierdas, que
       son las que quedan a la vista sobre la pantalla. */
    .action-panel {
        width: 620px; max-width: 96vw;
        border-left: none;
        border-radius: 18px 0 0 18px;
        overflow: hidden;
        box-shadow: -18px 0 45px -20px rgba(16,28,48,.35);
    }
    .action-panel .offcanvas-header { padding: 1.6rem 2rem 1.1rem; }
    .action-panel .offcanvas-title { font-size: 1.6rem; }
    .action-panel .offcanvas-header .text-uppercase { font-size: .82rem !important; }
    .action-section { display: none; height: 100%; }
    .action-section.d-block { display: block; }
    /* Mismo margen lateral en todas las secciones que el panel de cobro, para
       que el borde izquierdo del contenido no baile al cambiar de sección. */
    .action-section > form > .p-4,
    .action-section > form > .p-3 { padding-left: 1.875rem !important; padding-right: 1.875rem !important; }

    /* Tipografía a escala POS también en Opciones / Mover / Dividir, para que
       no se vean pequeñas al lado del panel de cobro. */
    .action-section .form-label { font-size: .85rem !important; }
    .action-section .form-control,
    .action-section .form-select { font-size: 1.05rem; min-height: 52px; }
    .action-section .input-group-lg > .form-control,
    .action-section .input-group-lg > .input-group-text { font-size: 1.15rem; }
    .action-section p.text-muted { font-size: .95rem; }
    .action-section .btn-lg { padding-top: .85rem; padding-bottom: .85rem; font-size: 1.1rem; }
    .action-panel .form-control, .action-panel .form-select { border-radius: 10px; }
    .action-panel .btn-lg { border-radius: 11px; font-size: 1rem; }

    /* ============================================================
       COBRAR · panel organizado en 3 pasos:
       1 Comprobante · 2 Cliente · 3 Pago
       ============================================================ */
    .checkout-scroll { padding: 22px 30px 12px; }

    /* ---- Bloques del cobro ---- */
    .checkout-step { margin-bottom: 26px; }

    /* ---- Tipo de comprobante ---- */
    .doc-type-group { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }
    .doc-type-btn {
        display: flex; flex-direction: column; align-items: center; gap: 6px;
        padding: 15px 4px; cursor: pointer; margin: 0;
        border: 1.5px solid #e6e9ef; border-radius: 12px;
        background: #fff; color: #6d7889;
        font-size: .92rem; font-weight: 700;
        transition: border-color .15s, color .15s, background-color .15s;
    }
    .doc-type-btn i { font-size: 1.45rem; }
    .doc-type-btn:hover { border-color: #b9cdf0; color: #0d6efd; }
    .btn-check:checked + .doc-type-btn {
        border-color: #0d6efd; background: #eef5ff; color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13,110,253,.1);
    }

    /* ---- Consulta de documento (DNI / RUC) ---- */
    .doc-lookup { display: flex; gap: 10px; }
    .doc-lookup-field {
        flex: 1; display: flex; align-items: center;
        border: 1.5px solid #e6e9ef; border-radius: 12px;
        background: #fff; overflow: hidden;
        transition: border-color .15s, box-shadow .15s;
    }
    .doc-lookup-field:focus-within {
        border-color: #0d6efd; box-shadow: 0 0 0 3.5px rgba(13,110,253,.12);
    }
    .doc-lookup-badge {
        flex-shrink: 0; margin: 6px; padding: 7px 11px; border-radius: 8px;
        background: #eef1f6; color: #97a1b1;
        font-size: .78rem; font-weight: 800; letter-spacing: .05em;
        transition: background-color .15s, color .15s;
    }
    .doc-lookup-badge.is-valid { background: #0d6efd; color: #fff; }
    .doc-lookup-input {
        flex: 1; min-width: 0; border: none; outline: none;
        height: 52px; padding: 0 12px 0 4px; background: transparent;
        font-size: 1.25rem; font-weight: 700; letter-spacing: .6px; color: #16202e;
    }
    .doc-lookup-input::placeholder { font-size: 1rem; font-weight: 400; letter-spacing: 0; color: #a7b0bf; }
    /* Solo lupa: cuadrado, mismo alto que el campo. Ocupa mucho menos que el
       botón con texto y deja el número de documento más ancho. */
    .doc-lookup-btn {
        flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
        width: 56px; height: 56px; cursor: pointer;
        border: none; border-radius: 12px;
        background: #0d6efd; color: #fff;
        font-size: 1.3rem;
        transition: filter .15s;
    }
    .doc-lookup-btn:hover:not(:disabled) { filter: brightness(1.08); }
    .doc-lookup-btn:disabled { opacity: .65; cursor: default; }

    .doc-lookup-status {
        min-height: 20px; margin: 9px 0 0;
        font-size: .88rem; font-weight: 600; color: #8a94a4;
    }
    .doc-lookup-status.is-ok { color: #12864d; }
    .doc-lookup-status.is-error { color: #d6336c; }
    .doc-lookup-status.is-warn { color: #b57500; }
    .doc-lookup-status.is-loading { color: #1667d4; }

    .doc-address {
        display: flex; gap: 9px; margin-top: 11px;
        padding: 11px 14px; border-radius: 11px;
        background: #f5f8fd; border: 1px solid #e3ebf7;
        font-size: .88rem; line-height: 1.5; color: #55637a;
    }
    .doc-address i { color: #7f9dcb; margin-top: 2px; flex-shrink: 0; }
    .doc-address[hidden] { display: none; }

    /* ---- Campos genéricos ---- */
    .checkout-field { margin-top: 16px; }
    .checkout-label {
        display: block; margin-bottom: 7px;
        font-size: .85rem; font-weight: 700; color: #7d8797;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .checkout-input-wrap { position: relative; }
    .checkout-input {
        width: 100%; height: 54px; padding: 0 46px 0 16px;
        border: 1.5px solid #e6e9ef; border-radius: 12px;
        background: #fff; font-size: 1.08rem; font-weight: 600; color: #16202e;
        transition: border-color .15s, box-shadow .15s;
    }
    .checkout-input::placeholder { font-size: 1rem; font-weight: 400; color: #a7b0bf; }
    .checkout-input:focus {
        outline: none; border-color: #0d6efd;
        box-shadow: 0 0 0 3.5px rgba(13,110,253,.12);
    }
    .checkout-input-clear {
        position: absolute; right: 9px; top: 50%; transform: translateY(-50%);
        width: 32px; height: 32px; border: none; border-radius: 8px;
        background: #eef1f6; color: #7d8797; cursor: pointer;
        display: flex; align-items: center; justify-content: center; font-size: .8rem;
    }
    .checkout-input-clear:hover { background: #e0e5ee; color: #16202e; }

    /* ---- Método de pago ---- */
    .pay-method-group { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .pay-method-btn {
        display: flex; align-items: center; justify-content: center; gap: 10px;
        padding: 17px 8px; cursor: pointer; margin: 0;
        border: 1.5px solid #e6e9ef; border-radius: 12px;
        background: #fff; color: #6d7889;
        font-size: 1.08rem; font-weight: 700;
        transition: border-color .15s, color .15s, background-color .15s;
    }
    .pay-method-btn i { font-size: 1.4rem; }
    .btn-check:checked + .pay-method-btn {
        border-color: #12864d; background: #eafaf1; color: #12864d;
        box-shadow: 0 0 0 3px rgba(18,134,77,.1);
    }
    #payCard:checked + .pay-method-btn {
        border-color: #0d6efd; background: #eef5ff; color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13,110,253,.1);
    }

    /* ---- Efectivo ---- */
    .cash-input-wrap {
        display: flex; align-items: center;
        border: 1.5px solid #e6e9ef; border-radius: 11px;
        background: #fff; overflow: hidden;
        transition: border-color .15s, box-shadow .15s;
    }
    .cash-input-wrap:focus-within {
        border-color: #12864d; box-shadow: 0 0 0 3.5px rgba(18,134,77,.13);
    }
    .cash-currency {
        flex-shrink: 0; padding: 0 6px 0 18px;
        font-size: 1.35rem; font-weight: 800; color: #7d8797;
    }
    .cash-input {
        flex: 1; min-width: 0; border: none; outline: none; background: transparent;
        height: 64px; padding: 0 18px 0 4px;
        font-size: 1.95rem; font-weight: 800; color: #12864d;
        -moz-appearance: textfield;
    }
    .cash-input::-webkit-outer-spin-button,
    .cash-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .cash-quick {
        display: grid; grid-template-columns: repeat(6, 1fr); gap: 7px; margin-top: 10px;
    }
    .cash-quick-btn {
        padding: 12px 2px; cursor: pointer;
        border: 1.5px solid #e6e9ef; border-radius: 10px;
        background: #fff; color: #55637a;
        font-size: .98rem; font-weight: 700;
        transition: border-color .15s, color .15s, background-color .15s;
    }
    .cash-quick-btn:hover { border-color: #12864d; color: #12864d; background: #f2fbf6; }
    .cash-quick-btn[data-exact] { font-size: .85rem; }

    .change-row {
        display: flex; align-items: baseline; justify-content: space-between;
        margin-top: 14px; padding: 15px 18px; border-radius: 12px;
        background: #f2fbf6; border: 1px solid #d6f0e2;
    }
    .change-label {
        font-size: .88rem; font-weight: 800; color: #12864d;
        text-transform: uppercase; letter-spacing: .05em;
    }
    .change-value { font-size: 1.85rem; font-weight: 800; color: #12864d; letter-spacing: -.4px; }
    /* Efectivo insuficiente: el bloque se vuelve rojo y muestra cuánto falta */
    .change-row.is-short { background: #fff5f6; border-color: #f6d4da; }
    .change-row.is-short .change-label,
    .change-row.is-short .change-value { color: #d6336c; }

    /* ---- Pie fijo ---- */
    .checkout-footer {
        flex-shrink: 0; padding: 16px 30px 22px;
        border-top: 1px solid #e6e9ef; background: #fff;
    }
    .checkout-submit {
        display: flex; align-items: center; justify-content: space-between;
        width: 100%; padding: 19px 26px; cursor: pointer;
        border: none; border-radius: 14px;
        background: linear-gradient(135deg, #17a55f, #12864d);
        color: #fff; font-size: 1.2rem; font-weight: 800;
        box-shadow: 0 8px 18px -6px rgba(18,134,77,.55);
        transition: filter .15s, transform .12s, box-shadow .18s;
    }
    .checkout-submit:hover { filter: brightness(1.06); box-shadow: 0 11px 22px -6px rgba(18,134,77,.6); }
    .checkout-submit:active { transform: scale(.985); }
    .checkout-submit-main { display: inline-flex; align-items: center; gap: 11px; }
    .checkout-submit-total { font-size: 1.5rem; letter-spacing: -.4px; }

    /* En pantallas cortas se recortan los espacios verticales (no las fuentes)
       para que los 3 pasos sigan entrando sin tanto scroll. */
    @media (max-height: 800px) {
        .checkout-scroll { padding-top: 16px; }
        .checkout-step { margin-bottom: 18px; }
        .doc-type-btn { padding: 11px 4px; }
        .pay-method-btn { padding: 13px 8px; }
        .cash-input { height: 56px; font-size: 1.7rem; }
        .checkout-submit { padding: 15px 24px; }
    }

    /* --- Dividir cuenta --- */
    .split-select-all { background: #f8f9fa; }
    .split-item { cursor: pointer; transition: background-color .15s; }
    .split-item:hover { background-color: #f8f9fa; }
    .split-item-list { border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden; }
    .split-item:last-child { border-bottom: 0 !important; }
    /* Escala POS: casillas y textos cómodos de tocar en pantalla táctil */
    .split-item .form-check-input { width: 1.35rem; height: 1.35rem; margin-top: 0; }
    .split-item .fw-bold { font-size: 1rem; }
    .split-item .small { font-size: .85rem; }
    #splitCheckAll { width: 1.35rem; height: 1.35rem; }
    #splitTotalDisplay { font-size: 1.85rem !important; }
</style>
@endsection