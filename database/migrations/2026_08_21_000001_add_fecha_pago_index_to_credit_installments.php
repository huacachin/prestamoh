<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguro a futuro para Reports/Advisor (whereBetween fecha_pago): hoy la
 * salva que solo hay ~220 créditos activos (pivota por credits_situacion),
 * pero sin índice degrada linealmente si la cartera activa crece.
 *
 * NOTA de la validación 21/08: los 3 índices que la auditoría marcó como
 * redundantes (payments_tipo, credit_installments_credit_id_pagado y
 * credit_installments_pagado) NO se dropean — performance_schema con 21 días
 * de producción muestra 6.5M/264k/13k lecturas respectivamente. La auditoría
 * muestreó EXPLAIN de pocas queries y se equivocó; los contadores reales
 * mandan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_installments', function (Blueprint $table) {
            $table->index('fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::table('credit_installments', function (Blueprint $table) {
            $table->dropIndex(['fecha_pago']);
        });
    }
};
