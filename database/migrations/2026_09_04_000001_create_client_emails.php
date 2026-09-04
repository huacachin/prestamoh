<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correos múltiples por cliente (04/09). La regla de oro: `clients.email`
 * es SIEMPRE el espejo del correo marcado como principal — contratos
 * (cláusula de notificaciones, guard de emisión, Anexo 1), exports y todo
 * lo existente siguen leyendo esa columna sin cambios.
 *
 * Sin reconciliación con el legacy: retirado el 04/09 — prestamoh es la
 * producción única y estos datos son permanentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('email', 150);
            $table->boolean('principal')->default(false);
            $table->timestamps();
            $table->unique(['client_id', 'email']);
        });

        // Backfill: el correo actual de cada ficha nace como principal.
        DB::statement("
            INSERT INTO client_emails (client_id, email, principal, created_at, updated_at)
            SELECT id, TRIM(email), 1, NOW(), NOW()
            FROM clients
            WHERE email IS NOT NULL AND TRIM(email) <> ''
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('client_emails');
    }
};
