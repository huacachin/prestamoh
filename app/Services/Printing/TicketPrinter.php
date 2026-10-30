<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\CreditInstallment;
use App\Models\MassDeletion;
use App\Support\AbonosPorCuota;
use App\Support\CancelacionHastaCuota;
use App\Support\RangoCuotas;
use App\Support\Usernames;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use RuntimeException;
use Throwable;

final class TicketPrinter
{
    private static ?\Transliterator $transliterator = null;

    /** @throws RuntimeException */
    public function printTest(): void
    {
        $this->sendBytesLocal($this->buildTestTicketBytes());
    }

    /**
     * Imprime el ticket de UN cobro (mass_deletion).
     *
     * @throws RuntimeException
     */
    public function printPayment(MassDeletion $masivo): void
    {
        $this->sendBytesLocal($this->buildPaymentTicketBytes($masivo));
    }

    public function buildTestTicketBytes(): string
    {
        $buffer = new BufferPrintConnector;
        $printer = new Printer($buffer);

        try {
            $columns = (int) config('printer.columns', 48);
            $line = str_repeat('-', $columns);

            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 2);
            $this->pt($printer, (string) config('printer.company_name', 'PRESTAMOS')."\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $this->pt($printer, "TICKET DE PRUEBA\n");
            $this->pt($printer, now()->format('d/m/Y H:i:s')."\n");
            $this->pt($printer, $line."\n");
            $this->pt($printer, "Si lees esto, la ticketera\n");
            $this->pt($printer, "esta correctamente conectada.\n\n");

            $printer->feed(2);
            $printer->cut();
        } finally {
            $printer->close();
        }

        return $buffer->getBytes();
    }

    /**
     * Datos del recibo de UN cobro, en la misma forma que los pinta el ticket
     * ESC/POS. Fuente única para la ticketera y para la vista 80mm del
     * navegador (resources/views/payments/ticket.blade.php).
     *
     * @return array{
     *   numero:string, fecha_hora:string, sede:?string, cliente:?string,
     *   documento:?string, credit_id:int, cobrador:?string, asesor:?string,
     *   cuotas:array<int,int|string>, resto_cuotas:?array{rotulo:string, monto:float}, capital:float, interes:float,
     *   excedente:float, mora:float, total:float, saldo:float, proxima:?string,
     *   cancelacion:?array{titulo:string, exonera:?string, exonerado:float}
     * }
     */
    /**
     * ¿El recibo lista el monto de cada cuota? Con varias, con alguna amortizada o cuando la única
     * no es todo el total (30/10, Antony, crédito 29186: el recibo del 28/10 decía "Cuotas: 17" y
     * "Mora: 532.80" sin los 167.60 que completaron la cuota, y parecía que la mora era la cuota).
     * Solo se omite con una cuota cuyo monto repite el total.
     *
     * @param  list<array{monto: float, parcial: bool}>  $detalle
     */
    public static function detalleVisible(array $detalle, float $total): bool
    {
        return count($detalle) > 1
            || array_any($detalle, fn ($c) => $c['parcial'])
            || (count($detalle) === 1 && abs($detalle[0]['monto'] - $total) > 0.01);
    }

    /**
     * "Cuota 17", "Cuota 17 (amortizada)" si quedó sin completar, o "Cuota 17 (pago de saldo)" si este
     * cobro completó una cuota que ya tenía abonos.
     *
     * @param  array{num: int, parcial: bool, pago_saldo?: bool}  $dc
     */
    public static function rotuloCuota(array $dc): string
    {
        return 'Cuota '.$dc['num'].($dc['parcial'] ? ' (amortizada)' : (($dc['pago_saldo'] ?? false) ? ' (pago de saldo)' : ''));
    }

