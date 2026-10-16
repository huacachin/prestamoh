<?php

namespace Tests\Feature;

use App\Livewire\Monitoreo\Sesiones;
use App\Models\User;
use App\Support\Audit;
use App\Support\MenuLateral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 16/10/2026 (Antony): módulo Monitoreo, SOLO director: todas las sesiones
 * de todos los usuarios, un bloque por usuario (título con el usuario, tabla
 * de sesiones abiertas y de inicios de sesión) y la posibilidad de cerrarlas.
 */
class MonitoreoSesionesTest extends TestCase
{
    use RefreshDatabase;

    private const UA_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36';

    private const UA_ANDROID = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';

    private User $director;

    private User $licet;

    private User $rosa;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('director', 'web');
        Role::findOrCreate('administrador', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->director = User::factory()->create(['username' => 'Guilmer', 'name' => 'Guilmer Huacachin']);
        $this->director->syncRoles(['director']);
        $this->licet = User::factory()->create(['username' => 'Licet', 'name' => 'Licet Tafur']);
        $this->licet->syncRoles(['administrador']);
        $this->rosa = User::factory()->create(['username' => 'Rosa', 'name' => 'Rosa Tafur']);
        $this->rosa->syncRoles(['administrador']);
        config(['session.driver' => 'database']);
    }

