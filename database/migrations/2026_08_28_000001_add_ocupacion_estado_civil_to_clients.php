<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Datos que los contratos/anexos ya leían de la ficha pero que no existían
// como columna (Documentos::deudorDesdeFicha los pedía y siempre venían null).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('ocupacion', 30)->default('transportista')->after('giro');
            $table->string('estado_civil', 20)->default('soltero')->after('ocupacion');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['ocupacion', 'estado_civil']);
        });
    }
};
