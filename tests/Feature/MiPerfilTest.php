<?php

namespace Tests\Feature;

use App\Livewire\Perfil\MiPerfil;
use App\Models\User;
use App\Support\AgenteNavegador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * 16/10/2026 (Antony): "Mi perfil, donde podamos editar mis datos y ver las
 * sesiones activas; en el desplegable, arriba de Cerrar sesión". Cualquier
 * usuario logueado, sin permiso propio. Las sesiones salen de la tabla
 * `sessions` (SESSION_DRIVER=database).
 */
class MiPerfilTest extends TestCase
{
    use RefreshDatabase;

    private const UA_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36';

    private const UA_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36';

    private function usuario(): User
    {
        return User::factory()->create([
            'username' => 'perfil', 'name' => 'Rosa Tafur', 'email' => 'rosa@huacachin.pe', 'phone' => '999111222',
            'password' => Hash::make('Actual.2026'),
        ]);
    }

    public function test_la_pagina_es_para_cualquier_usuario_logueado_y_sale_en_el_menu_encima_de_cerrar_sesion(): void
    {
        $this->get(route('perfil'))->assertRedirect(route('login'));

        $html = $this->actingAs($this->usuario())->get(route('perfil'))->assertOk()->getContent();
        $this->assertStringContainsString('MI PERFIL', $html);
        $this->assertStringContainsString('Sesiones activas', $html);
        $perfil = strpos($html, 'href="'.route('perfil').'"');
        $cerrar = strpos($html, 'Cerrar Sesión');
        $this->assertNotFalse($perfil, 'el menú lleva a Mi perfil');
        $this->assertNotFalse($cerrar);
        $this->assertLessThan($cerrar, $perfil, 'Mi perfil va antes de Cerrar Sesión');
    }

    public function test_mis_datos_es_solo_lectura(): void
    {
        $u = $this->usuario();
        $this->actingAs($u);

        $comp = Livewire::test(MiPerfil::class);
        $html = $comp->html();
        $this->assertStringContainsString('Solo lectura', $html);
        $this->assertStringContainsString('value="Rosa Tafur" readonly disabled', $html);
        $this->assertStringContainsString('value="rosa@huacachin.pe" readonly disabled', $html);
        $this->assertStringNotContainsString('wire:model="name"', $html, 'no hay campos editables de datos');
        $this->assertStringNotContainsString('Guardar mis datos', $html);
        $this->assertFalse(method_exists(MiPerfil::class, 'guardarDatos'), 'tampoco existe la acción por detrás');
        $this->assertSame('Rosa Tafur', $u->fresh()->name);
    }

    public function test_cambia_su_contrasena_confirmando_la_actual(): void
    {
        $u = $this->usuario();
        $this->actingAs($u);

        Livewire::test(MiPerfil::class)
            ->set('passwordActual', 'equivocada')->set('passwordNueva', 'Nueva.Clave.2026')->set('passwordNueva_confirmation', 'Nueva.Clave.2026')
            ->call('cambiarPassword')->assertHasErrors(['passwordActual']);
        $this->assertTrue(Hash::check('Actual.2026', $u->fresh()->password), 'sigue la de antes');

        Livewire::test(MiPerfil::class)
            ->set('passwordActual', 'Actual.2026')->set('passwordNueva', 'corta')->set('passwordNueva_confirmation', 'corta')
            ->call('cambiarPassword')->assertHasErrors(['passwordNueva']);

        Livewire::test(MiPerfil::class)
            ->set('passwordActual', 'Actual.2026')->set('passwordNueva', 'Nueva.Clave.2026')->set('passwordNueva_confirmation', 'Nueva.Clave.2026')
            ->call('cambiarPassword')->assertHasNoErrors()->assertDispatched('successAlert')->assertSet('passwordActual', '')->assertSet('passwordNueva', '');
        $this->assertTrue(Hash::check('Nueva.Clave.2026', $u->fresh()->password));
        $cambio = Activity::where('description', 'Cambió su contraseña')->sole();
        $this->assertSame($u->id, (int) $cambio->subject_id);
        $this->assertStringNotContainsString('Nueva.Clave', json_encode($cambio->properties), 'la contraseña no viaja a la auditoría');
        $this->assertSame(0, Activity::where('description', 'like', 'Editó Usuario%')->count(), 'sin fila automática duplicada');
    }

