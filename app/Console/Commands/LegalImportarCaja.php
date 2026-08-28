<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\Legal\CajaLegal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Importa el histórico de la caja del Área Legal (caja = 4) desde el Excel
 * "Cuadre de caja Area Legal. Ingreso y egreso" (hojas mensuales Enero..Junio).
 *
 * Estructura real del libro:
 *  - Enero..Abril: un solo bloque por hoja con cabecera doble
 *    N° | FECHA | USUA. | PLACA | DESCRIPCION | SIGM(CONST./CANC.) |
 *    CLIENT.(Exp./Cod./NOMBRE) | INGRESO(S/ garantía, S/ tarifa, For. de pag.,
 *    RR. HH) | EGRESO(SIGM, NOT., BOL., OTROS). BOL. siempre es 0.
 *  - "Mayo " (con espacio final) y Junio: la hoja se parte en 3 BLOQUES con
 *    cabecera propia (trámites varios/gastos, constituciones, cancelaciones),
 *    desaparece BOL. (OTROS pasa a la columna T) y el bloque de cancelaciones
 *    solo trae monto de garantía (N) + tasa SIGM (O), con una mini-tabla de
 *    utilidad incrustada en columnas laterales que se IGNORA. Las cabeceras se
 *    detectan por contenido (fila con FECHA y DESCRIPCION) en cualquier
 *    posición; cada bloque se parsea hasta su fila Total/TOTAL/Utilidad.
 *  - "Balance " (consolidado): SE OMITE — es derivado de las hojas mensuales.
 *  - Junio está incompleto: filas pre-numeradas sin fecha ni montos → saltar.
 *
 * Reglas de negocio (del análisis del área):
 *  - El INGRESO real de la caja es la TARIFA cobrada (2.º S/), NUNCA el monto
 *    de la garantía (1.er S/): ese es el préstamo y solo va al detail como
 *    contexto.
 *  - Una fila genera HASTA 4 movimientos atómicos entre sí (transacción por
 *    fila): 1 Income (tarifa > 0) + hasta 3 Expense — SIGM (~4.00, reason
 *    'Tasa SIGM'), NOT. (40/60, 'Gasto notarial') y OTROS ('Otros gastos'; si
 *    la fila es de gasto puro —sin tarifa ni placa: sueldos, suscripciones,
 *    viáticos— el reason es la propia DESCRIPCION). BOL. siempre 0 → ignorar.
 *  - reason del Income: DESCRIPCION truncada a ~120 chars. detail SIEMPRE
 *    arma el contexto completo y determinista: "Placa X — Cliente NOMBRE
 *    (Exp. E, Cod. C) — SIGM const A canc B — garantía S/ N — pago Forma a
 *    RRHH — importado del Excel {hoja}".
 *  - Fechas: seriales de Excel y texto D/M/AAAA; '13/03//2026' se repara
 *    colapsando '//'; si no parsea → fila omitida con nota. Fecha fuera del
 *    mes de la hoja (ej. 2025-01-03 en Enero 2026) → se importa IGUAL con su
 *    fecha original, nota en el resumen y marca '[REVISAR fecha]' al inicio
 *    del detail.
 *  - caja => 4 (CajaLegal::CAJA) EXPLÍCITO en todo create: el default de la
 *    columna es 1 (caja operativa) y omitirlo contaminaría sus reportes.
 *    modo 'Otros', documento 'GUIA' (misma semántica que CajaLegal, que usa
 *    auth() y NO sirve en consola). SIN mass_deletion_id ni parent_id.
 *  - user_id: USUA. ('Rosa T.') → users.name LIKE '%Rosa%' una sola vez
 *    (caché); con 0 o >1 coincidencias → null con nota. headquarter_id 1.
 *
 * Idempotencia: antes de crear se busca un movimiento caja=4 con la misma
 * (tabla, date, total, detail) exacta → se salta como 'ya importado'. El
 * detail es determinista, así que re-ejecutar no duplica.
 *
 * OJO memoria: se lee con setReadDataOnly() y por bloques de 200 filas con
 * corte al primer bloque vacío; nunca toArray() de la hoja completa.
 *
 * Uso:
 *   php artisan legal:importar-caja "ruta/al/Cuadre de caja Area Legal....xlsx" --dry-run
 *   php artisan legal:importar-caja "ruta/al/Cuadre de caja Area Legal....xlsx"
 */
