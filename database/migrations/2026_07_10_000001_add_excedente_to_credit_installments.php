<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuota uniforme redondeada a 0.10: el delta entre la cuota redondeada y
     * capital+interés se guarda como excedente (ingreso al cobrarse). Default 0
     * → los créditos existentes se comportan exactamente igual que antes.
     */
    public function up(): void
    {
        Schema::table('credit_installments', function (Blueprint $table) {
            $table->decimal('importe_excedente', 12, 2)->default(0)->after('importe_interes');
            $table->decimal('excedente_aplicado', 12, 2)->default(0)->after('interes_aplicado');
        });
    }

    public function down(): void
    {
        Schema::table('credit_installments', function (Blueprint $table) {
            $table->dropColumn(['importe_excedente', 'excedente_aplicado']);
        });
    }
};
