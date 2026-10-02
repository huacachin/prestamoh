<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reporte de GPS de un vehículo en garantía (26/09/2026): uno o varios puntos
 * (donde se queda, intermedio, punto de llegada…), cada uno con su horario de
 * estadía, más fotos. texto() arma el mensaje con el formato exacto que el
 * área manda por WhatsApp: un punto sin etiqueta ni horario sale en el formato
 * corto; con etiqueta u horario, en el formato largo.
 */
class VehiculoGpsReporte extends Model
{
    use Auditable;

    public const AUDIT_MODULO = 'Reporte GPS';

    /** Etiquetas sugeridas para los puntos del reporte avanzado. */
    public const ETIQUETAS = ['Donde se queda', 'Intermedio', 'Punto de llegada'];

    /**
     * Título por defecto de cada punto (02/10, Antony): editable en el
     * formulario ("Ubicación de la moto", "Ubicación del cliente"…). Los
     * reportes guardados sin 'titulo' siguen saliendo con este.
     */
    public const TITULO_PUNTO = 'Ubicación de vehículo';

    protected $fillable = [
        'client_id', 'vehiculo_id', 'placa', 'fecha',
        'inicio_desde', 'inicio_hasta', 'fin_desde', 'fin_hasta',
        'domicilio_direccion', 'domicilio_link', 'puntos', 'registrado_por',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'puntos' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(VehiculoGpsReporteFoto::class, 'reporte_id')->orderBy('id');
    }

    /**
     * URL para abrir en Google Maps: el enlace pegado (con https:// si le
     * falta) o, si no hay enlace, la búsqueda de la dirección escrita.
     */
    public static function maps(?string $link, ?string $direccion = null): ?string
    {
        $link = trim((string) $link);
        if ($link !== '') {
            return preg_match('#^https?://#i', $link) ? $link : 'https://'.$link;
        }
        $direccion = trim((string) $direccion);

        return $direccion !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($direccion) : null;
    }

    /** "06:30" → "6:30 a.m."; "23:00" → "11:00 p.m."; vacío → "". */
    public static function hora12(?string $hhmm): string
    {
        if (! $hhmm || ! preg_match('/^(\d{1,2}):(\d{2})/', $hhmm, $m)) {
            return '';
        }
        $h = (int) $m[1];
        $sufijo = $h >= 12 ? 'p.m.' : 'a.m.';
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return "{$h12}:{$m[2]} {$sufijo}";
    }

    /** "6:30 a.m. – 7:40 a.m." (o solo uno de los dos si falta el otro). */
    public static function rango(?string $desde, ?string $hasta): string
    {
        return implode(' – ', array_filter([self::hora12($desde), self::hora12($hasta)]));
    }

    /** El mensaje tal como lo manda el área (mismos emojis, mismo orden). */
    /**
     * Fotos del reporte como items del visor tipo galería (url + pie de foto).
     *
     * @return list<array{url: string, name: string}>
     */
    public function galeria(): array
    {
        $pie = $this->placa.' · '.$this->fecha?->format('d/m/Y H:i');

        return $this->fotos->map(fn (VehiculoGpsReporteFoto $f) => [
            'url' => $f->url(),
            'name' => $pie.' · '.$f->original_name.($f->client_attachment_id ? ' (de Adjuntos)' : ''),
        ])->values()->all();
    }

