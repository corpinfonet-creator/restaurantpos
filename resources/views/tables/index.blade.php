@extends('layouts.app')

@section('content')
<div class="container-fluid salon-page">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h2 class="fw-bold text-dark mb-0"><i class="bi bi-grid-3x3-gap-fill me-2"></i> Mesas / Salón</h2>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-dark fw-bold" onclick="openAreaPanel()">
                <i class="bi bi-plus-circle me-2"></i> Nueva Zona
            </button>
            <button type="button" class="btn btn-outline-primary fw-bold" onclick="openTablePanel()">
                <i class="bi bi-plus-lg me-2"></i> Nueva Mesa
            </button>
            <button class="btn btn-success fw-bold px-4 shadow-sm" onclick="savePositions()" id="btnSave">
                <i class="bi bi-save me-2"></i> Guardar Diseño
            </button>
        </div>
    </div>

    @if($areas->isEmpty())
        <div class="salon-empty-state text-center py-5">
            <i class="bi bi-grid-3x3-gap"></i>
            <h5 class="fw-bold text-dark mt-3 mb-1">Aún no hay zonas creadas</h5>
            <p class="text-muted mb-4">Crea tu primera zona (ej. Salón Principal, Terraza) para empezar a distribuir mesas.</p>
            <button type="button" class="btn btn-primary fw-bold px-4" onclick="openAreaPanel()">
                <i class="bi bi-plus-circle me-2"></i> Crear primera zona
            </button>
        </div>
    @else
        <ul class="nav salon-tabs mb-3" id="areaTabs" role="tablist">
            @foreach($areas as $index => $area)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $index == 0 ? 'active' : '' }} fw-bold"
                            id="tab-{{ $area->id }}"
                            data-bs-toggle="tab"
                            data-bs-target="#area-{{ $area->id }}"
                            type="button" role="tab">
                        {{ $area->name }}
                        <span class="salon-tab-count">{{ $area->tables->count() }}</span>
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content" id="areaTabsContent">
            @foreach($areas as $index => $area)
                <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" id="area-{{ $area->id }}" role="tabpanel">

                    <div class="salon-toolbar d-flex justify-content-between align-items-center mb-3">
                        <small class="text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-arrows-move"></i> Arrastra las mesas para acomodarlas y luego presiona <b class="text-dark">Guardar Diseño</b>.
                        </small>
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none fw-bold" onclick="autoArrange('area-{{ $area->id }}')">
                                <i class="bi bi-grid-3x3-gap me-1"></i> Ordenar automáticamente
                            </button>
                            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none fw-bold" onclick="confirmDeleteArea({{ $area->id }}, '{{ $area->name }}')">
                                <i class="bi bi-trash3 me-1"></i> Eliminar zona
                            </button>
                        </div>
                        <form id="deleteAreaForm-{{ $area->id }}" action="{{ route('tables.destroyArea', $area->id) }}" method="POST" class="d-none">
                            @csrf @method('DELETE')
                        </form>
                    </div>

                    <div class="salon-canvas" style="height: 640px;">
                        @if($area->tables->isEmpty())
                            <div class="salon-canvas-empty">
                                <i class="bi bi-columns-gap"></i>
                                <p class="mb-0">Esta zona no tiene mesas todavía.</p>
                                <button type="button" class="btn btn-sm btn-primary fw-bold mt-3" onclick="openTablePanel({{ $area->id }})">
                                    <i class="bi bi-plus-lg me-1"></i> Agregar mesa aquí
                                </button>
                            </div>
                        @else
                            <div class="salon-canvas-inner position-relative">
                                @foreach($area->tables as $table)
                                    <div class="draggable-table status-{{ $table->status }}"
                                         id="table-{{ $table->id }}"
                                         data-id="{{ $table->id }}"
                                         style="left: {{ $table->x_pos }}px; top: {{ $table->y_pos }}px;">

                                        <button type="button" class="draggable-table-remove" title="Borrar mesa" onclick="confirmDeleteTable({{ $table->id }}, '{{ $table->name }}')">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                        <button type="button" class="draggable-table-edit" title="Personalizar mesa"
                                                onclick="openTablePanel({{ $table->area_id }}, {{ $table->id }}, '{{ addslashes($table->name) }}')">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <form id="deleteTableForm-{{ $table->id }}" action="{{ route('tables.destroyTable', $table->id) }}" method="POST" class="d-none">
                                            @csrf @method('DELETE')
                                        </form>

                                        <img src="{{ $table->image ? asset('storage/'.$table->image) : asset('images/meza.webp') }}" alt="Mesa" class="draggable-table-img" draggable="false">
                                        <div class="draggable-table-label">
                                            <span class="draggable-table-name">{{ $table->name }}</span>
                                            <span class="draggable-table-status">{{ $table->status == 'available' ? 'Libre' : 'Ocupada' }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('modals')
{{-- ===== PANEL LATERAL: NUEVA ZONA ===== --}}
<div class="offcanvas offcanvas-end salon-side-panel" tabindex="-1" id="areaPanel" aria-labelledby="areaPanelLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;">Salón</span>
            <h4 class="offcanvas-title fw-bold mb-0" id="areaPanelLabel"><i class="bi bi-diagram-3 me-2"></i>Nueva Zona</h4>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <form action="{{ route('tables.storeArea') }}" method="POST" class="d-flex flex-column h-100">
            @csrf
            <div class="p-4 flex-grow-1">
                <p class="text-muted mb-4">Una zona agrupa mesas por ambiente: Salón Principal, Terraza, Barra, etc.</p>
                <div class="mb-2">
                    <label class="form-label fw-bold small text-uppercase text-muted">Nombre de la zona</label>
                    <input type="text" name="name" class="form-control form-control-lg" placeholder="Ej: Terraza" required autofocus>
                </div>
            </div>
            <div class="p-3 border-top bg-light">
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                    <i class="bi bi-check-lg me-1"></i> Crear Zona
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ===== PANEL LATERAL: NUEVA MESA / PERSONALIZAR MESA ===== --}}
<div class="offcanvas offcanvas-end salon-side-panel" tabindex="-1" id="tablePanel" aria-labelledby="tablePanelLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;">Salón</span>
            <h4 class="offcanvas-title fw-bold mb-0" id="tablePanelLabel"><i class="bi bi-square me-2"></i>Nueva Mesa</h4>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <form id="tablePanelForm" action="{{ route('tables.storeTable') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column h-100">
            @csrf
            <div class="p-4 flex-grow-1">
                <div class="mb-4 text-center">
                    <label class="form-label fw-bold small text-uppercase text-muted d-block mb-2">Imagen de la mesa</label>
                    <label for="tablePanelImageInput" class="table-image-picker" id="tablePanelImagePicker">
                        <img id="tablePanelImagePreview" src="" alt="Vista previa" class="d-none">
                        <span class="table-image-picker-placeholder" id="tablePanelImagePlaceholder">
                            <i class="bi bi-camera-fill"></i>
                            <small>Agregar foto</small>
                        </span>
                        <span class="table-image-picker-overlay">
                            <i class="bi bi-camera-fill"></i> Elegir foto
                        </span>
                    </label>
                    <input type="file" name="image" id="tablePanelImageInput" accept="image/*" class="d-none" onchange="previewTableImage(this)">
                    <small class="text-muted d-block mt-2">Opcional. Se optimiza automáticamente a WebP.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Nombre de la mesa</label>
                    <input type="text" name="name" id="tablePanelNameInput" class="form-control form-control-lg" placeholder="Ej: Mesa 5" required autofocus>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small text-uppercase text-muted">Zona</label>
                    <select name="area_id" id="tablePanelAreaSelect" class="form-select form-select-lg" required>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="p-3 border-top bg-light">
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" id="tablePanelSubmitBtn">
                    <i class="bi bi-check-lg me-1"></i> Crear Mesa
                </button>
            </div>
        </form>
    </div>