    private function sesion(string $id, User $u, string $ip, string $ua, int $haceMinutos): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $u->id, 'ip_address' => $ip, 'user_agent' => $ua, 'payload' => '', 'last_activity' => now()->subMinutes($haceMinutos)->timestamp]);
    }

    public function test_solo_el_director_entra_y_el_menu_solo_se_lo_muestra_a_el(): void
    {
        $this->get(route('monitoreo.index'))->assertRedirect(route('login'));
        $this->actingAs($this->licet)->get(route('monitoreo.index'))->assertForbidden();

        $html = $this->actingAs($this->director)->get(route('monitoreo.index'))->assertOk()->getContent();
        $this->assertStringContainsString('MONITOREO', $html);
        $this->assertStringContainsString('href="'.route('monitoreo.index').'"', $html, 'el menú lateral lleva a Monitoreo');

        $item = collect(MenuLateral::items())->firstWhere('id', 'monitoreo');
        $this->assertSame('director', $item['role']);
        $this->assertStringNotContainsString(route('monitoreo.index'), $this->actingAs($this->licet)->get(route('perfil'))->getContent(), 'la administradora no lo ve en el menú');
    }

    public function test_un_bloque_por_usuario_con_sus_sesiones_sus_inicios_y_sus_intentos_fallidos(): void
    {
        $this->actingAs($this->director);
        $this->sesion((string) session()->getId(), $this->director, '209.61.122.152', self::UA_WIN, 0);
        $this->sesion('licet-pc', $this->licet, '209.61.122.152', self::UA_WIN, 2);
        $this->sesion('licet-cel', $this->licet, '179.6.137.162', self::UA_ANDROID, 40);
        // Inicios de sesión y un intento fallido de Licet, en la auditoría.
        $this->actingAs($this->licet);
        Audit::log('Inicio de sesión');
        Audit::log('Intento de inicio de sesión fallido', null, ['username' => 'licet']);
        Audit::log('Intento de inicio de sesión fallido', null, ['username' => 'Licet']);
        $this->actingAs($this->director);

        $comp = Livewire::test(Sesiones::class);
        $bloques = $comp->instance()->bloques();
        // Primero quien más sesiones tiene, luego por nombre; Rosa sin sesiones al final.
        $this->assertSame(['Licet', 'Guilmer', 'Rosa'], $bloques->pluck('usuario.username')->all());
        $licet = $bloques->firstWhere('usuario.username', 'Licet');
        $this->assertSame(2, $licet['sesiones']->count());
        $this->assertSame(['licet-pc', 'licet-cel'], $licet['sesiones']->pluck('id')->all(), 'la más reciente primero');
        $this->assertTrue($licet['sesiones'][0]['activa'], 'con actividad hace 2 min');
        $this->assertFalse($licet['sesiones'][1]['activa'], 'sin actividad hace 40 min');
        $this->assertSame(1, $licet['inicios']->count());
        $this->assertSame(2, $licet['fallidos']);

        $html = $comp->html();
        $this->assertStringContainsString('Licet', $html);
        $this->assertStringContainsString('2 sesión(es) abierta(s)', $html);
        $this->assertStringContainsString('Celular · Android', $html);
        $this->assertStringContainsString('179.6.137.162', $html);
        $this->assertStringContainsString('2 fallido(s)', $html);
        $this->assertStringContainsString('Esta sesión', $html, 'la del director que mira');
        $this->assertStringContainsString("wire:click=\"cerrarTodas({$this->licet->id})\"", $html);
        $this->assertStringContainsString("wire:click=\"cerrarSesion('licet-cel')\"", $html);
        $this->assertStringNotContainsString("wire:click=\"cerrarTodas({$this->director->id})\"", $html, 'el director solo tiene su sesión actual: nada que cerrar');
        $this->assertStringContainsString('2 usuario(s) con sesión abierta', $html, 'Licet y el propio director');
        $this->assertStringContainsString('3 sesión(es) en total', $html);
    }

    public function test_el_director_cierra_una_sesion_o_todas_las_de_un_usuario_y_queda_en_la_auditoria(): void
    {
        $this->actingAs($this->director);
        $this->sesion((string) session()->getId(), $this->director, '209.61.122.152', self::UA_WIN, 0);
        $this->sesion('dir-otra', $this->director, '38.25.29.129', self::UA_WIN, 10);
        $this->sesion('licet-pc', $this->licet, '209.61.122.152', self::UA_WIN, 2);
        $this->sesion('licet-cel', $this->licet, '179.6.137.162', self::UA_ANDROID, 40);
        $this->sesion('rosa-pc', $this->rosa, '209.61.122.152', self::UA_WIN, 5);

        $comp = Livewire::test(Sesiones::class);
        // Una sesión de Licet.
        $comp->call('cerrarSesion', 'licet-cel')->assertDispatched('successAlert');
        $this->assertSame(['licet-pc'], DB::table('sessions')->where('user_id', $this->licet->id)->pluck('id')->all());
        $cierre = Activity::where('description', 'like', 'Cerró una sesión de Licet%')->sole();
        $this->assertSame($this->licet->id, (int) $cierre->subject_id);
        $this->assertSame('179.6.137.162', $cierre->properties['sesion']['ip']);
        $this->assertSame($this->director->id, (int) $cierre->causer_id);

        // Todas las de Licet; Rosa no se toca.
        $comp->call('cerrarTodas', $this->licet->id)->assertDispatched('successAlert');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->licet->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $this->rosa->id)->count());
        $this->assertSame(1, (int) Activity::where('description', 'Cerró las sesiones de Licet (1)')->sole()->properties['sesiones_cerradas']);
        $comp->call('cerrarTodas', $this->licet->id)->assertDispatched('errorAlert');

        // La sesión del propio director desde la que mira nunca se cierra; su otra sí.
        $comp->call('cerrarSesion', (string) session()->getId())->assertDispatched('errorAlert');
        $comp->call('cerrarTodas', $this->director->id)->assertDispatched('successAlert');
        $this->assertSame([(string) session()->getId()], DB::table('sessions')->where('user_id', $this->director->id)->pluck('id')->all());
        $comp->call('cerrarSesion', 'ya-no-existe')->assertDispatched('errorAlert');
    }

    public function test_filtros_buscar_y_solo_con_sesion(): void
    {
        $this->actingAs($this->director);
        $this->sesion('licet-pc', $this->licet, '209.61.122.152', self::UA_WIN, 2);

        $comp = Livewire::test(Sesiones::class);
        $this->assertSame(['Licet', 'Guilmer', 'Rosa'], $comp->instance()->bloques()->pluck('usuario.username')->all());
        $comp->set('soloConSesion', true);
        $this->assertSame(['Licet'], $comp->instance()->bloques()->pluck('usuario.username')->all());
        $comp->set('soloConSesion', false)->set('buscar', 'tafur');
        $this->assertSame(['Licet', 'Rosa'], $comp->instance()->bloques()->pluck('usuario.username')->all());
        $comp->set('buscar', 'nadie');
        $this->assertStringContainsString('No hay usuarios que coincidan', $comp->html());
    }

    public function test_una_administradora_no_puede_llamar_a_las_acciones(): void
    {
        $this->actingAs($this->licet);
        Livewire::test(Sesiones::class)->assertForbidden();
    }

    public function test_con_sesiones_en_archivos_avisa(): void
    {
        config(['session.driver' => 'file']);
        $this->actingAs($this->director);
        $html = Livewire::test(Sesiones::class)->html();
        $this->assertStringContainsString('SESSION_DRIVER=database', $html);
        $this->assertStringContainsString('0 sesión(es) en total', $html);
    }
}
