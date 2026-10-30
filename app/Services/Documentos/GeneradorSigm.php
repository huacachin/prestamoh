<?php

namespace App\Services\Documentos;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Garantia;
use App\Models\SigmAviso;
use App\Models\Vehiculo;
use App\Support\Audit;
use App\Support\Documentos\FechaEnLetras;
use App\Support\NumerosEnLetras;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Módulo SIGM de la pestaña Documentos (30/10/2026, Agregado 03 del Área Legal).
 *
 *  3.1 Correo previo a la constitución, para abrirlo en Gmail listo para enviar.
 *  3.2 Resumen de constitución: el texto que se pega en "Descripción de cláusula del contrato" del SIGM.
 *  3.3 Registro de la inscripción: N° de formulario, folio causal electrónico y fecha, guardados en las
 *      tablas del Área Legal (garantias + sigm_avisos, tipo 'constitucion').
 *  3.4 Resumen de cancelación: el texto para "Descripción de otros" del formulario de cancelación.
 *
 * Todo sale del contrato ya emitido del crédito (su snapshot). Sin modelo del área —y así quedan fuera
 * por ahora, decisión de Antony—: empresa, custodia y las combinaciones de vehículos que no tienen el suyo
 * (2 bienes futuros, futuro + presente, 2 vehículos sin GPS; el correo y la cancelación solo con uno).
 */
class GeneradorSigm
{
    /**
     * Lo que el contrato vigente del crédito permite generar.
     *
     * @return array{contrato: DocumentoCliente, persona: string, gps: bool, futuro: bool, placas: list<string>, nombres: list<string>, correos: list<string>, fecha: string, valores: list<string>, obligacion: string, montoMaximo: string, montoMaximoNumero: float, resumen: ?string, sinCorreo: ?string, sinResumen: ?string, sinCancelacion: ?string}
     */
    public static function contexto(Client $client, Credit $credit): array
    {
        $contrato = GeneradorDeclaracion::contratoDe($client, $credit);
        if ($contrato === null) {
            throw new InvalidArgumentException("El crédito #{$credit->id} no tiene contrato emitido: primero genera el contrato.");
        }
        $s = $contrato->snapshot;
        $deudores = $s['deudores'] ?? [];
        $bienes = array_values($s['bienes'] ?? []);
        $gps = (bool) ($s['gps'] ?? false);
        $futuros = count(array_filter($bienes, fn ($b) => ! empty($b['esFuturo'])));

        $sinModelo = match (true) {
            (bool) ($s['conjunto']['juridica'] ?? ($deudores[0]['esJuridica'] ?? false)) => 'No hay modelo para empresas.',
            (bool) ($s['custodia'] ?? false) => 'No hay modelo para contratos con custodia.',
            default => null,
        };

        // Resumen: GPS con 1 vehículo (o bien futuro) o 2 vehículos presentes; sin GPS, 1 vehículo presente.
        $resumen = match (true) {
            $sinModelo !== null => null,
            $gps && count($bienes) === 1 => $futuros ? 'gps-futuro' : 'gps',
            $gps && count($bienes) === 2 && $futuros === 0 => 'gps-2vehiculos',
            ! $gps && count($bienes) === 1 && $futuros === 0 => 'sn',
            default => null,
        };
        $unSoloBien = count($bienes) === 1 && ($gps || $futuros === 0);

        $montos = $s['montos'] ?? [];
        $valores = count($bienes) === 2
            ? array_map(fn ($b) => self::monto((float) ($b['valor'] ?? 0)), $bienes)
            : [self::monto((float) ($montos['valor_bien'] ?? ($bienes[0]['valor'] ?? 0)))];

        $persona = count($deudores) > 1 ? 'deudores' : (mb_strtoupper((string) ($deudores[0]['sexo'] ?? 'M')) === 'F' ? 'deudora' : 'deudor');
        $combinacion = 'No hay modelo para esta combinación de vehículos del contrato.';

        return [
            'contrato' => $contrato,
            'persona' => $persona,
            'gps' => $gps,
            'futuro' => $futuros > 0,
            'placas' => array_map(fn ($b) => (string) ($b['placa'] ?? ''), $bienes),
            'nombres' => array_map(fn ($d) => (string) ($d['nombre'] ?? ''), $deudores),
            'correos' => array_values(array_unique(array_filter(array_map(
                fn ($d) => mb_strtolower(trim((string) ($d['correo'] ?? ''))), $deudores
            )))),
            'fecha' => self::fechaContrato($s['fecha'] ?? null, $contrato),
            'valores' => $valores,
            'obligacion' => self::monto((float) ($montos['obligacion'] ?? $credit->importe)),
            'montoMaximo' => self::monto((float) ($montos['monto_maximo'] ?? 0)),
            'montoMaximoNumero' => (float) ($montos['monto_maximo'] ?? 0),
            'resumen' => $resumen,
            'sinCorreo' => $sinModelo ?? ($unSoloBien ? null : $combinacion),
            'sinResumen' => $sinModelo ?? ($resumen ? null : $combinacion),
            'sinCancelacion' => $sinModelo ?? (count($bienes) === 1 ? null : $combinacion),
        ];
    }

