<?php

namespace Tests\Unit\Legal;

use App\Support\Legal\BancosVoucher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Catálogo declarativo del Anexo 2 (constancia de entrega del monto): los 17
 * formatos Word del área son DATOS, no plantillas. El catálogo define qué
 * campos pide cada banco × modalidad y en qué orden se transcriben — 'monto'
 * siempre primero (es el dato que la validación cruzada compara con el
 * crédito) y los combos inexistentes fallan fuerte, nunca en silencio.
 */
class BancosVoucherTest extends TestCase
{
    public function test_combos_disponibles_cubre_los_cinco_bancos(): void
    {
        $combos = BancosVoucher::combosDisponibles();

        $this->assertSame(
            ['bcp', 'bbva', 'interbank', 'caja_cusco', 'caja_huancayo'],
            array_keys($combos)
        );
        $this->assertSame(array_keys(BancosVoucher::BANCOS), array_keys($combos));

        foreach ($combos as $banco => $modalidades) {
            $this->assertNotEmpty($modalidades, "El banco '{$banco}' debe tener al menos una modalidad");
        }
    }

    public function test_campos_de_cada_combo_empiezan_con_monto(): void
    {
        foreach (BancosVoucher::combosDisponibles() as $banco => $modalidades) {
            foreach ($modalidades as $modalidad) {
                $campos = BancosVoucher::campos($banco, $modalidad);

                $this->assertSame(
                    'monto',
                    array_key_first($campos),
                    "El combo {$banco}/{$modalidad} debe empezar con 'monto'"
                );
            }
        }
    }

    public function test_transcripcion_respeta_el_orden_del_catalogo_y_omite_vacios(): void
    {
        // Valores a propósito en desorden, con uno vacío (mensaje) y varios ausentes
        $valores = [
            'nro_operacion' => '00112233',
            'beneficiario' => 'juan perez',
            'monto' => 'S/ 7,000.00',
            'mensaje' => '   ', // vacío tras trim → se omite
            'cuenta_destino' => '191-11111111-0-11',
            'fecha_hora' => '09/03/2026 10:15',
        ];

        $out = BancosVoucher::transcripcion('bcp', 'transferencia', $valores);

        // El orden de salida es el del catálogo, no el de entrada; sin vacíos
        $this->assertSame(
            [
                'MONTO TRANSFERIDO',
                'FECHA Y HORA DE LA OPERACIÓN',
                'BENEFICIARIO',
                'CUENTA DE DESTINO',
                'N° DE OPERACIÓN',
            ],
            array_column($out, 'label')
        );

        // Los valores salen en MAYÚSCULAS
        $this->assertSame('JUAN PEREZ', $out[2]['valor']);
        $this->assertSame('S/ 7,000.00', $out[0]['valor']);
    }

    public function test_faltantes_detecta_los_requeridos_sin_valor(): void
    {
        $faltan = BancosVoucher::faltantes('bcp', 'transferencia', [
            'monto' => '7,000.00',
            'beneficiario' => 'JUAN PEREZ',
            'cuenta_destino' => '   ', // en blanco cuenta como faltante
        ]);

        $this->assertSame(
            ['FECHA Y HORA DE LA OPERACIÓN', 'CUENTA DE DESTINO', 'N° DE OPERACIÓN'],
            $faltan
        );

        // Con todos los requeridos completos no falta nada (los opcionales pueden quedar vacíos)
        $this->assertSame([], BancosVoucher::faltantes('bcp', 'transferencia', [
            'monto' => '7,000.00',
            'fecha_hora' => '09/03/2026 10:15',
            'beneficiario' => 'JUAN PEREZ',
            'cuenta_destino' => '191-11111111-0-11',
            'nro_operacion' => '00112233',
        ]));
    }

    public function test_es_combo_valido_rechaza_combinaciones_inexistentes(): void
    {
        // BCP no tiene depósito en cajero (BBVA sí)
        $this->assertFalse(BancosVoucher::esComboValido('bcp', 'cajero'));
        $this->assertTrue(BancosVoucher::esComboValido('bbva', 'cajero'));

        $this->assertTrue(BancosVoucher::esComboValido('bcp', 'transferencia'));
        $this->assertTrue(BancosVoucher::esComboValido('bcp', 'yape'));
        $this->assertFalse(BancosVoucher::esComboValido('caja_cusco', 'yape'));
        $this->assertFalse(BancosVoucher::esComboValido('banco_inexistente', 'transferencia'));
    }

    public function test_titulo_y_nombre_legal_correctos(): void
    {
        $this->assertSame('TRANSFERENCIA — BCP', BancosVoucher::titulo('bcp', 'transferencia'));
        $this->assertSame('DEPÓSITO EN VENTANILLA — CAJA HUANCAYO', BancosVoucher::titulo('caja_huancayo', 'deposito'));
        $this->assertSame('YAPE — BCP', BancosVoucher::titulo('bcp', 'yape'));

        $this->assertSame('EL BANCO DE CRÉDITO DEL PERÚ - BCP', BancosVoucher::nombreLegal('bcp'));
        $this->assertSame(
            'LA CAJA MUNICIPAL DE AHORRO Y CRÉDITO CUSCO - CMAC CUSCO',
            BancosVoucher::nombreLegal('caja_cusco')
        );
    }

    public function test_campos_de_combo_invalido_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BancosVoucher::campos('bcp', 'cajero');
    }
}
