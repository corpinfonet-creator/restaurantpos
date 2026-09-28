<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las columnas que más se filtran en caliente.
 *
 * Hasta ahora sólo existían los índices que Laravel crea solo para las claves
 * foráneas. Las consultas que realmente se repiten filtran por `status` y por
 * `created_at`, sin índice: MySQL recorría la tabla entera cada vez.
 *
 * Donde más pesa:
 *  - /kitchen/poll  → status + whereHas(details.status), cada pocos segundos y
 *    por cada pantalla de cocina abierta.
 *  - /pos           → mesas ocupadas = orders where status = 'pending'.
 *  - Dashboard y Reportes → agregados por rango de created_at.
 *
 * Con pocas filas no se nota; a partir de unos miles de órdenes la diferencia
 * es de orden de magnitud. Los índices compuestos siguen el orden de la
 * consulta (igualdad primero, rango después), que es el que MySQL aprovecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_index');
            $table->index('created_at', 'orders_created_at_index');
            // Mesas ocupadas: WHERE status = 'pending' AND table_id = ?
            $table->index(['status', 'table_id'], 'orders_status_table_index');
            // Reportes por día ya filtrados por estado.
            $table->index(['status', 'created_at'], 'orders_status_created_index');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->index('status', 'order_details_status_index');
            // El whereHas del KDS: por orden, filtrando estado del plato.
            $table->index(['order_id', 'status'], 'order_details_order_status_index');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->index('status', 'reservations_status_index');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            // El kardex ordena por fecha descendente y pagina.
            $table->index('created_at', 'inventory_logs_created_at_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('is_active', 'products_is_active_index');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index('created_at', 'expenses_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_index');
            $table->dropIndex('orders_created_at_index');
            $table->dropIndex('orders_status_table_index');
            $table->dropIndex('orders_status_created_index');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropIndex('order_details_status_index');
            $table->dropIndex('order_details_order_status_index');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_status_index');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropIndex('inventory_logs_created_at_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_is_active_index');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_created_at_index');
        });
    }
};
