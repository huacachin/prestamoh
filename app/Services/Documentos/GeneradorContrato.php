<?php

namespace App\Services\Documentos;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Vehiculo;
use App\Support\Audit;
use App\Support\Documentos\BancosVoucher;
use App\Support\Documentos\DomicilioLegal;
use App\Support\Documentos\FechaEnLetras;
use App\Support\Documentos\Genero;
use App\Support\Documentos\ModelosContrato;
use App\Support\Documentos\Ordinales;
use App\Support\NumerosEnLetras;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Generador del CONTRATO de garantía mobiliaria (documento suelto: sin Anexo 1
 * ni Anexo 2 — van por separado). Arma el snapshot congelado desde (Client,
 * Credit, vehículos, preset del modelo, campos editables del wizard) +
 * config('documentos') como constantes, y emite el DocumentoCliente versionado.
 *
 * VALIDACIÓN SUAVE: solo bloquea lo imposible (deudor sin nombre/DNI, sin
 * vehículo con placa, sin cronograma). Todo lo demás son campos EDITABLES del
 * wizard con defaults del sistema, que van al snapshot tal como el usuario
 * los deje.
 *
 * REGLA DE ORO: el cronograma sale SIEMPRE de credit_installments (ordenado
 * por num_cuota, sin filas de monto 0), jamás recalculado. La cuota "del
 * documento" es la moda del cronograma, contada con clave string (countBy con
 * float trunca a entero).
 *
 * Contrato de datos de $datos (campos editables del wizard; TODOS opcionales,
 * los ausentes toman el default del sistema):
 *  - fecha (Y-m-d), valor_bien, monto_maximo, cuota
 *  - deudores[i]{nombre, dni, nacionalidad, ocupacion, estado_civil,
 *    domicilio, correo, sexo}  (i=0 titular, i=1 codeudor)
 *  - codeudor_client_id (presets de 2 deudores)
 *  - empresa{razon_social, ruc, partida, oficina_registral, domicilio, correo,
 *    gerente{nombre, dni, sexo, nacionalidad, ocupacion, estado_civil, domicilio}}
 *    (presets de persona jurídica)
 *  - tercero{nombre, dni, cuenta, motivo}  (presets con destino 'tercero')
 *  - banco (clave de BancosVoucher — la cláusula de constancia lo menciona;
 *    el tenor gráfico del voucher queda para el Anexo 2)
 *  - bienes[vehiculo_id]{es_futuro, fecha_acta, kardex, notario}  (sin N° de
 *    acta: ninguna de las 32 maestras lo cita)
 *  - clausulas_adicionales
 */