    public function test_lista_sus_sesiones_marca_la_actual_y_cierra_las_demas_con_su_contrasena(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->usuario();
        $otro = User::factory()->create(['username' => 'otro']);
        $this->actingAs($u);
        $actual = (string) session()->getId();
        $hace = fn (int $min) => now()->subMinutes($min)->timestamp;
        DB::table('sessions')->insert([
            ['id' => $actual, 'user_id' => $u->id, 'ip_address' => '209.61.122.152', 'user_agent' => self::UA_MAC, 'payload' => '', 'last_activity' => $hace(0)],
            ['id' => 'otra-win', 'user_id' => $u->id, 'ip_address' => '38.25.29.129', 'user_agent' => self::UA_WIN, 'payload' => '', 'last_activity' => $hace(40)],
            ['id' => 'ajena', 'user_id' => $otro->id, 'ip_address' => '1.2.3.4', 'user_agent' => self::UA_WIN, 'payload' => '', 'last_activity' => $hace(5)],
        ]);

        $comp = Livewire::test(MiPerfil::class);
        $sesiones = $comp->instance()->sesiones();
        $this->assertSame([$actual, 'otra-win'], $sesiones->pluck('id')->all(), 'solo las mías, la actual primero');
        $html = $comp->html();
        $this->assertStringContainsString('Esta sesión', $html);
        $this->assertStringContainsString('Computadora · macOS', $html);
        $this->assertStringContainsString('Computadora · Windows', $html);
        $this->assertStringContainsString('38.25.29.129', $html);
        $this->assertStringNotContainsString('1.2.3.4', $html, 'las sesiones de otros no salen');

        $comp->set('passwordSesiones', 'mala')->call('cerrarOtrasSesiones')->assertHasErrors(['passwordSesiones']);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $u->id)->count());

        $comp->set('passwordSesiones', 'Actual.2026')->call('cerrarOtrasSesiones')->assertHasNoErrors()->assertDispatched('successAlert')->assertSet('passwordSesiones', '');
        $this->assertSame([$actual], DB::table('sessions')->where('user_id', $u->id)->pluck('id')->all(), 'queda solo la actual');
        $this->assertNotNull(DB::table('sessions')->find('ajena'), 'las de otros usuarios no se tocan');
        $this->assertSame(1, (int) Activity::where('description', 'Cerró sus otras sesiones (1)')->sole()->properties['sesiones_cerradas']);
    }

    public function test_con_sesiones_en_archivos_avisa_y_no_lista_nada(): void
    {
        config(['session.driver' => 'file']);
        $this->actingAs($this->usuario());

        $html = Livewire::test(MiPerfil::class)->html();
        $this->assertStringContainsString('SESSION_DRIVER=database', $html);
        $this->assertStringNotContainsString('Cerrar las otras sesiones', $html);
    }

    public function test_el_agente_se_lee_para_los_navegadores_de_la_casa(): void
    {
        $this->assertSame('Chrome 155 · Windows', AgenteNavegador::describir(self::UA_WIN)['texto']);
        $this->assertSame('Computadora', AgenteNavegador::describir(self::UA_MAC)['dispositivo']);
        $iphone = AgenteNavegador::describir('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1');
        $this->assertSame(['Safari 17', 'iPhone', 'Celular'], [$iphone['navegador'], $iphone['plataforma'], $iphone['dispositivo']]);
        $android = AgenteNavegador::describir('Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36');
        $this->assertSame('Chrome 130 · Android', $android['texto']);
        $this->assertSame('Celular', $android['dispositivo']);
        $this->assertSame('Edge 130', AgenteNavegador::describir('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0')['navegador']);
        $this->assertSame('Navegador desconocido · Sistema desconocido', AgenteNavegador::describir(null)['texto']);
    }
}
