<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10/10/2026 (Antony): enlace EXACTO copropietario → préstamo. Hasta ahora el
 * contrato no guardaba quién era el codeudor (el snapshot solo lleva sus
 * datos tipeados), así que la referencia del copropietario al crédito se
 * deducía por el vehículo compartido. Desde ahora cada contrato guarda el id
 * del codeudor con ficha; los 34 contratos anteriores quedan en null y siguen
 * resolviéndose por el vehículo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->foreignId('codeudor_client_id')->nullable()->after('credit_id')->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->dropConstrainedForeignId('codeudor_client_id');
        });
    }
};
