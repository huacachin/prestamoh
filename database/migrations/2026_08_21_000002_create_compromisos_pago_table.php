<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compromisos de pago MÚLTIPLES por notificación (antes: una sola columna en
 * client_notifications, un compromiso por notificación). Caso real: "el 25/08
 * paga 2 cuotas y el 30/08 paga 3". Se backfillean los existentes; las
 * columnas compromiso_* de client_notifications quedan en su sitio (dejan de
 * usarse) para poder volver atrás sin pérdida.
 *
 * OJO refresh de BD: esta tabla NO viene del legacy — está en la lista de
 * preservación del runbook (docs/INSTALACION.md §8.6b) junto a
 * client_notifications y short_links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromisos_pago', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('credit_id')->nullable()->index();
            $table->unsignedBigInteger('client_notification_id')->nullable()->index();
            $table->date('fecha');
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('cumplido_at')->nullable();
            $table->timestamps();
        });

        // Backfill de los compromisos existentes (uno por notificación)
        DB::statement('
            INSERT INTO compromisos_pago
                (client_id, credit_id, client_notification_id, fecha, detalle, user_id, cumplido_at, created_at, updated_at)
            SELECT client_id, credit_id, id, compromiso_fecha, compromiso_detalle, compromiso_user_id,
                   compromiso_cumplido_at, COALESCE(compromiso_registrado_at, created_at), updated_at
            FROM client_notifications
            WHERE compromiso_fecha IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisos_pago');
    }
};
