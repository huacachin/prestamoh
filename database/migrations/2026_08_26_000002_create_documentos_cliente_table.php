<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos generados por cliente (pedido 26/08): Anexo 1 (cronograma),
 * Contrato de garantía mobiliaria y Anexo 2 (constancia de entrega), cada uno
 * con su momento en el flujo real del área. El snapshot congela los datos al
 * emitir: el PDF se guarda en disco y el Word se deriva al vuelo del snapshot,
 * así ambos formatos son siempre idénticos en contenido. Regenerar crea
 * versión nueva — un documento emitido jamás se pisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('credit_id')->constrained('credits');
            $table->string('tipo', 10); // anexo1|contrato|anexo2
            $table->string('modelo', 20)->nullable(); // clave del modelo elegido (solo contrato)
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('snapshot');
            $table->string('pdf_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('estado', 10)->default('emitido'); // emitido|anulado
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'tipo']);
            $table->index('credit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_cliente');
    }
};
