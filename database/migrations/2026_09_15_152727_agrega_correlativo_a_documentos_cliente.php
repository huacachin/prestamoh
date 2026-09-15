<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correlativo del área legal ("2026-230") como COLUMNA del documento (15/09).
 *
 * Vivía dentro del snapshot JSON, pero es el número que el área cita en sus
 * registros y ante la notaría: tiene que poder consultarse y, sobre todo,
 * saberse cuáles están EN USO para no repetirlos ni saltarlos cuando se anula
 * un anexo. Buscar eso dentro de un JSON es frágil y lento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->string('correlativo', 20)->nullable()->after('version');
            $table->index(['tipo', 'estado', 'correlativo'], 'docs_correlativo_idx');
        });

        // Rescata el número de los anexos ya emitidos que lo llevan en el
        // snapshot (los anteriores al 15/09 no lo tienen y quedan en null).
        DB::table('documentos_cliente')
            ->where('tipo', 'anexo1')
            ->update([
                'correlativo' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT(snapshot, '$.credito.correlativo'))"),
            ]);
    }

    public function down(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->dropIndex('docs_correlativo_idx');
            $table->dropColumn('correlativo');
        });
    }
};
