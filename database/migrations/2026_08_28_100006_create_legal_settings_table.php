<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constantes de negocio del Área Legal, editables sin deploy (pantalla
 * legal/settings): acreedor, apoderada, representantes para la ejecución,
 * cuentas bancarias del acreedor, abogada firmante, WhatsApp. Hoy esos datos
 * viven hardcodeados en las plantillas de NotificationsModal y en los .docx.
 * valor es JSON: admite tanto un string como estructuras (lista de
 * representantes, lista de cuentas).
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->json('valor')->nullable();
            $table->string('etiqueta')->nullable();
            $table->string('updated_by')->nullable(); // username
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_settings');
    }
};
