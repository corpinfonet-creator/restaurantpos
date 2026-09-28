<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ \App\Models\Setting::get('company_name', 'Mi Restaurante') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        :root {
            --topbar-height: 60px; --subsidebar-width: 108px; --subsidebar-width-collapsed: 0px;
        }

        /* --- Shell: envuelve topbar + sidebar + contenido, ocupa toda la
           ventana (sin margen exterior, sin fondo gris visible en las
           esquinas). El único borde redondeado visible es el de .main-content. --- */
        .app-shell {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: #ffffff; overflow: hidden;
            display: flex; flex-direction: column;
        }

        /* --- Topbar plana: hamburguesa | logo | links centrados | usuario --- */
        .topbar {
            height: var(--topbar-height); flex-shrink: 0;
            background: #ffffff;
            display: flex; align-items: center;
            padding: 0 20px; gap: 24px;
        }

        .subsidebar-toggle-btn {
            width: var(--subsidebar-width); height: 38px; flex-shrink: 0; border: none; background: transparent;
            color: #495057; border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; cursor: pointer; transition: background-color 0.15s, color 0.15s, width 0.2s ease;
            margin-left: -20px;
        }
        .subsidebar-toggle-btn:hover { background-color: #f1f3f5; color: #0d6efd; }

        .topbar-brand { display: flex; align-items: center; gap: 10px; flex-shrink: 0; margin-left: 20px; }
        .logo-box { width: 36px; height: 36px; background: #0d6efd; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: bold; flex-shrink: 0; }
        .brand-text { line-height: 1.1; white-space: nowrap; }
        .brand-name { font-weight: 800; font-size: 15px; color: #212529; letter-spacing: -0.3px; }
        .brand-sub { font-size: 10.5px; color: #adb5bd; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }

        .topbar-nav { display: flex; align-items: center; justify-content: center; gap: 4px; flex-grow: 1; overflow-x: auto; }
        .topbar-nav::-webkit-scrollbar { height: 0; }

        .top-link {
            color: #6c757d; font-weight: 600; font-size: 0.92rem; padding: 8px 14px;
            white-space: nowrap; text-decoration: none; border-bottom: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s; background: transparent; border-top: none; border-left: none; border-right: none;
        }
        .top-link:hover { color: #0d6efd; }
        .top-link.active { color: #0d6efd; border-bottom-color: #0d6efd; }

        .topbar-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

        .topbar-icon-btn {
            width: 38px; height: 38px; border-radius: 50%; border: 1px solid #e9ecef; background: #fff;
            color: #6c757d; display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem; transition: background-color 0.15s, color 0.15s;
        }
        .topbar-icon-btn:hover { background-color: #f1f3f5; color: #0d6efd; }

        .topbar-clock {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 999px; background: #e7f1ff; color: #0d6efd;
            font-weight: 700; font-size: 0.82rem; font-variant-numeric: tabular-nums; white-space: nowrap;
        }

        .topbar-user-btn {
            display: flex; align-items: center; gap: 8px; padding: 4px 12px 4px 4px;
            border-radius: 999px; background: transparent; border: none;
        }
        .topbar-user-avatar {
            width: 34px; height: 34px; border-radius: 50%; background: #0d6efd; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700; flex-shrink: 0;
        }
        .topbar-user-name { font-weight: 700; font-size: 0.85rem; color: #212529; line-height: 1.15; }
        .topbar-role-badge { font-size: 0.62rem; padding: 1px 7px; }
        .user-dropdown-menu { min-width: 220px; }
        .user-dropdown-header { padding: 10px 14px 6px; }
        .gear-dropdown-menu { min-width: 210px; }

        /* --- Fila inferior del shell: sub-sidebar + contenido, lado a lado --- */
        .shell-body { flex-grow: 1; display: flex; min-height: 0; }

        /* --- Sub-sidebar: aparece solo cuando la ruta actual pertenece a
           Operación o Gestión. Grid vertical: ícono mediano-grande arriba,
           nombre debajo. Vive dentro del shell, fondo blanco. --- */
        .subsidebar {
            width: var(--subsidebar-width); flex-shrink: 0;
            background: #ffffff;
            padding: 16px 8px; overflow-y: auto; overflow-x: hidden;
            transition: width 0.2s ease, padding 0.2s ease;
        }
        .subsidebar-title { font-size: 0.68rem; font-weight: 700; color: #adb5bd; text-transform: uppercase; margin: 4px 4px 10px; letter-spacing: 0.4px; text-align: center; }
        .subsidebar .nav-link {
            color: #495057; font-weight: 600; border-radius: 10px;
            transition: background-color 0.15s, color 0.15s; margin-bottom: 6px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 6px; padding: 14px 4px; text-align: center;
            width: 100%; border: none; background: transparent; text-decoration: none;
        }
        .subsidebar .nav-link i { font-size: 1.6rem; color: #6c757d; flex-shrink: 0; }
        .subsidebar .nav-link span { font-size: 0.82rem; line-height: 1.15; }
        .subsidebar .nav-link:hover { background-color: #f1f3f5; color: #0d6efd; }
        .subsidebar .nav-link:hover i { color: #0d6efd; }
        .subsidebar .nav-link.active { background-color: #e7f1ff; color: #0d6efd; }
        .subsidebar .nav-link.active i { color: #0d6efd; }

        /* Sub-sidebar colapsado: se oculta por completo, el contenido recupera el ancho */
        html[data-subsidebar="collapsed"] .subsidebar { width: 0; padding-left: 0; padding-right: 0; }
        html[data-subsidebar="collapsed"] .subsidebar .subsidebar-title,
        html[data-subsidebar="collapsed"] .subsidebar .nav-link { display: none; }

        /* --- Contenido: panel propio con borde redondeado gris --- */
        .main-content {
            flex-grow: 1; min-width: 0; overflow-y: auto;
            margin: 12px;
            border: 1px solid #e2e5e9; border-radius: 15px;
            padding: 30px;
        }

        /* --- Modo pantalla completa (venta en mesa del POS): sin topbar,
           el contenido ocupa el 100% sin márgenes ni bordes propios --- */
        .app-shell-fullscreen .main-content {
            margin: 0; border: none; border-radius: 0; padding: 0; overflow: hidden;
        }

        /* --- Pie corporativo: una franja fina fuera de .main-content, en el
           borde inferior del shell. No tiene borde ni fondo propios: es solo
           una línea de texto tenue, para no restar alto al contenido. --- */
        .app-footer {
            flex-shrink: 0; height: 26px;
            padding: 0 30px 6px;
            display: flex; align-items: center; justify-content: center;
            flex-wrap: wrap; gap: 6px;
            font-size: 11.5px; font-weight: 500; color: #b8bfc7;
            letter-spacing: .2px; line-height: 1;
            user-select: none;
        }
        /* El panel ya deja 12px abajo; el pie vive en ese hueco, así que se
           recorta ese margen para no sumar altura al conjunto. */
        .app-shell:not(.app-shell-fullscreen) .main-content { margin-bottom: 2px; }
        .app-footer-brand { font-weight: 700; color: #9aa3ad; letter-spacing: .3px; }
        .app-footer-sep { color: #d8dde2; }

        /* --- Toast de notificación: nace pegado al borde superior de
           main-content (cancela su padding), como una cortina que baja,
           con barra de progreso indicando el tiempo antes de autodescartarse. --- */
        .app-toast-stack {
            display: flex; flex-direction: column;
            margin: -30px -30px 20px;
        }
        .app-toast-stack:empty { margin-bottom: 0; }
        .app-toast {
            position: relative; overflow: hidden;
            display: flex; align-items: flex-start; gap: 12px;
            background: #fff; color: #1c2333;
            padding: 14px 42px 14px 30px;
            box-shadow: 0 2px 10px rgba(20, 24, 33, 0.06);
            border-bottom: 1px solid #eef0f3;
            max-height: 0; opacity: 0; padding-top: 0; padding-bottom: 0;
            border-bottom-width: 0; transform: translateY(-100%);
            transition: opacity 0.3s ease, transform 0.35s cubic-bezier(.22,1,.36,1),
                        max-height 0.35s cubic-bezier(.22,1,.36,1),
                        padding 0.35s ease, border-bottom-width 0.35s ease;
        }
        .app-toast:first-child { border-top-left-radius: 15px; border-top-right-radius: 15px; }
        .app-toast.show {
            opacity: 1; transform: translateY(0); max-height: 120px;
            padding-top: 14px; padding-bottom: 14px; border-bottom-width: 1px;
        }
        .app-toast.hide { opacity: 0; transform: translateY(-8px); }
        .app-toast-icon {
            flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; margin-top: 1px;
        }
        .app-toast-body { flex-grow: 1; font-weight: 600; font-size: 0.92rem; line-height: 1.4; padding-top: 3px; }
        .app-toast-close {
            position: absolute; top: 10px; right: 10px;
            width: 26px; height: 26px; border: none; background: transparent;
            color: #adb5bd; border-radius: 6px; display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; cursor: pointer; transition: background-color 0.15s, color 0.15s;
        }
        .app-toast-close:hover { background: #f1f3f5; color: #495057; }
        .app-toast-progress {
            position: absolute; left: 0; bottom: 0; height: 3px; width: 100%;
            background: currentColor; opacity: 0.35;
            transform-origin: left; transform: scaleX(1);
            transition: transform linear;
        }
        .app-toast-success { color: #198754; background: #eafaf1; border-bottom-color: #cdefdc; }
        .app-toast-success .app-toast-icon { background: rgba(25,135,84,0.14); color: #198754; }
        .app-toast-danger { color: #dc3545; background: #fdedee; border-bottom-color: #f8d3d6; }
        .app-toast-danger .app-toast-icon { background: rgba(220,53,69,0.14); color: #dc3545; }
        .app-toast-warning { color: #997404; background: #fff9e6; border-bottom-color: #ffedb3; }
        .app-toast-warning .app-toast-icon { background: rgba(255,193,7,0.22); color: #997404; }
        @media (max-width: 575px) {
            .app-toast-stack { margin: -16px -16px 16px; }
            .app-toast { padding-left: 16px; }
        }

        @media (max-width: 991px) {
            .brand-sub { display: none; }
            .subsidebar { width: 0; padding-left: 0; padding-right: 0; }
            .subsidebar .subsidebar-title, .subsidebar .nav-link { display: none; }
        }
        @media (max-width: 767px) {
            .topbar-user-name { display: none; }
        }
        @media (max-width: 575px) {
            .main-content { padding: 16px; margin: 8px; }
            .app-shell:not(.app-shell-fullscreen) .main-content { margin-bottom: 2px; }
            .app-footer { padding: 0 16px 5px; font-size: 11px; }
        }
    </style>
    <script>
        // Aplica el estado colapsado antes del primer paint para evitar parpadeo.
        if (localStorage.getItem('subsidebarCollapsed') === '1') {
            document.documentElement.setAttribute('data-subsidebar', 'collapsed');
        }
    </script>
</head>
<body>

@php
    $logo = \App\Models\Setting::get('company_logo');
    $name = \App\Models\Setting::get('company_name', 'Mi Restaurante');
    $role = Auth::user()->role;

    $operacionRoutes = ['reservations.*', 'sales.*', 'kitchen.*'];
    $operacionActive = collect($operacionRoutes)->contains(fn($r) => request()->routeIs($r));

    $gestionRoutes = ['clients.*', 'categories.*', 'products.*', 'tables.*'];
    $gestionActive = collect($gestionRoutes)->contains(fn($r) => request()->routeIs($r)) && $role === 'admin';

    // POS: el sub-sidebar lista las zonas del salón (Salón Principal, Terraza, ...)
    // como pestañas dentro de la misma pantalla, igual que Operación/Gestión.
    // Solo zonas ACTIVAS: al eliminar una zona que ya tuvo pedidos no se borra
    // físicamente (se perdería el historial de ventas de sus mesas), sino que
    // se marca active = false. Sin este filtro esas zonas "eliminadas" seguían
    // apareciendo como pestañas aquí, aunque PosController ya no las envía y
    // por tanto su panel de mesas ni siquiera existía.
    $posActive = request()->routeIs('pos.index');
    $posAreas = $posActive
        ? \App\Models\Area::where('active', true)->orderBy('id')->get()
        : collect();

    // La pantalla de venta de una mesa específica (POS a pantalla completa)
    // oculta la topbar y el sub-sidebar globales: es la única vista que
    // necesita el 100% del espacio, con su propio "Volver" interno.
    $isFullscreenPos = request()->routeIs('pos.order');
@endphp

<div class="app-shell {{ $isFullscreenPos ? 'app-shell-fullscreen' : '' }}">
@unless($isFullscreenPos)
<div class="topbar">
    @if($operacionActive || $gestionActive || $posActive)
        <button type="button" class="subsidebar-toggle-btn" id="subsidebarToggleBtn" title="Mostrar/ocultar menú lateral">
            <i class="bi bi-list"></i>
        </button>
    @endif

    <div class="topbar-brand">
        @if($logo)
            <img src="{{ asset('storage/'.$logo) }}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 8px;">
        @else
            <div class="logo-box"><i class="bi bi-shop"></i></div>
        @endif
        <div class="brand-text">
            <div class="brand-name text-truncate" style="max-width: 160px;" title="{{ $name }}">{{ $name }}</div>
            <div class="brand-sub">Sistema Restaurante</div>
        </div>
    </div>

    <nav class="topbar-nav">
        @if(in_array($role, ['admin', 'cashier']))
            <a href="{{ route('dashboard') }}" class="top-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
        @endif

        <a href="{{ route('reservations.index') }}" class="top-link {{ $operacionActive ? 'active' : '' }}">Operación</a>

        @if($role === 'admin')
            <a href="{{ route('clients.index') }}" class="top-link {{ $gestionActive ? 'active' : '' }}">Gestión</a>
        @endif

        <a href="{{ route('pos.index') }}" class="top-link {{ request()->routeIs('pos.*') ? 'active' : '' }}">POS</a>

        @if($role === 'admin')
            <a href="{{ route('reports.index') }}" class="top-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">Reportes</a>
        @endif
    </nav>

    <div class="topbar-right">
        <div class="topbar-clock d-none d-md-flex">
            <i class="bi bi-clock-fill"></i>
            <span id="topbarClock">--:--:--</span>
        </div>

        @if($role === 'admin')
            <div class="dropdown">
                <button class="topbar-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Configuración">
                    <i class="bi bi-gear-fill"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end gear-dropdown-menu">
                    <li>
                        <a href="{{ route('settings.index') }}" class="dropdown-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                            <i class="bi bi-gear-fill me-2"></i> Configuración
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('users.index') }}" class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <i class="bi bi-person-badge-fill me-2"></i> Usuarios
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('system.index') }}" class="dropdown-item text-danger {{ request()->routeIs('system.*') ? 'active' : '' }}">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i> Reset Sistema
                        </a>
                    </li>
                </ul>
            </div>
        @endif

        <div class="dropdown">
            <button class="topbar-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="topbar-user-avatar">{{ substr(Auth::user()->name ?? 'U', 0, 1) }}</div>
                <div class="text-start d-none d-md-block">
                    <div class="topbar-user-name">{{ Auth::user()->name ?? 'Usuario' }}</div>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
                <li class="user-dropdown-header">
                    <div class="fw-bold small text-truncate">{{ Auth::user()->name ?? 'Usuario' }}</div>
                    @if($role == 'admin') <span class="badge bg-danger topbar-role-badge">Administrador</span>
                    @elseif($role == 'cashier') <span class="badge bg-primary topbar-role-badge">Cajero</span>
                    @else <span class="badge bg-success topbar-role-badge">Mozo</span>
                    @endif
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>
@endunless

<div class="shell-body">
@if($operacionActive)
    <div class="subsidebar">
        <a href="{{ route('reservations.index') }}" class="nav-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check"></i> <span>Reservas</span>
        </a>
        @if(in_array($role, ['admin', 'cashier']))
            <a href="{{ route('sales.index') }}" class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i> <span>Caja / Historial</span>
            </a>
        @endif
        <a href="{{ route('kitchen.index') }}" class="nav-link {{ request()->routeIs('kitchen.*') ? 'active' : '' }}">
            <i class="bi bi-fire"></i> <span>Monitor de Cocina</span>
        </a>
    </div>
@elseif($gestionActive)
    <div class="subsidebar">
        <a href="{{ route('clients.index') }}" class="nav-link {{ request()->routeIs('clients.*') ? 'active' : '' }}">
            <i class="bi bi-people-fill"></i> <span>Clientes (CRM)</span>
        </a>
        <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
            <i class="bi bi-tags-fill"></i> <span>Categorías</span>
        </a>
        <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam-fill"></i> <span>Inventario</span>
        </a>
        <a href="{{ route('tables.index') }}" class="nav-link {{ request()->routeIs('tables.*') ? 'active' : '' }}">
            <i class="bi bi-grid-3x3-gap-fill"></i> <span>Mesas / Salón</span>
        </a>
    </div>
@elseif($posActive)
    <div class="subsidebar" role="tablist">
        @foreach($posAreas as $index => $posArea)
            <button type="button" class="nav-link pos-zone-nav-link {{ $index === 0 ? 'active' : '' }}"
               data-bs-toggle="tab" data-bs-target="#area-{{ $posArea->id }}" role="tab">
                <i class="bi bi-grid-3x3-gap-fill"></i> <span>{{ $posArea->name }}</span>
            </button>
        @endforeach
    </div>
@endif

    <div class="main-content">
        @if(session('success') || session('error') || session('warning'))
            <div class="app-toast-stack">
                @if(session('success'))
                    <div class="app-toast app-toast-success" role="alert" data-autohide="4000">
                        <div class="app-toast-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="app-toast-body">{{ session('success') }}</div>
                        <button type="button" class="app-toast-close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                        <div class="app-toast-progress"></div>
                    </div>
                @endif
                @if(session('warning'))
                    <div class="app-toast app-toast-warning" role="alert" data-autohide="5000">
                        <div class="app-toast-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="app-toast-body">{{ session('warning') }}</div>
                        <button type="button" class="app-toast-close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                        <div class="app-toast-progress"></div>
                    </div>
                @endif
                @if(session('error'))
                    <div class="app-toast app-toast-danger" role="alert" data-autohide="6000">
                        <div class="app-toast-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
                        <div class="app-toast-body">{{ session('error') }}</div>
                        <button type="button" class="app-toast-close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                        <div class="app-toast-progress"></div>
                    </div>
                @endif
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger shadow-sm border-0">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</div>

{{-- Pie corporativo del proveedor. Va FUERA de .main-content y también fuera
     de .shell-body (que es una fila horizontal: sidebar | contenido), como
     última fila del .app-shell vertical. Así queda pegado al borde inferior
     de la ventana sin restarle alto al contenido ni moverse con su scroll.
     No se muestra en la venta de mesa (POS a pantalla completa). --}}
@unless($isFullscreenPos)
    <footer class="app-footer">
        <span class="app-footer-brand">KaYu SAC</span>
        <span class="app-footer-sep">·</span>
        <span>Software {{ date('Y') }}</span>
        <span class="app-footer-sep">·</span>
        <span>Restaurant</span>
        <span class="app-footer-sep">·</span>
        <span>Perú</span>
    </footer>
@endunless
</div>

{{-- Modales / offcanvas de las vistas: se inyectan aquí, como hijos directos
     de <body>, fuera de .app-shell (que tiene overflow:hidden). Un elemento
     position:fixed queda "atrapado" dentro de cualquier ancestro con overflow
     hidden + su propio stacking context, así que los modales/offcanvas de
     cada página deben vivir aquí (vía @push('modals') ... @endpush) en vez
     de junto al contenido normal para que Bootstrap los muestre correctamente. --}}
@stack('modals')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        var clockEl = document.getElementById('topbarClock');
        if (!clockEl) return;

        function tick() {
            var now = new Date();
            var hh = String(now.getHours()).padStart(2, '0');
            var mm = String(now.getMinutes()).padStart(2, '0');
            var ss = String(now.getSeconds()).padStart(2, '0');
            clockEl.textContent = hh + ':' + mm + ':' + ss;
        }

        tick();
        setInterval(tick, 1000);
    })();

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#subsidebarToggleBtn')) return;

        var collapsed = document.documentElement.getAttribute('data-subsidebar') === 'collapsed';
        if (collapsed) {
            document.documentElement.removeAttribute('data-subsidebar');
            localStorage.setItem('subsidebarCollapsed', '0');
        } else {
            document.documentElement.setAttribute('data-subsidebar', 'collapsed');
            localStorage.setItem('subsidebarCollapsed', '1');
        }
    });

    document.querySelectorAll('.app-toast').forEach(function (toast) {
        var duration = parseInt(toast.dataset.autohide, 10) || 4000;
        var progress = toast.querySelector('.app-toast-progress');
        var timer;

        function dismiss() {
            clearTimeout(timer);
            toast.classList.add('hide');
            toast.classList.remove('show');
            toast.addEventListener('transitionend', function (e) {
                if (e.propertyName === 'max-height') toast.remove();
            });
        }

        toast.querySelector('.app-toast-close').addEventListener('click', dismiss);

        requestAnimationFrame(function () {
            toast.classList.add('show');
            if (progress) {
                progress.style.transition = 'transform ' + duration + 'ms linear';
                requestAnimationFrame(function () { progress.style.transform = 'scaleX(0)'; });
            }
            timer = setTimeout(dismiss, duration);
        });

        toast.addEventListener('mouseenter', function () { clearTimeout(timer); if (progress) progress.style.transition = 'none'; });
        toast.addEventListener('mouseleave', function () {
            var remaining = progress ? progress.getBoundingClientRect().width / toast.getBoundingClientRect().width * duration : duration;
            if (progress) {
                progress.style.transition = 'transform ' + remaining + 'ms linear';
                requestAnimationFrame(function () { progress.style.transform = 'scaleX(0)'; });
            }
            timer = setTimeout(dismiss, remaining);
        });
    });
</script>
</body>
</html>
