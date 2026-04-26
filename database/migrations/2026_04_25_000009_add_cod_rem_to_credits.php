<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('credits', 'cod_rem')) {
            Schema::table('credits', function (Blueprint $table) {
                $table->string('cod_rem', 10)->nullable()->after('refinanciado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('credits', 'cod_rem')) {
            Schema::table('credits', function (Blueprint $table) {
                $table->dropColumn('cod_rem');
            });
        }
    }
};
