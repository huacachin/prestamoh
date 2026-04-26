<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'hora')) {
                $table->string('hora', 10)->nullable()->after('fecha');
            }
            if (!Schema::hasColumn('payments', 'usuario')) {
                $table->string('usuario', 50)->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'hora')) $table->dropColumn('hora');
            if (Schema::hasColumn('payments', 'usuario')) $table->dropColumn('usuario');
        });
    }
};
