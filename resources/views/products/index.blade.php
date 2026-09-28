@extends('layouts.app')

@section('content')
<div class="container-fluid products-page">
    <div class="card border-0 shadow-sm">
        {{-- ===== FILTROS ===== --}}
        <div class="card-header bg-white py-3">
            <form action="{{ route('products.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0" placeholder="Buscar producto...">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select name="category_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todas las categorías</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ (string) $categoryId === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Todo estado</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Activos</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactivos</option>
                        <option value="low_stock" {{ $status === 'low_stock' ? 'selected' : '' }}>Stock bajo</option>
                    </select>
                </div>
                <div class="col-12 col-md-1 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1 fw-bold" type="submit" title="Filtrar"><i class="bi bi-filter"></i></button>
                    @if($search || $categoryId || $status)
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
                <div class="col-12 col-md-4 d-flex gap-2 justify-content-md-end">
                    <a href="{{ route('inventory.logs') }}" class="btn btn-dark fw-bold shadow-sm text-nowrap">
                        <i class="bi bi-clock-history me-2"></i> Kardex
                    </a>
                    <button type="button" class="btn btn-primary fw-bold shadow-sm text-nowrap" onclick="openCreatePanel()">
                        <i class="bi bi-plus-lg me-2"></i> Nuevo Producto
                    </button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            {{-- ===== TABLA (desktop) ===== --}}
            <div class="products-table-scroll d-none d-lg-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        @if($product->image)
                                            <img src="{{ asset('storage/'.$product->image) }}" class="rounded me-3 border product-thumb" alt="">
                                        @else
                                            <div class="rounded me-3 border product-thumb d-flex align-items-center justify-content-center text-muted bg-light"><i class="bi bi-image"></i></div>
                                        @endif
                                        <div>
                                            <div class="fw-bold">{{ $product->name }}</div>
                                            @if(!$product->is_saleable)
                                                <span class="badge bg-secondary" style="font-size: 0.65rem;">
                                                    <i class="bi bi-eye-slash-fill me-1"></i> SOLO INSUMO
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $product->category->name ?? '—' }}</span></td>
                                <td class="fw-bold text-primary">{{ $currency }}{{ number_format($product->price, 2) }}</td>
                                <td class="text-center">
                                    @if($product->stock <= 5)
                                        <span class="badge bg-danger">Crítico: {{ $product->stock }}</span>
                                    @elseif($product->stock <= 15)
                                        <span class="badge bg-warning text-dark">Bajo: {{ $product->stock }}</span>
                                    @else
                                        <span class="badge bg-success">{{ $product->stock }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <form action="{{ route('products.toggle', $product->id) }}" method="POST">
                                        @csrf
                                        <button class="btn btn-sm {{ $product->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }} rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">
                                            {{ $product->is_active ? 'ACTIVO' : 'INACTIVO' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button class="btn btn-sm btn-outline-dark" onclick="adjustStock({{ $product }})" title="Ajustar Stock">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Editar" onclick="openEditPanel({{ $product->id }})"><i class="bi bi-pencil"></i></button>
                                        <form action="{{ route('products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('¿Eliminar producto?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="products-empty-state">
                                        <i class="bi bi-{{ ($search || $categoryId || $status) ? 'search' : 'box-seam' }}"></i>
                                        <h6 class="fw-bold text-dark mb-1 mt-3">
                                            {{ ($search || $categoryId || $status) ? 'Sin resultados' : 'Aún no hay productos' }}
                                        </h6>
                                        <small class="text-muted">
                                            {{ ($search || $categoryId || $status) ? 'Ajusta los filtros para encontrar lo que buscas.' : 'Registra tu primer producto para empezar.' }}
                                        </small>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ===== CARDS (mobile / tablet) ===== --}}
            <div class="d-lg-none">
                @forelse($products as $product)
                    <div class="product-card-row">
                        <div class="d-flex align-items-center gap-3">
                            @if($product->image)
                                <img src="{{ asset('storage/'.$product->image) }}" class="rounded border product-thumb" alt="">
                            @else
                                <div class="rounded border product-thumb d-flex align-items-center justify-content-center text-muted bg-light"><i class="bi bi-image"></i></div>
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="fw-bold text-truncate">{{ $product->name }}</span>
                                    <span class="fw-bold text-primary text-nowrap">{{ $currency }}{{ number_format($product->price, 2) }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                    <span class="badge bg-light text-dark border">{{ $product->category->name ?? '—' }}</span>
                                    @if($product->stock <= 5)
                                        <span class="badge bg-danger">Crítico: {{ $product->stock }}</span>
                                    @elseif($product->stock <= 15)
                                        <span class="badge bg-warning text-dark">Bajo: {{ $product->stock }}</span>
                                    @else
                                        <span class="badge bg-success">{{ $product->stock }}</span>
                                    @endif
                                    @if(!$product->is_saleable)
                                        <span class="badge bg-secondary" style="font-size: 0.65rem;">SOLO INSUMO</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <form action="{{ route('products.toggle', $product->id) }}" method="POST">
                                @csrf
                                <button class="btn btn-sm {{ $product->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }} rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">
                                    {{ $product->is_active ? 'ACTIVO' : 'INACTIVO' }}
                                </button>
                            </form>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-dark" onclick="adjustStock({{ $product }})" title="Ajustar Stock">
                                    <i class="bi bi-arrow-left-right"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" title="Editar" onclick="openEditPanel({{ $product->id }})"><i class="bi bi-pencil"></i></button>
                                <form action="{{ route('products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('¿Eliminar producto?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5">
                        <div class="products-empty-state">
                            <i class="bi bi-{{ ($search || $categoryId || $status) ? 'search' : 'box-seam' }}"></i>
                            <h6 class="fw-bold text-dark mb-1 mt-3">
                                {{ ($search || $categoryId || $status) ? 'Sin resultados' : 'Aún no hay productos' }}
                            </h6>
                            <small class="text-muted">
                                {{ ($search || $categoryId || $status) ? 'Ajusta los filtros para encontrar lo que buscas.' : 'Registra tu primer producto para empezar.' }}
                            </small>
                        </div>
                    </div>
                @endforelse
            </div>

            @if($products->hasPages())
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top flex-wrap gap-2">
                    <small class="text-muted">
                        Mostrando {{ $products->firstItem() }}–{{ $products->lastItem() }} de {{ $products->total() }}
                    </small>
                    {{ $products->onEachSide(1)->links('pagination.client-orders') }}
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .products-page .dash-kpi {
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 14px;
        padding: 16px 18px;
        height: 100%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        position: relative;
        overflow: hidden;
        isolation: isolate;
        display: flex;
        flex-direction: column;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .products-page .dash-kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.07); }
    .products-page .dash-kpi-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .products-page .bg-primary-soft { background: rgba(13,110,253,0.1); }
    .products-page .bg-success-soft { background: rgba(25,135,84,0.1); }
    .products-page .bg-danger-soft { background: rgba(220,53,69,0.1); }
    .products-page .bg-info-soft { background: rgba(13,202,240,0.12); }
    .products-page .dash-kpi-watermark {
        position: absolute; top: 50%; right: -14px; transform: translateY(-50%);
        font-size: 4.5rem; opacity: 0.07; z-index: -1; pointer-events: none;
    }
    .products-page .dash-kpi-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #8891a0; letter-spacing: .3px; }
    .products-page .dash-kpi-value { font-size: 1.5rem; font-weight: 800; color: #1c2333; }

    .products-page .product-thumb { width: 44px; height: 44px; object-fit: cover; flex-shrink: 0; }

    .products-page .products-table-scroll { max-height: 65vh; overflow-y: auto; overflow-x: auto; }
    .products-page .products-table-scroll thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; }
    .products-page .products-table-scroll table { min-width: 900px; }

    .products-page .product-card-row {
        padding: 14px 16px;
        border-bottom: 1px solid #f0f1f3;
    }
    .products-page .product-card-row:last-child { border-bottom: none; }
    .products-page .min-w-0 { min-width: 0; }

    .products-page .products-empty-state {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .products-page .products-empty-state i {
        font-size: 2.25rem; color: #adb5bd; background: #f1f3f5;
        width: 64px; height: 64px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
</style>

@push('modals')
{{-- ===== PANEL LATERAL: AJUSTAR STOCK ===== --}}
<div class="offcanvas offcanvas-end product-side-panel" tabindex="-1" id="adjustStockPanel" aria-labelledby="adjustStockPanelLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;">Inventario</span>
            <h4 class="offcanvas-title fw-bold mb-0" id="adjustStockPanelLabel"><i class="bi bi-arrow-left-right me-2"></i>Ajustar Stock</h4>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <form id="adjustStockForm" method="POST" class="d-flex flex-column h-100">
            @csrf
            <div class="p-4 flex-grow-1 overflow-auto">
                <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded-3">
                    <div class="product-side-panel-thumb" id="adjust_thumb"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <small class="text-muted d-block">Producto seleccionado</small>
                        <strong id="adjust_name" class="fs-5"></strong>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Tipo de Movimiento</label>
                    <select name="type" class="form-select form-select-lg">
                        <option value="add">📥 Entrada (Compra/Reposición)</option>
                        <option value="subtract">🗑️ Salida (Merma/Pérdida/Uso)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Cantidad</label>
                    <input type="number" name="quantity" class="form-control form-control-lg" min="1" required placeholder="Ej: 10">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small text-uppercase text-muted">Motivo (Opcional)</label>
                    <input type="text" name="note" class="form-control" placeholder="Ej: Compra semanal">
                </div>
            </div>
            <div class="p-3 border-top bg-light">
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                    <i class="bi bi-check-lg me-1"></i> Guardar Movimiento
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ===== PANEL LATERAL: EDITAR PRODUCTO (cargado vía AJAX) ===== --}}
<div class="offcanvas offcanvas-end product-side-panel product-edit-panel" tabindex="-1" id="editProductPanel" aria-labelledby="editProductPanelLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;">Editar registro</span>
            <h4 class="offcanvas-title fw-bold mb-0" id="editProductPanelLabel"><i class="bi bi-pencil-square me-2"></i>Editar Producto</h4>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4" id="editProductPanelBody">
        <div class="text-center py-5 product-panel-loading">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted small mt-2 mb-0">Cargando producto...</p>
        </div>
    </div>
</div>

{{-- ===== PANEL LATERAL: NUEVO PRODUCTO (cargado vía AJAX) ===== --}}
<div class="offcanvas offcanvas-end product-side-panel product-edit-panel" tabindex="-1" id="createProductPanel" aria-labelledby="createProductPanelLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;">Nuevo registro</span>
            <h4 class="offcanvas-title fw-bold mb-0" id="createProductPanelLabel"><i class="bi bi-plus-circle me-2"></i>Nuevo Producto</h4>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4" id="createProductPanelBody">
        <div class="text-center py-5 product-panel-loading">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted small mt-2 mb-0">Cargando formulario...</p>
        </div>
    </div>
</div>
@endpush

<style>
    .product-side-panel { width: 460px; max-width: 92vw; border-top-left-radius: 20px; border-bottom-left-radius: 20px; overflow: hidden; }
    .product-side-panel .offcanvas-header { padding: 1.5rem 1.5rem 1rem; }
    .product-side-panel .offcanvas-title { font-size: 1.25rem; }
    .product-side-panel-thumb {
        width: 56px; height: 56px; border-radius: 12px;
        background: #fff; border: 1px solid #e9ecef;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; color: #adb5bd; flex-shrink: 0;
        object-fit: cover; overflow: hidden;
    }
    .product-side-panel-thumb img { width: 100%; height: 100%; object-fit: cover; }
</style>

<script>
    function adjustStock(product) {
        document.getElementById('adjust_name').innerText = product.name;
        document.getElementById('adjustStockForm').action = "{{ url('/products') }}/" + product.id + "/adjust";

        const thumb = document.getElementById('adjust_thumb');
        if (product.image) {
            thumb.innerHTML = `<img src="{{ asset('storage') }}/${product.image}" alt="">`;
        } else {
            thumb.innerHTML = '<i class="bi bi-box-seam"></i>';
        }

        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('adjustStockPanel')).show();
    }

    const productsEditUrlBase = @json(url('/products'));

    function openEditPanel(productId) {
        const panelEl = document.getElementById('editProductPanel');
        const body = document.getElementById('editProductPanelBody');
        const panel = bootstrap.Offcanvas.getOrCreateInstance(panelEl);

        body.innerHTML = `
            <div class="text-center py-5 product-panel-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted small mt-2 mb-0">Cargando producto...</p>
            </div>
        `;
        panel.show();

        fetch(`${productsEditUrlBase}/${productId}/edit`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                body.innerHTML = html;
                // Los <script> insertados vía innerHTML no se ejecutan solos: los reinyectamos manualmente.
                body.querySelectorAll('script').forEach(oldScript => {
                    const newScript = document.createElement('script');
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    } else {
                        newScript.textContent = oldScript.textContent;
                    }
                    oldScript.replaceWith(newScript);
                });
            })
            .catch(() => {
                body.innerHTML = '<div class="alert alert-danger m-0">No se pudo cargar el producto. Intenta de nuevo.</div>';
            });
    }

    const productsCreateUrl = "{{ route('products.create') }}";

    function openCreatePanel() {
        const panelEl = document.getElementById('createProductPanel');
        const body = document.getElementById('createProductPanelBody');
        const panel = bootstrap.Offcanvas.getOrCreateInstance(panelEl);

        body.innerHTML = `
            <div class="text-center py-5 product-panel-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted small mt-2 mb-0">Cargando formulario...</p>
            </div>
        `;
        panel.show();

        fetch(productsCreateUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                body.innerHTML = html;
                body.querySelectorAll('script').forEach(oldScript => {
                    const newScript = document.createElement('script');
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    } else {
                        newScript.textContent = oldScript.textContent;
                    }
                    oldScript.replaceWith(newScript);
                });
            })
            .catch(() => {
                body.innerHTML = '<div class="alert alert-danger m-0">No se pudo cargar el formulario. Intenta de nuevo.</div>';
            });
    }
</script>
@endsection
