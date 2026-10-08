<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Cash\CreateExpense;
use App\Models\Concept;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\User;
use App\Support\Audit;
use App\Support\Auditoria\CapturaAuditoria;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 08/10/2026 (Antony): captura de pantalla en la auditoría de los formularios
 * importantes. El navegador manda la foto (data URL JPEG) con la acción; el
 * servidor la guarda en el disco privado la primera vez que se audita algo en
 * esa petición y la cuelga de todas sus filas (properties.captura); el visor
 * la muestra al director y un comando la purga a los N días.
 */
class AuditoriaCapturaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        CapturaAuditoria::limpiar();
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'captura-tester', 'headquarter_id' => $this->sede->id]);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo(['caja.egresos', 'caja.editar-historico', 'caja.eliminar']);
        $this->actingAs($this->user);
        Activity::query()->delete();
    }

    protected function tearDown(): void
    {
        CapturaAuditoria::limpiar();
        parent::tearDown();
    }

    /** Un JPEG de verdad (4×4), como data URL, igual que lo manda html2canvas. */
    private function fotoJpeg(): string
    {
        $img = imagecreatetruecolor(4, 4);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 0));
        ob_start();
        imagejpeg($img, null, 70);
        $bytes = ob_get_clean();
        imagedestroy($img);

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    public function test_la_foto_que_viaja_con_la_accion_se_guarda_una_vez_y_queda_en_todas_las_filas_de_auditoria(): void
    {
        // Fijos: el egreso de caja 1 y su espejo de caja 3 salen en la misma petición.
        Concept::create(['code' => 'E1', 'name' => 'Diario', 'type' => 'egreso', 'factor_ingreso' => 0, 'factor_egreso' => 0, 'status' => 'active']);
        Activity::query()->delete(); // el alta del concepto deja su propia fila (sin foto)
        $comp = Livewire::test(CreateExpense::class)
            ->set('modo', 'Fijos')->set('reason', 'Diario')->set('detail', 'Pasajes')->set('total', '120.50')
            ->set('capturaAuditoria', $this->fotoJpeg())
            ->assertSet('capturaAuditoria', null) // se vacía en el acto: no viaja de vuelta
            ->call('save');

        $egreso = Expense::where('caja', 1)->firstOrFail();
        $filas = Activity::query()->where('log_name', Audit::LOG)->get();
        $this->assertGreaterThanOrEqual(2, $filas->count(), 'egreso de caja 1 y su espejo de caja 3, como mínimo');

        $rutas = $filas->map(fn ($a) => $a->properties['captura'] ?? null)->unique()->values();
        $this->assertCount(1, $rutas, 'todas las filas de la petición comparten la misma captura');
        $ruta = $rutas->first();
        $this->assertMatchesRegularExpression('#^auditoria/capturas/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#', $ruta);
        Storage::disk('local')->assertExists($ruta);
        $this->assertSame(1, count(Storage::disk('local')->allFiles('auditoria')), 'un solo archivo por petición');
        $this->assertSame((int) $egreso->id, (int) $filas->firstWhere('subject_type', Expense::class)->subject_id);
    }

    public function test_una_foto_invalida_se_ignora_y_la_accion_sale_igual(): void
    {
        foreach (['data:image/png;base64,AAAA', 'data:image/jpeg;base64,bm8tZXMtanBlZw==', 'hola'] as $mala) {
            CapturaAuditoria::limpiar();
            Activity::query()->delete();
            Livewire::test(CreateExpense::class)
                ->set('modo', 'Otros')->set('reason', 'Banco')->set('detail', 'Dep. prueba')->set('total', '10')
                ->set('capturaAuditoria', $mala)
                ->call('save');

            $fila = Activity::query()->where('log_name', Audit::LOG)->where('subject_type', Expense::class)->first();
            $this->assertNotNull($fila, 'la acción se audita igual');
            $this->assertArrayNotHasKey('captura', $fila->properties->toArray());
        }
        $this->assertSame([], Storage::disk('local')->allFiles('auditoria'));
    }

    public function test_sin_foto_no_se_agrega_nada_a_la_auditoria(): void
    {
        Livewire::test(CreateExpense::class)
            ->set('modo', 'Otros')->set('reason', 'Banco')->set('detail', 'Dep. prueba')->set('total', '10')
            ->call('save');

        $fila = Activity::query()->where('log_name', Audit::LOG)->where('subject_type', Expense::class)->firstOrFail();
        $this->assertArrayNotHasKey('captura', $fila->properties->toArray());
        $this->assertArrayHasKey('contexto', $fila->properties->toArray());
    }

    public function test_el_visor_muestra_la_captura_al_director_y_la_ruta_privada_la_sirve_solo_a_el(): void
    {
        Livewire::test(CreateExpense::class)
            ->set('modo', 'Otros')->set('reason', 'Banco')->set('detail', 'Dep. prueba')->set('total', '10')
            ->set('capturaAuditoria', $this->fotoJpeg())
            ->call('save');
        $fila = Activity::query()->where('log_name', Audit::LOG)->where('subject_type', Expense::class)->firstOrFail();

        // Sin rol director: ni el visor ni la imagen.
        $this->get(route('audit.captura', $fila->id))->assertForbidden();

        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        $this->get(route('audit.captura', $fila->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $comp = Livewire::test(AuditIndex::class)->call('ver', $fila->id);
        $detalle = $comp->get('detalle');
        $this->assertSame($fila->properties['captura'], $detalle['captura']);
        $this->assertTrue($detalle['captura_existe']);
        $this->assertStringContainsString('/audit/captura/'.$fila->id, $detalle['captura_url']);
        $comp->assertSeeHtml('Pantalla al confirmar')->assertSeeHtml('alt="Captura de pantalla de la acción"');
        // La ruta no sale como "propiedad" cruda, y la lista marca la fila con la cámara.
        $this->assertSame([], array_filter($detalle['propiedades'], fn ($p) => $p['campo'] === 'Captura'));
        $comp->assertSeeHtml('title="Tiene captura de pantalla"');

        // Si la captura ya se purgó, el visor lo dice y la ruta da 404.
        Storage::disk('local')->delete($fila->properties['captura']);
        Livewire::test(AuditIndex::class)->call('ver', $fila->id)->assertSee('La captura ya fue purgada');
        $this->get(route('audit.captura', $fila->id))->assertNotFound();
    }

    public function test_el_comando_purga_por_mes_completo_y_respeta_los_dias_configurados(): void
    {
        $disco = Storage::disk('local');
        $viejo = now()->subDays(400)->format('Y/m');
        $limite = now()->subDays(190)->format('Y/m');
        $reciente = now()->format('Y/m');
        $disco->put("auditoria/capturas/{$viejo}/a.jpg", 'x');
        $disco->put("auditoria/capturas/{$limite}/b.jpg", 'x');
        $disco->put("auditoria/capturas/{$reciente}/c.jpg", 'x');

        $this->artisan('auditoria:purgar-capturas', ['--dias' => 180, '--dry-run' => true])->assertSuccessful();
        $this->assertCount(3, $disco->allFiles('auditoria'), 'dry-run no borra');

        $this->artisan('auditoria:purgar-capturas', ['--dias' => 180])->assertSuccessful();
        $disco->assertMissing("auditoria/capturas/{$viejo}/a.jpg");
        $disco->assertExists("auditoria/capturas/{$reciente}/c.jpg");
        // El mes de hace ~190 días se borra solo si su último día ya quedó fuera del límite.
        $finMes = Carbon::createFromFormat('Y/m/d', $limite.'/01')->endOfMonth();
        if ($finMes->lessThan(now()->subDays(180)->startOfMonth())) {
            $disco->assertMissing("auditoria/capturas/{$limite}/b.jpg");
        } else {
            $disco->assertExists("auditoria/capturas/{$limite}/b.jpg");
        }
    }

    public function test_los_formularios_importantes_disparan_la_captura_y_el_layout_carga_los_scripts(): void
    {
        $esperado = [
            'resources/views/livewire/payments/create.blade.php' => ["\$captura('pagar', false)", "\$captura('pagar', true)"],
            'resources/views/livewire/cash/create-expense.blade.php' => ["\$captura('save')", 'data-captura'],
            'resources/views/livewire/cash/create-income.blade.php' => ["\$captura('save')", 'data-captura'],
            'resources/views/livewire/cash/edit-expense.blade.php' => ["\$captura('update')"],
            'resources/views/livewire/cash/edit-income.blade.php' => ["\$captura('update')"],
            'resources/views/livewire/clients/documentos.blade.php' => ["\$captura('generar')", "\$captura('generarContrato')", "\$captura('generarAnexo2')", "\$captura('anular', {{ \$doc->id }})"],
            'resources/views/livewire/credits/index.blade.php' => ["\$captura('delete', {{ \$credit->id }})"],
            'resources/views/livewire/credits/create.blade.php' => ["x-on:submit.prevent=\"\$captura('save')\" data-captura"],
            'resources/views/livewire/credits/edit.blade.php' => ["\$captura('update')"],
            'resources/views/livewire/credits/activate.blade.php' => ["\$captura('activate')"],
            'resources/views/livewire/credits/change-status.blade.php' => ["\$captura('changeStatus')"],
            'resources/views/livewire/credits/mass-delete-edit.blade.php' => ["\$captura('reverse')"],
            'resources/views/livewire/clients/create.blade.php' => ["x-on:submit.prevent=\"\$captura('save')\" data-captura"],
            'resources/views/livewire/clients/edit.blade.php' => ["x-on:submit.prevent=\"\$captura('update')\" data-captura"],
        ];
        foreach ($esperado as $archivo => $cadenas) {
            $src = file_get_contents(base_path($archivo));
            foreach ($cadenas as $c) {
                $this->assertStringContainsString($c, $src, "{$archivo} debe llevar {$c}");
            }
        }

        $this->get(route('cash.expenses.create'))
            ->assertOk()
            ->assertSee('assets/js/html2canvas.min.js')
            ->assertSee('assets/js/auditoria-captura.js');
        $this->assertFileExists(public_path('assets/js/html2canvas.min.js'));
    }
}
