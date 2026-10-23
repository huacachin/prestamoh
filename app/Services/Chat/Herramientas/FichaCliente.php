<?php

namespace App\Services\Chat\Herramientas;

use App\Models\Client;
use App\Models\ClientUbicacion;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Vehiculo;
use Illuminate\Support\Str;

/**
 * Ficha de un cliente con el resumen de sus créditos (23/10/2026): lo que
 * se necesita para contestar "¿cómo va fulano?" en una sola consulta. Los
 * textos libres (observaciones, referencia) van recortados: son lo único
 * escrito a mano que llega al modelo.
 */
final class FichaCliente implements Herramienta
{
    private const TOPE_CANCELADOS = 10;

    public function nombre(): string
    {
        return 'ficha_cliente';
    }

    public function descripcion(): string
    {
        return 'Ficha completa de un cliente por su id: datos personales y de contacto, dirección, zona/ruta, giro, asesor, sede, vehículos, ubicaciones GPS, copropietarios, cuántos documentos tiene y el RESUMEN DE CADA CRÉDITO ACTIVO (capital, interés, saldo, cuotas pagadas/pendientes/vencidas, días de atraso, mora de hoy, próximo vencimiento, último pago). Con incluir_cancelados=true agrega también los 10 créditos cancelados más recientes (y dice cuántos hay en total).';
    }

    public function esquema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'client_id' => ['type' => 'integer', 'description' => 'Id del cliente (lo da buscar_clientes)'],
                'incluir_cancelados' => ['type' => 'boolean', 'description' => 'true para ver también los créditos cancelados más recientes'],
            ],
            'required' => ['client_id'],
        ];
    }

    public function ejecutar(array $argumentos): array
    {
        $id = (int) ($argumentos['client_id'] ?? 0);
        $cliente = Client::with(['asesor:id,name,username', 'headquarter:id,name', 'vehiculos.copropietarios', 'ubicaciones.user:id,username'])->find($id);
        if ($cliente === null) {
            return ['error' => "No existe el cliente {$id}."];
        }

        $incluirCancelados = (bool) ($argumentos['incluir_cancelados'] ?? false);
        $documentos = DocumentoCliente::where('client_id', $cliente->id)->selectRaw('estado, COUNT(*) AS n')->groupBy('estado')->pluck('n', 'estado');
        $cancelados = Credit::where('client_id', $cliente->id)->where('situacion', '<>', 'Activo')->count();

        return [
            'client_id' => (int) $cliente->id,
            'expediente' => $cliente->expediente,
            'nombre' => $cliente->fullName(),
            'documento' => trim(($cliente->tipo_documento ?? '').' '.($cliente->documento ?? '')),
            'persona_juridica' => $cliente->esPersonaJuridica(),
            'estado' => $cliente->status,
            'es_relacionado' => (bool) $cliente->es_relacionado,
            'fecha_registro' => ResumenCredito::fecha($cliente->fecha_registro),
            'fecha_nacimiento' => ResumenCredito::fecha($cliente->fecha_nacimiento),
            'sexo' => $cliente->sexo,
            'estado_civil' => $cliente->estado_civil,
            'ocupacion' => $cliente->ocupacion,
            'giro' => $cliente->giro,
            'zona' => $cliente->zona,
            'en_ejecucion' => is_string($cliente->zona) && stripos($cliente->zona, 'ejecuc') !== false,
            'celulares' => array_values(array_filter([$cliente->celular1, $cliente->celular2])),
            'correo' => $cliente->email,
            'direccion' => trim(implode(', ', array_filter([$cliente->direccion, $cliente->distrito, $cliente->provincia, $cliente->departamento]))),
            'referencia' => self::recorte($cliente->referencia),
            'contacto_emergencia' => array_values(array_filter([$cliente->contacto_emergencia, $cliente->telefono_contacto])),
            'asesor' => $cliente->asesor?->username ?? $cliente->asesor?->name,
            'sede' => $cliente->headquarter?->name,
            'capital_declarado' => $cliente->capital !== null ? round((float) $cliente->capital, 2) : null,
            'observaciones' => self::recorte($cliente->observaciones),
            'vehiculos' => $cliente->vehiculos->map(fn (Vehiculo $v) => [
                'placa' => $v->placa, 'marca' => $v->marca, 'modelo' => $v->modelo, 'anio' => $v->anio_modelo, 'color' => $v->color, 'estado' => $v->estado,
                'copropietarios' => $v->copropietarios->map(fn (Client $c) => $c->fullName())->values()->all(),
            ])->values()->all(),
            'copropietarios' => $cliente->copropietariosConPlacas()->map(fn ($x) => [
                'nombre' => $x['persona']->fullName(), 'client_id' => (int) $x['persona']->id, 'placas' => $x['placas'],
            ])->values()->all(),
            'ubicaciones' => $cliente->ubicaciones->map(fn (ClientUbicacion $u) => [
                'tipo' => ClientUbicacion::TIPOS[$u->tipo] ?? ucfirst((string) $u->tipo), 'nombre' => $u->nombre,
                'mapa' => $u->url(), 'registrada' => $u->registradoEl()?->format('d/m/Y'), 'por' => $u->user?->username,
            ])->values()->all(),
            'documentos' => ['emitidos' => (int) ($documentos['emitido'] ?? 0), 'anulados' => (int) ($documentos['anulado'] ?? 0)],
            'creditos_activos' => ResumenCredito::deCliente($cliente->id, activos: true)->values()->all(),
            'creditos_cancelados' => $incluirCancelados
                ? [
                    'cantidad' => $cancelados,
                    'mostrados' => min($cancelados, self::TOPE_CANCELADOS),
                    'nota' => $cancelados > self::TOPE_CANCELADOS ? 'Se muestran los '.self::TOPE_CANCELADOS." más recientes de {$cancelados}." : null,
                    'lista' => ResumenCredito::deCliente($cliente->id, activos: false, tope: self::TOPE_CANCELADOS)->values()->all(),
                ]
                : ['cantidad' => $cancelados, 'nota' => 'Pide incluir_cancelados=true para verlos.'],
            'enlaces' => ['ficha' => route('clients.show', $cliente->id), 'editar' => route('clients.edit', $cliente->id)],
        ];
    }

    private static function recorte(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : Str::limit($texto, 300);
    }
}
