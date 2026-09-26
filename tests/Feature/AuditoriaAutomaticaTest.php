<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 25/09/2026: auditoría automática por modelo (trait Auditable, traída de
 * newtaxivan sobre spatie/activitylog) y contexto de la petición en TODO registro.
 */
class AuditoriaAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Headquarter $sede;

    private function mundo(): void
    {
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'auditor', 'name' => 'Auditor Test', 'headquarter_id' => $this->sede->id]);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        $this->actingAs($this->user);
        Activity::query()->delete(); // limpia lo generado por el fixture
    }

    private function ultimo(): Activity
    {
        return Activity::where('log_name', 'auditoria')->latest('id')->firstOrFail();
    }

    public function test_crear_editar_y_borrar_un_cliente_dejan_registro_con_antes_y_despues(): void
    {
        $this->mundo();

        $c = Client::create(['expediente' => '7001', 'nombre' => 'ROSA', 'apellido_pat' => 'PEREZ', 'apellido_mat' => 'TEST', 'documento' => '45000001', 'headquarter_id' => $this->sede->id, 'status' => 'active']);
        $creado = $this->ultimo();
        $this->assertSame('created', $creado->event);
        $this->assertSame("Creó Cliente #{$c->id} (PEREZ TEST ROSA)", $creado->description);
        $this->assertSame(Client::class, $creado->subject_type);
        $this->assertSame((string) $c->id, (string) $creado->subject_id);
        $this->assertSame('ROSA', $creado->attribute_changes['attributes']['nombre']);
        $this->assertArrayNotHasKey('created_at', $creado->attribute_changes['attributes'], 'los timestamps no se auditan');
        $this->assertSame((string) $this->user->id, (string) $creado->causer_id);

        // Misma instancia recién creada (sin recargar): los valores por defecto de la tabla
        // NO deben salir como cambios; solo lo que Eloquent guardó de verdad.
        $c->update(['nombre' => 'ROSA MARIA', 'documento' => '45000001']); // documento igual → no cuenta
        $editado = $this->ultimo();
        $this->assertSame('updated', $editado->event);
        $this->assertSame("Editó Cliente #{$c->id} (PEREZ TEST ROSA MARIA)", $editado->description);
        $this->assertSame(['nombre' => 'ROSA MARIA'], $editado->attribute_changes['attributes'], 'solo lo que cambió');
        $this->assertSame(['nombre' => 'ROSA'], $editado->attribute_changes['old'], 'con su valor anterior');

        $antes = Activity::count();
        $c->update(['nombre' => 'ROSA MARIA']); // sin cambios reales → sin registro
        $this->assertSame($antes, Activity::count());

        $c->delete();
        $borrado = $this->ultimo();
        $this->assertSame('deleted', $borrado->event);
        $this->assertStringStartsWith("Eliminó Cliente #{$c->id}", $borrado->description);
        $this->assertSame('45000001', $borrado->attribute_changes['old']['documento'], 'el borrado guarda la fila completa');
    }

    public function test_la_contrasena_se_enmascara_y_el_remember_token_no_se_guarda(): void
    {
        $this->mundo();

        $u = User::create(['name' => 'Nuevo', 'username' => 'nuevo-user', 'email' => 'nuevo@test.local', 'password' => Hash::make('secreta'), 'headquarter_id' => $this->sede->id, 'status' => 'active']);
        $creado = $this->ultimo();
        $this->assertSame('••••••', $creado->attribute_changes['attributes']['password']);
        $this->assertArrayNotHasKey('remember_token', $creado->attribute_changes['attributes']);
        $this->assertStringNotContainsString('secreta', json_encode($creado->attribute_changes));

        $u->update(['password' => Hash::make('otra')]);
        $editado = $this->ultimo();
        $this->assertSame('••••••', $editado->attribute_changes['attributes']['password']);
        $this->assertSame('••••••', $editado->attribute_changes['old']['password']);
        $this->assertSame("Editó Usuario #{$u->id} (nuevo-user)", $editado->description);
    }

    public function test_todo_registro_lleva_el_contexto_de_la_peticion(): void
    {
        $this->mundo();

        // Petición HTTP real: la IP, el navegador y la ruta salen del request.
        $this->withHeaders(['User-Agent' => 'NavegadorPrueba/1.0'])
            ->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])
            ->get(route('audit.index'))
            ->assertOk();
        // El middleware no escribe en BD; forzamos un registro manual dentro del mismo request simulado:
        $this->withHeaders(['User-Agent' => 'NavegadorPrueba/1.0'])->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])
            ->post(route('logout'));
        $salida = Activity::where('description', 'Cerró sesión')->latest('id')->first();
        $this->assertNotNull($salida, 'el logout queda en la base');
        $ctx = $salida->properties['contexto'];
        $this->assertSame('10.9.8.7', $ctx['ip']);
        $this->assertSame('NavegadorPrueba/1.0', $ctx['agente']);
        $this->assertSame('logout', $ctx['ruta']);
        $this->assertSame('auditor', $ctx['usuario']['username']);
        $this->assertSame('Auditor Test', $ctx['usuario']['nombre']);
        $this->assertSame('director', $ctx['usuario']['rol']);

        // Y un Audit::log manual conserva sus propiedades de negocio junto al contexto.
        $this->actingAs($this->user); // el logout de arriba cerró la sesión
        Audit::log('Registró prueba', null, ['monto' => 12.5]);
        $manual = $this->ultimo();
        $this->assertSame(12.5, $manual->properties['monto']);
        $this->assertArrayHasKey('contexto', $manual->properties->toArray());
        $this->assertSame('auditor', $manual->properties['contexto']['usuario']['username']);
    }

    public function test_el_intento_de_login_fallido_queda_en_la_base_sin_usuario(): void
    {
        $this->mundo();
        auth()->logout();

        Livewire::test(Login::class)
            ->set('username', 'auditor')->set('password', 'incorrecta')
            ->call('authenticate')
            ->assertHasErrors('username');

        $intento = Activity::where('description', 'Intento de inicio de sesión fallido')->latest('id')->first();
        $this->assertNotNull($intento);
        $this->assertNull($intento->causer_id);
        $this->assertSame('auditor', $intento->properties['username']);
    }
}
