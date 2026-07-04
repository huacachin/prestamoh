<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Distribuye los archivos "en plano" del legacy (cliente_captura/, 62a3/, 62a/)
 * a las carpetas por entidad del storage nuevo, guiándose por las filas de
 * client_attachments / income_attachments / expense_attachments (que genera
 * `legacy:migrate --step=attachments`). Los thumbnails salen de la subcarpeta
 * pe/ del legacy; si no existe el thumb, la galería cae al original.
 *
 * Uso:
 *   php artisan legacy:copy-attachment-files \
 *     --clients=~/Downloads/legacy-imagenes/cliente_captura \
 *     --incomes=~/Downloads/legacy-imagenes/62a3 \
 *     --expenses=~/Downloads/legacy-imagenes/62a
 */
class CopyAttachmentFiles extends Command
{
    protected $signature = 'legacy:copy-attachment-files
                            {--clients= : Carpeta legacy cliente_captura}
                            {--incomes= : Carpeta legacy 62a3 (ingresos)}
                            {--expenses= : Carpeta legacy 62a (egresos)}
                            {--dry-run : Solo reportar, sin copiar}';

    protected $description = 'Copia los archivos de adjuntos del legacy (en plano) a storage/app/public por entidad';

    public function handle(): int
    {
        $jobs = [
            ['opt' => 'clients', 'tabla' => 'client_attachments'],
            ['opt' => 'incomes', 'tabla' => 'income_attachments'],
            ['opt' => 'expenses', 'tabla' => 'expense_attachments'],
        ];

        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');
        $algoCorrio = false;

        foreach ($jobs as $job) {
            $src = $this->option($job['opt']);
            if (! $src) {
                continue;
            }
            $src = rtrim(str_replace('~', $_SERVER['HOME'] ?? '~', $src), '/');
            if (! is_dir($src)) {
                $this->error("{$job['opt']}: no existe la carpeta {$src}");

                continue;
            }
            $algoCorrio = true;

            $copiados = 0;
            $thumbs = 0;
            $faltantes = 0;
            $yaExisten = 0;
            $rows = DB::table($job['tabla'])->get(['filename', 'path', 'thumb_path']);
            $bar = $this->output->createProgressBar($rows->count());
            $bar->start();

            foreach ($rows as $r) {
                $bar->advance();
                $origen = $src.'/'.$r->filename;
                if (! is_file($origen)) {
                    $faltantes++;

                    continue;
                }

                if ($disk->exists($r->path)) {
                    $yaExisten++;
                } elseif (! $dry) {
                    $destAbs = $disk->path($r->path);
                    @mkdir(dirname($destAbs), 0775, true);
                    copy($origen, $destAbs);
                    $copiados++;
                } else {
                    $copiados++;
                }

                // Thumbnail desde pe/ (si el legacy lo generó)
                $origenThumb = $src.'/pe/'.$r->filename;
                if ($r->thumb_path && is_file($origenThumb) && ! $disk->exists($r->thumb_path)) {
                    if (! $dry) {
                        $thumbAbs = $disk->path($r->thumb_path);
                        @mkdir(dirname($thumbAbs), 0775, true);
                        copy($origenThumb, $thumbAbs);
                    }
                    $thumbs++;
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info(sprintf(
                '  %s: %d copiados%s, %d thumbs, %d ya existían, %d sin archivo en el legacy',
                $job['tabla'], $copiados, $dry ? ' (dry-run)' : '', $thumbs, $yaExisten, $faltantes
            ));
        }

        if (! $algoCorrio) {
            $this->warn('Indica al menos una carpeta: --clients= --incomes= --expenses=');

            return 1;
        }

        return 0;
    }
}
