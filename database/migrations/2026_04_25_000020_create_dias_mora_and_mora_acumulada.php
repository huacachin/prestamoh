<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dias_mora', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_id')->index();
            $table->integer('dias')->default(0);          // legacy: diascan (días aplicados al cobrar)
            $table->integer('dias_descontados')->default(0); // legacy: diascondenado (descontados)
            $table->timestamps();
        });

        Schema::create('mora_acumulada', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_id')->index();
            $table->decimal('importe', 12, 2)->default(0);
            $table->integer('dias')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dias_mora');
        Schema::dropIfExists('mora_acumulada');
    }
};
