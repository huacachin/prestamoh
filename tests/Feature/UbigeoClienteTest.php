<?php

namespace Tests\Feature;

use App\Livewire\Clients\Create;
use App\Livewire\Clients\Edit;
use App\Models\Client;
use App\Models\User;
use App\Support\Ubigeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ubigeo del cliente (03/09): departamento pasa a desplegable LIMA/CALLAO
 * sincronizado con provincia (el par gobierna la frase registral), y el
 * distrito sugiere con datalist los distritos del departamento elegido
 * sin bloquear texto libre.
 */
class UbigeoClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_lima_43_distritos_y_callao_7(): void
    {
        $this->assertCount(43, Ubigeo::distritosDe('LIMA'));
        $this->assertCount(7, Ubigeo::distritosDe('CALLAO'));
        $this->assertContains('SAN JUAN DE LURIGANCHO', Ubigeo::distritosDe('LIMA'));
        $this->assertContains('VENTANILLA', Ubigeo::distritosDe('CALLAO'));
        // Desconocido/vacío: sugiere todos (Lima + Callao) en vez de nada.
        $this->assertCount(50, Ubigeo::distritosDe(null));
        $this->assertCount(50, Ubigeo::distritosDe('JUNIN'));
    }

    public function test_el_select_conserva_un_departamento_historico(): void
    {
        $this->assertSame(['JUNIN', 'LIMA', 'CALLAO'], Ubigeo::departamentosPara('Junin'));
        $this->assertSame(['LIMA', 'CALLAO'], Ubigeo::departamentosPara('LIMA'));
        $this->assertSame(['LIMA', 'CALLAO'], Ubigeo::departamentosPara(null));
    }

    public function test_en_alta_departamento_y_provincia_se_sincronizan(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'ubigeo-tester']));

        Livewire::test(Create::class)
            ->set('departamento', 'CALLAO')
            ->assertSet('provincia', 'CALLAO')
            ->set('provincia', 'LIMA')
            ->assertSet('departamento', 'LIMA');
    }

    public function test_en_edicion_se_sincronizan_y_el_null_hereda_la_provincia(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'ubigeo-tester']));
        $client = Client::create([
            'nombre' => 'Cliente Ubigeo', 'provincia' => 'CALLAO', 'departamento' => null,
        ]);

        Livewire::test(Edit::class, ['id' => $client->id])
            // NULL en BD → el select arranca mostrando lo que se guardará.
            ->assertSet('departamento', 'CALLAO')
            ->set('departamento', 'LIMA')
            ->assertSet('provincia', 'LIMA');
    }
}
