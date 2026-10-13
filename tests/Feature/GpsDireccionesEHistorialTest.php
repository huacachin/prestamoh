<?php

namespace Tests\Feature;

use App\Livewire\Clients\Gps;
use App\Models\Client;
use App\Models\ClientUbicacion;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
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
        $this->assertMatchesRegularExpression('~<th[^>]*>Op\.</th>(?:\s|<!--.*?-->)*<th[^>]*>N°</th>\s*<th[^>]*>Dirección</th>\s*<th>Coordenadas</th>~', $html);
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
        $this->assertEqualsWithDelta(-12.2, (float) $c->fresh()->latitud, 0.0001);
        $html = $comp->html();
        $this->assertStringContainsString('<span class="font-monospace">-12.2000000, -76.8000000</span>', $html);
        $this->assertStringContainsString('href="https://maps.google.com/?q=-12.2000000,-76.8000000" target="_blank"', $html);

        // Con coordenadas, dentro de la edición aparece Borrar coordenadas (con la fecha para la regla).
        $html = $comp->call('editar', 'casa')->html();
        $this->assertStringContainsString("wire:click=\"borrar('casa')\" data-creado=\"\"", $html, 'Casa no tiene fecha propia: solo el director la borra');
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
        $u = ClientUbicacion::sole();
        $this->assertSame(['Taller', $c->id, $this->user->id], [$u->nombre, $u->client_id, $u->user_id]);
        $this->assertEqualsWithDelta(-12.0464, (float) $u->latitud, 0.0001);
        $comp->assertDispatched('gps-guardado', clave: "u:{$u->id}");

        // Repetido (sin distinguir mayúsculas): no.
        $comp->set('nuevaNombre', 'TALLER')->set('nuevaCoordenadas', '-12.0, -77.0')->call('agregar')->assertHasErrors(['nuevaNombre']);

        // En la tabla, con su fila y su lápiz; dentro de la edición, Eliminar dirección con su fecha de registro.
        $html = $comp->html();
        $this->assertStringContainsString('wire:key="dir-u:'.$u->id.'"', $html);
        $this->assertStringContainsString("wire:click=\"editar('u:{$u->id}')\"", $html);
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
        $c->update(['latitud' => -12.3, 'longitud' => -76.7]);
        $c->update(['latitud' => -12.4]);

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
        $c->update(['latitud' => -12.1, 'longitud' => -76.9]);
        $comp = Livewire::test(Gps::class, ['id' => $c->id]);

        // Casa pasa a "Casa de la mamá" sin tocar las coordenadas.
        $comp->call('editar', 'casa')->set('editNombre', '  Casa   de la mamá ')->call('guardar', 'casa')
            ->assertHasNoErrors()->assertSet('msg', 'Dirección renombrada: Casa → Casa de la mamá.')->assertSet('editClave', null);
        $c->refresh();
        $this->assertSame('Casa de la mamá', $c->ubicacion1_nombre);
        $this->assertSame('Casa de la mamá', $c->nombreUbicacion('casa'));
        $this->assertEqualsWithDelta(-12.1, (float) $c->latitud, 0.0001, 'las coordenadas siguen');
        $this->assertStringContainsString('Casa de la mamá', $comp->html());

        // No puede repetir otro nombre (sin distinguir mayúsculas) ni quedar vacío.
        $comp->call('editar', 'negocio')->set('editNombre', 'casa DE LA MAMÁ')->call('guardar', 'negocio')->assertHasErrors(['editNombre']);
        $comp->set('editNombre', '')->call('guardar', 'negocio')->assertHasErrors(['editNombre']);
        // Renombrar y pegar coordenadas en el mismo guardado.
        $comp->set('editNombre', 'Tienda')->set('pegado.negocio', '-11.9, -77.1')->call('guardar', 'negocio')
            ->assertHasNoErrors()->assertSet('msg', 'Coordenadas de Tienda guardadas.');
        $c->refresh();
        $this->assertSame('Tienda', $c->ubicacion2_nombre);
        $this->assertEqualsWithDelta(-11.9, (float) $c->latitud2, 0.0001);
        // Volver al nombre de siempre deja la columna en null.
        $comp->call('editar', 'negocio')->set('editNombre', 'Negocio')->call('guardar', 'negocio')->assertHasNoErrors();
        $this->assertNull($c->fresh()->ubicacion2_nombre);

        // Una adicional también se renombra.
        $comp->set('nuevaNombre', 'Taller')->set('nuevaCoordenadas', '-12.0, -77.0')->call('agregar')->assertHasNoErrors();
        $u = ClientUbicacion::sole();
        $comp->call('editar', "u:{$u->id}")->set('editNombre', 'Chacra')->call('guardar', "u:{$u->id}")->assertHasNoErrors();
        $this->assertSame('Chacra', $u->fresh()->nombre);

        // La tablita: los renombres con antes/después = nombres, del más reciente al más viejo.
        $renombres = $comp->instance()->historial()->where('accion', 'Renombró')->values();
        $this->assertSame(['Chacra', 'Negocio', 'Tienda', 'Casa de la mamá'], $renombres->pluck('tipo')->all());
        $this->assertSame([['Taller', 'Chacra'], ['Tienda', 'Negocio'], ['Negocio', 'Tienda'], ['Casa', 'Casa de la mamá']],
            $renombres->map(fn ($f) => [$f['antes'], $f['despues']])->all());
    }
}
