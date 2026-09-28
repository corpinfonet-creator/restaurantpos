{{-- Formulario de edición de producto — usado tanto en la página completa como en el panel lateral (AJAX) --}}
<form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data" id="productEditForm">
    @csrf @method('PUT')

    <div class="mb-3">
        <label class="form-label fw-bold small text-uppercase text-muted">Nombre del Producto</label>
        <input type="text" name="name" class="form-control form-control-lg" value="{{ $product->name }}" required>
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <label class="form-label fw-bold small text-uppercase text-muted">Categoría</label>
            <select name="category_id" class="form-select" required>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6">
            <label class="form-label fw-bold small text-uppercase text-muted">Precio</label>
            <div class="input-group">
                <span class="input-group-text">{{ $currency ?? 'S/' }}</span>
                <input type="number" step="0.01" name="price" class="form-control" value="{{ $product->price }}" required>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold small text-uppercase text-muted">Stock Actual (Lectura)</label>
        <input type="text" class="form-control bg-light" value="{{ $product->stock }}" readonly>
        <small class="text-muted">Si tiene receta, el stock dependerá de los insumos.</small>
    </div>

    <div class="mb-3">
        <div class="form-check form-switch p-3 border rounded bg-light">
            <input type="hidden" name="is_saleable" value="0">
            <input class="form-check-input" type="checkbox" name="is_saleable" value="1" id="saleableCheck" {{ $product->is_saleable ? 'checked' : '' }}>
            <label class="form-check-label fw-bold ms-2" for="saleableCheck">
                Disponible para Venta en POS
            </label>
            <div class="small text-muted ms-2 mt-1">
                Si desmarcas esto, el producto servirá como <strong>Insumo</strong> (para recetas) pero no aparecerá en el menú de ventas.
            </div>
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold small text-uppercase text-muted">Imagen</label>
        <input type="file" name="image" class="form-control" accept="image/*">
    </div>

    <div class="card border-0 bg-light mb-4">
        <div class="card-header bg-primary text-white fw-bold py-3 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-basket me-2"></i>Receta / Insumos</span>
            <button type="button" class="btn btn-sm btn-light text-primary fw-bold" onclick="addIngredient()">
                <i class="bi bi-plus-lg"></i> Agregar
            </button>
        </div>
        <div class="card-body p-3" style="max-height: 320px; overflow-y: auto;">
            <small class="d-block text-muted mb-3">Selecciona los insumos que componen este plato. Al venderlo, se descontarán automáticamente.</small>

            <div id="ingredients-container">
                @foreach($product->ingredients as $index => $ing)
                    <div class="card mb-2 border-0 shadow-sm ingredient-row">
                        <div class="card-body p-2 d-flex gap-2 align-items-center">
                            <select name="ingredients[{{ $index }}][id]" class="form-select form-select-sm" required>
                                <option value="">-- Insumo --</option>
                                @foreach($allProducts as $p)
                                    <option value="{{ $p->id }}" {{ $ing->id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" name="ingredients[{{ $index }}][quantity]" class="form-control form-control-sm" style="width: 80px;" placeholder="Cant." value="{{ $ing->pivot->quantity }}" required>
                            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('.ingredient-row').remove()"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="no-ingredients-msg" class="text-center text-muted py-4 {{ $product->ingredients->count() > 0 ? 'd-none' : '' }}">
                <i class="bi bi-box-seam fs-1 opacity-25"></i>
                <p class="small mb-0">Sin receta configurada.</p>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">
        <i class="bi bi-check-lg me-1"></i> Guardar Cambios
    </button>
</form>

<script>
    (function () {
        let ingIndex = {{ $product->ingredients->count() }};
        const allProductsOptions = @json($allProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'stock' => $p->stock]));

        window.addIngredient = function () {
            document.getElementById('no-ingredients-msg').classList.add('d-none');

            const container = document.getElementById('ingredients-container');
            const row = document.createElement('div');
            row.className = 'card mb-2 border-0 shadow-sm ingredient-row';

            let optionsHtml = '<option value="">-- Seleccionar Insumo --</option>';
            allProductsOptions.forEach(p => {
                optionsHtml += `<option value="${p.id}">${p.name} (Stock: ${p.stock})</option>`;
            });

            row.innerHTML = `
                <div class="card-body p-2 d-flex gap-2 align-items-center">
                    <select name="ingredients[${ingIndex}][id]" class="form-select form-select-sm" required>${optionsHtml}</select>
                    <input type="number" step="0.01" name="ingredients[${ingIndex}][quantity]" class="form-control form-control-sm" style="width: 80px;" placeholder="Cant." required>
                    <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('.ingredient-row').remove()"><i class="bi bi-trash"></i></button>
                </div>
            `;
            container.appendChild(row);
            ingIndex++;
        };
    })();
</script>
