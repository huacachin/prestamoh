<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('credits', 'fecha_actualizacion')) {
            Schema::table('credits', function (Blueprint $table) {
                $table->date('fecha_actualizacion')->nullable()->after('fecha_prestamo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('credits', 'fecha_actualizacion')) {
            Schema::table('credits', function (Blueprint $table) {
                $table->dropColumn('fecha_actualizacion');
            });
        }
    }
};
