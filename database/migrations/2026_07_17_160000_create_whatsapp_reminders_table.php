<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de recordatorios de morosidad enviados por WhatsApp desde /clients:
 * una fila por cliente y día (compartida entre usuarios, a diferencia de
 * localStorage). El unique permite insertOrIgnore idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_reminders');
    }
};
