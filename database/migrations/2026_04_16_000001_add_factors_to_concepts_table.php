<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->decimal('factor_ingreso', 10, 2)->default(0)->after('type');  // facpago
            $table->decimal('factor_egreso', 10, 2)->default(0)->after('factor_ingreso'); // facdivi
        });
    }

    public function down(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->dropColumn(['factor_ingreso', 'factor_egreso']);
        });
    }
};
