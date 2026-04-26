<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'usuario')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('usuario', 50)->nullable()->after('fecha_registro');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'usuario')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('usuario');
            });
        }
    }
};
