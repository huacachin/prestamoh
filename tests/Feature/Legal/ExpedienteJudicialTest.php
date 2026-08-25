<?php

namespace Tests\Feature\Legal;

use App\Models\ExpedienteJudicial;
use App\Models\PlazoJudicial;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expedientes judiciales (expedientes_judiciales): el número sigue el formato
 * estándar del PJ (formatoValido) y el cuaderno cautelar deriva su número del
 * principal cambiando el dígito de cuaderno (nroCautelarDesde); el cautelar
 * cuelga del principal vía expediente_padre_id y cada cuaderno tiene su
 * propio catálogo de estados (estadosDisponibles). Las actuaciones se leen
 * como timeline (desc por fecha), los plazos por vencimiento asc, y
 * scopePorVencer alimenta la campana solo con pendientes dentro de la
 * ventana de DIAS_AVISO (los cumplidos nunca alertan).
 */
class ExpedienteJudicialTest extends TestCase
{
    use RefreshDatabase;

    /** Expediente con los datos mínimos (todas las FK son nullable) */
    private function expediente(array $attrs = []): ExpedienteJudicial
    {
        return ExpedienteJudicial::create(array_merge([
            'nro_expediente' => '04388-2024-0-3209-JP-CI-01',
        ], $attrs));
    }

    public function test_formato_valido_acepta_numeros_estandar_del_pj(): void
    {
        $this->assertTrue(ExpedienteJudicial::formatoValido('04388-2024-0-3209-JP-CI-01'));
        $this->assertTrue(ExpedienteJudicial::formatoValido('04395-2018-2-3202-JP-CI-02'));
    }

    public function test_formato_valido_rechaza_los_errores_de_tipeo_del_excel(): void
    {
        $this->assertFalse(ExpedienteJudicial::formatoValido('05138-2024-1-3209--JP-CI-01'), 'doble guion');
        $this->assertFalse(ExpedienteJudicial::formatoValido('000577-2025-0-3209-JP-CI-01'), '6 dígitos iniciales');
        $this->assertFalse(ExpedienteJudicial::formatoValido('04388-2024-0-3209-JP-CI-1'), 'sufijo de 1 dígito');
        $this->assertFalse(ExpedienteJudicial::formatoValido('04388 -2024-0-3209-JP-CI-01'), 'espacios internos');
    }

    public function test_nro_cautelar_se_deriva_cambiando_el_digito_de_cuaderno(): void
    {
        $this->assertSame(
            '04388-2024-1-3209-JP-CI-01',
            ExpedienteJudicial::nroCautelarDesde('04388-2024-0-3209-JP-CI-01')
        );
        $this->assertSame(
            '04388-2024-2-3209-JP-CI-01',
            ExpedienteJudicial::nroCautelarDesde('04388-2024-0-3209-JP-CI-01', 2)
        );
    }

    public function test_nro_expediente_duplicado_viola_la_restriccion_unique(): void
    {
        $this->expediente();

        $this->expectException(QueryException::class);
        $this->expediente();
    }

    public function test_cautelar_cuelga_del_principal_y_cada_cuaderno_tiene_su_catalogo(): void
    {
        $principal = $this->expediente();
        $cautelar = $this->expediente([
            'nro_expediente' => ExpedienteJudicial::nroCautelarDesde($principal->nro_expediente),
            'cuaderno' => 'cautelar',
            'expediente_padre_id' => $principal->id,
            'estado' => 'solicitada',
        ]);

        $this->assertTrue($principal->cautelares->contains($cautelar));
        $this->assertTrue($cautelar->principal->is($principal));

        $this->assertSame(ExpedienteJudicial::ESTADOS_PRINCIPAL, $principal->estadosDisponibles());
        $this->assertSame(ExpedienteJudicial::ESTADOS_CAUTELAR, $cautelar->estadosDisponibles());
    }

    public function test_actuaciones_se_ordenan_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $exp = $this->expediente();
        $vieja = $exp->actuaciones()->create([
            'tipo' => 'resolucion', 'numero' => 'UNO',
            'fecha' => '2024-03-10', 'sumilla' => 'Admite la demanda',
        ]);
        $reciente = $exp->actuaciones()->create([
            'tipo' => 'notificacion',
            'fecha' => '2025-06-01', 'sumilla' => 'Notifica al demandado',
        ]);
        $media = $exp->actuaciones()->create([
            'tipo' => 'escrito_demandante',
            'fecha' => '2024-09-15', 'sumilla' => 'Solicita medida cautelar',
        ]);

        $this->assertSame(
            [$reciente->id, $media->id, $vieja->id],
            $exp->fresh()->actuaciones->pluck('id')->all()
        );
    }

    public function test_plazos_se_ordenan_por_vencimiento_ascendente(): void
    {
        $exp = $this->expediente();
        $lejano = $exp->plazos()->create([
            'descripcion' => 'Informe pericial',
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
        ]);
        $proximo = $exp->plazos()->create([
            'descripcion' => 'Contestar demanda',
            'fecha_vencimiento' => now()->addDays(3)->toDateString(),
        ]);

        $this->assertSame(
            [$proximo->id, $lejano->id],
            $exp->fresh()->plazos->pluck('id')->all()
        );
    }

    public function test_por_vencer_incluye_pendientes_en_ventana_y_excluye_lejanos_y_cumplidos(): void
    {
        $exp = $this->expediente();
        $vencido = $exp->plazos()->create([
            'descripcion' => 'Apelar sentencia',
            'fecha_vencimiento' => now()->subDay()->toDateString(),
        ]);
        $enDosDias = $exp->plazos()->create([
            'descripcion' => 'Presentar escrito',
            'fecha_vencimiento' => now()->addDays(2)->toDateString(),
        ]);
        $lejano = $exp->plazos()->create([
            'descripcion' => 'Audiencia única',
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
        ]);
        $cumplido = $exp->plazos()->create([
            'descripcion' => 'Subsanar demanda',
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
            'cumplido_at' => now(),
        ]);

        $ids = PlazoJudicial::porVencer(2)->pluck('id');

        $this->assertTrue($ids->contains($vencido->id), 'vencido ayer y pendiente debe alertar');
        $this->assertTrue($ids->contains($enDosDias->id), 'vence justo en DIAS_AVISO días');
        $this->assertFalse($ids->contains($lejano->id), 'a 10 días aún no entra a la ventana');
        $this->assertFalse($ids->contains($cumplido->id), 'cumplido nunca alerta, aunque esté vencido');
    }
}
