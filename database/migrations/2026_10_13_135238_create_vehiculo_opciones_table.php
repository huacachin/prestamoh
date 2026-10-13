<?php

use App\Support\VehiculoCatalogos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 13/10/2026 (Antony): en los formularios de vehículo, Categoría, Carrocería,
 * Color y Combustible dejan de ser listas fijas. Lo que se escriba nuevo
 * ("Negro mate") se guarda aquí y sale la próxima vez en todos los altas.
 * Arranca con las listas de siempre más lo que ya había en los vehículos
 * registrados (variantes de la API de placa y del histórico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_opciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);   // categoria | carroceria | color | combustible
            $table->string('valor', 60);
            $table->timestamps();
            $table->unique(['tipo', 'valor']);
        });

        $ahora = now();
        foreach (VehiculoCatalogos::TIPOS as $tipo) {
            $valores = collect(VehiculoCatalogos::BASE[$tipo])
                ->concat(DB::table('vehiculos')->whereNotNull($tipo)->where($tipo, '<>', '')->distinct()->pluck($tipo))
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->unique(fn ($v) => mb_strtoupper($v))
                ->values();
            foreach ($valores as $valor) {
                DB::table('vehiculo_opciones')->insert([
                    'tipo' => $tipo, 'valor' => mb_substr($valor, 0, 60), 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_opciones');
    }
};
