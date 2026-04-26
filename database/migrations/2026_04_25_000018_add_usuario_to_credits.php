<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            if (!Schema::hasColumn('credits', 'usuario')) {
                $table->string('usuario', 50)->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            if (Schema::hasColumn('credits', 'usuario')) $table->dropColumn('usuario');
        });
    }
};
