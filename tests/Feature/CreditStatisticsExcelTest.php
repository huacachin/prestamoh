<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Excel del Reporte Estadístico de Crédito — espejo de /reports/credit-statistics. */
class CreditStatisticsExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_excel_descarga_con_las_dos_secciones(): void
    {
        $tester = User::factory()->create(['username' => 'tester']);
        $tester->givePermissionTo(Permission::findOrCreate('reportes.credito-estadistica', 'web'));
        $this->actingAs($tester);

        $respuesta = $this->get(route('exports.reports.credit-statistics', [
            'mes' => '07', 'anio' => '2026',
        ]));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Disposition',
            'attachment; filename="Reporte Estadistico De Credito.xls"');

        $respuesta->assertSee('REPORTE ESTADISTICO DE CREDITO', false);
        $respuesta->assertSee('Ingresos Creditos', false);
        $respuesta->assertSee('Egresos Capital', false);
        $respuesta->assertSee('RESUMEN MENSUAL 2026', false);

        // Dos tablas (diaria + mensual) y domingos pintados en la celda Fecha
        // (julio 2026 tiene 4: días 5/12/19/26).
        $html = $respuesta->getContent();
        $this->assertSame(2, substr_count($html, '<table'));
        $this->assertSame(4, substr_count($html, '#ff0000'),
            'Los domingos deben pintar su celda Fecha en rojo');
    }
}
