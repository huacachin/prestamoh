<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\RangoCuotas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La lista de cuotas del recibo va en rangos (09/09): un cobro de 30 cuotas
 * imprimía los 30 números y en la vista previa se salía del modal. Se usa el
 * MISMO texto en los cuatro sitios del recibo (vista previa, ticket térmico,
 * PDF y reimpresión) para que no se separen.
 */
class RangoCuotasTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_comprime_tramos_consecutivos(): void
    {
        $this->assertSame('19-48', RangoCuotas::texto(range(19, 48)));
        $this->assertSame('19-20,22-24,40', RangoCuotas::texto([19, 20, 22, 23, 24, 40]));
        $this->assertSame('7', RangoCuotas::texto([7]));
        $this->assertSame('', RangoCuotas::texto([]));
        // Desordenadas y repetidas: se normalizan.
        $this->assertSame('3-5', RangoCuotas::texto([5, 3, 4, 4]));
        // Valores no numéricos (etiquetas del legacy): se dejan tal cual.
        $this->assertSame('1/48,2/48', RangoCuotas::texto(['1/48', '2/48']));
    }

    public function test_la_vista_previa_del_recibo_muestra_el_rango(): void
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'tester-rango', 'headquarter_id' => 1]));

        // 8 cuotas semanales de 100 + 10; el cobro las toca todas.
        $client = Client::create(['nombre' => 'Cliente Rango']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subWeeks(9)->format('Y-m-d'),
            'importe' => 800, 'cuotas' => 8, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 80, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach (range(1, 8) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->subWeeks(9 - $n)->format('Y-m-d'),
                'importe_cuota' => 100, 'importe_interes' => 10, 'pagado' => 0,
            ]);
        }

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 880)
            ->call('confirmarPago')
            ->assertSee('1-8')
            ->assertDontSee('1,2,3,4,5,6,7,8');
    }
}
