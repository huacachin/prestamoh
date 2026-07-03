<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capital declarado del cliente. Su línea de crédito (informativa) es el
 * 25% de este capital; se calcula en el modelo, no se persiste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('capital', 12, 2)->nullable()->after('giro');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('capital');
        });
    }
};
