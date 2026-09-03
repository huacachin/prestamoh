<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de PERSONA JURÍDICA del cliente (tramo D, 28/08).
 *
 * La empresa se registra como un cliente con tipo_documento='RUC' (la razón
 * social vive en clients.nombre y el RUC en clients.documento — regla de
 * negocio previa). Esta tabla satélite guarda lo que el contrato SIGM a.4
 * exige y que hasta ahora vivía SOLO en memoria del wizard, congelado en el
 * snapshot: partida registral, oficina registral, domicilio y correo. Sin
 * ella, un segundo crédito de la misma empresa obligaba a retipearlo todo.
 *
 * OJO refresh de BD: tabla NUEVA, no viene del legacy — va en la lista de
 * preservación del runbook (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->string('partida_registral', 30)->nullable();
            $table->string('oficina_registral', 80)->nullable();
            $table->string('domicilio', 300)->nullable();
            $table->string('correo', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_empresas');
    }
};
