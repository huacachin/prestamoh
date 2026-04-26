<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->date('fecha_prestamo');
            $table->date('fecha_actualizacion')->nullable();
            $table->decimal('importe', 12, 2);
            $table->integer('cuotas');
            $table->unsignedTinyInteger('tipo_planilla')->comment('1=Semanal, 3=Mensual, 4=Diario');
            $table->decimal('interes', 8, 4)->default(0);
            $table->decimal('interes_total', 12, 2)->default(0);
            $table->decimal('mora', 12, 2)->default(0);
            $table->decimal('mora1', 10, 4)->default(0);
            $table->decimal('mora2', 10, 4)->default(0);
            $table->string('moneda', 10)->default('Soles');
            $table->string('documento', 50)->nullable();
            $table->string('glosa')->nullable();
            $table->string('situacion', 30)->default('Activo')->index();
            $table->unsignedTinyInteger('estado')->default(1)->index();
            $table->boolean('refinanciado')->default(false);
            $table->string('cod_rem', 10)->nullable();
            $table->decimal('gat', 12, 2)->default(0);
            $table->unsignedBigInteger('idcan')->nullable()->index();
            $table->date('fecha_vencimiento')->nullable();
            $table->date('fecha_cancelacion')->nullable();
            $table->string('asesor')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario', 50)->nullable();
            $table->foreignId('headquarter_id')->nullable()->constrained('headquarters')->nullOnDelete();
            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
