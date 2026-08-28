<?php

namespace Tests\Feature\Legal;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\SigmAviso;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciclo de vida de la garantía mobiliaria SIGM: el estado y la vigencia se
 * derivan SIEMPRE del historial de avisos (sincronizarConAvisos, sin
 * observers) — constitución la pone vigente, renovación extiende la vigencia,
 * cancelación la cancela. El N° de formulario SIGM es único en BD, "por
 * renovar" se deriva de la fecha (no es estado) y tipoDeCliente() prefiere la
 * tabla nueva con fallback al hack histórico de clients.zona.
 */
class GarantiaSigmTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'legal-tester']);
        $sede = Headquarter::create(['name' => 'Sede Central']);
        $this->client = Client::create([
            'nombre' => 'JUAN', 'apellido_pat' => 'PEREZ',
            'documento' => '45678912', 'sexo' => 'M', 'zona' => 'SIGM.S',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->client->id,
            'fecha_prestamo' => now()->toDateString(),
            'importe' => 10000, 'cuotas' => 12, 'tipo_planilla' => 3,
            'user_id' => $this->user->id, 'headquarter_id' => $sede->id,
        ]);
        $this->vehiculo = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'ABC-123',
            'marca' => 'TOYOTA', 'modelo' => 'HILUX',
        ]);
    }

    /** Garantía en constitución con los datos mínimos del contrato */
    private function garantia(): Garantia
    {
        return Garantia::create([
            'credit_id' => $this->credit->id,
            'client_id' => $this->client->id,
            'tipo' => 'mobiliaria_vehicular',
            'tipo_persona' => 'natural',
            'monto_gravamen' => 10000,
            'registrado_por' => $this->user->id,
        ]);
    }

    public function test_crear_garantia_y_adjuntar_vehiculo_como_bien_futuro(): void
    {
        $garantia = $this->garantia();
        $this->assertSame('en_constitucion', $garantia->estado);

        $garantia->vehiculos()->attach($this->vehiculo->id, [
            'es_bien_futuro' => true, 'kardex' => '0373-2026',
            'notario' => 'NOTARÍA DEMO', 'orden' => 1,
        ]);

        $this->assertDatabaseHas('garantia_vehiculo', [
            'garantia_id' => $garantia->id,
            'vehiculo_id' => $this->vehiculo->id,
            'es_bien_futuro' => 1,
            'kardex' => '0373-2026',
        ]);
        $this->assertTrue((bool) $garantia->vehiculos()->first()->pivot->es_bien_futuro);
    }

    public function test_aviso_de_constitucion_pone_la_garantia_vigente(): void
    {
        $garantia = $this->garantia();

        SigmAviso::create([
            'garantia_id' => $garantia->id, 'tipo' => 'constitucion',
            'nro_formulario' => '2026-380805',
            'fecha_presentacion' => '2026-08-01', 'vigencia_hasta' => '2031-08-01',
            'registrado_por' => $this->user->id,
        ]);
        $garantia->sincronizarConAvisos();
        $garantia->refresh();

        $this->assertSame('vigente', $garantia->estado);
        $this->assertSame('2031-08-01', $garantia->vigencia_hasta->toDateString());
        $this->assertSame('2026-08-01', $garantia->fecha_constitucion->toDateString());
    }

    public function test_una_renovacion_posterior_extiende_la_vigencia(): void
    {
        $garantia = $this->garantia();
        SigmAviso::create([
            'garantia_id' => $garantia->id, 'tipo' => 'constitucion',
            'nro_formulario' => '2026-380805',
            'fecha_presentacion' => '2026-08-01', 'vigencia_hasta' => '2031-08-01',
        ]);
        $garantia->sincronizarConAvisos();

        SigmAviso::create([
            'garantia_id' => $garantia->id, 'tipo' => 'renovacion',
            'nro_formulario' => '2031-112233',
            'fecha_presentacion' => '2031-07-15', 'vigencia_hasta' => '2036-07-15',
        ]);
        $garantia->sincronizarConAvisos();
        $garantia->refresh();

        $this->assertSame('vigente', $garantia->estado);
        $this->assertSame('2036-07-15', $garantia->vigencia_hasta->toDateString());
        // La fecha de constitución NO cambia con la renovación
        $this->assertSame('2026-08-01', $garantia->fecha_constitucion->toDateString());
    }

    public function test_un_aviso_de_cancelacion_cancela_la_garantia(): void
    {
        $garantia = $this->garantia();
        SigmAviso::create([
            'garantia_id' => $garantia->id, 'tipo' => 'constitucion',
            'nro_formulario' => '2026-380805',
            'fecha_presentacion' => '2026-08-01', 'vigencia_hasta' => '2031-08-01',
        ]);
        $garantia->sincronizarConAvisos();

        SigmAviso::create([
            'garantia_id' => $garantia->id, 'tipo' => 'cancelacion',
            'nro_formulario' => '2027-445566',
            'fecha_presentacion' => '2027-02-10',
        ]);
        $garantia->sincronizarConAvisos();

        $this->assertSame('cancelada', $garantia->fresh()->estado);
    }

    public function test_nro_formulario_duplicado_lo_rechaza_la_bd(): void
    {
        $garantia = $this->garantia();
        SigmAviso::create([
            'garantia_id' => $garantia->id, 'tipo' => 'constitucion',
            'nro_formulario' => '2026-380805',
            'fecha_presentacion' => '2026-08-01', 'vigencia_hasta' => '2031-08-01',
        ]);

        $this->expectException(QueryException::class);
        SigmAviso::create([
            'garantia_id' => $garantia->id, 'tipo' => 'renovacion',
            'nro_formulario' => '2026-380805', // unique en sigm_avisos
            'fecha_presentacion' => '2026-09-01',
        ]);
    }

    public function test_por_renovar_incluye_la_que_vence_manana_y_excluye_la_lejana(): void
    {
        $venceManana = $this->garantia();
        $venceManana->update(['estado' => 'vigente', 'vigencia_hasta' => now()->addDay()->toDateString()]);

        $venceEnTreinta = $this->garantia();
        $venceEnTreinta->update(['estado' => 'vigente', 'vigencia_hasta' => now()->addDays(30)->toDateString()]);

        $ids = Garantia::porRenovar(7)->pluck('id');

        $this->assertTrue($ids->contains($venceManana->id));
        $this->assertFalse($ids->contains($venceEnTreinta->id));
    }

    public function test_tipo_de_cliente_prefiere_la_garantia_y_cae_a_zona(): void
    {
        // Con garantía mobiliaria vigente manda la tabla nueva
        $garantia = $this->garantia();
        $garantia->update(['estado' => 'vigente']);
        $this->assertSame('vehicular', Garantia::tipoDeCliente($this->client));

        // Sin garantías registradas cae al hack histórico sobre clients.zona
        $sinGarantias = Client::create([
            'nombre' => 'MARIA', 'apellido_pat' => 'QUISPE',
            'documento' => '87654321', 'sexo' => 'F', 'zona' => 'Gar. Hip.S',
        ]);
        $this->assertSame('hipotecaria', Garantia::tipoDeCliente($sinGarantias));
    }
}
