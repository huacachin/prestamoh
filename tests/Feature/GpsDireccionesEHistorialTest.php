<?php

namespace Tests\Feature;

use App\Livewire\Clients\Gps;
use App\Models\Client;
use App\Models\ClientUbicacion;
use App\Models\User;
use App\Support\Audit;
use App\Support\HorarioEliminacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony, ficha 173): pestaña GPS organizada. Casa, Negocio y
 * cuantas direcciones más haga falta, con nombre propio ("tiene que haber la
 * posibilidad de agregar más direcciones"); desde el 13/10 van en tabla con
 * lápiz de edición (nombre y coordenadas) como los vehículos; y cada cambio
 * sale en una tablita (dirección, antes, después, quién, cuándo) debajo de
 * las direcciones y antes de las ubicaciones de los vehículos.
 */
class GpsDireccionesEHistorialTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Fila de la auditoría automática del modelo (antes del 15/10, al editar coordenadas desde la ficha). */
    private function filaAutomaticaDeLaFicha(Client $c, array $antes, array $despues): void
    {
        DB::table('activity_log')->insert([
            'log_name' => Audit::LOG, 'description' => 'Editó Cliente '.$c->fullName(), 'event' => 'updated',
            'subject_type' => $c->getMorphClass(), 'subject_id' => $c->id,
            'causer_type' => $this->user->getMorphClass(), 'causer_id' => $this->user->id,
            'properties' => json_encode(['old' => $antes, 'attributes' => $despues]),
            'old_data' => json_encode($antes), 'new_data' => json_encode($despues), 'changed_fields' => json_encode(array_keys($despues)),
            'user_name' => $this->user->name, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function cliente(): Client
    {
        $this->user = User::factory()->create(['username' => 'gps-hist', 'name' => 'Gps Hist']);
        $this->actingAs($this->user);

        return Client::create([
            'expediente' => '960', 'nombre' => 'Cliente', 'apellido_pat' => 'Con', 'apellido_mat' => 'Historial',
            'tipo_documento' => 'DNI', 'documento' => '33445599', 'sexo' => 'M', 'status' => 'active',
        ]);
    }

    /** 13/10 (Antony): las direcciones van en tabla, como las ubicaciones de los vehículos, y el lápiz abre su formulario. */
    public function test_las_direcciones_van_en_tabla_y_el_lapiz_abre_el_formulario_de_nombre_y_coordenadas(): void
    {
        $c = $this->cliente();
        $comp = Livewire::test(Gps::class, ['id' => $c->id]);
        $html = $comp->html();

        // Tabla como la de los vehículos: Op. · N° · Dirección · Coordenadas; Casa y Negocio sin ubicación.
        $this->assertMatchesRegularExpression('~<th[^>]*>Op\.</th>(?:\s|<!--.*?-->)*<th[^>]*>N°</th>\s*<th[^>]*>Fecha</th>\s*<th[^>]*>Usuario</th>\s*<th[^>]*>Dirección</th>\s*<th>Coordenadas</th>~', $html);
        $this->assertStringContainsString('wire:key="dir-casa"', $html);
        $this->assertStringContainsString('wire:key="dir-negocio"', $html);
        $this->assertSame(2, substr_count($html, 'Sin ubicación'));
        $this->assertStringContainsString("wire:click=\"editar('casa')\"", $html);
        $this->assertStringContainsString("wire:click=\"editar('negocio')\"", $html);
        $this->assertStringNotContainsString('wire:model="editNombre"', $html, 'sin formulario hasta pulsar el lápiz');
        $this->assertStringNotContainsString('wire:click="borrar(', $html, 'Borrar solo dentro de la edición');
        // El formulario de "Agregar dirección" se oculta con !important (d-flex de Bootstrap lo pisaba).
        $this->assertStringContainsString('x-show.important="agregando" x-cloak', $html);

        // El lápiz abre el formulario con el nombre actual y el campo de coordenadas vacío.
        $comp->call('editar', 'casa')->assertSet('editClave', 'casa')->assertSet('editNombre', 'Casa');
        $html = $comp->html();
        $this->assertStringContainsString('Editando Casa', $html);
        $this->assertStringContainsString('wire:model="editNombre"', $html);
        $this->assertStringContainsString('wire:model="pegado.casa"', $html);
        $this->assertStringContainsString('Guardar cambios', $html);
        $this->assertStringNotContainsString("wire:click=\"borrar('casa')\"", $html, 'sin coordenadas no hay nada que borrar');
        $this->assertStringContainsString('<tr wire:key="dir-casa" class="table-warning">', $html, 'la fila en edición se resalta');

        // Sin nada que guardar, avisa; con coordenadas, guarda y cierra el formulario.
        $comp->call('guardar', 'casa')->assertSet('msgType', 'warn')->assertSet('editClave', 'casa');
        $comp->set('pegado.casa', '-12.2, -76.8')->call('guardar', 'casa')
            ->assertSet('msgType', 'ok')->assertSet('editClave', null)->assertDispatched('gps-guardado', clave: 'casa');
        $this->assertEqualsWithDelta(-12.2, (float) $c->fresh()->ubicacionFija('casa')->latitud, 0.0001);
        $html = $comp->html();
        $this->assertStringContainsString('<span class="font-monospace">-12.2000000, -76.8000000</span>', $html);
        $this->assertStringContainsString('href="https://maps.google.com/?q=-12.2000000,-76.8000000" target="_blank"', $html);
        // 14/10 (Antony): la fila lleva la fecha del último registro y quién lo hizo; Negocio sigue sin nada.
        // Orden (14/10, Antony): Op. · N° · Fecha · Usuario · Dirección · Coordenadas.
        $this->assertMatchesRegularExpression('~<th[^>]*>N°</th>\s*<th[^>]*>Fecha</th>\s*<th[^>]*>Usuario</th>\s*<th[^>]*>Dirección</th>\s*<th>Coordenadas</th>~', $html);
        $this->assertLessThan(strpos($html, 'data-label="Dirección"'), strpos($html, 'data-label="Fecha"'), 'en el celular también: fecha antes de la dirección');
        $this->assertStringContainsString(now()->format('d/m/Y').' <span class="text-muted">'.now()->format('H:i').'</span>', $html);
        $this->assertStringContainsString('<td class="text-center" data-label="Usuario">gps-hist</td>', $html);
        $this->assertSame(1, substr_count($html, 'data-label="Usuario">gps-hist</td>'), 'solo Casa tiene registro');
        $this->assertSame(1, substr_count($html, 'data-label="Usuario">—</td>'), 'Negocio sin registro');

        // Con coordenadas, dentro de la edición aparece Borrar coordenadas (con la fecha para la regla).
        $html = $comp->call('editar', 'casa')->html();
        // 15/10: Casa ya es una fila con su propia fecha de registro; la regla del mismo día aplica como a las demás.
        $this->assertStringContainsString("wire:click=\"borrar('casa')\" data-creado=\"".now()->toDateString().'"', $html);
        $this->assertStringContainsString('data-confirmar="¿Borrar las coordenadas de Casa?"', $html);
        $comp->call('cancelarEdicion')->assertSet('editClave', null)->assertSet('editNombre', '');
    }

    public function test_se_agregan_direcciones_con_nombre_propio_y_se_editan_y_borran_como_las_fijas(): void
    {
        $c = $this->cliente();
        $comp = Livewire::test(Gps::class, ['id' => $c->id]);
        $comp->assertSee('Agregar dirección');

        // Nombre y coordenadas obligatorios; el nombre no puede repetir Casa/Negocio ni otra adicional.
        $comp->set('nuevaNombre', '')->set('nuevaCoordenadas', '')->call('agregar')->assertHasErrors(['nuevaNombre', 'nuevaCoordenadas']);
        $comp->set('nuevaNombre', 'casa')->set('nuevaCoordenadas', '-12.0, -77.0')->call('agregar')->assertHasErrors(['nuevaNombre']);
        $comp->set('nuevaNombre', 'Taller')->set('nuevaCoordenadas', 'por el mercado')->call('agregar')->assertHasErrors(['nuevaCoordenadas']);
        $this->assertSame(0, ClientUbicacion::count());

        $comp->set('nuevaNombre', '  Taller  ')->set('nuevaCoordenadas', 'https://www.google.com/maps/@-12.0464,-77.0428,17z')->call('agregar')
            ->assertHasNoErrors()
            ->assertSet('nuevaNombre', '')
            ->assertSet('msgType', 'ok');
        $u = ClientUbicacion::where('tipo', 'otra')->sole();
        $this->assertSame(['Taller', $c->id, $this->user->id], [$u->nombre, $u->client_id, $u->user_id]);
        $this->assertEqualsWithDelta(-12.0464, (float) $u->latitud, 0.0001);
        $comp->assertDispatched('gps-guardado', clave: "u:{$u->id}");

        // Repetido (sin distinguir mayúsculas): no.
        $comp->set('nuevaNombre', 'TALLER')->set('nuevaCoordenadas', '-12.0, -77.0')->call('agregar')->assertHasErrors(['nuevaNombre']);

        // En la tabla, con su fila y su lápiz; dentro de la edición, Eliminar dirección con su fecha de registro.
        $html = $comp->html();
        $this->assertStringContainsString('wire:key="dir-u:'.$u->id.'"', $html);
        $this->assertStringContainsString("wire:click=\"editar('u:{$u->id}')\"", $html);
        // 14/10: la adicional muestra quién la registró y cuándo.
        $this->assertStringContainsString('<td class="text-center" data-label="Usuario">gps-hist</td>', $html);
        $this->assertStringContainsString(now()->format('d/m/Y').' <span class="text-muted">'.now()->format('H:i').'</span>', $html);
        $html = $comp->call('editar', "u:{$u->id}")->assertSet('editNombre', 'Taller')->html();
        $this->assertStringContainsString("wire:click=\"borrar('u:{$u->id}')\" data-creado=\"".now()->toDateString().'"', $html, 'la dirección adicional sí tiene fecha de registro');
        $this->assertStringContainsString('data-confirmar="¿Eliminar la dirección Taller?"', $html);
        $this->assertStringContainsString('Eliminar dirección', $html);

        $comp->set("pegado.u:{$u->id}", '-12.5, -77.5')->call('guardar', "u:{$u->id}")->assertSet('msgType', 'ok');
        $this->assertEqualsWithDelta(-12.5, (float) $u->fresh()->latitud, 0.0001);

        $comp->call('borrar', "u:{$u->id}")->assertSet('msg', 'Dirección Taller eliminada.');
        $this->assertNull($u->fresh());

        // Y todo quedó en la tablita, con el nombre de la dirección.
        $filas = $comp->instance()->historial();
        $this->assertSame(['Borró', 'Actualizó', 'Registró'], $filas->pluck('accion')->all());
        $this->assertSame(['Taller', 'Taller', 'Taller'], $filas->pluck('tipo')->all());
        $this->assertSame('-12.5, -77.5', $filas[0]['antes']);
        $this->assertSame('—', $filas[0]['despues']);
        $this->assertSame('-12.0464, -77.0428', $filas[2]['despues']);
    }

    public function test_cada_cambio_queda_en_la_tablita_con_antes_despues_y_quien(): void
    {
        $c = $this->cliente();
        $comp = Livewire::test(Gps::class, ['id' => $c->id]);
        $comp->assertSee('Cambios de ubicación')->assertSee('Todavía no hay cambios registrados');

        $comp->set('pegado.casa', '-12.014431, -76.824936')->call('guardar', 'casa')
            ->set('pegado.casa', '-12.5, -76.5')->call('guardar', 'casa')
            ->set('pegado.negocio', '-11.9, -77.1')->call('guardar', 'negocio');
        // El director borra; para la prueba basta con que el bloqueo de horario/día esté apagado (phpunit.xml).
        $comp->call('borrar', 'negocio');

        $filas = $comp->instance()->historial();
        $this->assertCount(4, $filas);
        // Del más reciente al más viejo.
        $this->assertSame(['Borró', 'Registró', 'Actualizó', 'Registró'], $filas->pluck('accion')->all());
        $this->assertSame(['Negocio', 'Negocio', 'Casa', 'Casa'], $filas->pluck('tipo')->all());
        $this->assertSame('-11.9, -77.1', $filas[0]['antes']);
        $this->assertSame('—', $filas[0]['despues']);
        $this->assertSame('-12.014431, -76.824936', $filas[2]['antes']);
        $this->assertSame('-12.5, -76.5', $filas[2]['despues']);
        $this->assertSame('—', $filas[3]['antes']);
        $this->assertSame(['gps-hist', 'gps-hist', 'gps-hist', 'gps-hist'], $filas->pluck('quien')->all());

        // En pantalla, entre las direcciones y las ubicaciones de los vehículos.
        $html = $comp->html();
        $this->assertStringContainsString('tabla-cambios-gps', $html);
        $this->assertStringContainsString('<td data-label="Acción">Actualizó</td>', $html);
        $this->assertStringContainsString('<td class="fw-semibold" style="word-break:break-all;" data-label="Después">-12.5, -76.5</td>', $html);
        // 10/10: en el celular las tablas se apilan con el rótulo de cada columna (data-label) y la lista de direcciones es flexible.
        $this->assertStringContainsString('tabla-cambios-gps tabla-apilable', $html);
        // 13/10 (Antony): la tabla va escondida tras "Ver movimientos" (Alpine), con el conteo.
        $this->assertStringContainsString('x-data="{ verCambios: false }"', $html);
        $this->assertStringContainsString('<div class="table-responsive" x-show="verCambios" x-cloak x-transition.opacity>', $html);
        $this->assertStringContainsString("Ver movimientos ({$filas->count()})", $html);
        $this->assertStringContainsString('.tabla-apilable td[data-label]::before { content: attr(data-label) ": "', $html);
        $this->assertStringContainsString('<tr wire:key="dir-casa"', $html);
        $this->assertStringNotContainsString('style="width:160px;"', $html, 'sin anchos fijos que aplasten el campo en el celular');
        $this->assertLessThan(strpos($html, 'tabla-cambios-gps'), strpos($html, 'lista-direcciones-gps'), 'la lista de direcciones va antes de la tablita');
        $this->assertLessThan(strpos($html, 'wire:name="clients.gps-vehiculos"') ?: PHP_INT_MAX, strpos($html, 'tabla-cambios-gps'), 'la tablita va antes de los vehículos');

        // Sin filas duplicadas: la auditoría automática del modelo queda apagada en el guardado de esta
        // pestaña (solo quedan las 4 filas manuales con detalle y el alta del cliente).
        $delCliente = Activity::where('log_name', Audit::LOG)->where('subject_id', $c->id)->where('subject_type', $c->getMorphClass());
        $this->assertSame(4, (clone $delCliente)->where('description', 'like', '%la ubicación GPS%')->count());
        $this->assertSame(0, (clone $delCliente)->where('event', 'updated')->count(), 'ninguna fila automática "Editó Cliente"');
    }

    public function test_los_cambios_hechos_desde_la_ficha_del_cliente_tambien_salen(): void
    {
        $c = $this->cliente();
        // Edición normal del modelo (p. ej. desde la ficha): auditoría automática con old/attributes.
        // 15/10: clients ya no lleva coordenadas; las filas automáticas de antes siguen en la auditoría y se leen igual.
        $this->filaAutomaticaDeLaFicha($c, ['latitud' => null, 'longitud' => null], ['latitud' => -12.3, 'longitud' => -76.7]);
        $this->filaAutomaticaDeLaFicha($c, ['latitud' => -12.3], ['latitud' => -12.4]);

        $filas = Livewire::test(Gps::class, ['id' => $c->id])->instance()->historial();
        $this->assertCount(2, $filas);
        $this->assertSame('Casa (desde la ficha)', $filas[0]['tipo']);
        $this->assertSame('Actualizó', $filas[0]['accion']);
        // Cambió solo la latitud: la auditoría automática guarda únicamente lo que cambió, y eso es lo que se muestra.
        $this->assertSame('-12.3', $filas[0]['antes']);
        $this->assertSame('-12.4', $filas[0]['despues']);
        $this->assertSame('Registró', $filas[1]['accion']);
        $this->assertSame('—', $filas[1]['antes']);
        $this->assertSame('gps-hist', $filas[1]['quien']);
    }

    /** 13/10 (Antony): "las etiquetas Casa y Negocio deben ser editables". */
    public function test_casa_negocio_y_las_adicionales_se_renombran_y_el_cambio_queda_en_la_tablita(): void
    {
        $c = $this->cliente();
        $c->ubicaciones()->create(['tipo' => 'casa', 'nombre' => 'Casa', 'latitud' => -12.1, 'longitud' => -76.9, 'user_id' => $this->user->id, 'registrado_at' => now()]);
        $comp = Livewire::test(Gps::class, ['id' => $c->id]);

        // Casa pasa a "Casa de la mamá" sin tocar las coordenadas.
        $comp->call('editar', 'casa')->set('editNombre', '  Casa   de la mamá ')->call('guardar', 'casa')
            ->assertHasNoErrors()->assertSet('msg', 'Dirección renombrada: Casa → Casa de la mamá.')->assertSet('editClave', null);
        $c->refresh();
        $this->assertSame('Casa de la mamá', $c->ubicacionFija('casa')->nombre);
        $this->assertSame('Casa de la mamá', $c->nombreUbicacion('casa'));
        $this->assertEqualsWithDelta(-12.1, (float) $c->ubicacionFija('casa')->latitud, 0.0001, 'las coordenadas siguen');
        $this->assertStringContainsString('Casa de la mamá', $comp->html());

        // No puede repetir otro nombre (sin distinguir mayúsculas) ni quedar vacío.
        $comp->call('editar', 'negocio')->set('editNombre', 'casa DE LA MAMÁ')->call('guardar', 'negocio')->assertHasErrors(['editNombre']);
        $comp->set('editNombre', '')->call('guardar', 'negocio')->assertHasErrors(['editNombre']);
        // Renombrar y pegar coordenadas en el mismo guardado.
        $comp->set('editNombre', 'Tienda')->set('pegado.negocio', '-11.9, -77.1')->call('guardar', 'negocio')
            ->assertHasNoErrors()->assertSet('msg', 'Coordenadas de Tienda guardadas.');
        $c->refresh();
        $this->assertSame('Tienda', $c->ubicacionFija('negocio')->nombre);
        $this->assertEqualsWithDelta(-11.9, (float) $c->ubicacionFija('negocio')->latitud, 0.0001);
        // Volver al nombre de siempre deja la columna en null.
        $comp->call('editar', 'negocio')->set('editNombre', 'Negocio')->call('guardar', 'negocio')->assertHasNoErrors();
        $this->assertSame('Negocio', $c->fresh()->ubicacionFija('negocio')->nombre);

        // Una adicional también se renombra.
        $comp->set('nuevaNombre', 'Taller')->set('nuevaCoordenadas', '-12.0, -77.0')->call('agregar')->assertHasNoErrors();
        $u = ClientUbicacion::where('tipo', 'otra')->sole();
        $comp->call('editar', "u:{$u->id}")->set('editNombre', 'Chacra')->call('guardar', "u:{$u->id}")->assertHasNoErrors();
        $this->assertSame('Chacra', $u->fresh()->nombre);

        // La tablita: los renombres con antes/después = nombres, del más reciente al más viejo.
        $renombres = $comp->instance()->historial()->where('accion', 'Renombró')->values();
        $this->assertSame(['Chacra', 'Negocio', 'Tienda', 'Casa de la mamá'], $renombres->pluck('tipo')->all());
        $this->assertSame([['Taller', 'Chacra'], ['Tienda', 'Negocio'], ['Negocio', 'Tienda'], ['Casa', 'Casa de la mamá']],
            $renombres->map(fn ($f) => [$f['antes'], $f['despues']])->all());
    }

    /**
     * 15/10 (Antony): "se puede editar solo en el día que se registró, luego no; solo el
     * director puede editar en cualquier momento". La fecha que cuenta es la de registro de
     * las coordenadas (la columna Fecha), no la de la fila: Casa y Negocio viven para siempre.
     */
    public function test_quien_no_es_director_edita_una_direccion_solo_el_dia_en_que_se_registro(): void
    {
        config(['auditoria.eliminar_mismo_dia.activo' => true]);
        Carbon::setTestNow(Carbon::parse('2026-10-14 15:00', 'America/Lima'));
        $ayer = now()->subDay();
        $c = $this->cliente();
        // Casa: fila de la carga inicial, coordenadas registradas ayer. Taller: registrado hoy. Negocio: sin coordenadas.
        $casa = $c->ubicaciones()->create(['tipo' => 'casa', 'nombre' => 'Casa', 'latitud' => -12.1, 'longitud' => -76.9, 'user_id' => $this->user->id, 'registrado_at' => $ayer]);
        ClientUbicacion::whereKey($casa->id)->update(['created_at' => $ayer]);
        $taller = $c->ubicaciones()->create(['tipo' => 'otra', 'nombre' => 'Taller', 'latitud' => -12.0, 'longitud' => -77.0, 'user_id' => $this->user->id, 'registrado_at' => now()]);

        $comp = Livewire::test(Gps::class, ['id' => $c->id]);
        $html = $comp->html();
        $this->assertStringNotContainsString("wire:click=\"editar('casa')\"", $html, 'Casa de ayer: sin lápiz');
        $this->assertStringContainsString('ti-lock', $html, 'en su lugar, un candado');
        $this->assertStringContainsString("wire:click=\"editar('u:{$taller->id}')\"", $html, 'Taller de hoy: con lápiz');
        $this->assertStringContainsString("wire:click.prevent=\"editar('negocio')\"", $html, 'Negocio sin coordenadas: se pueden pegar cualquier día');

        // El servidor también lo rechaza, avisa y deja rastro; las coordenadas no cambian.
        $comp->call('editar', 'casa')->assertDispatched('errorAlert')->assertSet('editClave', null);
        $comp->set('pegado.casa', '-11.5, -77.5')->call('guardar', 'casa')->assertDispatched('errorAlert');
        $this->assertEqualsWithDelta(-12.1, (float) $casa->fresh()->latitud, 0.0001, 'Casa sigue con las de ayer');
        $this->assertSame(1, Activity::where('description', 'Intentó editar un registro de otro día: Gps::editar')->count());
        $this->assertSame(1, Activity::where('description', 'Intentó editar un registro de otro día: Gps::guardar')->count());
        $this->assertSame(HorarioEliminacion::mensajeMismoDia('editar'), HorarioEliminacion::motivoBloqueo($casa->fresh(), $this->user, null, 'editar'));

        // Lo de hoy y lo que no tiene coordenadas sí: Taller se edita y a Negocio se le pegan coordenadas.
        $comp->call('editar', "u:{$taller->id}")->assertNotDispatched('errorAlert')->assertSet('editClave', "u:{$taller->id}")
            ->set('pegado.u:'.$taller->id, '-12.2, -77.2')->call('guardar', "u:{$taller->id}")->assertHasNoErrors()->assertSet('editClave', null);
        $this->assertEqualsWithDelta(-12.2, (float) $taller->fresh()->latitud, 0.0001);
        $comp->call('editar', 'negocio')->assertNotDispatched('errorAlert')->assertSet('editClave', 'negocio')
            ->set('pegado.negocio', '-11.9, -77.1')->call('guardar', 'negocio')->assertHasNoErrors();
        $this->assertEqualsWithDelta(-11.9, (float) $c->fresh()->ubicacionFija('negocio')->latitud, 0.0001);

        // El director edita cualquier día; el botón Borrar lleva la fecha de registro (la que lee horario-eliminar.js).
        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        $director = Livewire::test(Gps::class, ['id' => $c->id]);
        $this->assertStringContainsString("wire:click=\"editar('casa')\"", $director->html(), 'el director ve el lápiz de Casa');
        $director->call('editar', 'casa')->assertNotDispatched('errorAlert')->assertSet('editClave', 'casa');
        $this->assertStringContainsString("wire:click=\"borrar('casa')\" data-creado=\"2026-10-13\"", $director->html());
        $director->set('pegado.casa', '-11.5, -77.5')->call('guardar', 'casa')->assertHasNoErrors();
        $casa->refresh();
        $this->assertEqualsWithDelta(-11.5, (float) $casa->latitud, 0.0001);
        // Registradas hoy por el director: hoy cualquiera con permiso vuelve a poder editarlas.
        $this->assertSame('2026-10-14', $casa->registrado_at->toDateString());
        $asesor = User::factory()->create(['username' => 'gps-asesor']);
        $this->assertNull(HorarioEliminacion::motivoBloqueo($casa, $asesor, null, 'editar'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