class GeneradorContrato
{
    /** Arma el snapshot del contrato (misma estructura que el factory legacy del área). */
    public static function construirSnapshot(Client $client, Credit $credit, array $vehiculoIds, string $modelo, array $datos = []): array
    {
        $preset = ModelosContrato::get($modelo);

        $deudores = $preset['personas'] === 'empresa'
            ? [self::deudorJuridico($client, $datos['empresa'] ?? [])]
            : self::deudoresNaturales($client, $preset, $datos);

        $bienes = self::bienes($vehiculoIds, $preset, $datos['bienes'] ?? []);

        // ─── Cronograma real (credit_installments; la relación no ordena) ───
        $filas = $credit->installments()
            ->orderBy('num_cuota')
            ->get(['num_cuota', 'fecha_vencimiento', 'importe_cuota', 'importe_interes', 'importe_excedente'])
            ->map(fn ($i) => [
                'n' => (int) $i->num_cuota,
                'fecha' => $i->fecha_vencimiento?->format('d/m/Y') ?? '',
                'monto' => round((float) $i->importe_cuota + (float) $i->importe_interes + (float) $i->importe_excedente, 2),
            ])
            ->filter(fn ($f) => $f['monto'] > 0)
            ->values();

        // Cuota del contrato = moda del cronograma; clave STRING (countBy con float trunca).
        $cuotaModa = (float) ($filas
            ->countBy(fn ($f) => number_format($f['monto'], 2, '.', ''))
            ->sortDesc()->keys()->first() ?? 0);

        $cuota = self::montoEditable($datos, 'cuota', $cuotaModa);

        return [
            'numero' => "CONTRATO CRÉDITO N° {$credit->id}",
            'modelo' => $modelo,
            'conjunto' => [
                'juridica' => $preset['personas'] === 'empresa',
                'sexos' => array_column($deudores, 'sexo'),
            ],
            'deudores' => $deudores,
            'constantes' => config('documentos'),
            'gps' => (bool) $preset['gps'],
            'custodia' => (bool) $preset['custodia'],
            'destino' => $preset['destino'],
            'tercero' => $preset['destino'] === 'tercero' ? [
                'nombre' => trim((string) ($datos['tercero']['nombre'] ?? '')),
                'dni' => trim((string) ($datos['tercero']['dni'] ?? '')),
                'banco' => mb_strtoupper(trim((string) ($datos['tercero']['banco'] ?? ''))),
                'cuenta' => trim((string) ($datos['tercero']['cuenta'] ?? '')),
                'motivo' => trim((string) ($datos['tercero']['motivo'] ?? '')),
            ] : null,
            'bienes' => $bienes,
            'montos' => [
                'valor_bien' => self::montoEditable($datos, 'valor_bien', round(array_sum(array_column($bienes, 'valor')), 2)),
                'obligacion' => round((float) $credit->importe, 2),
                // Gravamen máximo: por defecto cuota × n° de cuotas reales del cronograma.
                'monto_maximo' => self::montoEditable($datos, 'monto_maximo', round($cuota * $filas->count(), 2)),
                'cuota' => $cuota,
            ],
            // Cuotas que efectivamente se cobran (sin filas 0 de fines de semana
            // del diario) — mismo criterio que el Anexo 1 emitido.
            'numCuotas' => $filas->count(),
            'frecuencia' => $credit->tipoPlanillaLabel(),
            'fecha' => filled($datos['fecha'] ?? null) ? $datos['fecha'] : now()->toDateString(),
            'banco' => filled($datos['banco'] ?? null) ? $datos['banco'] : null,
            'cronograma' => [
                'filas' => $filas->all(),
                'total' => round($filas->sum('monto'), 2),
            ],
            // Congeladas en el snapshot: la numeración del documento emitido no
            // cambia aunque el motor de cláusulas cambie después.
            'clausulas' => self::clausulas((bool) $preset['gps'], (bool) $preset['custodia']),
            'clausulasAdicionales' => filled($datos['clausulas_adicionales'] ?? null) ? $datos['clausulas_adicionales'] : null,
        ];
    }

    /** Reconstruye el view-model desde el snapshot congelado (reimpresión exacta). */
    public static function vmDesdeSnapshot(array $snapshot): ContratoVm
    {
        $conj = $snapshot['conjunto'];
        $g = $conj['juridica']
            ? Genero::de('F', juridica: true)
            : Genero::conjunto($conj['sexos']);

        $deudores = array_map(function (array $d) {
            $d['g'] = Genero::de($d['sexo'], juridica: $d['esJuridica']);
            if ($d['gerente'] ?? null) {
                $d['gerente']['g'] = Genero::de($d['gerente']['sexo']);
            }

            return $d;
        }, $snapshot['deudores']);

        $clausulas = $snapshot['clausulas'] ?? self::clausulas((bool) $snapshot['gps'], (bool) $snapshot['custodia']);

        $montos = [];
        foreach ($snapshot['montos'] as $clave => $valor) {
            $montos[$clave] = [
                'cifra' => number_format((float) $valor, 2),
                'letras' => NumerosEnLetras::monto((float) $valor),
            ];
        }

        // La fecha llega 'd/m/Y' desde el wizard o 'Y-m-d' del default —
        // Carbon::parse interpreta las barras como m/d/Y y revienta con día > 12.
        $fecha = preg_match('/^\d{2}\/\d{2}\/\d{4}$/', (string) $snapshot['fecha'])
            ? Carbon::createFromFormat('d/m/Y', $snapshot['fecha'])->startOfDay()
            : Carbon::parse($snapshot['fecha']);

        // Sin tenor gráfico aquí (va en el Anexo 2): la cláusula de constancia
        // solo menciona la denominación legal del banco elegido.
        $voucher = filled($snapshot['banco'] ?? null)
            ? ['banco' => $snapshot['banco'], 'bancoNombreLegal' => BancosVoucher::nombreLegal($snapshot['banco'])]
            : null;

        return new ContratoVm(
            numero: $snapshot['numero'] ?? '',
            g: $g,
            deudores: $deudores,
            constantes: $snapshot['constantes'],
            ord: new Ordinales($clausulas),
            clausulas: $clausulas,
            gps: (bool) $snapshot['gps'],
            custodia: (bool) $snapshot['custodia'],
            destino: $snapshot['destino'],
            tercero: $snapshot['tercero'],
            bienes: $snapshot['bienes'],
            montos: $montos,
            numCuotas: (int) $snapshot['numCuotas'],
            frecuencia: $snapshot['frecuencia'],
            fechaLarga: FechaEnLetras::larga($fecha),
            fechaSimple: mb_strtoupper($fecha->format('d').' DE '.FechaEnLetras::mes($fecha->month).' DEL '.$fecha->format('Y')),
            voucher: $voucher,
            cronograma: $snapshot['cronograma'],
            clausulasAdicionales: $snapshot['clausulasAdicionales'] ?? null,
            snapshot: $snapshot,
        );
    }

