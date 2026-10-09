<?php

namespace Tests\Feature;

use App\Livewire\Cash\Opening;
use App\Models\CashOpening;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): en /cash/opening el clic en Editar tardaba 2-3 s en
 * producción en mostrar el input: cada clic iba al servidor y volvía con la
 * pantalla entera (123 aperturas pintadas dos veces, tabla + tarjetas: 350 KB).
 * Ahora el input se abre y se cierra en el navegador (Alpine), solo Guardar
 * viaja, y se pinta una sola lista según la pantalla ($movil).
 */
class AperturaCajaEdicionEnNavegadorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function mundo(array $permisos = ['caja.apertura', 'caja.editar-historico']): CashOpening
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'apertura-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo($permisos);

        return CashOpening::create(['fecha' => '2026-08-01', 'hora' => '08:00', 'saldo_inicial' => 1000, 'moneda' => 'Soles', 'user_id' => $this->user->id, 'headquarter_id' => $sede->id]);
    }

    public function test_editar_y_cancelar_no_van_al_servidor_y_solo_se_pinta_la_lista_que_se_ve(): void
    {
        $ap = $this->mundo();
        $comp = Livewire::test(Opening::class);
        $html = $comp->html();

        // Los métodos que redibujaban todo ya no existen.
        $this->assertFalse(method_exists(Opening::class, 'startEdit'));
        $this->assertFalse(method_exists(Opening::class, 'cancelEdit'));
        $this->assertStringNotContainsString('startEdit(', $html);

        // Editar es local (Alpine): abre el input de ESA fila y le pone el importe actual.
        $this->assertStringContainsString("x-on:click=\"editando = {$ap->id}; valor = '1000.00'; \$nextTick(() => \$refs['importe{$ap->id}'].focus())\"", $html);
        $this->assertStringContainsString("x-show=\"editando === {$ap->id}\" x-cloak x-model=\"valor\" x-ref=\"importe{$ap->id}\"", $html);
        $this->assertStringContainsString("x-on:click=\"\$wire.updateInline({$ap->id}, valor)\"", $html, 'solo Guardar viaja, con el valor');
        $this->assertStringContainsString('x-on:keydown.escape="editando = null"', $html);
        $this->assertStringContainsString('x-on:apertura-guardada.window="editando = null"', $html, 'al guardar se cierra el input');
        $this->assertStringContainsString('wire:loading.attr="disabled" wire:target="updateInline"', $html, 'señal de "guardando"');
        $this->assertStringContainsString("wire:key=\"apertura-{$ap->id}\"", $html);

        // Escritorio: tabla sí, tarjetas no. Celular: al revés.
        $this->assertStringContainsString('id="tabla-apertura"', $html);
        $this->assertStringNotContainsString('card mb-2 shadow-sm', $html);
        $movil = $comp->set('movil', true)->html();
        $this->assertStringNotContainsString('id="tabla-apertura"', $movil);
        $this->assertStringContainsString('card mb-2 shadow-sm', $movil);
        $this->assertStringContainsString("x-ref=\"importe{$ap->id}\"", $movil, 'en celular también se puede editar (antes el botón no abría nada)');

        // Los atributos de Alpine llegan enteros (sin comillas dobles dentro).
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $boton = $xp->query('//button[@title="Editar importe"]')->item(0);
        $this->assertNotNull($boton);
        $this->assertStringEndsWith("\$refs['importe{$ap->id}'].focus())", $boton->getAttribute('x-on:click'));
    }

    public function test_guardar_valida_en_el_servidor_y_avisa_al_navegador(): void
    {
        $ap = $this->mundo();

        Livewire::test(Opening::class)
            ->call('updateInline', $ap->id, '1,250.50')
            ->assertDispatched('successAlert')
            ->assertDispatched('apertura-guardada')
            ->assertNotDispatched('errorAlert');
        $this->assertEqualsWithDelta(1250.50, (float) $ap->fresh()->saldo_inicial, 0.001);

        Livewire::test(Opening::class)
            ->call('updateInline', $ap->id, 'abc')
            ->assertDispatched('errorAlert')
            ->assertNotDispatched('apertura-guardada');
        $this->assertEqualsWithDelta(1250.50, (float) $ap->fresh()->saldo_inicial, 0.001, 'un importe inválido no toca nada');
    }

    public function test_sin_permiso_de_historico_no_hay_botones_ni_se_guarda(): void
    {
        $ap = $this->mundo(['caja.apertura']);
        $comp = Livewire::test(Opening::class);
        $this->assertStringNotContainsString('Editar importe', $comp->html());
        $this->assertStringNotContainsString('x-ref="importe', $comp->html());

        $comp->call('updateInline', $ap->id, '5')->assertDispatched('errorAlert');
        $this->assertEqualsWithDelta(1000, (float) $ap->fresh()->saldo_inicial, 0.001);
    }
}
