<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            if (!Schema::hasColumn('credits', 'gat')) {
                $table->decimal('gat', 12, 2)->default(0)->after('cod_rem');
            }
            if (!Schema::hasColumn('credits', 'idcan')) {
                $table->unsignedBigInteger('idcan')->nullable()->after('gat')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            if (Schema::hasColumn('credits', 'gat')) $table->dropColumn('gat');
            if (Schema::hasColumn('credits', 'idcan')) $table->dropColumn('idcan');
        });
    }
};
