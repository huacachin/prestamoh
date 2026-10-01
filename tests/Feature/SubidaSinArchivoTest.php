<?php

namespace Tests\Feature;

use App\Support\ConSubidaDeArchivos;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 30/09/2026 22:02: la conexión de una usuaria se cortó mientras subía el
 * voucher de un cobro. El servidor no recibió el archivo, Livewire devolvió
 * una lista de rutas vacía y _finishUpload reventó con "Undefined array key 0"
 * (500). ConSubidaDeArchivos lo convierte en un error de validación.
 */
class SubidaSinArchivoTest extends TestCase
{
    private function componente(): Component
    {
        return new class extends Component
        {
            use ConSubidaDeArchivos;

            public $foto = null;

            public $fotos = [];

            public function render()
            {
                return '<div></div>';
            }
        };
    }

    public function test_subida_simple_que_llega_vacia_da_error_de_validacion_en_vez_de_500(): void
    {
        Livewire::test($this->componente())
            ->call('_finishUpload', 'foto', [], false)
            ->assertHasErrors(['foto'])
            ->assertDispatched('upload:errored', name: 'foto')
            ->assertSet('foto', null);
    }

    public function test_subida_multiple_que_llega_vacia_tambien_avisa(): void
    {
        Livewire::test($this->componente())
            ->call('_finishUpload', 'fotos', [], true)
            ->assertHasErrors(['fotos'])
            ->assertDispatched('upload:errored', name: 'fotos')
            ->assertSet('fotos', []);
    }

    public function test_el_mensaje_explica_que_se_corto_la_conexion(): void
    {
        $c = Livewire::test($this->componente())->call('_finishUpload', 'foto', [], false);

        $this->assertSame(
            'No se recibió el archivo: la conexión se cortó durante la subida. Inténtalo de nuevo.',
            $c->errors()->first('foto')
        );
    }

    public function test_una_subida_normal_sigue_llegando_a_la_propiedad(): void
    {
        $c = Livewire::test($this->componente())
            ->set('foto', UploadedFile::fake()->image('voucher.jpg'))
            ->assertHasNoErrors()
            ->assertNotDispatched('upload:errored');

        $this->assertInstanceOf(TemporaryUploadedFile::class, $c->get('foto'));
    }

    public function test_una_subida_multiple_normal_sigue_funcionando(): void
    {
        $c = Livewire::test($this->componente())
            ->set('fotos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
            ->assertHasNoErrors();

        $this->assertCount(2, $c->get('fotos'));
        $this->assertContainsOnlyInstancesOf(TemporaryUploadedFile::class, $c->get('fotos'));
    }
}
