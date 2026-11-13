<?php

namespace App\Services\Documentos\Ocr;

/**
 * Lee la imagen del "Formulario de constitución" que entrega el SIGM (Trello #137, 13/11/2026) y
 * devuelve los tres datos que se registran en Documentos → SIGM → Registro.
 *
 * Interfaz por lo mismo que LectorDeVoucher: en los tests se inyecta un doble y no se gasta una
 * llamada de verdad.
 */
interface LectorDeFormularioSigm
{
    /**
     * @param  string  $rutaAbsoluta  imagen del formulario en el disco
     * @return array{formulario: string, folio: string, fecha: string, documentos: list<string>, esConstitucion: bool, dudas: string, modelo: string}
     *
     * formulario "2026-928139" y folio "20261107002358210" tal como se leen (sin espacios); fecha en
     * Y-m-d o '' si no se lee; documentos: los N° de documento de los deudores que aparecen;
     * esConstitucion: false si la imagen es otro formulario (cancelación, modificación…).
     *
     * @throws FormularioSigmIlegible cuando no se puede leer (imagen o servicio)
     */
    public function leer(string $rutaAbsoluta): array;
}
