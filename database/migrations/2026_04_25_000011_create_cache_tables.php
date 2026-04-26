<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cache_capital_cobrado')) {
            Schema::create('cache_capital_cobrado', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->unique();
                $table->decimal('importe', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cache_credit_totals')) {
            Schema::create('cache_credit_totals', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->unique();
                $table->decimal('interes', 14, 2)->default(0);
                $table->decimal('mora', 14, 2)->default(0);
                $table->unsignedInteger('n_mensual')->default(0);
                $table->decimal('mensual', 14, 2)->default(0);
                $table->unsignedInteger('n_semanal')->default(0);
                $table->decimal('semanal', 14, 2)->default(0);
                $table->unsignedInteger('n_diario')->default(0);
                $table->decimal('diario', 14, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_capital_cobrado');
        Schema::dropIfExists('cache_credit_totals');
    }
};
