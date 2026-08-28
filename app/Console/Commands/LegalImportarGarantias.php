<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Garantia;
use App\Models\SigmAviso;
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
 * Importa el histórico de garantías mobiliarias vehiculares desde el Excel
 * "B. Registro de garantías constituidas - SIGM" del área legal.
 *
 * Estructura real del libro: una hoja por tasa mensual ("G.M 3%", "G.M 4%",
 * "G.M 5%", "G.M 5.2%", "G.M 8%", "G.M 10%"; "PJ. Embargo" se ignora).
 * Tras la cabecera doble, cada fila COMPLETA (nombre + DNI + placa) abre una
 * garantía; las filas siguientes con solo N° de formulario son sus avisos de
 * RENOVACIÓN, y las filas con solo nombre + DNI (sin placa ni formulario)
 * son el co-deudor de la garantía anterior.
 *
 * Reglas de importación (nunca autocorrige, solo marca requiere_revision):
 *  - Cliente por clients.documento == DNI; si no existe NO se crea: la
 *    garantía se omite y queda anotada en el resumen.
 *  - Crédito: el último con situacion 'Activo' (o el más reciente); sin
 *    crédito la garantía también se omite.
 *  - Vehículo por placa; si no existe se crea a nombre del deudor. Si la
 *    placa ya está registrada a OTRO cliente se usa igual pero se marca
 *    requiere_revision.
 *  - Un aviso cuyo N° de formulario (o folio) ya existe en BD se salta; si
 *    el ya existente es el de CONSTITUCIÓN, la garantía completa se omite
 *    como "ya importada" (esto hace al comando idempotente).
 *  - "FECHA FINAL DEL AVISO" viene como 'Indeterminado' → vigencia_hasta
 *    de los avisos queda NULL (no se inventan los 5 años del D. Leg. 1400).
 *
 * Cada garantía va en su propia transacción: una que falla no tumba el
 * resto. Con --dry-run se hace rollback por garantía y se muestra el mismo
 * resumen sin persistir nada.
 *
 * OJO memoria: la hoja "G.M 10%" arrastra más de un millón de filas fantasma
 * (formato sin datos), por eso se lee con setReadDataOnly() y por bloques con
 * corte al primer bloque vacío — nunca toArray() de la hoja completa.
 *
 * Uso:
 *   php artisan legal:importar-garantias "ruta/al/B. 2026. Registro....xlsx" --dry-run
 *   php artisan legal:importar-garantias "ruta/al/B. 2026. Registro....xlsx"
 *   php artisan legal:importar-garantias archivo.xlsx --tasa-hoja=3 --tasa-hoja=5.2
 */
class LegalImportarGarantias extends Command
{
    protected $signature = 'legal:importar-garantias
        {archivo : Ruta del Excel "B. Registro de garantías constituidas - SIGM"}
        {--dry-run : Simula la importación (rollback por garantía) y muestra el mismo resumen}
        {--tasa-hoja=* : Importa solo las hojas de estas tasas mensuales (ej. --tasa-hoja=3 --tasa-hoja=5.2); sin la opción, todas las hojas "G.M"}';

    protected $description = 'Importa el histórico de garantías SIGM (garantías, vehículos y avisos) desde el Excel "B. Registro de garantías constituidas" del área legal';

    /** Filas por bloque al leer una hoja (corta al primer bloque totalmente vacío) */
    private const FILAS_POR_BLOQUE = 200;

    /** Columnas del rango A:R (índice 0) según la cabecera doble del libro */
    private const COL_NOMBRE = 2;      // Apellidos y nombres

    private const COL_DNI = 3;         // DNI (o RUC de 11 dígitos)

    private const COL_CORREO = 4;      // Correo electrónico

    private const COL_PLACA = 5;       // Placa

    private const COL_MARCA = 6;       // Marca

    private const COL_MODELO = 7;      // Modelo

    private const COL_MOTOR = 8;       // N° de motor

    private const COL_SERIE = 9;       // N° de serie

    private const COL_FECHA = 10;      // Fecha de operación (serial Excel o texto)

    private const COL_FORMULARIO = 11; // N° de formulario (AAAA-NNNNNN)

    private const COL_FOLIO = 12;      // Folio causal

    private const COL_ESTADO = 17;     // ESTADO (VIGENTE / Cancelado / CANCELADO)

    private bool $dry = false;

    /** Contadores por hoja: [hoja => [creadas, omitidas, avisos, revision, errores]] */
    private array $resumen = [];

