<?php

namespace App\Services\Documentos;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Support\Audit;
use App\Support\Documentos\FechaEnLetras;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Declaraciones juradas de PROPIEDAD y de PUBLICIDAD (30/10/2026, Agregado 01 del Área Legal:
 * "agregar modelos de declaraciones juradas de propiedad … y de publicidad").
 *
 * No se tipea nada: TODO sale del contrato ya emitido del crédito (su snapshot congelado), que
 * tiene a los deudores, la empresa y su gerente, los vehículos, y en bien futuro el kárdex y
 * el notario. Así la declaración dice exactamente lo mismo que el contrato que acompaña.
 *
 * Una declaración por VEHÍCULO (el contrato puede tener dos): los modelos hablan de "EL
 * VEHÍCULO DE PLACA …" y así su texto legal queda intacto. El modelo se elige solo:
 *   propiedad: uno · dos · empresa · futuro-uno · futuro-dos (bien futuro del contrato)
 *   publicidad: uno · dos · empresa
 * La empresa no tiene modelo de bien futuro (el área no lo entregó): se rechaza con aviso.
 */
class GeneradorDeclaracion
{
    /** @var array<string, string> tipo de documento => nombre completo */
    public const TIPOS = [
        'djprop' => 'Declaración jurada de propiedad',
        'djpubli' => 'Declaración jurada de publicidad',
    ];

    /** @var array<string, string> variante => cómo se nombra en el listado */
    public const VARIANTES = [
        'uno' => 'un declarante',
        'dos' => 'dos declarantes',
        'empresa' => 'empresa',
        'futuro-uno' => 'bien futuro, un declarante',
        'futuro-dos' => 'bien futuro, dos declarantes',
    ];

