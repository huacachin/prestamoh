<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Livewire\Clients\Edit;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Documentos\Notarios;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Observaciones del Área Legal del 29/09/2026, bloque A (las que no
 * dependían de ninguna decisión): fechas fijas del día, valor del vehículo
 * y montos sin edición, campos "a elegir" en amarillo, toggle de bien
 * futuro rojo/verde, notario de catálogo con "Otro", y sexo/nacimiento/
 * nacionalidad bloqueados en editar cliente salvo SuperUsuario.
 */
class ObservacionesLegalBloqueATest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private User $user;

    private Client $client;

    private Vehiculo $v1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Obs', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'obs-legal', 'headquarter_id' => $this->sede->id]);
        $this->actingAs($this->user);

        $this->client = Client::create([
            'expediente' => '9810', 'nombre' => 'RICARDO', 'apellido_pat' => 'MANRIQUE', 'apellido_mat' => 'MELENDEZ',
            'tipo_documento' => 'DNI', 'documento' => '47875563', 'sexo' => 'M', 'fecha_nacimiento' => '1993-08-14',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'transportista', 'estado_civil' => 'soltero',
            'direccion' => 'UCV 72 LOTE 40', 'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-09-25',
            'importe' => 12000, 'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);
        foreach ([1, 2] as $i) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse('2026-10-02')->addWeeks($i - 1),
                'importe_cuota' => 6000, 'importe_interes' => 600, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }
        $this->v1 = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'F7A197', 'marca' => 'SUBARU', 'modelo' => 'XV', 'valor' => 18000]);
    }

    private function wizard(): Testable
    {
        return Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalContrato');
    }

    // ── Obs. 1.2 / 1.3 · Anexo 1 ────────────────────────────────────────

    public function test_anexo_1_fecha_del_dia_fija_y_valor_del_vehiculo_sin_edicion(): void
    {
        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalAnexo1');

        $this->assertSame(now()->format('Y-m-d'), $c->get('fechaDoc'));
        $c->assertSeeHtml('value="'.now()->format('d/m/Y').'"')
            ->assertDontSeeHtml('wire:model.live="fechaDoc"')
            ->assertDontSeeHtml('wire:model.live="anexoValores.'.$this->v1->id.'"')
            ->assertSeeHtml('value="18,000.00"')
            ->assertSee('se corrige en la pestaña Vehículos');

        // Sin valor en la ficha: aviso, no un campo para teclearlo.
        $this->v1->update(['valor' => null]);
        Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalAnexo1')
            ->assertSee('Sin valor: cárgalo en la pestaña Vehículos');
    }

    // ── Obs. 2.1 / 2.2 / 2.5 / 3.1 / 4.1 · amarillo y toggle ────────────

    /** El elemento ligado a $wire lleva la clase campo-elegir (amarillo). */
    private function assertCampoElegir(Testable $c, string $wire): void
    {
        $patron = '/class="[^"]*\bcampo-elegir\b[^"]*"[^>]*wire:model(?:\.[a-z]+)?="'.preg_quote($wire, '/').'"/';
        $this->assertMatchesRegularExpression($patron, $c->html(), "'{$wire}' debería ir en amarillo");
    }

    public function test_los_campos_que_se_eligen_van_en_amarillo_y_el_toggle_cambia_de_color(): void
    {
        $c = $this->wizard();

        foreach (['garantiaContrato', 'destinoContrato', 'contratoCreditoId', 'contratoVehiculos.0.vehiculo_id', 'bancoDesembolso'] as $wire) {
            $this->assertCampoElegir($c, $wire);
        }
        $c->assertSeeHtml('switch-futuro')
            ->assertSeeHtml('color: #dc3545;">Bien futuro');   // apagado: rojo

        $c->set('contratoVehiculos.0.es_futuro', true)
            ->assertSeeHtml('color: #198754;">Bien futuro');    // encendido: verde
        foreach (['contratoVehiculos.0.fecha_acta', 'contratoVehiculos.0.kardex', 'contratoVehiculos.0.notario'] as $wire) {
            $this->assertCampoElegir($c, $wire);                 // obs. 3.1
        }

        // Obs. 4.1: DNI y cuenta del tercero.
        $c->set('contratoVehiculos.0.es_futuro', false)->set('destinoContrato', 'tercero');
        $this->assertCampoElegir($c, 'tercero.dni');
        $this->assertCampoElegir($c, 'tercero.cuenta');
    }

    // ── Obs. 2.4 · Montos y condiciones sin edición ─────────────────────

    public function test_montos_cuota_y_fecha_del_contrato_no_se_editan(): void
    {
        $c = $this->wizard();

        $this->assertSame(now()->format('Y-m-d'), $c->get('fechaContrato'));
        $this->assertSame('18000.00', $c->get('valorBien'));
        $c->assertSeeHtml('wire:model.live="valorBien" placeholder="0.00" readonly')
            ->assertSeeHtml('wire:model.live="montoMaximo" placeholder="0.00" readonly')
            ->assertSeeHtml('wire:model.live="cuotaContrato" readonly')
            ->assertDontSeeHtml('$toggle(\'editarCuota\')')
            ->assertDontSeeHtml('wire:model.live="fechaContrato"')
            ->assertSee('Siempre la fecha del día');
    }

    // ── Obs. 3.4 · Notario de catálogo + Otro ───────────────────────────

    public function test_el_notario_sale_del_catalogo_en_orden_o_de_otro_con_texto(): void
    {
        $this->assertSame([
            'PAÚL JHON HINOJOSA CARRILLO',
            'ROQUE ALBERTO DÍAZ DELGADO',
            'MARIO CÉSAR ROMERO VALDIVIESO',
            'MÓNICA CECILIA SALVATIERRA SALDAÑA',
            'LUCIO ALFREDO ZAMBRANO RODRÍGUEZ',
        ], Notarios::LISTA, 'el orden del área, sin la repetida');

        $this->assertSame('ROQUE ALBERTO DÍAZ DELGADO', Notarios::deSlot(['notario' => 'ROQUE ALBERTO DÍAZ DELGADO', 'notario_otro' => 'IGNORADO']));
        $this->assertSame('JULIO BLAS', Notarios::deSlot(['notario' => Notarios::OTRO, 'notario_otro' => ' JULIO BLAS ']));
        $this->assertNull(Notarios::deSlot(['notario' => Notarios::OTRO, 'notario_otro' => '']));
        $this->assertNull(Notarios::deSlot(['notario' => '']));

        $c = $this->wizard()->set('contratoVehiculos.0.es_futuro', true);
        $c->assertSeeHtml('wire:model.live="contratoVehiculos.0.notario"')
            ->assertSee('Otro notario…')
            ->assertDontSeeHtml('wire:model.blur="contratoVehiculos.0.notario_otro"');

        $c->set('contratoVehiculos.0.notario', Notarios::OTRO)
            ->assertSeeHtml('wire:model.blur="contratoVehiculos.0.notario_otro"');

        $this->assertArrayHasKey('notario_otro', $c->get('contratoVehiculos')[0]);
    }

    // ── Obs. 6.1 · Editar cliente ────────────────────────────────────────

    public function test_sexo_nacimiento_y_nacionalidad_se_bloquean_salvo_superusuario(): void
    {
        // Sin el permiso de identidad: la vista los bloquea y el servidor ignora lo que llegue.
        Livewire::test(Edit::class, ['id' => $this->client->id])
            ->assertSeeHtml('wire:model.defer="sexo"')
            ->assertSeeHtml('disabled')
            ->assertSee('sexo, nacimiento y nacionalidad solo pueden ser editados por SuperUsuario')
            ->assertDontSeeHtml('name="fecha_nacimiento"')
            ->set('sexo', 'F')
            ->set('fecha_nacimiento', '1990-01-01')
            ->set('nacionalidad', 'VENEZOLANO')
            ->set('ocupacion', 'independiente')
            ->call('update')
            ->assertHasNoErrors();

        $c = $this->client->fresh();
        $this->assertSame('M', $c->sexo);
        $this->assertSame('1993-08-14', $c->fecha_nacimiento?->format('Y-m-d'));
        $this->assertSame('PERUANO', $c->nacionalidad);
        $this->assertSame('independiente', $c->ocupacion, 'lo demás sí se guarda');

        // Con el permiso (SuperUsuario): sí se editan.
        $this->user->givePermissionTo(Permission::findOrCreate('clientes.editar-identidad', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(Edit::class, ['id' => $this->client->id])
            ->assertSeeHtml('name="fecha_nacimiento"')
            ->set('sexo', 'F')
            ->set('fecha_nacimiento', '1990-01-01')
            ->set('nacionalidad', 'VENEZOLANO')
            ->call('update')
            ->assertHasNoErrors();

        $c = $this->client->fresh();
        $this->assertSame('F', $c->sexo);
        $this->assertSame('1990-01-01', $c->fecha_nacimiento?->format('Y-m-d'));
        $this->assertSame('VENEZOLANO', $c->nacionalidad);
    }
}
