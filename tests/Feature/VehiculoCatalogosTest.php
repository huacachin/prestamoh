<?php

namespace Tests\Feature;

use App\Livewire\Clients\Vehiculos;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\VehiculoOpcion;
use App\Support\Combustibles;
use App\Support\VehiculoCatalogos;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 13/10/2026 (Antony): en los formularios de vehículo, Categoría, Carrocería,
 * Color y Combustible dejan de ser listas fijas: lo que se escribe nuevo
 * ("Negro mate") se guarda en vehiculo_opciones y sale la próxima vez en todos
 * los altas. Las listas de siempre son el arranque.
 */
class VehiculoCatalogosTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_listas_de_siempre_arrancan_el_catalogo_y_lo_guardado_fuera_se_conserva_como_opcion(): void
    {
        // 26/09: PICK UP faltaba en el alta de cliente y en la placa.
        $this->assertContains('PICK UP', VehiculoCatalogos::opciones('carroceria'));
        $this->assertSame(['M1', 'M2', 'M2-C3', 'N1'], VehiculoCatalogos::opciones('categoria'));
        $this->assertContains('BI-COMBUSTIBLE GNV', VehiculoCatalogos::opciones('combustible'));

        // Un valor guardado fuera del catálogo (API de placa, histórico) se conserva como opción, al inicio.
        $this->assertSame('METROPOLITANO', VehiculoCatalogos::paraValor('carroceria', 'METROPOLITANO')[0]);
        // Lo que ya está en el catálogo no se duplica, aunque venga en otra caja.
        $this->assertSame(1, count(array_filter(VehiculoCatalogos::paraValor('carroceria', 'pick up'), fn ($o) => mb_strtoupper($o) === 'PICK UP')));
        $this->assertSame(VehiculoCatalogos::opciones('color'), VehiculoCatalogos::paraValor('color', ''));
    }

    public function test_lo_nuevo_se_guarda_y_sale_la_proxima_vez_sin_repetirse(): void
    {
        VehiculoCatalogos::recordar('color', '  Negro mate ');
        VehiculoCatalogos::recordar('color', 'negro MATE'); // misma opción en otra caja: no se repite
        VehiculoCatalogos::recordar('color', 'Blanco');      // ya está en la base: no se guarda otra vez
        VehiculoCatalogos::recordar('color', '   ');         // vacío: nada
        VehiculoCatalogos::recordar('carroceria', 'HATCHBACK');

        // La migración siembra las listas de siempre; lo nuevo se suma una sola vez y lo vacío no entra.
        $this->assertSame(1, VehiculoOpcion::where('tipo', 'color')->whereRaw('UPPER(valor) = ?', ['NEGRO MATE'])->count());
        $this->assertSame('Negro mate', VehiculoOpcion::where('tipo', 'color')->whereRaw('UPPER(valor) = ?', ['NEGRO MATE'])->value('valor'), 'se guarda como se escribió, recortada');
        $this->assertSame(1, VehiculoOpcion::where('tipo', 'color')->where('valor', 'Blanco')->count());
        $this->assertSame(1, VehiculoOpcion::where('tipo', 'carroceria')->where('valor', 'HATCHBACK')->count());
        $this->assertSame(0, VehiculoOpcion::where('valor', '')->count());
        // Sale después de las de siempre, y una sola vez.
        $colores = VehiculoCatalogos::opciones('color');
        $this->assertSame(['Rojo', 'Gris', 'Blanco', 'Negro', 'Plata', 'Negro mate'], $colores);
        $this->assertContains('HATCHBACK', VehiculoCatalogos::opciones('carroceria'));
        $this->assertNotContains('HATCHBACK', VehiculoCatalogos::opciones('color'), 'cada catálogo por su lado');

        // Las guardadas van en orden alfabético después de las de siempre.
        VehiculoCatalogos::recordar('color', 'Azul');
        $this->assertSame(['Rojo', 'Gris', 'Blanco', 'Negro', 'Plata', 'Azul', 'Negro mate'], VehiculoCatalogos::opciones('color'));
    }

    public function test_el_alta_de_vehiculo_en_la_ficha_guarda_lo_escrito_y_el_siguiente_formulario_lo_ofrece(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'catalogo-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['clientes']);
        $client = Client::create([
            'expediente' => '1500', 'nombre' => 'Con', 'apellido_pat' => 'Vehiculo', 'apellido_mat' => 'Nuevo',
            'tipo_documento' => 'DNI', 'documento' => '41500001', 'sexo' => 'M', 'status' => 'active', 'headquarter_id' => $sede->id, 'asesor_id' => $user->id,
        ]);

        Livewire::test(Vehiculos::class, ['id' => $client->id])
            ->call('nuevo')
            ->set('placa', 'XYZ123')->set('marca', 'KIA')->set('modelo', 'RIO')->set('valor', 15000)
            ->set('nro_motor', 'G4FA123')->set('nro_serie', 'KNA123')->set('anio_modelo', '2019')
            ->set('categoria', 'M1')->set('carroceria', 'Hatchback')->set('color', 'Negro mate')->set('combustible', 'Gasolina / GLP')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('msgType', 'ok');

        foreach (['carroceria' => 'Hatchback', 'color' => 'Negro mate', 'combustible' => 'Gasolina / GLP'] as $tipo => $valor) {
            $this->assertSame(1, VehiculoOpcion::where('tipo', $tipo)->where('valor', $valor)->count(), "{$tipo}: lo nuevo se guarda una vez");
        }
        $this->assertSame(1, VehiculoOpcion::where('tipo', 'categoria')->where('valor', 'M1')->count(), 'M1 ya estaba: no se repite');

        // El siguiente formulario (de otro cliente) ya ofrece las opciones nuevas en sus selects.
        $otro = Client::create([
            'expediente' => '1501', 'nombre' => 'Otro', 'apellido_pat' => 'Cliente', 'apellido_mat' => 'X',
            'tipo_documento' => 'DNI', 'documento' => '41500002', 'sexo' => 'M', 'status' => 'active', 'headquarter_id' => $sede->id, 'asesor_id' => $user->id,
        ]);
        $html = Livewire::test(Vehiculos::class, ['id' => $otro->id])->call('nuevo')->html();
        $this->assertStringContainsString('<option value="Negro mate">Negro mate</option>', $html);
        $this->assertStringContainsString('<option value="Hatchback">Hatchback</option>', $html);
        $this->assertStringContainsString('<option value="Gasolina / GLP">Gasolina / GLP</option>', $html);
        $this->assertSame(1, substr_count($html, '<option value="Negro">Negro</option>'), 'las de siempre siguen, una vez');

        // El alta de cliente (asistente) también lee del catálogo y lo alimenta al guardar sus vehículos.
        $alta = file_get_contents(resource_path('views/livewire/clients/create.blade.php'));
        foreach (VehiculoCatalogos::TIPOS as $tipo) {
            $this->assertStringContainsString("VehiculoCatalogos::paraValor('{$tipo}', ", $alta, $tipo);
        }
        $this->assertStringContainsString('VehiculoCatalogos::recordarDe($v);', file_get_contents(app_path('Livewire/Clients/Create.php')));
        $this->assertFalse(class_exists(Combustibles::class, false), 'Combustibles se fundió en VehiculoCatalogos');
    }
}
