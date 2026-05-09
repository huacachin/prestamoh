<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_avales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->string('dni', 20);
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->unique(['client_id', 'dni']); // un mismo DNI no puede repetirse como aval del mismo cliente
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_avales');
    }
};
