<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'fecha_registro')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->date('fecha_registro')->nullable()->after('documento');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'fecha_registro')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('fecha_registro');
            });
        }
    }
};