    /** Notas del proceso (omisiones, avisos saltados, marcas de revisión): [hoja, deudor, nota] */
    private array $notas = [];

    /** Errores capturados por garantía: [hoja, deudor, mensaje] */
    private array $errores = [];

    public function handle(): int
    {
        // Filas fantasma de "G.M 10%" (ver docblock): margen holgado para PhpSpreadsheet
        ini_set('memory_limit', '1024M');

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

        $filtroTasas = array_map([$this, 'normalizarTasa'], (array) $this->option('tasa-hoja'));

        foreach ($libro->getSheetNames() as $nombreHoja) {
            if (! str_starts_with(trim($nombreHoja), 'G.M')) {
                continue; // "PJ. Embargo" y cualquier hoja ajena al registro por tasa
            }

            $tasa = $this->tasaDeHoja($nombreHoja);
            if ($filtroTasas !== [] && ! in_array($this->normalizarTasa($tasa), $filtroTasas, true)) {
                continue;
            }

            $this->procesarHoja($libro->getSheetByName($nombreHoja), $nombreHoja, $tasa);
        }

        $libro->disconnectWorksheets();

        if ($this->resumen === []) {
            $this->warn('Ninguna hoja "G.M" coincidió con el filtro indicado; no se procesó nada.');

            return self::FAILURE;
        }

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    /* ─────────────────────────── Proceso por hoja ─────────────────────────── */

    private function procesarHoja(Worksheet $hoja, string $nombreHoja, string $tasa): void
    {
        $this->resumen[$nombreHoja] = ['creadas' => 0, 'omitidas' => 0, 'avisos' => 0, 'revision' => 0, 'errores' => 0];

        $grupos = $this->agruparGarantias($hoja, $nombreHoja);
        $this->line(" · {$nombreHoja}: ".count($grupos).' garantía(s) detectadas en el Excel');

        foreach ($grupos as $grupo) {
            $this->importarGarantia($grupo, $nombreHoja, $tasa);
        }
    }

    /**
     * Recorre las filas de la hoja y las agrupa en garantías:
     *  - nombre + placa            → fila completa: abre garantía (su fila trae la constitución),
     *  - nombre + formulario       → garantía SIN datos de vehículo (caso raro del área: se marca revisión),
     *  - solo nombre               → co-deudor de la garantía abierta,
     *  - solo formulario           → aviso de renovación de la garantía abierta.
     */
    private function agruparGarantias(Worksheet $hoja, string $nombreHoja): array
    {
        $grupos = [];
        $actual = null;

        foreach ($this->filasConDatos($hoja) as $nroFila => $fila) {
            $nombre = $fila[self::COL_NOMBRE];
            $dni = preg_replace('/\D/', '', $fila[self::COL_DNI]);
            $placa = strtoupper(preg_replace('/\s+/', '', $fila[self::COL_PLACA]));
            $formulario = $fila[self::COL_FORMULARIO];
            $estado = $fila[self::COL_ESTADO];

            $aviso = [
                'formulario' => $formulario,
                'folio' => $fila[self::COL_FOLIO],
                'fecha' => $fila[self::COL_FECHA],
                'fila' => $nroFila,
            ];

            if ($nombre !== '' && ($placa !== '' || $formulario !== '')) {
                // Fila completa (o sin vehículo pero con formulario): nueva garantía
                if ($actual !== null) {
                    $grupos[] = $actual;
                }
                $actual = [
                    'fila' => $nroFila,
                    'nombre' => $nombre,
                    'dni' => $dni,
                    'correo' => $fila[self::COL_CORREO],
                    'placa' => $placa,
                    'marca' => $fila[self::COL_MARCA],
                    'modelo' => $fila[self::COL_MODELO],
                    'motor' => $fila[self::COL_MOTOR],
                    'serie' => $fila[self::COL_SERIE],
                    'estado_excel' => $estado,
                    'avisos' => ($formulario !== '' || $aviso['fecha'] !== '') ? [$aviso] : [],
                    'codeudores' => [],
                ];
            } elseif ($nombre !== '') {
                // Co-deudor de la garantía abierta (fila sin numerar, sin placa ni formulario)
                if ($actual !== null) {
                    $actual['codeudores'][] = ['nombre' => $nombre, 'dni' => $dni];
                } else {
                    $this->nota($nombreHoja, $nombre, "Fila {$nroFila}: co-deudor sin garantía previa — ignorado");
                }
            } elseif ($formulario !== '' || $aviso['fecha'] !== '') {
                // Renovación: pertenece a la última garantía completa anterior
                if ($actual !== null) {
                    $actual['avisos'][] = $aviso;
                    if ($estado !== '') {
                        $actual['estado_excel'] = $estado; // el estado de la última fila manda
                    }
                } else {
                    $this->nota($nombreHoja, '—', "Fila {$nroFila}: formulario {$formulario} sin garantía previa — ignorado");
                }
            }
        }

        if ($actual !== null) {
            $grupos[] = $actual;
        }

        return $grupos;
    }

    /**
     * Generador de filas con datos (A:R normalizadas a texto), leídas por
     * bloques. Corta al primer bloque totalmente vacío: las hojas del área
     * arrastran filas fantasma (solo formato) hasta el final del libro.
     */
    private function filasConDatos(Worksheet $hoja): Generator
    {
        // La cabecera doble empieza en la fila cuyo A dice "N°"; los datos, dos filas después
        $inicio = null;
        foreach ($hoja->rangeToArray('A1:A10', null, true, true, false) as $i => $fila) {
            if (trim((string) $fila[0]) === 'N°') {
                $inicio = $i + 3; // $i es índice 0 → fila real +1, más las 2 filas de cabecera
                break;
            }
        }
        if ($inicio === null) {
            return;
        }

        $tope = $hoja->getHighestDataRow();
        $desde = $inicio;
        while ($desde <= $tope) {
            $hasta = min($desde + self::FILAS_POR_BLOQUE - 1, $tope);
            $bloque = $hoja->rangeToArray("A{$desde}:R{$hasta}", null, true, true, false);

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

    /* ───────────────────────── Importación por garantía ───────────────────────── */

    private function importarGarantia(array $g, string $nombreHoja, string $tasa): void
    {
        $contador = &$this->resumen[$nombreHoja];
        $deudor = "{$g['nombre']} ({$g['dni']})";

        // 1) Cliente por documento — NUNCA se crean clientes desde el importador
        $cliente = $this->buscarCliente($g['dni']);
        if ($cliente === null) {
            $contador['omitidas']++;
            $this->nota($nombreHoja, $deudor, "Cliente no encontrado: {$g['nombre']} ({$g['dni']}) — garantía omitida (requiere revisión)");

            return;
        }

        // 2) Crédito: último Activo o, en su defecto, el más reciente
        $credito = $cliente->credits()->where('situacion', 'Activo')->latest('id')->first()
            ?? $cliente->credits()->latest('id')->first();
        if ($credito === null) {
            $contador['omitidas']++;
            $this->nota($nombreHoja, $deudor, 'Cliente sin créditos registrados — garantía omitida');

            return;
        }

        // Idempotencia: si el formulario de la CONSTITUCIÓN ya está en BD, la garantía ya fue importada
        $constitucion = $g['avisos'][0] ?? null;
        if ($constitucion !== null && $constitucion['formulario'] !== ''
            && SigmAviso::where('nro_formulario', $constitucion['formulario'])->exists()) {
            $contador['omitidas']++;
            $this->nota($nombreHoja, $deudor, "Ya importada: el formulario {$constitucion['formulario']} ya existe en BD");

            return;
        }

        $notasRevision = [];
        if (! in_array(strlen($g['dni']), [8, 11], true)) {
            $notasRevision[] = "DNI inválido en el Excel: '{$g['dni']}' (se esperaban 8 u 11 dígitos)";
        }
        if ($g['placa'] === '') {
            $notasRevision[] = 'Sin datos de vehículo en el Excel';
        }
        if ($g['avisos'] === []) {
            $notasRevision[] = 'Sin aviso de constitución en el Excel';
        }

        DB::beginTransaction();
        try {
            // 3) Vehículo por placa (se crea si no existe; jamás se reasigna el dueño)
            $vehiculo = null;
            if ($g['placa'] !== '') {
                $vehiculo = Vehiculo::where('placa', $g['placa'])->first();
                if ($vehiculo !== null && $vehiculo->client_id !== null && $vehiculo->client_id !== $cliente->id) {
                    $notasRevision[] = "Placa registrada a otro cliente (vehículo #{$vehiculo->id})";
                }
                if ($vehiculo === null) {
                    $vehiculo = Vehiculo::create([
                        'client_id' => $cliente->id,
                        'propietario_tipo' => 'cliente',
                        'placa' => $g['placa'],
                        'marca' => $g['marca'] ?: null,
                        'modelo' => $g['modelo'] ?: null,
                        'nro_motor' => $g['motor'] ?: null,
                        'nro_serie' => $g['serie'] ?: null,
                        'estado' => 'activo',
                    ]);
                }
            }

            // Co-deudor: primera fila adicional cuyo documento exista como cliente
            $codeudorId = null;
            foreach ($g['codeudores'] as $cod) {
                $codCliente = $this->buscarCliente($cod['dni']);
                if ($codCliente !== null && $codeudorId === null) {
                    $codeudorId = $codCliente->id;
                } else {
                    $notasRevision[] = $codCliente === null
                        ? "Co-deudor no encontrado: {$cod['nombre']} ({$cod['dni']})"
                        : "Co-deudor adicional sin campo donde registrarse: {$cod['nombre']} ({$cod['dni']})";
                }
            }

            // 4) Garantía (tipo_persona jurídica cuando el documento es un RUC de 11 dígitos)
            $garantia = Garantia::create([
                'credit_id' => $credito->id,
                'client_id' => $cliente->id,
                'codeudor_client_id' => $codeudorId,
                'tipo' => 'mobiliaria_vehicular',
                'tipo_persona' => strlen($g['dni']) === 11 ? 'juridica' : 'natural',
                'gps' => false,
                'monto_gravamen' => null,
                'estado' => 'en_constitucion',
                'observaciones' => "Importada de {$nombreHoja} — tasa {$tasa}% mensual",
            ]);
            if ($vehiculo !== null) {
                $garantia->vehiculos()->attach($vehiculo->id, ['es_bien_futuro' => false, 'orden' => 1]);
            }

            // 5) Avisos SIGM: el primero es la constitución; los siguientes, renovaciones
            $primero = true;
            foreach ($g['avisos'] as $aviso) {
                $tipo = $primero ? 'constitucion' : 'renovacion';
                $primero = false;

                $fecha = $this->parsearFecha($aviso['fecha']);
                if ($fecha === null) {
                    $notasRevision[] = "Aviso {$tipo} (fila {$aviso['fila']}) sin fecha de operación válida — no importado";

                    continue;
                }

                $formulario = $aviso['formulario'] !== '' ? $aviso['formulario'] : null;
                $folio = $aviso['folio'] !== '' ? $aviso['folio'] : null;
                if (($formulario !== null && SigmAviso::where('nro_formulario', $formulario)->exists())
                    || ($folio !== null && SigmAviso::where('folio', $folio)->exists())) {
                    $this->nota($nombreHoja, $deudor, "Aviso saltado: formulario {$formulario} / folio {$folio} ya existe en BD");

                    continue;
                }

                SigmAviso::create([
                    'garantia_id' => $garantia->id,
                    'tipo' => $tipo,
                    'nro_formulario' => $formulario,
                    'folio' => $folio,
                    'fecha_presentacion' => $fecha->toDateString(),
                    'vigencia_hasta' => null, // el Excel dice 'Indeterminado'
                    'tasa' => SigmAviso::TASA_SIGM,
                    'estado' => 'registrado',
                ]);
                $contador['avisos']++;
            }

            // Estado y vigencia a partir del historial recién importado
            $garantia->sincronizarConAvisos();
            $garantia->refresh();

            // El registro del área manda: si el Excel la da por cancelada, se fuerza con nota
            if ($this->esCancelado($g['estado_excel']) && $garantia->estado !== 'cancelada') {
                $notaEstado = "Estado forzado a 'cancelada' según el Excel (el cálculo por avisos dio '{$garantia->estado}')";
                $garantia->update([
                    'estado' => 'cancelada',
                    'observaciones' => $garantia->observaciones.'. '.$notaEstado,
                ]);
                $this->nota($nombreHoja, $deudor, $notaEstado);
            }

            if ($notasRevision !== []) {
                $garantia->update([
                    'requiere_revision' => true,
                    'observaciones' => $garantia->observaciones.'. REVISAR: '.implode('; ', $notasRevision),
                ]);
                $contador['revision']++;
                foreach ($notasRevision as $nota) {
                    $this->nota($nombreHoja, $deudor, $nota);
                }
            }

            $this->dry ? DB::rollBack() : DB::commit();
            $contador['creadas']++;
        } catch (Throwable $e) {
            DB::rollBack();
            $contador['errores']++;
            $this->errores[] = [$nombreHoja, $deudor, mb_substr($e->getMessage(), 0, 160)];
        }
    }

    /* ─────────────────────────────── Utilitarios ─────────────────────────────── */

    /**
     * Busca el cliente por documento. Si el Excel trae 7 dígitos (DNI que
     * perdió el cero inicial al guardarse como número), prueba también con
     * el cero a la izquierda — sin modificar nunca al cliente.
     */
    private function buscarCliente(string $dni): ?Client
    {
        if ($dni === '') {
            return null;
        }

        $cliente = Client::where('documento', $dni)->first();
        if ($cliente === null && strlen($dni) === 7) {
            $cliente = Client::where('documento', str_pad($dni, 8, '0', STR_PAD_LEFT))->first();
        }

        return $cliente;
    }

    /**
     * Normaliza una celda cruda a texto: los enteros guardados como número
     * (folios, seriales de fecha) se devuelven sin notación científica.
     */
    private function celdaComoTexto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if ((is_float($valor) || is_int($valor)) && floatval($valor) === floor(floatval($valor))) {
            return sprintf('%.0F', $valor);
        }

        return trim((string) $valor);
    }

    /**
     * 'Fecha de operación' del Excel: serial de Excel (lectura sin formato),
     * 'M/D/AAAA' (lectura con formato, estilo US de PhpSpreadsheet) o ISO.
     */
    private function parsearFecha(string $valor): ?Carbon
    {
        $valor = trim($valor);
        if ($valor === '' || ! preg_match('/\d/', $valor)) {
            return null; // vacío o textos tipo 'Indeterminado'
        }

        if (is_numeric($valor)) {
            $serial = (float) $valor;

            return $serial > 25569 // 1970-01-01: descarta números que no son fechas
                ? Carbon::instance(FechaExcel::excelToDateTimeObject($serial))->startOfDay()
                : null;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valor, $m)) {
            [, $a, $b, $anio] = $m;
            if (checkdate((int) $a, (int) $b, (int) $anio)) {
                return Carbon::create((int) $anio, (int) $a, (int) $b); // M/D/AAAA
            }
            if (checkdate((int) $b, (int) $a, (int) $anio)) {
                return Carbon::create((int) $anio, (int) $b, (int) $a); // D/M/AAAA
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

    /** Tasa mensual del nombre de la hoja: 'G.M 5.2%' → '5.2' */
    private function tasaDeHoja(string $nombreHoja): string
    {
        return preg_match('/(\d+(?:\.\d+)?)\s*%/', $nombreHoja, $m) ? $m[1] : '?';
    }

    /** Normaliza el valor de --tasa-hoja: acepta '3', '3%', 'G.M 3%'... → '3' */
    private function normalizarTasa(string $tasa): string
    {
        return trim(str_replace(['g.m', 'gm', '%'], '', mb_strtolower($tasa)));
    }

    private function esCancelado(string $estadoExcel): bool
    {
        return str_contains(mb_strtolower($estadoExcel), 'cancel');
    }

    private function nota(string $hoja, string $deudor, string $texto): void
    {
        $this->notas[] = [$hoja, $deudor, $texto];
    }

    /* ──────────────────────────────── Resumen ──────────────────────────────── */

    private function mostrarResumen(): void
    {
        $this->newLine();
        $this->info($this->dry ? 'RESUMEN (DRY RUN — nada se persistió):' : 'RESUMEN DE LA IMPORTACIÓN:');

        $filas = [];
        $total = ['creadas' => 0, 'omitidas' => 0, 'avisos' => 0, 'revision' => 0, 'errores' => 0];
        foreach ($this->resumen as $hoja => $c) {
            $filas[] = [$hoja, $c['creadas'], $c['omitidas'], $c['avisos'], $c['revision'], $c['errores']];
            foreach ($total as $k => $v) {
                $total[$k] += $c[$k];
            }
        }
        $filas[] = ['TOTAL', $total['creadas'], $total['omitidas'], $total['avisos'], $total['revision'], $total['errores']];

        $this->table(
            ['Hoja', 'Garantías creadas', 'Omitidas', 'Avisos creados', 'Requiere revisión', 'Errores'],
            $filas
        );

        if ($this->notas !== []) {
            $this->warn('Notas (omisiones, avisos saltados y marcas de revisión):');
            $this->table(['Hoja', 'Deudor', 'Nota'], $this->notas);
        }

        if ($this->errores !== []) {
            $this->error('Errores por garantía (transacción revertida, el resto continuó):');
            $this->table(['Hoja', 'Deudor', 'Error'], $this->errores);
        }
    }
}
