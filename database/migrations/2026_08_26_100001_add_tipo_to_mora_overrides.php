<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tipifica la bitácora de mora: 'ajuste' (override manual del monto),
// 'condonacion-vigente' y 'condonacion-acumulada' (switches al cancelar).
// Antes las condonaciones por switch no dejaban rastro alguno.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mora_overrides', function (Blueprint $table) {
            $table->string('tipo', 30)->default('ajuste')->after('mass_deletion_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mora_overrides', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
