{{-- Firmas de las declaraciones juradas, como en los .docx del área (30/10/2026).
     PROPIEDAD: por cada firmante, su firma y al lado la huella digital; la empresa firma por su gerente.
     PUBLICIDAD: sin huella y en negrita; la empresa firma con su razón social y RUC.
     En los .docx de dos declarantes el bloque de firmas aparecía repetido (cuadros de texto de Word):
     aquí va UNO por declarante. Usa las variables que arma declaracion.blade.php. --}}
@php
    $doc = fn (array $p) => ($p['documento_tipo'] ?? 'DNI').' N.° ';
@endphp
@if ($propiedad)
    @php
        $firmantes = $d['variante'] === 'empresa'
            ? [['FIRMA DEL/LA GERENTE GENERAL', $g]]
            : (count($d['declarantes']) > 1
                ? [['FIRMA DECLARANTE 1', $p1], ['FIRMA DECLARANTE 2', $p2]]
                : [['FIRMA DEL(DE LA) DECLARANTE', $p1]]);
    @endphp
    {{-- Con dos declarantes van lado a lado (firma y huella de cada uno): apiladas no cabían en la hoja. --}}
    @php $ancho = count($firmantes) > 1 ? [32, 18] : [60, 40]; @endphp
    <table class="dj-firmas">
        <tr>
            @foreach ($firmantes as [$rotulo, $firmante])
                <td style="width:{{ $ancho[0] }}%;">
                    <div class="linea">{{ $rotulo }}</div>
                    {{ $dato($firmante['nombre']) }}<br>
                    {{ $doc($firmante) }}{{ $dato($firmante['documento']) }}
                </td>
                <td style="width:{{ $ancho[1] }}%;">
                    <div class="linea">HUELLA DIGITAL</div>
                </td>
            @endforeach
        </tr>
    </table>
@else
    <table class="dj-firmas negrita">
        <tr>
            @if ($d['variante'] === 'empresa')
                <td style="width:25%;"></td>
                <td style="width:50%;">
                    <div class="linea">FIRMA DE TITULAR</div>
                    {{ $dato($e['razon_social']) }}<br>
                    RUC N.º {{ $dato($e['ruc']) }}
                </td>
                <td style="width:25%;"></td>
            @elseif (count($d['declarantes']) > 1)
                @foreach ([$p1, $p2] as $firmante)
                    <td style="width:50%;">
                        <div class="linea">FIRMA DE TITULAR</div>
                        {{ $dato($firmante['nombre']) }}<br>
                        {{ str_replace('N.°', 'N.º', $doc($firmante)) }}{{ $dato($firmante['documento']) }}
                    </td>
                @endforeach
            @else
                <td style="width:25%;"></td>
                <td style="width:50%;">
                    <div class="linea">FIRMA DE LA PERSONA TITULAR</div>
                    {{ $dato($p1['nombre']) }}<br>
                    {{ str_replace('N.°', 'N.º', $doc($p1)) }}{{ $dato($p1['documento']) }}
                </td>
                <td style="width:25%;"></td>
            @endif
        </tr>
    </table>
@endif
