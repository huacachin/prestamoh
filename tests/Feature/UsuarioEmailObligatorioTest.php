<?php

namespace Tests\Feature;

use App\Livewire\Users\Create;
use App\Livewire\Users\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El email del usuario es OBLIGATORIO (14/09). La columna users.email es NOT
 * NULL, pero el formulario lo pedía como opcional: dejarlo vacío mandaba un
 * nulo a la base, MySQL respondía 1048 y el operador veía un 500 (pasó al dar
 * de alta a "Marvin Josue"). Ahora lo ataja la validación y se ve el mensaje.
 */
class UsuarioEmailObligatorioTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $u = User::factory()->create(['username' => 'admin-usuarios', 'headquarter_id' => 1]);
        $u->givePermissionTo(Permission::findOrCreate('configuracion.usuarios', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    public function test_no_deja_crear_usuario_sin_email_y_muestra_el_mensaje(): void
    {
        $this->actingAs($this->actor());
        $antes = User::count();

        $c = Livewire::test(Create::class)
            ->set('name', 'Marvin Josue')
            ->set('username', 'Marvin')
            ->set('pwd', 'Clave12345!')
            ->set('document_type', 'DNI')
            ->set('document_number', '76109966')
            ->set('phone', '930988032')
            ->set('headquarter_id', 1)
            ->set('email', null)
            ->call('save');

        $c->assertHasErrors(['email' => 'required']);
        // El mensaje que ve el operador, en español y en la vista.
        $c->assertSee('El campo correo electrónico es obligatorio.');
        // Y no se insertó nada (antes llegaba a la base y reventaba con 500).
        $this->assertSame($antes, User::count());
    }

    public function test_con_email_valido_crea_el_usuario(): void
    {
        $this->actingAs($this->actor());

        Livewire::test(Create::class)
            ->set('name', 'Marvin Josue')
            ->set('username', 'marvin')
            ->set('pwd', 'Clave12345!')
            ->set('document_type', 'DNI')
            ->set('document_number', '76109966')
            ->set('phone', '930988032')
            ->set('headquarter_id', 1)
            ->set('email', 'marvin@huacachin.pe')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'marvin', 'email' => 'marvin@huacachin.pe']);
    }

    public function test_un_email_mal_escrito_tambien_avisa(): void
    {
        $this->actingAs($this->actor());

        Livewire::test(Create::class)
            ->set('name', 'Marvin Josue')
            ->set('username', 'marvin2')
            ->set('pwd', 'Clave12345!')
            ->set('document_type', 'DNI')
            ->set('document_number', '76109967')
            ->set('phone', '930988032')
            ->set('headquarter_id', 1)
            ->set('email', 'marvin-sin-arroba')
            ->call('save')
            ->assertHasErrors(['email' => 'email'])
            ->assertSee('El campo correo electrónico no es un correo electrónico válido.');
    }

    public function test_la_sucursal_es_obligatoria_y_viene_marcada_la_principal(): void
    {
        $this->actingAs($this->actor());

        // Al abrir el formulario ya viene la sede principal (hoy, la única).
        $c = Livewire::test(Create::class);
        $this->assertSame(1, $c->get('headquarter_id'));

        // Y si la vacían, no deja guardar.
        $c->set('name', 'Sin Sede')
            ->set('username', 'sinsede')
            ->set('pwd', 'Clave12345!')
            ->set('document_type', 'DNI')
            ->set('document_number', '76109970')
            ->set('phone', '930988032')
            ->set('email', 'sinsede@huacachin.pe')
            ->set('headquarter_id', null)
            ->call('save')
            ->assertHasErrors(['headquarter_id' => 'required']);

        $this->assertDatabaseMissing('users', ['username' => 'sinsede']);
    }

    public function test_limpiar_el_formulario_no_deja_la_sede_vacia(): void
    {
        $this->actingAs($this->actor());

        Livewire::test(Create::class)
            ->set('headquarter_id', null)
            ->call('clean')
            ->assertSet('headquarter_id', 1);
    }

    public function test_recorta_los_espacios_al_crear(): void
    {
        $this->actingAs($this->actor());

        Livewire::test(Create::class)
            ->set('name', '  Marvin Josue Ramirez Blanco ')
            ->set('username', 'Marvin ')
            ->set('pwd', 'Clave12345!')
            ->set('document_type', 'DNI')
            ->set('document_number', ' 76109966 ')
            ->set('phone', ' 930988032 ')
            ->set('headquarter_id', 1)
            ->set('email', ' mramirez@huacachin.com ')
            ->call('save')
            ->assertHasNoErrors();

        $u = User::where('document_number', '76109966')->firstOrFail();
        $this->assertSame('Marvin Josue Ramirez Blanco', $u->name);
        $this->assertSame('Marvin', $u->username);
        $this->assertSame('mramirez@huacachin.com', $u->email);
        $this->assertSame('930988032', $u->phone);
    }

    public function test_recorta_los_espacios_al_editar(): void
    {
        $this->actingAs($this->actor());
        $otro = User::factory()->create([
            'username' => 'editable2', 'email' => 'editable2@huacachin.pe', 'headquarter_id' => 1,
        ]);

        Livewire::test(Edit::class, ['id' => $otro->id])
            ->set('name', ' Licet Tafur Collantes ')
            ->set('username', ' licet2 ')
            ->set('document_type', 'DNI')
            ->set('document_number', ' 45861856 ')
            ->set('phone', ' 930988033 ')
            ->set('headquarter_id', 1)
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('Licet Tafur Collantes', $otro->fresh()->name);
        $this->assertSame('licet2', $otro->fresh()->username);
        $this->assertSame('45861856', $otro->fresh()->document_number);
    }

    public function test_el_comando_limpia_los_espacios_ya_guardados(): void
    {
        // Filas sucias como las de producción (insert directo: el modelo no recorta).
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = DB::table('users')->insertGetId([
            'name' => 'Marvin Josue Ramirez Blanco', 'username' => 'MarvinX ',
            'email' => 'sucio@huacachin.pe', 'password' => bcrypt('x'),
            'headquarter_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('users:limpiar-espacios --dry-run')->assertSuccessful();
        $this->assertSame('MarvinX ', DB::table('users')->where('id', $id)->value('username'));

        $this->artisan('users:limpiar-espacios')->assertSuccessful();
        $this->assertSame('MarvinX', DB::table('users')->where('id', $id)->value('username'));

        // Idempotente.
        $this->artisan('users:limpiar-espacios')->expectsOutputToContain('No hay espacios sobrantes')->assertSuccessful();
    }

    public function test_al_editar_tampoco_se_puede_vaciar_el_email(): void
    {
        $this->actingAs($this->actor());
        $otro = User::factory()->create([
            'username' => 'editable', 'email' => 'editable@huacachin.pe', 'headquarter_id' => 1,
        ]);

        Livewire::test(Edit::class, ['id' => $otro->id])
            ->set('email', null)
            ->call('update')
            ->assertHasErrors(['email' => 'required'])
            ->assertSee('El campo correo electrónico es obligatorio.');

        // El email anterior sigue intacto.
        $this->assertSame('editable@huacachin.pe', $otro->fresh()->email);
    }
}
