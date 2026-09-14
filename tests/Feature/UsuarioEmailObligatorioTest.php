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
