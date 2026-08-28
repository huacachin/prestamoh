<?php

namespace Tests\Feature\Legal;

use App\Models\TramiteNotarial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seguimiento notarial (tramites_notariales): el flujo avanza con avanzarA()
 * — que registra estado, estado_desde y el hito de fecha del estado destino —
 * y las transiciones válidas las define TRANSICIONES (puedeTransicionarA).
 * La alerta de "varado" se deriva de estado_desde sobre los estados abiertos
 * (scopeVarados); los finales (archivado) nunca alertan. Un trámite puede
 * existir suelto, sin garantía ni cliente (hoja "Otros" del Excel histórico).
 */
class TramiteNotarialTest extends TestCase
{
    use RefreshDatabase;

    /** Trámite con los datos mínimos (todas las FK son nullable) */
    private function tramite(array $attrs = []): TramiteNotarial
    {
        return TramiteNotarial::create(array_merge([
            'tipo' => 'contrato_sigm',
            'estado' => 'firmado_oficina',
            'estado_desde' => now()->toDateString(),
        ], $attrs));
    }

    public function test_avanzar_registra_estado_estado_desde_y_el_hito_de_cada_paso(): void
    {
        $tramite = $this->tramite(['estado_desde' => now()->subDays(10)->toDateString()]);

        $tramite->avanzarA('en_notaria', '2026-08-10');
        $tramite->refresh();
        $this->assertSame('en_notaria', $tramite->estado);
        $this->assertSame('2026-08-10', $tramite->estado_desde->toDateString());
        $this->assertSame('2026-08-10', $tramite->fecha_ingreso_notaria->toDateString());
        $this->assertNull($tramite->fecha_firma);

        $tramite->avanzarA('firmado', '2026-08-18');
        $tramite->refresh();
        $this->assertSame('firmado', $tramite->estado);
        $this->assertSame('2026-08-18', $tramite->estado_desde->toDateString());
        $this->assertSame('2026-08-18', $tramite->fecha_firma->toDateString());
        // El hito anterior no se toca
        $this->assertSame('2026-08-10', $tramite->fecha_ingreso_notaria->toDateString());

        // Sin fecha explícita el hito toma hoy
        $tramite->avanzarA('recogido');
        $tramite->refresh();
        $this->assertSame('recogido', $tramite->estado);
        $this->assertSame(now()->toDateString(), $tramite->estado_desde->toDateString());
        $this->assertSame(now()->toDateString(), $tramite->fecha_recojo->toDateString());
    }

    public function test_transiciones_validas_por_estado(): void
    {
        $tramite = $this->tramite(); // firmado_oficina

        // Desde firmado_oficina SOLO en_notaria
        $this->assertTrue($tramite->puedeTransicionarA('en_notaria'));
        foreach (['firmado', 'por_recoger', 'recogido', 'archivado', 'no_firmo'] as $estado) {
            $this->assertFalse($tramite->puedeTransicionarA($estado), "firmado_oficina no debe permitir '{$estado}'");
        }

        // Desde en_notaria: firmado o no_firmo, nunca saltar a recogido
        $tramite->estado = 'en_notaria';
        $this->assertTrue($tramite->puedeTransicionarA('firmado'));
        $this->assertTrue($tramite->puedeTransicionarA('no_firmo'));
        $this->assertFalse($tramite->puedeTransicionarA('recogido'));

        // Archivado es final: no permite nada
        $tramite->estado = 'archivado';
        foreach (array_keys(TramiteNotarial::ESTADOS) as $estado) {
            $this->assertFalse($tramite->puedeTransicionarA($estado), "archivado no debe permitir '{$estado}'");
        }

        // no_firmo: reintento (en_notaria) o archivar
        $tramite->estado = 'no_firmo';
        $this->assertTrue($tramite->puedeTransicionarA('en_notaria'));
        $this->assertTrue($tramite->puedeTransicionarA('archivado'));
        $this->assertFalse($tramite->puedeTransicionarA('firmado'));
    }

    public function test_dias_en_estado_cuenta_desde_estado_desde(): void
    {
        $tramite = $this->tramite(['estado_desde' => now()->subDays(20)->toDateString()]);

        $this->assertSame(20, $tramite->diasEnEstado());
    }

    public function test_varados_incluye_abiertos_antiguos_y_excluye_frescos_y_archivados(): void
    {
        $varado = $this->tramite(['estado' => 'en_notaria', 'estado_desde' => now()->subDays(20)->toDateString()]);
        $fresco = $this->tramite(['estado' => 'en_notaria', 'estado_desde' => now()->subDays(5)->toDateString()]);
        $archivado = $this->tramite(['estado' => 'archivado', 'estado_desde' => now()->subDays(100)->toDateString()]);
        $noFirmo = $this->tramite(['estado' => 'no_firmo', 'estado_desde' => now()->subDays(40)->toDateString()]);

        $ids = TramiteNotarial::varados(15)->pluck('id');

        $this->assertTrue($ids->contains($varado->id));
        $this->assertFalse($ids->contains($fresco->id), 'con 5 días aún no está varado');
        $this->assertFalse($ids->contains($archivado->id), 'archivado nunca alerta, aunque sea antiguo');
        $this->assertTrue($ids->contains($noFirmo->id), 'no_firmo antiguo sí alerta');
    }

    public function test_tramite_suelto_sin_garantia_ni_cliente_se_crea_sin_error(): void
    {
        $tramite = TramiteNotarial::create([
            'tipo' => 'carta_notarial',
            'descripcion' => 'Carta notarial de requerimiento de pago',
            'estado_desde' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('tramites_notariales', [
            'id' => $tramite->id,
            'tipo' => 'carta_notarial',
            'garantia_id' => null,
            'contrato_id' => null,
            'client_id' => null,
        ]);
        // Default espejo de la migración, visible sin refresh()
        $this->assertSame('firmado_oficina', $tramite->estado);
    }
}