    /**
     * GUARD DE EMISIÓN: exige TODO lo que la guía del área pide para el modelo
     * elegido, para que ningún contrato salga con un hueco.
     *
     * Reemplaza a la "validación suave" anterior, que solo bloqueaba lo
     * imposible de redactar y dejaba que nacionalidad, ocupación, estado
     * civil, domicilio, correo, partida registral y los datos del bien futuro
     * se imprimieran en blanco.
     *
     * Es lo que hace innecesario corregir el dato histórico: si un cliente
     * viejo nunca declaró su ocupación, el contrato no se emite y el mensaje
     * dice dónde arreglarlo. Todo condicionado al preset — pedirle partida
     * registral a una persona natural bloquearía modelos que no la necesitan.
     *
     * Se invoca desde previsualizar() y generar(), así que cubre preview,
     * emisión y reemisión. NO toca vmDesdeSnapshot(): un documento ya emitido
     * se vuelve a renderizar tal como se firmó.
     *
     * @return list<string> errores bloqueantes, con la pestaña que los corrige
     */
    public static function validar(Client $client, Credit $credit, array $vehiculoIds, string $modelo, array $datos = []): array
    {
        if (! isset(ModelosContrato::MODELOS[$modelo])) {
            return ["El modelo de contrato '{$modelo}' no existe."];
        }

        $preset = ModelosContrato::get($modelo);
        $snapshot = self::construirSnapshot($client, $credit, $vehiculoIds, $modelo, $datos);
        $errores = [];

        // El modelo elegido debe corresponder al sexo del deudor: los 10 pares
        // Deudor/Deudora son idénticos salvo el nombre, así que elegir el que
        // no toca producía un contrato con el género cambiado y sin aviso.
        $sexoDeudor = $snapshot['deudores'][0]['sexo'] ?? null;
        if (! ModelosContrato::coherenteConSexo($modelo, $sexoDeudor)) {
            $errores[] = 'El modelo "'.$preset['nombre'].'" es para un deudor de sexo '
                .($preset['sexo'] === 'F' ? 'femenino' : 'masculino')
                .', y la ficha dice '.($sexoDeudor === 'F' ? 'femenino' : 'masculino').'.';
        }

        foreach ($snapshot['deudores'] as $i => $d) {
            $rol = $d['esJuridica'] ? 'la empresa deudora' : ($i === 0 ? 'el deudor' : 'el codeudor');

            if (blank($d['nombre'])) {
                $errores[] = 'Falta el nombre de '.$rol.'.';
            }
            if (blank($d['domicilio'])) {
                $errores[] = 'Falta el domicilio de '.$rol.' (ficha del cliente → Dirección y ubigeo).';
            }
            if (blank($d['correo'])) {
                $errores[] = 'Falta el correo de '.$rol.': la cláusula de notificaciones lo declara.';
            }

            if ($d['esJuridica']) {
                if (blank($d['ruc'])) {
                    $errores[] = 'Falta el RUC de la empresa deudora.';
                } elseif (! preg_match('/^\d{11}$/', (string) $d['ruc'])) {
                    $errores[] = 'El RUC de la empresa deudora debe tener 11 dígitos.';
                }
                if (blank($d['partida'])) {
                    $errores[] = 'Falta la partida registral de la empresa deudora.';
                }
                if (blank($d['oficinaRegistral'])) {
                    $errores[] = 'Falta la oficina registral de la empresa deudora.';
                }

                $g = $d['gerente'] ?? [];
                foreach ([
                    'nombre' => 'el nombre',
                    'dni' => 'el DNI',
                    'nacionalidad' => 'la nacionalidad',
                    'ocupacion' => 'la ocupación',
                    'estadoCivil' => 'el estado civil',
                    'domicilio' => 'el domicilio',
                ] as $campo => $etiqueta) {
                    if (blank($g[$campo] ?? null)) {
                        $errores[] = 'Falta '.$etiqueta.' del gerente general.';
                    }
                }

                continue;
            }

            if (blank($d['dni'])) {
                $errores[] = 'Falta el documento de '.$rol.'.';
            }
            if (blank($d['sexo'])) {
                $errores[] = 'Falta el sexo de '.$rol.': de él depende si el contrato dice DEUDOR o DEUDORA.';
            }
            if (blank($d['nacionalidad'])) {
                $errores[] = 'Falta la nacionalidad de '.$rol.'.';
            }
            if (blank($d['ocupacion'])) {
                $errores[] = 'Falta la ocupación de '.$rol.' (ficha del cliente → Ocupación).';
            }
            if (blank($d['estadoCivil'])) {
                $errores[] = 'Falta el estado civil de '.$rol.' (ficha del cliente → Estado civil).';
            }
        }

        if ($snapshot['bienes'] === []) {
            $errores[] = 'El contrato necesita al menos un vehículo en garantía.';
        }
        if (count($snapshot['bienes']) !== count(ModelosContrato::slots($preset['bienes']))) {
            $errores[] = 'El modelo "'.$preset['nombre'].'" exige '
                .count(ModelosContrato::slots($preset['bienes'])).' vehículo(s) y llegaron '
                .count($snapshot['bienes']).'.';
        }

        foreach ($snapshot['bienes'] as $i => $b) {
            $n = $i + 1;
            if (blank($b['placa'])) {
                $errores[] = "El vehículo {$n} no tiene placa.";
            }
            // Los datos de la ficha que el contrato describe en la cláusula del bien.
            foreach (['marca' => 'la marca', 'modelo' => 'el modelo', 'serie' => 'el N° de serie', 'motor' => 'el N° de motor'] as $campo => $etiqueta) {
                if (blank($b[$campo] ?? null)) {
                    $errores[] = "Falta {$etiqueta} del vehículo {$n} (pestaña Vehículos).";
                }
            }
            if ((float) ($b['valor'] ?? 0) <= 0) {
                $errores[] = "Falta el valor del vehículo {$n}: la cláusula de ejecución se remite a él.";
            }
            // Bien futuro: la declaración jurada cita la transferencia.
            if ($b['esFuturo'] ?? false) {
                foreach (['fechaActa' => 'la fecha de transferencia', 'kardex' => 'el kárdex', 'notario' => 'el notario', 'estadoRegistral' => 'el estado registral'] as $campo => $etiqueta) {
                    if (blank($b[$campo] ?? null)) {
                        $errores[] = "Falta {$etiqueta} del vehículo {$n}, que es bien futuro.";
                    }
                }
            }
        }

        if ($snapshot['cronograma']['filas'] === []) {
            $errores[] = "El crédito #{$credit->id} no tiene cronograma (credit_installments).";
        }

        if (blank($snapshot['banco'])) {
            $errores[] = 'Falta el banco del desembolso: la constancia de entrega lo menciona.';
        }

        if ($snapshot['destino'] === 'gerente') {
            $g = $snapshot['deudores'][0]['gerente'] ?? [];
            if (blank($g['banco'] ?? null)) {
                $errores[] = 'El depósito al gerente necesita el banco de su cuenta personal.';
            }
            if (blank($g['cuenta'] ?? null)) {
                $errores[] = 'El depósito al gerente necesita su cuenta personal.';
            }
        }

        if ($snapshot['destino'] === 'tercero') {
            foreach ([
                'nombre' => 'el nombre del tercero',
                'dni' => 'el DNI del tercero',
                'banco' => 'el banco del tercero',
                'cuenta' => 'la cuenta o CCI del tercero',
                'motivo' => 'el motivo de la autorización',
            ] as $campo => $etiqueta) {
                if (blank($snapshot['tercero'][$campo] ?? null)) {
                    $errores[] = 'El depósito a tercero necesita '.$etiqueta.'.';
                }
            }
        }

        return $errores;
    }

