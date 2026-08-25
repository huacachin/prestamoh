<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recursos y solicitudes presentados contra una papeleta (descargo,
 * apelación, prescripción de PIT, beneficio PRS, caducidad, acceso a la
 * información...). Los plazos legales (10 días hábiles el acceso a la
 * información, 30 los recursos) hoy viven en texto libre tipo "Descargo
 * Vence 30/10" y ya hay casos con "Plazo Venc." — aquí plazo_vence es dato
 * y la campana legal lo levanta mientras resultado sea 'pendiente'.
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papeleta_recursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('papeleta_id')->constrained('papeletas')->cascadeOnDelete();
            $table->string('tipo', 25);
            $table->string('nro_tramite', 40)->nullable(); // MPD0000426439 / 4950-2026-02-0002728 / 298-08X-...
            $table->date('fecha_presentacion');
            $table->date('plazo_vence')->nullable()->index();
            $table->string('resultado', 15)->default('pendiente');
            $table->date('resuelto_at')->nullable();
            $table->text('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papeleta_recursos');
    }
};
