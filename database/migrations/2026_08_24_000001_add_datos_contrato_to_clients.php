<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos del cliente que exige el contrato de garantía mobiliaria (Área Legal):
 * estado civil, ocupación y nacionalidad. La nacionalidad ya se mostraba
 * hardcodeada como 'PERUANO' en la ficha (Clients/Create.php) — aquí pasa a ser
 * dato real con ese mismo default. Aditivo: ninguna pantalla existente cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('estado_civil', 20)->nullable()->after('sexo');
            $table->string('ocupacion', 100)->nullable()->after('estado_civil');
            $table->string('nacionalidad', 50)->nullable()->default('PERUANO')->after('ocupacion');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['estado_civil', 'ocupacion', 'nacionalidad']);
        });
    }
};
