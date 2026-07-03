<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class Create extends Component
{
    // ── Búsqueda de documento ──────────────────────────────
    public string $docBuscar = '';
    public ?string $docMsg = null;
    public ?string $docMsgType = null; // 'ok' | 'warn' | 'err'

    // ── Campos del formulario (homologado a clientenew.php) ──
    public string $apellido_pat = '';
    public string $apellido_mat = '';
    public string $nombre = '';
    public string $nacionalidad = 'PERUANO';
    public string $sexo = 'M';
    public ?string $fecha_nacimiento = null;
    public string $documento = '';
    public string $tipo_documento = 'DNI'; // interno, no visible
    public ?int $expediente = null;
    public ?string $direccion = null;
    public ?string $giro = null;     // Legacy: input "Giro"
    public $capital = null;          // Capital declarado (línea de crédito = 25%)
    public ?string $zona = null;     // Legacy: input "T. Credito"
    public ?string $celular1 = null; // Legacy: "Celular / Whatsapp"
    public ?string $celular2 = null;
    public ?int $asesor_id = null;

    public $asesores;

    public function mount(): void
    {
        $this->asesores = User::permission('creditos.ser-asesor-responsable')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $correl = (int) (DB::table('correlativos')->where('tipo', 'Cliente')->value('correl') ?? 0);
        $this->expediente = $correl + 1;
    }

    /**
     * Consulta DNI o RUC contra api.migo.pe — desambigua por longitud.
     */
    public function consultarDocumento(): void
    {
        $this->docMsg = null;
        $this->docMsgType = null;

        $doc = preg_replace('/\D+/', '', (string) $this->docBuscar);
        $len = strlen($doc);

        if ($len !== 8 && $len !== 11) {
            $this->docMsgType = 'err';
            $this->docMsg = 'Ingrese DNI (8 dígitos) o RUC (11 dígitos).';
            return;
        }

        // Bloqueo de duplicados
        $exists = Client::where('documento', $doc)->first(['id', 'expediente', 'nombre', 'apellido_pat', 'apellido_mat']);
        if ($exists) {
            $this->docMsgType = 'warn';
            $this->docMsg = "Documento ya registrado: #{$exists->id} · {$exists->fullName()} (exp. {$exists->expediente}).";
            return;
        }

        $token = config('services.migo.token');
        $base  = rtrim((string) config('services.migo.base'), '/');

        if (!$token) {
            $this->docMsgType = 'err';
            $this->docMsg = 'Falta token MIGO_PE_TOKEN en .env';
            return;
        }

        try {
            if ($len === 8) {
                $this->buscarDni($doc, $base, $token);
            } else {
                $this->buscarRuc($doc, $base, $token);
            }
        } catch (\Throwable $e) {
            $this->docMsgType = 'err';
            $this->docMsg = 'Error consultando: ' . $e->getMessage();
        }
    }

    private function buscarDni(string $dni, string $base, string $token): void
    {
        $resp = Http::timeout(8)
            ->acceptJson()
            ->asJson()
            ->post("$base/dni", ['token' => $token, 'dni' => $dni]);

        if (!$resp->ok()) {
            $this->docMsgType = 'err';
            $this->docMsg = "API respondió HTTP {$resp->status()}.";
            return;
        }

        $j = $resp->json();
        if (empty($j) || (isset($j['success']) && $j['success'] === false)) {
            $this->docMsgType = 'err';
            $this->docMsg = $j['message'] ?? 'No se encontraron datos para ese DNI.';
            return;
        }

        // migo.pe devuelve un único campo "nombre" con formato "APELLIDOPAT APELLIDOMAT NOMBRES".
        // Compatibilidad: si la API algún día devolviera campos separados, los respetamos.
        $pat = $j['apellido_paterno'] ?? $j['apellidoPaterno'] ?? null;
        $mat = $j['apellido_materno'] ?? $j['apellidoMaterno'] ?? null;
        $nom = $j['nombres'] ?? null;
        $full = trim((string) ($j['nombre'] ?? ''));

        if ($pat === null && $mat === null && $nom === null && $full !== '') {
            // Split: 1ª palabra = pat, 2ª = mat, resto = nombres
            $partes = preg_split('/\s+/', $full, 3);
            $pat = $partes[0] ?? '';
            $mat = $partes[1] ?? '';
            $nom = $partes[2] ?? '';
        }

        $this->apellido_pat = mb_convert_case(trim((string) $pat), MB_CASE_TITLE, 'UTF-8');
        $this->apellido_mat = mb_convert_case(trim((string) $mat), MB_CASE_TITLE, 'UTF-8');
        $this->nombre       = mb_convert_case(trim((string) $nom), MB_CASE_TITLE, 'UTF-8');
        $this->documento    = $dni;
        $this->tipo_documento = 'DNI';

        $this->docMsgType = 'ok';
        $this->docMsg = 'Datos cargados desde RENIEC. Verifique apellidos y nombres.';
    }

    private function buscarRuc(string $ruc, string $base, string $token): void
    {
        $resp = Http::timeout(8)
            ->acceptJson()
            ->asJson()
            ->post("$base/ruc", ['token' => $token, 'ruc' => $ruc]);

        if (!$resp->ok()) {
            $this->docMsgType = 'err';
            $this->docMsg = "API respondió HTTP {$resp->status()}.";
            return;
        }

        $j = $resp->json();
        if (empty($j) || (isset($j['success']) && $j['success'] === false)) {
            $this->docMsgType = 'err';
            $this->docMsg = $j['message'] ?? 'No se encontraron datos para ese RUC.';
            return;
        }

        $razon = trim((string) ($j['nombre_o_razon_social'] ?? ''));
        $direc = trim((string) ($j['direccion_simple'] ?? $j['direccion'] ?? ''));

        // Para cualquier RUC, la razón social entera va al campo Nombres.
        // Los apellidos quedan vacíos (no aplican).
        $this->apellido_pat   = '';
        $this->apellido_mat   = '';
        $this->nombre         = $razon;
        $this->documento      = $ruc;
        $this->tipo_documento = 'RUC';

        if ($direc !== '') {
            $this->direccion = mb_convert_case($direc, MB_CASE_TITLE, 'UTF-8');
        }

        $this->docMsgType = 'ok';
        $this->docMsg = 'Razón social cargada en "Nombres". Apellidos no aplican para RUC.';
    }

    public function clean(): void
    {
        $this->reset([
            'docBuscar', 'docMsg', 'docMsgType',
            'apellido_pat', 'apellido_mat', 'nombre', 'sexo', 'fecha_nacimiento',
            'documento', 'tipo_documento',
            'direccion', 'giro', 'zona', 'celular1', 'celular2',
        ]);
        $this->sexo = 'M';
        $this->tipo_documento = 'DNI';
        $this->resetErrorBag();
    }

    protected function rules(): array
    {
        $isRuc = $this->tipo_documento === 'RUC';

        return [
            'nombre'           => 'required|string|max:200',
            'apellido_pat'     => $isRuc ? 'nullable|string|max:100' : 'required|string|max:100',
            'apellido_mat'     => 'nullable|string|max:100',
            'sexo'             => 'required|in:M,F',
            'fecha_nacimiento' => 'nullable|date',
            'documento'        => 'required|string|min:8|max:11|unique:clients,documento',
            'expediente'       => 'required|integer|min:1',
            'direccion'        => 'nullable|string|max:255',
            'giro'             => 'nullable|string|max:100',
            'capital'          => 'nullable|numeric|min:0',
            'zona'             => 'nullable|string|max:100',
            'celular1'         => 'nullable|string|max:20',
            'celular2'         => 'nullable|string|max:20',
            'asesor_id'        => 'nullable|exists:users,id',
        ];
    }

    public function save()
    {
        $this->validate();

        // Re-confirmar duplicado al guardar (race condition)
        if (Client::where('documento', $this->documento)->exists()) {
            $this->addError('documento', 'El documento ya está registrado.');
            return;
        }

        $expediente = (int) $this->expediente;

        DB::transaction(function () use ($expediente) {
            $clientId = DB::table('clients')->insertGetId([
                'expediente'       => (string) $expediente,
                'nombre'           => $this->nombre,
                'apellido_pat'     => $this->apellido_pat,
                'apellido_mat'     => $this->apellido_mat,
                'tipo_documento'   => $this->tipo_documento,
                'documento'        => $this->documento,
                'fecha_nacimiento' => $this->fecha_nacimiento,
                'sexo'             => $this->sexo,
                'email'            => 'g@huacachin.com',
                'direccion'        => $this->direccion,
                'giro'             => $this->giro,
                'capital'          => $this->capital !== null && $this->capital !== '' ? $this->capital : null,
                'zona'             => $this->zona,
                'celular1'         => $this->celular1,
                'celular2'         => $this->celular2,
                'asesor_id'        => $this->asesor_id,
                'headquarter_id'   => auth()->user()->headquarter_id ?? 1,
                'usuario'          => auth()->user()->username ?? auth()->user()->name ?? null,
                'fecha_registro'   => now()->toDateString(),
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Avanzar correlativo al expediente que terminó usando el usuario
            DB::table('correlativos')
                ->where('tipo', 'Cliente')
                ->update(['correl' => $expediente, 'updated_at' => now()]);

            session()->flash('client_success', "Cliente registrado · expediente {$expediente}.");
            $this->newClientId = $clientId;
        });

        $createdClient = Client::find($this->newClientId);
        \App\Support\Audit::log('Creó el cliente '.($createdClient?->fullName() ?? $this->documento), $createdClient);

        return redirect()->route('clients.gallery', $this->newClientId);
    }

    public ?int $newClientId = null;

    public function render()
    {
        return view('livewire.clients.create');
    }
}
