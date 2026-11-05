<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->increments('id_proveedor');
            $table->string('nombre', 100);
            $table->string('nit', 20)->unique();
            $table->string('telefono', 15)->nullable();
            $table->string('correo', 100)->nullable();
            $table->text('direccion')->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->increments('id_categoria');
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activa')->default(true);
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->increments('id_producto');
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->string('unidad_medida', 20);
            $table->decimal('precio_venta', 10, 2);
            $table->decimal('existencia_actual', 10, 2)->default(0);
            $table->decimal('existencia_minima', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('id_categoria');
            $table->foreign('id_categoria')->references('id_categoria')->on('categorias')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('entradas_inventario', function (Blueprint $table) {
            $table->increments('id_entrada');
            $table->string('numero', 20)->unique();
            $table->dateTime('fecha');
            $table->string('referencia', 50)->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->string('estado', 20)->default('CONFIRMADA');
            $table->unsignedInteger('id_proveedor');
            $table->unsignedBigInteger('id_usuario');
            $table->foreign('id_proveedor')->references('id_proveedor')->on('proveedores')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('id_usuario')->references('id')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('detalles_entrada', function (Blueprint $table) {
            $table->increments('id_detalle_entrada');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('costo_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->unsignedInteger('id_entrada');
            $table->unsignedInteger('id_producto');
            $table->foreign('id_entrada')->references('id_entrada')->on('entradas_inventario')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('id_producto')->references('id_producto')->on('productos')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->increments('id_venta');
            $table->string('numero', 20)->unique();
            $table->dateTime('fecha');
            $table->decimal('total', 10, 2)->default(0);
            $table->string('estado', 20)->default('CONFIRMADA');
            $table->unsignedBigInteger('id_usuario');
            $table->foreign('id_usuario')->references('id')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('detalles_venta', function (Blueprint $table) {
            $table->increments('id_detalle_venta');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->unsignedInteger('id_venta');
            $table->unsignedInteger('id_producto');
            $table->foreign('id_venta')->references('id_venta')->on('ventas')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('id_producto')->references('id_producto')->on('productos')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->increments('id_movimiento');
            $table->dateTime('fecha');
            $table->enum('tipo', ['APERTURA', 'RECEPCION', 'VENTA']);
            $table->decimal('cantidad', 10, 2);
            $table->decimal('saldo_anterior', 10, 2);
            $table->decimal('saldo_resultante', 10, 2);
            $table->string('referencia', 50)->nullable();
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_detalle_venta')->nullable();
            $table->unsignedInteger('id_detalle_entrada')->nullable();
            $table->foreign('id_producto')->references('id_producto')->on('productos')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('id_detalle_venta')->references('id_detalle_venta')->on('detalles_venta')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('id_detalle_entrada')->references('id_detalle_entrada')->on('detalles_entrada')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('detalles_venta');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('detalles_entrada');
        Schema::dropIfExists('entradas_inventario');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('proveedores');
    }
};
