{{-- Formulario de creación de producto — cargado en el panel lateral (AJAX) --}}
<form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" id="productCreateForm">
    @csrf

    <div class="mb-3">
        <label class="form-label fw-bold small text-uppercase text-muted">Nombre del Producto</label>
        <input type="text" name="name" class="form-control form-control-lg" placeholder="Ej: Lomo Saltado" required>
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <label class="form-label fw-bold small text-uppercase text-muted">Categoría</label>
            <select name="category_id" class="form-select" required>
                <option value="" selected disabled>-- Seleccionar --</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6">
            <label class="form-label fw-bold small text-uppercase text-muted">Precio de Venta</label>
            <div class="input-group">
                <span class="input-group-text">{{ $currency ?? 'S/' }}</span>
                <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold small text-uppercase text-muted">Stock Inicial</label>
        <input type="number" name="stock" class="form-control" value="0" min="0">
        <small class="text-muted">Se creará un registro de entrada en el Kardex.</small>
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold small text-uppercase text-muted">Imagen (Opcional)</label>
        <input type="file" name="image" class="form-control" accept="image/*">
    </div>

    <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">
        <i class="bi bi-check-lg me-1"></i> Guardar Producto
    </button>
</form>
