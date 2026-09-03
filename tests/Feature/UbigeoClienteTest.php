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
 * Ubigeo del cliente (03/09): cascada Departamento → Provincia → Distrito
 * con el catálogo completo (Lima: 10 provincias / 171 distritos; Callao),
 * valores en mayúscula/minúscula y selects tipo select2 con búsqueda. Los
 * contratos no cambian: DomicilioLegal pasa todo a mayúsculas y la frase
 * registral ya contempla el caso genérico provincia≠departamento.
 */
class UbigeoClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_completo_lima_10_provincias_171_distritos_callao_7(): void
    {
        $this->assertSame(['Lima', 'Callao'], Ubigeo::departamentos());
        $this->assertCount(10, Ubigeo::provinciasDe('Lima'));
        $this->assertContains('Cañete', Ubigeo::provinciasDe('Lima'));
        $this->assertContains('Huaral', Ubigeo::provinciasDe('Lima'));

        $this->assertCount(43, Ubigeo::distritosDe('Lima', 'Lima'));
        $this->assertCount(16, Ubigeo::distritosDe('Lima', 'Cañete'));
        $this->assertSame(171, collect(Ubigeo::UBIGEO['Lima'])->flatten()->count());
        $this->assertCount(7, Ubigeo::distritosDe('Callao', 'Callao'));

        // Mayúscula/minúscula en los valores…
        $this->assertContains('San Juan de Lurigancho', Ubigeo::distritosDe('Lima', 'Lima'));
        $this->assertContains('Lunahuaná', Ubigeo::distritosDe('Lima', 'Cañete'));
        // …pero lookups tolerantes al casing del historial migrado ("LIMA").
        $this->assertCount(43, Ubigeo::distritosDe('LIMA', 'LIMA'));
        $this->assertSame('Cañete', Ubigeo::resolverProvincia('LIMA', 'CAÑETE'));
    }

    public function test_buscar_provincia_ubica_departamento_y_casing(): void
    {
        $this->assertSame(['Lima', 'Cañete'], Ubigeo::buscarProvincia('CAÑETE'));
        $this->assertSame(['Lima', 'Huaral'], Ubigeo::buscarProvincia('huaral'));
        $this->assertSame(['Callao', 'Callao'], Ubigeo::buscarProvincia('CALLAO'));
        $this->assertNull(Ubigeo::buscarProvincia('AREQUIPA'));
        $this->assertNull(Ubigeo::buscarProvincia(null));
    }

    public function test_con_historico_conserva_valores_fuera_del_catalogo(): void
    {
        $this->assertSame(['JUNIN', 'Lima', 'Callao'], Ubigeo::conHistorico(Ubigeo::departamentos(), 'JUNIN'));
        // Un valor del catálogo (en cualquier casing) no se duplica.
        $this->assertSame(['Lima', 'Callao'], Ubigeo::conHistorico(Ubigeo::departamentos(), 'LIMA'));
        $this->assertSame(['Lima', 'Callao'], Ubigeo::conHistorico(Ubigeo::departamentos(), null));
    }

    public function test_en_alta_la_cascada_resetea_provincia_y_limpia_distrito_ajeno(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'ubigeo-tester']));

        Livewire::test(Create::class)
            ->set('provincia', 'Cañete')
            ->set('distrito', 'Lunahuaná')
            ->set('departamento', 'Callao')     // Cañete no es del Callao…
            ->assertSet('provincia', 'Callao')  // …resetea a la capital
            ->assertSet('distrito', null)       // …y Lunahuaná tampoco pertenece
            ->set('departamento', 'Lima')
            ->assertSet('provincia', 'Lima');
    }

    public function test_cambiar_provincia_conserva_el_distrito_si_le_pertenece(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'ubigeo-tester']));

        Livewire::test(Create::class)
            ->set('distrito', 'Ate')
            ->set('provincia', 'Huaral')       // Ate no es de Huaral → se limpia
            ->assertSet('distrito', null)
            ->set('distrito', 'Chancay')
            ->set('provincia', 'Huaral')       // sin cambio real: Chancay pertenece
            ->assertSet('distrito', 'Chancay');
    }

    public function test_en_edicion_el_historial_en_mayusculas_se_resuelve_al_catalogo(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'ubigeo-tester']));
        $client = Client::create([
            'nombre' => 'Cliente Ubigeo', 'provincia' => 'CALLAO', 'departamento' => null,
        ]);

        Livewire::test(Edit::class, ['id' => $client->id])
            // "CALLAO" migrado → catálogo, y el departamento NULL hereda el
            // de la provincia: lo que muestra el select es lo que se guardará.
            ->assertSet('departamento', 'Callao')
            ->assertSet('provincia', 'Callao')
            ->set('departamento', 'Lima')
            ->assertSet('provincia', 'Lima');
    }
}
