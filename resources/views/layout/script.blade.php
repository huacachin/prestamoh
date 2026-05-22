
<!-- latest jquery-->
<!-- TODO: optimizar esto, datepicker no debería ir con jquery -->
@if (true)
    <script src="{{ asset('assets/js/jquery-3.6.3.min.js') }}"></script>
@endif

<!-- Bootstrap js-->
<script src="{{asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')}}"></script>

<!-- Simple bar js-->
<script src="{{asset('assets/vendor/simplebar/simplebar.js')}}"></script>


<!-- Customizer js-->
<script src="{{asset('assets/js/customizer.js')}}"></script>

<!-- prism js-->
<script src="{{asset('assets/vendor/prism/prism.min.js')}}"></script>

<!-- App js-->
<script src="{{asset('assets/js/script.js')}}"></script>


{{-- Handler global para el botón "ir al final" (componente x-scroll-bottom-btn).
     Se delega en document para que también capture botones agregados después
     (Livewire re-renders, modales, etc.). --}}
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-scroll-sel]');
    if (!btn) return;
    var sel = btn.getAttribute('data-scroll-sel');
    var isCont = btn.getAttribute('data-scroll-cont') === '1';
    var el = document.querySelector(sel);
    if (!el) { console.warn('[scroll-bottom-btn] no se encontró', sel); return; }
    if (isCont) {
        // scrollTop directo: más compatible que scrollTo({behavior:smooth}) en
        // contenedores con overflow horizontal + vertical (algunos browsers no
        // animan correctamente cuando hay ambos).
        el.scrollTop = el.scrollHeight;
    } else {
        el.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
});
</script>

@stack('scripts')
@yield('script')
