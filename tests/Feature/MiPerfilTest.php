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
        $this->assertStringNotContainsString('Cambiar contraseña', $html, 'sin tarjeta de contraseña (Antony, 16/10)');
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
        // Confirmación en dos pasos: botón → cuadro con contraseña (candado + ojo) y Confirmar / Cancelar.
        $this->assertStringContainsString('Confirma con tu contraseña para cerrar la otra sesión', $html);
        $this->assertStringContainsString('x-ref="clave"', $html);
        $this->assertStringContainsString('x-on:click="$wire.cerrarOtrasSesiones($refs.clave.value)"', $html);
        $this->assertStringContainsString('x-data="{ confirmando: false, ver: false }"', $html);

        $comp->call('cerrarOtrasSesiones', 'mala-mala')->assertHasErrors(['passwordSesiones']);
        $this->assertStringNotContainsString('mala-mala', $comp->html(), 'la contraseña no vuelve al navegador');
        $this->assertStringContainsString('x-data="{ confirmando: true, ver: false }"', $comp->html(), 'con error el cuadro sigue abierto');
        $this->assertStringContainsString('La contraseña no es correcta.', $comp->html());
        $this->assertSame(2, DB::table('sessions')->where('user_id', $u->id)->count());

        $comp->call('cerrarOtrasSesiones', 'Actual.2026')->assertHasNoErrors()->assertDispatched('successAlert')->assertDispatched('perfil-sesiones-cerradas');
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