    /**
     * HTML del contrato con medio 'previa' (iframe del wizard), sin persistir
     * nada — mismo snapshot y misma vista que el PDF emitido.
     */
    /** @throws \InvalidArgumentException con la lista de bloqueantes si la validación falla */
    private static function asegurarValido(Client $client, Credit $credit, array $vehiculoIds, string $modelo, array $datos): void
    {
        $errores = self::validar($client, $credit, $vehiculoIds, $modelo, $datos);
        if ($errores !== []) {
            throw new \InvalidArgumentException('El contrato no puede generarse: '.implode(' | ', $errores));
        }
    }

    public static function previsualizar(Client $client, Credit $credit, array $vehiculoIds, string $modelo, array $datos = []): string
    {
        self::asegurarValido($client, $credit, $vehiculoIds, $modelo, $datos);

        $snapshot = self::construirSnapshot($client, $credit, $vehiculoIds, $modelo, $datos);

        return RenderDocumento::html($snapshot, 'contrato', 'previa');
    }

    /**
     * Emite el contrato: versiona por (cliente, crédito, tipo 'contrato'),
     * renderiza el PDF, lo guarda en el disco public y crea el
     * DocumentoCliente con el snapshot congelado y el modelo usado.
     */
    public static function generar(Client $client, Credit $credit, array $vehiculoIds, string $modelo, array $datos = []): DocumentoCliente
    {
        self::asegurarValido($client, $credit, $vehiculoIds, $modelo, $datos);

        return DB::transaction(function () use ($client, $credit, $vehiculoIds, $modelo, $datos) {
            $snapshot = self::construirSnapshot($client, $credit, $vehiculoIds, $modelo, $datos);

            $version = (int) DocumentoCliente::where('client_id', $client->id)
                ->where('credit_id', $credit->id)
                ->where('tipo', 'contrato')
                ->lockForUpdate()
                ->max('version') + 1;

            $contenido = Pdf::loadHTML(
                RenderDocumento::html($snapshot, 'contrato', 'pdf')
            )->setPaper('a4')->output();

            $path = "documentos/cliente-{$client->id}/contrato-credito-{$credit->id}-v{$version}.pdf";
            Storage::disk('public')->put($path, $contenido);

            $doc = DocumentoCliente::create([
                'client_id' => $client->id,
                'credit_id' => $credit->id,
                'tipo' => 'contrato',
                'modelo' => $modelo,
                'version' => $version,
                'snapshot' => $snapshot,
                'pdf_path' => $path,
                'sha256' => hash('sha256', $contenido),
                'estado' => 'emitido',
                'generado_por' => auth()->id(),
            ]);

            $nombreModelo = ModelosContrato::get($modelo)['nombre'];
            Audit::log("Generó el Contrato v{$version} del crédito #{$credit->id} (modelo {$nombreModelo})", $doc);

            return $doc;
        });
    }

