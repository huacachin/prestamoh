<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained('credits')->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('credit_installments')->nullOnDelete();
            $table->string('modo', 30)->default('CREDITO');
            $table->string('tipo', 20)->index()->comment('CAPITAL, INTERES, MORA');
            $table->string('documento', 50)->nullable();
            $table->string('nro_recibo', 50)->nullable();
            $table->date('fecha')->nullable();
            $table->string('hora', 10)->nullable();
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 10)->default('Soles');
            $table->decimal('tipo_cambio', 8, 4)->nullable();
            $table->text('detalle')->nullable();
            $table->string('asesor')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario', 50)->nullable();
            $table->foreignId('headquarter_id')->nullable()->constrained('headquarters')->nullOnDelete();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['credit_id', 'fecha']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
