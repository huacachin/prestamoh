<?php

namespace App\Services\Legal;

use App\Models\Garantia;
use App\Support\Legal\BancosVoucher;
use Carbon\Carbon;

/**
 * Validaciones BLOQUEANTES previas a generar el contrato de garantía
 * mobiliaria. La regla central: jamás emitir un documento que contradiga lo
 * que el sistema va a cobrar (cronograma real de credit_installments) ni con
 * datos incompletos que hoy, en Word, se pasaban por alto (los modelos del
 * área traen montos en letras que no cuadran y DNI cruzados).
 */
class ValidadorContrato
{
    /** Tolerancia entre el monto máximo capturado y la suma real del cronograma (S/) */
    public const TOLERANCIA_CRONOGRAMA = 1.00;

    /** @return list<string> errores bloqueantes (vacío = puede generarse) */
    public function validar(Garantia $garantia, array $parametros, bool $paraEmitir = true): array
    {
        $garantia->loadMissing(['client', 'codeudor', 'credit.installments', 'vehiculos']);
        $errores = [];
        $credit = $garantia->credit;

        // ─── Fecha ───
        $fecha = $parametros['fecha'] ?? null;
        if (! $fecha || ! strtotime((string) $fecha)) {
            $errores[] = 'La fecha del contrato es obligatoria y debe ser válida.';
        } elseif (Carbon::parse($fecha)->gt(now()->addDay())) {
            $errores[] = 'La fecha del contrato no puede ser futura.';
        }

        // ─── Deudores ───
        if ($garantia->tipo_persona === 'juridica') {
            $empresa = $parametros['empresa'] ?? [];
            if (trim((string) ($empresa['razon_social'] ?? '')) === '') {
                $errores[] = 'Persona jurídica: falta la razón social de la empresa.';
            }
            $ruc = (string) ($empresa['ruc'] ?? $garantia->client?->documento);
            if (! preg_match('/^\d{11}$/', $ruc)) {
                $errores[] = "Persona jurídica: el RUC debe tener 11 dígitos (actual: '{$ruc}').";
            }
            $gerente = $empresa['gerente'] ?? [];
            if (trim((string) ($gerente['nombre'] ?? '')) === '' || ! preg_match('/^\d{8}$/', (string) ($gerente['dni'] ?? ''))) {
                $errores[] = 'Persona jurídica: faltan los datos del gerente general (nombre y DNI de 8 dígitos).';
            }
        } else {
            foreach (array_filter([$garantia->client, $garantia->codeudor]) as $i => $cliente) {
                $rol = $i === 0 ? 'Deudor' : 'Co-deudor';
                if (! preg_match('/^\d{8}$/', (string) $cliente->documento)) {
                    $errores[] = "{$rol} {$cliente->fullName()}: el DNI debe tener 8 dígitos (actual: '{$cliente->documento}').";
                }
                if (! in_array(mb_strtoupper((string) $cliente->sexo), ['M', 'F'], true)) {
                    $errores[] = "{$rol} {$cliente->fullName()}: falta el sexo (M/F) en la ficha — define la redacción EL DEUDOR/LA DEUDORA.";
                }
                if (trim((string) $cliente->estado_civil) === '') {
                    $errores[] = "{$rol} {$cliente->fullName()}: falta el estado civil en la ficha.";
                }
                if (trim((string) $cliente->ocupacion) === '') {
                    $errores[] = "{$rol} {$cliente->fullName()}: falta la ocupación en la ficha.";
                }
                if (trim((string) $cliente->direccion) === '') {
                    $errores[] = "{$rol} {$cliente->fullName()}: falta el domicilio en la ficha.";
                }
                if (trim((string) $cliente->email) === '') {
                    $errores[] = "{$rol} {$cliente->fullName()}: falta el correo electrónico (la cláusula de comunicaciones lo consigna).";
                }
            }
        }

        // ─── Bienes ───
        $bienes = $garantia->vehiculos;
        if ($bienes->isEmpty()) {
            $errores[] = 'La garantía no tiene vehículos asociados.';
        }
        if ($bienes->count() > 2) {
            $errores[] = 'El contrato admite como máximo 2 bienes.';
        }
        foreach ($bienes as $v) {
            foreach (['marca' => 'marca', 'modelo' => 'modelo', 'nro_motor' => 'N° de motor', 'nro_serie' => 'N° de serie'] as $campo => $label) {
                if (trim((string) $v->{$campo}) === '') {
                    $errores[] = "Vehículo {$v->placa}: falta {$label}.";
                }
            }
            if ((float) $v->valor <= 0) {
                $errores[] = "Vehículo {$v->placa}: falta el valor del bien (cláusula de valor).";
            }
            if ($v->pivot->es_bien_futuro) {
                if (! $v->pivot->acta_notarial || ! $v->pivot->kardex || ! $v->pivot->notario) {
                    $errores[] = "Vehículo {$v->placa} (bien futuro): faltan acta de transferencia, kardex o notario.";
                }
            }
        }

        // ─── Montos vs cronograma real ───
        $totalCronograma = round((float) $credit->installments->sum(
            fn ($i) => (float) $i->importe_cuota + (float) $i->importe_interes + (float) $i->importe_excedente
        ), 2);

        if ($totalCronograma <= 0) {
            $errores[] = "El crédito N° {$credit->id} no tiene cronograma registrado (credit_installments vacío).";
        }
        $montoMaximo = round((float) $garantia->monto_gravamen, 2);
        if ($montoMaximo <= 0) {
            $errores[] = 'La garantía no tiene monto máximo (monto_gravamen).';
        } elseif ($totalCronograma > 0 && abs($totalCronograma - $montoMaximo) > self::TOLERANCIA_CRONOGRAMA) {
            $errores[] = sprintf(
                'El monto máximo de la garantía (S/ %s) no cuadra con el cronograma real del crédito (S/ %s). Corrige la garantía o el crédito — el contrato no puede contradecir lo que el sistema cobra.',
                number_format($montoMaximo, 2),
                number_format($totalCronograma, 2)
            );
        }

        // ─── Destino del desembolso ───
        $destino = $parametros['destino'] ?? 'propio';
        if (! in_array($destino, ['propio', 'tercero', 'gerente'], true)) {
            $errores[] = "Destino del desembolso no válido: '{$destino}'.";
        }
        if ($destino === 'tercero') {
            $t = $parametros['tercero'] ?? [];
            if (trim((string) ($t['nombre'] ?? '')) === '' || ! preg_match('/^\d{8}$/', (string) ($t['dni'] ?? '')) || trim((string) ($t['cuenta'] ?? '')) === '') {
                $errores[] = 'Depósito a tercero: faltan nombre, DNI (8 dígitos) o N° de cuenta del tercero autorizado.';
            }
            if (trim((string) ($t['motivo'] ?? '')) === '') {
                $errores[] = 'Depósito a tercero: falta el motivo (la constancia lo consigna expresamente).';
            }
        }
        if ($destino === 'gerente' && $garantia->tipo_persona !== 'juridica') {
            $errores[] = 'El destino "gerente" solo aplica a personas jurídicas.';
        }

        // ─── Voucher (Anexo 2) ───
        $voucher = $parametros['voucher'] ?? null;
        if ($paraEmitir && ! $voucher) {
            $errores[] = 'Falta el voucher del desembolso (Anexo 2) para emitir el contrato.';
        }
        if ($voucher) {
            $banco = (string) ($voucher['banco'] ?? '');
            $modalidad = (string) ($voucher['modalidad'] ?? '');
            if (! BancosVoucher::esComboValido($banco, $modalidad)) {
                $errores[] = "Voucher: combinación banco/modalidad no válida ('{$banco}'/'{$modalidad}').";
            } else {
                foreach (BancosVoucher::faltantes($banco, $modalidad, $voucher['campos'] ?? []) as $label) {
                    $errores[] = "Voucher: falta el campo obligatorio \"{$label}\".";
                }
                $montoVoucher = (float) str_replace([',', 'S/', ' '], '', (string) ($voucher['campos']['monto'] ?? '0'));
                $importe = round((float) $credit->importe, 2);
                if (abs($montoVoucher - $importe) > 0.01) {
                    $errores[] = sprintf(
                        'El monto del voucher (S/ %s) no coincide con el monto del crédito (S/ %s) — el inserto de la constancia debe reproducir el desembolso exacto.',
                        number_format($montoVoucher, 2),
                        number_format($importe, 2)
                    );
                }
            }
            if ($paraEmitir && empty($voucher['imagen_path'])) {
                $errores[] = 'Falta la imagen del voucher (el Anexo 2 inserta su tenor gráfico).';
            }
        }

        return $errores;
    }
}
