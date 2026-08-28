<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación de `vehiculos` para la fusión con feat/area-legal (28/08).
 *
 * Las dos ramas habían creado LA MISMA tabla con esquemas distintos
 * (feat/area-legal 2026_08_24_000002 vs feat/documentos-cliente
 * 2026_08_26_000001, la que vive en prod). La de área legal se descartó y sus
 * columnas de flota entran aquí como ALTER aditivo.
 *
 * Diferencias deliberadas frente al esquema original de área legal:
 *  - El año sigue siendo `anio_modelo` STRING (admite el caso dual
 *    '2017/2018' que traen las tarjetas de propiedad); NO se agrega el
 *    `anio` smallint. El código legal que leía `anio` se adaptó.
 *  - `client_id` pasa a NULLABLE: la flota propia de la empresa (papeletas)
 *    no tiene cliente. Las consultas por client_id no cambian — simplemente
 *    no matchean NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();

            $table->string('propietario_tipo', 20)->default('cliente')->after('client_id'); // cliente|empresa|tercero
            $table->string('propietario_nombre')->nullable()->after('propietario_tipo');    // cuando tipo = tercero
            $table->string('propietario_documento', 15)->nullable()->after('propietario_nombre');
            $table->string('partida_registral', 20)->nullable()->after('combustible');
            $table->string('estado', 20)->default('activo')->after('valor'); // activo|vendido|adjudicado|baja
            $table->text('observaciones')->nullable()->after('estado');

            $table->index('nro_serie');
            $table->index(['propietario_tipo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropIndex(['nro_serie']);
            $table->dropIndex(['propietario_tipo', 'estado']);
            $table->dropColumn([
                'propietario_tipo', 'propietario_nombre', 'propietario_documento',
                'partida_registral', 'estado', 'observaciones',
            ]);
            $table->foreignId('client_id')->nullable(false)->change();
        });
    }
};
