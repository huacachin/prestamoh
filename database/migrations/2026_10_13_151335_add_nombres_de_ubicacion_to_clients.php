<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 13/10/2026 (Antony): en la pestaña GPS, las etiquetas "Casa" y "Negocio" se
 * pueden renombrar. Las coordenadas siguen en latitud/longitud y
 * latitud2/longitud2; aquí solo va el nombre que el usuario les ponga
 * (null = el de siempre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('ubicacion1_nombre', 60)->nullable()->after('longitud2');
            $table->string('ubicacion2_nombre', 60)->nullable()->after('ubicacion1_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['ubicacion1_nombre', 'ubicacion2_nombre']);
        });
    }
};
