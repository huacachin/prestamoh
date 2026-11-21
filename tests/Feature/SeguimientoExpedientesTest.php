<?php

namespace Tests\Feature;

use App\Livewire\Clients\Seguimiento;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 20/11/2026 (Antony, ruta del expediente): la pantalla "Seguimiento de expedientes" lista a los clientes
 * que se están trabajando —con préstamo activo reciente o recién registrados sin préstamo— con el avance
 * de su ruta y el paso que les sigue; cada fila lleva a la ficha en ese paso.
 */
class SeguimientoExpedientesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-20 10:00:00');
        $sede = Headquarter::create(['name' => 'Sede Seguimiento', 'status' => 'active']);
        $this->usuario = User::factory()->create(['username' => 'seguimiento', 'headquarter_id' => $sede->id]);
        $this->seed(PermissionCatalogSeeder::class);
        $this->usuario->givePermissionTo('clientes');
        $this->actingAs($this->usuario);
    }

    /** @param  array<string, mixed>  $datos */
    private function cliente(string $apellido, array $datos = [], ?string $registradoEl = null): Client
    {
        $client = Client::create($datos + [
            'nombre' => 'Cliente', 'apellido_pat' => $apellido, 'zona' => 'SIGM.S', 'status' => 'active', 'asesor_id' => $this->usuario->id,
        ]);
        if ($registradoEl !== null) {
            DB::table('clients')->where('id', $client->id)->update(['created_at' => $registradoEl]);
        }

        return $client;
    }

    private function credito(Client $client, string $fecha, string $situacion = 'Activo'): Credit
    {
        return Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => $fecha, 'importe' => 1000, 'cuotas' => 10,
            'tipo_planilla' => 1, 'interes' => 20, 'interes_total' => 200, 'situacion' => $situacion, 'estado' => 1,
        ]);
    }

    private function adjunto(Client $client): void
    {
        DB::table('client_attachments')->insert(['client_id' => $client->id, 'filename' => 'dni.jpg', 'path' => 'clients/dni.jpg', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_lista_a_los_clientes_que_se_estan_trabajando_y_deja_fuera_al_resto_de_la_cartera(): void
    {
        $this->credito($this->cliente('Conprestamoreciente', registradoEl: '2026-09-01'), '2026-11-15');
        $this->cliente('Reciensinprestamo');
        // Fuera: préstamo antiguo, registrado hace tiempo y sin préstamo, con el préstamo ya cancelado,
        // cliente no activo y persona relacionada (copropietario).
        $this->credito($this->cliente('Prestamoantiguo', registradoEl: '2026-09-01'), '2026-01-10');
        $this->cliente('Antiguosinprestamo', registradoEl: '2026-09-01');
        $this->credito($this->cliente('Yacancelado'), '2026-11-15', 'Cancelado');
        $this->credito($this->cliente('Inactivo', ['status' => 'inactive']), '2026-11-15');
        $this->cliente('Copropietario', ['es_relacionado' => true]);

        Livewire::test(Seguimiento::class)
            ->assertSee('Conprestamoreciente')
            ->assertSee('Reciensinprestamo')
            ->assertDontSee('Prestamoantiguo')
            ->assertDontSee('Antiguosinprestamo')
            ->assertDontSee('Yacancelado')
            ->assertDontSee('Inactivo')
            ->assertDontSee('Copropietario')
            ->assertViewHas('conteo', ['incompletos' => 2, 'completos' => 0, 'todos' => 2]);
    }

    public function test_un_cliente_recien_registrado_cuyo_unico_prestamo_se_elimino_sigue_en_la_lista(): void
    {
        $this->credito($this->cliente('Prestamoeliminado'), '2026-11-15', 'Eliminado');

        Livewire::test(Seguimiento::class)->assertSee('Prestamoeliminado')->assertSee('Sin préstamo activo');
    }

    public function test_un_prestamo_que_viene_de_una_refinanciacion_no_mete_al_cliente_en_la_lista(): void
    {
        $refinanciado = $this->cliente('Solorefinanciacion', registradoEl: '2026-09-01');
        $this->credito($refinanciado, '2026-11-15')->update(['refinanciado' => true]);
        $this->credito($this->cliente('Prestamonuevo', registradoEl: '2026-09-01'), '2026-11-15');

        Livewire::test(Seguimiento::class)->assertSee('Prestamonuevo')->assertDontSee('Solorefinanciacion');

        config(['expediente.refinanciacion_pide_documentos' => true]);

        Livewire::test(Seguimiento::class)->assertSee('Solorefinanciacion');
    }

    public function test_el_periodo_elegido_decide_que_prestamos_entran(): void
    {
        $this->credito($this->cliente('Hacedosmeses', registradoEl: '2026-06-01'), '2026-09-20');

        Livewire::test(Seguimiento::class)
            ->assertDontSee('Hacedosmeses')
            ->set('dias', '90')
            ->assertSee('Hacedosmeses');
    }

    public function test_por_defecto_salen_los_incompletos_y_los_filtros_separan_completos_y_por_paso(): void
    {
        $this->credito($this->cliente('Faltavehiculo'), '2026-11-15');
        // Sin garantía mobiliaria solo se piden préstamo y adjuntos (el último paso): con el préstamo y sin
        // fotos faltan los adjuntos; con los dos, expediente completo.
        $this->credito($this->cliente('Faltaadjuntos', ['zona' => 'Sin Garantia']), '2026-11-16');
        $completo = $this->cliente('Expedientecompleto', ['zona' => 'Sin Garantia']);
        $this->adjunto($completo);
        $this->credito($completo, '2026-11-17');

        $pantalla = Livewire::test(Seguimiento::class)
            ->assertSee('Faltavehiculo')
            ->assertSee('Faltaadjuntos')
            ->assertDontSee('Expedientecompleto')
            ->assertViewHas('conteo', ['incompletos' => 2, 'completos' => 1, 'todos' => 3])
            ->assertViewHas('porPaso', ['adjuntos' => 1, 'vehiculo' => 1]);

        $pantalla->set('estado', 'completos')
            ->assertSee('Expedientecompleto')
            ->assertSee('Completo')
            ->assertDontSee('Faltavehiculo');

        $pantalla->set('estado', 'todos')
            ->assertSee('Expedientecompleto')
            ->assertSee('Faltavehiculo')
            ->assertSee('Faltaadjuntos');

        // Elegir un paso vuelve a los incompletos: los completos no esperan ninguno.
        $pantalla->set('estado', 'completos')
            ->set('paso', 'adjuntos')
            ->assertSet('estado', 'incompletos')
            ->assertSee('Faltaadjuntos')
            ->assertDontSee('Faltavehiculo')
            ->assertDontSee('Expedientecompleto');

        $pantalla->set('estado', 'completos')->assertSet('paso', '');
    }

    public function test_cada_fila_muestra_el_avance_y_lleva_a_la_ficha_en_el_paso_que_sigue(): void
    {
        $client = $this->cliente('Rutaenlazada', ['expediente' => '9911', 'documento' => '44556677']);
        Vehiculo::create(['client_id' => $client->id, 'placa' => 'SEG002']);
        $credit = $this->credito($client, '2026-11-18');

        Livewire::test(Seguimiento::class)
            ->assertSee('9911')
            ->assertSee('44556677')
            ->assertSee('#'.$credit->id)
            ->assertSee('18/11/2026')
            ->assertSee('3 de 7')
            ->assertSeeHtml('href="'.e(route('clients.edit', ['id' => $client->id, 'prestamo' => $credit->id, 'tab' => 'documentos'])).'"')
            ->assertSeeHtml('title="Ir a la ficha: emitir los documentos"');
    }

    /** Tras hablar con el cliente (20/11): el expediente es de cada préstamo — "verificar si los pasos de ese préstamo se han completado". */
    public function test_un_cliente_con_dos_prestamos_activos_sale_en_dos_filas_cada_una_con_el_avance_de_su_prestamo(): void
    {
        $client = $this->cliente('Condosprestamos', registradoEl: '2026-09-01');
        Vehiculo::create(['client_id' => $client->id, 'placa' => 'SEG010']);
        $this->adjunto($client);
        $conContrato = $this->credito($client, '2026-11-10');
        DB::table('documentos_cliente')->insert(['client_id' => $client->id, 'credit_id' => $conContrato->id, 'tipo' => 'contrato', 'estado' => 'emitido', 'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $sinDocumentos = $this->credito($client, '2026-11-18');

        $filas = Livewire::test(Seguimiento::class)
            ->assertViewHas('conteo', ['incompletos' => 2, 'completos' => 0, 'todos' => 2])
            ->viewData('filas');

        $this->assertSame([$sinDocumentos->id, $conContrato->id], $filas->map(fn (array $f) => $f['credito']->id)->all(), 'una fila por préstamo, el más reciente primero');
        $detalle = fn (array $fila) => array_column($fila['ruta']['pasos'], 'detalle', 'clave')['documentos'];
        $this->assertSame(['0 de 5', '1 de 5'], [$detalle($filas[0]), $detalle($filas[1])]);
    }

    public function test_los_de_movimiento_mas_reciente_van_primero(): void
    {
        $this->credito($this->cliente('Prestamodel10', registradoEl: '2026-09-01'), '2026-11-10');
        $this->cliente('Registradohoy');
        $this->credito($this->cliente('Prestamodel18', registradoEl: '2026-09-01'), '2026-11-18');

        Livewire::test(Seguimiento::class)->assertSeeInOrder(['Registradohoy', 'Prestamodel18', 'Prestamodel10']);
    }

    public function test_al_buscar_a_un_cliente_el_periodo_no_se_aplica(): void
    {
        $antiguo = $this->cliente('Quispe', ['nombre' => 'Marta', 'expediente' => '7001', 'documento' => '40112233'], registradoEl: '2026-09-01');
        $this->credito($antiguo, '2026-01-10');
        $this->credito($this->cliente('Otroreciente'), '2026-11-15');

        foreach (['marta quispe', '40112233', '7001'] as $busqueda) {
            Livewire::test(Seguimiento::class)
                ->assertDontSee('Quispe')
                ->set('buscar', $busqueda)
                ->assertSee('Quispe')
                ->assertDontSee('Otroreciente');
        }

        Livewire::test(Seguimiento::class)
            ->set('buscar', 'nadie con ese nombre')
            ->assertSee('Ningún cliente activo coincide con la búsqueda.');
    }

    public function test_el_analista_solo_ve_los_expedientes_de_su_cartera(): void
    {
        $otroAsesor = User::factory()->create(['username' => 'otro-asesor-seg']);
        $this->credito($this->cliente('Demicartera'), '2026-11-15');
        $this->credito($this->cliente('Decarteraajena', ['asesor_id' => $otroAsesor->id]), '2026-11-15');

        Livewire::test(Seguimiento::class)->assertSee('Demicartera')->assertSee('Decarteraajena');

        $this->usuario->givePermissionTo('clientes.scope-propio');

        Livewire::test(Seguimiento::class)->assertSee('Demicartera')->assertDontSee('Decarteraajena');
        // Tampoco buscándolo por su nombre.
        Livewire::test(Seguimiento::class)->set('buscar', 'Decarteraajena')
            ->assertViewHas('filas', fn ($filas) => $filas->isEmpty())
            ->assertSee('Ningún cliente activo coincide con la búsqueda.');
    }

    public function test_un_periodo_estado_o_paso_invalido_en_la_url_cae_al_valor_inicial(): void
    {
        $this->credito($this->cliente('Urlrara'), '2026-11-15');

        Livewire::withQueryParams(['dias' => 'abc', 'estado' => 'cualquiera', 'paso' => 'inventado'])
            ->test(Seguimiento::class)
            ->assertViewHas('periodoActual', 30)
            ->assertViewHas('estadoActual', 'incompletos')
            ->assertSee('Urlrara');
    }

    public function test_si_hay_mas_clientes_que_el_tope_muestra_los_mas_recientes_y_lo_avisa(): void
    {
        config(['prestamos.tope_filas.seguimiento' => 2]);
        $this->cliente('Primero');
        $this->cliente('Segundo');
        $this->cliente('Tercero');

        Livewire::test(Seguimiento::class)
            ->assertSee('Se muestran los 2 más recientes.')
            ->assertSee('Tercero')
            ->assertSee('Segundo')
            ->assertDontSee('Primero');
    }

    public function test_la_pantalla_esta_en_el_menu_y_pide_el_permiso_de_clientes(): void
    {
        $this->get(route('clients.seguimiento'))
            ->assertOk()
            ->assertSee('SEGUIMIENTO DE EXPEDIENTES')
            ->assertSee('Seguimiento Exped.');

        $sinPermiso = User::factory()->create(['username' => 'sin-clientes']);

        $this->actingAs($sinPermiso)->get(route('clients.seguimiento'))->assertForbidden();
        Livewire::actingAs($sinPermiso)->test(Seguimiento::class)->assertForbidden();
    }
}
