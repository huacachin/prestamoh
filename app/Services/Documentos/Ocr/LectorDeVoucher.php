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
     * @param  string  $banco  clave de BancosVoucher::BANCOS (contexto de lectura)
     * @param  string  $modalidad  clave de BancosVoucher::MODALIDADES
     * @return array{transcripcion: string, monto: string, beneficiario: string, dudas: string, modelo: string}
     *
     * @throws VoucherIlegible cuando no se puede leer (imagen o servicio)
     */
    public function leer(string $rutaAbsoluta, string $banco, string $modalidad): array;
}
