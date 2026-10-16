<?php

namespace App\Support\Documentos;

/**
 * Maqueta del CONTRATO (16/10/2026): márgenes y tipografía por nivel de ajuste.
 *
 * El margen DERECHO —el costado exterior de la hoja, donde los deudores firman
 * cada página— pasa de 1.8 a 3.2 cm porque la firma de varios clientes no
 * entraba (pedido de Antony). El izquierdo baja de 2.4 a 2 cm (sigue dando
 * para el legajo) y los verticales se aprietan un poco para compensar el
 * ancho de texto perdido: el contrato no puede pasar de MAX_HOJAS (regla del
 * área legal desde el 05/09; las maestras en papel tienen 5).
 *
 * Los niveles 1..MAX_NIVEL son la RESERVA: GeneradorContrato::generar()
 * renderiza el PDF y, si pasa del tope (cláusulas adicionales largas,
 * domicilios kilométricos, dos deudores…), sube de nivel hasta que cabe. El
 * nivel con el que cupo queda en el snapshot ('ajuste'), así el Word, la
 * copia de impresión y cualquier re-render salen igual que el PDF emitido.
 * La previa del wizard siempre usa el nivel 0 (no pagina, no hace falta).
 */
final class MaquetaContrato
{
    public const MAX_HOJAS = 5;

    public const MAX_NIVEL = 3;

    /**
     * margenes = arriba derecha abajo izquierda (orden CSS).
     *
     * @var array<int, array{fuente: string, interlineado: string, parrafo: string, margenes: string}>
     */
    private const NIVELES = [
        // Nivel 0 = la maqueta de siempre (6.5pt) con el margen exterior ancho;
        // el interlineado 1.2 → 1.17 y los verticales 1.8/1.5 → 1.5/1.3 cm
        // compensan el centímetro de texto que se pierde a lo ancho.
        0 => ['fuente' => '6.5pt', 'interlineado' => '1.17', 'parrafo' => '4px', 'margenes' => '1.5cm 3.2cm 1.3cm 2cm'],
        1 => ['fuente' => '6.5pt', 'interlineado' => '1.13', 'parrafo' => '3px', 'margenes' => '1.5cm 3.2cm 1.3cm 2cm'],
        2 => ['fuente' => '6.3pt', 'interlineado' => '1.12', 'parrafo' => '3px', 'margenes' => '1.4cm 3.2cm 1.2cm 2cm'],
        3 => ['fuente' => '6.1pt', 'interlineado' => '1.1', 'parrafo' => '2px', 'margenes' => '1.3cm 3.2cm 1.1cm 1.8cm'],
    ];

    /** @return array{fuente: string, interlineado: string, parrafo: string, margenes: string} */
    public static function nivel(int $ajuste): array
    {
        return self::NIVELES[max(0, min($ajuste, self::MAX_NIVEL))];
    }

    /** Hojas reales de un PDF de dompdf: cuenta los objetos /Type /Page (no /Pages). */
    public static function hojas(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page\b#', $pdf);
    }
}
