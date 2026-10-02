<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Observaciones del Área Legal del 29/09/2026, bloque B (resueltas con
 * criterio propio el 02/10, por encargo de Antony):
 *  1.1 el crédito del Anexo 1 se elige en una lista con color: amarillo el
 *      más reciente sin contrato (el que toca), negrita los que ya tienen
 *      contrato, rojo los anteriores sin contrato; y se preselecciona el amarillo;
 *  2.3 los deudores no se editan en el wizard: salen de la ficha y lo que
 *      falte se avisa con enlace a la ficha;
 *  3.2 kardex: se teclea solo el número y el año va fijo (del acta o el de
 *      hoy); el valor impreso sigue siendo "0373-2026" como la maestra;
 *  fecha del Anexo 2 fija del día, como el Anexo 1 y el contrato.
 */
class ObservacionesLegalBloqueBTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private Client $client;

    private Vehiculo $v1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede ObsB', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'obs-legal-b', 'headquarter_id' => $this->sede->id]));

        $this->client = Client::create([
            'expediente' => '9811', 'nombre' => 'ANA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'ROJAS',
            'tipo_documento' => 'DNI', 'documento' => '47875564', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'comerciante', 'estado_civil' => 'soltero',
            'email' => 'ana.quispe@example.com',
            'direccion' => 'AV. UNO 100', 'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);
        $this->v1 = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'OBS111', 'marca' => 'KIA', 'modelo' => 'RIO', 'valor' => 15000]);
    }

    private function credito(string $fecha): Credit
    {
        $credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => $fecha,
            'importe' => 5000, 'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);
        foreach ([1, 2] as $i) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse($fecha)->addWeeks($i),
                'importe_cuota' => 2500, 'importe_interes' => 250, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }

        return $credit;
    }

    private function contratoEmitido(Credit $credit): void
    {
        DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $credit->id, 'tipo' => 'contrato', 'version' => 1,
            'snapshot' => ['prueba' => true], 'pdf_path' => "documentos/cliente-{$this->client->id}/contrato-{$credit->id}.pdf",
            'sha256' => hash('sha256', "c{$credit->id}"), 'estado' => 'emitido', 'generado_por' => auth()->id(),
        ]);
    }

    private function wizard(): Testable
    {
        return Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalContrato');
    }

    // ── 1.1 · Lista de créditos del Anexo 1 con color ───────────────────

    public function test_el_anexo_1_lista_los_creditos_con_color_y_preselecciona_el_vigente(): void
    {
        $viejo = $this->credito('2026-06-01');     // anterior sin contrato → rojo
        $contratado = $this->credito('2026-08-01');  // con contrato → negrita
        $nuevo = $this->credito('2026-10-01');       // el más reciente sin contrato → amarillo
        $this->contratoEmitido($contratado);

        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalAnexo1');

        $this->assertSame($nuevo->id, $c->get('creditoId'), 'se preselecciona el vigente');
        $html = $c->html();
        $this->assertMatchesRegularExpression('/credito-vigente"[^>]*wire:key="anx-cred-'.$nuevo->id.'"/', $html);
        $this->assertMatchesRegularExpression('/credito-contratado"[^>]*wire:key="anx-cred-'.$contratado->id.'"/', $html);
        $this->assertMatchesRegularExpression('/credito-anterior"[^>]*wire:key="anx-cred-'.$viejo->id.'"/', $html);
        $c->assertSee('Vigente del día')->assertSee('Con contrato')->assertSee('Anterior')
            ->assertDontSeeHtml('wire:model.live="creditoId"><option');

        // Todos con contrato y más de uno: no hay "vigente" y no se preselecciona.
        $this->contratoEmitido($viejo);
        $this->contratoEmitido($nuevo);
        $c2 = Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalAnexo1');
        $this->assertNull($c2->get('creditoId'));
        // (la clase existe en el <style>; lo que no debe haber es una fila con ella)
        $this->assertDoesNotMatchRegularExpression('/credito-vigente"[^>]*wire:key/', $c2->html());
    }

    // ── 2.3 · Deudores sin edición ──────────────────────────────────────

    public function test_los_deudores_salen_de_la_ficha_sin_edicion_y_se_avisa_lo_que_falta(): void
    {
        $this->credito('2026-10-01');
        $c = $this->wizard();

        $html = $c->html();
        foreach (['nombre', 'dni', 'nacionalidad', 'ocupacion', 'correo', 'domicilio'] as $campo) {
            $this->assertMatchesRegularExpression('/wire:model\.blur="deudores\.0\.'.$campo.'" readonly/', $html, "{$campo} debe ir en solo lectura");
        }
        $this->assertMatchesRegularExpression('/wire:model\.live="deudores\.0\.sexo" disabled/', $html);
        $this->assertMatchesRegularExpression('/wire:model\.live="deudores\.0\.estado_civil" disabled/', $html);
        $c->assertSee('se corrigen en la ficha del cliente')
            ->assertDontSee('todo editable');

        // Con la ficha completa no hay aviso; sin correo ni ocupación, sí, con enlace a la ficha.
        $this->assertStringNotContainsString('Faltan en la ficha', $html);
        $this->client->update(['ocupacion' => null, 'email' => null]);
        $this->wizard()
            ->assertSee('Faltan en la ficha:')
            ->assertSee('ocupación, correo')
            ->assertSeeHtml(route('clients.edit', $this->client->id));
    }

    // ── 3.2 · Kardex: solo el número, el año fijo ───────────────────────

    public function test_el_kardex_se_arma_con_el_numero_tecleado_y_el_anio_del_acta(): void
    {
        $this->credito('2026-10-01');
        $c = $this->wizard()->set('contratoVehiculos.0.es_futuro', true);

        $c->assertSeeHtml('wire:model.blur="contratoVehiculos.0.kardex_num"')
            ->assertSee('-'.now()->format('Y'))
            ->assertDontSeeHtml('wire:model.blur="contratoVehiculos.0.kardex"');

        $c->set('contratoVehiculos.0.kardex_num', '0373');
        $this->assertSame('0373-'.now()->format('Y'), $c->get('contratoVehiculos')[0]['kardex']);

        // El año sale de la fecha del acta cuando está puesta.
        $c->set('contratoVehiculos.0.fecha_acta', '2025-05-04');
        $this->assertSame('0373-2025', $c->get('contratoVehiculos')[0]['kardex']);
        $c->assertSee('-2025');

        // Se limpia el número: se limpia el kardex. Y lo no numérico se descarta.
        $c->set('contratoVehiculos.0.kardex_num', '');
        $this->assertSame('', $c->get('contratoVehiculos')[0]['kardex']);
        $c->set('contratoVehiculos.0.kardex_num', 'N° 12a');
        $this->assertSame('12-2025', $c->get('contratoVehiculos')[0]['kardex']);
    }

    // ── Fecha del Anexo 2 fija ──────────────────────────────────────────

    public function test_la_fecha_del_anexo_2_es_siempre_la_del_dia(): void
    {
        $this->credito('2026-10-01');
        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalAnexo2');

        $this->assertSame(now()->format('Y-m-d'), $c->get('fechaAnexo2'));
        $c->assertDontSeeHtml('wire:model.live="fechaAnexo2"')
            ->assertSeeHtml('value="'.now()->format('d/m/Y').'"');
    }
}
