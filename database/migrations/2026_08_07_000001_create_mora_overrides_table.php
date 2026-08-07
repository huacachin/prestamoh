<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de ajustes manuales de mora.
 *
 * El campo "Total Mora" de /payments/create es editable por los roles con
 * permiso `pagos.mora-manual` y REEMPLAZA a la mora calculada. Hasta ahora ese
 * override no dejaba rastro: no se guardaba la mora original, ni el motivo, ni
 * quién lo autorizó — el único vestigio era un texto ambiguo en
 * `payments.detalle`. Para una operación de dinero (y más durante el run
 * paralelo con el legacy, donde el ajuste es rutinario) eso es un hueco de
 * control.
 *
 * Cada fila registra un ajuste: cuánto salía por fórmula, cuánto se cobró de
 * verdad, la diferencia, el motivo declarado y el responsable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mora_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_id')->index();

            // Pago MORA generado. NULL cuando el ajuste dejó la mora en 0
            // (se perdonó entera): no hay pago, pero el ajuste sí ocurrió.
            $table->unsignedBigInteger('payment_id')->nullable()->index();

            // Cobro (lote) dentro del que se hizo el ajuste.
            $table->unsignedBigInteger('mass_deletion_id')->nullable()->index();

            $table->decimal('mora_calculada', 12, 2);
            $table->decimal('mora_cobrada', 12, 2);
            // cobrada - calculada. Negativo = se rebajó (el caso normal).
            $table->decimal('diferencia', 12, 2);

            // Contexto del cálculo original, para poder releer el ajuste
            // sin recomputar (los días y la tarifa cambian con el tiempo).
            $table->integer('dias_atraso')->default(0);
            $table->decimal('mora_diaria', 12, 2)->default(0);

            $table->string('motivo');
            $table->date('fecha')->index();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mora_overrides');
    }
};