class LegalImportarCaja extends Command
{
    protected $signature = 'legal:importar-caja
        {archivo : Ruta del Excel "Cuadre de caja Area Legal. Ingreso y egreso"}
        {--dry-run : Simula la importación (rollback por fila) y muestra el mismo resumen}';

    protected $description = 'Importa el histórico de la caja del Área Legal (caja=4, hojas mensuales del Excel "Cuadre de caja") como Income/Expense';

    /** Filas por bloque al leer una hoja (corta al primer bloque totalmente vacío) */
    private const FILAS_POR_BLOQUE = 200;

    /** Hojas mensuales admitidas (título sin acentos, minúsculas, trim) */
    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'setiembre', 'octubre', 'noviembre', 'diciembre'];

    /** Totales ene–jun de la hoja "Balance " del Excel, para cotejar al pie del resumen */
    private const REF_INGRESOS = 46241.60;

    private const REF_EGRESOS = 31082.10;

    /**
     * Columnas fijas (índice 0 = A). Valen para TODOS los bloques; el resto
     * varía por tipo de bloque (ver COL_* de abajo).
     */
    private const COL = [
        'numero' => 1,       // B: N° global de la hoja
        'fecha' => 4,        // E
        'usuario' => 5,      // F: USUA.
        'placa' => 6,        // G
        'descripcion' => 7,  // H
        'sigm_const' => 8,   // I: N° SIGM de constitución
        'sigm_canc' => 9,    // J: N° SIGM de cancelación
        'exp' => 10,         // K: expediente del cliente
        'cod' => 11,         // L: código del cliente
        'nombre' => 12,      // M
        'garantia' => 13,    // N: 1.er S/ = monto del préstamo (NO es ingreso)
    ];

    /** Bloques con INGRESO (todas las hojas): tarifa/forma/RRHH/egresos */
    private const COL_TARIFA = 14;      // O: 2.º S/ = tarifa cobrada (el ingreso real)

    private const COL_FORMA_PAGO = 15;  // P: For. de pag.

    private const COL_RRHH = 16;        // Q: RR. HH (quién recibió el depósito)

    private const COL_EG_SIGM = 17;     // R: EGRESO SIGM (~4.00)

    private const COL_EG_NOT = 18;      // S: EGRESO NOT./NOTARIA (40/60)

    private const COL_OTROS_CON_BOL = 20; // U: OTROS cuando existe BOL. en T (ene–abr)

    private const COL_OTROS_SIN_BOL = 19; // T: OTROS cuando ya no hay BOL. (may–jun)

    /** Bloque solo-EGRESO (cancelaciones de may–jun): N=garantía, O=tasa SIGM */
    private const COL_EG_SIGM_CANC = 14;

    private bool $dry = false;

    /** Contadores por hoja (n = movimientos, s = suma S/) */
    private array $resumen = [];

    /** Notas del proceso: [hoja, referencia, nota] */
    private array $notas = [];

    /** Errores capturados por fila: [hoja, referencia, mensaje] */
    private array $errores = [];

    /** Caché del match de USUA. → user_id (se resuelve una sola vez por texto) */
    private array $cacheUsuarios = [];

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

        foreach ($libro->getWorksheetIterator() as $hoja) {
            $titulo = trim($hoja->getTitle()); // "Mayo " y "Balance " traen espacio final
            $clave = $this->sinAcentos(mb_strtolower($titulo));

            if (str_starts_with($clave, 'balance')) {
                $this->line("Hoja \"{$hoja->getTitle()}\" omitida: es el consolidado derivado de las hojas mensuales.");

                continue;
            }
            if (! in_array($clave, self::MESES, true)) {
                $this->warn("Hoja \"{$hoja->getTitle()}\" no es una hoja mensual conocida — saltada.");

                continue;
            }

            $this->procesarHoja($hoja, $titulo);
        }

        $libro->disconnectWorksheets();

        if ($this->resumen === []) {
            $this->error('El libro no contiene ninguna hoja mensual (Enero..Junio).');

            return self::FAILURE;
        }

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    /* ─────────────────────────── Proceso por hoja ─────────────────────────── */

    private function procesarHoja(Worksheet $hoja, string $nombreHoja): void
    {
        $this->resumen[$nombreHoja] = [
            'ing_n' => 0, 'ing_s' => 0.0,
            'sigm_n' => 0, 'sigm_s' => 0.0,
            'not_n' => 0, 'not_s' => 0.0,
            'otr_n' => 0, 'otr_s' => 0.0,
            'dup' => 0, 'salt' => 0, 'err' => 0,
        ];

        $filas = $this->leerFilas($hoja);
        $bloques = $this->detectarBloques($filas);

        if ($bloques === []) {
            $this->nota($nombreHoja, '—', 'Sin cabecera reconocible (fila con FECHA y DESCRIPCION) — hoja sin datos que importar');

            return;
        }

        // Primera pasada: mes/año esperado de la hoja = moda de Y-m de sus fechas,
        // para marcar '[REVISAR fecha]' en las que caen fuera (típico typo de año).
        $mesEsperado = $this->modaDeMes($filas, $bloques);

        foreach ($bloques as $bloque) {
            foreach ($filas as $nroFila => $celdas) {
                if ($nroFila < $bloque['desde'] || $nroFila > $bloque['hasta']) {
                    continue;
                }
                $this->procesarFila($celdas, $nroFila, $bloque, $nombreHoja, $mesEsperado);
            }
        }
    }

    /**
     * Filas con datos (A:U normalizadas a texto), leídas por bloques de 200
     * con corte al primer bloque totalmente vacío (filas fantasma del área).
     */
    private function leerFilas(Worksheet $hoja): array
    {
        $filas = [];
        $tope = $hoja->getHighestDataRow();
        $desde = 1;

        while ($desde <= $tope) {
            $hasta = min($desde + self::FILAS_POR_BLOQUE - 1, $tope);
            $bloque = $hoja->rangeToArray("A{$desde}:U{$hasta}", null, true, true, false);

            $bloqueVacio = true;
            foreach ($bloque as $i => $celdas) {
                $fila = array_map([$this, 'celdaComoTexto'], $celdas);
                if (implode('', $fila) !== '') {
                    $bloqueVacio = false;
                    $filas[$desde + $i] = $fila;
                }
            }
            if ($bloqueVacio) {
                break;
            }
            $desde = $hasta + 1;
        }

        return $filas;
    }

    /**
     * Detecta los bloques de la hoja por contenido: cabecera = fila donde
     * aparecen FECHA y DESCRIPCION (en cualquier posición de la hoja; desde
     * mayo hay 3). Tipo 'completo' si la cabecera trae INGRESO; 'egreso' si
     * solo trae EGRESO (bloque de cancelaciones). La columna de OTROS depende
     * de si el bloque aún tiene BOL. (ene–abr: U; may–jun: T). Los datos van
     * de cabecera+2 (cabecera doble) hasta la cabecera siguiente.
     */
    private function detectarBloques(array $filas): array
    {
        $cabeceras = [];
        foreach ($filas as $nroFila => $celdas) {
            $texto = $this->sinAcentos(mb_strtoupper(implode(' ', $celdas)));
            if (str_contains($texto, 'FECHA') && str_contains($texto, 'DESCRIPCION')) {
                $subTexto = isset($filas[$nroFila + 1])
                    ? $this->sinAcentos(mb_strtoupper(implode(' ', $filas[$nroFila + 1])))
                    : '';
                $cabeceras[] = [
                    'fila' => $nroFila,
                    'tipo' => str_contains($texto, 'INGRESO') ? 'completo' : 'egreso',
                    'col_otros' => str_contains($texto.' '.$subTexto, 'BOL')
                        ? self::COL_OTROS_CON_BOL
                        : self::COL_OTROS_SIN_BOL,
                ];
            }
        }

        $bloques = [];
        foreach ($cabeceras as $i => $cab) {
            $bloques[] = [
                'tipo' => $cab['tipo'],
                'col_otros' => $cab['col_otros'],
                'desde' => $cab['fila'] + 2, // cabecera doble: fila principal + sub-fila CONST./CANC./S/...
                'hasta' => isset($cabeceras[$i + 1]) ? $cabeceras[$i + 1]['fila'] - 1 : PHP_INT_MAX,
            ];
        }

        return $bloques;
    }

    /** Moda de 'Y-m' entre las fechas parseables de los bloques de la hoja */
    private function modaDeMes(array $filas, array $bloques): ?string
    {
        $conteo = [];
        foreach ($bloques as $bloque) {
            foreach ($filas as $nroFila => $celdas) {
                if ($nroFila < $bloque['desde'] || $nroFila > $bloque['hasta']) {
                    continue;
                }
                $fecha = $this->parsearFecha($celdas[self::COL['fecha']] ?? '');
                if ($fecha !== null) {
                    $conteo[$fecha->format('Y-m')] = ($conteo[$fecha->format('Y-m')] ?? 0) + 1;
                }
            }
        }
        if ($conteo === []) {
            return null;
        }
        arsort($conteo);

        return array_key_first($conteo);
    }

    /* ───────────────────────── Importación por fila ───────────────────────── */

    private function procesarFila(array $celdas, int $nroFila, array $bloque, string $nombreHoja, ?string $mesEsperado): void
    {
        $contador = &$this->resumen[$nombreHoja];
        $c = fn (string $campo): string => $celdas[self::COL[$campo]] ?? '';

        $numero = $c('numero');
        $descripcion = trim($c('descripcion'));
        $placa = trim($c('placa'));
        $nombre = trim($c('nombre'));
        $fechaTexto = $c('fecha');

        // Filas Total / TOTAL / Utilidad de la propia hoja: son derivadas, se saltan
        if (in_array($this->sinAcentos(mb_strtolower($numero)), ['total', 'utilidad'], true)
            || in_array($this->sinAcentos(mb_strtolower($descripcion)), ['total', 'utilidad'], true)) {
            $contador['salt']++;

            return;
        }

        // Montos según el tipo de bloque. BOL. (siempre 0) se ignora por regla.
        if ($bloque['tipo'] === 'egreso') {
            $tarifa = 0.0;
            $sigm = $this->monto($celdas[self::COL_EG_SIGM_CANC] ?? '');
            $notarial = 0.0;
            $otros = 0.0; // las columnas laterales (mini-tabla de utilidad) se ignoran
            $formaPago = '';
            $rrhh = '';
        } else {
            $tarifa = $this->monto($celdas[self::COL_TARIFA] ?? '');
            $sigm = $this->monto($celdas[self::COL_EG_SIGM] ?? '');
            $notarial = $this->monto($celdas[self::COL_EG_NOT] ?? '');
            $otros = $this->monto($celdas[$bloque['col_otros']] ?? '');
            $formaPago = trim($celdas[self::COL_FORMA_PAGO] ?? '');
            $rrhh = trim($celdas[self::COL_RRHH] ?? '');
        }
        $garantia = $this->monto($c('garantia'));
        $hayMontos = $tarifa > 0 || $sigm > 0 || $notarial > 0 || $otros > 0;

        $referencia = trim(($numero !== '' ? "N° {$numero} " : '').$this->recortar($descripcion, 40)." (fila {$nroFila})");
        $fecha = $this->parsearFecha($fechaTexto);

        if ($fecha === null) {
            $contador['salt']++;
            // Pre-numeradas de la plantilla y filas-suma sin fecha: salto silencioso.
            // Una fila con contenido real pero fecha ilegible sí se anota.
            if ($descripcion !== '' || $nombre !== '' || $hayMontos) {
                $this->nota($nombreHoja, $referencia, "Fecha ausente o ilegible ('{$fechaTexto}') — fila omitida");
            }

            return;
        }

        if (str_contains($fechaTexto, '//')) {
            $this->nota($nombreHoja, $referencia, "Fecha malformada '{$fechaTexto}' reparada como {$fecha->format('d/m/Y')}");
        }

        if (! $hayMontos) {
            $contador['salt']++;
            $this->nota($nombreHoja, $referencia, 'Sin tarifa ni egresos (solo contexto) — fila omitida');

            return;
        }

        $fueraDeMes = $mesEsperado !== null && $fecha->format('Y-m') !== $mesEsperado;
        if ($fueraDeMes) {
            $this->nota($nombreHoja, $referencia, '[fecha fuera del mes de la hoja] '.$fecha->format('d/m/Y').' — importada igual, con [REVISAR fecha] en el detail');
        }

        // Gasto puro (sueldos, suscripciones, viáticos): sin tarifa ni placa → solo Expense OTROS
        $gastoPuro = $tarifa <= 0 && $placa === '';

        $detalle = $this->armarDetalle($celdas, $bloque, $nombreHoja, $fueraDeMes, $garantia, $formaPago, $rrhh);
        $usuarioId = $this->buscarUsuario($c('usuario'), $nombreHoja);
        $fechaStr = $fecha->format('Y-m-d');

        // Hasta 4 movimientos por fila: 1 Income (tarifa) + hasta 3 Expense
        $movimientos = [];
        if ($tarifa > 0) {
            $movimientos[] = ['modelo' => Income::class, 'rubro' => 'ing',
                'reason' => $this->recortar($descripcion, 120) !== '' ? $this->recortar($descripcion, 120) : 'Ingreso Área Legal',
                'monto' => $tarifa];
        }
        if ($sigm > 0) {
            $movimientos[] = ['modelo' => Expense::class, 'rubro' => 'sigm', 'reason' => 'Tasa SIGM', 'monto' => $sigm];
        }
        if ($notarial > 0) {
            $movimientos[] = ['modelo' => Expense::class, 'rubro' => 'not', 'reason' => 'Gasto notarial', 'monto' => $notarial];
        }
        if ($otros > 0) {
            $movimientos[] = ['modelo' => Expense::class, 'rubro' => 'otr',
                'reason' => ($gastoPuro && $descripcion !== '') ? $this->recortar($descripcion, 120) : 'Otros gastos',
                'monto' => $otros];
        }

        // Transacción POR FILA: sus 1-4 movimientos son atómicos entre sí
        DB::beginTransaction();
        try {
            $creados = []; // [rubro, monto] — se suman al resumen recién tras el commit
            $duplicados = 0;

            foreach ($movimientos as $mov) {
                /** @var class-string<Income|Expense> $modelo */
                $modelo = $mov['modelo'];

                // Idempotencia: mismo (tabla, date, total, detail) en caja=4 → ya importado
                $existe = $modelo::where('caja', CajaLegal::CAJA)
                    ->whereDate('date', $fechaStr)
                    ->where('total', $mov['monto'])
                    ->where('detail', $detalle)
                    ->exists();
                if ($existe) {
                    $duplicados++;
                    $this->nota($nombreHoja, $referencia, "Ya importado: {$mov['reason']} S/ ".number_format($mov['monto'], 2).' (mismo date/total/detail en caja 4)');

                    continue;
                }

                $campos = [
                    'date' => $fechaStr,
                    'modo' => 'Otros',       // misma semántica que CajaLegal (que usa auth() y no sirve en consola)
                    'documento' => 'GUIA',
                    'caja' => CajaLegal::CAJA, // EXPLÍCITO: el default de la columna es 1 (caja operativa)
                    'reason' => $mov['reason'],
                    'detail' => $detalle,
                    'total' => $mov['monto'],
                    'user_id' => $usuarioId,
                    'headquarter_id' => 1,
                    // Sin mass_deletion_id ni parent_id (reglas de la caja legal).
                ];
                if ($modelo === Expense::class) {
                    $campos['document_type'] = null;
                    $campos['in_charge'] = null;
                }

                $modelo::create($campos);
                $creados[] = [$mov['rubro'], $mov['monto']];
            }

            $this->dry ? DB::rollBack() : DB::commit();

            foreach ($creados as [$rubro, $monto]) {
                $contador[$rubro.'_n']++;
                $contador[$rubro.'_s'] += $monto;
            }
            $contador['dup'] += $duplicados;
        } catch (Throwable $e) {
            DB::rollBack();
            $contador['err']++;
            $this->errores[] = [$nombreHoja, $referencia, mb_substr($e->getMessage(), 0, 160)];
        }
    }

    /**
     * detail determinista con TODO el contexto de la fila:
     * "Placa X — Cliente NOMBRE (Exp. E, Cod. C) — SIGM const A canc B —
     * garantía S/ N — pago Forma a RRHH — importado del Excel {hoja}".
     * Las filas de gasto puro (sin placa/cliente/SIGM) anteponen la
     * DESCRIPCION completa para que el contexto (y la idempotencia) no se
     * pierdan. Con fecha fuera del mes se antepone '[REVISAR fecha]'.
     */
    private function armarDetalle(array $celdas, array $bloque, string $nombreHoja, bool $fueraDeMes, float $garantia, string $formaPago, string $rrhh): string
    {
        $c = fn (string $campo): string => trim($celdas[self::COL[$campo]] ?? '');

        $partes = [];
        if ($c('placa') !== '') {
            $partes[] = 'Placa '.$c('placa');
        }
        if ($c('nombre') !== '') {
            $ids = array_filter([
                $c('exp') !== '' ? 'Exp. '.$c('exp') : null,
                $c('cod') !== '' ? 'Cod. '.$c('cod') : null,
            ]);
            $partes[] = 'Cliente '.$c('nombre').($ids !== [] ? ' ('.implode(', ', $ids).')' : '');
        }
        if ($c('sigm_const') !== '' || $c('sigm_canc') !== '') {
            $partes[] = trim('SIGM'
                .($c('sigm_const') !== '' ? ' const '.$c('sigm_const') : '')
                .($c('sigm_canc') !== '' ? ' canc '.$c('sigm_canc') : ''));
        }
        if ($garantia > 0) {
            $partes[] = 'garantía S/ '.number_format($garantia, 2); // el préstamo: SOLO contexto, nunca ingreso
        }
        if ($formaPago !== '') {
            $partes[] = 'pago '.$formaPago.($rrhh !== '' ? ' a '.$rrhh : '');
        }

        // Gasto puro / fila sin vínculos: la DESCRIPCION es el único contexto
        if ($partes === [] && $c('descripcion') !== '') {
            $partes[] = $c('descripcion');
        }

        $partes[] = 'importado del Excel '.$nombreHoja;

        // Tope determinista: si la BD truncara distinto, la idempotencia se rompería
        return $this->recortar(($fueraDeMes ? '[REVISAR fecha] ' : '').implode(' — ', $partes), 250);
    }

    /* ─────────────────────────────── Matching ─────────────────────────────── */

    /**
     * USUA. ('Rosa T.') → users.name LIKE '%Rosa%' (primer token), resuelto
     * UNA sola vez por texto (caché). Con 0 o >1 coincidencias → null + nota.
     * Nunca se crean usuarios desde aquí.
     */
    private function buscarUsuario(string $usua, string $nombreHoja): ?int
    {
        $usua = trim($usua);
        if ($usua === '') {
            return null;
        }

        $clave = $this->sinAcentos(mb_strtolower($usua));
        if (array_key_exists($clave, $this->cacheUsuarios)) {
            return $this->cacheUsuarios[$clave];
        }

        $token = trim((string) (preg_split('/\s+/', $usua) ?: [''])[0], '.,;');
        if (mb_strlen($token) < 3) {
            $this->nota($nombreHoja, '—', "USUA. '{$usua}' demasiado corto para un match confiable — user_id null");

            return $this->cacheUsuarios[$clave] = null;
        }

        $candidatos = User::where('name', 'LIKE', '%'.$token.'%')->limit(2)->get();
        if ($candidatos->count() !== 1) {
            $this->nota($nombreHoja, '—', "USUA. '{$usua}' con ".($candidatos->count() === 0 ? '0' : 'varias')." coincidencias en users (LIKE '%{$token}%') — user_id null");

            return $this->cacheUsuarios[$clave] = null;
        }

        return $this->cacheUsuarios[$clave] = $candidatos->first()->id;
    }

    /* ────────────────────────────── Utilitarios ───────────────────────────── */

    /**
     * Normaliza una celda cruda a texto: los enteros guardados como número
     * (montos, seriales de fecha) se devuelven sin notación científica.
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

    /** Monto en soles de una celda ('464.8', '1,500', 'S/ 40') → float; no numérico → 0 */
    private function monto(string $valor): float
    {
        $valor = str_replace([',', 'S/', ' '], '', trim($valor));

        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    /**
     * Fecha de celda: serial de Excel (lectura sin formato), texto D/M/AAAA
     * (registro manual peruano; se intenta M/D como último recurso) o ISO.
     * El typo '13/03//2026' se repara colapsando '//' antes de parsear.
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

        $valor = preg_replace('#/{2,}#', '/', $valor); // repara '13/03//2026'

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valor, $m)) {
            [, $a, $b, $anio] = $m;
            if (checkdate((int) $b, (int) $a, (int) $anio)) {
                return Carbon::create((int) $anio, (int) $b, (int) $a); // D/M/AAAA (preferido: fechas tipeadas a mano)
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

    private function recortar(string $texto, int $largo): string
    {
        return trim(mb_substr(trim($texto), 0, $largo));
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

    /* ──────────────────────────────── Resumen ─────────────────────────────── */

    private function mostrarResumen(): void
    {
        $this->newLine();
        $this->info($this->dry ? 'RESUMEN (DRY RUN — nada se persistió):' : 'RESUMEN DE LA IMPORTACIÓN:');

        $filas = [];
        $total = array_fill_keys(['ing_n', 'ing_s', 'sigm_n', 'sigm_s', 'not_n', 'not_s', 'otr_n', 'otr_s', 'dup', 'salt', 'err'], 0);
        foreach ($this->resumen as $hoja => $c) {
            $filas[] = [
                $hoja,
                "{$c['ing_n']} (S/ ".number_format($c['ing_s'], 2).')',
                "{$c['sigm_n']} (S/ ".number_format($c['sigm_s'], 2).')',
                "{$c['not_n']} (S/ ".number_format($c['not_s'], 2).')',
                "{$c['otr_n']} (S/ ".number_format($c['otr_s'], 2).')',
                $c['dup'], $c['salt'], $c['err'],
            ];
            foreach ($total as $k => $v) {
                $total[$k] += $c[$k];
            }
        }
        $filas[] = [
            'TOTAL',
            "{$total['ing_n']} (S/ ".number_format($total['ing_s'], 2).')',
            "{$total['sigm_n']} (S/ ".number_format($total['sigm_s'], 2).')',
            "{$total['not_n']} (S/ ".number_format($total['not_s'], 2).')',
            "{$total['otr_n']} (S/ ".number_format($total['otr_s'], 2).')',
            $total['dup'], $total['salt'], $total['err'],
        ];

        $this->table(
            ['Hoja', 'Ingresos creados', 'Eg. SIGM', 'Eg. NOT.', 'Eg. OTROS', 'Duplicados', 'Saltadas', 'Errores'],
            $filas
        );

        // Cotejo contra la hoja "Balance " del Excel (que NO se importa: es derivada)
        $egresos = $total['sigm_s'] + $total['not_s'] + $total['otr_s'];
        $this->line('Totales importados:  ingresos S/ '.number_format($total['ing_s'], 2).'  —  egresos S/ '.number_format($egresos, 2));
        $this->line('Referencia Balance (ene–jun del Excel): ingresos S/ '.number_format(self::REF_INGRESOS, 2).'  —  egresos S/ '.number_format(self::REF_EGRESOS, 2));
        $this->line('(Los duplicados saltados por idempotencia no suman: en re-ejecuciones los totales importados serán menores.)');

        if ($this->notas !== []) {
            $this->warn('Notas (omisiones, fechas fuera de mes/reparadas, matching, duplicados):');
            $this->table(['Hoja', 'Fila', 'Nota'], $this->notas);
        }

        if ($this->errores !== []) {
            $this->error('Errores por fila (transacción revertida, el resto continuó):');
            $this->table(['Hoja', 'Fila', 'Error'], $this->errores);
        }
    }
}
