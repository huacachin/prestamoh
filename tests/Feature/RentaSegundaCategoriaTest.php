<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\RentaSegundaCategoria;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Excel 2da categoría" (30/10/2026, Antony): el Excel de la renta de segunda categoría del mes, igual al
 * manual "2-cat-prestamoh": sale del cronograma (las cuotas que vencen en el mes, pagadas o no), con el
 * crédito cancelado a mitad de mes solo hasta la cancelación, sin refinanciados y solo T. Crédito SIGM o
 * garantía hipotecaria.
 */
class RentaSegundaCategoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-30 10:00:00');
        DB::table('headquarters')->insertOrIgnore(['id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Semanal de $capital en cuotas que vencen desde $primera cada 7 días; $pagadas = las primeras pagadas
     * (con su pago "Cuota: n/N" como los migrados, el día del vencimiento).
     */
    private function credito(string $zona, string $dni, float $capital, int $cuotas, string $primera, int $pagadas = 0, array $extra = []): Credit
    {
        $client = Client::create(['nombre' => 'Cliente', 'apellido_pat' => 'Prueba', 'apellido_mat' => $dni, 'documento' => $dni, 'zona' => $zona, 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create(array_merge([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-03', 'importe' => $capital, 'cuotas' => $cuotas, 'tipo_planilla' => 1,
            'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ], $extra));
        $capCuota = round($capital / $cuotas, 2);
        $intCuota = round($capital * 0.0125, 2);
        foreach (range(1, $cuotas) as $n) {
            $venc = Carbon::parse($primera)->addWeeks($n - 1)->toDateString();
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => $venc,
                'importe_cuota' => $capCuota, 'importe_interes' => $intCuota,
                'importe_aplicado' => $n <= $pagadas ? $capCuota : 0, 'interes_aplicado' => $n <= $pagadas ? $intCuota : 0, 'pagado' => $n <= $pagadas,
            ]);
            if ($n <= $pagadas) {
                DB::table('payments')->insert(['credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => 'CAPITAL', 'documento' => 'CAPITAL', 'fecha' => "$venc 00:00:00",
                    'hora' => '10:00:00', 'monto' => $capCuota, 'detalle' => "Pago : {$credit->id} Cuota:  {$n}/{$cuotas}", 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        return $credit;
    }

    public function test_las_cuotas_que_vencen_en_el_mes_por_credito(): void
    {
        // SIGM semanal (cuotas de 500 + 50): vencen 4 en septiembre (03, 10, 17, 24); pagadas la de agosto y dos de septiembre.
        $this->credito('SIGM.S', '40000001', 4000, 8, '2026-08-27', pagadas: 3);
        // Garantía hipotecaria de una cuota (6,000 + 75) que vence el 15/09, impaga: entra igual (no hace falta cancelar).
        $this->credito('Gar. Hip.S', '40000002', 6000, 1, '2026-09-15');
        // Cancelado el 09/09 (cuotas 02 y 09 sí; 16 y 23 ya no): Fecha Cancelado = la del pago de la 09.
        $this->credito('SIGM.M', '40000003', 2000, 4, '2026-09-02', pagadas: 2, extra: ['situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-09-09']);
        // Fuera: sin garantía y sin cuotas en el mes.
        $this->credito('Sin Garantia', '40000004', 1000, 4, '2026-09-02');
        $this->credito('SIGM.S', '40000006', 3000, 4, '2026-10-07');
        // Renovación (mensual de 1 cuota refinanciado al vencer): no entra, como en el Excel manual.
        $renovado = $this->credito('SIGM.M', '40000005', 10000, 1, '2026-09-15', extra: ['tipo_planilla' => 3, 'situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-09-15', 'cancelado_por_refi' => true]);
        CreditInstallment::where('credit_id', $renovado->id)->update(['importe_aplicado' => 1000, 'interes_aplicado' => 125]);
        // Cancelado el 08/09, antes de su primera cuota del mes (10/09): cuenta la cuota en curso, con la fecha de la
        // cancelación y sin interés (caso Chaname).
        $this->credito('SIGM.S', '40000007', 2000, 4, '2026-09-10', extra: ['situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-09-08']);
        // Mensual cancelado el 20/09 cuya cuota vencía el 12/10: la cuota en curso es la del mes siguiente y va en septiembre.
        $this->credito('SIGM.M', '40000008', 3000, 1, '2026-10-12', extra: ['tipo_planilla' => 3, 'situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-09-20']);
        // T. Crédito "Garantia Mobiliaria…" también entra.
        $this->credito('Garantia Mobiliaria Ll.', '40000009', 800, 1, '2026-09-05');

        $lista = RentaSegundaCategoria::filas(2026, 9);
        $filas = collect($lista)->keyBy('dni');

        $this->assertSame(['40000009', '40000007', '40000003', '40000002', '40000008', '40000001'], array_column($lista, 'dni'), 'ordenadas por Fec/Venc, sin la renovación');
        $ver = fn (string $dni) => [$filas[$dni]['cuotas'], $filas[$dni]['prestamo'], $filas[$dni]['interes'], $filas[$dni]['pct'], $filas[$dni]['fec_venc'], $filas[$dni]['fec_cancelado']];
        // Préstamo = capital de las cuotas del mes; Interés % = interés ÷ capital del crédito.
        // Fecha Cancelado = la fecha en que debió cancelarse según el cronograma (Fec/Venc), pagada o no.
        $this->assertSame([4, 2000.0, 200.0, 5.0, '2026-09-24', '2026-09-24'], $ver('40000001'), 'dos de las cuatro siguen impagas: entran igual');
        $this->assertSame([1, 6000.0, 75.0, 1.25, '2026-09-15', '2026-09-15'], $ver('40000002'));
        $this->assertSame([2, 1000.0, 50.0, 2.5, '2026-09-09', '2026-09-09'], $ver('40000003'));
        $this->assertSame('2026-08-03', $filas['40000003']['fec_cred']);
        $this->assertSame([1, 500.0, 0.0, 0.0, '2026-09-08', '2026-09-08'], $ver('40000007'), 'cuota en curso: capital, sin interés, fecha de la cancelación');
        $this->assertSame([1, 3000.0, 0.0, 0.0, '2026-09-20', '2026-09-20'], $ver('40000008'), 'mensual: la cuota del mes siguiente, igual');
        $this->assertSame([1, 800.0, 10.0, 1.25, '2026-09-05', '2026-09-05'], $ver('40000009'));

        // En octubre siguen los vigentes con cuotas de octubre; los cancelados de septiembre ya no (ni la cuota en
        // curso del 40000008, que se contó en septiembre).
        $this->assertSame(['40000001', '40000006'], array_column(RentaSegundaCategoria::filas(2026, 10), 'dni'));
        $this->assertSame([], RentaSegundaCategoria::filas(2026, 7));
    }

    public function test_el_excel_tiene_el_formato_del_manual_con_sus_formulas(): void
    {
        $user = User::factory()->create(['username' => 'renta-2da', 'headquarter_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('reportes.cancelados', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->credito('SIGM.S', '40000001', 4000, 8, '2026-08-27', pagadas: 3);
        $this->credito('Gar. Hip.S', '40000002', 6000, 1, '2026-09-15');

        $respuesta = $this->actingAs($user)->get(route('exports.reports.renta-2da-categoria', ['selemes' => '09', 'selecano' => '2026']));
        $respuesta->assertOk();
        $this->assertStringContainsString('2da categoria - Septiembre 2026.xlsx', (string) $respuesta->headers->get('content-disposition'));

        $hoja = IOFactory::load($respuesta->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Sep.', $hoja->getTitle());
        $this->assertSame('CREDITOS CANCELADOS - SEPTIEMBRE 2026', $hoja->getCell('B1')->getValue());
        $this->assertSame(['N°', 'Dni', 'Apellidos y Nombres', 'Capital', 'Préstamo', 'Interes', null, 'Fec/Cred', 'Fec/Venc', 'Fecha Cancelado', 'Descripción'],
            $hoja->rangeToArray('B2:L2', null, false, false)[0]);
        $this->assertSame(['%', 'S/'], [$hoja->getCell('G3')->getValue(), $hoja->getCell('H3')->getValue()]);
        $this->assertEquals([1, '40000002', 6000, 6000, 1.25, 75], [$hoja->getCell('B4')->getValue(), $hoja->getCell('C4')->getValue(), $hoja->getCell('E4')->getValue(), $hoja->getCell('F4')->getValue(), $hoja->getCell('G4')->getValue(), $hoja->getCell('H4')->getValue()]);
        $this->assertSame('40000001', $hoja->getCell('C5')->getValue(), 'el DNI va como texto');
        $this->assertSame('dd/mm/yyyy', $hoja->getStyle('J4')->getNumberFormat()->getFormatCode());
        $this->assertEquals($hoja->getCell('J4')->getValue(), $hoja->getCell('K4')->getValue(), 'Fecha Cancelado = Fec/Venc');
        $this->assertNull($hoja->getCell('L4')->getValue(), 'Descripción vacía');
        // Pie: las fórmulas del manual.
        $this->assertSame(['TOTAL CAPITAL', '=SUM(E4:E5)', '=SUM(F4:F5)', 'INT.', '=SUM(H4:H5)'],
            [$hoja->getCell('B6')->getValue(), $hoja->getCell('E6')->getValue(), $hoja->getCell('F6')->getValue(), $hoja->getCell('G6')->getValue(), $hoja->getCell('H6')->getValue()]);
        $this->assertSame(['TOTAL CAPITAL MÁS INTERES', '=+F6+H6'], [$hoja->getCell('B7')->getValue(), $hoja->getCell('F7')->getValue()]);
        $this->assertSame(['A PAGAR IMP. 5%', '=H6*5%'], [$hoja->getCell('B8')->getValue(), $hoja->getCell('H8')->getValue()]);
        $this->assertSame('=ROUND(H8,0)', $hoja->getCell('H9')->getValue());
        $this->assertEqualsWithDelta(13.75, $hoja->getCell('H8')->getCalculatedValue(), 0.001); // 5 % de 275
        $this->assertSame(['FF00B050', 'FF0070C0', 'FFFFFF00'], [$hoja->getStyle('B7')->getFill()->getStartColor()->getARGB(), $hoja->getStyle('B8')->getFill()->getStartColor()->getARGB(), $hoja->getStyle('B9')->getFill()->getStartColor()->getARGB()]);

        // Sin mes elegido no se genera; sin permiso de Cancelados, tampoco.
        $this->actingAs($user)->get(route('exports.reports.renta-2da-categoria', ['selemes' => '00', 'selecano' => '2026']))->assertStatus(422);
        $this->actingAs(User::factory()->create(['username' => 'sin-permiso', 'headquarter_id' => 1]))
            ->get(route('exports.reports.renta-2da-categoria', ['selemes' => '09', 'selecano' => '2026']))->assertForbidden();
    }
}
