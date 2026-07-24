<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Snapshot diario de morosidad (reconstruible; ver MorosidadService).
        // Definición espejo de /reports/portfolio: un crédito está EN MORA
        // cuando su fecha de vencimiento FINAL ya pasó y aún tiene saldo.
        Schema::create('cache_morosidad_diaria', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('saldo_mora', 14, 2)->default(0);      // saldo de créditos vencidos
            $table->decimal('saldo_cartera', 14, 2)->default(0);   // saldo total por cobrar
            $table->unsignedInteger('n_creditos_mora')->default(0);
            $table->decimal('pct', 6, 2)->default(0);              // mora*100/cartera
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache_morosidad_diaria');
    }
};
