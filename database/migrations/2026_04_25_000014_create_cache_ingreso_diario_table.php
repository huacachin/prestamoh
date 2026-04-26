<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache_ingreso_diario', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('importe', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_ingreso_diario');
    }
};
