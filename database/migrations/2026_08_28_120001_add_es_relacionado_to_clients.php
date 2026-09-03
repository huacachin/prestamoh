<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personas RELACIONADAS (28/08): copropietarios/codeudores creados desde el
 * alta rápida del buscador de copropietario.
 *
 * Viven en `clients` (misma tabla, marcados) y NO en una tabla aparte, a
 * propósito: la copropietaria de hoy pide su propio crédito mañana, y con el
 * flag la "promoción" es completar la ficha y apagarlo — mismo id, mismo DNI,
 * sus vehículos y vínculos intactos. Con tabla aparte ese día habría persona
 * duplicada y migración manual.
 *
 * El listado /clients y el export los EXCLUYEN por defecto: no ensucian la
 * lista ni la cartera de ningún asesor (tampoco tienen asesor ni expediente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('es_relacionado')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('es_relacionado');
        });
    }
};
