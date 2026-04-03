<!-- Animation css -->
<link rel="stylesheet" href="{{ asset('assets/vendor/animation/animate.min.css') }}">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800;900&display=swap"
      rel="stylesheet">

<!-- Weather icon css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/weather/weather-icons.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/weather/weather-icons-wind.css') }}">

<!--font-awesome-css-->
<link rel="stylesheet" href="{{asset('assets/vendor/fontawesome/css/all.css')}}">

<!--Flag Icon css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/flag-icons-master/flag-icon.css') }}">

<!-- Tabler icons-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/tabler-icons/tabler-icons.css') }}">

<!-- Prism css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/prism/prism.min.css') }}">

<!-- Bootstrap css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">

<!-- Simplebar css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/simplebar/simplebar.css') }}">

@yield('css')
<style>
    [x-cloak] { display: none !important; }

    @font-face {
        font-family: 'brush';
        src: url({{asset('assets/fonts/brush.otf')}}) format('opentype');
        font-weight: normal;
        font-style: normal;
    }

    @font-face {
        font-family: 'cooper';
        src: url({{asset('assets/fonts/cooper.otf')}}) format('opentype');
        font-weight: normal;
        font-style: normal;
    }

    .logo-home{
        width: 200px;        /* ajusta a tu tamaño */
        height: 80px;
        background: #FFFFFF; /* el color nuevo del “texto” */
        -webkit-mask: url({{ asset('assets/images/logo/logo1.png') }}) no-repeat center / contain;
        mask: url({{ asset('assets/images/logo/logo1.png') }}) no-repeat center / contain;
    }

    .slogan{
        color: #FFF;
        font-family: 'cooper','sans-serif';
        font-size: 12px;
    }

    .logo-footer{
        font-family: 'brush','sans-serif';
        font-size: 22px;
    }

    .active-menu {
        color: rgba(var(--dark), 1);
        background: rgba(var(--dark), .08);
        border: 1px dashed rgba(var(--dark), .2);
    }

    .title-modules{
        color: red !important;
    }

    .report-blue{
        color: blue !important;
    }

    label{
        font-weight: 600;
    }

    .btn-search{
        background: #2d2f39 !important;
        color: #fff !important;
    }

</style>
@vite(['public/assets/scss/style.scss'])
@livewireStyles
