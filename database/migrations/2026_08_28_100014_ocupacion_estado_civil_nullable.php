<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación con feat/area-legal (28/08), parte clients: `ocupacion` y
 * `estado_civil` pasan a NULLABLE, como las declaraba la migración de área
 * legal (2026_08_24_000001, descartada por colisión). Era el diseño correcto:
 * NOT NULL con default hacía IMPOSIBLE representar "no declarado" — el
 * validador de contratos no podía distinguir a un transportista soltero real
 * de uno que nunca lo afirmó.
 *
 * NO toca ninguna fila: los clientes migrados conservan su default. El UPDATE
 * que pondría NULL donde nadie declaró el dato sigue siendo decisión de
 * negocio pendiente (docs/PENDIENTES.md) — esto solo destraba el paso 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('ocupacion', 30)->nullable()->default('transportista')->change();
            $table->string('estado_civil', 20)->nullable()->default('soltero')->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('ocupacion', 30)->nullable(false)->default('transportista')->change();
            $table->string('estado_civil', 20)->nullable(false)->default('soltero')->change();
        });
    }
};
