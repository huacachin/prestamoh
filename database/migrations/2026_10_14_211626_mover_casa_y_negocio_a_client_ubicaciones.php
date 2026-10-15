<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 15/10/2026 (Antony): Casa y Negocio se desatan de clients y pasan a
 * client_ubicaciones como filas con tipo ('casa' | 'negocio' | 'otra'), junto a
 * las direcciones adicionales. Cada fila lleva el último usuario y la fecha en
 * que se registraron sus coordenadas (user_id, registrado_at), sacados de la
 * auditoría: el registro real si lo hubo, o la carga inicial de Licet del
 * 14/10/2026 16:00. Antes de quitar las seis columnas de clients se deja un
 * respaldo JSON en storage/app/private.
 */
return new class extends Migration
{
    private const BASE = '2026-10-14 16:00:00';

    private const FIJAS = [
        'casa' => ['latitud', 'longitud', 'ubicacion1_nombre', 'Casa'],
        'negocio' => ['latitud2', 'longitud2', 'ubicacion2_nombre', 'Negocio'],
    ];

    public function up(): void
    {
        Schema::table('client_ubicaciones', function (Blueprint $table) {
            $table->string('tipo', 10)->default('otra')->after('client_id'); // casa | negocio | otra
            $table->timestamp('registrado_at')->nullable()->after('user_id'); // último registro de coordenadas
            $table->index(['client_id', 'tipo']);
        });

        // Las adicionales que ya existían: tipo 'otra' y su último registro de coordenadas.
        foreach (DB::table('client_ubicaciones')->get(['id', 'client_id', 'user_id', 'created_at']) as $u) {
            $ultimo = $this->ultimoRegistro((int) $u->client_id, "u:{$u->id}");
            DB::table('client_ubicaciones')->where('id', $u->id)->update([
                'tipo' => 'otra',
                'registrado_at' => $ultimo['fecha'] ?? $u->created_at,
                'user_id' => $ultimo['user_id'] ?? $u->user_id,
            ]);
        }

        $clientes = DB::table('clients')->where(function ($q) {
            $q->whereNotNull('latitud')->orWhereNotNull('latitud2')->orWhereNotNull('ubicacion1_nombre')->orWhereNotNull('ubicacion2_nombre');
        })->orderBy('id')->get(['id', 'latitud', 'longitud', 'latitud2', 'longitud2', 'ubicacion1_nombre', 'ubicacion2_nombre']);

        if ($clientes->isNotEmpty()) {
            Storage::disk('local')->put(
                'respaldo-coordenadas-clientes-'.now()->format('Y-m-d_His').'.json',
                $clientes->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        $licet = User::where('username', 'Licet')->value('id');
        $ahora = now();
        foreach ($clientes as $c) {
            foreach (self::FIJAS as $tipo => [$cLat, $cLng, $cNombre, $porDefecto]) {
                $tiene = $c->{$cLat} !== null && $c->{$cLng} !== null;
                $nombre = trim((string) $c->{$cNombre});
                if (! $tiene && $nombre === '') {
                    continue;
                }
                $nombre = $nombre !== '' ? $nombre : $porDefecto;
                // client_ubicaciones es único por (client_id, nombre): si una adicional ya se llama así, se distingue.
                if (DB::table('client_ubicaciones')->where('client_id', $c->id)->where('nombre', $nombre)->exists()) {
                    $nombre = "{$nombre} ({$porDefecto})";
                }
                $ultimo = $tiene ? $this->ultimoRegistro((int) $c->id, $tipo) : null;
                $fecha = $ultimo['fecha'] ?? self::BASE;
                DB::table('client_ubicaciones')->insert([
                    'client_id' => $c->id,
                    'tipo' => $tipo,
                    'nombre' => $nombre,
                    'latitud' => $tiene ? $c->{$cLat} : null,
                    'longitud' => $tiene ? $c->{$cLng} : null,
                    'user_id' => $tiene ? ($ultimo['user_id'] ?? $licet) : null,
                    'registrado_at' => $tiene ? $fecha : null,
                    'created_at' => $tiene ? $fecha : $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud', 'latitud2', 'longitud2', 'ubicacion1_nombre', 'ubicacion2_nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('latitud2', 10, 7)->nullable();
            $table->decimal('longitud2', 10, 7)->nullable();
            $table->string('ubicacion1_nombre', 60)->nullable();
            $table->string('ubicacion2_nombre', 60)->nullable();
        });
        foreach (DB::table('client_ubicaciones')->whereIn('tipo', ['casa', 'negocio'])->get() as $u) {
            [$cLat, $cLng, $cNombre, $porDefecto] = self::FIJAS[$u->tipo];
            DB::table('clients')->where('id', $u->client_id)->update([
                $cLat => $u->latitud, $cLng => $u->longitud, $cNombre => $u->nombre === $porDefecto ? null : $u->nombre,
            ]);
        }
        DB::table('client_ubicaciones')->whereIn('tipo', ['casa', 'negocio'])->delete();
        Schema::table('client_ubicaciones', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'tipo']);
            $table->dropColumn(['tipo', 'registrado_at']);
        });
    }

    /**
     * Último "Registró" o "Actualizó" de coordenadas de esa dirección en la auditoría.
     *
     * @return array{fecha: string, user_id: int|null}|null
     */
    private function ultimoRegistro(int $clientId, string $clave): ?array
    {
        $fila = DB::table('activity_log')
            ->where('log_name', 'auditoria')
            ->where('subject_type', 'App\\Models\\Client')
            ->where('subject_id', $clientId)
            ->where('properties->gps->tipo', $clave)
            ->where(function ($q) {
                $q->where('description', 'like', 'Registró la ubicación GPS%')->orWhere('description', 'like', 'Actualizó la ubicación GPS%');
            })
            ->orderByDesc('id')
            ->first(['created_at', 'causer_id']);

        return $fila ? ['fecha' => $fila->created_at, 'user_id' => $fila->causer_id !== null ? (int) $fila->causer_id : null] : null;
    }
};