    /**
     * Texto del reporte, listo para pegar en WhatsApp. Las negritas van con
     * asteriscos (*…*), que es como WhatsApp las entiende (02/10, Antony,
     * según la captura del área): título, nombre del cliente, placa y los
     * rótulos; direcciones, enlaces, horas y expediente en normal.
     */
    public function texto(): string
    {
        $c = $this->client;
        $l = [
            '📍 *REPORTE DE GPS – VEHÍCULO EN GARANTÍA*',
            '👤 Cliente: *'.($c?->fullName() ?? '').'*',
            '🚗 Placa: *'.$this->placa.'*',
            '📄 Expediente: '.($c?->expediente ?? ''),
            '',
        ];

        // Las líneas sin dato no salen (26/09): si no se llenó el horario de ruta
        // o el enlace, el mensaje no lleva el rótulo vacío.
        $inicio = self::rango($this->inicio_desde, $this->inicio_hasta);
        $fin = self::rango($this->fin_desde, $this->fin_hasta);
        if ($inicio !== '' || $fin !== '') {
            if ($inicio !== '') {
                $l[] = '⏰ *Inicio de ruta:* '.$inicio;
            }
            if ($fin !== '') {
                $l[] = '⏰ *Fin de ruta:* '.$fin;
            }
            $l[] = '';
        }

        foreach ($this->puntos ?? [] as $p) {
            // Título editable por punto (02/10); el enlace lo repite en minúscula inicial.
            $titulo = trim((string) ($p['titulo'] ?? '')) ?: self::TITULO_PUNTO;
            $tituloLink = mb_strtolower(mb_substr($titulo, 0, 1)).mb_substr($titulo, 1);
            $etiqueta = trim((string) ($p['etiqueta'] ?? ''));
            $estadia = self::rango($p['estadia_desde'] ?? null, $p['estadia_hasta'] ?? null);
            $link = trim((string) ($p['link'] ?? ''));
            if ($etiqueta !== '' || $estadia !== '') {
                $l[] = '📍 *'.$titulo.($etiqueta !== '' ? " ({$etiqueta})" : '').':*';
                if ($estadia !== '') {
                    $l[] = '⏱️ *Horario aproximado de estadía:* '.$estadia;
                }
                $l[] = '→ '.trim((string) ($p['direccion'] ?? ''));
            } else {
                $l[] = '📍 *'.$titulo.':*';
                $l[] = trim((string) ($p['direccion'] ?? ''));
            }
            if ($link !== '') {
                $l[] = '🔗 *Link de '.$tituloLink.' en Google Maps:*';
                $l[] = $link;
            }
            $l[] = '';
        }

        $l[] = '📍 *Ubicación de domicilio:*';
        $l[] = trim((string) $this->domicilio_direccion);
        $domLink = trim((string) $this->domicilio_link);
        if ($domLink !== '') {
            $l[] = '🔗 *Link de ubicación de domicilio en Google Maps:*';
            $l[] = $domLink;
        }

        return implode("\n", $l);
    }

    /**
     * Fotos del reporte para copiarlas, descargarlas o compartirlas desde el
     * navegador (02/10): url, mime y un nombre de archivo que diga de qué
     * reporte vienen ("ALP837_20261002_1030_1.jpg"), que es como quedan en
     * Descargas o en el teléfono.
     *
     * @return list<array{id: int, url: string, mime: string, nombre: string}>
     */
    public function descargas(): array
    {
        $base = preg_replace('/[^A-Za-z0-9]+/', '', (string) $this->placa).'_'.$this->fecha?->format('Ymd_Hi');

        return $this->fotos->values()->map(fn (VehiculoGpsReporteFoto $f, int $i) => [
            'id' => (int) $f->id,
            'url' => $f->url(),
            'mime' => $f->mime ?: 'image/jpeg',
            'nombre' => $base.'_'.($i + 1).'.'.(strtolower(pathinfo($f->path, PATHINFO_EXTENSION)) ?: 'jpg'),
        ])->all();
    }

    /**
     * El mismo texto para mostrarlo en pantalla como se verá en WhatsApp:
     * escapado y con las negritas *…* convertidas en <b>. Lo que se copia
     * sigue siendo texto() (con los asteriscos).
     */
    public function textoHtml(): string
    {
        return preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', e($this->texto()));
    }

    /** Texto corto para la descripción de auditoría. */
    public function auditNombre(): ?string
    {
        return trim($this->placa.' '.$this->fecha?->format('d/m/Y H:i'));
    }
}
