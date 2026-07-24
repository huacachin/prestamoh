<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vincula el egreso automático "Dep. ..." con el cobro que lo generó
        // (pago vía depósito). La reversa del cobro elimina el egreso vinculado.
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('mass_deletion_id')->nullable()->after('parent_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('mass_deletion_id');
        });
    }
};
