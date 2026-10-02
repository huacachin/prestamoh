<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 02/10/2026: una foto del reporte de GPS puede venir de la pestaña Adjuntos
 * del cliente (se COPIA al reporte; esta columna solo recuerda de dónde vino,
 * para no anexarla dos veces y rotularla en el visor). Sin FK: si el adjunto
 * se borra, la copia del reporte sigue viva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculo_gps_reporte_fotos', function (Blueprint $table) {
            $table->unsignedBigInteger('client_attachment_id')->nullable()->after('reporte_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('vehiculo_gps_reporte_fotos', function (Blueprint $table) {
            $table->dropIndex(['client_attachment_id']);
            $table->dropColumn('client_attachment_id');
        });
    }
};
