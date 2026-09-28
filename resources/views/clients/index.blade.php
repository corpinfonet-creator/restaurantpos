@extends('layouts.app')

@section('content')
<div class="container-fluid clients-page">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-people-fill me-2"></i>Cartera de Clientes</h2>
            <p class="text-muted mb-0">Gestión de relaciones y fidelización (CRM)</p>
        </div>
        <button type="button" class="btn btn-primary fw-bold shadow-sm" onclick="openClientPanel('create')">
            <i class="bi bi-person-plus-fill me-2"></i> Nuevo Cliente
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form action="{{ route('clients.index') }}" method="GET" class="clients-search-form">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0"
                           placeholder="Buscar por nombre, documento, teléfono o email...">
                    @if($search)
                        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda"><i class="bi bi-x-lg"></i></a>
                    @endif
                    <button class="btn btn-primary" type="submit">Buscar</button>
                </div>
            </form>
            <span class="badge bg-light text-dark border">{{ $clients->total() }} cliente{{ $clients->total() == 1 ? '' : 's' }}</span>
        </div>

        <div class="card-body p-0">
            <div class="clients-table-scroll">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Cliente</th>
                            <th>Documento / RUC</th>
                            <th>Contacto</th>
                            <th class="text-center">Visitas</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $client)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('clients.show', $client->id) }}" class="d-flex align-items-center text-decoration-none text-dark">
                                        <div class="clients-avatar me-3">{{ substr($client->name, 0, 1) }}</div>
                                        <span class="fw-bold">{{ $client->name }}</span>
                                    </a>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $client->document_number ?? '---' }}</span></td>
                                <td class="small text-muted">
                                    @if($client->phone) <i class="bi bi-telephone"></i> {{ $client->phone }}<br> @endif
                                    @if($client->email) <i class="bi bi-envelope"></i> {{ $client->email }} @endif
                                    @if(!$client->phone && !$client->email) <span>—</span> @endif
                                </td>
                                <td class="text-center">
                                    @if($client->orders_count > 0)
                                        <span class="badge bg-success rounded-pill">{{ $client->orders_count }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('clients.show', $client->id) }}" class="btn btn-sm btn-outline-primary" title="Ver Perfil 360">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Editar"
                                                onclick='openClientPanel("edit", @json($client))'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('clients.destroy', $client->id) }}" method="POST" onsubmit="return confirm('¿Eliminar cliente?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="clients-empty-state">
                                        <i class="bi bi-{{ $search ? 'search' : 'people' }}"></i>
                                        <h6 class="fw-bold text-dark mb-1 mt-3">
                                            {{ $search ? 'Sin resultados' : 'Aún no hay clientes' }}
                                        </h6>
                                        <small class="text-muted">
                                            {{ $search ? 'No encontramos clientes que coincidan con "'.$search.'".' : 'Registra tu primer cliente para empezar.' }}
                                        </small>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($clients->hasPages())
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top flex-wrap gap-2">
                    <small class="text-muted">
                        Mostrando {{ $clients->firstItem() }}–{{ $clients->lastItem() }} de {{ $clients->total() }}
                    </small>
                    {{ $clients->onEachSide(1)->links('pagination.client-orders') }}
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .clients-page .clients-search-form { flex: 1 1 380px; max-width: 480px; }
    .clients-page .clients-search-form .input-group-text { background: #fff; }
    .clients-page .clients-search-form input:focus { box-shadow: none; border-color: #ced4da; }

    .clients-page .clients-avatar {
        width: 38px; height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0d6efd, #3d8bfd);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 0.95rem;
        flex-shrink: 0;
    }

    .clients-page .clients-table-scroll {
        max-height: 60vh;
        overflow-y: auto;
        overflow-x: auto;
    }
    .clients-page .clients-table-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8f9fa;
    }
    .clients-page .clients-table-scroll table { min-width: 720px; }

    .clients-page .clients-empty-state {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .clients-page .clients-empty-state i {
        font-size: 2.25rem; color: #adb5bd; background: #f1f3f5;
        width: 64px; height: 64px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }

    @media (max-width: 767.98px) {
        .clients-page .clients-search-form { max-width: 100%; flex-basis: 100%; }
    }
</style>

@push('modals')
{{-- Panel lateral derecho: Nuevo Cliente / Editar Cliente (mismo panel, dos modos) --}}
<div class="offcanvas offcanvas-end client-panel" tabindex="-1" id="clientPanel" aria-labelledby="clientPanelLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;" id="clientPanelEyebrow">Cliente</span>
            <h4 class="offcanvas-title fw-bold mb-0" id="clientPanelLabel">Nuevo Cliente</h4>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <form id="clientForm" method="POST" class="d-flex flex-column h-100">
            @csrf
            <input type="hidden" name="_method" id="clientFormMethod" value="POST">

            <div class="p-4 flex-grow-1 overflow-auto">
                @if($errors->any())
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="client-avatar-preview mb-4" id="clientAvatarPreview">
                    <span id="clientAvatarInitial">?</span>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">DNI / RUC</label>
                    <div class="input-group">
                        <input type="text" name="document_number" id="clientDocument" class="form-control" autocomplete="off" maxlength="8" inputmode="numeric">
                        <button type="button" class="btn btn-outline-primary" id="clientDniSearchBtn" title="Buscar por DNI">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <div class="form-text" id="clientDniLookupStatus"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Nombre Completo *</label>
                    <input type="text" name="name" id="clientName" class="form-control form-control-lg" required autocomplete="off">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Teléfono</label>
                    <input type="text" name="phone" id="clientPhone" class="form-control" autocomplete="off">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="clientEmail" class="form-control" autocomplete="off">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Dirección</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-geo-alt"></i></span>
                        <input type="text" name="address" id="clientAddress" class="form-control" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="p-3 border-top bg-light">
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" id="clientFormSubmitBtn">
                    <i class="bi bi-check-lg me-1"></i> Guardar Cliente
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const clientStoreUrl = @json(route('clients.store'));
    const clientUpdateBaseUrl = @json(url('/clients'));

    window.openClientPanel = function (mode, client) {
        const form = document.getElementById('clientForm');
        const methodField = document.getElementById('clientFormMethod');
        const submitBtn = document.getElementById('clientFormSubmitBtn');
        const eyebrow = document.getElementById('clientPanelEyebrow');
        const title = document.getElementById('clientPanelLabel');
        const avatarInitial = document.getElementById('clientAvatarInitial');

        const fields = {
            name: document.getElementById('clientName'),
            document_number: document.getElementById('clientDocument'),
            phone: document.getElementById('clientPhone'),
            email: document.getElementById('clientEmail'),
            address: document.getElementById('clientAddress'),
        };

        if (mode === 'edit' && client) {
            form.action = `${clientUpdateBaseUrl}/${client.id}`;
            methodField.value = 'PUT';
            eyebrow.textContent = 'Editar registro';
            title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Cliente';
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Actualizar Cliente';
            fields.name.value = client.name ?? '';
            fields.document_number.value = client.document_number ?? '';
            fields.phone.value = client.phone ?? '';
            fields.email.value = client.email ?? '';
            fields.address.value = client.address ?? '';
            avatarInitial.textContent = (client.name ?? '?').charAt(0).toUpperCase();
        } else {
            form.action = clientStoreUrl;
            methodField.value = 'POST';
            eyebrow.textContent = 'Nuevo registro';
            title.innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>Nuevo Cliente';
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar Cliente';
            form.reset();
            avatarInitial.textContent = '?';
        }

        document.getElementById('clientDniLookupStatus').textContent = '';

        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('clientPanel')).show();
        setTimeout(() => fields.name.focus(), 350);
    };

    @if($errors->any())
        // La validación falló en el servidor (ej: DNI/RUC duplicado).
        // Reabrimos el panel con los datos enviados para que el usuario corrija sin perder lo escrito.
        document.addEventListener('DOMContentLoaded', function () {
            @php $oldMethod = old('_method', 'POST'); @endphp
            @if($oldMethod === 'PUT')
                openClientPanel('edit', {
                    id: {{ (int) request()->segment(2) }},
                    name: @json(old('name')),
                    document_number: @json(old('document_number')),
                    phone: @json(old('phone')),
                    email: @json(old('email')),
                    address: @json(old('address')),
                });
            @else
                openClientPanel('create');
                document.getElementById('clientName').value = @json(old('name'));
                document.getElementById('clientDocument').value = @json(old('document_number'));
                document.getElementById('clientPhone').value = @json(old('phone'));
                document.getElementById('clientEmail').value = @json(old('email'));
                document.getElementById('clientAddress').value = @json(old('address'));
                document.getElementById('clientAvatarInitial').textContent = (@json(old('name')) || '?').charAt(0).toUpperCase();
            @endif
        });
    @endif

    document.getElementById('clientName').addEventListener('input', function (e) {
        document.getElementById('clientAvatarInitial').textContent = (e.target.value || '?').charAt(0).toUpperCase();
    });

    // --- Búsqueda de DNI (api.json.pe) para autocompletar el nombre ---
    const dniLookupUrlBase = @json(url('/dni-lookup'));

    document.getElementById('clientDniSearchBtn').addEventListener('click', function () {
        const btn = this;
        const dniInput = document.getElementById('clientDocument');
        const nameInput = document.getElementById('clientName');
        const statusEl = document.getElementById('clientDniLookupStatus');
        const dni = dniInput.value.trim();

        if (!/^\d{8}$/.test(dni)) {
            statusEl.textContent = 'Ingresa un DNI válido de 8 dígitos.';
            statusEl.className = 'form-text text-danger';
            return;
        }

        btn.disabled = true;
        const originalIcon = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        statusEl.textContent = 'Buscando...';
        statusEl.className = 'form-text text-muted';

        fetch(`${dniLookupUrlBase}/${dni}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.found) {
                    nameInput.value = data.name;
                    nameInput.dispatchEvent(new Event('input'));
                    statusEl.textContent = 'Nombre encontrado y completado.';
                    statusEl.className = 'form-text text-success';
                } else {
                    statusEl.textContent = data.message || 'No se encontró información para ese DNI.';
                    statusEl.className = 'form-text text-danger';
                }
            })
            .catch(() => {
                statusEl.textContent = 'Error al consultar el servicio. Intenta de nuevo.';
                statusEl.className = 'form-text text-danger';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalIcon;
            });
    });
</script>

<style>
    .client-panel { width: 440px; max-width: 92vw; }
    .client-panel .offcanvas-header { padding: 1.5rem 1.5rem 1rem; }
    .client-panel .offcanvas-title { font-size: 1.3rem; }

    .client-avatar-preview {
        width: 76px; height: 76px; border-radius: 50%; margin: 0 auto;
        background: linear-gradient(135deg, #0d6efd, #3d8bfd);
        color: #fff; display: flex; align-items: center; justify-content: center;
        font-size: 2rem; font-weight: 800; box-shadow: 0 6px 16px rgba(13,110,253,0.25);
    }
</style>
@endpush
@endsection