    public function paymentTicketData(MassDeletion $masivo): array
    {
        $masivo->loadMissing(['credit.client', 'credit.headquarter:id,name']);
        // Las cuotas se cargan SIEMPRE completas: si quien llama ya trajo la relación con pocas columnas
        // (el controlador del recibo la traía con id,num_cuota), loadMissing la dejaba a medias y el
        // desglose salía sin montos (16/10: el recibo web/PDF no mostraba la amortizada ni su saldo).
        $masivo->load(['details.installment:id,num_cuota,pagado,importe_cuota,importe_interes,importe_excedente,importe_aplicado,interes_aplicado,excedente_aplicado']);

        $totales = ['C' => 0.0, 'I' => 0.0, 'E' => 0.0, 'M' => 0.0];
        // Desglose POR CUOTA (capital+interés+excedente que recibió cada una en
        // ESTE cobro). La mora queda fuera: va como línea global aparte. Se
        // marca "amortizada" SOLO la cuota que quedó sin completar (por montos,
        // no por la bandera pagado) y debajo va "Cuota n (Saldo)" con lo que le
        // faltaba (16/10, Antony). 17/10 (Antony, "en el saldo ir descontando"):
        // el saldo es el de JUSTO DESPUÉS de este cobro, no el actual, así los
        // tres recibos de una cuota pagada en 3 abonos de 100 dicen 325, 225 y
        // 125 también al reimprimirlos, igual que el desglose del cronograma.
        $saldosAlCobro = AbonosPorCuota::saldosAlCobro((int) $masivo->credit_id, (string) ($masivo->date?->format('Y-m-d') ?? ''), (string) $masivo->time);
        $porCuota = [];
        foreach ($masivo->details as $d) {
            $tipo = (string) ($d->tipo ?? '');
            if (isset($totales[$tipo])) {
                $totales[$tipo] += (float) $d->amount;
            }
            if ($d->installment?->num_cuota !== null && in_array($tipo, ['C', 'I', 'E'], true)) {
                $num = (int) $d->installment->num_cuota;
                $porCuota[$num]['monto'] = ($porCuota[$num]['monto'] ?? 0.0) + (float) $d->amount;
                $porCuota[$num]['saldo'] = $saldosAlCobro['cuotas'][(int) $d->installment->id] ?? self::saldoDeCuota($d->installment);
                $porCuota[$num]['parcial'] = $porCuota[$num]['saldo'] > 0.001;
                $porCuota[$num]['cuota'] = (float) $d->installment->importe_cuota + (float) $d->installment->importe_interes + (float) $d->installment->importe_excedente;
            }
        }
        ksort($porCuota);

        $detalleCuotas = [];
        foreach ($porCuota as $num => $info) {
            $detalleCuotas[] = [
                'num' => $num, 'monto' => round($info['monto'], 2), 'parcial' => $info['parcial'], 'saldo' => round($info['saldo'], 2),
                // 30/10: completó una cuota que ya tenía abonos (recibió menos que la cuota entera).
                'pago_saldo' => ! $info['parcial'] && $info['monto'] < $info['cuota'] - 0.01,
            ];
        }
        // Con interés-primero una cuota puede recibir solo interés: entra igual
        // al listado (antes solo se listaban las que recibían capital).
        $cuotasTocadas = array_keys($porCuota);
        $detalleVisible = self::detalleVisible($detalleCuotas, (float) $masivo->amount);

        // 23/10 (Antony): el cobro de "Calcular cancelación" lista una por una solo las cuotas hasta la
        // elegida; lo que fue a las siguientes va en una línea. interes_exonerado marca que canceló.
        $resto = null;
        $cancelacion = null;
        if ($masivo->hasta_cuota !== null) {
            $cancelo = $masivo->interes_exonerado !== null;
            $agrupado = CancelacionHastaCuota::agrupar($detalleCuotas, (int) $masivo->hasta_cuota, $cancelo);
            [$detalleCuotas, $cuotasTocadas, $resto] = [$agrupado['detalle'], $agrupado['cuotas'], $agrupado['resto']];
            if ($cancelo) {
                $cancelacion = CancelacionHastaCuota::lineasRecibo(
                    (int) $masivo->hasta_cuota,
                    (int) CreditInstallment::where('credit_id', $masivo->credit_id)->max('num_cuota'),
                    (float) $masivo->interes_exonerado,
                );
            }
        }

        $client = $masivo->credit?->client;
        $nombre = $client
            ? trim(($client->apellido_pat ?? '').' '.($client->apellido_mat ?? '').' '.($client->nombre ?? ''))
            : null;
        $documento = ($client && $client->documento)
            ? trim(($client->tipo_documento ?? 'DNI').' '.$client->documento)
            : null;

        return [
            'numero' => str_pad((string) $masivo->id, 6, '0', STR_PAD_LEFT),
            'fecha_hora' => trim(($masivo->date?->format('d/m/Y') ?? '').' '.($masivo->time ?? '')),
            'sede' => $masivo->credit?->headquarter?->name,
            'cliente' => $nombre ?: null,
            'documento' => $documento,
            'credit_id' => (int) $masivo->credit_id,
            // El voucher muestra el USERNAME del cobrador (pedido 14/08). Tras
            // el refresh del 17/08 las operaciones migradas traen user vacío y
            // el username del legacy en performed_by; las nuevas guardan el
            // nombre completo y Usernames::de lo resuelve. El asesor ya no se
            // imprime (queda en la data por si otro consumidor lo necesita).
            'cobrador' => $this->usernameCobrador($masivo->performed_by ?: $masivo->user),
            'asesor' => $masivo->advisor ?: null,
            'cuotas' => $cuotasTocadas,
            'detalle_cuotas' => $detalleCuotas,
            'resto_cuotas' => $resto,
            'detalle_cuotas_visible' => $detalleVisible,
            'capital' => round($totales['C'], 2),
            'interes' => round($totales['I'], 2),
            'excedente' => round($totales['E'], 2),
            'mora' => round($totales['M'], 2),
            'total' => round((float) $masivo->amount, 2),
            // Saldo restante del crédito justo después de este cobro (17/10): lo que
            // falta hoy más lo cobrado después; para el último cobro es el actual.
            'saldo' => $saldosAlCobro['credito'],
            'proxima' => $this->proximaCuota((int) $masivo->credit_id),
            'metodo' => $this->metodoPago((int) $masivo->id),
            // "Calcular cancelación" (23/10, Antony): el cobro canceló el crédito cobrando el interés
            // hasta esa cuota; el de las siguientes se exoneró. Null en los demás cobros.
            'cancelacion' => $cancelacion,
        ];
    }

