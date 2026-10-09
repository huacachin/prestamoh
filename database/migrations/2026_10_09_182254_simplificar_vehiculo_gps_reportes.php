<?php

use App\Support\Coordenadas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 09/10/2026 (Antony): el reporte de GPS de vehículos se simplifica a
 * coordenadas, fecha de registro y una descripción. Fuera los puntos del
 * recorrido, los horarios de ruta y estadía, el domicilio, las fotos (subidas
 * y copiadas de Adjuntos) y el texto para WhatsApp.
 *
 * Lo ya registrado no se pierde: las coordenadas salen del enlace del primer
 * punto que se pueda leer y la descripción se arma con los puntos (título,
 * dirección, horario y el enlace cuando no se pudo leer) y los horarios de
 * ruta. El domicilio no se copia: es el de la ficha del cliente. Las fotos y
 * su tabla se eliminan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculo_gps_reportes', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable()->after('fecha');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->text('descripcion')->nullable()->after('longitud');
        });

        foreach (DB::table('vehiculo_gps_reportes')->orderBy('id')->cursor() as $r) {
            [$lat, $lng, $descripcion] = $this->convertir($r);
            DB::table('vehiculo_gps_reportes')->where('id', $r->id)->update([
                'latitud' => $lat, 'longitud' => $lng, 'descripcion' => $descripcion,
            ]);
        }

        Schema::table('vehiculo_gps_reportes', function (Blueprint $table) {
            $table->dropColumn(['inicio_desde', 'inicio_hasta', 'fin_desde', 'fin_hasta', 'domicilio_direccion', 'domicilio_link', 'puntos']);
        });

        Schema::dropIfExists('vehiculo_gps_reporte_fotos');
        try {
            Storage::disk('public')->deleteDirectory('gps/reportes');
        } catch (Throwable $e) {
            Log::warning('No se pudo borrar la carpeta de fotos de los reportes GPS', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        Schema::table('vehiculo_gps_reportes', function (Blueprint $table) {
            $table->string('inicio_desde', 5)->nullable();
            $table->string('inicio_hasta', 5)->nullable();
            $table->string('fin_desde', 5)->nullable();
            $table->string('fin_hasta', 5)->nullable();
            $table->string('domicilio_direccion')->nullable();
            $table->string('domicilio_link', 500)->nullable();
            $table->json('puntos')->nullable();
        });
        Schema::table('vehiculo_gps_reportes', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud', 'descripcion']);
        });
        Schema::create('vehiculo_gps_reporte_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('vehiculo_gps_reportes')->cascadeOnDelete();
            $table->unsignedBigInteger('client_attachment_id')->nullable();
            $table->string('path', 500);
            $table->string('thumb_path', 500)->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Coordenadas y descripción de un reporte viejo a partir de sus puntos y horarios.
     *
     * @return array{0: float|null, 1: float|null, 2: string|null}
     */
    private function convertir(object $r): array
    {
        $puntos = json_decode((string) $r->puntos, true) ?: [];
        $lat = $lng = null;
        $lineas = [];

        foreach ($puntos as $p) {
            $titulo = trim((string) ($p['titulo'] ?? '')) ?: 'Ubicación de vehículo';
            $etiqueta = trim((string) ($p['etiqueta'] ?? ''));
            $estadia = implode(' – ', array_filter([$p['estadia_desde'] ?? null, $p['estadia_hasta'] ?? null]));
            $link = trim((string) ($p['link'] ?? ''));

            $leido = false;
            if ($lat === null && $link !== '') {
                $coords = $this->coordenadas($link);
                if ($coords !== null) {
                    [$lat, $lng] = $coords;
                    $leido = true;
                }
            }

            $lineas[] = $titulo.($etiqueta !== '' ? " ({$etiqueta})" : '').': '.trim((string) ($p['direccion'] ?? ''))
                .($estadia !== '' ? " · {$estadia}" : '')
                .($link !== '' && ! $leido ? " · {$link}" : '');
        }

        $inicio = implode(' – ', array_filter([$r->inicio_desde, $r->inicio_hasta]));
        $fin = implode(' – ', array_filter([$r->fin_desde, $r->fin_hasta]));
        if ($inicio !== '') {
            $lineas[] = "Inicio de ruta: {$inicio}";
        }
        if ($fin !== '') {
            $lineas[] = "Fin de ruta: {$fin}";
        }

        return [$lat, $lng, $lineas === [] ? null : implode("\n", $lineas)];
    }

    /** @return array{0: float, 1: float}|null */
    private function coordenadas(string $texto): ?array
    {
        try {
            return Coordenadas::parse($texto);
        } catch (Throwable) {
            return null;
        }
    }
};
