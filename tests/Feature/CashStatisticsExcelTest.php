<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Excel del Reporte Estadístico de Caja — equivalente del legacy
 * caja-estadisticae1.php: espejo de /reports/cash-statistics.
 */
class CashStatisticsExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_excel_descarga_con_las_cinco_secciones(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));

        $respuesta = $this->get(route('exports.reports.cash-statistics', [
            'month' => 7, 'year' => 2026,
        ]));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Disposition',
            'attachment; filename="Reporte Estadistico De Caja.xls"');

        // Las 5 secciones del reporte, como la pantalla y el legacy e1.
        $respuesta->assertSee('REPORTE ESTADISTICO DE CAJA', false);
        $respuesta->assertSee('Capital T.', false);
        $respuesta->assertSee('Utilidad Caja 3', false);
        $respuesta->assertSee('DETALLES', false);
        $respuesta->assertSee('RESUMEN MENSUAL', false);
        $respuesta->assertSee('RESUMEN ANUAL', false);
    }
}
