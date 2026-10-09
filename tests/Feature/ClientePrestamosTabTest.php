<?php

namespace Tests\Feature;

use App\Livewire\Clients\Edit;
use App\Livewire\Clients\Prestamos;
use App\Livewire\Credits\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony, ficha 27): en /clients/{id}/edit hay una pestaña
 * "Préstamos" con los créditos del cliente y un botón "Nuevo préstamo" que
 * abre el formulario de /credits/create/{id} en la misma pestaña; al guardar,
 * la tabla se vuelve a pintar con el crédito nuevo.
 */
class ClientePrestamosTabTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Client $otro;

    private function mundo(array $permisos = ['clientes', 'creditos', 'pagos']): void
    {
        $sede = Headquarter::forceCreate(['id' => 1, 'name' => 'Sede Test', 'status' => 'active']); // Credits\Create graba headquarter_id = 1
        $this->user = User::factory()->create(['username' => 'asesor-pre', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo($permisos);

        $crear = fn (string $exp, string $nombre, string $doc) => Client::create([
            'expediente' => $exp, 'nombre' => $nombre, 'apellido_pat' => 'Prestamo', 'apellido_mat' => 'Test',
            'tipo_documento' => 'DNI', 'documento' => $doc, 'sexo' => 'M', 'status' => 'active',
            'headquarter_id' => $sede->id, 'asesor_id' => $this->user->id,
        ]);
        $this->client = $crear('27', 'Fernando', '41849995');
        $this->otro = $crear('28', 'Ajeno', '41849996');

        DB::table('correlativos')->updateOrInsert(['tipo' => 'Credito'], ['correl' => 29500, 'updated_at' => now(), 'created_at' => now()]);
    }

    private function credito(Client $c, int $id, string $situacion, float $importe = 1000, string $fecha = '2026-09-01'): Credit
    {
        $credit = Credit::forceCreate([
            'id' => $id, 'client_id' => $c->id, 'fecha_prestamo' => $fecha, 'fecha_actualizacion' => $fecha,
            'fecha_vencimiento' => '2026-09-29', 'importe' => $importe, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => $importe * 0.10, 'situacion' => $situacion, 'estado' => $situacion === 'Activo' ? 1 : 0,
            'moneda' => 'Soles', 'asesor' => 'Asesor-pre', 'user_id' => $this->user->id, 'usuario' => 'Asesor-pre', 'headquarter_id' => 1,
        ]);
        $pagada = $situacion === 'Cancelado';
        foreach (range(1, 4) as $n) {
            CreditInstallment::forceCreate([
                'credit_id' => $id, 'num_cuota' => $n, 'fecha_vencimiento' => '2026-09-0'.$n, 'importe_cuota' => $importe / 4,
                'importe_interes' => $importe * 0.10 / 4, 'importe_aplicado' => $pagada || $n === 1 ? $importe / 4 : 0,
                'interes_aplicado' => $pagada || $n === 1 ? $importe * 0.10 / 4 : 0, 'importe_mora' => 0, 'pagado' => $pagada || $n === 1,
                'usuario' => 'asesor-pre',
            ]);
        }

        return $credit;
    }

    public function test_la_ficha_tiene_la_pestana_prestamos_con_los_creditos_del_cliente(): void
    {
        $this->mundo();
        $this->credito($this->client, 29001, 'Activo', 1000, '2026-09-01');
        $this->credito($this->client, 28001, 'Cancelado', 500, '2026-03-01');
        $this->credito($this->client, 27001, 'Eliminado', 300, '2026-01-01');
        $this->credito($this->otro, 29002, 'Activo', 800, '2026-09-02');

        $html = Livewire::test(Edit::class, ['id' => $this->client->id])->html();
        $this->assertStringContainsString("wire:click=\"\$set('tab', 'prestamos')\"", $html);
        $this->assertStringContainsString('ti-cash', $html);

        $comp = Livewire::test(Prestamos::class, ['id' => $this->client->id]);
        $html = $comp->html();
        // Cabecera con conteo, botón de alta y la tabla (sin el eliminado ni los créditos de otros).
        $this->assertStringContainsString('Préstamos <span class="text-muted small fw-normal">(2 · 1 activo)</span>', $html);
        $this->assertStringContainsString('x-on:click="abierto = true;', $html, 'el botón abre con Alpine, sin viaje al servidor');
        $this->assertStringContainsString(route('credits.show', 29001), $html);
        $this->assertStringContainsString(route('credits.show', 28001), $html);
        $this->assertStringNotContainsString(route('credits.show', 27001), $html, 'los eliminados no se listan');
        $this->assertStringNotContainsString(route('credits.show', 29002), $html, 'los créditos de otro cliente no');
        // Del más reciente al más antiguo.
        $this->assertLessThan(strpos($html, 'wire:key="prestamo-28001"'), strpos($html, 'wire:key="prestamo-29001"'));
        // Activo: 1000 + 100 de interés, una cuota pagada (275), saldo 825; Cobrar y Cronograma.
        $this->assertStringContainsString('1,100.00', $html);
        $this->assertStringContainsString('275.00', $html);
        $this->assertStringContainsString('825.00', $html);
        $this->assertStringContainsString(route('payments.create', 29001), $html);
        $this->assertStringContainsString(route('credits.schedule', 29001), $html);
        $this->assertStringNotContainsString(route('payments.create', 28001), $html, 'un cancelado no se cobra');
        $this->assertStringContainsString('badge bg-success" style="font-size:9px;">Activo', $html);
        $this->assertStringContainsString('badge bg-secondary" style="font-size:9px;">Cancelado', $html);
        // El formulario ya está montado, oculto, con fade in al abrir.
        $this->assertMatchesRegularExpression('/<div class="border rounded p-2 mb-3 alta-prestamo" x-ref="alta" style="display:none;"\s+x-show="abierto" x-transition\.opacity\.duration\.300ms>/', $html);
        $this->assertStringContainsString('wire:model.defer="codpre_"', $html);
    }

    public function test_nuevo_prestamo_abre_el_alta_en_la_misma_pestana_y_al_guardar_la_tabla_muestra_el_credito(): void
    {
        $this->mundo();
        $comp = Livewire::test(Prestamos::class, ['id' => $this->client->id]);
        $this->assertStringContainsString('aún no tiene préstamos', $comp->html());

        $html = $comp->html();
        // El formulario de /credits/create está dentro de la pestaña, oculto hasta el clic (Alpine) y sin cabecera.
        $this->assertStringContainsString('Nuevo préstamo para '.$this->client->fullName(), $html);
        $this->assertStringContainsString('wire:model.defer="codpre_"', $html, 'el formulario de /credits/create está dentro de la pestaña');
        $this->assertStringNotContainsString('NUEVO PRÉSTAMO</h4>', $html, 'sin la cabecera de la página completa');
        $this->assertStringContainsString('value="41849995" readonly', $html, 'el DNI queda fijo al cliente de la ficha');
        $this->assertStringContainsString("x-on:click=\"\$dispatch('prestamo-cancelado')\"", $html, 'Cancelar oculta sin viaje al servidor');
        $this->assertStringContainsString('x-on:prestamo-cancelado="abierto = false"', $html);
        $this->assertStringContainsString('x-on:prestamo-creado.window="abierto = false"', $html, 'al guardar se cierra solo');
        $this->assertStringContainsString('x-show="! abierto"', $html, 'el botón se esconde mientras el formulario está abierto');

        // El alta embebida guarda sin redirigir, avisa a la pestaña y queda limpia para otro crédito.
        $alta = Livewire::test(Create::class, ['clientId' => $this->client->id, 'embebido' => true])
            ->assertSet('codigoc', '41849995')
            ->assertSet('codpre_', 29501)
            ->set('seletipl', '1')->set('impopres', 1000)->set('cuot', 4)->set('inte', 10)->set('nomasesores', 'asesor-pre')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertDispatched('successAlert')
            ->assertDispatched('prestamo-creado', id: 29501)
            ->assertSet('codpre_', 29502)
            ->assertSet('impopres', null)
            ->assertSet('seletipl', '')
            ->assertSet('cuot', null)
            ->assertSet('codigoc', '41849995')
            ->assertSet('nomasesores', 'asesor-pre');
        $this->assertSame(1, Credit::where('id', 29501)->where('client_id', $this->client->id)->where('situacion', 'Activo')->count());

        // La pestaña recibe el aviso y pinta la fila nueva resaltada.
        $comp->dispatch('prestamo-creado', id: 29501)->assertSet('recienCreado', 29501);
        $html = $comp->html();
        $this->assertStringContainsString('(1 · 1 activo)', $html);
        $this->assertMatchesRegularExpression('/<tr class="table-success fila-nueva" wire:key="prestamo-29501">/', $html);
        $this->assertStringContainsString('>nuevo</span>', $html);

        // La ficha también se entera: el Estado del cliente deja de editarse.
        Livewire::test(Edit::class, ['id' => $this->client->id])->assertSet('tieneCreditosVigentes', true);
        Livewire::test(Edit::class, ['id' => $this->otro->id])->assertSet('tieneCreditosVigentes', false)
            ->dispatch('prestamo-creado', id: 29501)->assertSet('tieneCreditosVigentes', true);

        // Un segundo crédito desde el mismo formulario ya limpio: siguiente correlativo.
        $alta->set('seletipl', '3')->set('impopres', 300)->set('inte', 10)->call('save')
            ->assertHasNoErrors()->assertDispatched('prestamo-creado', id: 29502)->assertSet('codpre_', 29503);
        $this->assertSame(2, Credit::where('client_id', $this->client->id)->count());

        // La página completa /credits/create sigue redirigiendo a la ficha del crédito.
        Livewire::test(Create::class, ['clientId' => $this->client->id])
            ->set('seletipl', '1')->set('impopres', 500)->set('cuot', 4)->set('inte', 10)->set('nomasesores', 'asesor-pre')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('credits.show', 29503));
    }

    public function test_el_analista_ve_la_lista_pero_no_el_boton_de_nuevo_prestamo(): void
    {
        $this->mundo(['clientes', 'clientes.scope-propio', 'creditos']);
        $this->credito($this->client, 29001, 'Activo');

        $comp = Livewire::test(Prestamos::class, ['id' => $this->client->id]);
        $html = $comp->html();
        $this->assertStringContainsString(route('credits.show', 29001), $html);
        $this->assertStringNotContainsString('Nuevo préstamo', $html);
        $this->assertStringNotContainsString('credits.create', $html, 'el alta ni se monta: Credits\Create aborta con 403 para el analista');
        $this->assertStringNotContainsString('wire:model.defer="codpre_"', $html);
    }
}