    /** El contrato vigente del crédito (el emitido más reciente), o null si aún no hay. */
    public static function contratoDe(Client $client, Credit $credit): ?DocumentoCliente
    {
        return DocumentoCliente::where('client_id', $client->id)
            ->where('credit_id', $credit->id)
            ->where('tipo', 'contrato')
            ->where('estado', 'emitido')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Placas del contrato vigente, en su orden.
     *
     * @return list<string>
     */
    public static function placasDe(Client $client, Credit $credit): array
    {
        $contrato = self::contratoDe($client, $credit);

        return array_values(array_filter(array_map(
            fn ($b) => (string) ($b['placa'] ?? ''),
            $contrato?->snapshot['bienes'] ?? []
        )));
    }

    /**
     * Snapshot congelado de la declaración.
     *
     * @return array{tipo: string, variante: string, credito: array{numero: int}, contrato: array{id: int, version: int, modelo: ?string, fecha: string, fecha_texto: string}, fecha: string, fecha_de: string, fecha_del: string, ciudad: string, declarantes: list<array{nombre: string, documento_tipo: string, documento: string, domicilio: string}>, empresa: ?array<string, mixed>, bien: array{placa: string, es_futuro: bool, kardex: ?string, notario: ?string}, constantes: array<string, mixed>}
     */
    public static function construirSnapshot(Client $client, Credit $credit, string $tipo, ?string $placa = null): array
    {
        if (! isset(self::TIPOS[$tipo])) {
            throw new InvalidArgumentException('Tipo de declaración desconocido.');
        }

        $contrato = self::contratoDe($client, $credit);
        if ($contrato === null) {
            throw new InvalidArgumentException("El crédito #{$credit->id} no tiene contrato emitido: primero genera el contrato.");
        }
        $s = $contrato->snapshot;

        $bienes = $s['bienes'] ?? [];
        $bien = $placa === null || $placa === ''
            ? ($bienes[0] ?? null)
            : collect($bienes)->first(fn ($b) => ($b['placa'] ?? null) === $placa);
        if ($bien === null) {
            throw new InvalidArgumentException($placa
                ? "La placa {$placa} no está en el contrato del crédito #{$credit->id}."
                : "El contrato del crédito #{$credit->id} no tiene vehículos.");
        }

        $deudores = $s['deudores'] ?? [];
        $esEmpresa = (bool) ($s['conjunto']['juridica'] ?? ($deudores[0]['esJuridica'] ?? false));
        $esFuturo = $tipo === 'djprop' && (bool) ($bien['esFuturo'] ?? false);

        if ($esEmpresa && $esFuturo) {
            throw new InvalidArgumentException('No hay modelo de declaración de propiedad de bien futuro para empresas.');
        }
        if ($esFuturo && (blank($bien['kardex'] ?? null) || blank($bien['notario'] ?? null))) {
            throw new InvalidArgumentException("Al contrato le falta el kárdex o el notario del bien futuro {$bien['placa']}: corrígelo en el contrato.");
        }

        $variante = $esEmpresa ? 'empresa' : (count($deudores) > 1 ? 'dos' : 'uno');
        if ($esFuturo) {
            $variante = 'futuro-'.$variante;
        }

        $hoy = now();
        // La del contrato llega 'd/m/Y' (wizard) o 'Y-m-d': el mismo lector que usa el contrato.
        $fechaContrato = filled($s['fecha'] ?? null)
            ? GeneradorContrato::fechaDelSnapshot((string) $s['fecha'])
            : Carbon::parse($contrato->created_at);

        return [
            'tipo' => $tipo,
            'variante' => $variante,
            'credito' => ['numero' => (int) $credit->id],
            'contrato' => [
                'id' => (int) $contrato->id,
                'version' => (int) $contrato->version,
                'modelo' => $contrato->modelo,
                'fecha' => $fechaContrato->toDateString(),
                'fecha_texto' => self::fecha($fechaContrato, 'DEL'),
            ],
            // Siempre la fecha del día en que se genera (como el Anexo 1, obs. 1.2 del área).
            'fecha' => $hoy->toDateString(),
            'fecha_de' => self::fecha($hoy, 'DE'),
            'fecha_del' => self::fecha($hoy, 'DEL'),
            'ciudad' => mb_strtoupper((string) config('documentos.ciudad_firma')),
            'declarantes' => $esEmpresa ? [] : array_map(fn (array $p) => [
                'nombre' => (string) ($p['nombre'] ?? ''),
                'documento_tipo' => (string) ($p['documentoTipo'] ?? 'DNI'),
                'documento' => (string) ($p['dni'] ?? ''),
                'domicilio' => (string) ($p['domicilio'] ?? ''),
            ], array_slice($deudores, 0, 2)),
            'empresa' => $esEmpresa ? [
                'razon_social' => (string) ($deudores[0]['nombre'] ?? ''),
                'ruc' => (string) ($deudores[0]['ruc'] ?? ''),
                'domicilio' => (string) ($deudores[0]['domicilio'] ?? ''),
                'partida' => (string) ($deudores[0]['partida'] ?? ''),
                'oficina_registral' => (string) ($deudores[0]['oficinaRegistral'] ?? ''),
                'gerente' => [
                    'nombre' => (string) ($deudores[0]['gerente']['nombre'] ?? ''),
                    'documento_tipo' => (string) ($deudores[0]['gerente']['documentoTipo'] ?? 'DNI'),
                    'documento' => (string) ($deudores[0]['gerente']['dni'] ?? ''),
                ],
            ] : null,
            'bien' => [
                'placa' => (string) $bien['placa'],
                'es_futuro' => $esFuturo,
                'kardex' => $bien['kardex'] ?? null,
                'notario' => $bien['notario'] ?? null,
            ],
            'constantes' => [
                'acreedor' => [
                    'nombre' => (string) config('documentos.acreedor.nombre'),
                    'dni' => (string) config('documentos.acreedor.dni'),
                ],
                'publicidad' => (array) config('documentos.publicidad'),
            ],
        ];
    }

    public static function previsualizar(Client $client, Credit $credit, string $tipo, ?string $placa = null): string
    {
        return RenderDocumento::html(self::construirSnapshot($client, $credit, $tipo, $placa), $tipo, 'previa');
    }

    /**
     * Emite la declaración: versiona por (cliente, crédito, tipo), guarda el PDF en el disco public y
     * crea el DocumentoCliente con el snapshot congelado y la variante del modelo en `modelo`.
     */
    public static function generar(Client $client, Credit $credit, string $tipo, ?string $placa = null): DocumentoCliente
    {
        $snapshot = self::construirSnapshot($client, $credit, $tipo, $placa);

        return DB::transaction(function () use ($client, $credit, $tipo, $snapshot) {
            $version = (int) DocumentoCliente::where('client_id', $client->id)
                ->where('credit_id', $credit->id)
                ->where('tipo', $tipo)
                ->lockForUpdate()
                ->max('version') + 1;

            $contenido = Pdf::loadHTML(RenderDocumento::html($snapshot, $tipo, 'pdf'))->setPaper('a4')->output();

            $path = "documentos/cliente-{$client->id}/{$tipo}-credito-{$credit->id}-v{$version}.pdf";
            Storage::disk('public')->put($path, $contenido);

            $doc = new DocumentoCliente([
                'client_id' => $client->id,
                'credit_id' => $credit->id,
                'tipo' => $tipo,
                'modelo' => $snapshot['variante'],
                'version' => $version,
                'snapshot' => $snapshot,
                'pdf_path' => $path,
                'sha256' => hash('sha256', $contenido),
                'estado' => 'emitido',
                'generado_por' => auth()->id(),
            ]);
            $doc->sinAuditoriaAutomatica(fn () => $doc->save());

            Audit::log(sprintf(
                'Generó la %s v%d del crédito #%d (placa %s, %s)',
                mb_strtolower(self::TIPOS[$tipo]), $version, $credit->id, $snapshot['bien']['placa'],
                self::VARIANTES[$snapshot['variante']] ?? $snapshot['variante']
            ), $doc);

            return $doc;
        });
    }

    /** "07 DE OCTUBRE DE 2026" o "07 DE OCTUBRE DEL 2026", como en los modelos. */
    private static function fecha(Carbon $f, string $enlace): string
    {
        return mb_strtoupper($f->format('d').' DE '.FechaEnLetras::mes($f->month).' '.$enlace.' '.$f->format('Y'));
    }
}
