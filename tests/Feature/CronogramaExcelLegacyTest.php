<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 25/09/2026: el Excel del cronograma es espejo del legacy (cliente_viewexcel2.php):
 * título "Reporte de Pago", bloque de cabecera, 8 columnas, domingo en rojo y
 * sábado en verde por el periodo, bloque Total, pie Totales / Saldo.
 */
class CronogramaExcelLegacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_excel_tiene_el_formato_del_legacy(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $asesor = User::factory()->create(['username' => 'asesor-uno', 'name' => 'Asesor Uno', 'headquarter_id' => $sede->id]);
        $this->actingAs($asesor);
        $this->seed(PermissionCatalogSeeder::class);
        $asesor->givePermissionTo('creditos');

        $client = Client::create([
            'expediente' => '9051', 'nombre' => 'ROSA', 'apellido_pat' => 'PEREZ', 'apellido_mat' => 'TEST',
            'tipo_documento' => 'DNI', 'documento' => '45000001', 'sexo' => 'F',
            'headquarter_id' => $sede->id, 'asesor_id' => $asesor->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-03', 'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1,
            'interes' => 10, 'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id, 'user_id' => $asesor->id,
        ]);
        // Cuota 1 vence sábado 08/08 (verde), cuota 2 domingo 09/08 (rojo), 3 y 4 entre semana.
        $ins = [];
        foreach (['2026-08-08', '2026-08-09', '2026-08-17', '2026-08-24'] as $i => $venc) {
            $ins[] = CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i + 1, 'fecha_vencimiento' => $venc,
                'importe_cuota' => 250, 'importe_interes' => 25, 'pagado' => $i === 0 ? 1 : 0,
                'importe_aplicado' => $i === 0 ? 250 : 0, 'interes_aplicado' => $i === 0 ? 25 : 0,
            ]);
        }
        foreach ([['CAPITAL', 250], ['INTERES', 25]] as [$tipo, $monto]) {
            DB::table('payments')->insert([
                'credit_id' => $credit->id, 'installment_id' => $ins[0]->id, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
                'fecha' => '2026-08-08', 'hora' => '10:00:00', 'monto' => $monto, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $xls = $this->get(route('exports.credits.schedule', $credit->id))->assertOk()->getContent();

        // Cabecera como el legacy.
        foreach (['Reporte de Pago', '<b>Cliente </b>', $client->fullName(), '<b>Asesor </b>', 'Asesor Uno', '<b>Dni </b>', '45000001',
            '<b>Tasa % </b>', '10.00', '<b>N&deg; Exp.</b>', '9051', '<b>Capital </b>', '1,000.00',
            '<b>N&deg; Cred.</b>', $credit->id.' - <b>03/08/2026</b>', '<b>Moneda </b>', 'Soles'] as $texto) {
            $this->assertStringContainsString($texto, $xls, "falta: {$texto}");
        }
        // Las 8 columnas del legacy y ninguna de la pantalla nueva.
        foreach (['N&deg; Cuota', 'Periodo', 'Capital', 'Interes', 'Total', 'Mora', 'Pagado', 'Fecha Pago'] as $col) {
            $this->assertStringContainsString('>'.$col.'</th>', $xls);
        }
        foreach (['Pagado Cap.', 'Pagado Int.', 'Estado', 'Fecha Venc.', 'Retraso', 'Capital pendiente total'] as $viejo) {
            $this->assertStringNotContainsString($viejo, $xls);
        }
        // Sábado en verde y domingo en rojo: las 8 celdas de cada fila.
        $this->assertSame(8, substr_count($xls, 'color:green;'), 'la cuota del sábado 08/08 va en verde');
        $this->assertSame(8, substr_count($xls, 'color:red;'), 'la cuota del domingo 09/08 va en rojo');
        // Cuota 1 pagada el 08/08: total 275, pagado 275 con fecha; cuota 3 sin fecha de pago.
        $this->assertMatchesRegularExpression('~>1</th>\s*<th[^>]*>2026-08-08</th>\s*<th[^>]*>250\.00</th>\s*<th[^>]*>25\.00</th>\s*<th[^>]*>275\.00</th>\s*<th[^>]*>0\.00</th>\s*<th[^>]*>275\.00</th>\s*<th[^>]*>2026-08-08</th>~', $xls);
        $this->assertMatchesRegularExpression('~>3</th>\s*<th[^>]*>2026-08-17</th>(?:\s*<th[^>]*>[^<]*</th>){5}\s*<th[^>]*></th>~', $xls);
        // Bloque Total: capital 1,000 / interés 100 / total 1,100 / mora 0 / pagado 275 y mora+pagado 275.
        $this->assertMatchesRegularExpression('~<b>Total</b></th>\s*<th[^>]*><b>1,000\.00</b></th>\s*<th[^>]*><b>100\.00</b></th>\s*<th[^>]*><b>1,100\.00</b></th>\s*<th[^>]*><b>0\.00</b></th>\s*<th[^>]*><b>275\.00</b></th>~', $xls);
        // Pie: Totales de pagos fuera del cronograma (0) y Saldo = 1,100 - 275 = 825.
        $this->assertStringContainsString('<b>Totales</b>', $xls);
        $this->assertMatchesRegularExpression('~<b>Saldo</b></font></td>\s*<td[^>]*><font color="red"><b>825\.00</b></font>~', $xls);
    }
}
