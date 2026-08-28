<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos generados por el Área Legal (garantía mobiliaria y, a futuro,
 * escritos). El documento emitido debe poder reimprimirse EXACTO aunque el
 * cliente o el crédito cambien después → datos_snapshot congela el view-model
 * completo al generar. Regenerar = fila nueva con version+1 (jamás se pisa un
 * PDF emitido); sha256 permite verificar la integridad del archivo.
 *
 *  - numero: correlativo tipo 'Contrato' (App\Support\Correlativo, con lock).
 *  - parametros: los 7 ejes de la plantilla maestra (género, nº deudores,
 *    natural/jurídica, gps, custodia, bienes presente/futuro, destino del
 *    desembolso) + datos del voucher del Anexo 2 y del gerente si es empresa.
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garantia_id')->constrained('garantias')->cascadeOnDelete();
            $table->foreignId('credit_id')->constrained('credits');
            $table->foreignId('client_id')->constrained('clients');
            $table->string('numero', 30)->unique();
            $table->string('tipo', 40)->default('garantia_mobiliaria');
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('parametros')->nullable();
            $table->json('datos_snapshot')->nullable();
            $table->string('estado', 20)->default('emitido'); // borrador|emitido|anulado
            $table->string('pdf_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['garantia_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
