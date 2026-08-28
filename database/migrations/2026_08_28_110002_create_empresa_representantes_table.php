<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Representantes legales de la empresa deudora (tramo D, 28/08).
 *
 * Tabla propia (1-N desde client_empresas) y no columnas embebidas porque el
 * poder cambia de titular: el contrato debe citar al representante VIGENTE a
 * la fecha de emisión, y los anteriores quedan como historial auditable en
 * vez de pisarse. El gerente lleva los mismos datos personales que un deudor
 * (el párrafo de a.4 los exige todos y flexiona su género aparte).
 *
 * OJO refresh de BD: tabla NUEVA — va en la lista de preservación del
 * runbook (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_representantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_empresa_id')->constrained('client_empresas')->cascadeOnDelete();
            $table->string('cargo', 60)->default('GERENTE GENERAL');
            $table->string('nombre', 150);
            $table->string('tipo_documento', 10)->default('DNI');
            $table->string('documento', 20);
            $table->enum('sexo', ['M', 'F'])->default('M');
            $table->string('nacionalidad', 50)->nullable()->default('PERUANO');
            $table->string('ocupacion', 100)->nullable();
            $table->string('estado_civil', 30)->nullable();
            $table->string('domicilio', 300)->nullable();
            $table->boolean('vigente')->default(true);
            $table->timestamps();

            $table->index(['client_empresa_id', 'vigente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_representantes');
    }
};
