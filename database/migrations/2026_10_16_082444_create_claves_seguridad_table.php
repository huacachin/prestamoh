<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claves de seguridad de segundo nivel (16/10/2026, Antony): la primera protege
 * los cambios de rol que entran o salen de Director ('rol-director') y la
 * custodia el gerente. Solo se guarda el hash (bcrypt); la clave en claro no
 * existe en ningún lado del sistema. Se crea o cambia con el comando
 * usuarios:clave-director.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claves_seguridad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 40)->unique();
            $table->string('hash');
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_seguridad');
    }
};
