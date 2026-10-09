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
 * posibilidad de agregar más direcciones"); si la dirección ya tiene
 * coordenadas el campo va bloqueado hasta pulsar "Modificar"; y cada cambio
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

    public function test_el_campo_va_bloqueado_cuando_ya_hay_coordenadas_hasta_pulsar_modificar(): void
    {
        $c = $this->cliente();
        $html = Livewire::test(Gps::class, ['id' => $c->id])->html();

        // Sin coordenadas: campo a la vista y Guardar, sin botón Modificar.
        $this->assertSame(2, substr_count($html, '<div class="input-group input-group-sm">'), 'Casa y Negocio con el campo libre');
        $this->assertStringNotContainsString('Modificar', $html);

        $c->update(['latitud' => -12.1, 'longitud' => -76.9]);
        $html = Livewire::test(Gps::class, ['id' => $c->id])->html();

        // Casa con coordenadas: el campo solo sale al pulsar Modificar; Negocio sigue libre.
        $this->assertStringContainsString("<div class=\"input-group input-group-sm\" x-show=\"modificando === 'casa'\" x-cloak>", $html);
        $this->assertSame(1, substr_count($html, '<div class="input-group input-group-sm">'));
        // El formulario de "Agregar dirección" se oculta con !important (d-flex de Bootstrap lo pisaba).
        $this->assertStringContainsString('x-show.important="agregando" x-cloak', $html);
        $this->assertStringContainsString("x-on:click=\"modificando = 'casa'; \$nextTick(() => \$refs['campo-casa'].focus())\"", $html);
        $this->assertStringContainsString('<i class="ti ti-pencil"></i> Modificar', $html);
        $this->assertStringContainsString("x-on:click=\"modificando = null; \$wire.set('pegado.casa', '', false)\">Cancelar", $html, 'Cancelar vuelve a bloquear sin viaje');
        $this->assertStringContainsString('x-on:gps-guardado.window="modificando = null; agregando = false"', $html);
        $this->assertStringContainsString('Las coordenadas ya están registradas: pulsa Modificar si hay que cambiarlas.', $html);

        // Al guardar, el servidor avisa para que el navegador vuelva a bloquear.
        Livewire::test(Gps::class, ['id' => $c->id])
            ->set('pegado.casa', '-12.2, -76.8')
            ->call('guardar', 'casa')
            ->assertDispatched('gps-guardado', clave: 'casa');
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

        // En la lista, con su fila, el campo bloqueado y Modificar; se edita y se borra con la misma clave.
        $html = $comp->html();
        $this->assertStringContainsString('wire:key="dir-u:'.$u->id.'"', $html);
        $this->assertStringContainsString("x-on:click=\"modificando = 'u:{$u->id}'; \$nextTick(() => \$refs['campo-u:{$u->id}'].focus())\"", $html);
        $this->assertStringContainsString("wire:click=\"borrar('u:{$u->id}')\" data-creado=\"".now()->toDateString().'"', $html, 'la dirección adicional sí tiene fecha de registro');
        $this->assertStringContainsString('data-confirmar="¿Eliminar la dirección Taller?"', $html);

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
        $this->assertStringContainsString('<td>Actualizó</td>', $html);
        $this->assertStringContainsString('<td class="fw-semibold" style="word-break:break-all;">-12.5, -76.5</td>', $html);
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
}
