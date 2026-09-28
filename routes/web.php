<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SystemController; // <--- Nuevo Controlador de Sistema
use App\Http\Controllers\DniLookupController;
use App\Http\Controllers\LicenciaController;

/*
|--------------------------------------------------------------------------
| Web Routes (SISTEMA PROFESIONAL v5.0 - PRODUCCIÓN)
|--------------------------------------------------------------------------
*/

// --- 1. AUTENTICACIÓN ---
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.perform');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// --- 1b. LICENCIA ---
// Fuera del middleware 'licencia': si estuvieran dentro, un sistema con la
// licencia vencida no podría siquiera activarla.
Route::middleware(['auth'])->group(function () {
    Route::get('/licencia', [LicenciaController::class, 'aviso'])->name('licencia.aviso');
    Route::post('/licencia/activar', [LicenciaController::class, 'activar'])->name('licencia.activar');
    Route::post('/licencia/revalidar', [LicenciaController::class, 'revalidar'])->name('licencia.revalidar');
});

// --- 2. SISTEMA INTERNO ---
// El middleware 'licencia' protege toda la operación. Verifica la firma del
// token guardado, sin llamadas de red: la revalidación es un trabajo diario.
Route::middleware(['auth', 'licencia'])->group(function () {

    // =========================================================
    // ZONA OPERATIVA (Accesible para Mozo, Cajero, Admin)
    // =========================================================
    
    // Consulta de DNI (api.json.pe) - usada en Nuevo Cliente y en el checkout del POS
    Route::get('/dni-lookup/{dni}', [DniLookupController::class, 'lookup'])->name('dni.lookup');

    // POS (Punto de Venta)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/table/{table}', [PosController::class, 'order'])->name('pos.order');
    Route::post('/pos/order/{table}/add', [PosController::class, 'addToOrder'])->name('pos.add');
    Route::get('/pos/table/{table}/cart', [PosController::class, 'refreshCart'])->name('pos.cart.refresh');

    // Herramientas de Orden
    Route::get('/pos/order/{order}/precheck', [PosController::class, 'precheck'])->name('pos.precheck');
    Route::get('/pos/order/{order}/kitchen-ticket', [PosController::class, 'kitchenTicket'])->name('pos.kitchen');
    Route::post('/pos/order/{order}/move', [PosController::class, 'moveTable'])->name('pos.move');
    
    // División de Cuenta
    Route::get('/pos/order/{order}/split-content', [PosController::class, 'getSplitContent'])->name('pos.split.content');
    Route::post('/pos/order/{order}/split', [PosController::class, 'processSplit'])->name('pos.split');
    
    // Gestión de Items
    Route::post('/pos/detail/{detail}/update', [PosController::class, 'updateQuantity'])->name('pos.update');
    Route::post('/pos/detail/{detail}/note', [PosController::class, 'updateNote'])->name('pos.note');
    Route::delete('/pos/detail/{detail}', [PosController::class, 'removeItem'])->name('pos.remove');
    Route::delete('/pos/table/{table}/clear', [PosController::class, 'clearOrder'])->name('pos.clear');
    
    // Monitor de Cocina
    Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
    Route::get('/kitchen/poll', [KitchenController::class, 'poll'])->name('kitchen.poll');
    Route::post('/kitchen/{detail}/status', [KitchenController::class, 'updateStatus'])->name('kitchen.update');
    Route::post('/kitchen/{detail}/revert', [KitchenController::class, 'revertStatus'])->name('kitchen.revert');

    // RESERVAS Y AGENDA
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::put('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.status');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');


    // =========================================================
    // ZONA FINANCIERA (Cajeros y Admins)
    // =========================================================
    Route::middleware(['role:admin,cashier'])->group(function () {
        // Cobro Final
        Route::post('/pos/order/{order}/checkout', [PosController::class, 'checkout'])->name('pos.checkout');

        // Descuentos y propina: afectan el total cobrado, por eso viven aquí y
        // no en la zona operativa. Un mozo no debe poder rebajar una cuenta.
        Route::post('/pos/order/{order}/discount', [PosController::class, 'applyDiscount'])->name('pos.discount');
        
        // Ventas, Caja y Gastos
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/daily-report', [SaleController::class, 'dailyReport'])->name('sales.daily.report');
        Route::get('/sales/{order}/ticket', [SaleController::class, 'ticket'])->name('sales.ticket');
        
        Route::resource('expenses', ExpenseController::class)->only(['store', 'destroy']);
    });


    // =========================================================
    // ZONA ADMINISTRATIVA (Solo Admin)
    // =========================================================
    Route::middleware(['role:admin'])->group(function () {
        
        // Dashboard y BI
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        
        // Gestión
        // create/edit quedan fuera: el alta y la edición se hacen en modales
        // dentro de clients.index, y sus vistas (clients.create / clients.edit)
        // no existen. Declararlas daba una ruta que devolvía error 500.
        Route::resource('clients', ClientController::class)->except(['create', 'edit']);
        // CategoryController solo implementa index/store/update/destroy.
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        
        // Productos e Inventario
        Route::post('/products/{product}/adjust', [ProductController::class, 'adjustStock'])->name('products.adjust');
        Route::post('/products/{product}/toggle', [ProductController::class, 'toggleStatus'])->name('products.toggle');
        // No hay método show(): el detalle se ve en el propio listado.
        Route::resource('products', ProductController::class)->except(['show']);
        Route::get('/inventory/logs', function() {
            $logs = \App\Models\InventoryLog::with('product', 'user')->orderBy('created_at', 'desc')->paginate(50);
            return view('products.kardex', compact('logs'));
        })->name('inventory.logs');
        
        // Configuración y Usuarios
        // UserController solo implementa index/store/update/destroy.
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

        // MANTENIMIENTO DEL SISTEMA (RESET)
        Route::get('/system', [SystemController::class, 'index'])->name('system.index');
        Route::post('/system/reset', [SystemController::class, 'resetData'])->name('system.reset');

        // Mapa de Mesas
        Route::get('/tables', [TableController::class, 'index'])->name('tables.index');
        Route::post('/tables/area', [TableController::class, 'storeArea'])->name('tables.storeArea');
        Route::delete('/tables/area/{area}', [TableController::class, 'destroyArea'])->name('tables.destroyArea');
        Route::post('/tables/table', [TableController::class, 'storeTable'])->name('tables.storeTable');
        Route::post('/tables/table/{table}', [TableController::class, 'updateTable'])->name('tables.updateTable');
        Route::delete('/tables/table/{table}', [TableController::class, 'destroyTable'])->name('tables.destroyTable');
        Route::post('/tables/update-positions', [TableController::class, 'updatePositions'])->name('tables.updatePositions');
    });

});