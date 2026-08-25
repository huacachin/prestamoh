<?php

namespace App\Console\Commands;

use App\Models\Vehiculo;
use Carbon\Carbon;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Importa/actualiza la flota de vehículos del grupo desde el Excel
 * "3. Tramites administrativos. Papeletas / 1. Relacion de Vehiculos G-IH-CH".
 *
 * Hojas del libro real:
 *  - "Vehic." (~18 filas): flota activa con placa, año, SOAT, revisión
 *    técnica, vigencia ATU y la empresa del grupo en la columna PROP.
 *    (Inverhuac / Guilmer H. / Corhuac / Comphuac / Gruphuac), que solo
 *    aparece en la primera fila de cada bloque → se arrastra hacia abajo.
 *  - "V.Veh." (~44 filas): vehículos vendidos, mismas columnas → al CREAR
 *    entran con estado 'vendido' (grupos Inverhuac / Guilmer / Corhuac /
 *    Jhosep).
 *  - "Veh. Cresencio" (9 filas): vehículos de un familiar → propietario_tipo
 *    'tercero' con propietario_nombre "Huacachin Medina Cresencio".
 *
 * Matching por PLACA (mayúsculas). Si el vehículo YA existe (muchos vienen
 * del módulo de garantías) solo se COMPLETAN los campos vacíos — anio,
 * soat_vence, revision_tecnica_vence, habilitacion_atu_vence — y NUNCA se
 * pisa client_id, estado ni ningún valor existente (queda como 'actualizado'
 * en el resumen). Si no existe se crea con propietario_tipo 'empresa' y
 * propietario_nombre = la empresa del grupo (texto de la hoja).
 *
 * Fechas documentarias: seriales de Excel y texto (D/M/AAAA, ISO). Valores
 * absurdos (año < 2000 o > 2100 — ej. la vigencia ATU '1932-12-31' real del
 * Excel) → NULL con nota. El texto 'VENCIDO' → NULL con nota (la fecha real
 * no se conoce). Marcadores 'F.', 'x', 'AFOCAT' → NULL sin nota.
 *
 * Modelo, tipo de servicio y renta NO se importan (fuera de alcance): solo
 * se anota "Flota {hoja}: {tipo servicio}" en observaciones al crear.
 *
 * OJO columnas: la subcabecera del Excel está corrida una columna respecto
 * de los datos, y hay filas digitadas una columna a la derecha (se detecta
 * porque la celda de MODELO trae una fecha y se corrige con nota). La
 * columna PROP. solo acepta textos de empresas del grupo conocidas para no
 * contaminar el arrastre con celdas desalineadas.
 *
 * Cada vehículo va en su propia transacción; con --dry-run se hace rollback
 * por registro y se muestra el mismo resumen.
 *
 * Uso:
 *   php artisan legal:importar-flota "ruta/al/1. Relacion de Vehiculos G-IH-CH (13-3-24).xlsx" --dry-run
 *   php artisan legal:importar-flota "ruta/al/1. Relacion de Vehiculos G-IH-CH (13-3-24).xlsx"
 */
class LegalImportarFlota extends Command
{
    protected $signature = 'legal:importar-flota
        {archivo : Ruta del Excel "1. Relacion de Vehiculos G-IH-CH" (hojas Vehic., V.Veh. y Veh. Cresencio)}
        {--dry-run : Simula la importación (rollback por vehículo) y muestra el mismo resumen}';

    protected $description = 'Importa/actualiza la flota de vehículos (hojas Vehic., V.Veh. y Veh. Cresencio) desde el Excel "1. Relacion de Vehiculos G-IH-CH" del área legal';

    /** Filas por bloque al leer una hoja (corta al primer bloque totalmente vacío) */
    private const FILAS_POR_BLOQUE = 200;

    /** Propietario (tercero) de todos los vehículos de la hoja "Veh. Cresencio" */
    private const PROPIETARIO_CRESENCIO = 'Huacachin Medina Cresencio';

