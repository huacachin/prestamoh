<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->string('modo', 30)->nullable()->after('reason');       // Fijos / Otros / Compra
            $table->string('documento', 30)->nullable()->after('modo');    // CAPITAL / INTERES / MORA
            $table->string('asesor', 100)->nullable()->after('documento');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropColumn(['modo', 'documento', 'asesor']);
        });
    }
};
