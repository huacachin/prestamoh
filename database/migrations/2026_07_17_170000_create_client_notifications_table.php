<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones de cobranza por WhatsApp (modal de /clients): historial
 * numerado por cliente del texto enviado, y compromiso de pago opcional
 * (fecha + detalle) que alimenta la campana de compromisos del navbar.
 * Reemplaza a whatsapp_reminders (el check "enviado hoy" ahora se deriva
 * de la fecha de la notificación).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('numero'); // correlativo por cliente (1, 2, 3…)
            $table->text('mensaje');
            $table->string('telefono', 20)->nullable();
            // Compromiso de pago (opcional, editable después del envío)
            $table->date('compromiso_fecha')->nullable()->index();
            $table->text('compromiso_detalle')->nullable();
            $table->unsignedBigInteger('compromiso_user_id')->nullable();
            $table->timestamp('compromiso_cumplido_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'numero']);
        });

        Schema::dropIfExists('whatsapp_reminders');
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notifications');

        Schema::create('whatsapp_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('sent_on');
            $table->timestamps();
            $table->unique(['client_id', 'sent_on']);
            $table->index('sent_on');
        });
    }
};
