<?php

namespace Tests\Feature;

use App\Livewire\Clients\Edit;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Audit;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): en /clients/{id}/edit se quita el resumen de arriba y
 * los copropietarios se listan como un acordeón: el nombre de cada uno y, al
 * pulsar +, el formulario para editar solo los datos que piden los documentos.
 */
class ClienteEditCopropietariosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $titular;

    private Client $copro;

    private Client $otroTitular;

    private function mundo(array $permisos = ['clientes']): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'copro-editor', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo($permisos);

        $this->titular = Client::create([
            'expediente' => '1404', 'nombre' => 'Carla', 'apellido_pat' => 'Ore', 'apellido_mat' => 'Barrientos',
            'tipo_documento' => 'DNI', 'documento' => '41001468', 'sexo' => 'F', 'status' => 'active', 'headquarter_id' => $sede->id,
        ]);
        $this->copro = Client::create([
            'nombre' => 'Joselyn', 'apellido_pat' => 'Escobar', 'apellido_mat' => 'Suma', 'tipo_documento' => 'DNI', 'documento' => '41001467',
            'sexo' => 'F', 'status' => 'active', 'es_relacionado' => true, 'headquarter_id' => $sede->id,
            'direccion' => 'Jr. Los Pinos 123', 'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'email' => 'joselyn@example.com', 'celular1' => '999111222', 'ocupacion' => 'transportista', 'estado_civil' => 'soltero', 'nacionalidad' => 'PERUANO',
        ]);
        $this->otroTitular = Client::create([
            'expediente' => '1405', 'nombre' => 'Pedro', 'apellido_pat' => 'Solo', 'apellido_mat' => 'Uno', 'tipo_documento' => 'DNI', 'documento' => '41001469',
            'sexo' => 'M', 'status' => 'active', 'headquarter_id' => $sede->id, 'direccion' => 'Av. Uno 1', 'distrito' => 'LIMA', 'provincia' => 'LIMA',
            'email' => 'pedro@example.com', 'celular1' => '999000111', 'ocupacion' => 'transportista', 'estado_civil' => 'soltero', 'nacionalidad' => 'PERUANO',
        ]);
        $v1 = Vehiculo::create(['client_id' => $this->titular->id, 'placa' => 'D8M235', 'marca' => 'BMW', 'valor' => 20000]);
        $v2 = Vehiculo::create(['client_id' => $this->titular->id, 'placa' => 'AWI132', 'marca' => 'NISSAN', 'valor' => 15000]);
        $v1->copropietarios()->attach($this->copro->id, ['rol' => 'copropietario']);
        $v2->copropietarios()->attach($this->copro->id, ['rol' => 'copropietario']);
        $v2->copropietarios()->attach($this->otroTitular->id, ['rol' => 'copropietario']);
    }

    public function test_sin_resumen_arriba_y_los_copropietarios_como_acordeon_cerrado(): void
    {
        $this->mundo();
        $html = Livewire::test(Edit::class, ['id' => $this->titular->id])->html();

        $this->assertStringNotContainsString('resumen-linea', $html);
        $this->assertStringNotContainsString('resumen-cliente', $html);
        $this->assertStringNotContainsString('badge rounded-pill', $html, 'sin globitos en las pestañas');
        $this->assertStringNotContainsString('tabla-copropietarios', $html, 'ya no es una tabla');

        $this->assertStringContainsString('class="acordeon-copro mb-2" x-on:keydown.enter.prevent', $html);
        $this->assertSame(2, substr_count($html, 'class="d-flex align-items-center gap-2 px-2 py-1 copro-cabecera"'), 'una cabecera por copropietario');
        $this->assertStringContainsString("wire:click=\"abrirCopro({$this->copro->id})\"", $html);
        $this->assertStringContainsString('Escobar Suma Joselyn</b>', $html);
        $this->assertStringContainsString('DNI 41001467 · vehículos D8M235, AWI132', $html);
        $this->assertStringContainsString('Solo Uno Pedro</b>', $html);
        $this->assertStringContainsString('DNI 41001469 · vehículo AWI132', $html);
        $this->assertSame(2, substr_count($html, '<i class="ti ti-plus"></i>'), 'cerrados: todos con +');
        $this->assertStringNotContainsString('wire:model="coproForm.', $html, 'sin formulario hasta pulsar +');

        // Sin copropietarios: "ninguno" con atajo a Vehículos.
        $solo = Livewire::test(Edit::class, ['id' => $this->otroTitular->id])->html();
        $this->assertStringContainsString('agregar desde Vehículos', $solo);
        $this->assertStringContainsString('Es copropietario en:', $solo, 'Pedro es copropietario del AWI132 de Carla');
    }

    public function test_el_mas_abre_el_formulario_con_sus_datos_y_guardar_actualiza_solo_a_esa_persona(): void
    {
        $this->mundo();
        $comp = Livewire::test(Edit::class, ['id' => $this->titular->id])
            ->call('abrirCopro', $this->copro->id)
            ->assertSet('coproAbierto', $this->copro->id)
            ->assertSet('coproForm.documento', '41001467')
            ->assertSet('coproForm.nombre', 'Joselyn')
            ->assertSet('coproForm.direccion', 'Jr. Los Pinos 123')
            ->assertSet('coproForm.celular1', '999111222');
        $html = $comp->html();
        $this->assertStringContainsString('wire:model="coproForm.direccion"', $html);
        $this->assertStringContainsString('<i class="ti ti-minus"></i>', $html, 'abierto: el botón pasa a −');
        $this->assertStringContainsString('Guardar copropietario', $html);
        // Persona relacionada: su identidad se puede editar desde aquí (no hay candado).
        $this->assertStringNotContainsString('Es cliente titular: su documento', $html);

        // Validación: correo obligatorio y documento único.
        $comp->set('coproForm.email', '')->call('guardarCopro')->assertHasErrors(['coproForm.email']);
        $comp->set('coproForm.email', 'joselyn@example.com')->set('coproForm.documento', '41001468')->call('guardarCopro')->assertHasErrors(['coproForm.documento']);

        // Guardar: solo cambia a Joselyn, con rastro en auditoría, y el acordeón se cierra.
        Activity::query()->delete();
        $comp->set('coproForm.documento', '41001467')->set('coproForm.direccion', 'Av. Nueva 456')->set('coproForm.distrito', 'santa anita')
            ->set('coproForm.celular1', '988777666')->set('coproForm.estado_civil', 'casado')
            ->call('guardarCopro')
            ->assertHasNoErrors()
            ->assertDispatched('successAlert')
            ->assertSet('coproAbierto', null);
        $j = $this->copro->fresh();
        $this->assertSame(['Av. Nueva 456', 'SANTA ANITA', '988777666', 'casado'], [$j->direccion, $j->distrito, $j->celular1, $j->estado_civil]);
        $this->assertSame('Ore', $this->titular->fresh()->apellido_pat, 'el titular no se toca');
        $this->assertSame(1, Activity::where('log_name', Audit::LOG)->where('subject_id', $j->id)->where('description', 'like', 'Editó los datos de Escobar Suma Joselyn (copropietario)%')->count());

        // Pulsar + otra vez sobre el abierto lo cierra.
        $comp->call('abrirCopro', $this->copro->id)->assertSet('coproAbierto', $this->copro->id)
            ->call('abrirCopro', $this->copro->id)->assertSet('coproAbierto', null);
    }

    public function test_si_el_copropietario_es_un_cliente_titular_su_identidad_queda_bloqueada_sin_el_permiso(): void
    {
        $this->mundo();
        $comp = Livewire::test(Edit::class, ['id' => $this->titular->id])->call('abrirCopro', $this->otroTitular->id);
        $html = $comp->html();
        $this->assertStringContainsString('Es cliente titular: su documento, nombres, sexo y nacionalidad se editan desde su ficha', $html);
        $this->assertMatchesRegularExpression('/wire:model="coproForm\.documento"\s+disabled/', $html);

        // Aunque se mande otro nombre, se respeta el de la ficha; la dirección sí se guarda.
        $comp->set('coproForm.nombre', 'Hackeado')->set('coproForm.direccion', 'Av. Dos 2')->call('guardarCopro')->assertHasNoErrors();
        $p = $this->otroTitular->fresh();
        $this->assertSame('Pedro', $p->nombre);
        $this->assertSame('Av. Dos 2', $p->direccion);

        // Con el permiso de identidad sí se edita.
        $this->user->givePermissionTo('clientes.editar-identidad');
        Livewire::test(Edit::class, ['id' => $this->titular->id])->call('abrirCopro', $this->otroTitular->id)
            ->set('coproForm.nombre', 'Pedro Pablo')->call('guardarCopro')->assertHasNoErrors();
        $this->assertSame('Pedro Pablo', $this->otroTitular->fresh()->nombre);
    }

    public function test_sin_permiso_de_guardar_no_se_guarda_nada(): void
    {
        $this->mundo(['clientes', 'clientes.scope-propio']);
        $this->titular->update(['asesor_id' => $this->user->id]);
        Livewire::test(Edit::class, ['id' => $this->titular->id])
            ->call('abrirCopro', $this->copro->id)
            ->set('coproForm.direccion', 'Otra')
            ->call('guardarCopro')
            ->assertDispatched('errorAlert');
        $this->assertSame('Jr. Los Pinos 123', $this->copro->fresh()->direccion);
    }
}
