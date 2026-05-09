<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Credit;
use Livewire\Component;

class CommandPalette extends Component
{
    public string $query = '';

    public function search(string $term): array
    {
        $term = trim($term);
        $user = auth()->user();
        if (!$user) return ['clients' => [], 'credits' => []];

        if ($term === '') {
            return ['clients' => [], 'credits' => []];
        }

        $clients = [];
        if ($user->can('clientes')) {
            $isNum = ctype_digit($term);
            $clients = Client::query()
                ->when($isNum, fn ($q) =>
                    $q->where(function ($w) use ($term) {
                        $w->where('documento', 'like', "%{$term}%")
                          ->orWhere('expediente', 'like', "%{$term}%")
                          ->orWhere('id', $term);
                    })
                )
                ->when(!$isNum, fn ($q) =>
                    $q->where(function ($w) use ($term) {
                        $w->where('nombre', 'like', "%{$term}%")
                          ->orWhere('apellido_pat', 'like', "%{$term}%")
                          ->orWhere('apellido_mat', 'like', "%{$term}%")
                          ->orWhere('documento', 'like', "%{$term}%");
                    })
                )
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'expediente', 'nombre', 'apellido_pat', 'apellido_mat', 'documento'])
                ->map(fn ($c) => [
                    'id'    => $c->id,
                    'title' => trim($c->apellido_pat.' '.$c->apellido_mat.' '.$c->nombre),
                    'sub'   => 'Exp. '.($c->expediente ?? '—').' · DNI '.($c->documento ?? '—'),
                    'url'   => route('clients.show', $c->id),
                    'icon'  => 'ti-user',
                ])->all();
        }

        $credits = [];
        if ($user->can('creditos')) {
            $credits = Credit::query()
                ->with('client:id,nombre,apellido_pat,apellido_mat')
                ->when(ctype_digit($term), fn ($q) => $q->where('id', $term))
                ->when(!ctype_digit($term), fn ($q) =>
                    $q->whereHas('client', function ($w) use ($term) {
                        $w->where('nombre', 'like', "%{$term}%")
                          ->orWhere('apellido_pat', 'like', "%{$term}%")
                          ->orWhere('apellido_mat', 'like', "%{$term}%");
                    })
                )
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'client_id', 'importe', 'cuotas', 'tipo_planilla', 'situacion'])
                ->map(function ($c) {
                    $tipo = match ((int) $c->tipo_planilla) {
                        1 => 'Sem', 3 => 'Mens', 4 => 'Diar', default => '—',
                    };
                    $cli = $c->client ? trim($c->client->apellido_pat.' '.$c->client->apellido_mat.' '.$c->client->nombre) : '—';
                    return [
                        'id'    => $c->id,
                        'title' => '#'.$c->id.' — '.$cli,
                        'sub'   => 'S/ '.number_format((float) $c->importe, 2).' · '.$c->cuotas.' c. · '.$tipo.' · '.$c->situacion,
                        'url'   => route('credits.show', $c->id),
                        'icon'  => 'ti-credit-card',
                    ];
                })->all();
        }

        return ['clients' => $clients, 'credits' => $credits];
    }

    public function render()
    {
        $user = auth()->user();
        $results = $this->search($this->query);

        // Navegación según permisos del usuario
        $nav = collect([
            ['title' => 'Dashboard',          'sub' => 'g d', 'url' => route('dashboard.index'),         'icon' => 'ti-home',          'can' => 'dashboard'],
            ['title' => 'Clientes',           'sub' => 'g c', 'url' => route('clients.index'),           'icon' => 'ti-users',         'can' => 'clientes'],
            ['title' => 'Préstamos',          'sub' => 'g r', 'url' => route('credits.index'),           'icon' => 'ti-cash',          'can' => 'creditos'],
            ['title' => 'Pagos',              'sub' => 'g p', 'url' => route('payments.index'),          'icon' => 'ti-receipt',       'can' => 'pagos'],
            ['title' => 'Ingresos',           'sub' => 'g i', 'url' => route('cash.incomes'),            'icon' => 'ti-arrow-down',    'can' => 'caja.ingresos'],
            ['title' => 'Egresos',            'sub' => 'g e', 'url' => route('cash.expenses'),           'icon' => 'ti-arrow-up',      'can' => 'caja.egresos'],
            ['title' => 'Apertura Caja',      'sub' => 'g a', 'url' => route('cash.opening'),            'icon' => 'ti-cash-banknote', 'can' => 'caja.apertura'],
            ['title' => 'Usuarios',           'sub' => '',    'url' => route('settings.users.index'),    'icon' => 'ti-user-cog',      'can' => 'configuracion.usuarios'],
            ['title' => 'Conceptos Fijos',    'sub' => '',    'url' => route('settings.concepts.index'), 'icon' => 'ti-tag',           'can' => 'configuracion.conceptos'],
        ])->filter(fn ($n) => !$n['can'] || $user?->can($n['can']))->values()->all();

        $actions = collect([
            ['title' => 'Nuevo cliente', 'sub' => 'n c', 'url' => route('clients.create'),          'icon' => 'ti-user-plus',  'can' => 'clientes'],
            ['title' => 'Nuevo préstamo','sub' => 'n r', 'url' => route('credits.create'),          'icon' => 'ti-plus',       'can' => 'creditos'],
            ['title' => 'Nuevo ingreso', 'sub' => '',    'url' => route('cash.incomes.create'),     'icon' => 'ti-circle-plus','can' => 'caja.ingresos'],
            ['title' => 'Nuevo egreso',  'sub' => '',    'url' => route('cash.expenses.create'),    'icon' => 'ti-circle-plus','can' => 'caja.egresos'],
        ])->filter(fn ($n) => !$n['can'] || $user?->can($n['can']))->values()->all();

        // Si hay query, filtrar nav y actions también por título
        if ($this->query !== '') {
            $needle = mb_strtolower($this->query);
            $filterByTitle = fn ($x) => mb_stripos($x['title'], $needle) !== false;
            $nav     = array_values(array_filter($nav, $filterByTitle));
            $actions = array_values(array_filter($actions, $filterByTitle));
        }

        return view('livewire.command-palette', [
            'clients' => $results['clients'],
            'credits' => $results['credits'],
            'nav'     => $nav,
            'actions' => $actions,
        ]);
    }
}
