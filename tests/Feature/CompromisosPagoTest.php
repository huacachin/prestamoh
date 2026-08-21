<?php

namespace Tests\Feature;

use App\Livewire\Clients\NotificationsModal;
use App\Livewire\Layout\CompromisosBell;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Compromisos de pago MÚLTIPLES por notificación (tabla compromisos_pago):
 * "el 25/08 paga 2 cuotas y el 30/08 paga 3", editables, y visibles en la
 * campana de compromisos del navbar.
 */
class CompromisosPagoTest extends TestCase
{
    use RefreshDatabase;

    private function notificacion(): array
    {
        $client = Client::create(['nombre' => 'Cliente Comp', 'celular1' => '999111222']);
        $notifId = DB::table('client_notifications')->insertGetId([
            'client_id' => $client->id, 'credit_id' => null, 'user_id' => 1, 'numero' => 1,
            'mensaje' => 'aviso', 'telefono' => '999111222', 'cuotas_vencidas' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$client, $notifId];
    }

    public function test_permite_varios_compromisos_por_notificacion_y_edicion(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client, $notifId] = $this->notificacion();

        $c = Livewire::test(NotificationsModal::class)->call('abrir', $client->id);

        // Dos compromisos sobre la misma notificación
        $c->call('abrirCompromiso', $notifId)
            ->set('compFecha', now()->addDays(4)->format('Y-m-d'))
            ->set('compDetalle', 'paga 2 cuotas')
            ->call('guardarCompromiso');
        $c->call('abrirCompromiso', $notifId)
            ->set('compFecha', now()->addDays(9)->format('Y-m-d'))
            ->set('compDetalle', 'paga 3 cuotas')
            ->call('guardarCompromiso');

        $this->assertSame(2, DB::table('compromisos_pago')->where('client_notification_id', $notifId)->count());

        // Edición de uno de ellos
        $comp = DB::table('compromisos_pago')->where('detalle', 'paga 2 cuotas')->first();
        $c->call('editarCompromiso', $comp->id)
            ->set('compDetalle', 'paga 2 cuotas y la mora')
            ->call('guardarCompromiso');
        $this->assertSame('paga 2 cuotas y la mora', DB::table('compromisos_pago')->where('id', $comp->id)->value('detalle'));

        // Cumplido alternable y eliminación
        $c->call('toggleCumplido', $comp->id);
        $this->assertNotNull(DB::table('compromisos_pago')->where('id', $comp->id)->value('cumplido_at'));
        $c->call('toggleCumplido', $comp->id);
        $this->assertNull(DB::table('compromisos_pago')->where('id', $comp->id)->value('cumplido_at'));

        $c->call('eliminarCompromiso', $comp->id);
        $this->assertSame(1, DB::table('compromisos_pago')->where('client_notification_id', $notifId)->count());
    }

    public function test_la_campana_muestra_los_proximos_y_marca_cumplido(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        [$client, $notifId] = $this->notificacion();

        // Uno vencido (rojo), uno próximo (naranja) y uno lejano (fuera de la campana)
        foreach ([[-1, 'vencido'], [1, 'proximo'], [9, 'lejano']] as [$dias, $detalle]) {
            DB::table('compromisos_pago')->insert([
                'client_id' => $client->id, 'client_notification_id' => $notifId,
                'fecha' => now()->addDays($dias)->format('Y-m-d'), 'detalle' => $detalle,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $bell = Livewire::test(CompromisosBell::class);
        $bell->assertSee('vencido')->assertSee('proximo')->assertDontSee('lejano');

        $comp = DB::table('compromisos_pago')->where('detalle', 'vencido')->first();
        $bell->call('marcarCumplido', $comp->id);
        $this->assertNotNull(DB::table('compromisos_pago')->where('id', $comp->id)->value('cumplido_at'));
        Livewire::test(CompromisosBell::class)->assertDontSee('vencido');
    }
}