    /**
     * 3.1: el correo previo, con el enlace que lo abre en Gmail listo para enviar.
     *
     * @return array{para: list<string>, asunto: string, cuerpo: string, gmail: string}
     */
    public static function correo(array $ctx): array
    {
        if ($ctx['sinCorreo'] !== null) {
            throw new InvalidArgumentException($ctx['sinCorreo']);
        }
        $plural = $ctx['persona'] === 'deudores';
        $firma = (array) config('documentos.usuaria_sigm');

        $cuerpo = self::texto('correo', [
            'saludo' => ['deudor' => 'Estimado señor', 'deudora' => 'Estimada señora', 'deudores' => 'Estimados señores'][$ctx['persona']],
            'nombres' => implode(' y ', $ctx['nombres']),
            'plural' => $plural,
            'futuro' => $ctx['futuro'],
            'gps' => $ctx['gps'],
            'fecha' => $ctx['fecha'],
            'firma' => [
                'nombre' => mb_convert_case(mb_strtolower((string) ($firma['nombre'] ?? '')), MB_CASE_TITLE),
                'cargo' => (string) ($firma['cargo'] ?? ''),
                'dni' => (string) ($firma['dni'] ?? ''),
                'correo' => (string) ($firma['correo'] ?? ''),
                'celular' => (string) ($firma['celular'] ?? ''),
            ],
        ]);
        $asunto = 'Comunicación sobre la '.($ctx['futuro'] ? 'Pre-constitución' : 'constitución')
            .' de garantía mobiliaria en el SIGM – PLACA N° '.$ctx['placas'][0].'.';

        // Redacción de Gmail con todo puesto; authuser elige la cuenta del área si está abierta.
        $gmail = 'https://mail.google.com/mail/?'.http_build_query(array_filter([
            'view' => 'cm',
            'fs' => '1',
            'authuser' => $firma['correo'] ?? null,
            'to' => implode(',', $ctx['correos']),
            'su' => $asunto,
            'body' => $cuerpo,
        ], fn ($v) => $v !== null && $v !== ''), '', '&', PHP_QUERY_RFC3986);

        return ['para' => $ctx['correos'], 'asunto' => $asunto, 'cuerpo' => $cuerpo, 'gmail' => $gmail];
    }

    /** 3.2: el resumen para "Descripción de cláusula del contrato". */
    public static function resumen(array $ctx): string
    {
        if ($ctx['sinResumen'] !== null) {
            throw new InvalidArgumentException($ctx['sinResumen']);
        }

        return self::texto("resumen-{$ctx['resumen']}-{$ctx['persona']}", [
            'fecha' => $ctx['fecha'],
            'valores' => $ctx['valores'],
            'obligacion' => $ctx['obligacion'],
            'montoMaximo' => $ctx['montoMaximo'],
        ]);
    }

    /** 3.4: el resumen de cancelación de una constitución registrada. */
    public static function cancelacion(array $ctx, SigmAviso $constitucion): string
    {
        if ($ctx['sinCancelacion'] !== null) {
            throw new InvalidArgumentException($ctx['sinCancelacion']);
        }

        return self::texto('cancelacion', [
            'placa' => $ctx['placas'][0],
            'formulario' => (string) $constitucion->nro_formulario,
            'folio' => (string) $constitucion->folio,
            'fecha' => $constitucion->fecha_presentacion?->format('d/m/Y'),
            'sujeto' => ['deudor' => 'EL DEUDOR', 'deudora' => 'LA DEUDORA', 'deudores' => 'LOS DEUDORES'][$ctx['persona']],
            'nombres' => implode(' Y ', $ctx['nombres']),
            'plural' => $ctx['persona'] === 'deudores',
        ]);
    }

    /** 3.3: la constitución registrada del crédito (la vigente, no anulada), o null. */
    public static function constitucionDe(Credit $credit): ?SigmAviso
    {
        $garantia = Garantia::where('credit_id', $credit->id)->latest('id')->first();

        return $garantia?->avisos()->where('tipo', 'constitucion')->where('estado', '!=', 'anulado')->latest('id')->first();
    }

