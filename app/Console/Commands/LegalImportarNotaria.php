<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\TramiteNotarial;
use App\Models\User;
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
 * Importa el histórico de trámites notariales desde el Excel "D. Notaria
 * Hinojosa. Registro de documentos pendientes" del área legal (registro D).
 *
 * Estructura real del libro — 3 hojas con columnas distintas:
 *  - "SIGM" (~83 filas): contratos SIGM con placa, monto, modalidad y las
 *    fechas F. Firma oficina / F. Firma notaría / F. Recogido / Archivo,
 *    más el responsable (texto: Gianella, Guilmer, Lapa, Rosa). Las celdas
 *    de fecha traen valores especiales: 'No firmo', 'Pendiente',
 *    'No entregado'. La numeración tiene un N° 11 duplicado y hay fechas
 *    incoherentes (recogido antes de la firma): se importan IGUAL con
 *    requiere_revision — nunca se autocorrige.
 *  - "G.Hipotecaria" (~5 filas): partidas registrales con recojo por
 *    documento (C. Minuta / L. Contrato / Testimonio) → tipo
 *    'garantia_hipotecaria', descripcion 'Partida {X}', fecha_recojo = la
 *    última de las tres.
 *  - "Otros" (~11 filas): cartas notariales, arrendamientos, declaraciones
 *    juradas, transferencias, testimonios → el texto del trámite se mapea a
 *    TramiteNotarial::TIPOS y F.Recep. hace de ingreso a notaría.
 *
 * Estado derivado de las fechas (en este orden):
 *  Archivo con fecha → 'archivado'; F. Recogido con fecha → 'recogido';
 *  F. Firma notaría = 'No firmo' → 'no_firmo' (desde F. Firma oficina);
 *  F. Recogido 'Pendiente'/'No entregado' o firma en notaría sin recojo →
 *  'por_recoger' (desde F. Firma notaría); solo F. Firma oficina →
 *  'en_notaria'. Una fila sin ninguna fecha se omite (estado_desde es NOT
 *  NULL y no se inventan fechas).
 *
 * Matching (nunca se crean clientes/usuarios/vehículos desde aquí):
 *  - Cliente por nombre normalizado (todas las palabras vía LIKE, en
 *    cualquier orden apellido-nombre); si no matchea EXACTO un único
 *    cliente → client_id null, el nombre queda en la descripción y se marca
 *    requiere_revision 'Cliente no identificado'.
 *  - Garantía por placa: vehiculos.placa → garantía más reciente del
 *    vehículo; si no hay, garantia_id null + nota.
 *  - Responsable por users.name LIKE; si no matchea único, null + nota.
 *
 * Idempotencia: si ya existe un trámite con el mismo tipo + descripcion
 * (+ client_id cuando se identificó) + estado_desde, la fila se salta como
 * "ya importado".
 *
 * Cada fila va en su propia transacción: una que falla no tumba el resto.
 * Con --dry-run se hace rollback por fila y se muestra el mismo resumen.
 *
 * OJO memoria: se lee con setReadDataOnly() y por bloques con corte al
 * primer bloque vacío — los libros del área arrastran filas fantasma (solo
 * formato); nunca toArray() de la hoja completa.
 *
 * Uso:
 *   php artisan legal:importar-notaria "ruta/al/D. Notaria Hinojosa....xlsx" --dry-run
 *   php artisan legal:importar-notaria "ruta/al/D. Notaria Hinojosa....xlsx"
 */
class LegalImportarNotaria extends Command
{
    protected $signature = 'legal:importar-notaria
        {archivo : Ruta del Excel "D. Notaria Hinojosa. Registro de documentos pendientes"}
        {--dry-run : Simula la importación (rollback por fila) y muestra el mismo resumen}';

    protected $description = 'Importa el histórico de trámites notariales (hojas SIGM, G.Hipotecaria y Otros) desde el Excel "D. Notaria Hinojosa" del área legal';

    /** Todas las filas del registro D pertenecen a la misma notaría */
    private const NOTARIA = 'Notaría Hinojosa';

    /** Filas por bloque al leer una hoja (corta al primer bloque totalmente vacío) */
    private const FILAS_POR_BLOQUE = 200;

