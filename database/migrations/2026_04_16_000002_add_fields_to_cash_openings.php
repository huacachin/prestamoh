<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_openings', function (Blueprint $table) {
            $table->string('hora', 10)->nullable()->after('fecha');
            $table->string('moneda', 20)->default('Soles')->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('cash_openings', function (Blueprint $table) {
            $table->dropColumn(['hora', 'moneda']);
        });
    }
};
