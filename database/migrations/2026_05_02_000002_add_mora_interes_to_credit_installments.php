<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_installments', function (Blueprint $table) {
            // Mora INTERÉS por cuota (separada de mora capital).
            // Mapea al campo `impomorai` del legacy huaca_det_cuentacorriente.
            // `importe_mora` se mantiene como mora CAPITAL.
            $table->decimal('mora_interes', 12, 2)->default(0)->after('importe_mora');
        });
    }

    public function down(): void
    {
        Schema::table('credit_installments', function (Blueprint $table) {
            $table->dropColumn('mora_interes');
        });
    }
};
