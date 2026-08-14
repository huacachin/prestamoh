<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones multi-crédito: cada notificación queda ligada al crédito
 * que se está cobrando (un cliente puede tener varios créditos atrasados a
 * la vez). Nullable: el historial previo a este cambio era a nivel cliente
 * y se muestra como "General". Sin FK a propósito — el flujo de rebuild
 * (migrate:fresh + legacy:migrate) reconstruye credits y una FK ataría el
 * orden de esa reconstrucción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_id')->nullable()->after('client_id')->index();
            // El correlativo pasa a ser por crédito: el único viejo
            // (client_id, numero) chocaría con el N° 1 de dos créditos del
            // mismo cliente. Las filas históricas (credit_id NULL) no se
            // tocan: en MySQL cada NULL cuenta distinto en un unique.
            $table->dropUnique(['client_id', 'numero']);
            $table->unique(['client_id', 'credit_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::table('client_notifications', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'credit_id', 'numero']);
            $table->unique(['client_id', 'numero']);
            $table->dropColumn('credit_id');
        });
    }
};
