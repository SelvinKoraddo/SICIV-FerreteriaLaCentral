<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usuario', 50)->unique()->after('email');
            $table->enum('rol', ['ADMINISTRADOR', 'VENDEDOR', 'ENCARGADO_INVENTARIO'])
                ->default('VENDEDOR')->after('usuario');
            $table->boolean('activo')->default(true)->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['usuario']);
            $table->dropColumn(['usuario', 'rol', 'activo']);
        });
    }
};
