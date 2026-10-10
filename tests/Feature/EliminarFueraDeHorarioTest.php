<?php

namespace Tests\Feature;

use App\Livewire\Cash\CreateExpense;
use App\Livewire\Cash\EditExpense;
use App\Livewire\Clients\Documentos;
use App\Livewire\Clients\Gallery;
use App\Livewire\Clients\Gps;
use App\Livewire\Clients\GpsVehiculos;
use App\Livewire\Credits\Index as CreditsIndex;
use App\Models\Client;
use App\Models\ClientAttachment;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsReporte;
use App\Support\Audit;
use App\Support\HorarioEliminacion;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 09/10/2026 (Antony): quien no es director solo puede eliminar de 6:00 a
 * 11:00 de la mañana Y solo lo que se registró ese mismo día. Bloqueado en el
 * servidor (hook de Livewire para las llamadas directas + ConReglasDeEliminacion
 * dentro de cada método, que es lo único que ve los eliminados que llegan como
 * evento tras el SweetAlert) y en pantalla (horario-eliminar.js, con la bandera
 * que pinta el layout y el data-creado de cada botón).
 */
class EliminarFueraDeHorarioTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'horario-tester', 'headquarter_id' => $this->sede->id]);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo(['caja.egresos', 'caja.eliminar', 'caja.editar-historico', 'caja.ver-todo']);
        $this->actingAs($this->user);
        // En el resto de la suite las dos reglas van apagadas (phpunit.xml); aquí se prueban encendidas.
        config(['auditoria.eliminar_horario.activo' => true, 'auditoria.eliminar_mismo_dia.activo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function egreso(?string $creadoEl = null): Expense
    {
        $egreso = Expense::create([
            'date' => now()->format('Y-m-d'), 'modo' => 'Otros', 'documento' => 'GUIA', 'caja' => 1, 'reason' => 'Banco',
            'detail' => 'Dep.', 'total' => 10, 'user_id' => $this->user->id, 'headquarter_id' => $this->sede->id,
        ]);
        if ($creadoEl) {
            Expense::whereKey($egreso->id)->update(['created_at' => $creadoEl]);
        }

        return $egreso->fresh();
    }

    private function cliente(): Client
    {
        return Client::create([
            'expediente' => '950', 'nombre' => 'Cliente', 'apellido_pat' => 'Con', 'apellido_mat' => 'Borrables',
            'tipo_documento' => 'DNI', 'documento' => '33445566', 'sexo' => 'M', 'status' => 'active',
            'headquarter_id' => $this->sede->id, 'asesor_id' => $this->user->id, 'latitud' => '-12.014431', 'longitud' => '-76.824936',
        ]);
    }

    private function intentos(string $prefijo): int
    {
        return Activity::query()->where('log_name', Audit::LOG)->where('description', 'like', $prefijo.'%')->count();
    }

    public function test_la_ventana_es_de_6_a_11_hora_de_lima_y_el_mismo_dia_se_compara_en_lima(): void
    {
        config(['auditoria.eliminar_horario.activo' => false]);
        $this->assertFalse(HorarioEliminacion::bloqueado($this->user, Carbon::parse('2026-10-09 15:00', 'America/Lima')), 'apagado: nunca bloquea');
        config(['auditoria.eliminar_horario.activo' => true]);
        $this->assertTrue(HorarioEliminacion::bloqueado($this->user, Carbon::parse('2026-10-09 15:00', 'America/Lima')));
        foreach (['05:59' => false, '06:00' => true, '10:59' => true, '11:00' => false, '15:30' => false, '02:00' => false] as $hora => $abierta) {
            $this->assertSame($abierta, HorarioEliminacion::enVentana(Carbon::parse("2026-10-09 {$hora}", 'America/Lima')), "a las {$hora}");
        }
        foreach (['delete', 'deleteAttachment', 'destroy', 'eliminar', 'eliminarFoto', 'borrar', 'anular', 'questionDelete'] as $m) {
            $this->assertTrue(HorarioEliminacion::esMetodoDeEliminar($m), $m);
        }
        foreach (['save', 'update', 'removeFile', 'quitarVehiculo', 'reverse', 'pagar', 'reactivate', 'editarCompromiso'] as $m) {
            $this->assertFalse(HorarioEliminacion::esMetodoDeEliminar($m), $m);
        }

        // Mismo día: la fecha del registro se compara con hoy en hora de Lima (created_at se guarda en UTC).
        $ahora = Carbon::parse('2026-10-09 08:30', 'America/Lima');
        $this->assertTrue(HorarioEliminacion::esDeHoy(Carbon::parse('2026-10-09 00:10', 'America/Lima'), $ahora));
        $this->assertTrue(HorarioEliminacion::esDeHoy(Carbon::parse('2026-10-10 02:00', 'UTC'), $ahora), 'las 21:00 de Lima del 09 ya son día 10 en UTC');
        $this->assertFalse(HorarioEliminacion::esDeHoy(Carbon::parse('2026-10-08 23:59', 'America/Lima'), $ahora));
        $this->assertFalse(HorarioEliminacion::esDeHoy(null, $ahora));

        // motivoBloqueo: primero el horario, luego el día; sin fecha no se elimina; el director nunca se bloquea.
        $deAyer = $this->egreso('2026-10-08 16:00:00');
        $deHoy = $this->egreso('2026-10-09 07:00:00'); // \"hoy\" respecto al reloj fijo del test (2026-10-09), no a la fecha real
        $this->assertSame(HorarioEliminacion::mensaje(), HorarioEliminacion::motivoBloqueo($deAyer, $this->user, Carbon::parse('2026-10-09 15:00', 'America/Lima')));
        $this->assertSame(HorarioEliminacion::mensajeMismoDia(), HorarioEliminacion::motivoBloqueo($deAyer, $this->user, $ahora));
        $this->assertNull(HorarioEliminacion::motivoBloqueo($deHoy, $this->user, $ahora));
        $this->assertSame(HorarioEliminacion::mensajeSinFecha(), HorarioEliminacion::motivoBloqueo(null, $this->user, $ahora));
        config(['auditoria.eliminar_mismo_dia.activo' => false]);
        $this->assertNull(HorarioEliminacion::motivoBloqueo($deAyer, $this->user, $ahora), 'regla del día apagada: solo manda el horario');
        config(['auditoria.eliminar_mismo_dia.activo' => true]);
        $director = User::factory()->create(['username' => 'dir-tester']);
        $director->syncRoles([Role::findOrCreate('director', 'web')]);
        $this->assertNull(HorarioEliminacion::motivoBloqueo($deAyer, $director, Carbon::parse('2026-10-09 15:00', 'America/Lima')));
        $this->assertNull(HorarioEliminacion::motivoBloqueo(null, $director, $ahora));

        // El patrón del JS: lo que escribe cada botón en su wire:click / x-on:click.
        $js = '/'.HorarioEliminacion::paraJs()['metodos'].'/i';
        foreach (['questionDelete(5)', "\$captura('anular', 12)", 'eliminarFoto(9)', "borrar('casa')", "\$captura('delete', 7)", 'destroy(3)', 'deleteAval(2)'] as $v) {
            $this->assertMatchesRegularExpression($js, $v, $v);
        }
        foreach (['removeFile(0)', 'quitarAdjuntoForm(1)', 'reverse', 'editarCompromiso(3)', "\$captura('save')", 'reactivate(4)'] as $v) {
            $this->assertDoesNotMatchRegularExpression($js, $v, $v);
        }
        $this->assertSame('data-creado="2026-10-08"', (string) HorarioEliminacion::atributoCreado($deAyer));
        $this->assertSame('data-creado=""', (string) HorarioEliminacion::atributoCreado(null));
    }

    public function test_fuera_de_horario_el_servidor_no_elimina_avisa_y_lo_deja_en_la_auditoria(): void
    {
        $egreso = $this->egreso();
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima'));
        Activity::query()->delete();

        Livewire::test(EditExpense::class, ['id' => $egreso->id])
            ->call('destroy', $egreso->id)
            ->assertDispatched('errorAlert')
            ->assertNoRedirect();

        $this->assertNotNull($egreso->fresh(), 'el egreso sigue');
        $this->assertStringContainsString(HorarioEliminacion::mensaje(), json_encode(Livewire::test(EditExpense::class, ['id' => $egreso->id])->call('destroy', $egreso->id)->effects['dispatches'] ?? [], JSON_UNESCAPED_UNICODE));
        $intento = Activity::query()->where('log_name', Audit::LOG)->where('description', 'like', 'Intentó eliminar fuera de horario%')->first();
        $this->assertNotNull($intento);
        $this->assertStringContainsString('EditExpense::destroy', $intento->description);
    }

    /**
     * Los botones Eliminar con confirmación (SweetAlert) no llaman a destroy:
     * disparan el evento register_destroy, que Livewire ejecuta dentro de su
     * propio hook sin pasar por el nuestro. La guardia dentro del método es la
     * que los frena; y el questionDelete previo ni abre la confirmación.
     */
    public function test_el_eliminar_con_confirmacion_llega_como_evento_y_tambien_se_bloquea(): void
    {
        $egreso = $this->egreso();
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima'));
        Activity::query()->delete();

        Livewire::test(EditExpense::class, ['id' => $egreso->id])
            ->call('questionDelete', $egreso->id)
            ->assertDispatched('errorAlert')
            ->assertNotDispatched('questionDelete');

        Livewire::test(EditExpense::class, ['id' => $egreso->id])
            ->dispatch('register_destroy', id: $egreso->id)
            ->assertDispatched('errorAlert')
            ->assertNoRedirect();

        $this->assertNotNull($egreso->fresh(), 'el egreso sigue aunque el evento haya llegado');
        $this->assertSame(2, $this->intentos('Intentó eliminar fuera de horario'), 'questionDelete (hook) + destroy (método)');
    }

    public function test_dentro_de_horario_solo_se_elimina_lo_registrado_hoy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:30', 'America/Lima'));
        $deAyer = $this->egreso('2026-10-08 16:00:00');
        $deHoy = $this->egreso('2026-10-09 07:00:00'); // \"hoy\" respecto al reloj fijo del test (2026-10-09), no a la fecha real
        Activity::query()->delete();

        Livewire::test(EditExpense::class, ['id' => $deAyer->id])
            ->call('questionDelete', $deAyer->id)
            ->assertDispatched('errorAlert')
            ->assertNotDispatched('questionDelete');
        Livewire::test(EditExpense::class, ['id' => $deAyer->id])
            ->dispatch('register_destroy', id: $deAyer->id)
            ->assertDispatched('errorAlert')
            ->assertNoRedirect();
        $this->assertNotNull($deAyer->fresh(), 'lo de ayer ya no se elimina');
        $this->assertSame(2, $this->intentos('Intentó eliminar un registro de otro día'), 'questionDelete + destroy, los dos por el método');
        $intento = Activity::query()->where('log_name', Audit::LOG)->where('description', 'Intentó eliminar un registro de otro día: EditExpense::destroy')->first();
        $this->assertNotNull($intento);
        $this->assertSame(HorarioEliminacion::mensajeMismoDia(), $intento->properties['motivo']);
        $this->assertSame('2026-10-08 16:00:00', $intento->properties['creado_el']);

        Livewire::test(EditExpense::class, ['id' => $deHoy->id])
            ->dispatch('register_destroy', id: $deHoy->id)
            ->assertNotDispatched('errorAlert');
        $this->assertNull($deHoy->fresh(), 'lo registrado hoy sí se elimina a las 8:30');

        // El director elimina lo de ayer, y a cualquier hora.
        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima'));
        Livewire::test(EditExpense::class, ['id' => $deAyer->id])
            ->dispatch('register_destroy', id: $deAyer->id)
            ->assertNotDispatched('errorAlert');
        $this->assertNull($deAyer->fresh(), 'el director elimina lo de otro día a cualquier hora');
    }

    public function test_la_regla_del_dia_cubre_adjuntos_documentos_gps_y_creditos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:30', 'America/Lima'));
        $this->user->givePermissionTo(['clientes', 'clientes.eliminar', 'creditos', 'creditos.eliminar']);
        $cliente = $this->cliente();
        $ayer = '2026-10-08 16:00:00';

        // Adjunto del cliente (llega como evento register_destroy).
        $adjunto = ClientAttachment::create([
            'client_id' => $cliente->id, 'filename' => 'a.jpg', 'original_name' => 'a.jpg', 'path' => "clients/{$cliente->id}/a.jpg",
            'thumb_path' => null, 'mime' => 'image/jpeg', 'size' => 10, 'uploaded_by' => $this->user->id,
        ]);
        ClientAttachment::whereKey($adjunto->id)->update(['created_at' => $ayer]);
        Livewire::test(Gallery::class, ['id' => $cliente->id])
            ->dispatch('register_destroy', id: $adjunto->id)
            ->assertDispatched('errorAlert');
        $this->assertNotNull($adjunto->fresh(), 'adjunto de ayer: sigue');

        // Documento emitido (Anular en la pestaña Documentos).
        $credito = Credit::create([
            'client_id' => $cliente->id, 'fecha_prestamo' => '2026-10-08', 'importe' => 5000, 'cuotas' => 4,
            'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id, 'user_id' => $this->user->id,
        ]);
        Credit::whereKey($credito->id)->update(['created_at' => $ayer]);
        $doc = DocumentoCliente::create([
            'client_id' => $cliente->id, 'credit_id' => $credito->id, 'tipo' => 'anexo1', 'version' => 1, 'correlativo' => '2026-900',
            'snapshot' => ['credito' => ['correlativo' => '2026-900']], 'pdf_path' => 'x.pdf', 'sha256' => str_repeat('a', 64), 'estado' => 'emitido',
        ]);
        DocumentoCliente::whereKey($doc->id)->update(['created_at' => $ayer]);
        Livewire::test(Documentos::class, ['id' => $cliente->id])
            ->call('anular', $doc->id)
            ->assertDispatched('errorAlert')
            ->assertNotDispatched('successAlert');
        $this->assertSame('emitido', $doc->fresh()->estado, 'documento de ayer: no se anula');

        // Reporte GPS del vehículo (pestaña GPS).
        $vehiculo = Vehiculo::create(['client_id' => $cliente->id, 'placa' => 'ALP-837', 'marca' => 'TOYOTA', 'modelo' => 'HIACE', 'valor' => 20000]);
        $reporte = VehiculoGpsReporte::create([
            'client_id' => $cliente->id, 'vehiculo_id' => $vehiculo->id, 'placa' => 'ALP-837', 'fecha' => '2026-10-08 10:30',
            'latitud' => -12.014431, 'longitud' => -76.824936, 'descripcion' => 'Las Lúcumas', 'registrado_por' => $this->user->id,
        ]);
        VehiculoGpsReporte::whereKey($reporte->id)->update(['created_at' => $ayer]);
        Livewire::test(GpsVehiculos::class, ['id' => $cliente->id])
            ->call('eliminar', $reporte->id)
            ->assertDispatched('errorAlert');
        $this->assertNotNull($reporte->fresh(), 'reporte GPS de ayer: sigue');

        // Coordenadas de la casa: no tienen fecha propia, solo el director las borra.
        Livewire::test(Gps::class, ['id' => $cliente->id])
            ->call('borrar', 'casa')
            ->assertDispatched('errorAlert');
        $this->assertEqualsWithDelta(-12.014431, (float) $cliente->fresh()->latitud, 0.000001, 'las coordenadas siguen');

        // Crédito del listado (ayer, sin pagos; con histórico antes se podía).
        Livewire::test(CreditsIndex::class)
            ->call('delete', $credito->id)
            ->assertDispatched('errorAlert');
        $this->assertNotNull($credito->fresh(), 'crédito de ayer: sigue');

        $this->assertSame(5, $this->intentos('Intentó eliminar un registro de otro día'), 'un intento por cada bloqueo');
        $this->assertSame(0, $this->intentos('Intentó eliminar fuera de horario'));
    }

    public function test_dentro_de_horario_elimina_y_el_director_elimina_a_cualquier_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:30', 'America/Lima'));
        $egreso = $this->egreso(); // creado \"hoy\" según el reloj congelado, no la fecha real
        Livewire::test(EditExpense::class, ['id' => $egreso->id])->call('destroy', $egreso->id)->assertNotDispatched('errorAlert');
        $this->assertNull($egreso->fresh(), 'a las 8:30 sí se elimina');

        $otro = $this->egreso();
        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima'));
        Livewire::test(EditExpense::class, ['id' => $otro->id])->call('destroy', $otro->id)->assertNotDispatched('errorAlert');
        $this->assertNull($otro->fresh(), 'el director elimina a cualquier hora');
    }

    public function test_el_hook_no_toca_las_acciones_que_no_eliminan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima'));
        Livewire::test(CreateExpense::class)
            ->set('modo', 'Otros')->set('reason', 'Banco')->set('detail', 'Dep.')->set('total', '10')
            ->call('save')
            ->assertNotDispatched('errorAlert');
        $this->assertSame(1, Expense::where('caja', 1)->count(), 'guardar sigue funcionando a cualquier hora');

        // Y el delete de créditos (otro componente, otro nombre de método) sí queda bloqueado.
        $this->user->givePermissionTo('creditos');
        Livewire::test(CreditsIndex::class)->call('delete', 999999)->assertDispatched('errorAlert');
    }

    public function test_el_layout_entrega_la_bandera_y_el_boton_lleva_la_fecha_del_registro(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:30', 'America/Lima'));
        $html = $this->get(route('cash.expenses.create'))->assertOk()->getContent();
        $this->assertStringContainsString('window.HorarioEliminacion = {"activo":true,"director":false,"desde":6,"hasta":11,', $html);
        $this->assertStringContainsString('"mismoDia":true,', $html);
        $this->assertStringContainsString('questionDelete)', $html, 'el patrón del JS incluye el botón con confirmación');
        $this->assertStringContainsString('assets/js/horario-eliminar.js', $html);

        // El botón Eliminar de editar egreso lleva data-creado con la fecha del registro (hoy, en Lima).
        $egreso = $this->egreso();
        $html = $this->get(route('cash.expenses.edit', $egreso->id))->assertOk()->getContent();
        $this->assertStringContainsString('wire:click="questionDelete('.$egreso->id.')" data-creado="2026-10-09">', $html);

        $js = file_get_contents(public_path('assets/js/horario-eliminar.js'));
        $this->assertStringContainsString("hasAttribute('data-creado')", $js);
        $this->assertStringContainsString("querySelectorAll('button, a')", $js, 'también los <a wire:click.prevent> (compromisos)');

        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        $this->assertStringContainsString('"director":true', $this->get(route('cash.expenses.create'))->getContent());
    }
}
