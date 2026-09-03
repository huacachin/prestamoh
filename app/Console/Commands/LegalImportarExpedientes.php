<?php

namespace App\Console\Commands;

use App\Models\ActuacionJudicial;
use App\Models\Client;
use App\Models\ExpedienteJudicial;
use App\Models\PlazoJudicial;
use App\Models\User;
use Carbon\Carbon;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Importa el histórico de expedientes judiciales desde el Excel "A. Registro
 * de seguimiento. Expedientes Judiciales" del área legal (registro A).
 *
 * Estructura real del libro — 5 hojas con la MISMA grilla base (cabecera de
 * 3 filas: la fila cuyo A dice "Nº" + 2 filas de subcabecera) pero vías y
 * particularidades distintas:
 *  - "a. Exp. Vehículos cap." → via captura_vehicular. Cuaderno principal
 *    (cols F..V) + cuaderno cautelar (cols W..AF) + REV. (col AG).
 *  - "b. Exp. Inscripción"    → via inscripcion. Misma grilla que (a).
 *  - "c. Exp. Planilla"       → via planilla. SIN cuaderno cautelar y sin
 *    columna de codeudor (todo corrido una columna a la izquierda); a la
 *    derecha de REV. vive una LEYENDA que se ignora.
 *  - "d. Exp. Condonado"      → estado del principal FORZADO a 'condonado'
 *    (la via se deriva de la forma de medida del cautelar; si no hay, 'otra').
 *    Tiene una columna ESTADO extra ("Condonado 10/11/2025") antes de REV.
 *  - "e. Exp. Cancelados"     → estado FORZADO a 'cancelado' (misma
 *    derivación de via). Sin código Exp. interno en casi todas las filas.
 *
 * Mugre real del registro manual (se maneja, nunca se autocorrige a ciegas):
 *  - Cada hoja puede repetir un segundo bloque "EXPEDIENTES JUDICIALES EN
 *    PROCESO" con las MISMAS cabeceras en medio de los datos → se detectan
 *    y saltan como filas de cabecera.
 *  - Filas corridas 1-2 columnas a la derecha (celdas de más en la zona del
 *    codeudor) → se detecta el corrimiento buscando el N° de expediente / la
 *    fecha de inicio en una ventana y se re-alinea toda la fila.
 *  - N° con pipes, espacios internos, doble guion y primer segmento de 6
 *    dígitos ("| 04623-…", "…-3209--JP-…", "000577-…") → se normalizan; si
 *    aun así no calzan el formato PJ se importan con requiere_revision.
 *  - Cautelares tipeados con el MISMO N° del principal (dígito '-0-') → se
 *    corrige vía ExpedienteJudicial::nroCautelarDesde() + requiere_revision.
 *    Si la columna cautelar solo repite el N° sin ningún otro dato, no se
 *    crea cuaderno (solo nota).
 *  - Expedientes duplicados entre hojas (p. ej. 06982-2024 aparece dos
 *    veces en "e") → el segundo se salta como duplicado (idempotencia: un
 *    nro_expediente ya existente en BD también se salta).
 *  - Fechas basura ("21-arbil", "#REF!", "29708/2025", "00:00:00") → null.
 *
 * Estado del principal desde el texto libre (el texto ORIGINAL siempre va a
 * observaciones — trae información operativa): EJECUCION→en_ejecucion,
 * TRAMITE/RESUELTO/APELADO→en_tramite, CANCELADO/CONDONADO/DESISTIDO/
 * ARCHIVADO→su estado, AMORTIZANDO→en_ejecucion con nota; sin equivalencia
 * →en_tramite con nota. Hojas d/e fuerzan su estado. El cautelar mapea su
 * propio catálogo (captura_informado, capturado, oficio_entregado, inscrito,
 * levantado, rechazada, solicitada, concedida; sin match→concedida + nota).
 *
 * ACTUACIONES: resolución del principal (numero en letras: "CUATRO"),
 * escritos demandante/demandado y la resolución + escrito del cautelar.
 * Sumillas largas: sumilla truncada a 500 y detalle completo. La fecha es
 * NOT NULL: sin fecha legible se usa la fecha de inicio del expediente (u
 * hoy) y se anota en el detalle.
 *
 * PLAZOS desde texto libre: sobre estados y sumillas se buscan vencimientos
 * ("VENCE 09/03/2026", "Venc. 22/01", "venc. 10/03", "vence el 02 de junio
 * del 2026"). Fechas sin año usan el año de REV., de la actuación más
 * reciente o el actual, marcando requiere_revision. Se crean SIN
 * cumplido_at aunque ya vencieron: que la campana los muestre vencidos es
 * el objetivo del import.
 *
 * Matching (nunca se crean clientes/usuarios desde aquí):
 *  - Cliente por exp_interno == clients.expediente (exacto); fallback por
 *    nombre palabra a palabra (LIKE, match único); si no → client_id null +
 *    requiere_revision. Codeudor y REV. van a observaciones.
 *  - Asesor/a (Licet, Jhoseph, Ada, Mary, Guilmer…) → users.name LIKE con
 *    match único; si no, queda anotado en observaciones.
 *
 * Cada fila va en su propia transacción (principal + cautelar + actuaciones
 * + plazos): una que falla no tumba el resto. Con --dry-run se hace
 * rollback por fila y se muestra el mismo resumen.
 *
 * OJO memoria: setReadDataOnly() y lectura por bloques con corte al primer
 * bloque vacío, rango acotado a 40 columnas — las hojas arrastran columnas
 * fantasma (dims hasta FL).
 *
 * Uso:
 *   php artisan legal:importar-expedientes "ruta/al/A. Registro de seguimiento....xlsx" --dry-run
 *   php artisan legal:importar-expedientes "ruta/al/A. Registro de seguimiento....xlsx"
 */
class LegalImportarExpedientes extends Command
{
    protected $signature = 'legal:importar-expedientes
        {archivo : Ruta del Excel "A. Registro de seguimiento. Expedientes Judiciales"}
        {--dry-run : Simula la importación (rollback por fila) y muestra el mismo resumen}';

    protected $description = 'Importa el histórico de expedientes judiciales (hojas a-e: vehículos captura, inscripción, planilla, condonados y cancelados) desde el Excel "A. Registro de seguimiento" del área legal';

    /** Filas por bloque al leer una hoja (corta al primer bloque totalmente vacío) */
    private const FILAS_POR_BLOQUE = 200;

    /** Filas de cabecera desde la fila cuyo A dice "Nº" (esa + 2 subcabeceras) */
    private const FILAS_CABECERA = 3;

    /** Máximo corrimiento de columnas tolerado en una fila tipeada corrida */
    private const MAX_CORRIMIENTO = 3;

    private const MESES = [
        'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4, 'MAYO' => 5, 'JUNIO' => 6,
        'JULIO' => 7, 'AGOSTO' => 8, 'SETIEMBRE' => 9, 'SEPTIEMBRE' => 9, 'OCTUBRE' => 10,
        'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
    ];

    /**
     * Columnas base por layout (índice 0 desde A). Las filas corridas se
     * re-alinean con detectarCorrimiento(); la zona nombre/codeudor/asesor
     * (cols C..antes de fecha) se resuelve aparte porque el corrimiento ahí
     * no es uniforme.
     */
    private const LAYOUTS = [
        // Hojas a, b, d y e: principal + cautelar
        'completo' => [
            'exp' => 1, 'nombre' => 2, 'fecha' => 5, 'nro' => 6, 'juzgado' => 7, 'distrito' => 8,
            'materia' => 9, 'proceso' => 10, 'espec' => 11, 'juez' => 12, 'secretario' => 13,
            'res_num' => 14, 'res_fecha' => 15, 'res_sum' => 16,
            'dte_fecha' => 17, 'dte_sum' => 18, 'ddo_fecha' => 19, 'ddo_sum' => 20, 'estado' => 21,
            'c_fecha' => 22, 'c_nro' => 23, 'c_bien' => 24, 'c_forma' => 25,
            'c_res_num' => 26, 'c_res_fecha' => 27, 'c_res_sum' => 28,
            'c_esc_fecha' => 29, 'c_esc_sum' => 30, 'c_estado' => 31, 'rev' => 32,
        ],
        // Hoja c: sin codeudor ni cuaderno cautelar; LEYENDA a la derecha de REV. (se ignora)
        'planilla' => [
            'exp' => 1, 'nombre' => 2, 'fecha' => 4, 'nro' => 5, 'juzgado' => 6, 'distrito' => 7,
            'materia' => 8, 'proceso' => 9, 'espec' => 10, 'juez' => 11, 'secretario' => 12,
            'res_num' => 13, 'res_fecha' => 14, 'res_sum' => 15,
            'dte_fecha' => 16, 'dte_sum' => 17, 'ddo_fecha' => 18, 'ddo_sum' => 19, 'estado' => 20,
            'rev' => 21,
        ],
    ];

    /**
     * Hojas del libro: via fija u obtenida de la forma de medida ('via' null),
     * estado del principal forzado (hojas d/e) y columna ESTADO extra (hoja d,
     * antes de REV. que ahí pasa a la col 33).
     */
    private const HOJAS = [
        'a. Exp. Vehículos cap.' => ['layout' => 'completo', 'via' => 'captura_vehicular', 'estado_forzado' => null, 'estado_extra' => null, 'rev' => null],
        'b. Exp. Inscripción' => ['layout' => 'completo', 'via' => 'inscripcion', 'estado_forzado' => null, 'estado_extra' => null, 'rev' => null],
        'c. Exp. Planilla' => ['layout' => 'planilla', 'via' => 'planilla', 'estado_forzado' => null, 'estado_extra' => null, 'rev' => null],
        'd. Exp. Condonado' => ['layout' => 'completo', 'via' => null, 'estado_forzado' => 'condonado', 'estado_extra' => 32, 'rev' => 33],
        'e. Exp. Cancelados' => ['layout' => 'completo', 'via' => null, 'estado_forzado' => 'cancelado', 'estado_extra' => null, 'rev' => null],
    ];

    private bool $dry = false;

    /** Contadores por hoja */
    private array $resumen = [];

    /** Notas del proceso (omisiones, duplicados, matching fallido, marcas de revisión): [hoja, referencia, nota] */
    private array $notas = [];

    /** Errores capturados por fila: [hoja, referencia, mensaje] */
    private array $errores = [];

    /** N° de expediente ya importados en ESTA corrida (clave del dry-run, donde la BD no los retiene) */
    private array $nrosImportados = [];

    /** Cachés de matching (el Excel repite clientes y asesores) */
    private array $cacheClientesExp = [];

    private array $cacheClientesNombre = [];

    private array $cacheAsesores = [];

    public function handle(): int
    {
        ini_set('memory_limit', '512M'); // margen para PhpSpreadsheet ante columnas fantasma

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
            $lector->setReadDataOnly(true); // sin estilos: evita agotar memoria con filas/columnas fantasma
            $libro = $lector->load($archivo);
        } catch (Throwable $e) {
            $this->error('No se pudo leer el Excel: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach (self::HOJAS as $nombreHoja => $config) {
            $hoja = $this->buscarHoja($libro, $nombreHoja);
            if ($hoja === null) {
                $this->warn("Hoja \"{$nombreHoja}\" no encontrada en el libro — saltada.");

                continue;
            }
            $this->procesarHoja($hoja, $nombreHoja, $config);
        }

        $libro->disconnectWorksheets();

        if ($this->resumen === []) {
            $this->error('El libro no contiene ninguna de las hojas esperadas (a-e del registro de seguimiento).');

            return self::FAILURE;
        }

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    /** Hoja por nombre exacto o, en su defecto, comparando sin acentos/mayúsculas */
    private function buscarHoja(Spreadsheet $libro, string $nombre): ?Worksheet
    {
        $hoja = $libro->getSheetByName($nombre);
        if ($hoja !== null) {
            return $hoja;
        }

        $clave = $this->sinAcentos(mb_strtolower(trim($nombre)));
        foreach ($libro->getWorksheetIterator() as $candidata) {
            $titulo = $this->sinAcentos(mb_strtolower(trim($candidata->getTitle())));
            if ($titulo === $clave || str_starts_with($titulo, mb_substr($clave, 0, 6))) {
                return $candidata;
            }
        }

        return null;
    }

    /* ─────────────────────────── Proceso por hoja ─────────────────────────── */

    private function procesarHoja(Worksheet $hoja, string $nombreHoja, array $config): void
    {
        $this->resumen[$nombreHoja] = [
            'principales' => 0, 'cautelares' => 0, 'actuaciones' => 0, 'plazos' => 0,
            'omitidos' => 0, 'revision' => 0, 'errores' => 0,
        ];

        $col = self::LAYOUTS[$config['layout']];
        if ($config['rev'] !== null) {
            $col['rev'] = $config['rev'];
        }

        foreach ($this->filasConDatos($hoja) as $nroFila => $celdas) {
            // Bloque "EXPEDIENTES JUDICIALES EN PROCESO" repetido en medio de los datos
            if ($this->esFilaCabecera($celdas)) {
                continue;
            }

            $datos = $this->normalizarFila($celdas, $nroFila, $col, $config);
            if ($datos === null) {
                continue; // fila sin cliente ni expediente (leyenda, resto de formato)
            }

            $this->importarFila($datos, $nombreHoja, $config);
        }
    }

    /**
     * Generador de filas con datos (A..AN normalizadas a texto), leídas por
     * bloques. La cabecera empieza en la fila cuyo A dice "Nº"; los datos,
     * 3 filas después. Corta al primer bloque totalmente vacío.
     */
    private function filasConDatos(Worksheet $hoja): Generator
    {
        $inicio = null;
        foreach ($hoja->rangeToArray('A1:A10', null, true, true, false) as $i => $fila) {
            $a = trim((string) $fila[0]);
            if ($a === 'Nº' || $a === 'N°') {
                $inicio = $i + 1 + self::FILAS_CABECERA; // $i es índice 0 → fila real +1, más las subcabeceras
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
            $bloque = $hoja->rangeToArray("A{$desde}:AN{$hasta}", null, true, true, false);

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

    /** Título del segundo bloque o cualquiera de sus 3 filas de cabecera repetidas */
    private function esFilaCabecera(array $celdas): bool
    {
        $limpias = array_map('trim', $celdas);

        if (str_contains(mb_strtoupper($limpias[0] ?? ''), 'EXPEDIENTES JUDICIALES')) {
            return true; // "EXPEDIENTES JUDICIALES EN PROCESO"
        }
        if (in_array($limpias[0], ['Nº', 'N°'], true) || in_array('Nombres y apellidos', $limpias, true)) {
            return true; // 1.ª y 2.ª fila de cabecera
        }

        // 3.ª fila (subcabecera Número/Fecha/Sumilla), venga donde venga por corrimientos
        return in_array('Número', $limpias, true) && in_array('Sumilla', $limpias, true);
    }

    /* ───────────────────── Normalización de una fila ───────────────────── */

    /**
     * Re-alinea la fila (corrimientos reales de 1-2 columnas), resuelve la
     * zona nombre/codeudor/asesor y devuelve los campos crudos con nombres.
     * Devuelve null si la fila no trae ni cliente ni N° de expediente.
     */
    private function normalizarFila(array $celdas, int $nroFila, array $col, array $config): ?array
    {
        $s = $this->detectarCorrimiento($celdas, $col);
        $en = fn (string $campo): string => trim($celdas[$col[$campo] + $s] ?? '');

        // Zona cliente: de la col C hasta antes de la fecha de inicio ya corrida.
        // El corrimiento aquí no es uniforme (el codeudor a veces ocupa 2 celdas),
        // así que se toman las celdas no vacías: primera=titular, última=asesor si
        // parece nombre de pila suelto, el resto=codeudor.
        [$nombre, $codeudor, $asesor] = $this->zonaCliente($celdas, $col['nombre'], $col['fecha'] + $s);

        $expInterno = trim($celdas[$col['exp']] ?? '');
        $nroCrudo = $en('nro');

        if ($nombre === '' && $nroCrudo === '') {
            return null; // resto de leyenda o formato
        }

        $datos = [
            'fila' => $nroFila,
            'exp_interno' => preg_match('/^\d{1,10}$/', $expInterno) ? $expInterno : '',
            'nombre' => $nombre,
            'codeudor' => $codeudor,
            'asesor' => $asesor,
            'fecha_inicio' => $en('fecha'),
            'nro' => $nroCrudo,
            'juzgado' => $en('juzgado'),
            'distrito' => $en('distrito'),
            'materia' => $en('materia'),
            'proceso' => $en('proceso'),
            'juez' => $en('juez'),
            'secretario' => $en('secretario'),
            'res_num' => $en('res_num'),
            'res_fecha' => $en('res_fecha'),
            'res_sum' => $en('res_sum'),
            'dte_fecha' => $en('dte_fecha'),
            'dte_sum' => $en('dte_sum'),
            'ddo_fecha' => $en('ddo_fecha'),
            'ddo_sum' => $en('ddo_sum'),
            'estado' => $en('estado'),
            'rev' => $en('rev'),
            'estado_extra' => $config['estado_extra'] !== null ? trim($celdas[$config['estado_extra'] + $s] ?? '') : '',
        ];

        // Cuaderno cautelar solo en el layout completo
        foreach (['c_fecha', 'c_nro', 'c_bien', 'c_forma', 'c_res_num', 'c_res_fecha', 'c_res_sum', 'c_esc_fecha', 'c_esc_sum', 'c_estado'] as $campo) {
            $datos[$campo] = isset($col[$campo]) ? $en($campo) : '';
        }

        return $datos;
    }

    /**
     * Corrimiento de la fila: busca el N° de expediente (o la fecha de
     * inicio) en una ventana de 0..3 columnas a la derecha de su posición
     * base. Filas reales del libro vienen corridas 1-2 columnas.
     */
    private function detectarCorrimiento(array $celdas, array $col): int
    {
        for ($s = 0; $s <= self::MAX_CORRIMIENTO; $s++) {
            if ($this->esNroExpediente(trim($celdas[$col['nro'] + $s] ?? ''))) {
                return $s;
            }
        }
        for ($s = 0; $s <= self::MAX_CORRIMIENTO; $s++) {
            if ($this->parsearFecha(trim($celdas[$col['fecha'] + $s] ?? '')) !== null) {
                return $s;
            }
        }

        return 0;
    }

    /** [titular, codeudor, asesor] desde las celdas no vacías de la zona cliente */
    private function zonaCliente(array $celdas, int $desde, int $hastaExclusivo): array
    {
        $valores = [];
        for ($i = $desde; $i < $hastaExclusivo; $i++) {
            $v = trim($celdas[$i] ?? '');
            if ($v !== '') {
                $valores[] = $v;
            }
        }
        if ($valores === []) {
            return ['', '', ''];
        }

        $nombre = array_shift($valores);
        $asesor = '';
        if ($valores !== [] && $this->pareceAsesor(end($valores))) {
            $asesor = array_pop($valores);
        }

        return [$nombre, implode(' / ', $valores), $asesor];
    }

    /** Nombre de pila suelto (Licet, Jhoseph, Ada, Mary, Guilmer…): una sola palabra corta */
    private function pareceAsesor(string $valor): bool
    {
        return (bool) preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]{2,15}$/u', $valor);
    }

    /* ───────────────────────── Importación por fila ───────────────────────── */

    private function importarFila(array $d, string $nombreHoja, array $config): void
    {
        $contador = &$this->resumen[$nombreHoja];
        $referencia = trim(($d['exp_interno'] !== '' ? "Exp. {$d['exp_interno']} " : '')."{$d['nombre']} (fila {$d['fila']})");

        if ($d['nro'] === '') {
            $contador['omitidos']++;
            $this->nota($nombreHoja, $referencia, 'Sin N° de expediente en el Excel — omitida');

            return;
        }

        $nro = $this->normalizarNro($d['nro']);

        // Idempotencia + duplicados reales entre hojas
        if (isset($this->nrosImportados[$nro]) || ExpedienteJudicial::where('nro_expediente', $nro)->exists()) {
            $contador['omitidos']++;
            $this->nota($nombreHoja, $referencia, "Duplicado: el expediente {$nro} ya existe (en BD o en otra hoja de esta corrida) — omitido");

            return;
        }

        $notasRevision = []; // marcan requiere_revision del principal
        $observaciones = ["Importado del Excel \"A. Registro de seguimiento\" (hoja {$nombreHoja}, fila {$d['fila']})"];

        if (! ExpedienteJudicial::formatoValido($nro)) {
            $notasRevision[] = "N° de expediente con formato no estándar: {$d['nro']}";
        }

        // Cliente: exp_interno exacto → fallback por nombre → null + revisión
        $cliente = $this->buscarCliente($d['exp_interno'], $d['nombre']);
        if ($cliente === null) {
            $notasRevision[] = "Cliente no identificado: {$d['nombre']}";
        }

        // Asesor/a por users.name LIKE (match único)
        $asesorId = null;
        if ($d['asesor'] !== '') {
            $asesorId = $this->buscarAsesor($d['asesor']);
            if ($asesorId === null) {
                $observaciones[] = "Asesor/a (Excel) sin usuario en el sistema: {$d['asesor']}";
            }
        }

        // Estado del principal (hojas d/e lo fuerzan); el texto original SIEMPRE queda
        if ($config['estado_forzado'] !== null) {
            $estado = $config['estado_forzado'];
        } else {
            [$estado, $notaEstado] = $this->estadoPrincipalDesde($d['estado']);
            if ($notaEstado !== null) {
                $observaciones[] = $notaEstado;
            }
        }
        if ($d['estado'] !== '' && $d['estado'] !== '#REF!') {
            $observaciones[] = 'Estado (Excel): '.$d['estado'];
        }
        if ($d['estado_extra'] !== '' && $d['estado_extra'] !== '#REF!') {
            $observaciones[] = 'Estado adicional (Excel): '.$d['estado_extra'];
        }
        if ($d['codeudor'] !== '') {
            $observaciones[] = 'Codeudor (Excel): '.$d['codeudor'];
        }

        $fechaRev = $this->parsearFecha($d['rev']);
        if ($fechaRev !== null) {
            $observaciones[] = 'REV.: '.$fechaRev->format('d/m/Y');
        } elseif ($d['rev'] !== '' && $d['rev'] !== '#REF!') {
            $observaciones[] = 'REV.: '.$d['rev'];
        }

        // Cuaderno cautelar: solo con N° propio; si la columna únicamente
        // repite el N° del principal sin ningún otro dato, no es un cuaderno.
        $nroCautelar = $d['c_nro'] !== '' ? $this->normalizarNro($d['c_nro']) : '';
        $datosCautelar = implode('', [$d['c_fecha'], $d['c_bien'], $d['c_forma'], $d['c_res_num'], $d['c_res_fecha'], $d['c_res_sum'], $d['c_esc_fecha'], $d['c_esc_sum'], $d['c_estado']]) !== '';
        $crearCautelar = $nroCautelar !== '' && ($nroCautelar !== $nro || $datosCautelar);
        if ($nroCautelar !== '' && ! $crearCautelar) {
            $observaciones[] = 'Columna cautelar del Excel solo repite el N° del principal — no se creó cuaderno cautelar';
        }
        if (! $crearCautelar && $d['c_estado'] !== '' && $d['c_estado'] !== '#REF!') {
            $observaciones[] = 'Cuaderno cautelar (Excel, sin N° propio): '.trim($d['c_estado'].' '.$d['c_bien'].' '.$d['c_forma']);
        }

        // Via: fija por hoja o derivada de la forma de medida (hojas d/e)
        $formaMedida = $this->formaMedidaDesde($d['c_forma']);
        $via = $config['via'] ?? match ($formaMedida) {
            'secuestro' => 'captura_vehicular',
            'inscripcion' => 'inscripcion',
            default => 'otra',
        };

        // Año de referencia para plazos sin año: REV. o la actuación más reciente
        $anioRef = $fechaRev?->year;
        if ($anioRef === null) {
            foreach (['res_fecha', 'dte_fecha', 'ddo_fecha', 'c_res_fecha', 'c_esc_fecha'] as $campo) {
                $f = $this->parsearFecha($d[$campo]);
                if ($f !== null && ($anioRef === null || $f->year > $anioRef)) {
                    $anioRef = $f->year;
                }
            }
        }

        // Plazos por cuaderno, extraídos ANTES de crear (marcan revisión)
        $asumioAnio = false;
        $plazosPrincipal = $this->extraerPlazos([$d['estado'], $d['estado_extra'], $d['res_sum'], $d['dte_sum'], $d['ddo_sum']], $anioRef, $asumioAnio);
        $plazosCautelar = $this->extraerPlazos([$d['c_estado'], $d['c_res_sum'], $d['c_esc_sum']], $anioRef, $asumioAnio);
        if ($asumioAnio) {
            $notasRevision[] = 'Vencimiento sin año en el Excel: se asumió el año '.($anioRef ?? now()->year);
        }

        DB::beginTransaction();
        try {
            if ($notasRevision !== []) {
                $observaciones[] = 'REVISAR: '.implode('; ', $notasRevision);
            }

            $principal = ExpedienteJudicial::create([
                'client_id' => $cliente?->id,
                'exp_interno' => $d['exp_interno'] !== '' ? $d['exp_interno'] : null,
                'nro_expediente' => $nro,
                'cuaderno' => 'principal',
                'juzgado' => $this->recortar($d['juzgado'], 150),
                'distrito_judicial' => $this->recortar($d['distrito'], 60),
                'materia' => $this->recortar($d['materia'], 100),
                'proceso' => $this->recortar($d['proceso'], 40),
                'juez' => $this->recortar($d['juez'], 120),
                'secretario' => $this->recortar($d['secretario'], 120),
                'via' => $via,
                'estado' => $estado,
                'asesor_responsable_id' => $asesorId,
                'fecha_inicio' => $this->parsearFecha($d['fecha_inicio'])?->toDateString(),
                'requiere_revision' => $notasRevision !== [],
                'observaciones' => implode('. ', $observaciones).'.',
            ]);

            $actuaciones = 0;
            $actuaciones += $this->crearActuacion($principal, 'resolucion', $d['res_num'], $d['res_fecha'], $d['res_sum']);
            $actuaciones += $this->crearActuacion($principal, 'escrito_demandante', '', $d['dte_fecha'], $d['dte_sum']);
            $actuaciones += $this->crearActuacion($principal, 'escrito_demandado', '', $d['ddo_fecha'], $d['ddo_sum']);

            // Cuaderno cautelar colgado del principal
            $cautelar = null;
            if ($crearCautelar) {
                $cautelar = $this->crearCautelar($principal, $d, $nro, $nroCautelar, $via, $formaMedida, $nombreHoja, $referencia);
                $actuaciones += $this->crearActuacion($cautelar, 'resolucion', $d['c_res_num'], $d['c_res_fecha'], $d['c_res_sum']);
                $actuaciones += $this->crearActuacion($cautelar, 'escrito_demandante', '', $d['c_esc_fecha'], $d['c_esc_sum']);
            }

            $plazos = $this->crearPlazos($principal, $plazosPrincipal, $asesorId);
            $plazos += $this->crearPlazos($cautelar ?? $principal, $plazosCautelar, $asesorId);

            $this->dry ? DB::rollBack() : DB::commit();

            // Aun en dry-run se registran los N° para cazar duplicados entre hojas
            $this->nrosImportados[$nro] = true;
            $contador['principales']++;
            if ($cautelar !== null) {
                $this->nrosImportados[$cautelar->nro_expediente] = true;
                $contador['cautelares']++;
                if ($cautelar->requiere_revision) {
                    $contador['revision']++;
                }
            }
            $contador['actuaciones'] += $actuaciones;
            $contador['plazos'] += $plazos;
            if ($notasRevision !== []) {
                $contador['revision']++;
                foreach ($notasRevision as $nota) {
                    $this->nota($nombreHoja, $referencia, $nota);
                }
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $contador['errores']++;
            $this->errores[] = [$nombreHoja, $referencia, mb_substr($e->getMessage(), 0, 160)];
        }
    }

    /**
     * Crea el cuaderno cautelar colgado del principal. Si su N° normalizado
     * es idéntico al del principal (error real del Excel: cautelar tipeado
     * con '-0-') se deriva con nroCautelarDesde() y se marca revisión.
     */
    private function crearCautelar(ExpedienteJudicial $principal, array $d, string $nroPrincipal, string $nroCautelar, string $via, ?string $formaMedida, string $nombreHoja, string $referencia): ExpedienteJudicial
    {
        $notasRevision = [];
        $observaciones = ["Importado del Excel \"A. Registro de seguimiento\" (hoja {$nombreHoja}, fila {$d['fila']}, cuaderno cautelar)"];

        if ($nroCautelar === $nroPrincipal) {
            $original = $nroCautelar;
            $nroCautelar = ExpedienteJudicial::nroCautelarDesde($nroPrincipal);
            $notasRevision[] = "El Excel registró el cautelar con el mismo N° del principal ({$original}); se derivó {$nroCautelar}";
        }
        if (! ExpedienteJudicial::formatoValido($nroCautelar)) {
            $notasRevision[] = "N° de expediente con formato no estándar: {$d['c_nro']}";
        }
        if (isset($this->nrosImportados[$nroCautelar]) || ExpedienteJudicial::where('nro_expediente', $nroCautelar)->exists()) {
            // Colisión real: se deriva el siguiente cuaderno libre para no perder la fila
            $n = 2;
            do {
                $candidato = ExpedienteJudicial::nroCautelarDesde($nroPrincipal, $n);
                $n++;
            } while ((isset($this->nrosImportados[$candidato]) || ExpedienteJudicial::where('nro_expediente', $candidato)->exists()) && $n <= 9);
            $notasRevision[] = "El N° cautelar {$nroCautelar} ya existía; se registró como {$candidato}";
            $nroCautelar = $candidato;
        }

        [$estado, $notaEstado] = $this->estadoCautelarDesde($d['c_estado']);
        if ($notaEstado !== null) {
            $observaciones[] = $notaEstado;
        }
        if ($d['c_estado'] !== '' && $d['c_estado'] !== '#REF!') {
            $observaciones[] = 'Estado (Excel): '.$d['c_estado'];
        }
        if ($formaMedida === null && $d['c_forma'] !== '') {
            $observaciones[] = 'Forma de medida (Excel) sin equivalencia: '.$d['c_forma'];
        }

        if ($notasRevision !== []) {
            $observaciones[] = 'REVISAR: '.implode('; ', $notasRevision);
            foreach ($notasRevision as $nota) {
                $this->nota($nombreHoja, $referencia, $nota);
            }
        }

        return ExpedienteJudicial::create([
            'client_id' => $principal->client_id,
            'exp_interno' => $principal->exp_interno,
            'nro_expediente' => $nroCautelar,
            'cuaderno' => 'cautelar',
            'expediente_padre_id' => $principal->id,
            'juzgado' => $principal->juzgado,
            'distrito_judicial' => $principal->distrito_judicial,
            'materia' => $principal->materia,
            'proceso' => $principal->proceso,
            'juez' => $principal->juez,
            'secretario' => $principal->secretario,
            'via' => $via,
            'forma_medida' => $formaMedida,
            'bien_descripcion' => $this->recortar($d['c_bien'], 255),
            'estado' => $estado,
            'asesor_responsable_id' => $principal->asesor_responsable_id,
            'fecha_inicio' => $this->parsearFecha($d['c_fecha'])?->toDateString(),
            'requiere_revision' => $notasRevision !== [],
            'observaciones' => implode('. ', $observaciones).'.',
        ]);
    }

    /**
     * Actuación desde las columnas Resolución/Escritos. Se crea si hay fecha
     * o sumilla (o número, en resoluciones). La fecha es NOT NULL: sin fecha
     * legible cae a la fecha de inicio del expediente (u hoy) y queda
     * anotado en el detalle. Sumilla truncada a 500 con el texto completo en
     * detalle. Devuelve 1 si creó, 0 si no.
     */
    private function crearActuacion(ExpedienteJudicial $expediente, string $tipo, string $numero, string $fechaCruda, string $sumilla): int
    {
        if ($numero === '' && $fechaCruda === '' && $sumilla === '') {
            return 0;
        }

        $detalle = [];
        $fecha = $this->parsearFecha($fechaCruda);
        if ($fecha === null) {
            if ($fechaCruda !== '') {
                $detalle[] = "Fecha no legible en el Excel: \"{$fechaCruda}\"";
            }
            $fecha = $expediente->fecha_inicio !== null ? Carbon::parse($expediente->fecha_inicio) : now()->startOfDay();
            $detalle[] = 'Se usó '.($expediente->fecha_inicio !== null ? 'la fecha de inicio del expediente' : 'la fecha de importación').' por falta de fecha propia';
        }

        if ($sumilla === '') {
            $sumilla = match ($tipo) {
                'resolucion' => trim('Resolución '.$numero),
                'escrito_demandante' => 'Escrito del demandante (sin sumilla en el Excel)',
                'escrito_demandado' => 'Escrito del demandado (sin sumilla en el Excel)',
                default => 'Sin sumilla en el Excel',
            };
        }
        if (mb_strlen($sumilla) > 500) {
            array_unshift($detalle, $sumilla); // texto completo
        }

        ActuacionJudicial::create([
            'expediente_id' => $expediente->id,
            'tipo' => $tipo,
            'numero' => $numero !== '' ? $this->recortar($numero, 40) : null,
            'fecha' => $fecha->toDateString(),
            'sumilla' => $this->recortar($sumilla, 500),
            'detalle' => $detalle !== [] ? implode('. ', $detalle) : null,
        ]);

        return 1;
    }

    /** Crea los plazos extraídos (SIN cumplido_at: los vencidos deben sonar en la campana) */
    private function crearPlazos(ExpedienteJudicial $expediente, array $plazos, ?int $responsableId): int
    {
        $creados = 0;
        foreach ($plazos as $plazo) {
            PlazoJudicial::create([
                'expediente_id' => $expediente->id,
                'descripcion' => $this->recortar($plazo['texto'], 255),
                'fecha_vencimiento' => $plazo['fecha']->toDateString(),
                'responsable_id' => $responsableId,
            ]);
            $creados++;
        }

        return $creados;
    }

    /* ───────────────── Estados, vías y vencimientos (texto libre) ───────────────── */

    /** [estado, nota|null] del cuaderno principal desde el texto libre del Excel */
    private function estadoPrincipalDesde(string $texto): array
    {
        $t = $this->sinAcentos(mb_strtoupper(trim($texto)));

        return match (true) {
            $t === '' || $t === '#REF!' => ['en_tramite', 'Sin estado en el Excel — se asumió En trámite'],
            str_contains($t, 'CONDONADO') => ['condonado', null],
            str_contains($t, 'CANCELADO') => ['cancelado', null],
            str_contains($t, 'DESISTIDO') => ['desistido', null],
            str_contains($t, 'ARCHIVADO') => ['archivado', null],
            str_contains($t, 'AMORTIZANDO') => ['en_ejecucion', 'Estado "AMORTIZANDO" en el Excel — se registró como En ejecución'],
            str_contains($t, 'EJECUCION') => ['en_ejecucion', null],
            str_contains($t, 'TRAMITE') || str_contains($t, 'RESUELTO') || str_contains($t, 'APELADO') => ['en_tramite', null],
            default => ['en_tramite', "Estado \"{$texto}\" sin equivalencia — se asumió En trámite"],
        };
    }

    /** [estado, nota|null] del cuaderno cautelar desde el texto libre del Excel */
    private function estadoCautelarDesde(string $texto): array
    {
        $t = $this->sinAcentos(mb_strtoupper(trim($texto)));

        return match (true) {
            $t === '' || $t === '#REF!' => ['concedida', 'Sin estado cautelar en el Excel — se asumió Medida concedida'],
            str_contains($t, 'CAPTURA INFORMADO') => ['captura_informado', null],
            str_contains($t, 'CAPTURADO') => ['capturado', null],
            str_contains($t, 'ENTREGADO') || str_contains($t, 'OFICIO') => ['oficio_entregado', null],
            str_contains($t, 'INSCRITO') => ['inscrito', null],
            str_contains($t, 'LEVANTA') => ['levantado', null], // LEVANTADO / LEVANTAR CAPTURA
            str_contains($t, 'IMPROCEDENTE') || str_contains($t, 'RECHAZ') => ['rechazada', null],
            str_contains($t, 'CALIFICA') => ['solicitada', null], // (PENDIENTE CALIFICAR) / EN CALIFICACIÓN
            str_contains($t, 'CONCEDE') || str_contains($t, 'TRABESE') => ['concedida', null],
            default => ['concedida', "Estado cautelar \"{$texto}\" sin equivalencia — se asumió Medida concedida"],
        };
    }

    /** Forma de medida del cautelar (SECUESTRO / INSCRIPCIÓN); null si no calza */
    private function formaMedidaDesde(string $texto): ?string
    {
        $t = $this->sinAcentos(mb_strtoupper($texto));

        return match (true) {
            str_contains($t, 'SECUESTRO') => 'secuestro',
            str_contains($t, 'INSCRIP') => 'inscripcion',
            default => null,
        };
    }

    /**
     * Vencimientos en texto libre: 'VENCE 09/03/2026', 'Venc. 12/06/2026',
     * 'Venc. 22/01', 'venc. 10/03' y 'vence el 02 de junio del 2026'.
     * Sin año usa $anioRef (REV./actuación más reciente) o el actual y
     * enciende $asumioAnio (el llamador marca requiere_revision).
     * Devuelve [['fecha' => Carbon, 'texto' => origen], …] sin fechas repetidas.
     */
    private function extraerPlazos(array $textos, ?int $anioRef, bool &$asumioAnio): array
    {
        $plazos = [];

        foreach ($textos as $texto) {
            $texto = trim($texto);
            if ($texto === '' || ! preg_match('/VENC/iu', $this->sinAcentos($texto))) {
                continue;
            }

            // d/m o d/m/aaaa tras "vence"/"venc."
            if (preg_match_all('/\bVENC\w*\.?\s*(?:EL\s+)?(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?/iu', $texto, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $dia = (int) $hit[1];
                    $mes = (int) $hit[2];
                    $anio = isset($hit[3]) && $hit[3] !== '' ? (int) $hit[3] : null;
                    if ($anio !== null && $anio < 100) {
                        $anio += 2000;
                    }
                    if ($anio === null) {
                        $anio = $anioRef ?? now()->year;
                        $asumioAnio = true;
                    }
                    if (checkdate($mes, $dia, $anio)) {
                        $plazos[Carbon::create($anio, $mes, $dia)->toDateString()] = $texto;
                    }
                }
            }

            // 'vence el 02 de junio del 2026'
            if (preg_match_all('/\bVENC\w*\.?\s*(?:EL\s+)?(\d{1,2})\s+DE\s+([A-ZÁÉÍÓÚÜÑ]+)\s+(?:DEL?\s+)?(\d{4})/iu', $texto, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $dia = (int) $hit[1];
                    $mes = self::MESES[$this->sinAcentos(mb_strtoupper($hit[2]))] ?? null;
                    $anio = (int) $hit[3];
                    if ($mes !== null && checkdate($mes, $dia, $anio)) {
                        $plazos[Carbon::create($anio, $mes, $dia)->toDateString()] = $texto;
                    }
                }
            }
        }

        return array_map(
            fn (string $fecha, string $texto) => ['fecha' => Carbon::parse($fecha), 'texto' => $texto],
            array_keys($plazos),
            array_values($plazos),
        );
    }

    /* ─────────────────────────────── Matching ─────────────────────────────── */

    /** Cliente por exp_interno exacto; fallback por nombre palabra a palabra (match único) */
    private function buscarCliente(string $expInterno, string $nombre): ?Client
    {
        if ($expInterno !== '') {
            if (! array_key_exists($expInterno, $this->cacheClientesExp)) {
                $this->cacheClientesExp[$expInterno] = Client::where('expediente', $expInterno)->first();
            }
            if ($this->cacheClientesExp[$expInterno] !== null) {
                return $this->cacheClientesExp[$expInterno];
            }
        }

        return $this->buscarClientePorNombre($nombre);
    }

    /**
     * Todas las palabras del nombre deben aparecer (LIKE, en cualquier orden
     * apellido-nombre) sobre nombre + apellidos. Solo se acepta un match
     * ÚNICO; con 0 o varios candidatos devuelve null y el llamador marca
     * requiere_revision. Nunca se crean clientes desde aquí.
     */
    private function buscarClientePorNombre(string $nombre): ?Client
    {
        $palabras = [];
        foreach (preg_split('/\s+/', trim($nombre)) ?: [] as $palabra) {
            $palabra = trim($palabra, '.,;');
            if (mb_strlen($palabra) >= 2) {
                $palabras[] = $palabra;
            }
        }
        if (count($palabras) < 2) {
            return null; // muy poco para un match confiable
        }

        $clave = $this->sinAcentos(mb_strtolower(implode(' ', $palabras)));
        if (array_key_exists($clave, $this->cacheClientesNombre)) {
            return $this->cacheClientesNombre[$clave];
        }

        $q = Client::query();
        foreach ($palabras as $palabra) {
            $q->whereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ['%'.$palabra.'%']);
        }
        $candidatos = $q->limit(2)->get();

        return $this->cacheClientesNombre[$clave] = ($candidatos->count() === 1 ? $candidatos->first() : null);
    }

    /** Asesor/a (nombre de pila del Excel) → users.name LIKE, solo si el match es único */
    private function buscarAsesor(string $nombre): ?int
    {
        $clave = $this->sinAcentos(mb_strtolower(trim($nombre)));
        if (array_key_exists($clave, $this->cacheAsesores)) {
            return $this->cacheAsesores[$clave];
        }

        $candidatos = User::where('name', 'LIKE', '%'.trim($nombre).'%')->limit(2)->get();

        return $this->cacheAsesores[$clave] = ($candidatos->count() === 1 ? $candidatos->first()->id : null);
    }

    /* ─────────────────────────────── Utilitarios ─────────────────────────────── */

    /**
     * Normaliza un N° de expediente del PJ: mayúsculas, sin espacios internos
     * ni pipes, '--'→'-' y primer segmento de 6 dígitos con cero inicial
     * recortado a 5 ("000577-…"→"00577-…"). NO valida: eso lo decide
     * formatoValido() y, si falla, se importa igual con requiere_revision.
     */
    private function normalizarNro(string $nro): string
    {
        $nro = mb_strtoupper(trim($nro));
        $nro = str_replace(['|', ' ', "\u{A0}"], '', $nro);
        while (str_contains($nro, '--')) {
            $nro = str_replace('--', '-', $nro);
        }
        $nro = trim($nro, '-');

        if (preg_match('/^0(\d{5})(-.*)$/', $nro, $m)) {
            $nro = $m[1].$m[2];
        }

        return $nro;
    }

    /** ¿La celda (cruda) parece un N° de expediente del PJ? — ancla del re-alineado de filas corridas */
    private function esNroExpediente(string $valor): bool
    {
        return (bool) preg_match('/^\d{4,6}-\d{4}-\d{1,2}-/', $this->normalizarNro($valor));
    }

    /**
     * Normaliza una celda cruda a texto: los enteros guardados como número
     * (seriales de fecha) se devuelven sin notación científica.
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
     * Fecha de celda del Excel: serial (lectura sin formato), 'D/M/AAAA'
     * (texto tipeado a la peruana — a diferencia del import de notaría, aquí
     * la lectura es data-only, así que NO llegan fechas re-formateadas al
     * estilo US: prima D/M y solo si es inválido se intenta M/D) o ISO.
     * Basura real ('21-arbil', '#REF!', '29708/2025', '00:00:00') → null.
     */
    private function parsearFecha(string $valor): ?Carbon
    {
        $valor = trim($valor);
        if ($valor === '' || ! preg_match('/\d/', $valor)) {
            return null;
        }

        if (is_numeric($valor)) {
            $serial = (float) $valor;

            return $serial > 25569 // 1970-01-01: descarta números que no son fechas
                ? Carbon::instance(FechaExcel::excelToDateTimeObject($serial))->startOfDay()
                : null;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valor, $m)) {
            [, $a, $b, $anio] = $m;
            if (checkdate((int) $b, (int) $a, (int) $anio)) {
                return Carbon::create((int) $anio, (int) $b, (int) $a); // D/M/AAAA
            }
            if (checkdate((int) $a, (int) $b, (int) $anio)) {
                return Carbon::create((int) $anio, (int) $a, (int) $b); // M/D/AAAA
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            try {
                return Carbon::parse(mb_substr($valor, 0, 10))->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function recortar(string $texto, int $max): ?string
    {
        $texto = trim($texto);

        return $texto === '' ? null : mb_substr($texto, 0, $max);
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
        $total = ['principales' => 0, 'cautelares' => 0, 'actuaciones' => 0, 'plazos' => 0, 'omitidos' => 0, 'revision' => 0, 'errores' => 0];
        foreach ($this->resumen as $hoja => $c) {
            $filas[] = [$hoja, $c['principales'], $c['cautelares'], $c['actuaciones'], $c['plazos'], $c['omitidos'], $c['revision'], $c['errores']];
            foreach ($total as $k => $v) {
                $total[$k] += $c[$k];
            }
        }
        $filas[] = ['TOTAL', $total['principales'], $total['cautelares'], $total['actuaciones'], $total['plazos'], $total['omitidos'], $total['revision'], $total['errores']];

        $this->table(
            ['Hoja', 'Principales', 'Cautelares', 'Actuaciones', 'Plazos', 'Omitidos/duplicados', 'Requiere revisión', 'Errores'],
            $filas
        );

        if ($this->notas !== []) {
            $this->warn('Notas (omisiones, duplicados, matching fallido y marcas de revisión):');
            $this->table(['Hoja', 'Expediente', 'Nota'], $this->notas);
        }

        if ($this->errores !== []) {
            $this->error('Errores por fila (transacción revertida, el resto continuó):');
            $this->table(['Hoja', 'Expediente', 'Error'], $this->errores);
        }
    }
}
