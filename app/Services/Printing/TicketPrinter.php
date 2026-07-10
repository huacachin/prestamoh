<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\MassDeletion;
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

    public function buildPaymentTicketBytes(MassDeletion $masivo): string
    {
        $masivo->loadMissing([
            'credit.client',
            'credit.headquarter:id,name',
            'details.installment:id,num_cuota',
        ]);

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
            $this->pt($printer, (string) config('printer.company_name', 'PRESTAMOS HUACACHIN')."\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            if ($ruc = (string) config('printer.company_ruc', '')) {
                $this->pt($printer, 'RUC '.$ruc."\n");
            }
            if ($addr = (string) config('printer.company_addr', '')) {
                $this->pt($printer, Str::limit($addr, $columns)."\n");
            }
            if ($masivo->credit?->headquarter?->name) {
                $this->pt($printer, $masivo->credit->headquarter->name."\n");
            }

            $this->pt($printer, $double."\n");

            // ── Tipo + número ───────────────────────────────────────────
            $printer->setEmphasis(true);
            $this->pt($printer, "RECIBO DE PAGO\n");
            $printer->setTextSize(2, 2);
            $this->pt($printer, '#'.str_pad((string) $masivo->id, 6, '0', STR_PAD_LEFT)."\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);

            $this->pt($printer, $sep."\n");

            // ── Cliente / fecha ─────────────────────────────────────────
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $fechaHora = trim(($masivo->date?->format('d/m/Y') ?? '').' '.($masivo->time ?? ''));
            $this->pt($printer, $this->row('Fecha:', $fechaHora, $columns));

            $client = $masivo->credit?->client;
            if ($client) {
                $nombre = trim(($client->apellido_pat ?? '').' '.($client->apellido_mat ?? '').' '.($client->nombre ?? ''));
                $this->pt($printer, $this->row('Cliente:', Str::limit($nombre, $columns - 10), $columns));
                if ($doc = $client->documento) {
                    $tipo = $client->tipo_documento ?? 'DNI';
                    $this->pt($printer, $this->row('Doc:', "{$tipo} {$doc}", $columns));
                }
            }

            $this->pt($printer, $this->row('Credito:', '#'.$masivo->credit_id, $columns));

            if ($masivo->user) {
                $this->pt($printer, $this->row('Cobrador:', Str::limit((string) $masivo->user, $columns - 10), $columns));
            }
            if ($masivo->advisor) {
                $this->pt($printer, $this->row('Asesor:', Str::limit((string) $masivo->advisor, $columns - 10), $columns));
            }

            $this->pt($printer, $sep."\n");

            // ── Detalle por tipo (C, I, E, M) ────────────────────────────
            $totales = ['C' => 0.0, 'I' => 0.0, 'E' => 0.0, 'M' => 0.0];
            $cuotasTocadas = [];
            foreach ($masivo->details as $d) {
                $tipo = (string) ($d->tipo ?? '');
                if (isset($totales[$tipo])) {
                    $totales[$tipo] += (float) $d->amount;
                }
                if ($d->installment?->num_cuota !== null && $tipo === 'C') {
                    $cuotasTocadas[] = $d->installment->num_cuota;
                }
            }
            $cuotasTocadas = array_values(array_unique($cuotasTocadas));
            sort($cuotasTocadas);

            if ($cuotasTocadas) {
                $this->pt($printer, $this->row('Cuotas:', implode(',', $cuotasTocadas), $columns));
            }

            $this->pt($printer, $this->row('Capital:', number_format($totales['C'], 2), $columns));
            $this->pt($printer, $this->row('Interes:', number_format($totales['I'], 2), $columns));
            if ($totales['E'] > 0.001) {
                $this->pt($printer, $this->row('Excedente:', number_format($totales['E'], 2), $columns));
            }
            if ($totales['M'] > 0.001) {
                $this->pt($printer, $this->row('Mora:', number_format($totales['M'], 2), $columns));
            }

            $this->pt($printer, $sep."\n");

            // ── Total cobrado ───────────────────────────────────────────
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $this->pt($printer, $this->row('TOTAL', 'S/ '.number_format((float) $masivo->amount, 2), $columns));
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);

            // ── Saldo restante ──────────────────────────────────────────
            $saldo = $this->saldoPendiente((int) $masivo->credit_id);
            $this->pt($printer, $sep."\n");
            $this->pt($printer, $this->row('Saldo restante:', 'S/ '.number_format($saldo, 2), $columns));

            $proxima = $this->proximaCuota((int) $masivo->credit_id);
            if ($proxima) {
                $this->pt($printer, $this->row('Prox. vencimiento:', $proxima, $columns));
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