    /**
     * Prefijos admitidos en la columna PROP. (empresa del grupo). Protege el
     * arrastre: una fila desalineada puede dejar el nombre de un deudor en
     * esa columna y no debe convertirse en "empresa".
     */
    private const EMPRESAS_GRUPO = ['inverhuac', 'corhuac', 'comphuac', 'gruphuac', 'guilmer', 'jhosep'];

    private const HOJAS = [
        'Vehic.' => ['formato' => 'flota', 'estado' => 'activo'],
        'V.Veh.' => ['formato' => 'flota', 'estado' => 'vendido'],
        'Veh. Cresencio' => ['formato' => 'cresencio', 'estado' => 'activo'],
    ];

    /**
     * Columnas del formato flota (índice 0 = A), ancladas en las FILAS DE
     * DATOS reales (las cabeceras del Excel están corridas). En la hoja
     * "Veh. Cresencio" la placa va en C (índice 2).
     */
    private const COL = [
        'placa' => 4, 'anio' => 5, 'soat' => 7, 'revision' => 8,
        'modelo' => 9, 'servicio' => 10, 'atu' => 11, 'empresa' => 13,
    ];

    private bool $dry = false;

    /** Contadores por hoja: [hoja => [creados, actualizados, completos, errores]] */
    private array $resumen = [];

    /** Notas del proceso: [hoja, vehículo, nota] */
    private array $notas = [];

    /** Errores capturados por fila: [hoja, vehículo, mensaje] */
    private array $errores = [];

