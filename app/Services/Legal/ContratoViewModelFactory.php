<?php

namespace App\Services\Legal;

use App\Models\Garantia;
use App\Support\Legal\BancosVoucher;
use App\Support\Legal\FechaEnLetras;
use App\Support\Legal\Genero;
use App\Support\Legal\LegalSettings;
use App\Support\Legal\Ordinales;
use App\Support\NumerosEnLetras;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Arma el ContratoViewModel. Dos caminos:
 *  - crear(Garantia, $parametros): desde los datos vivos (garantía, crédito,
 *    cronograma real de credit_installments, legal_settings) — genera además
 *    el snapshot que se congela en contratos.datos_snapshot.
 *  - desdeSnapshot($snapshot): reconstruye el MISMO documento de un contrato
 *    emitido, aunque cliente/crédito hayan cambiado después.
 *
 * Regla de oro: el cronograma y la cuota SALEN de credit_installments — jamás
 * se recalculan aquí (la fuente de verdad es lo que el sistema cobra).
 */
class ContratoViewModelFactory
{
    public function crear(Garantia $garantia, array $parametros): ContratoViewModel
    {
        $garantia->loadMissing(['client', 'codeudor', 'credit.installments', 'vehiculos']);
        $credit = $garantia->credit;

        // ─── Deudores ───
        $deudores = [];
        if ($garantia->tipo_persona === 'juridica') {
            $empresa = $parametros['empresa'] ?? [];
            $gerente = $empresa['gerente'] ?? [];
            $deudores[] = [
                'esJuridica' => true,
                'sexo' => 'F', // la empresa se redacta como LA DEUDORA
                'nombre' => mb_strtoupper($empresa['razon_social'] ?? $garantia->client?->fullName() ?? ''),
                'dni' => null,
                'ruc' => $empresa['ruc'] ?? $garantia->client?->documento,
                'partida' => $empresa['partida'] ?? null,
                'oficinaRegistral' => $empresa['oficina_registral'] ?? null,
                'nacionalidad' => null,
                'ocupacion' => null,
                'estadoCivil' => null,
                'domicilio' => $this->domicilio($empresa['domicilio'] ?? null, $garantia->client),
                'correo' => mb_strtoupper($empresa['correo'] ?? (string) $garantia->client?->email),
                'gerente' => [
                    'sexo' => mb_strtoupper($gerente['genero'] ?? 'M'),
                    'nombre' => mb_strtoupper($gerente['nombre'] ?? ''),
                    'dni' => $gerente['dni'] ?? null,
                    'ocupacion' => mb_strtoupper($gerente['ocupacion'] ?? ''),
                    'estadoCivil' => mb_strtoupper($gerente['estado_civil'] ?? ''),
                    'domicilio' => mb_strtoupper($gerente['domicilio'] ?? ''),
                ],
            ];
        } else {
            foreach (array_filter([$garantia->client, $garantia->codeudor]) as $cliente) {
                $deudores[] = [
                    'esJuridica' => false,
                    'sexo' => mb_strtoupper((string) ($cliente->sexo ?: 'M')),
                    'nombre' => mb_strtoupper($cliente->fullName()),
                    'dni' => $cliente->documento,
                    'ruc' => null,
                    'partida' => null,
                    'oficinaRegistral' => null,
                    'nacionalidad' => mb_strtoupper($cliente->nacionalidad ?: 'PERUANO'),
                    'ocupacion' => mb_strtoupper((string) $cliente->ocupacion),
                    'estadoCivil' => mb_strtoupper((string) $cliente->estado_civil),
                    'domicilio' => $this->domicilio(null, $cliente),
                    'correo' => mb_strtoupper((string) $cliente->email),
                    'gerente' => null,
                ];
            }
        }

        // ─── Bienes (vehículos con su pivot) ───
        $bienes = $garantia->vehiculos->map(fn ($v) => [
            'placa' => $v->placa,
            'marca' => mb_strtoupper((string) $v->marca),
            'modelo' => mb_strtoupper((string) $v->modelo),
            'motor' => mb_strtoupper((string) $v->nro_motor),
            'serie' => mb_strtoupper((string) $v->nro_serie),
            'categoria' => mb_strtoupper((string) $v->categoria),
            'anio' => $v->anio,
            'carroceria' => mb_strtoupper((string) $v->carroceria),
            'color' => mb_strtoupper((string) $v->color),
            'combustible' => mb_strtoupper((string) $v->combustible),
            'valor' => (float) $v->valor,
            'esFuturo' => (bool) $v->pivot->es_bien_futuro,
            'actaNotarial' => $v->pivot->acta_notarial,
            'kardex' => $v->pivot->kardex,
            'notario' => $v->pivot->notario ? mb_strtoupper($v->pivot->notario) : null,
            'fechaActa' => $v->pivot->fecha_acta,
        ])->values()->all();

        // ─── Cronograma real (credit_installments; filas 0 = fines de semana del diario) ───
        // sortBy explícito: la relación installments no ordena y la BD puede
        // devolver las filas en cualquier orden (bug real: la cuota 48 salía primera).
        $cuotasReales = $credit->installments
            ->sortBy('num_cuota')->values()
            ->map(fn ($i) => [
                'n' => $i->num_cuota,
                'fecha' => Carbon::parse($i->fecha_vencimiento)->format('d/m/Y'),
                'monto' => round((float) $i->importe_cuota + (float) $i->importe_interes + (float) $i->importe_excedente, 2),
            ])
            ->filter(fn ($f) => $f['monto'] > 0)
            ->values();

        // Cuota "del contrato": la más frecuente del cronograma (CuotaUniforme
        // iguala casi todas; la moda absorbe el redondeo de la primera/última).
        // countBy con clave string: un float como clave de array se truncaría a int.
        $cuota = (float) ($cuotasReales
            ->countBy(fn ($f) => number_format($f['monto'], 2, '.', ''))
            ->sortDesc()->keys()->first() ?? 0);
        $totalCronograma = round($cuotasReales->sum('monto'), 2);

        $datos = [
            'numero' => $parametros['numero'] ?? '',
            'conjunto' => [
                'juridica' => $garantia->tipo_persona === 'juridica',
                'sexos' => array_column($deudores, 'sexo'),
            ],
            'deudores' => $deudores,
            'constantes' => LegalSettings::todas(),
            'gps' => (bool) $garantia->gps,
            'custodia' => (bool) $garantia->custodia,
            'destino' => $parametros['destino'] ?? 'propio',
            'tercero' => $parametros['tercero'] ?? null,
            'bienes' => $bienes,
            'montos' => [
                'valor_bien' => round(array_sum(array_column($bienes, 'valor')), 2),
                'obligacion' => round((float) $credit->importe, 2),
                'monto_maximo' => round((float) $garantia->monto_gravamen, 2),
                'cuota' => $cuota,
            ],
            'numCuotas' => (int) $credit->cuotas,
            'frecuencia' => $credit->tipoPlanillaLabel(),
            'fecha' => $parametros['fecha'] ?? now()->toDateString(),
            'voucher' => $parametros['voucher'] ?? null,
            'cronograma' => [
                'filas' => $cuotasReales->all(),
                'total' => $totalCronograma,
            ],
            'clausulasAdicionales' => $parametros['clausulas_adicionales'] ?? null,
        ];

        return self::desdeSnapshot($datos);
    }

