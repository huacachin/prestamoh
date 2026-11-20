<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Support\FiltrosSeguros;
use App\Support\RutaExpediente;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Seguimiento de expedientes (20/11/2026, Antony: "un flujo de creación de clientes hasta la generación
 * de sus documentos… que les sea amigable"): los préstamos que se están trabajando, cada uno con el avance
 * de su paso a paso (App\Support\RutaExpediente) y el paso que le falta, para ver de un vistazo qué falta
 * y a quién. Cada fila lleva a la ficha del cliente, en los pasos de ese préstamo.
 *
 * Una fila por PRÉSTAMO (tras hablar con el cliente: "verificar si los pasos de ese préstamo se han
 * completado"), más los clientes recién registrados que todavía no tienen ninguno.
 *
 * "Se están trabajando" = préstamos Activos dados en el período elegido, de clientes activos. Así no entra
 * el resto de la cartera, que viene del sistema anterior sin documentos y saldría entera como incompleta.
 * Un préstamo que viene de una refinanciación no cuenta: no abre expediente
 * (RutaExpediente::refinanciacionAbreExpediente). Al buscar a un cliente por nombre, documento o
 * expediente el período no se aplica: salen todos sus préstamos activos.
 */
class Seguimiento extends Component
{
    public const PERIODOS = [7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días', 180 => 'Últimos 6 meses'];

    public const ESTADOS = ['incompletos' => 'Incompletos', 'completos' => 'Completos', 'todos' => 'Todos'];

    private const PERIODO_INICIAL = 30;

    #[Url(as: 'buscar', except: '')]
    public string $buscar = '';

    /** Días hacia atrás; texto porque llega de la URL y del select (se valida en periodo()). */
    #[Url(as: 'dias', except: '30')]
    public string $dias = '30';

    #[Url(as: 'estado', except: 'incompletos')]
    public string $estado = 'incompletos';

    /** Solo los expedientes cuyo paso siguiente es este ('' = cualquiera). */
    #[Url(as: 'paso', except: '')]
    public string $paso = '';

    /** En cada petición, no solo al abrir: el permiso de la ruta no acompaña a las de Livewire. */
    public function boot(): void
    {
        abort_unless(auth()->user()?->can('clientes') ?? false, 403);
    }

    /** Elegir un paso solo tiene sentido entre los incompletos; y los completos no tienen paso que siga. */
    public function updatedPaso(string $paso): void
    {
        if ($paso !== '') {
            $this->estado = 'incompletos';
        }
    }

    public function updatedEstado(string $estado): void
    {
        if ($estado === 'completos') {
            $this->paso = '';
        }
    }

    private function periodo(): int
    {
        return array_key_exists((int) $this->dias, self::PERIODOS) ? (int) $this->dias : self::PERIODO_INICIAL;
    }

    /** Clientes activos al alcance del usuario; con búsqueda, solo los que coinciden. */
    private function clientes(): Builder
    {
        $buscar = trim($this->buscar);

        return Client::query()
            ->where('status', 'active')
            ->titulares()
            // Analista (scope-propio): solo SUS clientes, igual que en el listado de clientes.
            ->when(auth()->user()->can('clientes.scope-propio'), fn (Builder $q) => $q->where('asesor_id', auth()->id()))
            ->when($buscar !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('documento', $buscar)
                ->orWhere('expediente', $buscar)
                ->orWhere(fn (Builder $q) => $q->nombreContiene($buscar))));
    }

    /**
     * Las filas: una por préstamo que se está trabajando y una por cliente que todavía no tiene préstamo,
     * las de movimiento más reciente primero.
     *
     * @return Collection<int, array{client: Client, ruta: array<string, mixed>, credito: ?Credit, fecha: mixed}>
     */
    private function filas(int $limite): Collection
    {
        $conBusqueda = trim($this->buscar) !== '';
        $desde = now()->subDays($this->periodo())->startOfDay();
        $columnas = ['id', 'expediente', 'nombre', 'apellido_pat', 'apellido_mat', 'documento', 'zona', 'asesor_id', 'created_at'];

        $prestamos = Credit::query()
            ->where('situacion', 'Activo')
            ->when(! RutaExpediente::refinanciacionAbreExpediente(), fn (Builder $q) => $q->where('refinanciado', false))
            ->when(! $conBusqueda, fn (Builder $q) => $q->where('fecha_prestamo', '>=', $desde->toDateString()))
            ->whereIn('client_id', $this->clientes()->select('id'))
            ->orderByDesc('fecha_prestamo')->orderByDesc('id')
            ->limit($limite)
            ->get(['id', 'client_id', 'fecha_prestamo', 'importe']);

        // Sin préstamo en la lista: registrados en el período que no tienen ninguno; al buscar, los que
        // coinciden y no tienen un préstamo de los de arriba.
        $sinPrestamo = $this->clientes()
            ->when(
                $conBusqueda,
                fn (Builder $q) => $q->whereNotIn('id', $prestamos->pluck('client_id')->unique()),
                fn (Builder $q) => $q->where('clients.created_at', '>=', $desde)
                    ->whereNotExists(fn ($credito) => $credito->selectRaw('1')->from('credits')
                        ->whereColumn('credits.client_id', 'clients.id')
                        ->where('credits.situacion', '!=', 'Eliminado'))
            )
            ->with('asesor:id,name,username')
            ->orderByDesc('id')
            ->limit($limite)
            ->get($columnas);

        $titulares = Client::with('asesor:id,name,username')->whereIn('id', $prestamos->pluck('client_id')->unique())->get($columnas)->keyBy('id');
        $rutasDePrestamos = RutaExpediente::dePrestamos($prestamos->pluck('id'));
        $rutasDeClientes = RutaExpediente::deClientes($sinPrestamo->pluck('id'));
        // Al buscar, un cliente sin préstamo en la lista puede tener uno que viene de una refinanciación.
        $otros = Credit::whereIn('id', array_filter(array_column($rutasDeClientes, 'credito')))->get(['id', 'client_id', 'fecha_prestamo', 'importe'])->keyBy('id');

        return $prestamos
            ->map(fn (Credit $credito): array => ['client' => $titulares[$credito->client_id], 'ruta' => $rutasDePrestamos[$credito->id], 'credito' => $credito, 'fecha' => $credito->fecha_prestamo])
            ->concat($sinPrestamo->map(function (Client $client) use ($rutasDeClientes, $otros): array {
                $ruta = $rutasDeClientes[$client->id];
                $credito = $ruta['credito'] !== null ? $otros->get($ruta['credito']) : null;

                return ['client' => $client, 'ruta' => $ruta, 'credito' => $credito, 'fecha' => $credito?->fecha_prestamo ?? $client->created_at];
            }))
            ->sortByDesc(fn (array $fila) => $fila['fecha']?->format('Y-m-d') ?? '')
            ->values();
    }

    public function render(): View
    {
        $tope = FiltrosSeguros::tope('seguimiento');
        // Una de más, solo para saber si el filtro trae más de las que se muestran.
        $filas = $this->filas($tope + 1);
        $recortado = $filas->count() > $tope;
        $filas = $filas->take($tope);

        $incompletos = $filas->filter(fn (array $fila) => ! $fila['ruta']['completa']);
        $estado = isset(self::ESTADOS[$this->estado]) ? $this->estado : 'incompletos';
        $paso = isset(RutaExpediente::PASOS[$this->paso]) ? $this->paso : '';

        $visibles = match ($estado) {
            'completos' => $filas->filter(fn (array $fila) => $fila['ruta']['completa']),
            'todos' => $filas,
            default => $incompletos,
        };
        if ($paso !== '') {
            $visibles = $visibles->filter(fn (array $fila) => ($fila['ruta']['siguiente']['clave'] ?? null) === $paso);
        }

        return view('livewire.clients.seguimiento', [
            'filas' => $visibles->values(),
            'estadoActual' => $estado,
            'conteo' => ['incompletos' => $incompletos->count(), 'completos' => $filas->count() - $incompletos->count(), 'todos' => $filas->count()],
            // Cuántos expedientes esperan en cada paso (para el selector).
            'porPaso' => $incompletos->countBy(fn (array $fila) => $fila['ruta']['siguiente']['clave'])->all(),
            'periodoActual' => $this->periodo(),
            'conBusqueda' => trim($this->buscar) !== '',
            'tope' => $recortado ? $tope : null,
        ]);
    }
}
