<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garantías mobiliarias del Área Legal (una por crédito garantizado; un
 * refinanciamiento puede generar una nueva → credit_id NO es unique).
 * Reemplaza como fuente de verdad al hack sobre clients.zona
 * (App\Support\Garantias), que se mantiene como fallback de solo lectura.
 *
 *  - vigencia_hasta: denormalizada del último aviso SIGM de constitución o
 *    renovación. "Por renovar" NO es un estado: se deriva de esta fecha
 *    (badge del Index y campana legal).
 *  - requiere_revision: marcada por el importador de los Excel históricos
 *    cuando detecta datos inconsistentes entre registros (nunca autocorrige).
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garantias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained('credits');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('codeudor_client_id')->nullable()->constrained('clients');
            $table->string('tipo', 30)->default('mobiliaria_vehicular');
            $table->string('tipo_persona', 10)->default('natural'); // natural|juridica
            $table->boolean('gps')->default(false);
            $table->boolean('custodia')->default(false);
            $table->decimal('monto_gravamen', 12, 2)->nullable();
            $table->string('estado', 20)->default('en_constitucion');
            $table->date('vigencia_hasta')->nullable();
            $table->date('fecha_constitucion')->nullable();
            $table->boolean('requiere_revision')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'vigencia_hasta']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garantias');
    }
};