</div>
@endpush

<style>
    .salon-page .salon-empty-state {
        background: #fff; border: 1px dashed #dee2e6; border-radius: 18px; padding: 60px 20px;
    }
    .salon-page .salon-empty-state i { font-size: 3rem; color: #ced4da; }

    /* --- Tabs de zonas: pill style, más presencia --- */
    .salon-page .salon-tabs { border-bottom: none; gap: 6px; flex-wrap: wrap; }
    .salon-page .salon-tabs .nav-link {
        border: 1px solid #e9ecef; border-radius: 10px; color: #6c757d;
        padding: 9px 16px; transition: all 0.15s ease; display: flex; align-items: center; gap: 8px;
    }
    .salon-page .salon-tabs .nav-link:hover { background: #f8f9fa; color: #212529; }
    .salon-page .salon-tabs .nav-link.active { background: #0d6efd; border-color: #0d6efd; color: #fff; }
    .salon-page .salon-tab-count {
        background: rgba(0,0,0,0.08); border-radius: 20px; font-size: 0.72rem; font-weight: 800;
        padding: 1px 8px; line-height: 1.5;
    }
    .salon-page .salon-tabs .nav-link.active .salon-tab-count { background: rgba(255,255,255,0.25); color: #fff; }

    .salon-page .salon-toolbar { min-height: 32px; }

    /* --- Canvas: el lienzo "nivel Dios" ---
       .salon-canvas es la "ventana" visible con scroll; .salon-canvas-inner es
       el lienzo real donde se posicionan las mesas, más grande que la ventana
       para poder arrastrar mesas hasta cualquier esquina y hacer scroll. --- */
    .salon-page .salon-canvas {
        border: 1px solid #e6e9ed;
        border-radius: 18px;
        box-shadow: inset 0 1px 3px rgba(20,24,33,0.04), 0 1px 2px rgba(20,24,33,0.03);
        overflow: auto;
    }
    .salon-page .salon-canvas-inner {
        width: 2200px; height: 1400px; min-height: 100%;
        background:
            radial-gradient(circle, #d7dce2 1.5px, transparent 1.5px),
            linear-gradient(180deg, #fbfcfd 0%, #f4f6f8 100%);
        background-size: 22px 22px, 100% 100%;
    }
    .salon-page .salon-canvas-empty {
        height: 100%; display: flex; flex-direction: column;
        align-items: center; justify-content: center; color: #adb5bd; text-align: center;
    }
    .salon-page .salon-canvas-empty i { font-size: 2.6rem; opacity: 0.5; margin-bottom: 10px; }

    /* --- Mesa arrastrable: foto real (mesa + 4 sillas) recortada visualmente
       sobre el fondo del canvas mediante mix-blend-mode. El estado (libre/
       ocupada) se indica solo con el badge bajo la mesa. --- */
    .salon-page .draggable-table {
        position: absolute;
        width: 168px; height: 168px;
        display: flex; align-items: center; justify-content: center;
        cursor: grab; user-select: none;
        z-index: 10;
        transition: transform 0.05s ease;
    }
    .salon-page .draggable-table.dragging {
        cursor: grabbing; z-index: 100; transform: scale(1.06);
    }

    .salon-page .draggable-table-img {
        width: 100%; height: 100%; object-fit: contain;
        mix-blend-mode: multiply; pointer-events: none;
    }

    .salon-page .draggable-table-label {
        position: absolute; bottom: -6px; left: 50%; transform: translate(-50%, 100%);
        display: flex; flex-direction: column; align-items: center; pointer-events: none;
        background: #fff; padding: 3px 10px 4px; border-radius: 10px;
        box-shadow: 0 2px 6px rgba(20,24,33,0.08); border: 1px solid #eef0f3;
    }
    .salon-page .draggable-table-name {
        font-weight: 800; font-size: 0.76rem; color: #1c2333;
        text-align: center; max-width: 90px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .salon-page .draggable-table-status {
        font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
        margin-top: 1px; padding: 1px 7px; border-radius: 20px;
    }
    .salon-page .draggable-table.status-available .draggable-table-status { background: rgba(25,135,84,0.14); color: #198754; }
    .salon-page .draggable-table.status-occupied .draggable-table-status { background: rgba(220,53,69,0.14); color: #dc3545; }

    .salon-page .draggable-table-remove,
    .salon-page .draggable-table-edit {
        position: absolute; top: 2px;
        width: 22px; height: 22px; border-radius: 50%;
        background: #fff; border: 1px solid #e2e5e9; color: #adb5bd;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.62rem; opacity: 0; transition: opacity 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        box-shadow: 0 2px 6px rgba(20,24,33,0.1);
        z-index: 5;
    }
    .salon-page .draggable-table-remove { right: 2px; }
    .salon-page .draggable-table-edit { left: 2px; }
    .salon-page .draggable-table:hover .draggable-table-remove,
    .salon-page .draggable-table:hover .draggable-table-edit { opacity: 1; }
    .salon-page .draggable-table-remove:hover { color: #dc3545; border-color: #f3c2c8; }
    .salon-page .draggable-table-edit:hover { color: #0d6efd; border-color: #bcd6fd; }

    /* --- Panel lateral (offcanvas): bordes izquierdos redondeados --- */
    .salon-side-panel { width: 440px; max-width: 92vw; border-top-left-radius: 20px; border-bottom-left-radius: 20px; overflow: hidden; }
    .salon-side-panel .offcanvas-header { padding: 1.5rem 1.5rem 1rem; }
    .salon-side-panel .offcanvas-title { font-size: 1.25rem; }

    /* --- Selector de imagen de mesa (panel Nueva/Editar Mesa) --- */
    .table-image-picker {
        position: relative; display: block; width: 132px; height: 132px; margin: 0 auto;
        border-radius: 50%; overflow: hidden; cursor: pointer;
        border: 2px dashed #dee2e6; background: #f8f9fa;
    }
    .table-image-picker img { width: 100%; height: 100%; object-fit: cover; }
    .table-image-picker-placeholder {
        position: absolute; inset: 0; display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 4px; color: #adb5bd;
    }
    .table-image-picker-placeholder i { font-size: 1.6rem; }
    .table-image-picker-placeholder small { font-size: 0.7rem; font-weight: 700; }
    .table-image-picker-overlay {
        position: absolute; inset: 0; display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 4px;
        background: rgba(20,24,33,0.55); color: #fff; font-size: 0.72rem; font-weight: 700;
        opacity: 0; transition: opacity 0.15s ease;
    }
    .table-image-picker-overlay i { font-size: 1.1rem; }
    .table-image-picker:hover .table-image-picker-overlay { opacity: 1; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvases = document.querySelectorAll('.salon-canvas');
        let activeDrag = null;
        let offsetX = 0, offsetY = 0;

        // Evita crear/editar mesas duplicadas por doble clic o doble envío del formulario.
        const tablePanelFormEl = document.getElementById('tablePanelForm');
        if (tablePanelFormEl) {
            tablePanelFormEl.addEventListener('submit', function (event) {
                const submitBtn = document.getElementById('tablePanelSubmitBtn');
                if (submitBtn.disabled) {
                    event.preventDefault();
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Guardando...';
            });
        }

        canvases.forEach(canvas => {
            canvas.querySelectorAll('.draggable-table').forEach(el => el.addEventListener('mousedown', dragStart));
        });
        document.addEventListener('mouseup', dragEnd);
        document.addEventListener('mousemove', drag);

        function dragStart(e) {
            if (e.target.closest('.draggable-table-remove')) return;
            activeDrag = e.currentTarget;
            const parentRect = activeDrag.parentElement.getBoundingClientRect();
            offsetX = e.clientX - parentRect.left - activeDrag.offsetLeft;
            offsetY = e.clientY - parentRect.top - activeDrag.offsetTop;
            activeDrag.classList.add('dragging');
        }

        function dragEnd() {
            if (!activeDrag) return;
            activeDrag.classList.remove('dragging');
            activeDrag = null;
        }

        function drag(e) {
            if (!activeDrag) return;
            e.preventDefault();
            const parentRect = activeDrag.parentElement.getBoundingClientRect();
            let x = e.clientX - parentRect.left - offsetX;
            let y = e.clientY - parentRect.top - offsetY;

            x = Math.max(0, Math.min(x, parentRect.width - activeDrag.offsetWidth));
            y = Math.max(0, Math.min(y, parentRect.height - activeDrag.offsetHeight));

            activeDrag.style.left = x + 'px';
            activeDrag.style.top = y + 'px';
        }
    });

    function autoArrange(paneId) {
        const canvas = document.querySelector('#' + paneId + ' .salon-canvas-inner');
        if (!canvas) return;

        const tables = Array.from(canvas.querySelectorAll('.draggable-table'));
        if (!tables.length) return;

        const gap = 16;
        const cellW = 168 + gap;
        const cellH = 168 + gap + 34; // +34: espacio para la etiqueta de nombre/estado bajo cada mesa
        const cols = Math.max(1, Math.floor((canvas.clientWidth - gap) / cellW));

        tables.forEach((el, i) => {
            const col = i % cols;
            const row = Math.floor(i / cols);
            el.style.left = (gap + col * cellW) + 'px';
            el.style.top = (gap + row * cellH) + 'px';
        });

        const outer = document.querySelector('#' + paneId + ' .salon-canvas');
        if (outer) {
            outer.scrollTop = 0;
            outer.scrollLeft = 0;
        }
    }

    function openAreaPanel() {
        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('areaPanel')).show();
    }

    const tablesStoreUrl = "{{ route('tables.storeTable') }}";
    const tablesUpdateUrlBase = "{{ url('/tables/table') }}";
    const tableImages = @json($areas->flatMap->tables->mapWithKeys(fn($t) => [$t->id => $t->image ? asset('storage/'.$t->image) : null]));

    function setTableImagePreview(url) {
        const preview = document.getElementById('tablePanelImagePreview');
        const placeholder = document.getElementById('tablePanelImagePlaceholder');
        if (url) {
            preview.src = url;
            preview.classList.remove('d-none');
            placeholder.classList.add('d-none');
        } else {
            preview.src = '';
            preview.classList.add('d-none');
            placeholder.classList.remove('d-none');
        }
    }

    function openTablePanel(areaId, tableId, tableName) {
        const form = document.getElementById('tablePanelForm');
        const title = document.getElementById('tablePanelLabel');
        const submitBtn = document.getElementById('tablePanelSubmitBtn');
        const nameInput = document.getElementById('tablePanelNameInput');
        const areaSelect = document.getElementById('tablePanelAreaSelect');
        const imageInput = document.getElementById('tablePanelImageInput');

        imageInput.value = '';
        submitBtn.disabled = false;

        if (tableId) {
            // Modo edición / personalizar
            form.action = tablesUpdateUrlBase + '/' + tableId;
            title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Personalizar Mesa';
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar Cambios';
            nameInput.value = tableName || '';
            if (areaId) areaSelect.value = areaId;
            setTableImagePreview(tableImages[tableId] || null);
        } else {
            // Modo creación: sin foto por defecto
            form.action = tablesStoreUrl;
            title.innerHTML = '<i class="bi bi-square me-2"></i>Nueva Mesa';
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Crear Mesa';
            nameInput.value = '';
            if (areaId) areaSelect.value = areaId;
            setTableImagePreview(null);
        }

        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('tablePanel')).show();
    }

    function previewTableImage(input) {
        if (input.files && input.files[0]) {
            setTableImagePreview(URL.createObjectURL(input.files[0]));
        }
    }

    function confirmDeleteArea(id, name) {
        if (confirm('¿Eliminar la zona "' + name + '" y todas sus mesas? Esta acción no se puede deshacer.')) {
            document.getElementById('deleteAreaForm-' + id).submit();
        }
    }

    function confirmDeleteTable(id, name) {
        if (confirm('¿Borrar la mesa "' + name + '"?')) {
            document.getElementById('deleteTableForm-' + id).submit();
        }
    }

    function savePositions() {
        let btn = document.getElementById('btnSave');
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Guardando...';
        btn.disabled = true;

        let positions = [];
        document.querySelectorAll('.draggable-table').forEach(el => {
            positions.push({
                id: el.getAttribute('data-id'),
                x: parseInt(el.style.left.replace('px', '') || 0),
                y: parseInt(el.style.top.replace('px', '') || 0)
            });
        });

        fetch("{{ route('tables.updatePositions') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ positions: positions })
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en el servidor: ' + response.statusText);
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                btn.innerHTML = '<i class="bi bi-check-lg me-2"></i> ¡Guardado!';
                setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 1400);
            } else {
                throw new Error(data.message || 'Error desconocido');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ocurrió un error al guardar el diseño.\n' + error.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
</script>
@endsection
