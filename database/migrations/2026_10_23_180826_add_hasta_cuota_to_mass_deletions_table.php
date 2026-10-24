<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Calcular cancelación" (23/10/2026, Antony): el recibo de ese cobro lista una
 * por una solo las cuotas hasta la elegida (las siguientes van en una línea) y,
 * si canceló el crédito, dice cuánto interés de las siguientes se exoneró. Se
 * guarda en el lote para que salga igual al verlo, imprimirlo o reimprimirlo.
 *
 * - hasta_cuota: la cuota elegida en el cálculo (cancele o quede vigente).
 * - interes_exonerado: solo si ese cobro canceló el crédito.
 * Null en todos los demás cobros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mass_deletions', function (Blueprint $table) {
            $table->unsignedSmallInteger('hasta_cuota')->nullable()->after('performed_by');
            $table->decimal('interes_exonerado', 12, 2)->nullable()->after('hasta_cuota');
        });
    }

    public function down(): void
    {
        Schema::table('mass_deletions', function (Blueprint $table) {
            $table->dropColumn(['hasta_cuota', 'interes_exonerado']);
        });
    }
};
