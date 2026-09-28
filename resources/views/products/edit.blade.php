@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-9">

            <div class="d-flex align-items-center mb-4">
                <a href="{{ route('products.index') }}" class="btn btn-light border shadow-sm me-3"><i class="bi bi-arrow-left"></i></a>
                <div>
                    <h2 class="fw-bold text-dark mb-0">Editar Producto</h2>
                    <p class="text-muted mb-0">Gestiona detalles y receta</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    @include('products._edit_form')
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
