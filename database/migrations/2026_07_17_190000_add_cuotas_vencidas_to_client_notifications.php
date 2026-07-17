<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuotas vencidas del cliente en el momento del envío de la notificación.
 * Permite precargar el último mensaje del MISMO nivel de morosidad
 * (2 vencidas vs 3+) al redactar una nueva notificación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_notifications', function (Blueprint $table) {
            $table->unsignedSmallInteger('cuotas_vencidas')->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('client_notifications', function (Blueprint $table) {
            $table->dropColumn('cuotas_vencidas');
        });
    }
};
