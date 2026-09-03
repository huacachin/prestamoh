<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trámites notariales del Área Legal (reemplaza el Excel "D. Notaria
 * Hinojosa. Registro de documentos pendientes", que hoy acumula 12+
 * documentos varados sin alerta). Flujo: firmado_oficina → en_notaria →
 * firmado → por_recoger → recogido → archivado, con la excepción no_firmo
 * (cliente que no acude a firmar — hoy solo una nota "No firmo" a mano).
 *
 *  - estado_desde: fecha de entrada al estado ACTUAL — alimenta la alerta de
 *    "varado" (campana legal); el historial completo queda en Audit::log.
 *  - garantia_id/client_id nullable: la hoja "Otros" registra trámites sin
 *    garantía (cartas notariales, declaraciones juradas, testimonios).
 *  - expense_id nullable: gancho para el asiento automático del gasto
 *    notarial en la caja legal (fase posterior).
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramites_notariales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garantia_id')->nullable()->constrained('garantias')->nullOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('tipo', 40)->default('contrato_sigm');
            $table->string('descripcion')->nullable(); // detalle libre (hoja "Otros": carta notarial, testimonio...)
            $table->string('notaria', 120)->nullable();
            $table->string('estado', 20)->default('firmado_oficina');
            $table->date('estado_desde');
            $table->date('fecha_ingreso_notaria')->nullable();
            $table->date('fecha_firma')->nullable();
            $table->date('fecha_recojo')->nullable();
            $table->decimal('costo', 8, 2)->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->string('ubicacion_archivo')->nullable();
            $table->text('nota')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('requiere_revision')->default(false); // marcada por el importador
            $table->timestamps();

            $table->index(['estado', 'estado_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramites_notariales');
    }
};
