<?php

namespace App\Livewire\Legal;

use App\Models\LegalSetting;
use App\Support\Audit;
use Livewire\Component;

/**
 * Configuración del Área Legal: edita las constantes de negocio de la tabla
 * legal_settings (acreedor, apoderada, usuaria SIGM, abogada, representantes
 * de ejecución, cuentas bancarias, etc.) que consumen los contratos y avisos
 * SIGM. El caché del helper App\Support\Legal\LegalSettings se invalida solo
 * al guardar (hook saved() del modelo LegalSetting).
 */
class Settings extends Component
{
    /** Valores decodificados por clave: string, mapa {sub => valor} o lista de mapas. */
    public array $valores = [];

    /** Etiquetas legibles por clave (columna etiqueta de legal_settings). */
    public array $etiquetas = [];

    /**
     * Sub-claves de cada clave tipo lista, memorizadas al cargar desde la
     * primera fila existente (permite re-agregar filas aunque la lista quede
     * vacía durante la edición).
     */
    public array $columnasListas = [];

    public function mount(): void
    {
        if (! auth()->user()?->can('legal.configuracion')) {
            abort(403);
        }

        $this->cargar();
    }

    /** Carga todas las filas de legal_settings a las props del componente. */
    private function cargar(): void
    {
        $this->valores = [];
        $this->etiquetas = [];
        $this->columnasListas = [];

        foreach (LegalSetting::orderBy('id')->get() as $fila) {
            $this->valores[$fila->clave] = $fila->valor;
            $this->etiquetas[$fila->clave] = (string) $fila->etiqueta;

            if (is_array($fila->valor) && array_is_list($fila->valor)) {
                $this->columnasListas[$fila->clave] = array_keys($fila->valor[0] ?? []);
            }
        }
    }

    /** Agrega una fila vacía a una clave tipo lista (sub-claves inferidas de la primera fila). */
    public function agregarFila(string $clave): void
    {
        $valor = $this->valores[$clave] ?? null;
        if (! is_array($valor) || ! array_is_list($valor)) {
            return;
        }

        // Infiere las sub-claves de la primera fila existente; si la lista
        // quedó vacía, usa las columnas memorizadas al cargar.
        $subclaves = array_keys($valor[0] ?? []) ?: ($this->columnasListas[$clave] ?? []);
        if (empty($subclaves)) {
            $this->dispatch('errorAlert', ['message' => "No hay una fila de referencia en «{$clave}» para inferir sus campos."]);

            return;
        }

        $this->valores[$clave][] = array_fill_keys($subclaves, '');
    }

    /** Quita la fila i de una clave tipo lista y reindexa. */
    public function quitarFila(string $clave, int $i): void
    {
        $valor = $this->valores[$clave] ?? null;
        if (! is_array($valor) || ! array_is_list($valor) || ! array_key_exists($i, $valor)) {
            return;
        }

        unset($valor[$i]);
        $this->valores[$clave] = array_values($valor);
        // Los índices cambiaron: los errores previos ya no apuntan a la fila correcta
        $this->resetErrorBag();
    }

    /** Valida y persiste TODAS las claves modificadas de una sola vez. */
    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.configuracion')) {
            abort(403);
        }

        // Normaliza: recorta espacios y descarta filas de lista totalmente vacías
        foreach ($this->valores as $clave => $valor) {
            $this->valores[$clave] = $this->normalizar($valor);
        }

        if (! $this->validarDnis()) {
            $this->dispatch('errorAlert', ['message' => 'Revisa los DNI marcados: deben tener exactamente 8 dígitos.']);

            return;
        }

        try {
            $filas = LegalSetting::whereIn('clave', array_keys($this->valores))->get()->keyBy('clave');
            $usuario = auth()->user()->username ?? auth()->user()->name;
            $modificadas = [];

            foreach ($this->valores as $clave => $valor) {
                $fila = $filas->get($clave);
                if (! $fila || $fila->valor === $valor) {
                    continue;
                }

                // update() sobre la INSTANCIA del modelo (no query builder) para
                // que dispare saved() y se invalide el caché de LegalSettings
                $fila->update(['valor' => $valor, 'updated_by' => $usuario]);
                $modificadas[] = $clave;
            }

            if (empty($modificadas)) {
                $this->dispatch('successAlert', ['message' => 'No había cambios que guardar.']);

                return;
            }

            Audit::log('Editó la configuración del Área Legal', null, ['claves' => $modificadas]);

            $this->cargar();
            $this->dispatch('successAlert', ['message' => 'Configuración legal guardada correctamente.']);
        } catch (\Throwable $e) {
            $this->dispatch('errorAlert', ['message' => 'Error al guardar: '.$e->getMessage()]);
        }
    }

    /**
     * Valida que todo sub-campo llamado "dni" (en mapas y en filas de listas)
     * tenga exactamente 8 dígitos. Marca el error sobre el input exacto.
     */
    private function validarDnis(): bool
    {
        $this->resetErrorBag();
        $mensaje = 'El DNI debe tener exactamente 8 dígitos.';
        $ok = true;

        foreach ($this->valores as $clave => $valor) {
            if (! is_array($valor)) {
                continue;
            }

            if (array_is_list($valor)) {
                foreach ($valor as $i => $fila) {
                    if (is_array($fila) && array_key_exists('dni', $fila)
                        && ! preg_match('/^\d{8}$/', (string) $fila['dni'])) {
                        $this->addError("valores.{$clave}.{$i}.dni", $mensaje);
                        $ok = false;
                    }
                }
            } elseif (array_key_exists('dni', $valor)
                && ! preg_match('/^\d{8}$/', (string) $valor['dni'])) {
                $this->addError("valores.{$clave}.dni", $mensaje);
                $ok = false;
            }
        }

        return $ok;
    }

    /** Recorta espacios en todos los niveles y descarta filas de lista totalmente vacías. */
    private function normalizar(mixed $valor): mixed
    {
        if (is_string($valor)) {
            return trim($valor);
        }
        if (! is_array($valor)) {
            return $valor;
        }

        if (array_is_list($valor)) {
            $filas = [];
            foreach ($valor as $fila) {
                if (is_array($fila)) {
                    $fila = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $fila);
                    $vacia = count(array_filter($fila, fn ($v) => $v !== '' && $v !== null)) === 0;
                    if ($vacia) {
                        continue;
                    }
                }
                $filas[] = $fila;
            }

            return $filas;
        }

        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $valor);
    }

    public function render()
    {
        // Tipo de editor por clave: texto (string), mapa (objeto) o lista (tabla)
        $tipos = [];
        foreach ($this->valores as $clave => $valor) {
            $tipos[$clave] = ! is_array($valor)
                ? 'texto'
                : (array_is_list($valor) ? 'lista' : 'mapa');
        }

        return view('livewire.legal.settings', compact('tipos'));
    }
}
