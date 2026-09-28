<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licencia - Restaurante POS</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .lic-card {
            width: 100%;
            max-width: 520px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .lic-header {
            color: #fff;
            padding: 28px 20px;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }
        .lic-header.bloqueo { background: #dc3545; }
        .lic-header.aviso   { background: #fd7e14; }
        .lic-icon {
            width: 54px;
            height: 54px;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: rgba(255,255,255,.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            line-height: 1;
        }
        .lic-id {
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            color: #6c757d;
        }
        /* El código de licencia se teclea a mano: campo amplio y legible. */
        .lic-input {
            font-family: monospace;
            font-size: 16px;      /* evita el zoom automático en iOS */
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 12px 14px;
        }
        /* Móvil: la tarjeta ocupa el ancho y se alinea arriba, para que el
           teclado virtual no tape el campo al enfocarlo. */
        @media (max-width: 575.98px) {
            body {
                align-items: flex-start;
                padding: 12px;
            }
            .lic-card { border-radius: 12px; }
            .lic-header {
                padding: 22px 16px;
                border-radius: 12px 12px 0 0;
            }
            .lic-icon {
                width: 46px;
                height: 46px;
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="card lic-card">
        <div class="lic-header {{ $estado['motivo'] === 'gracia' ? 'aviso' : 'bloqueo' }}">
            <div class="lic-icon" aria-hidden="true">
                @if ($estado['motivo'] === 'sin_licencia')
                    &#128273;{{-- llave --}}
                @elseif ($estado['motivo'] === 'sin_contacto')
                    &#128246;{{-- señal --}}
                @else
                    &#9888;{{-- advertencia --}}
                @endif
            </div>
            <h4 class="mb-1">
                @if ($estado['motivo'] === 'sin_licencia')
                    Active su licencia
                @elseif ($estado['motivo'] === 'vencida')
                    Licencia vencida
                @elseif ($estado['motivo'] === 'revocada')
                    Licencia suspendida
                @elseif ($estado['motivo'] === 'sin_contacto')
                    No se pudo verificar la licencia
                @else
                    Atención con su licencia
                @endif
            </h4>
            <small>{{ $estado['mensaje'] }}</small>
        </div>

        <div class="card-body p-4">

            @if (session('licencia_error'))
                <div class="alert alert-danger py-2">{{ session('licencia_error') }}</div>
            @endif
            @if (session('licencia_ok'))
                <div class="alert alert-success py-2">{{ session('licencia_ok') }}</div>
            @endif

            @if ($estado['motivo'] === 'sin_contacto')
                {{-- Aquí no hace falta un código nuevo: sólo recuperar la conexión. --}}
                <p class="text-muted">
                    El sistema no ha logrado contactar con el servidor de licencias
                    dentro del plazo permitido. Compruebe la conexión a internet y
                    vuelva a intentarlo.
                </p>
                <form method="POST" action="{{ route('licencia.revalidar') }}">
                    @csrf
                    <button class="btn btn-primary w-100">Reintentar verificación</button>
                </form>
            @else
                <p class="text-muted">
                    Introduzca el código de licencia que le entregó su proveedor.
                </p>
                <form method="POST" action="{{ route('licencia.activar') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Código de licencia</label>
                        <input type="text"
                               name="codigo"
                               class="form-control lic-input @error('codigo') is-invalid @enderror"
                               value="{{ old('codigo') }}"
                               placeholder="REST-0-XXXXXXXX"
                               autocomplete="off"
                               autocapitalize="characters"
                               spellcheck="false"
                               autofocus>
                        @error('codigo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button class="btn btn-primary w-100">Activar licencia</button>
                </form>
            @endif

            <hr class="my-4">

            <div class="small text-muted">
                <div class="mb-1">Identificador de esta instalación:</div>
                <div class="lic-id">{{ $instalacion }}</div>
                <div class="mt-2">
                    Facilite este identificador a su proveedor si necesita soporte.
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="btn btn-link btn-sm text-muted p-0">Cerrar sesión</button>
            </form>
        </div>
    </div>
</body>
</html>
