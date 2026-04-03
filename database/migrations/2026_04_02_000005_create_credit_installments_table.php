<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained('credits')->cascadeOnDelete();
            $table->unsignedSmallInteger('num_cuota');
            $table->date('fecha_vencimiento');
            $table->decimal('importe_cuota', 12, 2)->default(0);
            $table->decimal('importe_interes', 12, 2)->default(0);
            $table->decimal('importe_aplicado', 12, 2)->default(0);
            $table->decimal('interes_aplicado', 12, 2)->default(0);
            $table->decimal('importe_mora', 12, 2)->default(0);
            $table->boolean('pagado')->default(false)->index();
            $table->date('fecha_pago')->nullable();
            $table->string('observacion')->nullable();
            $table->string('usuario')->nullable();
            $table->timestamps();

            $table->index(['credit_id', 'pagado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_installments');
    }
};
