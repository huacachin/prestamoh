<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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
        $tester = User::factory()->create(['username' => 'tester']);
        $tester->givePermissionTo(Permission::findOrCreate('reportes.caja-estadistica', 'web'));
        $this->actingAs($tester);

        $respuesta = $this->get(route('exports.reports.cash-statistics', [
            'month' => 7, 'year' => 2026,
        ]));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Disposition',
            'attachment; filename="Reporte Estadistico De Caja.xls"');

        // Las 7 secciones del reporte, como la pantalla y el legacy e1.
        $respuesta->assertSee('REPORTE ESTADISTICO DE CAJA', false);
        $respuesta->assertSee('Capital T.', false);
        $respuesta->assertSee('Utilidad Caja 3', false);
        $respuesta->assertSee('DETALLES', false);
        $respuesta->assertSee('RESUMEN MENSUAL', false);
        $respuesta->assertSee('RESUMEN ANUAL', false);

        // Detalles visuales homologados con la pantalla (14/08):
        // domingos con la celda Fecha pintada (julio 2026 tiene 4: días
        // 5/12/19/26) y la fila Promedio con fondo gris.
        $html = $respuesta->getContent();
        $this->assertSame(4, substr_count($html, '#ffe5e5'),
            'Los domingos deben pintar SOLO su celda Fecha');
        $this->assertSame(22, substr_count($html, '#f0f0f0'),
            'La fila Promedio debe pintar sus 22 celdas de gris');
        $respuesta->assertSee('Promedio', false);
        // Detalles + distribución salen 2 veces: las del mes y las del acumulado.
        $this->assertSame(4, substr_count($html, '>DETALLES<'));
        // Cabecera multinivel en las 3 tablas grandes (diaria, mensual, anual).
        $this->assertSame(3, substr_count($html, '>CREDITO<'));
    }
}
