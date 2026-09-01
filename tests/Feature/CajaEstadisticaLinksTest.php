<?php

namespace Tests\Feature;

use App\Livewire\Reports\CashGeneral1;
use App\Livewire\Reports\CashStatistics;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los links de la estadística de caja, homólogos al legacy (01/09).
 *
 * caja-estadistica.php tiene exactamente TRES links:
 *   1. Capital2 del DÍA → reporte1a.php?mes&anio#fecha (detalle de caja 1
 *      anclado en el día) → aquí: cash-general-1?mes&anio&dia=Y-m-d, que
 *      dispara el scroll-to-day ya existente.
 *   2. Capital del MES → reporte.php — ARCHIVO INEXISTENTE (link roto
 *      también en prod del legacy) → se mapea al detalle real del mes
 *      (cash-general-1?mes&anio) en vez de replicar el 404.
 *   3. Utilidad2 del MES → reporte2a3.php (Reporte General Caja 3) →
 *      cash-general-3?mes&anio.
 */
class CajaEstadisticaLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Headquarter::create(['name' => 'Sede Links', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'links-tester', 'headquarter_id' => $sede->id]));

        // Un pago en el mes para que la tabla diaria tenga una fila con monto.
        $client = Client::create([
            'expediente' => '9995', 'nombre' => 'LINK', 'apellido_pat' => 'TEST',
            'tipo_documento' => 'DNI', 'documento' => '49000001', 'sexo' => 'M',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-01',
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        Payment::create([
            'credit_id' => $credit->id, 'fecha' => '2026-08-15', 'tipo' => 'CAPITAL',
            'documento' => 'CAPITAL', 'monto' => 250, 'detalle' => 'Pago : X Cuota: 1/4',
            'user_id' => auth()->id(), 'headquarter_id' => $sede->id,
        ]);
    }

    public function test_capital2_del_dia_linkea_al_detalle_de_caja_1_con_ancla(): void
    {
        $html = Livewire::test(CashStatistics::class)
            ->set('month', '08')->set('year', '2026')
            ->html();

        // El día con cobro linkea a cash-general-1 con mes, año y día.
        $this->assertStringContainsString('reports/cash-general-1?mes=08&amp;anio=2026&amp;dia=2026-08-15', $html);
    }

    public function test_capital_del_mes_y_utilidad2_linkean_a_sus_reportes(): void
    {
        $html = Livewire::test(CashStatistics::class)
            ->set('month', '08')->set('year', '2026')
            ->html();

        // Capital del mes (tabla resumen) → detalle del mes en caja 1.
        $this->assertStringContainsString('reports/cash-general-1?mes=08&amp;anio=2026', $html);
        // Utilidad2 → reporte general de caja 3 del mes.
        $this->assertStringContainsString('reports/cash-general-3?mes=08&amp;anio=2026', $html);
    }

    public function test_cash_general_1_con_dia_dispara_el_scroll(): void
    {
        Livewire::withQueryParams(['mes' => '08', 'anio' => '2026', 'dia' => '2026-08-15'])
            ->test(CashGeneral1::class)
            ->assertDispatched('scroll-to-day', date: '2026-08-15')
            ->assertSet('vista', 'detalle');
    }

    public function test_un_dia_invalido_no_dispara_nada(): void
    {
        Livewire::withQueryParams(['mes' => '08', 'anio' => '2026', 'dia' => 'x"onmouseover=alert(1)'])
            ->test(CashGeneral1::class)
            ->assertNotDispatched('scroll-to-day');
    }
}
