<!DOCTYPE html>
<html lang="es">

<head>
    <!-- All meta and title start-->
@include('layout.head')
<!-- meta and title end-->

    <!-- css start-->
@include('layout.css')
<!-- css end-->
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

    <!-- Quick access buttons -->
    @include('partials.quick-access')

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
