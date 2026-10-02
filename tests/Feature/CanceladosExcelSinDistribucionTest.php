<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 02/10/2026 (Antony): en el Excel de cancelados salía la cabecera de la
 * tabla de distribución ("Detalle / % / S/") sin filas, un cuadro que se quitó
 * de la pantalla el 18/09. Ya no va: el Excel termina en el Total General.
 */
class CanceladosExcelSinDistribucionTest extends TestCase
{
    public function test_el_excel_termina_en_el_total_general_sin_la_cabecera_de_distribucion(): void
    {
        $html = view('exports.cancelled', [
            'rows' => [[
                'n' => 1, 'tot2' => 1, 'exp' => '1299', 'codigo' => '29447', 'dni' => '41234567', 'nombre' => 'ROQUE WILDER', 'cod_rem' => '',
                'capital' => 1000, 'r_capital' => 0, 'capital_neto' => 1000, 'detalles' => 'Semanal', 'interes_pct' => '10%', 'interes_s' => 100,
                'mora' => 0, 'total' => 1100, 'mxd' => 0, 'mora_s' => 0, 'dias' => 0, 'fec_cred' => '01/09/2026', 'fec_venc' => '01/10/2026',
                'fec_cancel' => '02/10/2026', 'estado' => 'Cancelado', 'asesor' => 'Licet',
            ]],
            'totals' => ['cancecapi' => 1000, 'canceinteg' => 0, 'todf1' => 1000, 'canceinte' => 100, 'cancemora' => 0, 'totGP' => 1100, 'montomorxdia' => 0, 'total_gat' => 0],
        ])->render();

        $this->assertStringContainsString('CREDITO CANCELADO', $html);
        $this->assertStringContainsString('Total General', $html);
        $this->assertStringNotContainsString('>Detalle</th>', $html);
        $this->assertStringNotContainsString('Gastos Operativos', $html);
        // La cabecera de 2 filas sigue teniendo su columna "Detalles" (tipo de crédito), que sí va.
        $this->assertStringContainsString('>Detalles</th>', $html);
    }
}