    public function handle(): int
    {
        ini_set('memory_limit', '512M'); // margen para PhpSpreadsheet ante filas fantasma

        $archivo = (string) $this->argument('archivo');
        $this->dry = (bool) $this->option('dry-run');

        if (! is_file($archivo)) {
            $this->error("No se encontró el archivo: {$archivo}");

            return self::FAILURE;
        }

        if ($this->dry) {
            $this->warn('DRY RUN — no se persistirá ningún cambio.');
        }

        try {
            $lector = IOFactory::createReaderForFile($archivo);
            $lector->setReadDataOnly(true); // sin estilos: evita agotar memoria con las filas fantasma
            $libro = $lector->load($archivo);
        } catch (Throwable $e) {
            $this->error('No se pudo leer el Excel: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach (self::HOJAS as $nombreHoja => $config) {
            $hoja = $libro->getSheetByName($nombreHoja);
            if ($hoja === null) {
                $this->warn("Hoja \"{$nombreHoja}\" no encontrada en el libro — saltada.");

                continue;
            }
            $this->procesarHoja($hoja, $nombreHoja, $config);
        }

        $libro->disconnectWorksheets();

        if ($this->resumen === []) {
            $this->error('El libro no contiene ninguna de las hojas esperadas (Vehic., V.Veh., Veh. Cresencio).');

            return self::FAILURE;
        }

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    /* ─────────────────────────── Proceso por hoja ─────────────────────────── */

    private function procesarHoja(Worksheet $hoja, string $nombreHoja, array $config): void
    {
        $this->resumen[$nombreHoja] = ['creados' => 0, 'actualizados' => 0, 'completos' => 0, 'errores' => 0];
        $empresa = null; // arrastre de la columna PROP. (solo primera fila de cada bloque)

        foreach ($this->filasConDatos($hoja) as $nroFila => $c) {
            if ($config['formato'] === 'cresencio') {
                $placa = $this->placaValida($c[2] ?? '');
                if ($placa === null) {
                    continue; // título, cabecera, TOTAL o fila sin placa
                }
                $this->importarVehiculo($nombreHoja, $nroFila, [
                    'placa' => $placa,
                    'estado' => $config['estado'],
                    'propietario_tipo' => 'tercero',
                    'propietario_nombre' => self::PROPIETARIO_CRESENCIO,
                    'anio' => null,
                    'soat' => [null, null], 'revision' => [null, null], 'atu' => [null, null],
                    'servicio' => '',
                ]);

                continue;
            }

            $placa = $this->placaValida($c[self::COL['placa']] ?? '');
            if ($placa === null) {
                continue; // título, cabecera, TOTAL, filas de estadísticas al pie
            }

            // Fila digitada una columna a la derecha (pasa en el Excel real):
            // se detecta porque la celda de MODELO trae una fecha.
            $d = $this->pareceFecha($c[self::COL['modelo']] ?? '') ? 1 : 0;
            $cel = fn (string $campo): string => trim($c[self::COL[$campo] + $d] ?? '');
            if ($d === 1) {
                $this->nota($nombreHoja, "{$placa} (fila {$nroFila})", 'Fila desalineada en el Excel (columnas corridas una a la derecha) — corregida al importar');
            }

            // Empresa del grupo: solo textos conocidos actualizan el arrastre
            $textoEmpresa = $this->normalizarEspacios($cel('empresa'));
            if ($textoEmpresa !== '') {
                if ($this->esEmpresaGrupo($textoEmpresa)) {
                    $empresa = $textoEmpresa;
                } else {
                    $this->nota($nombreHoja, "{$placa} (fila {$nroFila})", "Texto '{$textoEmpresa}' en la columna PROP. no reconocido como empresa del grupo — se mantiene '".($empresa ?? '—')."'");
                }
            }

            $anio = null;
            if (preg_match('/^\d{4}$/', $cel('anio')) && (int) $cel('anio') >= 1900 && (int) $cel('anio') <= 2100) {
                $anio = (int) $cel('anio');
            }

            // Tipo de servicio: solo para observaciones ('F.', 'x', '-' no aportan)
            $servicio = $this->normalizarEspacios($cel('servicio'));
            if (mb_strlen($servicio) < 3) {
                $servicio = '';
            }

            $this->importarVehiculo($nombreHoja, $nroFila, [
                'placa' => $placa,
                'estado' => $config['estado'],
                'propietario_tipo' => 'empresa',
                'propietario_nombre' => $empresa,
                'anio' => $anio,
                'soat' => $this->fechaDocumentaria('SOAT', $cel('soat')),
                'revision' => $this->fechaDocumentaria('Revisión técnica', $cel('revision')),
                'atu' => $this->fechaDocumentaria('Habilitación ATU', $cel('atu')),
                'servicio' => $servicio,
            ]);
        }
    }

    /* ───────────────────────── Importación por fila ───────────────────────── */

    private function importarVehiculo(string $hoja, int $fila, array $d): void
    {
        $contador = &$this->resumen[$hoja];
        $referencia = "{$d['placa']} (fila {$fila})";

        [$soat, $notaSoat] = $d['soat'];
        [$revision, $notaRevision] = $d['revision'];
        [$atu, $notaAtu] = $d['atu'];
        $notasFechas = array_values(array_filter([$notaSoat, $notaRevision, $notaAtu]));

        DB::beginTransaction();
        try {
            $vehiculo = Vehiculo::where('placa', $d['placa'])->first();

            if ($vehiculo !== null) {
                // Ya existe (muchos vienen de garantías): solo se completan
                // campos vacíos; client_id y valores existentes NUNCA se pisan.
                $completados = [];
                if (($vehiculo->anio === null || (int) $vehiculo->anio === 0) && $d['anio'] !== null) {
                    $vehiculo->anio = $d['anio'];
                    $completados[] = 'anio';
                }
                foreach (['soat_vence' => $soat, 'revision_tecnica_vence' => $revision, 'habilitacion_atu_vence' => $atu] as $campo => $valor) {
                    if ($vehiculo->{$campo} === null && $valor !== null) {
                        $vehiculo->{$campo} = $valor->toDateString();
                        $completados[] = $campo;
                    }
                }
                if ($completados !== []) {
                    $vehiculo->save();
                }
                $this->dry ? DB::rollBack() : DB::commit();

                if ($completados !== []) {
                    $contador['actualizados']++;
                    $this->nota($hoja, $referencia, 'Ya existía — actualizado (completado: '.implode(', ', $completados).')');
                } else {
                    $contador['completos']++;
                }
                if ($d['estado'] === 'vendido' && $vehiculo->estado !== 'vendido') {
                    $this->nota($hoja, $referencia, "La hoja lo lista como vendido; el estado actual '{$vehiculo->estado}' no se modifica");
                }
                foreach ($notasFechas as $nota) {
                    $this->nota($hoja, $referencia, $nota);
                }

                return;
            }

            $observaciones = array_merge(
                ["Importado del Excel de flota (hoja {$hoja}, fila {$fila})"],
                $d['servicio'] !== '' ? ["Flota {$hoja}: {$d['servicio']}"] : [],
                $notasFechas,
                ($d['propietario_tipo'] === 'empresa' && $d['propietario_nombre'] === null)
                    ? ['Sin empresa del grupo identificada en la hoja'] : [],
            );

            Vehiculo::create([
                'client_id' => null,
                'propietario_tipo' => $d['propietario_tipo'],
                'propietario_nombre' => $d['propietario_nombre'],
                'placa' => $d['placa'],
                'anio' => $d['anio'],
                'soat_vence' => $soat?->toDateString(),
                'revision_tecnica_vence' => $revision?->toDateString(),
                'habilitacion_atu_vence' => $atu?->toDateString(),
                'estado' => $d['estado'],
                'observaciones' => implode('. ', $observaciones),
            ]);

            $this->dry ? DB::rollBack() : DB::commit();

            $contador['creados']++;
            foreach ($notasFechas as $nota) {
                $this->nota($hoja, $referencia, $nota);
            }
            if ($d['propietario_tipo'] === 'empresa' && $d['propietario_nombre'] === null) {
                $this->nota($hoja, $referencia, 'Sin empresa del grupo identificada — propietario_nombre queda vacío');
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $contador['errores']++;
            $this->errores[] = [$hoja, $referencia, mb_substr($e->getMessage(), 0, 160)];
        }
    }

    /* ─────────────────────────────── Lectura ─────────────────────────────── */

    /**
     * Generador de filas con datos (A:Z como texto), leídas por bloques con
     * corte al primer bloque totalmente vacío: los libros del área arrastran
     * filas fantasma (solo formato). No se busca cabecera — cada fila se
     * acepta o descarta por la validez de su placa.
     */
    private function filasConDatos(Worksheet $hoja): Generator
    {
        $tope = $hoja->getHighestDataRow();
        $desde = 1;
        while ($desde <= $tope) {
            $hasta = min($desde + self::FILAS_POR_BLOQUE - 1, $tope);
            $bloque = $hoja->rangeToArray("A{$desde}:Z{$hasta}", null, true, true, false);

            $bloqueVacio = true;
            foreach ($bloque as $i => $celdas) {
                $fila = array_map([$this, 'celdaComoTexto'], $celdas);
                if (implode('', $fila) !== '') {
                    $bloqueVacio = false;
                    yield ($desde + $i) => $fila;
                }
            }
            if ($bloqueVacio) {
                return;
            }
            $desde = $hasta + 1;
        }
    }

    /* ─────────────────────────────── Utilitarios ─────────────────────────────── */

    /** Placa peruana del registro: 6 alfanuméricos con al menos una letra y un dígito */
    private function placaValida(string $valor): ?string
    {
        $placa = strtoupper(trim($valor));

        return preg_match('/^(?=.*[A-Z])(?=.*\d)[A-Z0-9]{6}$/', $placa) === 1 ? $placa : null;
    }

    private function esEmpresaGrupo(string $texto): bool
    {
        $t = $this->sinAcentos(mb_strtolower($texto));
        foreach (self::EMPRESAS_GRUPO as $prefijo) {
            if (str_starts_with($t, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fecha documentaria (SOAT / revisión técnica / habilitación ATU).
     * Devuelve [fecha|null, nota|null]: 'VENCIDO' y los años absurdos
     * (< 2000 o > 2100, ej. la vigencia ATU '1932-12-31' real del Excel)
     * quedan en NULL con nota; 'F.', 'x', 'AFOCAT' en NULL sin nota.
     */
    private function fechaDocumentaria(string $etiqueta, string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return [null, null];
        }

        if (str_contains($this->sinAcentos(mb_strtoupper($valor)), 'VENCIDO')) {
            return [null, "{$etiqueta}: el Excel trae 'VENCIDO' — la fecha real no se conoce, queda vacío"];
        }

        $fecha = $this->parsearFecha($valor);
        if ($fecha === null) {
            return [null, null]; // 'F.', 'x', 'AFOCAT' y otros marcadores sin fecha
        }
        if ($fecha->year < 2000 || $fecha->year > 2100) {
            return [null, "{$etiqueta}: fecha absurda {$fecha->toDateString()} en el Excel — se ignora"];
        }

        return [$fecha, null];
    }

    /** ¿La celda parece una fecha? (serial de Excel plausible o texto de fecha) */
    private function pareceFecha(string $valor): bool
    {
        $valor = trim($valor);
        if ($valor === '') {
            return false;
        }
        if (is_numeric($valor)) {
            return (float) $valor >= 20000; // serial ≈ 1954 en adelante
        }

        return preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $valor) === 1
            || preg_match('/^\d{4}-\d{2}-\d{2}/', $valor) === 1;
    }

    /**
     * Fecha de celda del Excel: serial (lectura sin formato, se acepta
     * cualquier año — el llamador valida el rango para poder ANOTAR los
     * absurdos como 1932), 'D/M/AAAA'-'M/D/AAAA' o ISO.
     */
    private function parsearFecha(string $valor): ?Carbon
    {
        $valor = trim($valor);
        if ($valor === '' || ! preg_match('/\d/', $valor)) {
            return null;
        }

        if (is_numeric($valor)) {
            $serial = (float) $valor;

            return $serial >= 1000 // descarta horas sueltas tipo '00:00:00' y números chicos
                ? Carbon::instance(FechaExcel::excelToDateTimeObject($serial))->startOfDay()
                : null;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valor, $m)) {
            [, $a, $b, $anio] = $m;
            if (checkdate((int) $b, (int) $a, (int) $anio)) {
                return Carbon::create((int) $anio, (int) $b, (int) $a); // D/M/AAAA (uso local)
            }
            if (checkdate((int) $a, (int) $b, (int) $anio)) {
                return Carbon::create((int) $anio, (int) $a, (int) $b); // M/D/AAAA
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            try {
                return Carbon::parse($valor)->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Normaliza una celda cruda a texto: los enteros guardados como número
     * (años, seriales de fecha) se devuelven sin notación científica.
     */
    private function celdaComoTexto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if ((is_float($valor) || is_int($valor)) && floatval($valor) === floor(floatval($valor))) {
            return sprintf('%.0F', $valor);
        }

        // Celdas digitadas a mano traen espacios duros (U+00A0) que trim() no quita
        return trim(str_replace("\u{00A0}", ' ', (string) $valor));
    }

    private function normalizarEspacios(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    private function sinAcentos(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);
    }

    private function nota(string $hoja, string $referencia, string $texto): void
    {
        $this->notas[] = [$hoja, $referencia, $texto];
    }

    /* ──────────────────────────────── Resumen ──────────────────────────────── */

    private function mostrarResumen(): void
    {
        $this->newLine();
        $this->info($this->dry ? 'RESUMEN (DRY RUN — nada se persistió):' : 'RESUMEN DE LA IMPORTACIÓN:');

        $filas = [];
        $total = ['creados' => 0, 'actualizados' => 0, 'completos' => 0, 'errores' => 0];
        foreach ($this->resumen as $hoja => $c) {
            $filas[] = [$hoja, $c['creados'], $c['actualizados'], $c['completos'], $c['errores']];
            foreach ($total as $k => $v) {
                $total[$k] += $c[$k];
            }
        }
        $filas[] = ['TOTAL', $total['creados'], $total['actualizados'], $total['completos'], $total['errores']];

        $this->table(
            ['Hoja', 'Vehículos creados', 'Actualizados (completados)', 'Ya completos (sin cambios)', 'Errores'],
            $filas
        );

        if ($this->notas !== []) {
            $this->warn('Notas (desalineaciones, fechas descartadas y campos completados):');
            $this->table(['Hoja', 'Vehículo', 'Nota'], $this->notas);
        }

        if ($this->errores !== []) {
            $this->error('Errores por fila (transacción revertida, el resto continuó):');
            $this->table(['Hoja', 'Vehículo', 'Error'], $this->errores);
        }
    }
}
