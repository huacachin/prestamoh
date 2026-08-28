<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos electrónicos presentados en el SIGM de SUNARP (D. Leg. 1400): cada
 * constitución, renovación, modificación, cancelación o ejecución es un aviso
 * con su N° de formulario (ej. 2026-380805) y folio causal (20 dígitos).
 * El timeline de avisos de una garantía reproduce el registro Excel "B" que
 * llevaba el área — la vigencia del último aviso alimenta garantias.vigencia_hasta.
 *
 *  - nro_formulario y folio son unique NULLABLE (MySQL admite múltiples NULL):
 *    se puede registrar el aviso apenas se presenta y completar el folio después.
 *  - expense_id queda desde ya para la caja legal (fase posterior): el asiento
 *    automático de la tasa SIGM (S/ 4.00) se enlazará aquí sin re-migrar.
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sigm_avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garantia_id')->constrained('garantias')->cascadeOnDelete();
            $table->string('tipo', 20); // constitucion|renovacion|modificacion|cancelacion|ejecucion
            $table->string('nro_formulario', 20)->nullable()->unique();
            $table->string('folio', 25)->nullable()->unique();
            $table->date('fecha_presentacion');
            $table->date('vigencia_hasta')->nullable()->index();
            $table->string('modalidad_ejecucion', 20)->nullable(); // venta|adjudicacion
            $table->date('fecha_inicio_ejecucion')->nullable();
            $table->date('fecha_termino_ejecucion')->nullable();
            $table->decimal('tasa', 8, 2)->default(4.00);
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->string('estado', 20)->default('registrado'); // registrado|observado|anulado
            $table->string('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sigm_avisos');
    }
};
