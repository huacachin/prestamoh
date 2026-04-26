<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            if (!Schema::hasColumn('credits', 'mora1')) {
                $table->decimal('mora1', 10, 4)->default(0)->after('mora');
            }
            if (!Schema::hasColumn('credits', 'mora2')) {
                $table->decimal('mora2', 10, 4)->default(0)->after('mora1');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            if (Schema::hasColumn('credits', 'mora1')) $table->dropColumn('mora1');
            if (Schema::hasColumn('credits', 'mora2')) $table->dropColumn('mora2');
        });
    }
};
