<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'latitud2')) {
                $table->decimal('latitud2', 10, 7)->nullable()->after('longitud');
            }
            if (!Schema::hasColumn('clients', 'longitud2')) {
                $table->decimal('longitud2', 10, 7)->nullable()->after('latitud2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'latitud2')) {
                $table->dropColumn('latitud2');
            }
            if (Schema::hasColumn('clients', 'longitud2')) {
                $table->dropColumn('longitud2');
            }
        });
    }
};