    /**
     * 3.3: registra (o corrige) la inscripción de la constitución. Si el crédito aún no tiene garantía se
     * crea con los datos del contrato; una que ya existía (del Área Legal) se usa tal cual, sin pisar sus
     * datos ni sus vehículos. La tasa y la caja del Área Legal no se tocan.
     */
    public static function registrar(Client $client, Credit $credit, string $formulario, string $folio, string $fecha): SigmAviso
    {
        $ctx = self::contexto($client, $credit);
        $contrato = $ctx['contrato'];

        return DB::transaction(function () use ($client, $credit, $formulario, $folio, $fecha, $ctx, $contrato) {
            $garantia = Garantia::where('credit_id', $credit->id)->latest('id')->first();
            if ($garantia === null) {
                $garantia = self::crearGarantia($client, $credit, $ctx, $contrato);
            }

            $aviso = self::constitucionDe($credit) ?? new SigmAviso(['garantia_id' => $garantia->id, 'tipo' => 'constitucion']);
            $aviso->fill([
                'nro_formulario' => $formulario,
                'folio' => $folio,
                'fecha_presentacion' => $fecha,
                'estado' => 'registrado',
                'registrado_por' => $aviso->registrado_por ?? auth()->id(),
            ]);
            $nuevo = ! $aviso->exists;
            $aviso->sinAuditoriaAutomatica(fn () => $aviso->save());
            $garantia->sincronizarConAvisos();

            Audit::log(sprintf(
                '%s la inscripción SIGM del crédito #%d: formulario %s, folio %s, del %s (placa %s)',
                $nuevo ? 'Registró' : 'Corrigió', $credit->id, $formulario, $folio,
                Carbon::parse($fecha)->format('d/m/Y'), implode(', ', $ctx['placas'])
            ), $garantia);

            return $aviso->fresh();
        });
    }

    /**
     * Quita la inscripción registrada. La garantía se borra solo si quedó vacía (sin avisos, contratos,
     * trámites notariales ni expedientes): borrarla arrastraría los contratos del Área Legal.
     */
    public static function quitarRegistro(Credit $credit): void
    {
        $aviso = self::constitucionDe($credit);
        if ($aviso === null) {
            return;
        }

        DB::transaction(function () use ($credit, $aviso) {
            $garantia = $aviso->garantia;
            Audit::log("Quitó la inscripción SIGM del crédito #{$credit->id}: formulario {$aviso->nro_formulario}, folio {$aviso->folio}", $garantia);
            $aviso->sinAuditoriaAutomatica(fn () => $aviso->delete());
            $vacia = $garantia->avisos()->doesntExist() && $garantia->contratos()->doesntExist()
                && $garantia->tramitesNotariales()->doesntExist()
                && DB::table('expedientes_judiciales')->where('garantia_id', $garantia->id)->doesntExist();
            if ($vacia) {
                $garantia->sinAuditoriaAutomatica(fn () => $garantia->delete());
            } else {
                $garantia->sincronizarConAvisos();
            }
        });
    }

    /** La garantía de un crédito que aún no la tiene, con los datos y vehículos de su contrato. */
    private static function crearGarantia(Client $client, Credit $credit, array $ctx, DocumentoCliente $contrato): Garantia
    {
        $s = $contrato->snapshot;
        $garantia = new Garantia([
            'credit_id' => $credit->id,
            'client_id' => $client->id,
            'codeudor_client_id' => $contrato->codeudor_client_id,
            'tipo' => 'mobiliaria_vehicular',
            'tipo_persona' => 'natural',
            'gps' => $ctx['gps'],
            'custodia' => (bool) ($s['custodia'] ?? false),
            'monto_gravamen' => $ctx['montoMaximoNumero'] ?: null,
            'registrado_por' => auth()->id(),
        ]);
        $garantia->sinAuditoriaAutomatica(fn () => $garantia->save());

        $orden = 0;
        $vehiculos = [];
        foreach ($s['bienes'] ?? [] as $b) {
            $id = Vehiculo::where('placa', $b['placa'] ?? '')->value('id');
            if ($id) {
                $vehiculos[$id] = [
                    'es_bien_futuro' => ! empty($b['esFuturo']),
                    'kardex' => $b['kardex'] ?? null,
                    'notario' => isset($b['notario']) ? mb_substr((string) $b['notario'], 0, 120) : null,
                    'orden' => ++$orden,
                ];
            }
        }
        $garantia->vehiculos()->sync($vehiculos);

        return $garantia;
    }

    /** "S/ 22,401.60 (VEINTIDOS MIL CUATROCIENTOS UNO CON 60/100 SOLES)", como en los modelos y el contrato. */
    public static function monto(float $valor): string
    {
        return 'S/ '.number_format($valor, 2).' ('.NumerosEnLetras::monto($valor).' SOLES)';
    }

    private static function fechaContrato(mixed $fecha, DocumentoCliente $contrato): string
    {
        $f = filled($fecha) ? GeneradorContrato::fechaDelSnapshot((string) $fecha) : Carbon::parse($contrato->created_at);

        return mb_strtoupper($f->format('d').' DE '.FechaEnLetras::mes($f->month).' DEL '.$f->format('Y'));
    }

    /** Las plantillas son texto plano: se quita el comentario de cabecera y los saltos de los bordes. */
    private static function texto(string $plantilla, array $datos): string
    {
        return trim(view("documentos.sigm.{$plantilla}", $datos)->render());
    }
}
