<?php

namespace Tests\Feature\Legal;

use App\Models\Papeleta;
use App\Models\PapeletaRecurso;
use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Papeletas y sus recursos (papeletas / papeleta_recursos): el N° de papeleta
 * es clave natural POR ENTIDAD (unique compuesto entidad+nro_papeleta — el
 * mismo número puede existir en SAT y en ATU), el plazo legal por tipo lo
 * resuelve plazoDias() (10 días el acceso a la información, 30 el resto y
 * cualquier tipo desconocido) y la campana levanta con scopePorVencer los
 * recursos PENDIENTES con plazo_vence dentro de la ventana o ya vencido;
 * los resueltos y los sin plazo nunca alertan.
 */
class PapeletaRecursoTest extends TestCase
{
    use RefreshDatabase;

    /** Vehículo mínimo (la placa es unique) */
    private function vehiculo(string $placa = 'TST-001'): Vehiculo
    {
        return Vehiculo::create(['placa' => $placa]);
    }

    /** Papeleta con los datos mínimos sobre un vehículo dado */
    private function papeleta(Vehiculo $vehiculo, array $attrs = []): Papeleta
    {
        return Papeleta::create(array_merge([
            'vehiculo_id' => $vehiculo->id,
            'entidad' => 'SAT',
            'nro_papeleta' => 'M000001',
        ], $attrs));
    }

    /** Recurso (pendiente por default de la migración) de una papeleta */
    private function recurso(Papeleta $papeleta, array $attrs = []): PapeletaRecurso
    {
        return $papeleta->recursos()->create(array_merge([
            'tipo' => 'descargo',
            'fecha_presentacion' => now()->toDateString(),
        ], $attrs));
    }

    public function test_unique_compuesto_entidad_nro_papeleta(): void
    {
        $vehiculo = $this->vehiculo();
        $this->papeleta($vehiculo, ['entidad' => 'SAT', 'nro_papeleta' => 'M123456']);

        // El MISMO número en OTRA entidad sí se crea: la clave es compuesta
        $otra = $this->papeleta($vehiculo, ['entidad' => 'ATU', 'nro_papeleta' => 'M123456']);
        $this->assertDatabaseHas('papeletas', [
            'id' => $otra->id, 'entidad' => 'ATU', 'nro_papeleta' => 'M123456',
        ]);

        // Repetir entidad+número revienta contra el unique de BD
        $this->expectException(QueryException::class);
        $this->papeleta($vehiculo, ['entidad' => 'SAT', 'nro_papeleta' => 'M123456']);
    }

    public function test_plazo_dias_por_tipo(): void
    {
        $this->assertSame(10, PapeletaRecurso::plazoDias('acceso_informacion'));
        $this->assertSame(30, PapeletaRecurso::plazoDias('descargo'));
        // Tipo no catalogado cae al default de 30
        $this->assertSame(30, PapeletaRecurso::plazoDias('desconocido'));
    }

    public function test_por_vencer_incluye_vencidos_y_proximos_y_excluye_lejanos_resueltos_y_sin_plazo(): void
    {
        $papeleta = $this->papeleta($this->vehiculo());

        $vencido = $this->recurso($papeleta, ['plazo_vence' => now()->subDay()->toDateString()]);
        $proximo = $this->recurso($papeleta, ['plazo_vence' => now()->addDays(3)->toDateString()]);
        $lejano = $this->recurso($papeleta, ['plazo_vence' => now()->addDays(10)->toDateString()]);
        $resuelto = $this->recurso($papeleta, [
            'plazo_vence' => now()->subDays(5)->toDateString(),
            'resultado' => 'fundado',
            'resuelto_at' => now()->toDateString(),
        ]);
        $sinPlazo = $this->recurso($papeleta, ['plazo_vence' => null]);

        $ids = PapeletaRecurso::porVencer(3)->pluck('id');

        $this->assertTrue($ids->contains($vencido->id), 'el vencido ayer alerta');
        $this->assertTrue($ids->contains($proximo->id), 'el que vence en 3 días alerta');
        $this->assertFalse($ids->contains($lejano->id), 'a 10 días aún no alerta');
        $this->assertFalse($ids->contains($resuelto->id), 'resuelto no alerta aunque esté vencido');
        $this->assertFalse($ids->contains($sinPlazo->id), 'sin plazo_vence no alerta');
    }

    public function test_relaciones_recursos_ordenados_desc_y_camino_hasta_la_placa(): void
    {
        $papeleta = $this->papeleta($this->vehiculo('XYZ-789'));

        $antiguo = $this->recurso($papeleta, ['fecha_presentacion' => now()->subDays(10)->toDateString()]);
        $reciente = $this->recurso($papeleta, ['fecha_presentacion' => now()->subDays(2)->toDateString()]);

        // recursos() ordena por fecha_presentacion desc: el más reciente primero
        $this->assertSame(
            [$reciente->id, $antiguo->id],
            $papeleta->recursos()->pluck('id')->all()
        );

        // recurso → papeleta → vehículo llega hasta la placa
        $this->assertSame('XYZ-789', $antiguo->papeleta->vehiculo->placa);
    }
}
