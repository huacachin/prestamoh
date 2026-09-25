<?php

namespace Tests\Feature;

use App\Livewire\Cash\EditExpense;
use App\Livewire\Cash\ExpenseGallery;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 26/09/2026: en editar egreso las imágenes se eligen en el formulario y se
 * suben con el MISMO "Guardar cambios" (antes: un campo suelto de una imagen
 * y otro botón "Subir" aparte en la galería de abajo).
 */
class EditarEgresoConImagenesTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private Expense $egreso;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->director = User::factory()->create(['username' => 'director-img', 'headquarter_id' => 1]);
        foreach (['caja.editar-historico', 'caja.eliminar', 'caja.egresos'] as $p) {
            $this->director->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->director);

        $this->egreso = Expense::create([
            'date' => now()->format('Y-m-d'), 'reason' => 'Egreso Prueba', 'detail' => 'test',
            'total' => 100, 'modo' => 'Otros', 'caja' => 1, 'user_id' => $this->director->id, 'headquarter_id' => 1,
        ]);
        Storage::fake('public');
    }

    public function test_guardar_cambios_sube_las_imagenes_elegidas_en_el_mismo_clic(): void
    {
        Livewire::test(EditExpense::class, ['id' => $this->egreso->id])
            ->set('detail', 'detalle nuevo')
            ->set('files', [UploadedFile::fake()->image('voucher1.jpg', 800, 600), UploadedFile::fake()->image('voucher2.png', 400, 400)])
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('cash.expenses'));

        $this->assertSame('detalle nuevo', $this->egreso->fresh()->detail);
        $adjuntos = ExpenseAttachment::where('expense_id', $this->egreso->id)->get();
        $this->assertCount(2, $adjuntos);
        foreach ($adjuntos as $a) {
            Storage::disk('public')->assertExists($a->path);
        }
        $this->assertEqualsCanonicalizing(['voucher1.jpg', 'voucher2.png'], $adjuntos->pluck('original_name')->all());
        $this->assertStringContainsString('2 imágenes subidas', session('cash_success'));
    }

    public function test_sin_imagenes_guarda_igual_que_siempre(): void
    {
        Livewire::test(EditExpense::class, ['id' => $this->egreso->id])
            ->set('total', '150')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('cash.expenses'));

        $this->assertEqualsWithDelta(150.0, (float) $this->egreso->fresh()->total, 0.001);
        $this->assertSame(0, ExpenseAttachment::where('expense_id', $this->egreso->id)->count());
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza_y_no_se_guarda_nada(): void
    {
        Livewire::test(EditExpense::class, ['id' => $this->egreso->id])
            ->set('detail', 'no debe guardarse')
            ->set('files', [UploadedFile::fake()->create('voucher.pdf', 100, 'application/pdf')])
            ->call('update')
            ->assertHasErrors(['files.0']);

        $this->assertSame('test', $this->egreso->fresh()->detail);
        $this->assertSame(0, ExpenseAttachment::where('expense_id', $this->egreso->id)->count());
    }

    public function test_el_formulario_tiene_la_zona_de_imagenes_y_un_solo_boton_de_guardar(): void
    {
        $comp = Livewire::test(EditExpense::class, ['id' => $this->egreso->id]);
        $comp->assertSeeHtml('wire:model="files"')
            ->assertSee('Guardar cambios')
            ->assertDontSeeHtml('wire:model="image"');

        // Con imágenes elegidas el botón lo dice: no hace falta otro botón "Subir".
        $comp->set('files', [UploadedFile::fake()->image('a.jpg')])
            ->assertSee('Guardar cambios y subir 1 imagen');
    }

    public function test_la_galeria_embebida_solo_lista_y_no_sube(): void
    {
        ExpenseAttachment::create([
            'expense_id' => $this->egreso->id, 'filename' => 'x.jpg', 'original_name' => 'existente.jpg',
            'path' => "expenses/{$this->egreso->id}/x.jpg", 'mime' => 'image/jpeg', 'size' => 10, 'uploaded_by' => 'director-img',
        ]);

        Livewire::test(ExpenseGallery::class, ['id' => $this->egreso->id, 'embedded' => true])
            ->assertSee('existente.jpg')
            ->assertDontSeeHtml('wire:submit.prevent="save"')
            ->assertDontSeeHtml('wire:model="files"');

        // La galería a pantalla completa conserva su propia subida.
        Livewire::test(ExpenseGallery::class, ['id' => $this->egreso->id])
            ->assertSeeHtml('wire:submit.prevent="save"')
            ->assertSeeHtml('wire:model="files"');
    }
}