    /** Lista ordenada de cláusulas activas según los parámetros del modelo. */
    public static function clausulas(bool $gps, bool $custodia): array
    {
        $claves = ['datos', 'objeto', 'bien', 'declaracion', 'valor', 'obligacion', 'vigencia', 'ejecucion'];
        if ($gps) {
            $claves[] = 'gps';
        }
        if ($custodia) {
            $claves[] = 'custodia';
        }

        return array_merge($claves, [
            'representantes', 'interes', 'legislacion', 'sigm', 'correo',
            'constancia', 'supletoria', 'prohibicion',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Armado interno del snapshot
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Deudores persona natural: titular + (preset de 2 personas) codeudor.
     * Cada campo toma el override del wizard y, si no viene, el default de la
     * ficha del cliente. El codeudor puede venir de la BD (codeudor_client_id)
     * o tipeado a mano en el wizard (sin ficha).
     */
    private static function deudoresNaturales(Client $client, array $preset, array $datos): array
    {
        $fichas = [$client];
        if ($preset['personas'] === 2) {
            $fichas[] = filled($datos['codeudor_client_id'] ?? null)
                ? Client::find($datos['codeudor_client_id'])
                : null;
        }

        $deudores = [];
        foreach ($fichas as $i => $ficha) {
            $o = $datos['deudores'][$i] ?? [];

            $deudores[] = [
                'esJuridica' => false,
                'sexo' => mb_strtoupper(trim((string) (($o['sexo'] ?? null) ?: $ficha?->sexo ?: 'M'))),
                'nombre' => mb_strtoupper(trim((string) (($o['nombre'] ?? null) ?: $ficha?->fullName() ?: ''))),
                'dni' => trim((string) (($o['dni'] ?? null) ?: $ficha?->documento ?: '')),
                // "CON DNI N°" estaba hardcodeado: un deudor con carné de
                // extranjería salía "IDENTIFICADO CON DNI N° <su CE>". Mismo
                // patrón que ya usaban los Anexos 1 y 2 (documento_tipo).
                'documentoTipo' => self::etiquetaDocumento($ficha?->tipo_documento),
                'ruc' => null,
                'partida' => null,
                'oficinaRegistral' => null,
                // Base masculina ('PERUANO'): el partial la flexiona con el Genero del deudor.
                'nacionalidad' => mb_strtoupper(trim((string) (($o['nacionalidad'] ?? null) ?: $ficha?->nacionalidad ?: 'PERUANO'))),
                // La ficha tiene `ocupacion` desde el 28/08; `giro` es el rubro
                // del negocio y solo sirve de último recurso.
                'ocupacion' => mb_strtoupper(trim((string) (($o['ocupacion'] ?? null) ?: $ficha?->ocupacion ?: $ficha?->giro ?: ''))),
                'estadoCivil' => mb_strtoupper(trim((string) (($o['estado_civil'] ?? null) ?: $ficha?->estado_civil ?: ''))),
                'domicilio' => filled($o['domicilio'] ?? null)
                    ? mb_strtoupper(trim($o['domicilio']))
                    : ($ficha ? self::domicilio($ficha) : ''),
                'correo' => mb_strtoupper(trim((string) (($o['correo'] ?? null) ?: $ficha?->email ?: ''))),
                'gerente' => null,
            ];
        }

        return $deudores;
    }

    /** Deudor persona jurídica: la empresa se redacta como LA DEUDORA; el gerente se flexiona aparte. */
    private static function deudorJuridico(Client $client, array $empresa): array
    {
        $gerente = $empresa['gerente'] ?? [];

        return [
            'esJuridica' => true,
            'sexo' => 'F', // la empresa se redacta como LA DEUDORA
            'nombre' => mb_strtoupper(trim((string) (($empresa['razon_social'] ?? null) ?: $client->fullName()))),
            'dni' => null,
            'ruc' => trim((string) (($empresa['ruc'] ?? null) ?: $client->documento ?: '')),
            'partida' => trim((string) ($empresa['partida'] ?? '')),
            'oficinaRegistral' => mb_strtoupper(trim((string) ($empresa['oficina_registral'] ?? ''))),
            'nacionalidad' => null,
            'ocupacion' => null,
            'estadoCivil' => null,
            'domicilio' => filled($empresa['domicilio'] ?? null)
                ? mb_strtoupper(trim($empresa['domicilio']))
                : self::domicilio($client),
            'correo' => mb_strtoupper(trim((string) (($empresa['correo'] ?? null) ?: $client->email ?: ''))),
            'gerente' => [
                'sexo' => mb_strtoupper(trim((string) (($gerente['sexo'] ?? null) ?: 'M'))),
                'nombre' => mb_strtoupper(trim((string) ($gerente['nombre'] ?? ''))),
                'documentoTipo' => self::etiquetaDocumento($gerente['tipo_documento'] ?? null),
                'dni' => trim((string) ($gerente['dni'] ?? '')),
                // Banco y cuenta personal del gerente (a.4.1): la Guía simple
                // los exige como dato del sistema; la maestra no los imprime,
                // quedan congelados en el snapshot.
                'banco' => mb_strtoupper(trim((string) ($gerente['banco'] ?? ''))),
                'cuenta' => trim((string) ($gerente['cuenta'] ?? '')),
                'nacionalidad' => mb_strtoupper(trim((string) ($gerente['nacionalidad'] ?? ''))),
                'ocupacion' => mb_strtoupper(trim((string) ($gerente['ocupacion'] ?? ''))),
                'estadoCivil' => mb_strtoupper(trim((string) ($gerente['estado_civil'] ?? ''))),
                'domicilio' => mb_strtoupper(trim((string) ($gerente['domicilio'] ?? ''))),
            ],
        ];
    }

    /**
     * Bienes en garantía: los vehículos elegidos (en el orden del wizard) +
     * los datos por vehículo (acta, kardex, notario). El carácter futuro lo
     * fija el preset del modelo; solo el mixto (futuro_presente) lo decide el
     * wizard vehículo por vehículo.
     */
    /**
     * Etiqueta del documento de identidad tal como la escribe el contrato:
     * "IDENTIFICADO CON {etiqueta} N° ...". El CE se expande a su nombre
     * completo porque "CON CE N°" no es la forma legal.
     */
    private static function etiquetaDocumento(?string $tipo): string
    {
        return match (mb_strtoupper(trim((string) $tipo))) {
            'CE' => 'CARNÉ DE EXTRANJERÍA',
            'RUC' => 'RUC',
            default => 'DNI',
        };
    }

    /**
     * Fecha de la transferencia vehicular en el formato de las maestras:
     * "04 DE MAYO DEL 2026". Devuelve null si no vino o no se puede parsear,
     * y entonces el guard (fase 3) es quien impide emitir con el hueco.
     */
    private static function fechaActa(mixed $valor): ?string
    {
        if (! filled($valor)) {
            return null;
        }

        try {
            $f = Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return null;
        }

        return mb_strtoupper($f->format('d').' DE '.FechaEnLetras::mes($f->month).' DEL '.$f->format('Y'));
    }

    private static function bienes(array $vehiculoIds, array $preset, array $datosBienes): array
    {
        $vehiculos = Vehiculo::whereIn('id', $vehiculoIds)->get()->keyBy('id');

        $bienes = [];
        foreach ($vehiculoIds as $id) {
            $v = $vehiculos->get($id);
            if (! $v) {
                continue;
            }
            $d = $datosBienes[$v->id] ?? [];

            $esFuturo = match ($preset['bienes']) {
                'futuro', '2futuros' => true,
                'futuro_presente' => (bool) ($d['es_futuro'] ?? false),
                default => false, // 'presente', '2presentes'
            };

            $bienes[] = [
                'placa' => mb_strtoupper(trim((string) $v->placa)),
                'marca' => mb_strtoupper(trim((string) $v->marca)),
                'modelo' => mb_strtoupper(trim((string) $v->modelo)),
                'motor' => mb_strtoupper(trim((string) $v->nro_motor)),
                'serie' => mb_strtoupper(trim((string) $v->nro_serie)),
                'categoria' => mb_strtoupper(trim((string) $v->categoria)),
                'anio' => $v->anio_modelo,
                'carroceria' => mb_strtoupper(trim((string) $v->carroceria)),
                'color' => mb_strtoupper(trim((string) $v->color)),
                'combustible' => mb_strtoupper(trim((string) $v->combustible)),
                'valor' => (float) $v->valor,
                'esFuturo' => $esFuturo,
                'kardex' => filled($d['kardex'] ?? null) ? trim((string) $d['kardex']) : null,
                'notario' => filled($d['notario'] ?? null) ? mb_strtoupper(trim((string) $d['notario'])) : null,
                'fechaActa' => self::fechaActa($d['fecha_acta'] ?? null),
                // La Guía simple lo pide junto con fecha/kárdex/notario; las
                // maestras no lo imprimen — queda en el snapshot.
                'estadoRegistral' => filled($d['estado_registral'] ?? null) ? mb_strtoupper(trim((string) $d['estado_registral'])) : null,
            ];
        }

        return $bienes;
    }

    /** Monto editable del wizard: usa el override si vino con valor; si no, el default del sistema. */
    private static function montoEditable(array $datos, string $clave, float $default): float
    {
        $valor = $datos[$clave] ?? null;

        return ($valor !== null && $valor !== '') ? round((float) $valor, 2) : $default;
    }

    /** Domicilio legal en el formato de las maestras (ver DomicilioLegal). */
    private static function domicilio(Client $client): string
    {
        return DomicilioLegal::deCliente($client);
    }
}
