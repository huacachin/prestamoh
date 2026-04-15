<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('expediente', 20)->nullable()->index();
            $table->string('nombre');
            $table->string('apellido_pat')->nullable();
            $table->string('apellido_mat')->nullable();
            $table->string('tipo_documento', 10)->default('DNI');
            $table->string('documento', 20)->nullable()->index();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('sexo', ['M', 'F'])->nullable();
            $table->string('email')->nullable();
            $table->string('telefono_fijo', 20)->nullable();
            $table->string('celular1', 20)->nullable();
            $table->string('celular2', 20)->nullable();
            $table->string('direccion')->nullable();
            $table->string('referencia')->nullable();
            $table->string('distrito')->nullable();
            $table->string('provincia')->nullable();
            $table->string('departamento')->nullable();
            $table->string('zona', 100)->nullable();
            $table->string('contacto_emergencia')->nullable();
            $table->string('telefono_contacto', 20)->nullable();
            $table->string('banco_haberes')->nullable();
            $table->string('cuenta_haberes')->nullable();
            $table->string('banco_cts')->nullable();
            $table->string('cuenta_cts')->nullable();
            $table->string('afp')->nullable();
            $table->string('cussp')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('imagen')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('asesor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('headquarter_id')->nullable()->constrained('headquarters')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
