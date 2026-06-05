<!DOCTYPE html>
<html lang="es">

<head>
    <!-- All meta and title start-->
@include('layout.head')
<!-- meta and title end-->

    <!-- css start-->
@include('layout.css')
<!-- css end-->

    {{-- El ancho del sidebar (13rem) se controla por la variable SCSS $sidebar-width.
         Aquí solo se ajusta el tamaño de letra del menú (independiente del ancho). --}}
    <style>
        nav.dark-sidebar .main-nav a { font-size: 12px; }
        nav.dark-sidebar .main-nav a i { font-size: 16px; }
        nav.dark-sidebar .menu-title span { font-size: 10px; letter-spacing: .3px; }
    </style>
</head>

<body text="small-text">
<div class="app-wrapper">

    <!-- Menu Navigation start -->
@include('layout.sidebar')
<!-- Menu Navigation end -->


    <div class="app-content">
        <!-- Header Section start -->
    @include('layout.header')
    <!-- Header Section end -->

        <!-- Main Section start -->
        <main>
            {{-- main body content --}}
            @yield('main-content')
        </main>
        <!-- Main Section end -->
    </div>

    <!-- tap on top -->
    <div class="go-top">
      <span class="progress-value">
        <i class="ti ti-arrow-up"></i>
      </span>
    </div>

    <!-- Footer Section start -->
     @include('layout.footer')
    <!-- Footer Section end -->
</div>

@auth
    {{-- Paleta de comandos universal (cmd+k) + atajos de teclado tipo Gmail --}}
    <livewire:command-palette />
@endauth

@stack('datepicker_js')
</body>

<!--customizer-->
<!--div id="customizer"></div-->

<!-- scripts start-->
@include('layout.script')
<!-- scripts end-->
<script src="{{asset('assets/vendor/sweetalert/sweetalert.js')}}"></script>
<script src="{{ asset('assets/js/custom.js') }}"></script>

</html>