    /** Lo que le falta a una cuota: capital + interés + excedente menos lo aplicado (nunca negativo). */
    private static function saldoDeCuota(CreditInstallment $i): float
    {
        return max(0.0, round(
            (float) $i->importe_cuota + (float) $i->importe_interes + (float) $i->importe_excedente
            - (float) $i->importe_aplicado - (float) $i->interes_aplicado - (float) $i->excedente_aplicado, 2));
    }

    public function buildPaymentTicketBytes(MassDeletion $masivo): string
    {
        $t = $this->paymentTicketData($masivo);

        $buffer = new BufferPrintConnector;
        $printer = new Printer($buffer);

        try {
            $columns = (int) config('printer.columns', 48);
            $sep = str_repeat('-', $columns);
            $double = str_repeat('=', $columns);

            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // ── Logo (si existe) ────────────────────────────────────────
            $this->printLogoIfAvailable($printer);

            // ── Cabecera empresa ────────────────────────────────────────
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 1);
            $this->pt($printer, (string) config('printer.company_name', 'HUACACHIN')."\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            if ($ruc = (string) config('printer.company_ruc', '')) {
                $this->pt($printer, 'RUC '.$ruc."\n");
            }
            if ($addr = (string) config('printer.company_addr', '')) {
                $this->pt($printer, Str::limit($addr, $columns)."\n");
            }
            $this->pt($printer, $double."\n");

            // ── Tipo + número ───────────────────────────────────────────
            $printer->setEmphasis(true);
            $this->pt($printer, "RECIBO DE PAGO\n");
            $printer->setTextSize(2, 2);
            $this->pt($printer, '#'.$t['numero']."\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);

            $this->pt($printer, $sep."\n");

            // ── Cliente / fecha ─────────────────────────────────────────
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->pt($printer, $this->row('Fecha:', $t['fecha_hora'], $columns));
            if (! empty($t['metodo'])) {
                $this->pt($printer, $this->row('Pago:', $t['metodo'], $columns));
            }

            // 08/10 (Antony): el nombre ya no se recorta con "…"; si no cabe al
            // lado del rótulo, baja a la línea siguiente (igual que en las vistas previas).
            if ($t['cliente']) {
                $this->pt($printer, $this->rowAbajo('Cliente:', $t['cliente'], $columns));
            }
            if ($t['documento']) {
                $this->pt($printer, $this->row('Doc:', $t['documento'], $columns));
            }

            $this->pt($printer, $this->row('Credito:', '#'.$t['credit_id'], $columns));

            if ($t['cobrador']) {
                $this->pt($printer, $this->rowAbajo('Cobrador:', $t['cobrador'], $columns));
            }

            $this->pt($printer, $sep."\n");

            // ── Detalle por tipo (C, I, E, M) ────────────────────────────
            if ($t['cuotas']) {
                $this->pt($printer, $this->row('Cuotas:', RangoCuotas::texto($t['cuotas']), $columns));
            }
            // Desglose por cuota: cuánto recibió cada una en este cobro, con
            // marca de amortizada cuando quedó sin completar y, debajo, lo que
            // le falta a esa cuota (16/10).
            if ($t['detalle_cuotas_visible'] ?? false) {
                foreach ($t['detalle_cuotas'] as $dc) {
                    $this->pt($printer, $this->row(
                        ' '.self::rotuloCuota($dc).':',
                        number_format($dc['monto'], 2),
                        $columns
                    ));
                    if ($dc['parcial']) {
                        $this->pt($printer, $this->row(' Cuota '.$dc['num'].' (Saldo):', number_format($dc['saldo'] ?? 0, 2), $columns));
                    }
                }
                if (! empty($t['resto_cuotas'])) {
                    $this->pt($printer, $this->row(' '.$t['resto_cuotas']['rotulo'], number_format($t['resto_cuotas']['monto'], 2), $columns));
                }
            }

            // Cobro de "Calcular cancelación" (23/10): hasta qué cuota se cobró el interés y lo exonerado.
            if (! empty($t['cancelacion'])) {
                // Partidas al ancho del papel: ninguna línea se sale del ticket.
                $this->pt($printer, wordwrap($this->ascii($t['cancelacion']['titulo']), $columns, "\n", true)."\n");
                if ($t['cancelacion']['exonera'] !== null) {
                    $this->pt($printer, wordwrap($this->ascii($t['cancelacion']['exonera']), $columns, "\n", true)."\n");
                    $this->pt($printer, $this->row('Interés exonerado:', number_format($t['cancelacion']['exonerado'], 2), $columns));
                }
            }

            // Capital e Interes globales fuera a pedido del negocio (15/08): el
            // desglose por cuota ya cuenta la historia y el TOTAL cierra. La
            // línea "Excedente" también fuera (16/10, Antony): va dentro de
            // cada cuota y del TOTAL.
            if ($t['mora'] > 0.001) {
                $this->pt($printer, $this->row('Mora:', number_format($t['mora'], 2), $columns));
            }

            $this->pt($printer, $sep."\n");

            // ── Total cobrado ───────────────────────────────────────────
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $this->pt($printer, $this->row('TOTAL', 'S/ '.number_format($t['total'], 2), $columns));
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);

            // ── Saldo restante ──────────────────────────────────────────
            $this->pt($printer, $sep."\n");
            $this->pt($printer, $this->row('Saldo restante:', 'S/ '.number_format($t['saldo'], 2), $columns));

            if ($t['proxima']) {
                $this->pt($printer, $this->row('Prox. vencimiento:', $t['proxima'], $columns));
            }

            $this->pt($printer, $double."\n");

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->pt($printer, "Gracias por su pago!\n");
            $this->pt($printer, "Conserve este recibo.\n");

            $printer->feed(2);
            $printer->cut();
        } finally {
            $printer->close();
        }

