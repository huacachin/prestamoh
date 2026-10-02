<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 02/10/2026 (Antony): en el Excel de egresos no se podían poner fórmulas de
 * suma. El layout de los exports marcaba TODAS las celdas como texto
 * (mso-number-format "@"), así que Excel mostraba "1,234.50" pero =SUMA()
 * daba 0. Ahora los montos de egresos e ingresos van como número con
 * formato (#,##0.00): valor crudo en la celda, misma pinta en Excel, y se
 * pueden sumar. Fechas, códigos y textos siguen como texto.
 */
class ExportsMontosNumericosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::factory()->create(['username' => 'excel-tester', 'headquarter_id' => 1]);
        foreach (['caja.egresos', 'caja.ingresos', 'caja.ver-todo'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->user);
    }

    public function test_los_montos_del_excel_de_egresos_son_numericos_y_las_fechas_siguen_como_texto(): void
    {
        $hoy = now()->format('Y-m-d');
        Expense::create(['date' => $hoy, 'reason' => 'Diario', 'detail' => 'gasolina', 'total' => 1234.5, 'modo' => 'Fijos', 'caja' => 1, 'user_id' => $this->user->id, 'headquarter_id' => 1]);
        Expense::create(['date' => $hoy, 'reason' => 'Diario', 'detail' => 'pasajes', 'total' => 10.25, 'modo' => 'Otros', 'caja' => 1, 'user_id' => $this->user->id, 'headquarter_id' => 1]);

        $res = $this->get("/exports/expenses?fei={$hoy}&fef={$hoy}");
        $res->assertOk();
        $this->assertStringStartsWith('application/vnd.ms-excel', $res->headers->get('Content-Type'));
        $html = $res->getContent();

        // Celda de monto: clase num + formato numérico de Excel + valor crudo.
        $this->assertMatchesRegularExpression('/<th class="num"[^>]*mso-number-format:\'#,##0\.00\';[^>]*>1234\.50<\/th>/', $html);
        $this->assertMatchesRegularExpression('/<th class="num"[^>]*>10\.25<\/th>/', $html);
        $this->assertStringNotContainsString('>1,234.50<', $html, 'el monto ya no viaja con separador de miles (texto)');

        // Totales también numéricos: general, fijos, otros.
        $this->assertMatchesRegularExpression('/<td class="num"[^>]*mso-number-format:\'#,##0\.00\';[^>]*><b>1244\.75<\/b><\/td>/', $html);
        $this->assertMatchesRegularExpression('/<td class="num"[^>]*><b><font color="red">1234\.50<\/font><\/b><\/td>/', $html);
        $this->assertMatchesRegularExpression('/<td class="num"[^>]*><b>10\.25<\/b><\/td>/', $html);

        // El resto sigue como texto (regla global del layout) y la fecha va d/m/Y sin clase num.
        $this->assertStringContainsString('td, th { mso-number-format: "\@"', $html);
        $this->assertMatchesRegularExpression('/<th style="[^"]*"><b>'.preg_quote(now()->format('d/m/Y'), '/').'<\/b><\/th>/', $html);
    }

    public function test_los_montos_del_excel_de_ingresos_son_numericos(): void
    {
        $hoy = now()->format('Y-m-d');
        Income::create(['date' => $hoy, 'total' => 500.4, 'reason' => 'OTROS', 'detail' => 'venta', 'asesor' => '', 'user_id' => $this->user->id, 'caja' => 1, 'modo' => 'Otros', 'headquarter_id' => 1]);

        $res = $this->get("/exports/incomes?fei={$hoy}&fef={$hoy}");
        $res->assertOk();
        $html = $res->getContent();

        $this->assertMatchesRegularExpression('/<td class="num"[^>]*mso-number-format:\'#,##0\.00\';[^>]*>500\.40<\/td>/', $html);
        $this->assertStringNotContainsString('>500.4<', $html, 'siempre con dos decimales');
        $this->assertMatchesRegularExpression('/<td class="num" align="right"[^>]*><b>500\.40<\/b><\/td>/', $html, 'total general numérico');
        $this->assertMatchesRegularExpression('/<td class="num" align="center"[^>]*><b>500\.40<\/b><\/td>/', $html, 'subtotal Otros numérico');
    }
}
