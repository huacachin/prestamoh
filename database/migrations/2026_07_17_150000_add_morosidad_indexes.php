<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para el semáforo de morosidad de /clients (y consultas afines):
 *
 * - credits (situacion, client_id): resuelve "créditos Activos de estos
 *   clientes" solo con índice (también sirve al pintado de clientes con
 *   crédito vigente, que hace DISTINCT client_id sobre el mismo filtro).
 * - credit_installments (credit_id, pagado, fecha_vencimiento): el conteo de
 *   cuotas vencidas impagas por crédito queda cubierto por el índice (el
 *   rango de fecha se filtra dentro del índice, sin tocar filas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->index(['situacion', 'client_id'], 'credits_situacion_client_index');
        });

        Schema::table('credit_installments', function (Blueprint $table) {
            $table->index(['credit_id', 'pagado', 'fecha_vencimiento'], 'cri_credit_pagado_venc_index');
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropIndex('credits_situacion_client_index');
        });

        Schema::table('credit_installments', function (Blueprint $table) {
            $table->dropIndex('cri_credit_pagado_venc_index');
        });
    }
};
