<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 26/09: la estructura de la tabla de auditoría de newtaxivan, como columnas
 * propias sobre la tabla de spatie (que sigue mandando para el paquete):
 *   user_name / user_role  copia del usuario en el momento (sobrevive a cambios y borrados)
 *   module                 etiqueta del módulo afectado (Cliente, Crédito…)
 *   old_data / new_data    fila o campos antes y después (JSON)
 *   changed_fields         columnas que cambiaron en una edición (JSON)
 *   ip_address / user_agent
 * Equivalencias con newtaxivan que ya existían: user_id = causer_id,
 * action = event, record_id = subject_id. Se rellenan los registros existentes
 * a partir de properties.contexto y attribute_changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('user_name', 150)->nullable()->after('causer_id');
            $table->string('user_role', 50)->nullable()->after('user_name');
            $table->string('module', 100)->nullable()->index()->after('user_role');
            $table->json('old_data')->nullable()->after('attribute_changes');
            $table->json('new_data')->nullable()->after('old_data');
            $table->json('changed_fields')->nullable()->after('new_data');
            $table->string('ip_address', 45)->nullable()->after('properties');
            $table->text('user_agent')->nullable()->after('ip_address');
        });

        $modulos = config('auditoria.modulos', []);
        $usuarios = DB::table('users')->pluck('name', 'id');

        DB::table('activity_log')->orderBy('id')->chunk(500, function ($filas) use ($modulos, $usuarios) {
            foreach ($filas as $f) {
                $props = json_decode($f->properties ?? '[]', true) ?: [];
                $ctx = $props['contexto'] ?? [];
                $cambios = json_decode($f->attribute_changes ?? '[]', true) ?: [];
                $nuevo = $cambios['attributes'] ?? null;
                $viejo = $cambios['old'] ?? null;

                DB::table('activity_log')->where('id', $f->id)->update([
                    'user_name' => $ctx['usuario']['nombre'] ?? ($f->causer_id ? ($usuarios[$f->causer_id] ?? null) : null),
                    'user_role' => $ctx['usuario']['rol'] ?? null,
                    'module' => $f->subject_type ? ($modulos[$f->subject_type] ?? class_basename($f->subject_type)) : null,
                    'old_data' => $viejo !== null ? json_encode($viejo, JSON_UNESCAPED_UNICODE) : null,
                    'new_data' => $nuevo !== null ? json_encode($nuevo, JSON_UNESCAPED_UNICODE) : null,
                    'changed_fields' => ($f->event === 'updated' && is_array($nuevo)) ? json_encode(array_keys($nuevo), JSON_UNESCAPED_UNICODE) : null,
                    'ip_address' => $ctx['ip'] ?? null,
                    'user_agent' => $ctx['agente'] ?? null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['module']);
            $table->dropColumn(['user_name', 'user_role', 'module', 'old_data', 'new_data', 'changed_fields', 'ip_address', 'user_agent']);
        });
    }
};
