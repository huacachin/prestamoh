<?php

namespace App\Services\Documentos\Ocr;

/**
 * Lee la imagen de un voucher y devuelve su transcripción.
 *
 * Es una interfaz para que el flujo del Anexo 2 no dependa del proveedor: en
 * los tests se inyecta un doble y no se gasta una llamada de verdad.
 */
interface LectorDeVoucher
{
    /**
     * @param  string  $rutaAbsoluta  imagen del voucher en el disco
     * @param  string  $banco  clave de BancosVoucher::BANCOS como PISTA; vacío para que la lectura lo identifique (18/09)
     * @param  string  $modalidad  clave de BancosVoucher::MODALIDADES, ídem
     * @return array{transcripcion: string, monto: string, beneficiario: string, dudas: string, banco: string, modalidad: string, modelo: string}
     *
     * banco/modalidad: la pista si se dio; si no, lo identificado (claves del
     * catálogo) o '' si no coincide con ninguno.
     *
     * @throws VoucherIlegible cuando no se puede leer (imagen o servicio)
     */
    public function leer(string $rutaAbsoluta, string $banco, string $modalidad): array;
}