    /**
     * Configuración por hoja: filas de cabecera tras la fila cuyo B dice "N°"
     * y columnas (índice 0 desde A). La hoja SIGM lleva 2 filas de cabecera;
     * G.Hipotecaria, 3 (el recojo se desglosa en C. Minuta / L. Contrato /
     * Testimonio); Otros, 2 (sin firma: F.Recep. hace de ingreso a notaría).
     */
    private const HOJAS = [
        'SIGM' => [
            'cabeceras' => 2,
            'col' => ['numero' => 1, 'nombre' => 2, 'placa' => 3, 'monto' => 4, 'modalidad' => 5,
                'firma_oficina' => 6, 'firma_notaria' => 7, 'recogido' => 8, 'responsable' => 9, 'archivo' => 10],
        ],
        'G.Hipotecaria' => [
            'cabeceras' => 3,
            'col' => ['numero' => 1, 'nombre' => 2, 'partida' => 3, 'monto' => 4, 'modalidad' => 5,
                'firma_oficina' => 6, 'firma_notaria' => 7, 'rec_minuta' => 8, 'rec_contrato' => 9,
                'rec_testimonio' => 10, 'responsable' => 11, 'archivo' => 12],
        ],
        'Otros' => [
            'cabeceras' => 2,
            'col' => ['numero' => 1, 'nombre' => 2, 'tramite' => 3, 'placa_partida' => 4,
                'recepcion' => 5, 'recogido' => 6, 'archivo' => 7],
        ],
    ];

    private bool $dry = false;

    /** Contadores por hoja: [hoja => [creados, omitidos, revision, errores]] */
    private array $resumen = [];

    /** Notas del proceso (omisiones, matching fallido, marcas de revisión): [hoja, referencia, nota] */
    private array $notas = [];

    /** Errores capturados por fila: [hoja, referencia, mensaje] */
    private array $errores = [];

    /** Cachés de matching por nombre (el Excel repite clientes y responsables) */
    private array $cacheClientes = [];

    private array $cacheResponsables = [];

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
            $this->error('El libro no contiene ninguna de las hojas esperadas (SIGM, G.Hipotecaria, Otros).');

