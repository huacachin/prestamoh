{{--
    Aviso de tope de filas (23/10/2026): el listado no pinta la tabla cuando el
    filtro devuelve más filas de las que el navegador de la oficina aguanta.
    Espera $topeFilas = ['total' => N, 'tope' => M] o null (App\Support\FiltrosSeguros::aviso).
    El Excel sí trae todo: los exports marcan $sinTope en el componente.
--}}
@if(! empty($topeFilas))
    <div class="alert alert-warning py-2 px-3 my-2 small" role="alert">
        <i class="ti ti-alert-triangle"></i>
        <strong>{{ number_format($topeFilas['total']) }} registros</strong> coinciden con el filtro, más de los
        {{ number_format($topeFilas['tope']) }} que se muestran en pantalla.
        Acota las fechas o afina la búsqueda para verlos aquí; el Excel sí trae el resultado completo.
    </div>
@endif
