<?php

namespace Tests\Feature;

use App\Livewire\Clients\Gps;
use App\Models\Client;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony, ficha 173): pestaña GPS organizada. Una dirección más
 * (Negocio) aparte de Casa; si la dirección ya tiene coordenadas el campo va
 * bloqueado hasta pulsar "Modificar"; y cada cambio sale en una tablita
 * (dirección, antes, después, quién, cuándo) debajo de las direcciones y antes
 * de las ubicaciones de los vehículos.
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

        // Sin coordenadas: campo libre y Guardar a la vista, sin botón Modificar.
        $this->assertSame(2, substr_count($html, ':disabled="false && !modificando"'), 'Casa y Negocio libres');
        $this->assertStringNotContainsString('Modificar', $html);
        $this->assertSame(2, substr_count($html, 'x-show="true"'));

        $c->update(['latitud' => -12.1, 'longitud' => -76.9]);
        $html = Livewire::test(Gps::class, ['id' => $c->id])->html();

        // Casa con coordenadas: bloqueada, con Modificar; Negocio sigue libre.
        $this->assertSame(1, substr_count($html, ':disabled="true && !modificando"'));
        $this->assertSame(1, substr_count($html, ':disabled="false && !modificando"'));
        $this->assertStringContainsString('x-on:click="modificando = true; $nextTick(() => $refs.campo.focus())"', $html);
        $this->assertStringContainsString('<i class="ti ti-pencil"></i> Modificar', $html);
        $this->assertStringContainsString('x-show="modificando"', $html, 'Guardar solo cuando se está modificando');
        $this->assertStringContainsString("x-on:click=\"modificando = false; \$wire.set('pegado.casa', '', false)\"", $html, 'Cancelar vuelve a bloquear sin viaje');
        $this->assertStringContainsString("x-on:gps-guardado.window=\"if (\$event.detail.tipo === 'casa') modificando = false\"", $html);
        $this->assertStringContainsString('Las coordenadas ya están registradas: pulsa Modificar si hay que cambiarlas.', $html);

        // Al guardar, el servidor avisa para que el navegador vuelva a bloquear ESA dirección.
        Livewire::test(Gps::class, ['id' => $c->id])
            ->set('pegado.casa', '-12.2, -76.8')
            ->call('guardar', 'casa')
            ->assertDispatched('gps-guardado', tipo: 'casa');
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
