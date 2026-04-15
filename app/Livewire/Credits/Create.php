<?php

namespace App\Livewire\Credits;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use Carbon\Carbon;
use Livewire\Component;

class Create extends Component
{
    public ?int $client_id = null;
    public ?string $clientName = null;
    public string $searchClient = '';

    public string $fecha_prestamo = '';
    public float $importe = 0;
    public int $cuotas = 1;
    public int $tipo_planilla = 3; // 1=semanal, 3=mensual, 4=diario
    public float $interes = 0;
    public string $moneda = 'PEN';
    public ?string $documento = null;
    public ?string $glosa = null;

    // Preview cronograma
    public array $preview = [];

    public $clients = [];

    protected $rules = [
        'client_id'      => 'required|exists:clients,id',
        'fecha_prestamo' => 'required|date',
        'importe'        => 'required|numeric|min:1',
        'cuotas'         => 'required|integer|min:1|max:60',
        'tipo_planilla'  => 'required|in:1,3,4',
        'interes'        => 'required|numeric|min:0',
        'moneda'         => 'required|in:PEN,USD',
    ];

    public function mount(?int $clientId = null)
    {
        $this->fecha_prestamo = now()->format('Y-m-d');

        if ($clientId) {
            $client = Client::find($clientId);
            if ($client) {
                $this->client_id = $client->id;
                $this->clientName = $client->fullName() . ' - ' . $client->documento;
            }
        }
    }

    public function updatedSearchClient()
    {
        if (strlen($this->searchClient) >= 2) {
            $this->clients = Client::where('status', 'active')
                ->where(function ($q) {
                    $q->where('nombre', 'like', "%{$this->searchClient}%")
                      ->orWhere('apellido_pat', 'like', "%{$this->searchClient}%")
                      ->orWhere('documento', 'like', "%{$this->searchClient}%");
                })
                ->limit(10)
                ->get(['id', 'nombre', 'apellido_pat', 'apellido_mat', 'documento']);
        } else {
            $this->clients = [];
        }
    }

    public function selectClient(int $id)
    {
        $client = Client::find($id);
        if ($client) {
            $this->client_id = $client->id;
            $this->clientName = $client->fullName() . ' - ' . $client->documento;
            $this->searchClient = '';
            $this->clients = [];
        }
    }

    public function clearClient()
    {
        $this->client_id = null;
        $this->clientName = null;
    }

    /**
     * Fórmula del legacy (simulacro.php):
     * Cuota capital = Capital / NroCuotas
     * Cuota interés = (Capital × Interés%) / 100
     * Mora diaria   = (Cuota capital + Cuota interés) × Interés% / 100 / 30 × 2
     */
    public function generatePreview()
    {
        $this->preview = [];

        if ($this->importe <= 0 || $this->cuotas <= 0) return;

        $capital = $this->importe;
        $nCuotas = $this->cuotas;
        $interesRate = $this->interes;
        $fechaBase = Carbon::parse($this->fecha_prestamo);

        $cuotaCapital = round($capital / $nCuotas, 2);
        $cuotaInteres = round(($capital * $interesRate) / 100, 2);

        for ($i = 1; $i <= $nCuotas; $i++) {
            $fechaVenc = match ((int) $this->tipo_planilla) {
                1 => (clone $fechaBase)->addWeeks($i),
                4 => (clone $fechaBase)->addDays($i),
                default => (clone $fechaBase)->addMonths($i),
            };

            $this->preview[] = [
                'num'     => $i,
                'fecha'   => $fechaVenc->format('d/m/Y'),
                'capital' => $cuotaCapital,
                'interes' => $cuotaInteres,
                'total'   => $cuotaCapital + $cuotaInteres,
            ];
        }
    }

    public function save()
    {
        $this->validate();

        $capital = $this->importe;
        $nCuotas = $this->cuotas;
        $interesRate = $this->interes;
        $fechaBase = Carbon::parse($this->fecha_prestamo);

        $cuotaCapital = round($capital / $nCuotas, 2);
        $cuotaInteres = round(($capital * $interesRate) / 100, 2);
        $interesTotal = round($cuotaInteres * $nCuotas, 2);

        // Fecha vencimiento = última cuota
        $fechaVencimiento = match ((int) $this->tipo_planilla) {
            1 => (clone $fechaBase)->addWeeks($nCuotas),
            4 => (clone $fechaBase)->addDays($nCuotas),
            default => (clone $fechaBase)->addMonths($nCuotas),
        };

        // Mora diaria del legacy
        $mora = round(($cuotaCapital + $cuotaInteres) * $interesRate / 100 / 30 * 2, 2);

        $credit = Credit::create([
            'client_id'        => $this->client_id,
            'fecha_prestamo'   => $this->fecha_prestamo,
            'importe'          => $capital,
            'cuotas'           => $nCuotas,
            'tipo_planilla'    => $this->tipo_planilla,
            'interes'          => $interesRate,
            'interes_total'    => $interesTotal,
            'mora'             => $mora,
            'moneda'           => $this->moneda,
            'documento'        => $this->documento,
            'glosa'            => $this->glosa,
            'situacion'        => 'Activo',
            'estado'           => 'Vigente',
            'refinanciado'     => false,
            'fecha_vencimiento' => $fechaVencimiento,
            'user_id'          => auth()->id(),
            'headquarter_id'   => auth()->user()->headquarter_id,
        ]);

        // Generar cuotas
        for ($i = 1; $i <= $nCuotas; $i++) {
            $fechaVenc = match ((int) $this->tipo_planilla) {
                1 => (clone $fechaBase)->addWeeks($i),
                4 => (clone $fechaBase)->addDays($i),
                default => (clone $fechaBase)->addMonths($i),
            };

            CreditInstallment::create([
                'credit_id'        => $credit->id,
                'num_cuota'        => $i,
                'fecha_vencimiento' => $fechaVenc,
                'importe_cuota'    => $cuotaCapital,
                'importe_interes'  => $cuotaInteres,
                'importe_aplicado' => 0,
                'interes_aplicado' => 0,
                'importe_mora'     => 0,
                'pagado'           => false,
            ]);
        }

        session()->flash('credit_success', 'Crédito creado con ' . $nCuotas . ' cuotas.');
        return redirect()->route('credits.show', $credit->id);
    }

    public function render()
    {
        return view('livewire.credits.create');
    }
}
