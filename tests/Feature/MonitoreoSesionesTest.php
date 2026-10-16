<?php

namespace Tests\Feature;

use App\Livewire\Monitoreo\Sesiones;
use App\Livewire\Users\Index as UsersIndex;
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
        // Indicadores (16/10, nuevo look): número y rótulo en tarjetas.
        $this->assertStringContainsString('<div class="mon-kpi-num">2</div><div class="mon-kpi-label">usuario(s) con sesión abierta</div>', $html, 'Licet y el propio director');
        $this->assertStringContainsString('<div class="mon-kpi-num">3</div><div class="mon-kpi-label">sesión(es) en total</div>', $html);
        $this->assertStringContainsString('<div class="mon-kpi-num">2</div><div class="mon-kpi-label">con actividad en 5 min</div>', $html, 'la del director (ahora) y la de Licet de hace 2 min');
        $this->assertStringContainsString('<div class="mon-kpi-num">2</div><div class="mon-kpi-label">intento(s) fallido(s) en 7 días</div>', $html);
        $this->assertStringContainsString('En línea', $html, 'Licet tiene actividad reciente');
        $this->assertStringContainsString('Sin sesión', $html, 'Rosa');
    }

    /** 16/10 (Antony): se listan TODOS los inicios y cada IP distinta lleva su color (misma IP, mismo color). */
    public function test_se_listan_todos_los_inicios_y_cada_ip_distinta_tiene_su_color(): void
    {
        $this->actingAs($this->director);
        $inicio = function (User $u, string $ip, int $haceDias): void {
            DB::table('activity_log')->insert([
                'log_name' => Audit::LOG, 'description' => 'Inicio de sesión', 'event' => null,
                'subject_type' => null, 'subject_id' => null, 'causer_type' => $u->getMorphClass(), 'causer_id' => $u->id,
                'properties' => json_encode([]), 'ip_address' => $ip, 'user_agent' => self::UA_WIN, 'user_name' => $u->name,
                'created_at' => now()->subDays($haceDias), 'updated_at' => now()->subDays($haceDias),
            ]);
        };
        for ($d = 12; $d >= 1; $d--) {
            $inicio($this->licet, $d % 3 === 0 ? '38.25.29.129' : '209.61.122.152', $d); // 12 inicios, 2 IPs
        }
        $inicio($this->rosa, '209.61.122.152', 2); // la misma IP de la oficina: mismo color que en Licet

        $comp = Livewire::test(Sesiones::class);
        $licet = $comp->instance()->bloques()->firstWhere('usuario.username', 'Licet');
        $this->assertSame(12, $licet['inicios']->count(), 'todos, no solo los últimos 8');
        $this->assertSame(['209.61.122.152' => 8, '38.25.29.129' => 4], $licet['ips']->all(), 'la más usada primero');

        $html = $comp->html();
        $this->assertStringContainsString('2 IP(s) distinta(s):', $html);
        $this->assertStringContainsString('209.61.122.152 <b>×8</b>', $html);
        $this->assertStringContainsString('38.25.29.129 <b>×4</b>', $html);
        preg_match_all('/<span class="mon-ip" style="(--ip-bg:[^"]+)">209\.61\.122\.152<\/span>/', $html, $oficina);
        preg_match_all('/<span class="mon-ip" style="(--ip-bg:[^"]+)">38\.25\.29\.129<\/span>/', $html, $casa);
        $this->assertCount(9, $oficina[1], '8 de Licet + 1 de Rosa');
        $this->assertCount(4, $casa[1]);
        $this->assertCount(1, array_unique($oficina[1]), 'misma IP, mismo color, también entre usuarios');
        $this->assertCount(1, array_unique($casa[1]));
        $this->assertNotSame($oficina[1][0], $casa[1][0], 'IPs distintas, colores distintos');
        $this->assertStringContainsString('mon-inicios-lista', $html, 'recuadro con scroll, no una columna que se alarga');
    }

    /** 16/10 (Antony): filtro de rango de fechas para los inicios de sesión (datepicker del sistema, Y-m-d). */
    public function test_el_rango_de_fechas_filtra_los_inicios_sin_cambiar_los_colores(): void
    {
        $this->actingAs($this->director);
        $inicio = function (User $u, string $ip, string $fecha): void {
            DB::table('activity_log')->insert([
                'log_name' => Audit::LOG, 'description' => 'Inicio de sesión', 'event' => null,
                'subject_type' => null, 'subject_id' => null, 'causer_type' => $u->getMorphClass(), 'causer_id' => $u->id,
                'properties' => json_encode([]), 'ip_address' => $ip, 'user_agent' => self::UA_WIN, 'user_name' => $u->name,
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
        };
        $inicio($this->licet, '38.25.29.129', '2026-10-01 08:00:00');
        $inicio($this->licet, '209.61.122.152', '2026-10-05 08:00:00');
        $inicio($this->licet, '209.61.122.152', '2026-10-10 23:30:00');
        $inicio($this->licet, '179.6.137.162', '2026-10-15 08:00:00');

        $comp = Livewire::test(Sesiones::class);
        $this->assertStringContainsString('class="form-control form-control-sm dates" placeholder="Desde" wire:model.live="desde"', $comp->html(), 'datepicker del sistema');
        $colorAntes = preg_match('/<span class="mon-ip" style="(--ip-bg:[^"]+)">209\.61\.122\.152<\/span>/', $comp->html(), $m) ? $m[1] : null;
        $this->assertNotNull($colorAntes);

        $licet = fn () => $comp->instance()->bloques()->firstWhere('usuario.username', 'Licet');
        $this->assertSame(4, $licet()['inicios']->count());

        $comp->set('desde', '2026-10-05')->set('hasta', '2026-10-10');
        $this->assertSame(2, $licet()['inicios']->count(), 'del 05 al 10 inclusive (hasta las 23:59)');
        $html = $comp->html();
        $this->assertStringContainsString('del 05/10/2026 al 10/10/2026', $html);
        $this->assertStringNotContainsString('179.6.137.162', $html, 'el del 15 queda fuera');
        preg_match('/<span class="mon-ip" style="(--ip-bg:[^"]+)">209\.61\.122\.152<\/span>/', $html, $m2);
        $this->assertSame($colorAntes, $m2[1], 'la IP conserva su color al filtrar');

        $comp->set('desde', '2026-10-10')->set('hasta', '2026-10-05');
        $this->assertSame(2, $licet()['inicios']->count(), 'al revés se invierten');
        $comp->set('desde', '2026-10-16')->set('hasta', '');
        $this->assertSame(0, $licet()['inicios']->count());
        $this->assertStringContainsString('Sin inicios de sesión en ese rango.', $comp->html());
        $comp->set('desde', 'no-es-fecha');
        $this->assertSame(4, $licet()['inicios']->count(), 'una fecha mal escrita no filtra');
        $comp->set('desde', '2026-10-01')->call('limpiarFechas')->assertSet('desde', '')->assertSet('hasta', '');
        $this->assertSame(4, $licet()['inicios']->count());
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

    /** 16/10 (Antony): "hay que mostrar solo los status 1"; un cesado solo mientras conserve una sesión. */
    public function test_solo_usuarios_activos_salvo_un_cesado_con_sesion_abierta(): void
    {
        $this->actingAs($this->director);
        $cesadoSinSesion = User::factory()->create(['username' => 'Gianella', 'status' => 'inactive']);
        $cesadoConSesion = User::factory()->create(['username' => 'Marvin', 'status' => 'inactive']);
        $this->sesion('marvin-pc', $cesadoConSesion, '209.61.122.152', self::UA_WIN, 3);

        $comp = Livewire::test(Sesiones::class);
        $this->assertSame(['Marvin', 'Guilmer', 'Licet', 'Rosa'], $comp->instance()->bloques()->pluck('usuario.username')->all());
        $html = $comp->html();
        $this->assertStringContainsString('Cesado', $html, 'Marvin sale marcado como cesado');
        $this->assertStringNotContainsString('Gianella', $html);

        $comp->call('cerrarTodas', $cesadoConSesion->id)->assertDispatched('successAlert');
        $this->assertStringNotContainsString('Marvin', $comp->html(), 'sin sesión, el cesado desaparece');
    }

    /** Al cesar a un usuario desde /users se cierran sus sesiones: ya no puede seguir dentro hasta que caduque. */
    public function test_desactivar_un_usuario_cierra_sus_sesiones(): void
    {
        $this->actingAs($this->director);
        $this->sesion('licet-pc', $this->licet, '209.61.122.152', self::UA_WIN, 2);
        $this->sesion('licet-cel', $this->licet, '179.6.137.162', self::UA_ANDROID, 40);
        $this->sesion('rosa-pc', $this->rosa, '209.61.122.152', self::UA_WIN, 5);

        Livewire::test(UsersIndex::class)->call('destroy', $this->licet->id)
            ->assertDispatched('successAlert', ['message' => 'Usuario desactivado correctamente y 2 sesión(es) suya(s) cerrada(s)']);
        $this->assertSame('inactive', $this->licet->fresh()->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->licet->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $this->rosa->id)->count(), 'las de otros no se tocan');
        $this->assertSame(2, (int) Activity::where('description', 'like', 'Desactivó el usuario Licet%')->sole()->properties['sesiones_cerradas']);
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
        $this->assertStringContainsString('<div class="mon-kpi-num">0</div><div class="mon-kpi-label">sesión(es) en total</div>', $html);
    }
}
