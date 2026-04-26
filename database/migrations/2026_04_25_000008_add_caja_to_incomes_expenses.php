<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('incomes', 'caja')) {
            Schema::table('incomes', function (Blueprint $table) {
                $table->unsignedTinyInteger('caja')->default(1)->index()->after('headquarter_id');
            });
        }
        if (!Schema::hasColumn('expenses', 'caja')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->unsignedTinyInteger('caja')->default(1)->index()->after('headquarter_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('incomes', 'caja')) {
            Schema::table('incomes', function (Blueprint $table) {
                $table->dropColumn('caja');
            });
        }
        if (Schema::hasColumn('expenses', 'caja')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('caja');
            });
        }
    }
};