    /** Reconstruye el view-model desde datos_snapshot (reimpresión exacta). */
    public static function desdeSnapshot(array $datos): ContratoViewModel
    {
        $conj = $datos['conjunto'];
        $g = $conj['juridica']
            ? Genero::de('F', juridica: true)
            : Genero::conjunto($conj['sexos']);

        $deudores = array_map(function (array $d) {
            $d['g'] = Genero::de($d['sexo'], juridica: $d['esJuridica']);
            if ($d['gerente']) {
                $d['gerente']['g'] = Genero::de($d['gerente']['sexo']);
            }

            return $d;
        }, $datos['deudores']);

        $clausulas = self::clausulas((bool) $datos['gps'], (bool) $datos['custodia']);

        $montos = [];
        foreach ($datos['montos'] as $clave => $valor) {
            $montos[$clave] = [
                'cifra' => number_format((float) $valor, 2),
                'letras' => NumerosEnLetras::monto((float) $valor),
            ];
        }

        $fecha = Carbon::parse($datos['fecha']);

        $voucher = $datos['voucher'] ?? null;
        if ($voucher) {
            $voucher['titulo'] = BancosVoucher::titulo($voucher['banco'], $voucher['modalidad']);
            $voucher['bancoNombreLegal'] = BancosVoucher::nombreLegal($voucher['banco']);
            $voucher['camposOrdenados'] = BancosVoucher::transcripcion($voucher['banco'], $voucher['modalidad'], $voucher['campos'] ?? []);
            $voucher['imagenAbs'] = ! empty($voucher['imagen_path'])
                ? Storage::disk('public')->path($voucher['imagen_path'])
                : null;
            $voucher['imagenUrl'] = ! empty($voucher['imagen_path'])
                ? '/storage/'.ltrim($voucher['imagen_path'], '/')
                : null;
        }

        return new ContratoViewModel(
            numero: $datos['numero'] ?? '',
            g: $g,
            deudores: $deudores,
            constantes: $datos['constantes'],
            ord: new Ordinales($clausulas),
            clausulas: $clausulas,
            gps: (bool) $datos['gps'],
            custodia: (bool) $datos['custodia'],
            destino: $datos['destino'],
            tercero: $datos['tercero'],
            bienes: $datos['bienes'],
            montos: $montos,
            numCuotas: (int) $datos['numCuotas'],
            frecuencia: $datos['frecuencia'],
            fechaLarga: FechaEnLetras::larga($fecha),
            fechaSimple: mb_strtoupper($fecha->format('d').' DE '.FechaEnLetras::mes($fecha->month).' DEL '.$fecha->format('Y')),
            voucher: $voucher,
            cronograma: $datos['cronograma'],
            clausulasAdicionales: $datos['clausulasAdicionales'] ?? null,
            snapshot: $datos,
        );
    }

    /** Lista ordenada de cláusulas activas según los parámetros de la garantía. */
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

    private function domicilio(?string $override, $cliente): string
    {
        if ($override) {
            return mb_strtoupper($override);
        }
        if (! $cliente) {
            return '';
        }

        $partes = array_filter([
            $cliente->direccion,
            $cliente->distrito ? 'DISTRITO DE '.$cliente->distrito : null,
            $cliente->provincia ? 'PROVINCIA DE '.$cliente->provincia : null,
            $cliente->departamento ? 'DEPARTAMENTO DE '.$cliente->departamento : null,
        ]);

        return mb_strtoupper(implode(', ', $partes));
    }
}
