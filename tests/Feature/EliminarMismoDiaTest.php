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
use App\Models\ClientUbicacion;
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
 * 09/10/2026 (Antony): quien no es director solo elimina lo que se registró
 * ese mismo día. 16/10/2026 (Antony): "esa regla de 6 a 11 hay que quitarla;
 * todos los anular o eliminar o incluso editar solo se debe poder el mismo
 * día, pasadas las 00:00 ya no, excepto el director". Bloqueado en el servidor
 * (ConReglasDeEliminacion dentro de cada método, que es lo único que ve los
 * eliminados que llegan como evento tras el SweetAlert) y en pantalla
 * (horario-eliminar.js con la bandera del layout y el data-creado del botón).
 */
class EliminarMismoDiaTest extends TestCase
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
        // En el resto de la suite la regla va apagada (phpunit.xml); aquí se prueba encendida.
        config(['auditoria.eliminar_mismo_dia.activo' => true]);
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
            'headquarter_id' => $this->sede->id, 'asesor_id' => $this->user->id,
        ]);
    }

    private function intentos(string $prefijo): int
    {
        return Activity::query()->where('log_name', Audit::LOG)->where('description', 'like', $prefijo.'%')->count();
    }

    public function test_solo_cuenta_el_dia_en_hora_de_lima_sin_ventana_horaria(): void
    {
        foreach (['delete', 'deleteAttachment', 'destroy', 'eliminar', 'eliminarFoto', 'borrar', 'anular', 'questionDelete'] as $m) {
            $this->assertTrue(HorarioEliminacion::esMetodoDeEliminar($m), $m);
        }
        foreach (['save', 'update', 'removeFile', 'quitarVehiculo', 'reverse', 'pagar', 'reactivate', 'editarCompromiso'] as $m) {
            $this->assertFalse(HorarioEliminacion::esMetodoDeEliminar($m), $m);
        }

        // La fecha del registro se compara con hoy en hora de Lima (created_at se guarda en UTC).
        $ahora = Carbon::parse('2026-10-09 08:30', 'America/Lima');
        $this->assertTrue(HorarioEliminacion::esDeHoy(Carbon::parse('2026-10-09 00:10', 'America/Lima'), $ahora));
        $this->assertTrue(HorarioEliminacion::esDeHoy(Carbon::parse('2026-10-10 02:00', 'UTC'), $ahora), 'las 21:00 de Lima del 09 ya son día 10 en UTC');
        $this->assertFalse(HorarioEliminacion::esDeHoy(Carbon::parse('2026-10-08 23:59', 'America/Lima'), $ahora));
        $this->assertFalse(HorarioEliminacion::esDeHoy(null, $ahora));

        // motivoBloqueo: lo de hoy a CUALQUIER hora (16/10: ya no hay ventana de 6 a 11); lo de ayer nunca;
        // sin fecha no; el director siempre puede.
        $deAyer = $this->egreso('2026-10-08 16:00:00');
        $deHoy = $this->egreso('2026-10-09 07:00:00');
        foreach (['02:00', '08:30', '15:00', '23:59'] as $hora) {
            $reloj = Carbon::parse("2026-10-09 {$hora}", 'America/Lima');
            $this->assertNull(HorarioEliminacion::motivoBloqueo($deHoy, $this->user, $reloj), "hoy a las {$hora}");
            $this->assertSame(HorarioEliminacion::mensajeMismoDia(), HorarioEliminacion::motivoBloqueo($deAyer, $this->user, $reloj), "ayer a las {$hora}");
        }
        $this->assertSame(HorarioEliminacion::mensajeMismoDia(), HorarioEliminacion::motivoBloqueo($deHoy, $this->user, Carbon::parse('2026-10-10 00:00', 'America/Lima')), 'a las 00:00 ya es otro día');
        $this->assertSame(HorarioEliminacion::mensajeMismoDia('editar'), HorarioEliminacion::motivoBloqueo($deAyer, $this->user, $ahora, 'editar'));
        $this->assertSame(HorarioEliminacion::mensajeSinFecha(), HorarioEliminacion::motivoBloqueo(null, $this->user, $ahora));
        config(['auditoria.eliminar_mismo_dia.activo' => false]);
        $this->assertNull(HorarioEliminacion::motivoBloqueo($deAyer, $this->user, $ahora), 'regla apagada: nada bloquea');
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
        $this->assertArrayNotHasKey('desde', HorarioEliminacion::paraJs(), 'la ventana horaria ya no existe');
    }

    public function test_a_cualquier_hora_se_elimina_lo_de_hoy_y_nunca_lo_de_ayer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima')); // antes esta hora bloqueaba todo
        $deAyer = $this->egreso('2026-10-08 16:00:00');
        $deHoy = $this->egreso('2026-10-09 07:00:00');
        Activity::query()->delete();

        // Lo de ayer: ni el questionDelete abre la confirmación ni el evento register_destroy elimina.
        Livewire::test(EditExpense::class, ['id' => $deAyer->id])
            ->call('questionDelete', $deAyer->id)
            ->assertDispatched('errorAlert')
            ->assertNotDispatched('questionDelete');
        Livewire::test(EditExpense::class, ['id' => $deAyer->id])
            ->dispatch('register_destroy', id: $deAyer->id)
            ->assertDispatched('errorAlert')
            ->assertNoRedirect();
        $this->assertNotNull($deAyer->fresh(), 'lo de ayer ya no se elimina');
        $this->assertSame(2, $this->intentos('Intentó eliminar un registro de otro día'), 'questionDelete + destroy');
        $intento = Activity::query()->where('log_name', Audit::LOG)->where('description', 'Intentó eliminar un registro de otro día: EditExpense::destroy')->first();
        $this->assertNotNull($intento);
        $this->assertSame(HorarioEliminacion::mensajeMismoDia(), $intento->properties['motivo']);
        $this->assertSame('2026-10-08 16:00:00', $intento->properties['creado_el']);
        $this->assertSame(0, $this->intentos('Intentó eliminar fuera de horario'), 'ese motivo ya no existe');

        // Lo de hoy sí, aunque sean las 15:00.
        Livewire::test(EditExpense::class, ['id' => $deHoy->id])
            ->dispatch('register_destroy', id: $deHoy->id)
            ->assertNotDispatched('errorAlert');
        $this->assertNull($deHoy->fresh(), 'lo registrado hoy se elimina a cualquier hora');

        // Pasada la medianoche, lo de "hoy" pasa a ser de ayer.
        $otroDeHoy = $this->egreso('2026-10-09 22:00:00');
        Carbon::setTestNow(Carbon::parse('2026-10-10 00:05', 'America/Lima'));
        Livewire::test(EditExpense::class, ['id' => $otroDeHoy->id])->call('destroy', $otroDeHoy->id)->assertDispatched('errorAlert');
        $this->assertNotNull($otroDeHoy->fresh(), 'a las 00:05 del día siguiente ya no');

        // El director elimina lo de cualquier día, a cualquier hora.
        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        Livewire::test(EditExpense::class, ['id' => $deAyer->id])
            ->dispatch('register_destroy', id: $deAyer->id)
            ->assertNotDispatched('errorAlert');
        $this->assertNull($deAyer->fresh(), 'el director elimina lo de otro día');
        Livewire::test(EditExpense::class, ['id' => $otroDeHoy->id])->call('destroy', $otroDeHoy->id)->assertNotDispatched('errorAlert');
        $this->assertNull($otroDeHoy->fresh());
    }

    public function test_la_regla_del_dia_cubre_adjuntos_documentos_gps_y_creditos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 18:20', 'America/Lima')); // tarde: antes bloqueaba por la hora
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

        // Documento emitido (Anular en la pestaña Documentos): el de ayer no; el de HOY sí aunque sean las 18:20
        // (16/10: el caso de Rosa, que a las 12:20 no podía anular el anexo que acababa de generar).
        $credito = Credit::create([
            'client_id' => $cliente->id, 'fecha_prestamo' => '2026-10-08', 'importe' => 5000, 'cuotas' => 4,
            'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id, 'user_id' => $this->user->id,
        ]);
        Credit::whereKey($credito->id)->update(['created_at' => $ayer]);
        $docAyer = DocumentoCliente::create([
            'client_id' => $cliente->id, 'credit_id' => $credito->id, 'tipo' => 'anexo1', 'version' => 1, 'correlativo' => '2026-900',
            'snapshot' => ['credito' => ['correlativo' => '2026-900']], 'pdf_path' => 'x.pdf', 'sha256' => str_repeat('a', 64), 'estado' => 'emitido',
        ]);
        DocumentoCliente::whereKey($docAyer->id)->update(['created_at' => $ayer]);
        $docHoy = DocumentoCliente::create([
            'client_id' => $cliente->id, 'credit_id' => $credito->id, 'tipo' => 'anexo2', 'version' => 1,
            'snapshot' => ['credito' => ['numero' => $credito->id]], 'pdf_path' => 'y.pdf', 'sha256' => str_repeat('b', 64), 'estado' => 'emitido',
        ]);
        $comp = Livewire::test(Documentos::class, ['id' => $cliente->id]);
        $comp->call('anular', $docAyer->id)->assertDispatched('errorAlert')->assertNotDispatched('successAlert');
        $this->assertSame('emitido', $docAyer->fresh()->estado, 'documento de ayer: no se anula');
        $comp->call('anular', $docHoy->id)->assertNotDispatched('errorAlert')->assertDispatched('successAlert');
        $this->assertSame('anulado', $docHoy->fresh()->estado, 'documento de hoy: se anula a las 18:20');

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

        // Coordenadas de la casa (fila de client_ubicaciones con su fecha de registro): registradas ayer, no se borran.
        $casa = $cliente->ubicaciones()->create(['tipo' => 'casa', 'nombre' => 'Casa', 'latitud' => -12.014431, 'longitud' => -76.824936, 'user_id' => $this->user->id, 'registrado_at' => $ayer]);
        ClientUbicacion::whereKey($casa->id)->update(['created_at' => $ayer]);
        Livewire::test(Gps::class, ['id' => $cliente->id])
            ->call('borrar', 'casa')
            ->assertDispatched('errorAlert');
        $this->assertEqualsWithDelta(-12.014431, (float) $cliente->fresh()->ubicacionFija('casa')->latitud, 0.000001, 'las coordenadas siguen');

        // Crédito del listado (ayer, sin pagos; con histórico antes se podía).
        Livewire::test(CreditsIndex::class)
            ->call('delete', $credito->id)
            ->assertDispatched('errorAlert');
        $this->assertNotNull($credito->fresh(), 'crédito de ayer: sigue');

        $this->assertSame(5, $this->intentos('Intentó eliminar un registro de otro día'), 'un intento por cada bloqueo');
    }

    public function test_las_acciones_que_no_eliminan_no_se_tocan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 23:30', 'America/Lima'));
        Livewire::test(CreateExpense::class)
            ->set('modo', 'Otros')->set('reason', 'Banco')->set('detail', 'Dep.')->set('total', '10')
            ->call('save')
            ->assertNotDispatched('errorAlert');
        $this->assertSame(1, Expense::where('caja', 1)->count(), 'guardar sigue funcionando a cualquier hora');
    }

    public function test_el_layout_entrega_la_bandera_y_el_boton_lleva_la_fecha_del_registro(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:30', 'America/Lima'));
        $html = $this->get(route('cash.expenses.create'))->assertOk()->getContent();
        $this->assertStringContainsString('window.HorarioEliminacion = {"director":false,"mismoDia":true,', $html);
        $this->assertStringNotContainsString('"desde":', $html, 'sin ventana horaria en la bandera');
        $this->assertStringContainsString('questionDelete)', $html, 'el patrón del JS incluye el botón con confirmación');
        $this->assertStringContainsString('assets/js/horario-eliminar.js', $html);

        // El botón Eliminar de editar egreso lleva data-creado con la fecha del registro (hoy, en Lima).
        $egreso = $this->egreso();
        $html = $this->get(route('cash.expenses.edit', $egreso->id))->assertOk()->getContent();
        $this->assertStringContainsString('wire:click="questionDelete('.$egreso->id.')" data-creado="2026-10-09">', $html);

        $js = file_get_contents(public_path('assets/js/horario-eliminar.js'));
        $this->assertStringContainsString("hasAttribute('data-creado')", $js);
        $this->assertStringContainsString("querySelectorAll('button, a')", $js, 'también los <a wire:click.prevent> (compromisos)');
        $this->assertStringNotContainsString('getHours()', $js, 'el JS ya no mira la hora');

        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        $this->assertStringContainsString('"director":true', $this->get(route('cash.expenses.create'))->getContent());
    }
}