        return $buffer->getBytes();
    }

    /** Username del cobrador — lógica compartida en App\Support\Usernames. */
    private function usernameCobrador(?string $nombre): ?string
    {
        return Usernames::de($nombre);
    }

    private function saldoPendiente(int $creditId): float
    {
        $r = DB::table('credit_installments')
            ->where('credit_id', $creditId)
            ->selectRaw('
                SUM(importe_cuota) - SUM(importe_aplicado) AS sc,
                SUM(importe_interes) - SUM(interes_aplicado) AS si,
                SUM(importe_excedente) - SUM(excedente_aplicado) AS se
            ')->first();

        return round((float) ($r->sc ?? 0) + (float) ($r->si ?? 0) + (float) ($r->se ?? 0), 2);
    }

    /**
     * Método de pago del cobro: si tiene un egreso "Dep. ..." vinculado
     * (pago vía depósito), devuelve "Canal - Banco" (p. ej. "Yape - Bcp")
     * parseando la descripción estandarizada que genera el sistema:
     * "Dep. {Banco} {Cuenta} {Cliente} ({Canal})[ dd/mm]".
     */
    private function metodoPago(int $massDeletionId): ?string
    {
        $detail = DB::table('expenses')
            ->where('mass_deletion_id', $massDeletionId)
            ->value('detail');

        if (! $detail) {
            return null;
        }

        $banco = preg_match('/^Dep\.\s+(\S+)/u', (string) $detail, $m) ? $m[1] : null;
        $canal = preg_match('/\(([^)]+)\)/u', (string) $detail, $m2) ? $m2[1] : null;

        if (! $banco && ! $canal) {
            return null;
        }

        return trim(($canal ?? 'Depósito').($banco ? " - {$banco}" : ''));
    }

    private function proximaCuota(int $creditId): ?string
    {
        $f = DB::table('credit_installments')
            ->where('credit_id', $creditId)
            ->where('pagado', 0)
            ->where('importe_cuota', '>', 0)
            ->min('fecha_vencimiento');

        return $f ? Carbon::parse($f)->format('d/m/Y') : null;
    }

    /** @throws RuntimeException */
    private function sendBytesLocal(string $bytes): void
    {
        $connector = $this->openConnector();
        try {
            $connector->write($bytes);
            $connector->finalize();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No se pudo enviar a la impresora local: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    /** @throws RuntimeException */
    private function openConnector(): PrintConnector
    {
        $driver = (string) config('printer.driver', 'cups');

        try {
            return match ($driver) {
                'cups' => new CupsRawPrintConnector((string) config('printer.cups_name')),
                'windows' => new WindowsPrintConnector((string) config('printer.windows_path')),
                'network' => new NetworkPrintConnector(
                    (string) config('printer.network_host'),
                    (int) config('printer.network_port'),
                ),
                'file' => new FilePrintConnector((string) config('printer.file_path')),
                default => throw new RuntimeException("Driver de impresora desconocido: {$driver}"),
            };
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No se pudo abrir el conector de la impresora: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    private function row(string $left, string $right, int $width): string
    {
        $left = $this->ascii($left);
        $right = $this->ascii($right);
        $space = max(1, $width - mb_strlen($left) - mb_strlen($right));

        return $left.str_repeat(' ', $space).$right."\n";
    }

    /**
     * Como row(), pero si el valor no cabe al lado del rótulo se parte por
     * palabras: el primer tramo va en la misma línea que el rótulo y los
     * siguientes debajo, todos alineados a la derecha (09/10, Antony: "Cliente
     * y el nombre en la misma línea"; antes se recortaba con "…").
     */
    public function rowAbajo(string $left, string $right, int $width): string
    {
        $left = $this->ascii($left);
        $right = $this->ascii($right);
        if (mb_strlen($left) + 1 + mb_strlen($right) <= $width) {
            return $this->row($left, $right, $width);
        }
        $anchoValor = max(8, $width - mb_strlen($left) - 1);
        $tramos = explode("\n", wordwrap($right, $anchoValor, "\n", true));
        $out = $this->row($left, array_shift($tramos), $width);
        foreach ($tramos as $linea) {
            $out .= str_pad($linea, $width, ' ', STR_PAD_LEFT)."\n";
        }

        return $out;
    }

    private function pt(Printer $printer, string $text): void
    {
        $printer->text($this->ascii($text));
    }

    private function ascii(string $text): string
    {
        if (self::$transliterator === null) {
            self::$transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
        }
        $r = self::$transliterator?->transliterate($text);

        return $r ?? $text;
    }

    /**
     * Imprime el logo si está habilitado y el archivo existe.
     * Cualquier fallo se loguea pero no rompe la impresión del ticket.
     */
    private function printLogoIfAvailable(Printer $printer): void
    {
        if (! (bool) config('printer.print_logo', true)) {
            return;
        }

        $rel = (string) config('printer.logo_path', 'printer/logo.png');
        if ($rel === '') {
            return;
        }

        $path = storage_path('app/public/'.ltrim($rel, '/'));
        if (! file_exists($path)) {
            return;
        }

        try {
            // Normalización: re-codifica a PNG b/n y redimensiona al máx ancho
            // físico de la ticketera (~576px en 80mm). Logos grandes tardan eternidades.
            $maxWidth = (int) config('printer.logo_max_width', 220);
            $tmp = $this->normalizeLogoToPng($path, $maxWidth);
            if ($tmp === null) {
                return;
            }

            try {
                $img = EscposImage::load($tmp, false);
                $printer->bitImage($img);
                // Margen de respiro entre el logo y el nombre de la empresa.
                $printer->feed(2);
            } finally {
                @unlink($tmp);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function normalizeLogoToPng(string $path, int $maxWidth): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return null;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return null;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw <= 0 || $sh <= 0) {
            imagedestroy($src);

            return null;
        }

        if ($sw > $maxWidth) {
            $tw = $maxWidth;
            $th = (int) round($sh * ($maxWidth / $sw));
            $dst = imagecreatetruecolor($tw, $th);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $tw, $th, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
            imagedestroy($src);
            $src = $dst;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'logo-').'.png';
        $ok = imagepng($src, $tmp);
        imagedestroy($src);

        return $ok ? $tmp : null;
    }
}
