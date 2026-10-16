<?php

namespace Tests\Feature;

use App\Livewire\Users\Create;
use App\Livewire\Users\Edit;
use App\Models\ClaveSeguridad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 16/10/2026 (Antony): "cada vez que alguien quiera cambiar el rol de director
 * a alguien, o de otro rol a director, hay que pedir una contraseña… solo el
 * gerente va a manejar este password". La clave vive como hash en
 * claves_seguridad (ClaveSeguridad::ROL_DIRECTOR), se pide en la edición y en
 * el alta de usuarios (trait ProtegeRolDirector; llega como parámetro de la
 * acción, nunca como estado) y se crea o cambia con el comando
 * usuarios:clave-director.
 */
class ClaveRolDirectorTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Gerente-2026-Huacachin';

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Role::findOrCreate('director', 'web')->givePermissionTo(Permission::findOrCreate('configuracion.usuarios', 'web'));
        Role::findOrCreate('administrador', 'web');
        Role::findOrCreate('analista-creditos', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ClaveSeguridad::establecer(ClaveSeguridad::ROL_DIRECTOR, self::CLAVE);

        $this->actor = $this->usuario('Nicefaro', 'director');
        $this->actingAs($this->actor);
        RateLimiter::clear('clave-director:'.$this->actor->id);
    }

    private function usuario(string $username, string $rol): User
    {
        $u = User::factory()->create([
            'username' => $username, 'email' => strtolower($username).'@huacachin.pe', 'headquarter_id' => 1,
            'document_number' => (string) random_int(10000000, 99999999), 'phone' => '999999999',
        ]);
        $u->syncRoles([$rol]);

        return $u;
    }

    private function rolId(string $nombre): int
    {
        return (int) Role::findByName($nombre, 'web')->id;
    }

    public function test_quitar_el_rol_director_sin_la_clave_no_pasa(): void
    {
        $guilmer = $this->usuario('Guilmer', 'director');

        Livewire::test(Edit::class, ['id' => $guilmer->id])
            ->set('selectedRoleId', $this->rolId('administrador'))
            ->call('update')
            ->assertHasErrors(['claveDirector'])
            ->assertNoRedirect();

        $this->assertSame(['director'], $guilmer->fresh()->roles->pluck('name')->all(), 'sigue director');
        $this->assertSame(0, Activity::where('description', 'like', 'Cambió el rol%')->count());
    }

    public function test_con_la_clave_incorrecta_avisa_y_deja_rastro(): void
    {
        $guilmer = $this->usuario('Guilmer', 'director');

        Livewire::test(Edit::class, ['id' => $guilmer->id])
            ->set('selectedRoleId', $this->rolId('administrador'))
            ->call('update', 'esta-no-es')
            ->assertHasErrors(['claveDirector' => 'Clave de seguridad incorrecta.'])
            ->assertNoRedirect();

        $this->assertSame(['director'], $guilmer->fresh()->roles->pluck('name')->all());
        $intento = Activity::where('description', 'Intentó cambiar el rol de Guilmer de director a administrador con una clave de seguridad incorrecta')->sole();
        $this->assertSame($guilmer->id, (int) $intento->subject_id);
        $this->assertSame(['antes' => 'director', 'despues' => 'administrador'], $intento->properties['rol']);
    }

    public function test_con_la_clave_del_gerente_cambia_el_rol_y_queda_en_la_auditoria(): void
    {
        $guilmer = $this->usuario('Guilmer', 'director');

        Livewire::test(Edit::class, ['id' => $guilmer->id])
            ->set('selectedRoleId', $this->rolId('administrador'))
            ->call('update', self::CLAVE)
            ->assertHasNoErrors()
            ->assertRedirect(route('settings.users.index'));

        $this->assertSame(['administrador'], $guilmer->fresh()->roles->pluck('name')->all(), 'Guilmer pasó a administrador');
        $cambio = Activity::where('description', 'Cambió el rol de Guilmer de director a administrador')->sole();
        $this->assertSame(['antes' => 'director', 'despues' => 'administrador'], $cambio->properties['rol']);
        $this->assertTrue($cambio->properties['clave_seguridad']);
        $this->assertSame($this->actor->id, (int) $cambio->causer_id);
    }

    public function test_subir_a_director_tambien_la_pide_y_los_cambios_entre_otros_roles_no(): void
    {
        $rosa = $this->usuario('Rosa', 'administrador');

        // administrador → analista: sin clave.
        Livewire::test(Edit::class, ['id' => $rosa->id])
            ->set('selectedRoleId', $this->rolId('analista-creditos'))
            ->call('update')
            ->assertHasNoErrors()->assertRedirect(route('settings.users.index'));
        $this->assertSame(['analista-creditos'], $rosa->fresh()->roles->pluck('name')->all());
        $this->assertFalse(Activity::where('description', 'Cambió el rol de Rosa de administrador a analista-creditos')->sole()->properties['clave_seguridad']);

        // analista → director: con clave sí, sin clave no.
        Livewire::test(Edit::class, ['id' => $rosa->id])
            ->set('selectedRoleId', $this->rolId('director'))
            ->call('update')
            ->assertHasErrors(['claveDirector' => 'Para dar o quitar el rol Director hace falta la clave de seguridad del gerente.']);
        $this->assertSame(['analista-creditos'], $rosa->fresh()->roles->pluck('name')->all());

        Livewire::test(Edit::class, ['id' => $rosa->id])
            ->set('selectedRoleId', $this->rolId('director'))
            ->call('update', self::CLAVE)
            ->assertHasNoErrors();
        $this->assertSame(['director'], $rosa->fresh()->roles->pluck('name')->all());
        $this->assertTrue(Activity::where('description', 'Cambió el rol de Rosa de analista-creditos a director')->sole()->properties['clave_seguridad']);
    }

    public function test_dar_de_alta_como_director_pide_la_clave(): void
    {
        $datos = fn ($c) => $c->set('name', 'Nuevo Director')->set('username', 'nuevodir')->set('pwd', 'Secreta.2026')
            ->set('email', 'nuevodir@huacachin.pe')->set('document_type', 'DNI')->set('document_number', '12345678')
            ->set('phone', '988888888')->set('headquarter_id', 1)->set('selectedRoleId', $this->rolId('director'));

        $datos(Livewire::test(Create::class))->call('save')->assertHasErrors(['claveDirector'])->assertNoRedirect();
        $this->assertNull(User::where('username', 'nuevodir')->first(), 'sin clave no se crea');

        $datos(Livewire::test(Create::class))->call('save', self::CLAVE)->assertHasNoErrors()->assertRedirect(route('settings.users.index'));
        $nuevo = User::where('username', 'nuevodir')->sole();
        $this->assertSame(['director'], $nuevo->roles->pluck('name')->all());
        $this->assertTrue(Activity::where('description', 'Asignó el rol director a nuevodir')->sole()->properties['clave_seguridad']);

        // Un analista se crea sin clave, como siempre.
        $datos(Livewire::test(Create::class))->set('username', 'analista1')->set('email', 'a1@huacachin.pe')->set('document_number', '87654321')
            ->set('selectedRoleId', $this->rolId('analista-creditos'))->call('save')->assertHasNoErrors();
        $this->assertSame(['analista-creditos'], User::where('username', 'analista1')->sole()->roles->pluck('name')->all());
    }

    public function test_sin_clave_configurada_nadie_puede_dar_ni_quitar_director(): void
    {
        ClaveSeguridad::query()->delete();
        $guilmer = $this->usuario('Guilmer', 'director');

        $comp = Livewire::test(Edit::class, ['id' => $guilmer->id])
            ->set('selectedRoleId', $this->rolId('administrador'))
            ->call('update', self::CLAVE)
            ->assertHasErrors(['claveDirector'])
            ->assertNoRedirect();
        // (assertHasErrors parte el mensaje en los dos puntos de "usuarios:clave-director"; se mira directo.)
        $this->assertSame('No hay clave de seguridad configurada para dar o quitar el rol Director. Hay que crearla con el comando usuarios:clave-director.', $comp->errors()->first('claveDirector'));
        $this->assertSame(['director'], $guilmer->fresh()->roles->pluck('name')->all());
    }

    public function test_cinco_fallos_bloquean_aunque_despues_llegue_la_clave_buena(): void
    {
        $guilmer = $this->usuario('Guilmer', 'director');
        $comp = Livewire::test(Edit::class, ['id' => $guilmer->id])->set('selectedRoleId', $this->rolId('administrador'));
        for ($i = 0; $i < 5; $i++) {
            $comp->call('update', "mala-{$i}")->assertHasErrors(['claveDirector' => 'Clave de seguridad incorrecta.']);
        }
        $comp->call('update', self::CLAVE)->assertHasErrors(['claveDirector']);
        $this->assertStringStartsWith('Demasiados intentos', $comp->errors()->first('claveDirector'));
        $this->assertSame(['director'], $guilmer->fresh()->roles->pluck('name')->all(), 'bloqueado: sigue director');
        $this->assertSame(5, Activity::where('description', 'like', 'Intentó cambiar el rol de Guilmer%')->count());

        RateLimiter::clear('clave-director:'.$this->actor->id);
        $comp->call('update', self::CLAVE)->assertHasNoErrors();
        $this->assertSame(['administrador'], $guilmer->fresh()->roles->pluck('name')->all());
    }

    /** La clave no es estado del componente: no viaja de vuelta en el snapshot de Livewire ni con otros errores. */
    public function test_la_clave_escrita_nunca_vuelve_al_navegador(): void
    {
        $guilmer = $this->usuario('Guilmer', 'director');

        $comp = Livewire::test(Edit::class, ['id' => $guilmer->id])
            ->set('name', '')->set('selectedRoleId', $this->rolId('administrador'))
            ->call('update', self::CLAVE)->assertHasErrors(['name']);
        $this->assertStringNotContainsString(self::CLAVE, $comp->html());
        $this->assertStringNotContainsString('wire:model="claveDirector"', $comp->html());

        $comp = Livewire::test(Edit::class, ['id' => $guilmer->id])
            ->set('selectedRoleId', $this->rolId('administrador'))
            ->call('update', 'equivocada-123')->assertHasErrors(['claveDirector']);
        $this->assertStringNotContainsString('equivocada-123', $comp->html());

        $comp = Livewire::test(Create::class)->set('selectedRoleId', $this->rolId('director'))
            ->call('save', self::CLAVE)->assertHasErrors(['name']);
        $this->assertStringNotContainsString(self::CLAVE, $comp->html());
    }

    public function test_un_director_sigue_sin_poder_quitarse_su_propio_rol(): void
    {
        Livewire::test(Edit::class, ['id' => $this->actor->id])
            ->set('selectedRoleId', $this->rolId('administrador'))
            ->call('update', self::CLAVE)
            ->assertDispatched('errorAlert')
            ->assertNoRedirect();
        $this->assertSame(['director'], $this->actor->fresh()->roles->pluck('name')->all());
    }

    public function test_el_campo_de_la_clave_sale_en_la_edicion_y_en_el_alta(): void
    {
        $guilmer = $this->usuario('Guilmer', 'director');
        $director = $this->rolId('director');

        $html = Livewire::test(Edit::class, ['id' => $guilmer->id])->html();
        $this->assertStringContainsString('id="clave-director"', $html);
        $this->assertStringContainsString("wire:click=\"update(document.getElementById('clave-director').value)\"", $html);
        $this->assertStringContainsString("x-show.important=\"(\$wire.selectedRoleId ?? 0) != {$director} && ((\$wire.selectedRoleId ?? 0) == {$director} || {$director} == {$director})\"", $html);

        $html = Livewire::test(Create::class)->html();
        $this->assertStringContainsString('id="clave-director"', $html);
        $this->assertStringContainsString("wire:click=\"save(document.getElementById('clave-director').value)\"", $html);
        $this->assertStringContainsString("x-show.important=\"(\$wire.selectedRoleId ?? 0) == {$director}\"", $html);
    }

    public function test_el_comando_crea_reemplaza_y_comprueba_la_clave(): void
    {
        ClaveSeguridad::query()->delete();

        $this->artisan('usuarios:clave-director', ['--generar' => true, '--por' => 'Nicefaro'])
            ->expectsOutputToContain('creada')
            ->expectsOutputToContain('se muestra UNA sola vez')
            ->assertSuccessful();
        $fila = ClaveSeguridad::where('nombre', ClaveSeguridad::ROL_DIRECTOR)->sole();
        $this->assertSame($this->actor->id, (int) $fila->actualizado_por);
        $this->assertFalse(ClaveSeguridad::verificar(ClaveSeguridad::ROL_DIRECTOR, self::CLAVE), 'la vieja ya no vale');

        $this->artisan('usuarios:clave-director', ['--generar' => true])->expectsOutputToContain('REEMPLAZADA')->assertSuccessful();
        $this->artisan('usuarios:clave-director', ['--generar' => true, '--por' => 'nadie'])->expectsOutputToContain('No existe el usuario nadie')->assertFailed();

        // Por teclado: dos veces igual, con el largo mínimo; la auditoría enmascara el hash.
        $this->artisan('usuarios:clave-director')
            ->expectsQuestion('Clave de seguridad', 'Otra-Clave-Larga-2026')
            ->expectsQuestion('Repite la clave', 'Otra-Clave-Larga-2026')
            ->assertSuccessful();
        $this->assertTrue(ClaveSeguridad::verificar(ClaveSeguridad::ROL_DIRECTOR, 'Otra-Clave-Larga-2026'));
        $this->artisan('usuarios:clave-director')->expectsQuestion('Clave de seguridad', 'corta')->expectsQuestion('Repite la clave', 'corta')->assertFailed();
        $this->artisan('usuarios:clave-director', ['--verificar' => true])->expectsQuestion('Clave de seguridad', 'Otra-Clave-Larga-2026')->expectsOutputToContain('correcta')->assertSuccessful();
        $this->artisan('usuarios:clave-director', ['--verificar' => true])->expectsQuestion('Clave de seguridad', 'nope')->assertFailed();

        $auditoria = Activity::where('subject_type', ClaveSeguridad::class)->get();
        $this->assertNotEmpty($auditoria);
        $this->assertStringNotContainsString('$2y$', json_encode($auditoria->pluck('properties')), 'ningún hash en la auditoría');
    }

    public function test_la_clave_generada_es_legible_y_distinta_cada_vez(): void
    {
        $a = ClaveSeguridad::generar();
        $b = ClaveSeguridad::generar();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Za-hj-km-z2-9]{4}(-[A-HJ-NP-Za-hj-km-z2-9]{4}){3}$/', $a);
        $this->assertNotSame($a, $b);
    }
}
