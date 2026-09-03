<?php

namespace App\Support\Documentos;

use InvalidArgumentException;

/**
 * Catálogo declarativo del Anexo 2 (constancia de entrega del monto): qué
 * campos pide la transcripción del voucher según banco × modalidad. Los 17
 * formatos Word que mantenía el área son DATOS, no plantillas: una sola vista
 * Blade (legal/pdf/anexo2-constancia.blade.php) itera transcripcion() y el
 * formulario de captura genera sus inputs desde campos() — una única fuente
 * de verdad para capturar y renderizar.
 *
 * Los campos y su orden calcan los vouchers reales de cada banco (carpeta
 * "3. Anexo 2 - Constancia de entrega del monto" del área legal).
 */
final class BancosVoucher
{
    public const BANCOS = [
        'bcp' => 'BCP',
        'bbva' => 'BBVA',
        'interbank' => 'Interbank',
        'caja_cusco' => 'Caja Cusco',
        'caja_huancayo' => 'Caja Huancayo',
    ];

    public const NOMBRES_LEGALES = [
        'bcp' => 'EL BANCO DE CRÉDITO DEL PERÚ - BCP',
        'bbva' => 'EL BANCO BBVA PERÚ',
        'interbank' => 'EL BANCO INTERNACIONAL DEL PERÚ - INTERBANK',
        'caja_cusco' => 'LA CAJA MUNICIPAL DE AHORRO Y CRÉDITO CUSCO - CMAC CUSCO',
        'caja_huancayo' => 'LA CAJA MUNICIPAL DE AHORRO Y CRÉDITO HUANCAYO - CMAC HUANCAYO',
    ];

    public const MODALIDADES = [
        'transferencia' => 'Transferencia',
        'transferencia_datos' => 'Transferencia (datos de la operación)',
        'transferencia_interbancaria' => 'Transferencia interbancaria',
        'deposito' => 'Depósito en ventanilla',
        'deposito_descripcion' => 'Depósito (con descripción)',
        'yape' => 'Yape',
        'cajero' => 'Depósito en cajero/agente',
        'entre_cuentas' => 'Transferencia entre cuentas',
        'detalle_movimiento' => 'Detalle de movimiento',
        'constancia' => 'Constancia de transferencia',
    ];

