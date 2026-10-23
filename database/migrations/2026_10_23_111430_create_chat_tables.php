<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat de consulta (23/10/2026, Antony): conversaciones y mensajes del
 * asistente que responde preguntas sobre clientes y créditos con herramientas
 * de solo lectura. Cada pregunta y cada respuesta quedan guardadas con quién
 * las hizo, qué consultas corrió el modelo, sus tokens y su costo en USD,
 * para controlar el gasto de la prueba y revisar qué se preguntó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titulo', 120)->nullable(); // la primera pregunta, recortada
            $table->string('modelo', 60)->nullable();
            $table->unsignedInteger('preguntas')->default(0);
            $table->unsignedBigInteger('tokens_entrada')->default(0);
            $table->unsignedBigInteger('tokens_salida')->default(0);
            $table->decimal('costo_usd', 10, 6)->default(0);
            $table->timestamp('ultima_actividad_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'ultima_actividad_at']);
        });

        Schema::create('chat_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('chat_conversaciones')->cascadeOnDelete();
            $table->string('rol', 10); // usuario | asistente
            $table->text('contenido');
            $table->json('herramientas')->nullable(); // [{nombre, argumentos, ms, error}]
            $table->string('modelo', 60)->nullable();
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->unsignedInteger('tokens_cache_lectura')->default(0);
            $table->unsignedInteger('tokens_cache_escritura')->default(0);
            $table->decimal('costo_usd', 10, 6)->default(0);
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['rol', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mensajes');
        Schema::dropIfExists('chat_conversaciones');
    }
};
