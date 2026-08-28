<?php

namespace App\Console\Commands;

use App\Models\Papeleta;
use App\Models\PapeletaRecurso;
use App\Models\Vehiculo;
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
 * Importa las papeletas de tránsito desde las hojas Pap.* del Excel de flota
 * "1. Relacion de Vehiculos G-IH-CH" y, opcionalmente, los recursos desde
 * los Excel de solicitudes presentadas (a. Sat / b. Atu).
 *
 * Hojas Pap.* del libro de flota:
 *  - "Pap.1": deuda por entidad/placa SIN N° de papeleta → sus filas se
 *    OMITEN con nota (no se inventan claves); su total S/ 113,164.75 es la
 *    referencia contra la que se coteja la deuda importada al pie.
 *  - "Pap.2" y "Pap.2.1.": fotopapeletas/actas con N° identificable
 *    (E3601614, ANC049528, 7001028568...). La misma papeleta aparece hasta
 *    en 6 hojas: DEDUPLICAR es el objetivo — el UNIQUE entidad+nro manda y
 *    la segunda aparición se salta como duplicado (gana la primera hoja).
 *
 * Por fila con N°: entidad (celda Sat./Atu./Sutr./Callao), vehículo por
 * placa (con arrastre: la placa solo va en la primera fila de cada grupo;
 * si el vehículo no existe se crea mínimo con propietario_tipo 'empresa' y
 * la papeleta queda con requiere_revision y nota "Vehículo no estaba en
 * flota"), código de falta, puntos, monto, fecha de infracción,
 * responsable_pago mapeado del texto y estado por palabras clave:
 * PAGad→pagada; PRESCRI/ANULad/FUNDADA→anulada; DEMANDA/JUDICIAL→
 * judicializada; FRACCION→fraccionada; descargo/apelación/recurso→
 * en_recurso; resto→pendiente. El texto original SIEMPRE va a nota.
 *
 * Recursos (--sat / --atu): se crea PapeletaRecurso SOLO cuando la
 * solicitud referencia un N° de papeleta/acta ya importado (tipo mapeado
 * del texto: Prescripción, Descargo, Apelación, Acceso, PRS, Caducidad,
 * Reconocimiento, Retiro de puntos, Verificación de datos), con
 * nro_tramite, fecha_presentacion (INICIO — obligatoria), plazo_vence
 * (FIN, o inicio + días, o 'Vence dd/mm' del texto con requiere_revision
 * de la papeleta) y resultado (APROBADO/Procedente→fundado,
 * ATENDIDO→atendido, PENDIENTE/Presentado→pendiente). Las solicitudes sin
 * papeleta vinculable se listan en el resumen como 'no vinculadas' SIN
 * importar.
 *
 * OJO columnas: las hojas traen filas digitadas ±1 columna — el N°, la
 * placa y la entidad se detectan por patrón en una ventana de columnas y
 * fecha/monto/falta/puntos se leen RELATIVOS al N° encontrado.
 *
 * Cada registro va en su propia transacción; con --dry-run se hace
 * rollback por registro (los recursos de papeletas creadas en la misma
 * corrida se validan sin insertar, porque su papeleta ya fue revertida).
 *
 * Uso:
 *   php artisan legal:importar-papeletas "ruta/1. Relacion de Vehiculos G-IH-CH (13-3-24).xlsx" --dry-run
 *   php artisan legal:importar-papeletas "ruta/1. Relacion de Vehiculos G-IH-CH (13-3-24).xlsx" \
 *       --sat="ruta/a. Sat. Relación de solicitudes presentadas.xlsx" \
 *       --atu="ruta/b. Atu. Relación de solicitudes presentadas.xlsx"
 *
 * Correr DESPUÉS de legal:importar-flota para que las placas ya existan.
 */
class LegalImportarPapeletas extends Command
{
    protected $signature = 'legal:importar-papeletas
        {archivo : Ruta del Excel de flota "1. Relacion de Vehiculos G-IH-CH" (hojas Pap.1, Pap.2, Pap.2.1)}
        {--sat= : Excel opcional "a. Sat. Relación de solicitudes presentadas" — crea recursos sobre papeletas ya importadas}
        {--atu= : Excel opcional "b. Atu. Relación de solicitudes presentadas" — ídem para actas ATU}
        {--dry-run : Simula la importación (rollback por registro) y muestra el mismo resumen}';

    protected $description = 'Importa papeletas (hojas Pap.* del Excel de flota) y, con --sat/--atu, los recursos de los Excel de solicitudes presentadas del área legal';

    private const FILAS_POR_BLOQUE = 200;

    /** Última columna leída por fila (las hojas Pap.2 llegan hasta ~BF con textos de estado) */
    private const COL_FIN = 'BH';

    /** Total de la hoja Pap.1 del Excel, referencia para cotejar la deuda importada */
    private const TOTAL_EXCEL = 'S/ 113,164.75';

    /** Celda Ent. de Pap.2 → Papeleta::ENTIDADES */
    private const ENTIDADES_TEXTO = ['sat' => 'SAT', 'atu' => 'ATU', 'sutr' => 'SUTRAN', 'callao' => 'SAT_CALLAO'];

    private bool $dry = false;

    /** Contadores papeletas por hoja: [creadas, duplicadas, omitidas, revision, errores] */
    private array $resumenPapeletas = [];

    /** Contadores recursos por hoja: [creados, no_vinculadas, omitidos, errores] */
    private array $resumenRecursos = [];

    /** Notas del proceso: [hoja, referencia, nota] */
    private array $notas = [];

    /** Errores por fila: [hoja, referencia, mensaje] */
    private array $errores = [];

    /** Solicitudes sin papeleta vinculable: [hoja, nro, placa, tipo] */
    private array $noVinculadas = [];

    /** Papeletas de esta corrida: "ENTIDAD|NRO" => id (null en dry-run: fila revertida) */
    private array $vistas = [];

    /** Recursos de esta corrida: "papeleta|tipo|tramite|fecha" => true */
    private array $recursosVistos = [];

    /** Vehículos por placa (solo ids persistidos) */
    private array $cacheVehiculos = [];

    /** Suma de montos importados en papeletas no pagadas/anuladas */
    private float $deudaImportada = 0.0;

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

        $libro = $this->abrirLibro($archivo);
        if ($libro === null) {
            return self::FAILURE;
        }

        foreach ($libro->getSheetNames() as $nombreHoja) {
            if (! str_starts_with($nombreHoja, 'Pap.')) {
                continue;
            }
            $hoja = $libro->getSheetByName($nombreHoja);
            $nombreHoja === 'Pap.1'
                ? $this->procesarHojaSinNumero($hoja, $nombreHoja)
                : $this->procesarHojaDetalle($hoja, $nombreHoja);
        }
        $libro->disconnectWorksheets();

        if ($this->resumenPapeletas === []) {
            $this->error('El libro no contiene hojas Pap.* (Pap.1, Pap.2, Pap.2.1).');

            return self::FAILURE;
        }

        // Recursos desde los Excel de solicitudes presentadas (opcionales)
        foreach (['SAT' => (string) $this->option('sat'), 'ATU' => (string) $this->option('atu')] as $entidad => $ruta) {
            if ($ruta === '') {
                continue;
            }
            if (! is_file($ruta)) {
                $this->error("No se encontró el archivo de solicitudes {$entidad}: {$ruta}");

                continue;
            }
            $libroSol = $this->abrirLibro($ruta);
            if ($libroSol === null) {
                continue;
            }
            foreach ($libroSol->getSheetNames() as $nombreHoja) {
                $this->procesarHojaSolicitudes($libroSol->getSheetByName($nombreHoja), $nombreHoja, $entidad);
            }
            $libroSol->disconnectWorksheets();
        }

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    private function abrirLibro(string $ruta): ?Spreadsheet
    {
        try {
            $lector = IOFactory::createReaderForFile($ruta);
            $lector->setReadDataOnly(true); // sin estilos: evita agotar memoria con las filas fantasma

            return $lector->load($ruta);
        } catch (Throwable $e) {
            $this->error("No se pudo leer el Excel {$ruta}: ".$e->getMessage());

            return null;
        }
    }

    /* ──────────────── Pap.1: deuda por placa SIN N° de papeleta ──────────────── */

    /**
     * La hoja Pap.1 no tiene columna de N° de papeleta: cada monto se OMITE
     * con nota (no se inventan claves). La placa va solo en la primera fila
     * del vehículo → arrastre, que se corta en las filas "Total" para no
     * contaminar el desglose Propietario/Empresa/Conductor del pie.
     */
    private function procesarHojaSinNumero(Worksheet $hoja, string $nombreHoja): void
    {
        $contador = &$this->inicializarPapeletas($nombreHoja);
        $placa = null;

        foreach ($this->filasConDatos($hoja) as $nroFila => $c) {
            $esTotal = in_array('total', [mb_strtolower(trim($c[1] ?? '')), mb_strtolower(trim($c[2] ?? ''))], true);
            if ($esTotal) {
                $placa = null; // corta el arrastre antes del desglose del pie

                continue;
            }

            $propia = $this->placaValida($c[4] ?? '');
            if ($propia !== null) {
                $placa = $propia;
            }
            if ($placa === null) {
                continue;
            }

            // Montos de la fila (las fechas llegan como seriales >= 40000 y no cuentan)
            $montos = [];
            for ($i = 5; $i <= 17; $i++) {
                $v = trim($c[$i] ?? '');
                if ($v !== '' && is_numeric($v) && (float) $v > 0 && (float) $v < 40000) {
                    $montos[] = number_format((float) $v, 2);
                }
            }
            if ($montos === []) {
                continue;
            }

            $contador['omitidas'] += count($montos);
            $this->nota($nombreHoja, "{$placa} (fila {$nroFila})", 'Sin N° de papeleta: S/ '.implode(', S/ ', $montos).' — omitida(s); los N° identificables están en Pap.2/Pap.2.1');
        }
    }

    /* ──────────────── Pap.2 / Pap.2.1: papeletas con N° ──────────────── */

    private function procesarHojaDetalle(Worksheet $hoja, string $nombreHoja): void
    {
        $contador = &$this->inicializarPapeletas($nombreHoja);
        $placaArrastre = null;
        $entidadArrastre = null;

        foreach ($this->filasConDatos($hoja) as $nroFila => $c) {
            // Entidad (Sat./Atu./Sutr./Callao) en ventana D..F, con arrastre
            foreach ([3, 4, 5] as $i) {
                $token = $this->sinAcentos(mb_strtolower(trim($c[$i] ?? '', " .\t")));
                foreach (self::ENTIDADES_TEXTO as $prefijo => $entidad) {
                    if ($token !== '' && str_starts_with($token, $prefijo)) {
                        $entidadArrastre = $entidad;
                        break 2;
                    }
                }
            }

            // Placa en ventana F..H, con arrastre (solo va en la primera fila del grupo)
            $heredada = false;
            $propia = null;
            foreach ([5, 6, 7] as $i) {
                $propia = $this->placaValida($c[$i] ?? '');
                if ($propia !== null) {
                    break;
                }
            }
            if ($propia !== null) {
                $placaArrastre = $propia;
            } else {
                $heredada = true;
            }

            // N° de papeleta/acta por patrón en ventana H..J (prefiere la columna I)
            $nro = null;
            $idxNro = null;
            foreach ([8, 7, 9] as $i) {
                $candidato = strtoupper(preg_replace('/\s+/', '', $c[$i] ?? '') ?? '');
                if (preg_match('/^[A-Z]{0,3}\d{6,10}$/', $candidato) === 1) {
                    $nro = $candidato;
                    $idxNro = $i;
                    break;
                }
            }
            if ($idxNro === null) {
                continue; // cabeceras, filas de seguimiento sin N°
            }

            $referencia = "{$nro} (fila {$nroFila})";

            if ($entidadArrastre === null) {
                $contador['omitidas']++;
                $this->nota($nombreHoja, $referencia, 'Sin entidad identificable (Sat./Atu./Sutr./Callao) — omitida');

                continue;
            }
            if ($placaArrastre === null) {
                $contador['omitidas']++;
                $this->nota($nombreHoja, $referencia, 'Sin placa en la fila ni en filas previas — omitida');

                continue;
            }

            // Deduplicación: UNIQUE entidad+nro — la misma papeleta aparece hasta en 6 hojas
            $clave = $entidadArrastre.'|'.$nro;
            if (array_key_exists($clave, $this->vistas)) {
                $contador['duplicadas']++;
                $this->nota($nombreHoja, $referencia, 'Duplicada: ya importada en esta corrida desde otra hoja');

                continue;
            }
            $existente = Papeleta::where('entidad', $entidadArrastre)->where('nro_papeleta', $nro)->first();
            if ($existente !== null) {
                $this->vistas[$clave] = $existente->id;
                $contador['duplicadas']++;
                $this->nota($nombreHoja, $referencia, 'Ya importada (existe en la base) — saltada como duplicado');

                continue;
            }

            // Fecha / monto / falta / puntos RELATIVOS al N° (hay filas corridas ±1 columna)
            $fecha = null;
            $idxFecha = null;
            foreach ([$idxNro + 1, $idxNro + 2] as $i) {
                $f = $this->fechaValida($c[$i] ?? '');
                if ($f !== null) {
                    $fecha = $f;
                    $idxFecha = $i;
                    break;
                }
            }

            $monto = null;
            $idxMonto = null;
            for ($i = $idxNro + 1; $i <= $idxNro + 4; $i++) {
                if ($i === $idxFecha) {
                    continue;
                }
                $v = trim($c[$i] ?? '');
                if ($v !== '' && is_numeric($v) && (float) $v > 0 && (float) $v < 40000) {
                    $monto = round((float) $v, 2);
                    $idxMonto = $i;
                    break;
                }
            }

            $falta = null;
            $idxFalta = null;
            for ($i = $idxNro + 2; $i <= $idxNro + 5; $i++) {
                if ($i === $idxFecha || $i === $idxMonto) {
                    continue;
                }
                $v = strtoupper(trim($c[$i] ?? ''));
                if (preg_match('/^(?=.*\d)[A-Z]{1,4}[0-9.\-]{1,5}[A-Z]?$/', $v) === 1 && mb_strlen($v) <= 10) {
                    $falta = $v;
                    $idxFalta = $i;
                    break;
                }
            }

            $puntos = null;
            if ($idxFalta !== null) {
                $v = trim($c[$idxFalta + 1] ?? '');
                if (preg_match('/^\d{1,3}$/', $v) === 1 && (int) $v <= 120) {
                    $puntos = (int) $v;
                }
            }

            // Texto descriptivo de la fila (estado, seguimiento): SIEMPRE va a nota
            $texto = $this->textoDescriptivo($c, max($idxNro + 5, ($idxFalta ?? 0) + 2), [$idxFecha, $idxMonto, $idxFalta]);
            $estado = $this->estadoDePapeleta($texto);
            $responsable = $this->responsableDePago($texto);

            DB::beginTransaction();
            try {
                [$vehiculoId, $vehiculoNuevo] = $this->vehiculoPorPlaca($placaArrastre, $nombreHoja, $nroFila);

                $notasFila = array_filter([
                    "Importado del Excel de flota (hoja {$nombreHoja}, fila {$nroFila})",
                    $texto !== '' ? "Texto del Excel: {$texto}" : null,
                    $heredada ? 'Placa heredada de la fila anterior del Excel' : null,
                    $vehiculoNuevo ? 'Vehículo no estaba en flota — creado al importar' : null,
                ]);

                $papeleta = Papeleta::create([
                    'vehiculo_id' => $vehiculoId,
                    'entidad' => $entidadArrastre,
                    'nro_papeleta' => $nro,
                    'codigo_falta' => $falta,
                    'puntos' => $puntos,
                    'fecha_infraccion' => $fecha?->toDateString(),
                    'monto' => $monto,
                    'responsable_pago' => $responsable,
                    'estado' => $estado,
                    'requiere_revision' => $vehiculoNuevo,
                    'nota' => implode('. ', $notasFila),
                ]);

                $this->dry ? DB::rollBack() : DB::commit();

                $this->vistas[$clave] = $this->dry ? null : $papeleta->id;
                $contador['creadas']++;
                if ($vehiculoNuevo) {
                    $contador['revision']++;
                    $this->nota($nombreHoja, $referencia, "Vehículo {$placaArrastre} no estaba en flota — creado mínimo (propietario_tipo 'empresa') y papeleta con requiere_revision");
                }
                if (! in_array($estado, ['pagada', 'anulada'], true)) {
                    $this->deudaImportada += (float) ($monto ?? 0);
                }
            } catch (Throwable $e) {
                DB::rollBack();
                $contador['errores']++;
                $this->errores[] = [$nombreHoja, $referencia, mb_substr($e->getMessage(), 0, 160)];
            }
        }
    }

    /** Vehículo por placa; si no existe se crea mínimo. Devuelve [id, esNuevo]. */
    private function vehiculoPorPlaca(string $placa, string $hoja, int $fila): array
    {
        if (isset($this->cacheVehiculos[$placa])) {
            return [$this->cacheVehiculos[$placa], false];
        }

        $vehiculo = Vehiculo::where('placa', $placa)->first();
        if ($vehiculo !== null) {
            $this->cacheVehiculos[$placa] = $vehiculo->id;

            return [$vehiculo->id, false];
        }

        $vehiculo = Vehiculo::create([
            'client_id' => null,
            'propietario_tipo' => 'empresa',
            'placa' => $placa,
            'observaciones' => "Creado al importar papeletas (hoja {$hoja}, fila {$fila}) — no estaba en la relación de flota",
        ]);
        if (! $this->dry) {
            $this->cacheVehiculos[$placa] = $vehiculo->id; // en dry-run se revierte con la papeleta
        }

        return [$vehiculo->id, true];
    }

    /* ──────────────── Solicitudes presentadas (a. Sat / b. Atu) ──────────────── */

    /**
     * Crea PapeletaRecurso SOLO para solicitudes que referencian un N° de
     * papeleta/acta ya importado. El N° se detecta por patrón en la ventana
     * F..H y tipo/fechas/estado se leen relativos a la celda de TIPO DE
     * SOLICITUD (las hojas Prop./Permiso/Fam./Tercero difieren en columnas).
     */
    private function procesarHojaSolicitudes(Worksheet $hoja, string $nombreHoja, string $entidad): void
    {
        $etiqueta = "{$entidad}: {$nombreHoja}";
        $contador = &$this->inicializarRecursos($etiqueta);

        foreach ($this->filasConDatos($hoja) as $nroFila => $c) {
            $nro = null;
            $idxNro = null;
            foreach ([6, 7, 5] as $i) {
                $candidato = strtoupper(preg_replace('/\s+/', '', $c[$i] ?? '') ?? '');
                if ($this->esNroDeSolicitud($candidato)) {
                    $nro = $candidato;
                    $idxNro = $i;
                    break;
                }
            }
            if ($idxNro === null) {
                continue; // cabeceras, filas de contactos, filas vacías
            }

            $placa = $this->placaValida($c[4] ?? '') ?? '—';
            $referencia = "{$nro} (fila {$nroFila})";

            // Tipo de solicitud: última celda mapeable en la ventana +4..+6
            // (así "CADUCADO E INICIADO EL PROCESO" en la etapa no gana al
            // "Descargo" de la columna TIPO DE SOLICITUD)
            $tipo = null;
            $idxTipo = null;
            for ($i = $idxNro + 4; $i <= $idxNro + 6; $i++) {
                $t = $this->tipoDeSolicitud($c[$i] ?? '');
                if ($t !== null) {
                    $tipo = $t;
                    $idxTipo = $i;
                }
            }

            // Papeleta ya importada (de esta corrida o de la base)
            [$papeletaId, $creadaEnDry] = $this->papeletaImportada($entidad, $nro, $etiqueta, $referencia);
            if ($papeletaId === null && ! $creadaEnDry) {
                $contador['no_vinculadas']++;
                $this->noVinculadas[] = [$etiqueta, $nro, $placa, $tipo !== null ? PapeletaRecurso::TIPOS[$tipo] : '—'];

                continue;
            }

            if ($tipo === null) {
                $contador['omitidos']++;
                $this->nota($etiqueta, $referencia, 'Sin tipo de solicitud identificable — no importada');

                continue;
            }

            $inicio = $this->fechaValida($c[$idxTipo + 1] ?? '');
            $fin = $this->fechaValida($c[$idxTipo + 2] ?? '');
            $dias = preg_match('/^\d{1,3}$/', trim($c[$idxTipo + 3] ?? '')) === 1 ? (int) trim($c[$idxTipo + 3]) : null;
            $textoEstado = $this->normalizarEspacios(trim(($c[$idxTipo + 4] ?? '').' — '.($c[$idxTipo + 5] ?? ''), " —\t"));

            if ($inicio === null) {
                $contador['omitidos']++;
                $this->nota($etiqueta, $referencia, 'Sin fecha de presentación (INICIO, obligatoria) — no importada');

                continue;
            }

            $notasRecurso = [];
            $marcarRevision = false;

            $plazo = $fin;
            if ($plazo === null) {
                // 'Vence dd/mm' en el texto: año del contexto (o el actual) → requiere_revision de la papeleta
                $plazo = $this->plazoDesdeTexto($textoEstado, $inicio);
                if ($plazo !== null) {
                    $marcarRevision = true;
                    $notasRecurso[] = "Plazo tomado del texto 'Vence dd/mm' con año supuesto — revisar";
                } else {
                    $plazo = $inicio->copy()->addDays($dias ?? PapeletaRecurso::plazoDias($tipo));
                }
            } elseif ($plazo->lt($inicio)) {
                $notasRecurso[] = 'El FIN del Excel es anterior a la presentación — se importa igual';
            }

            // N° de trámite: primera celda con dígitos entre el N° y el tipo
            $tramite = null;
            for ($i = $idxNro + 4; $i < $idxTipo; $i++) {
                $v = $this->normalizarEspacios($c[$i] ?? '');
                if ($v !== '' && preg_match('/\d{4,}/', $v) === 1) {
                    $tramite = mb_substr($v, 0, 40);
                    break;
                }
            }

            $resultado = $this->resultadoDeSolicitud($textoEstado);

            // Idempotencia: mismo papeleta + tipo + trámite (o fecha si no hay trámite)
            $claveRecurso = ($papeletaId ?? 'dry:'.$entidad.'|'.$nro).'|'.$tipo.'|'.($tramite ?? $inicio->toDateString());
            if (isset($this->recursosVistos[$claveRecurso])) {
                $contador['omitidos']++;
                $this->nota($etiqueta, $referencia, 'Recurso repetido en las hojas de solicitudes — omitido');

                continue;
            }
            $this->recursosVistos[$claveRecurso] = true;

            if ($papeletaId !== null) {
                $duplicado = PapeletaRecurso::where('papeleta_id', $papeletaId)
                    ->where('tipo', $tipo)
                    ->when($tramite !== null,
                        fn ($q) => $q->where('nro_tramite', $tramite),
                        fn ($q) => $q->where('fecha_presentacion', $inicio->toDateString()))
                    ->exists();
                if ($duplicado) {
                    $contador['omitidos']++;
                    $this->nota($etiqueta, $referencia, 'Ya importado: existe un recurso con el mismo tipo y trámite — omitido');

                    continue;
                }
            }

            if ($creadaEnDry) {
                // La papeleta de esta corrida ya fue revertida (dry-run): se valida sin insertar
                $contador['creados']++;
                $this->nota($etiqueta, $referencia, PapeletaRecurso::TIPOS[$tipo].' — validado sin insertar (dry-run: su papeleta se creó y revirtió en esta corrida)');

                continue;
            }

            DB::beginTransaction();
            try {
                PapeletaRecurso::create([
                    'papeleta_id' => $papeletaId,
                    'tipo' => $tipo,
                    'nro_tramite' => $tramite,
                    'fecha_presentacion' => $inicio->toDateString(),
                    'plazo_vence' => $plazo?->toDateString(),
                    'resultado' => $resultado,
                    'nota' => implode('. ', array_merge(
                        ["Importado del Excel de solicitudes {$entidad} (hoja {$nombreHoja}, fila {$nroFila})"],
                        $textoEstado !== '' ? ["Estado en el Excel: {$textoEstado}"] : [],
                        $notasRecurso,
                    )),
                ]);
                if ($marcarRevision) {
                    Papeleta::whereKey($papeletaId)->update(['requiere_revision' => true]);
                }

                $this->dry ? DB::rollBack() : DB::commit();

                $contador['creados']++;
                foreach ($notasRecurso as $nota) {
                    $this->nota($etiqueta, $referencia, $nota);
                }
            } catch (Throwable $e) {
                DB::rollBack();
                $contador['errores']++;
                $this->errores[] = [$etiqueta, $referencia, mb_substr($e->getMessage(), 0, 160)];
            }
        }
    }

    /**
     * Busca la papeleta referenciada: primero las de esta corrida, luego la
     * base por entidad+nro y, como rescate, por nro solo si es único (hay
     * actas registradas con otra entidad). Devuelve [id|null, creadaEnDry].
     */
    private function papeletaImportada(string $entidad, string $nro, string $etiqueta, string $referencia): array
    {
        $clave = $entidad.'|'.$nro;
        if (array_key_exists($clave, $this->vistas)) {
            return [$this->vistas[$clave], $this->vistas[$clave] === null];
        }

        $papeleta = Papeleta::where('entidad', $entidad)->where('nro_papeleta', $nro)->first();
        if ($papeleta === null) {
            $candidatas = Papeleta::where('nro_papeleta', $nro)->limit(2)->get();
            if ($candidatas->count() === 1) {
                $papeleta = $candidatas->first();
                $this->nota($etiqueta, $referencia, "Vinculada a la papeleta registrada con entidad {$papeleta->entidad} (el archivo es de {$entidad})");
            }
        }
        // También puede haberse creado en esta corrida (dry) con otra entidad
        if ($papeleta === null) {
            foreach ($this->vistas as $claveVista => $id) {
                if (str_ends_with($claveVista, '|'.$nro)) {
                    return [$id, $id === null];
                }
            }
        }

        return [$papeleta?->id, false];
    }

    /* ─────────────────────────────── Mapeos ─────────────────────────────── */

    /** Estado de la papeleta según el texto libre del Excel (primer match gana) */
    private function estadoDePapeleta(string $texto): string
    {
        $t = $this->sinAcentos(mb_strtolower($texto));

        return match (true) {
            str_contains($t, 'pagad') => 'pagada',
            str_contains($t, 'prescri') || str_contains($t, 'anulad') || preg_match('/(?<!in)fundada/', $t) === 1 => 'anulada',
            str_contains($t, 'demanda') || str_contains($t, 'judicial') => 'judicializada',
            str_contains($t, 'fraccion') => 'fraccionada',
            str_contains($t, 'descargo') || str_contains($t, 'apelaci') || str_contains($t, 'recurso') => 'en_recurso',
            default => 'pendiente',
        };
    }

    /** Responsable de pago según el texto libre (Prop.-Empresa antes que Propietario) */
    private function responsableDePago(string $texto): ?string
    {
        $t = $this->sinAcentos(mb_strtolower($texto));

        return match (true) {
            preg_match('/prop[.\- ]*empr/', $t) === 1 => 'prop_empresa',
            str_contains($t, 'conduct') => 'conductor',
            str_contains($t, 'propietario') => 'propietario',
            preg_match('/\bempresa\b/', $t) === 1 => 'empresa',
            default => null,
        };
    }

    /** Texto de la solicitud → PapeletaRecurso::TIPOS (null si no es una celda de tipo) */
    private function tipoDeSolicitud(string $texto): ?string
    {
        $t = $this->sinAcentos(mb_strtolower(trim($texto)));
        if ($t === '') {
            return null;
        }

        return match (true) {
            str_contains($t, 'prescri') => 'prescripcion',
            str_contains($t, 'descargo') => 'descargo',
            str_contains($t, 'apelaci') => 'apelacion',
            str_contains($t, 'acceso') => 'acceso_informacion',
            preg_match('/\bprs\b/', $t) === 1 => 'beneficio_prs',
            str_contains($t, 'caducid') || str_contains($t, 'caducad') => 'caducidad',
            str_contains($t, 'reconoc') => 'reconocimiento',
            str_contains($t, 'retiro') => 'retiro_puntos',
            str_contains($t, 'verificac') => 'verificacion_datos',
            str_contains($t, 'fraccion') => 'fraccionamiento',
            default => null,
        };
    }

    /** ATENDIDO/Procedente/APROBADO → atendido o fundado; PENDIENTE/Presentado → pendiente */
    private function resultadoDeSolicitud(string $texto): string
    {
        $t = $this->sinAcentos(mb_strtolower($texto));

        return match (true) {
            str_contains($t, 'aprobado') || str_contains($t, 'procedente') || preg_match('/(?<!in|im)fundad/', $t) === 1 => 'fundado',
            str_contains($t, 'atendido') => 'atendido',
            default => 'pendiente',
        };
    }

    /** 'Vence dd/mm' del texto libre — el año se supone del contexto (inicio) o el actual */
    private function plazoDesdeTexto(string $texto, ?Carbon $inicio): ?Carbon
    {
        if (preg_match('/vence\D{0,10}(\d{1,2})[\/.\-](\d{1,2})/i', $this->sinAcentos($texto), $m) !== 1) {
            return null;
        }
        $anio = $inicio?->year ?? now()->year;

        return checkdate((int) $m[2], (int) $m[1], $anio)
            ? Carbon::create($anio, (int) $m[2], (int) $m[1])
            : null;
    }

    /**
     * ¿La celda (ya en mayúsculas y sin espacios) es un N° de papeleta/acta
     * citado en una solicitud? Acepta E3601614, 14157633, ANC011784,
     * C434233, 76765-ATU-U... y descarta fechas (seriales y dd/mm/aaaa),
     * montos y años sueltos.
     */
    private function esNroDeSolicitud(string $candidato): bool
    {
        if ($candidato === '' || mb_strlen($candidato) > 24 || str_contains($candidato, '/')) {
            return false;
        }
        if (preg_match('/\d{5,}/', $candidato) !== 1) {
            return false;
        }
        if (preg_match('/^\d+(\.\d+)?$/', $candidato) === 1 && (float) $candidato < 100000) {
            return false; // serial de fecha o monto
        }

        return true;
    }

    /** Celdas descriptivas de la fila desde $desde: con letras, sin URLs ni horas */
    private function textoDescriptivo(array $c, int $desde, array $excluidos): string
    {
        $textos = [];
        for ($i = $desde; $i < count($c); $i++) {
            if (in_array($i, $excluidos, true)) {
                continue;
            }
            $v = $this->normalizarEspacios($c[$i] ?? '');
            if ($v === '' || mb_strlen($v) < 3 || str_starts_with($v, 'http')) {
                continue;
            }
            if (preg_match('/[a-záéíóúñ]/i', $v) !== 1) {
                continue; // seriales de fecha, montos, teléfonos
            }
            if (preg_match('/^\d{1,2}:\d{2}/', $v) === 1) {
                continue; // horas de seguimiento
            }
            $textos[$v] = true;
        }

        return mb_substr(implode(' | ', array_keys($textos)), 0, 500);
    }

    /* ─────────────────────────────── Lectura ─────────────────────────────── */

    /**
     * Generador de filas con datos (A:BH como texto), leídas por bloques con
     * corte al primer bloque totalmente vacío (filas fantasma). No se busca
     * cabecera: cada fila se acepta o descarta por patrón de N°/placa.
     */
    private function filasConDatos(Worksheet $hoja): Generator
    {
        $tope = $hoja->getHighestDataRow();
        $desde = 1;
        while ($desde <= $tope) {
            $hasta = min($desde + self::FILAS_POR_BLOQUE - 1, $tope);
            $bloque = $hoja->rangeToArray('A'.$desde.':'.self::COL_FIN.$hasta, null, true, true, false);

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

    /**
     * Fecha plausible (año 2000-2100): seriales de Excel, 'D/M/AAAA' o ISO.
     * Los montos (< 36526 = serial del 2000-01-01) y los códigos largos
     * caen fuera del rango y devuelven null.
     */
    private function fechaValida(string $valor): ?Carbon
    {
        $valor = trim($valor);
        if ($valor === '' || ! preg_match('/\d/', $valor)) {
            return null;
        }

        $fecha = null;
        if (is_numeric($valor)) {
            $serial = (float) $valor;
            if ($serial >= 1000 && $serial < 100000) {
                $fecha = Carbon::instance(FechaExcel::excelToDateTimeObject($serial))->startOfDay();
            }
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valor, $m)) {
            [, $a, $b, $anio] = $m;
            if (checkdate((int) $b, (int) $a, (int) $anio)) {
                $fecha = Carbon::create((int) $anio, (int) $b, (int) $a); // D/M/AAAA (uso local)
            } elseif (checkdate((int) $a, (int) $b, (int) $anio)) {
                $fecha = Carbon::create((int) $anio, (int) $a, (int) $b); // M/D/AAAA
            }
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            try {
                $fecha = Carbon::parse($valor)->startOfDay();
            } catch (Throwable) {
                $fecha = null;
            }
        }

        return ($fecha !== null && $fecha->year >= 2000 && $fecha->year <= 2100) ? $fecha : null;
    }

    /**
     * Normaliza una celda cruda a texto: los enteros guardados como número
     * (N° de papeleta, seriales, montos) se devuelven sin notación científica.
     */
    private function celdaComoTexto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if ((is_float($valor) || is_int($valor)) && floatval($valor) === floor(floatval($valor))) {
            return sprintf('%.0F', $valor);
        }

        // Los N° digitados a mano traen espacios duros (U+00A0, ej. "E3601614 " en
        // Pap.2/Pap.2.1) que trim() y \s sin /u no quitan: se normalizan a espacio.
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

    private function &inicializarPapeletas(string $hoja): array
    {
        $this->resumenPapeletas[$hoja] = ['creadas' => 0, 'duplicadas' => 0, 'omitidas' => 0, 'revision' => 0, 'errores' => 0];

        return $this->resumenPapeletas[$hoja];
    }

    private function &inicializarRecursos(string $hoja): array
    {
        $this->resumenRecursos[$hoja] = ['creados' => 0, 'no_vinculadas' => 0, 'omitidos' => 0, 'errores' => 0];

        return $this->resumenRecursos[$hoja];
    }

    /* ──────────────────────────────── Resumen ──────────────────────────────── */

    private function mostrarResumen(): void
    {
        $this->newLine();
        $this->info($this->dry ? 'RESUMEN (DRY RUN — nada se persistió):' : 'RESUMEN DE LA IMPORTACIÓN:');

        $filas = [];
        $total = ['creadas' => 0, 'duplicadas' => 0, 'omitidas' => 0, 'revision' => 0, 'errores' => 0];
        foreach ($this->resumenPapeletas as $hoja => $c) {
            $filas[] = [$hoja, $c['creadas'], $c['duplicadas'], $c['omitidas'], $c['revision'], $c['errores']];
            foreach ($total as $k => $v) {
                $total[$k] += $c[$k];
            }
        }
        $filas[] = ['TOTAL', $total['creadas'], $total['duplicadas'], $total['omitidas'], $total['revision'], $total['errores']];

        $this->table(
            ['Hoja', 'Papeletas creadas', 'Duplicadas (saltadas)', 'Omitidas (sin N°/placa)', 'Requiere revisión', 'Errores'],
            $filas
        );

        if ($this->resumenRecursos !== []) {
            $filas = [];
            $total = ['creados' => 0, 'no_vinculadas' => 0, 'omitidos' => 0, 'errores' => 0];
            foreach ($this->resumenRecursos as $hoja => $c) {
                $filas[] = [$hoja, $c['creados'], $c['no_vinculadas'], $c['omitidos'], $c['errores']];
                foreach ($total as $k => $v) {
                    $total[$k] += $c[$k];
                }
            }
            $filas[] = ['TOTAL', $total['creados'], $total['no_vinculadas'], $total['omitidos'], $total['errores']];

            $this->table(
                ['Hoja de solicitudes', 'Recursos creados', 'No vinculadas', 'Omitidas', 'Errores'],
                $filas
            );
        }

        $this->info(sprintf(
            'Deuda importada (papeletas no pagadas/anuladas): S/ %s — cotejar contra %s del Excel (hoja Pap.1); la diferencia son papeletas sin N° no importadas.',
            number_format($this->deudaImportada, 2),
            self::TOTAL_EXCEL
        ));

        if ($this->noVinculadas !== []) {
            $this->warn('Solicitudes sin papeleta vinculable (NO importadas):');
            $this->table(['Hoja', 'N° papeleta/acta', 'Placa', 'Tipo'], $this->noVinculadas);
        }

        if ($this->notas !== []) {
            $this->warn('Notas (omisiones, duplicados, placas heredadas y marcas de revisión):');
            $this->table(['Hoja', 'Referencia', 'Nota'], $this->notas);
        }

        if ($this->errores !== []) {
            $this->error('Errores por fila (transacción revertida, el resto continuó):');
            $this->table(['Hoja', 'Referencia', 'Error'], $this->errores);
        }
    }
}