    /**
     * Combos válidos y sus campos EN ORDEN de transcripción.
     * Cada campo: clave => [label de la constancia, requerido].
     * 'monto' y 'fecha_hora' están en todos; el resto calca el voucher real.
     */
    private const CATALOGO = [
        'bcp' => [
            'transferencia' => [
                'monto' => ['MONTO TRANSFERIDO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'cuenta_origen' => ['CUENTA DE ORIGEN', false],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
                'mensaje' => ['MENSAJE', false],
                'canal' => ['CANAL', false],
                'itf' => ['ITF', false],
            ],
            'deposito' => [
                'monto' => ['IMPORTE DEPOSITADO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'cci' => ['CCI', false],
                'referencia' => ['REFERENCIA', false],
                'agencia' => ['AGENCIA/OFICINA', false],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
                'itf' => ['ITF', false],
            ],
            'yape' => [
                'monto' => ['MONTO YAPEADO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'celular' => ['N° DE CELULAR', false],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
                'mensaje' => ['MENSAJE', false],
                'comision' => ['COMISIÓN', false],
            ],
            'entre_cuentas' => [
                'monto' => ['MONTO TRANSFERIDO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'titular_origen' => ['TITULAR DE LA CUENTA DE ORIGEN', false],
                'cuenta_origen' => ['CUENTA DE ORIGEN', true],
                'beneficiario' => ['TITULAR DE LA CUENTA DE DESTINO', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
            ],
            // Voucher 1.2 del set final: la app del BCP con el detalle
            // "DATOS DE LA OPERACIÓN" desplegado.
            'transferencia_datos' => [
                'monto' => ['MONTO TRANSFERIDO', true],
                'operacion_realizada' => ['OPERACIÓN REALIZADA', false],
                'fecha_hora' => ['FECHA Y HORA', true],
                'beneficiario' => ['ENVIADO A', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'cuenta_origen' => ['DESDE (CUENTA DE ORIGEN)', false],
                'mensaje' => ['MENSAJE', false],
                'canal' => ['CANAL', false],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
            ],
            // Voucher 1.3 del set final: transferencia interbancaria (BCP →
            // otro banco), con comisión y total cobrado.
            'transferencia_interbancaria' => [
                'monto' => ['MONTO TRANSFERIDO', true],
                'fecha_hora' => ['FECHA Y HORA', true],
                'beneficiario' => ['ENVIADO A', true],
                'cuenta_destino' => ['CUENTA DE DESTINO (BANCO)', true],
                'comision' => ['COMISIÓN', false],
                'total_cobrado' => ['TOTAL COBRADO', false],
                'cuenta_origen' => ['DESDE (CUENTA DE ORIGEN)', false],
                'nro_operacion' => ['NÚMERO DE OPERACIÓN', true],
                'mensaje' => ['MENSAJE', false],
            ],
        ],
        'bbva' => [
            'transferencia' => [
                'monto' => ['IMPORTE PAGADO', true],
                'monto_abonado' => ['IMPORTE ABONADO', false],
                'itf' => ['ITF', false],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_origen' => ['CUENTA DE ORIGEN', false],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'concepto' => ['CONCEPTO', false],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
            ],
            'deposito' => [
                'monto' => ['IMPORTE DEPOSITADO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'oficina' => ['OFICINA', false],
                'clave' => ['CLAVE DE LA OPERACIÓN', false],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
                'itf' => ['ITF', false],
            ],
            'cajero' => [
                'monto' => ['IMPORTE DEPOSITADO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'nro_cajero' => ['N° DE CAJERO', false],
                'nro_movimiento' => ['N° DE MOVIMIENTO', true],
            ],
            // Voucher 2.2 del set final: pantalla "detalle movimiento" de la
            // app (mínima: el IMPORTE sale en negativo — el cuadre usa la
            // magnitud).
            'detalle_movimiento' => [
                'monto' => ['IMPORTE', true],
                'fecha_hora' => ['FECHA Y HORA', true],
                'concepto' => ['CONCEPTO', false],
                'nro_operacion' => ['NÚMERO DE OPERACIÓN', true],
            ],
        ],
        'interbank' => [
            'transferencia' => [
                'monto' => ['MONTO TRANSFERIDO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_origen' => ['CUENTA DE ORIGEN', false],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'nro_operacion' => ['CÓDIGO DE OPERACIÓN', true],
                'comision' => ['COMISIÓN', false],
            ],
            'deposito' => [
                'monto' => ['IMPORTE DEPOSITADO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'agencia' => ['AGENCIA/TIENDA', false],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
            ],
            // Voucher 5.2 del set final: constancia formal de transferencia.
            'constancia' => [
                'monto' => ['MONEDA Y MONTO', true],
                'fecha_hora' => ['FECHA Y HORA', true],
                'titular_origen' => ['TITULAR', false],
                'cuenta_origen' => ['CUENTA A CARGO', false],
                'beneficiario' => ['DESTINATARIO', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'nro_operacion' => ['CÓDIGO DE OPERACIÓN', true],
            ],
        ],
        'caja_cusco' => [
            'deposito' => [
                'monto' => ['IMPORTE DEPOSITADO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_destino' => ['CUENTA DE DESTINO', true],
                'cci' => ['CCI', false],
                'agencia' => ['AGENCIA', false],
                'nro_operacion' => ['N° DE OPERACIÓN', true],
                'usuario' => ['USUARIO', false],
            ],
        ],
        'caja_huancayo' => [
            'deposito' => [
                'monto' => ['IMPORTE DEPOSITADO', true],
                'fecha_hora' => ['FECHA Y HORA DE LA OPERACIÓN', true],
                'beneficiario' => ['BENEFICIARIO', true],
                'cuenta_destino' => ['CUENTA (CTA)', true],
                'cci' => ['CCI', false],
                'nro_operacion' => ['DOC/N° DE OPERACIÓN', true],
                'itf' => ['ITF', false],
                'depositante' => ['DATOS DEL DEPOSITANTE', false],
                'descripcion' => ['DESCRIPCIÓN', false],
            ],
            // Voucher 3.2 del set final: depósito con la descripción y el
            // detalle de efectivo/ITF desplegado.
            'deposito_descripcion' => [
                'monto' => ['DEPÓSITO', true],
                'fecha_hora' => ['FECHA Y HORA', true],
                'beneficiario' => ['NOMBRE', true],
                'cuenta_destino' => ['CTA.', true],
                'cci' => ['NRO. CCI', false],
                'total_efectivo' => ['TOT. EFECTIVO', false],
                'itf' => ['IMP. (ITF)', false],
                'comision' => ['COM (FUE-PLA)', false],
                'descripcion' => ['DESCRIPCIÓN', false],
                'nro_operacion' => ['DOC.', false],
            ],
        ],
    ];

    /** @return array<string, array{0: string, 1: bool}> campos del combo, en orden */
    public static function campos(string $banco, string $modalidad): array
    {
        $campos = self::CATALOGO[$banco][$modalidad] ?? null;
        if ($campos === null) {
            throw new InvalidArgumentException("Combo banco/modalidad no válido: {$banco}/{$modalidad}");
        }

        return $campos;
    }

    /** @return array<string, list<string>> banco => modalidades válidas */
    public static function combosDisponibles(): array
    {
        return array_map('array_keys', self::CATALOGO);
    }

    public static function esComboValido(string $banco, string $modalidad): bool
    {
        return isset(self::CATALOGO[$banco][$modalidad]);
    }

    /** "TRANSFERENCIA BANCARIA — BCP" (título del bloque en la constancia) */
    public static function titulo(string $banco, string $modalidad): string
    {
        $mod = mb_strtoupper(self::MODALIDADES[$modalidad] ?? $modalidad);
        $bco = mb_strtoupper(self::BANCOS[$banco] ?? $banco);

        return "{$mod} — {$bco}";
    }

    /** Denominación legal del banco para el cuerpo del contrato (cláusula de constancia) */
    public static function nombreLegal(string $banco): string
    {
        return self::NOMBRES_LEGALES[$banco] ?? mb_strtoupper($banco);
    }

    /**
     * Transcripción lista para render: solo los campos con valor, en el orden
     * del catálogo. @return list<array{label: string, valor: string}>
     */
    public static function transcripcion(string $banco, string $modalidad, array $valores): array
    {
        $out = [];
        foreach (self::campos($banco, $modalidad) as $clave => [$label, $requerido]) {
            $valor = trim((string) ($valores[$clave] ?? ''));
            if ($valor !== '') {
                $out[] = ['label' => $label, 'valor' => mb_strtoupper($valor)];
            }
        }

        return $out;
    }

    /** Campos requeridos sin valor. @return list<string> labels faltantes */
    public static function faltantes(string $banco, string $modalidad, array $valores): array
    {
        $faltan = [];
        foreach (self::campos($banco, $modalidad) as $clave => [$label, $requerido]) {
            if ($requerido && trim((string) ($valores[$clave] ?? '')) === '') {
                $faltan[] = $label;
            }
        }

        return $faltan;
    }
}
