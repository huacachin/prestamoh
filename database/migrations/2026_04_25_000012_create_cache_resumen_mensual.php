<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cache_resumen_mensual')) {
            Schema::create('cache_resumen_mensual', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('idmes');
                $table->unsignedSmallInteger('idano');
                $table->decimal('capital', 14, 2)->default(0);
                $table->decimal('recucapi', 14, 2)->default(0);
                $table->unsignedInteger('n1')->default(0);
                $table->decimal('mensual', 14, 2)->default(0);
                $table->unsignedInteger('n2')->default(0);
                $table->decimal('semanal', 14, 2)->default(0);
                $table->unsignedInteger('n3')->default(0);
                $table->decimal('diario', 14, 2)->default(0);
                $table->decimal('mora', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('otros', 14, 2)->default(0);
                $table->decimal('egreso', 14, 2)->default(0);
                $table->decimal('utilidad', 14, 2)->default(0);
                $table->decimal('otros2', 14, 2)->default(0);
                $table->decimal('egresov', 14, 2)->default(0);
                $table->decimal('utilidad2', 14, 2)->default(0);
                $table->decimal('fijoi', 14, 2)->default(0);
                $table->decimal('otrosi', 14, 2)->default(0);
                $table->decimal('fijoe', 14, 2)->default(0);
                $table->decimal('otrose', 14, 2)->default(0);
                $table->decimal('mora1', 14, 2)->default(0);
                $table->decimal('mora3', 14, 2)->default(0);
                $table->decimal('mora4', 14, 2)->default(0);
                $table->timestamps();

                $table->unique(['idmes', 'idano']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_resumen_mensual');
    }
};
