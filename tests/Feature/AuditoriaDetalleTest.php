<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index as AuditIndex;
use App\Models\Client;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 25/09/2026: visor de Auditoría con los registros automáticos por modelo
 * (trait Auditable): filtro por módulo, filtro de acción por `event`, y el
 * modal de detalle con el antes/después, el contexto y las propiedades.
 */
class AuditoriaDetalleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function mundo(): void
    {
        $this->user = User::factory()->create(['username' => 'auditor', 'name' => 'Auditor Test']);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        $this->actingAs($this->user);
        Activity::query()->delete(); // limpia lo generado por el fixture
    }

    private function cliente(): Client
    {
        return Client::create([
            'expediente' => '7001', 'nombre' => 'ROSA', 'apellido_pat' => 'PEREZ', 'apellido_mat' => 'TEST',
            'tipo_documento' => 'DNI', 'documento' => '45000001', 'sexo' => 'F', 'status' => 'active',
            'email' => null, 'es_relacionado' => false,
        ]);
    }

    private function filaAuditoria(string $event): Activity
    {
        return Activity::where('log_name', Audit::LOG)->where('event', $event)->latest('id')->firstOrFail();
    }

    public function test_al_editar_un_cliente_el_modal_muestra_antes_y_despues_con_su_etiqueta(): void
    {
        $this->mundo();
        // Etiqueta controlada desde el test: así no depende de lo que traiga config/auditoria.php
        config()->set('auditoria.etiquetas.'.Client::class, ['nombre' => 'Nombre del cliente']);

        $c = $this->cliente();
        $c->update(['nombre' => 'ROSA MARIA']);

        $fila = $this->filaAuditoria('updated');
        $this->assertSame(Client::class, $fila->subject_type);

        $comp = Livewire::test(AuditIndex::class)
            ->assertSee('Tiene detalle de campos')   // icono de la fila: tiene attribute_changes
            ->call('ver', $fila->id)
            ->assertDispatched('audit-detalle-open');

        $detalle = $comp->get('detalle');
        $this->assertSame('updated', $detalle['modo']);
        $this->assertSame('edicion', $detalle['accion']);
        $this->assertSame('Cliente', $detalle['modulo']);
        $this->assertSame((string) $c->id, (string) $detalle['subject_id']);
        $this->assertSame(route('clients.show', $c->id), $detalle['subject_url']);
        $this->assertSame(
            [['campo' => 'Nombre del cliente', 'antes' => 'ROSA', 'despues' => 'ROSA MARIA']],
            $detalle['cambios'],
            'solo la columna que cambió, con su etiqueta y su valor anterior'
        );
        $this->assertSame([], $detalle['propiedades'], 'el contexto no se lista como propiedad de negocio');
        $this->assertSame('auditor', $detalle['usuario']['username']);
        $this->assertSame('director', $detalle['usuario']['rol']);

        $comp->assertSee('Antes')->assertSee('Después')
            ->assertSee('Nombre del cliente')->assertSee('ROSA MARIA')
            ->assertSee("Editó Cliente #{$c->id}")
            ->assertSee('Auditor Test');
        $this->assertStringContainsString(route('clients.show', $c->id), $comp->html(), 'enlace a la ficha del cliente');

        $comp->call('cerrarDetalle')
            ->assertSet('detalle', null)
            ->assertDispatched('audit-detalle-close');
    }

    public function test_el_modal_de_un_alta_y_de_un_borrado_lista_los_valores_de_la_fila(): void
    {
        $this->mundo();
        config()->set('auditoria.etiquetas.'.Client::class, []); // sin etiquetas: nombres humanizados
        $c = $this->cliente();

        $detalle = Livewire::test(AuditIndex::class)->call('ver', $this->filaAuditoria('created')->id)->get('detalle');
        $this->assertSame('created', $detalle['modo']);
        $this->assertSame([], $detalle['cambios']);
        $valores = collect($detalle['valores'])->pluck('valor', 'campo');
        $this->assertSame('ROSA', $valores['Nombre']);
        $this->assertSame('No', $valores['Es relacionado'], 'los booleanos salen como Sí/No');

        $c->fresh()->delete();
        $detalle = Livewire::test(AuditIndex::class)->call('ver', $this->filaAuditoria('deleted')->id)->get('detalle');
        $this->assertSame('deleted', $detalle['modo']);
        $valores = collect($detalle['valores'])->pluck('valor', 'campo');
        $this->assertSame('45000001', $valores['Documento'], 'el borrado guarda la fila completa');
        $this->assertSame('—', $valores['Email'], 'los nulos salen como guion');
    }

    public function test_el_filtro_por_modulo_deja_fuera_los_registros_sin_subject(): void
    {
        $this->mundo();
        $this->cliente();                        // automático: subject Client
        Audit::log('Registró algo suelto');      // manual, sin subject

        $this->assertCount(2, Livewire::test(AuditIndex::class)->viewData('logs'), 'sin filtro salen los dos');

        $comp = Livewire::withQueryParams(['modulo' => Client::class])->test(AuditIndex::class);
        $logs = $comp->viewData('logs');
        $this->assertCount(1, $logs);
        $this->assertSame(Client::class, $logs->first()->subject_type);
        $comp->assertSeeHtml('value="'.e(Client::class).'"'); // la opción del select Módulo

        // Y con set() por el select
        $this->assertCount(1, Livewire::test(AuditIndex::class)->set('modulo', Client::class)->viewData('logs'));
        $this->assertCount(2, Livewire::test(AuditIndex::class)->set('modulo', Client::class)->call('limpiar')->viewData('logs'));
    }

    public function test_el_filtro_edicion_incluye_las_filas_por_event_aunque_el_verbo_no_este_en_la_lista(): void
    {
        $this->mundo();
        Audit::log('Registró algo');   // creación por verbo
        Activity::create(['log_name' => Audit::LOG, 'description' => 'Xyz', 'event' => 'updated']);

        $logs = Livewire::withQueryParams(['accion' => 'edicion'])->test(AuditIndex::class)->viewData('logs');
        $this->assertCount(1, $logs);
        $this->assertSame('Xyz', $logs->first()->description);

        // La fila lleva el badge de Edición aunque 'Xyz' no sea un verbo conocido
        Livewire::withQueryParams(['accion' => 'edicion'])->test(AuditIndex::class)
            ->assertSeeHtml('badge bg-warning text-dark');

        $this->assertCount(0, Livewire::withQueryParams(['accion' => 'eliminacion'])->test(AuditIndex::class)->viewData('logs'));
        $this->assertCount(1, Livewire::withQueryParams(['accion' => 'creacion'])->test(AuditIndex::class)->viewData('logs'));
    }

    public function test_el_modal_de_un_registro_manual_muestra_las_propiedades_y_la_ip_del_contexto(): void
    {
        $this->mundo();

        // Registro manual tal como lo deja Audit::log + GuardarActividad, con el
        // contexto fijado a mano para que la IP y el navegador sean deterministas.
        $fila = Activity::create([
            'log_name' => Audit::LOG,
            'description' => 'Registró pago de 150.50 del crédito #9',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'properties' => [
                'monto' => 150.5,
                'fecha_pago' => '2026-09-25',
                'anulado' => false,
                'motivo' => null,
                'detalle' => ['cuota' => 3],
                'contexto' => [
                    'ip' => '10.1.2.3', 'agente' => 'NavegadorPrueba/1.0', 'ruta' => 'payments.create',
                    'usuario' => ['id' => $this->user->id, 'username' => 'auditor', 'nombre' => 'Auditor Test', 'rol' => 'director'],
                ],
            ],
        ]);

        $comp = Livewire::test(AuditIndex::class)->call('ver', $fila->id);
        $detalle = $comp->get('detalle');

        $this->assertNull($detalle['modo'], 'un registro manual no tiene antes/después');
        $this->assertSame('creacion', $detalle['accion']);
        $this->assertSame('10.1.2.3', $detalle['ip']);
        $this->assertSame('NavegadorPrueba/1.0', $detalle['navegador']);
        $this->assertSame('payments.create', $detalle['ruta']);
        $this->assertNull($detalle['modulo']);

        $props = collect($detalle['propiedades'])->pluck('valor', 'campo')->all();
        $this->assertArrayNotHasKey('Contexto', $props, 'el contexto no es una propiedad de negocio');
        $this->assertSame('150.5', $props['Monto']);
        $this->assertSame('2026-09-25', $props['Fecha pago']);
        $this->assertSame('No', $props['Anulado']);
        $this->assertSame('—', $props['Motivo']);
        $this->assertStringContainsString('"cuota": 3', $props['Detalle'], 'los arreglos salen como JSON legible');

        $comp->assertSee('Propiedad')->assertSee('10.1.2.3')->assertSee('NavegadorPrueba/1.0')
            ->assertSee('payments.create')->assertSee('Monto')->assertSee('150.5')
            ->assertDontSee('Tiene detalle de campos');
    }

    public function test_el_modal_de_un_registro_manual_cae_al_causer_si_no_hay_usuario_en_el_contexto(): void
    {
        $this->mundo();
        $fila = Activity::create([
            'log_name' => Audit::LOG, 'description' => 'Inicio de sesión',
            'causer_type' => User::class, 'causer_id' => $this->user->id,
            'properties' => ['contexto' => ['ip' => '10.0.0.1', 'agente' => null, 'ruta' => 'login', 'usuario' => null]],
        ]);

        $detalle = Livewire::test(AuditIndex::class)->call('ver', $fila->id)->get('detalle');
        $this->assertSame('Auditor Test', $detalle['usuario']['nombre']);
        $this->assertSame('auditor', $detalle['usuario']['username']);
        $this->assertSame('director', $detalle['usuario']['rol']);
        $this->assertSame('acceso', $detalle['accion']);
        $this->assertSame([], $detalle['propiedades']);
    }

    public function test_las_etiquetas_de_columna_salen_del_config_o_humanizadas(): void
    {
        $this->assertSame('Fecha prestamo', AuditIndex::etiquetaColumna('App\\Models\\Inexistente', 'fecha_prestamo'));
        $this->assertSame('Fecha prestamo', AuditIndex::etiquetaColumna(null, 'fecha_prestamo'));
        config()->set('auditoria.etiquetas.App\\Models\\Inexistente', ['fecha_prestamo' => 'Fecha del préstamo']);
        $this->assertSame('Fecha del préstamo', AuditIndex::etiquetaColumna('App\\Models\\Inexistente', 'fecha_prestamo'));

        $this->assertSame('—', AuditIndex::formatearValor(null));
        $this->assertSame('Sí', AuditIndex::formatearValor(true));
        $this->assertSame('No', AuditIndex::formatearValor(false));
        $this->assertSame('12', AuditIndex::formatearValor(12));
        $this->assertSame('12.5', AuditIndex::formatearValor(12.5));
        $this->assertSame('••••••', AuditIndex::formatearValor('••••••'));
        $this->assertSame("{\n    \"a\": 1\n}", AuditIndex::formatearValor(['a' => 1]));
    }
}