            return self::FAILURE;
        }

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    /* ─────────────────────────── Proceso por hoja ─────────────────────────── */

    private function procesarHoja(Worksheet $hoja, string $nombreHoja, array $config): void
    {
        $this->resumen[$nombreHoja] = ['creados' => 0, 'omitidos' => 0, 'revision' => 0, 'errores' => 0];
        $numerosVistos = [];

        foreach ($this->filasConDatos($hoja, $config['cabeceras']) as $nroFila => $celdas) {
            $fila = [];
            foreach ($config['col'] as $campo => $indice) {
                $fila[$campo] = $celdas[$indice] ?? '';
            }
            $fila['fila'] = $nroFila;

            // Fila de totales del área ("TOTAL" con la suma de montos): no es un trámite
            if (mb_strtoupper($fila['nombre']) === 'TOTAL' || mb_strtoupper($fila['numero']) === 'TOTAL') {
                continue;
            }
            // Fila con solo el N° pre-numerado (plantilla vacía de la hoja)
            if ($fila['nombre'] === '') {
                continue;
            }

            // N° duplicado en el Excel (ej. dos N° 11 en SIGM): se importa igual, con revisión
            $fila['numero_duplicado'] = $fila['numero'] !== '' && in_array($fila['numero'], $numerosVistos, true);
            if ($fila['numero'] !== '') {
                $numerosVistos[] = $fila['numero'];
            }

            $datos = match ($nombreHoja) {
                'SIGM' => $this->normalizarFilaSigm($fila),
                'G.Hipotecaria' => $this->normalizarFilaHipotecaria($fila),
                'Otros' => $this->normalizarFilaOtros($fila),
            };

            $this->importarTramite($datos, $nombreHoja);
        }
    }

    /**
     * Generador de filas con datos (A:M normalizadas a texto), leídas por
     * bloques. La cabecera empieza en la fila cuyo B dice "N°"; los datos,
     * $cabeceras filas después. Corta al primer bloque totalmente vacío:
     * las hojas del área arrastran filas fantasma (solo formato).
     */
    private function filasConDatos(Worksheet $hoja, int $cabeceras): Generator
    {
        $inicio = null;
        foreach ($hoja->rangeToArray('B1:B10', null, true, true, false) as $i => $fila) {
            if (trim((string) $fila[0]) === 'N°') {
                $inicio = $i + 1 + $cabeceras; // $i es índice 0 → fila real +1, más las filas de cabecera
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
            $bloque = $hoja->rangeToArray("A{$desde}:M{$hasta}", null, true, true, false);

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

    /* ──────────────────── Normalización por tipo de hoja ──────────────────── */

    /**
     * Forma común de una fila para importarTramite():
     * tipo, nombre, placa (para vincular garantía), descripcion_base,
     * detalle (monto/modalidad → nota), responsable y las 4 fechas crudas
     * (ingreso a notaría, firma en notaría, recogido, archivo).
     */
    private function normalizarFilaSigm(array $fila): array
    {
        $detalle = array_filter([
            $fila['monto'] !== '' ? 'S/ '.$fila['monto'] : null,
            ($fila['modalidad'] !== '' && $fila['modalidad'] !== '-') ? $fila['modalidad'] : null,
        ]);

        return [
            'fila' => $fila['fila'],
            'numero' => $fila['numero'],
            'numero_duplicado' => $fila['numero_duplicado'],
            'tipo' => 'contrato_sigm',
            'nombre' => $fila['nombre'],
            'placa' => $fila['placa'],
            'descripcion_base' => trim('Placa '.($fila['placa'] !== '' ? $fila['placa'] : '—').($detalle !== [] ? ' — '.implode(' ', $detalle) : '')),
            'responsable' => $fila['responsable'],
            'f_ingreso' => $fila['firma_oficina'],
            'f_firma' => $fila['firma_notaria'],
            'f_recogido' => $fila['recogido'],
            'f_archivo' => $fila['archivo'],
            'notas_extra' => [],
        ];
    }

    private function normalizarFilaHipotecaria(array $fila): array
    {
        // Recojo por documento: manda la última fecha; si falta alguno y no
        // está archivado, queda anotado (recojo parcial), sin autocorregir.
        $recojos = ['C. Minuta' => $fila['rec_minuta'], 'L. Contrato' => $fila['rec_contrato'], 'Testimonio' => $fila['rec_testimonio']];
        $ultimaFecha = null;
        $pendientes = [];
        foreach ($recojos as $documento => $valor) {
            $fecha = $this->parsearFecha($valor);
            if ($fecha !== null) {
                $ultimaFecha = ($ultimaFecha === null || $fecha->gt($ultimaFecha)) ? $fecha : $ultimaFecha;
            } else {
                $pendientes[] = $documento;
            }
        }

        $notasExtra = [];
        if ($ultimaFecha !== null && $pendientes !== [] && $this->parsearFecha($fila['archivo']) === null) {
            $notasExtra[] = 'Recojo parcial en el Excel: sin fecha de '.implode(' / ', $pendientes);
        }

        return [
            'fila' => $fila['fila'],
            'numero' => $fila['numero'],
            'numero_duplicado' => $fila['numero_duplicado'],
            'tipo' => 'garantia_hipotecaria',
            'nombre' => $fila['nombre'],
            'placa' => '', // partida registral: no hay vehículo que vincular
            'descripcion_base' => 'Partida '.($fila['partida'] !== '' ? $fila['partida'] : '—'),
            'responsable' => $fila['responsable'],
            'f_ingreso' => $fila['firma_oficina'],
            'f_firma' => $fila['firma_notaria'],
            'f_recogido' => $ultimaFecha?->toDateString() ?? implode(' ', array_unique(array_filter($recojos))),
            'f_archivo' => $fila['archivo'],
            'notas_extra' => array_merge($notasExtra, array_filter([
                $fila['monto'] !== '' ? 'Monto S/ '.$fila['monto'] : null,
            ])),
        ];
    }

    private function normalizarFilaOtros(array $fila): array
    {
        return [
            'fila' => $fila['fila'],
            'numero' => $fila['numero'],
            'numero_duplicado' => $fila['numero_duplicado'],
            'tipo' => $this->tipoDeTramite($fila['tramite']),
            'nombre' => $fila['nombre'],
            'placa' => $fila['placa_partida'],
            'descripcion_base' => trim($fila['tramite'].($fila['placa_partida'] !== '' ? ' — '.$fila['placa_partida'] : '')),
            'responsable' => '',
            'f_ingreso' => $fila['recepcion'], // F.Recep. hace de ingreso a notaría
            'f_firma' => '', // la hoja Otros no registra firma
            'f_recogido' => $fila['recogido'],
            'f_archivo' => $fila['archivo'],
            'notas_extra' => [],
        ];
    }

    /** Mapea el texto libre del trámite (hoja Otros) a TramiteNotarial::TIPOS */
    private function tipoDeTramite(string $texto): string
    {
        $t = $this->sinAcentos(mb_strtolower($texto));

        return match (true) {
            str_contains($t, 'carta') => 'carta_notarial',
            str_contains($t, 'arrend') => 'contrato_arrendamiento',
            str_contains($t, 'declarac') => 'declaracion_jurada',
            str_contains($t, 'testimonio') => 'testimonio',
            // el Excel trae el typo "Tranferencia Vehicular"
            str_contains($t, 'transferencia') || str_contains($t, 'tranferencia') => 'transferencia_vehicular',
            default => 'otro',
        };
    }

    /* ───────────────────────── Importación por fila ───────────────────────── */

    private function importarTramite(array $d, string $nombreHoja): void
    {
        $contador = &$this->resumen[$nombreHoja];
        $referencia = trim(($d['numero'] !== '' ? "N° {$d['numero']} " : '')."{$d['nombre']} (fila {$d['fila']})");

        $ingreso = $this->parsearFecha($d['f_ingreso']);
        $firma = $this->parsearFecha($d['f_firma']);
        $recojo = $this->parsearFecha($d['f_recogido']);
        $archivo = $this->parsearFecha($d['f_archivo']);

        // Estado derivado de las fechas; sin ninguna fecha no hay estado_desde posible
        [$estado, $estadoDesde] = $this->derivarEstado($d, $ingreso, $firma, $recojo, $archivo);
        if ($estado === null || $estadoDesde === null) {
            $contador['omitidos']++;
            $this->nota($nombreHoja, $referencia, $d['numero'] === ''
                ? 'Fila sin N° ni fechas (posible co-firmante de la fila anterior) — omitida'
                : 'Sin ninguna fecha en el Excel — omitida (estado_desde es obligatorio y no se inventan fechas)');

            return;
        }

        $notasRevision = [];

        // Cliente por nombre; si no matchea único, el trámite igual entra (client_id null)
        $cliente = $this->buscarClientePorNombre($d['nombre']);
        $descripcion = $cliente !== null
            ? $d['descripcion_base']
            : trim($d['nombre'].' — '.$d['descripcion_base']);
        if ($cliente === null) {
            $notasRevision[] = "Cliente no identificado: '{$d['nombre']}'";
        }

        // Idempotencia: mismo tipo + descripcion (+ cliente si se identificó) + estado_desde → ya importado
        $existente = TramiteNotarial::where('tipo', $d['tipo'])
            ->where('descripcion', $descripcion)
            ->where('estado_desde', $estadoDesde->toDateString())
            ->when($cliente !== null, fn ($q) => $q->where('client_id', $cliente->id))
            ->exists();
        if ($existente) {
            $contador['omitidos']++;
            $this->nota($nombreHoja, $referencia, 'Ya importado: existe un trámite con el mismo tipo, descripción y estado_desde');

            return;
        }

        $notasProceso = $d['notas_extra'];

        if ($d['numero_duplicado']) {
            $notasRevision[] = "N° {$d['numero']} duplicado en el Excel";
        }

        // Fechas incoherentes del registro manual: se importan igual, con revisión
        foreach ([
            [$firma, $ingreso, 'F. Firma en notaría anterior a la firma en oficina'],
            [$recojo, $firma, 'F. Recogido anterior a la firma en notaría'],
            [$archivo, $recojo, 'F. Archivo anterior al recojo'],
        ] as [$posterior, $anterior, $texto]) {
            if ($posterior !== null && $anterior !== null && $posterior->lt($anterior)) {
                $notasRevision[] = $texto.' ('.$posterior->format('d/m/Y').' < '.$anterior->format('d/m/Y').')';
            }
        }

        // Garantía vía placa del vehículo (la celda puede traer más de una placa)
        [$garantiaId, $notaGarantia] = $this->buscarGarantiaPorPlaca($d['placa']);
        if ($notaGarantia !== null) {
            $notasProceso[] = $notaGarantia;
        }

        // Responsable (texto: Gianella, Guilmer, Lapa, Rosa) → users.name LIKE
        $responsableId = null;
        if ($d['responsable'] !== '') {
            $responsableId = $this->buscarResponsable($d['responsable']);
            if ($responsableId === null) {
                $notasProceso[] = "Responsable '{$d['responsable']}' no encontrado en usuarios";
            }
        }

        DB::beginTransaction();
        try {
            $notas = array_merge(
                ["Importado del Excel \"D. Notaria Hinojosa\" (hoja {$nombreHoja}, fila {$d['fila']})"],
                $notasProceso,
                $notasRevision !== [] ? ['REVISAR: '.implode('; ', $notasRevision)] : [],
            );

            TramiteNotarial::create([
                'garantia_id' => $garantiaId,
                'contrato_id' => null,
                'client_id' => $cliente?->id,
                'tipo' => $d['tipo'],
                'descripcion' => $descripcion,
                'notaria' => self::NOTARIA,
                'estado' => $estado,
                'estado_desde' => $estadoDesde->toDateString(),
                'fecha_ingreso_notaria' => $ingreso?->toDateString(),
                'fecha_firma' => $firma?->toDateString(),
                'fecha_recojo' => $recojo?->toDateString(),
                'responsable_id' => $responsableId,
                'requiere_revision' => $notasRevision !== [],
                'nota' => implode('. ', $notas),
            ]);

            $this->dry ? DB::rollBack() : DB::commit();

            $contador['creados']++;
            if ($notasRevision !== []) {
                $contador['revision']++;
                foreach ($notasRevision as $nota) {
                    $this->nota($nombreHoja, $referencia, $nota);
                }
            }
            foreach ($notasProceso as $nota) {
                $this->nota($nombreHoja, $referencia, $nota);
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $contador['errores']++;
            $this->errores[] = [$nombreHoja, $referencia, mb_substr($e->getMessage(), 0, 160)];
        }
    }

    /**
     * Estado según las fechas del Excel (ver docblock de la clase). Devuelve
     * [estado, estado_desde]; [null, null] cuando no hay ninguna fecha.
     */
    private function derivarEstado(array $d, ?Carbon $ingreso, ?Carbon $firma, ?Carbon $recojo, ?Carbon $archivo): array
    {
        if ($archivo !== null) {
            return ['archivado', $archivo];
        }
        if ($recojo !== null) {
            return ['recogido', $recojo];
        }
        if ($this->esNoFirmo($d['f_firma'])) {
            return ['no_firmo', $ingreso];
        }
        if ($firma !== null || $this->esPendiente($d['f_recogido'])) {
            return ['por_recoger', $firma ?? $ingreso];
        }
        if ($ingreso !== null) {
            return ['en_notaria', $ingreso];
        }

        return [null, null];
    }

    /* ─────────────────────────────── Matching ─────────────────────────────── */

    /**
     * Cliente por nombre normalizado: todas las palabras deben aparecer (LIKE,
     * en cualquier orden apellido-nombre) sobre nombre + apellidos. Solo se
     * acepta un match ÚNICO; con 0 o varios candidatos devuelve null y el
     * llamador marca requiere_revision. Nunca se crean clientes desde aquí.
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
        if (array_key_exists($clave, $this->cacheClientes)) {
            return $this->cacheClientes[$clave];
        }

        $q = Client::query();
        foreach ($palabras as $palabra) {
            $q->whereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ['%'.$palabra.'%']);
        }
        $candidatos = $q->limit(2)->get();

        return $this->cacheClientes[$clave] = ($candidatos->count() === 1 ? $candidatos->first() : null);
    }

    /**
     * Garantía por placa: vehiculos.placa → la garantía más reciente del
     * vehículo. La celda puede traer varias placas ("B1X702 F4G406") o una
     * partida registral (no vehicular): se intenta cada token y, si ninguno
     * vincula, garantia_id queda null con nota (sin marcar revisión).
     * Devuelve [garantia_id|null, nota|null].
     */
    private function buscarGarantiaPorPlaca(string $placas): array
    {
        $placas = strtoupper(trim($placas));
        if ($placas === '') {
            return [null, null];
        }

        $sinVehiculo = [];
        $sinGarantia = [];
        foreach (preg_split('/\s+/', $placas) ?: [] as $placa) {
            $vehiculo = Vehiculo::where('placa', $placa)->first();
            if ($vehiculo === null) {
                $sinVehiculo[] = $placa;

                continue;
            }
            $garantia = $vehiculo->garantias()->orderByDesc('garantias.id')->first();
            if ($garantia !== null) {
                return [$garantia->id, null];
            }
            $sinGarantia[] = $placa;
        }

        if ($sinGarantia !== []) {
            return [null, 'Vehículo con placa '.implode(', ', $sinGarantia).' sin garantía registrada — trámite sin vincular'];
        }

        return [null, 'Placa/partida '.implode(', ', $sinVehiculo).' sin vehículo registrado — trámite sin vincular'];
    }

    /** Responsable (nombre de pila del Excel) → users.name LIKE, solo si el match es único */
    private function buscarResponsable(string $nombre): ?int
    {
        $clave = $this->sinAcentos(mb_strtolower(trim($nombre)));
        if (array_key_exists($clave, $this->cacheResponsables)) {
            return $this->cacheResponsables[$clave];
        }

        $candidatos = User::where('name', 'LIKE', '%'.trim($nombre).'%')->limit(2)->get();

        return $this->cacheResponsables[$clave] = ($candidatos->count() === 1 ? $candidatos->first()->id : null);
    }

    /* ─────────────────────────────── Utilitarios ─────────────────────────────── */

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

    /**
     * Fecha de celda del Excel: serial de Excel (lectura sin formato),
     * 'M/D/AAAA' (lectura con formato, estilo US de PhpSpreadsheet) o ISO.
     * Los textos 'No firmo' / 'Pendiente' / 'No entregado' devuelven null.
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

    /** 'No firmo' / 'No firmó' del registro manual */
    private function esNoFirmo(string $valor): bool
    {
        return str_contains($this->sinAcentos(mb_strtolower($valor)), 'no firmo');
    }

    /** 'Pendiente' / 'No entregado' en la celda de recojo */
    private function esPendiente(string $valor): bool
    {
        $t = $this->sinAcentos(mb_strtolower($valor));

        return str_contains($t, 'pendiente') || str_contains($t, 'no entregado');
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
        $total = ['creados' => 0, 'omitidos' => 0, 'revision' => 0, 'errores' => 0];
        foreach ($this->resumen as $hoja => $c) {
            $filas[] = [$hoja, $c['creados'], $c['omitidos'], $c['revision'], $c['errores']];
            foreach ($total as $k => $v) {
                $total[$k] += $c[$k];
            }
        }
        $filas[] = ['TOTAL', $total['creados'], $total['omitidos'], $total['revision'], $total['errores']];

        $this->table(
            ['Hoja', 'Trámites creados', 'Omitidos', 'Requiere revisión', 'Errores'],
            $filas
        );

        if ($this->notas !== []) {
            $this->warn('Notas (omisiones, matching fallido y marcas de revisión):');
            $this->table(['Hoja', 'Trámite', 'Nota'], $this->notas);
        }

        if ($this->errores !== []) {
            $this->error('Errores por fila (transacción revertida, el resto continuó):');
            $this->table(['Hoja', 'Trámite', 'Error'], $this->errores);
        }
    }
}
