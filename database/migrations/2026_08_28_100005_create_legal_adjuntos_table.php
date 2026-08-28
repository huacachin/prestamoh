<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adjuntos del Área Legal, polimórficos (a diferencia de client_attachments /
 * income_attachments / expense_attachments, que son 3 tablas idénticas): un
 * solo almacén para vouchers del Anexo 2, constancias SIGM, testimonios,
 * resoluciones y actas, colgando de garantías, avisos, contratos, etc.
 * Columnas calcadas del patrón de client_attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->morphs('adjuntable'); // adjuntable_type + adjuntable_id (con índice)
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('path');           // ruta relativa al disk public
            $table->string('thumb_path')->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('uploaded_by')->nullable(); // username
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_adjuntos');
    }
};
