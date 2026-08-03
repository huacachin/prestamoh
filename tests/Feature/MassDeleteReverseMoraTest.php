<?php

namespace Tests\Feature;

use App\Livewire\Credits\MassDeleteEdit;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\MassDeletion;
use App\Models\MassDeletionDetail;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reversa de "Eliminar Masivo": debe borrar el pago COMPLETO — capital,
 * interés Y mora — sin dejar la mora huérfana en payments (que el /schedule
 * seguiría mostrando porque calcula la mora desde los payments de tipo MORA).
 */
class MassDeleteReverseMoraTest extends TestCase
{
    use RefreshDatabase;

    private function actorConPermiso(): User
    {
        $perm = Permission::findOrCreate('registro.eliminar-masivo.revertir', 'web');
        $user = User::factory()->create(['username' => 'tester']);
        $user->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_reverse_borra_la_mora_huerfana_de_la_operacion(): void
    {
        $user = $this->actorConPermiso();
        $this->actingAs($user);

        $client = Client::create(['nombre' => 'Cliente Test']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-01-01',
            'importe' => 1000, 'cuotas' => 10, 'tipo_planilla' => 1,
            'situacion' => 'Activo', 'estado' => 1,
        ]);
        // Cuota con un abono previo de 25 (para comprobar que la reversa RESTA,
        // no pone a cero: 125 - 100 = 25 debe sobrevivir).
        $ins = CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => '2026-01-08',
            'importe_cuota' => 100, 'importe_aplicado' => 125, 'pagado' => 1, 'fecha_pago' => '2026-02-01',
        ]);

        $fecha = '2026-02-01';
        $hora = '10:20:30';

        $md = MassDeletion::create([
            'credit_id' => $credit->id, 'amount' => 223.45, 'date' => $fecha, 'time' => $hora,
            'user' => 'Test', 'advisor' => 'Test', 'performed_by' => 'Test',
        ]);

        // Capital de la operación (con su detalle → la reversa lo des-aplica).
        $cap = Payment::create([
            'credit_id' => $credit->id, 'installment_id' => $ins->id, 'modo' => 'CREDITO',
            'tipo' => 'CAPITAL', 'documento' => 'CAPITAL', 'fecha' => $fecha, 'hora' => $hora, 'monto' => 100,
        ]);
        MassDeletionDetail::create([
            'mass_deletion_id' => $md->id, 'installment_id' => $ins->id, 'payment_id' => $cap->id,
            'amount' => 100, 'tipo' => 'C', 'fecha' => now(),
        ]);

        // Mora HUÉRFANA de la MISMA operación (installment_id NULL, sin detalle):
        // así es como createMoraPayment registra MORA ACUM. / MORA CAPITAL.
        $mora = Payment::create([
            'credit_id' => $credit->id, 'installment_id' => null, 'modo' => 'CREDITO',
            'tipo' => 'MORA', 'documento' => 'MORA ACUM.', 'fecha' => $fecha, 'hora' => $hora, 'monto' => 123.45,
        ]);

        // Control: mora de OTRA fecha (otra operación) — NO debe borrarse.
        $moraOtra = Payment::create([
            'credit_id' => $credit->id, 'installment_id' => null, 'modo' => 'CREDITO',
            'tipo' => 'MORA', 'documento' => 'MORA ACUM.', 'fecha' => '2026-03-15', 'hora' => '08:00:00', 'monto' => 50,
        ]);

        Livewire::test(MassDeleteEdit::class, ['id' => $md->id])->call('reverse');

        // El pago de capital y su detalle desaparecen.
        $this->assertDatabaseMissing('payments', ['id' => $cap->id]);
        $this->assertDatabaseMissing('mass_deletions', ['id' => $md->id]);
        $this->assertDatabaseMissing('mass_deletion_details', ['mass_deletion_id' => $md->id]);

        // LA CLAVE: la mora huérfana de la operación TAMBIÉN desaparece.
        $this->assertDatabaseMissing('payments', ['id' => $mora->id]);

        // La mora de otra fecha sobrevive (acotado a credit+fecha+hora).
        $this->assertDatabaseHas('payments', ['id' => $moraOtra->id]);

        // La cuota vuelve a su abono previo (125 - 100 = 25), no a cero, y queda impaga.
        $ins->refresh();
        $this->assertEquals(25.0, (float) $ins->importe_aplicado);
        $this->assertFalse((bool) $ins->pagado);
        $this->assertNull($ins->fecha_pago);

        // El crédito queda Activo.
        $credit->refresh();
        $this->assertSame('Activo', $credit->situacion);
        $this->assertEquals(1, (int) $credit->estado);
    }
}
