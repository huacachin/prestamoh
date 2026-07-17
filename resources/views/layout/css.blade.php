<!-- Animation css -->
<link rel="stylesheet" href="{{ asset('assets/vendor/animation/animate.min.css') }}">

<!-- jQuery UI datepicker css -->
<link rel="stylesheet" href="{{ asset('assets/vendor/jquery-ui/jquery-ui.css') }}">
<style>
    /* Icono de calendario dentro del input (réplica del legacy ideasweb.css).
       Se usa background-image (no el shorthand background) + !important para que
       ningún script/tema posterior pueda pisarlo al re-renderizar. */
    input.dates,
    input.dates2,
    input.dates3 {
        background-image: url("{{ asset('assets/vendor/jquery-ui/calen.png') }}") !important;
        background-repeat: no-repeat !important;
        background-position: right 8px center !important;
        background-size: 16px 16px !important;
        padding-right: 30px !important;
        cursor: pointer;
    }
</style>

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

    /* ── Paginación Livewire homologada (estilo light-pagination del tema) ── */
    .lw-pager {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; gap: 10px;
        width: 100%; padding: 2px 0;
    }
    .lw-pager-info { font-size: 12px; color: #8a93a2; }
    .lw-pager-info b { color: #46505e; font-weight: 600; }
    .lw-pager-list {
        display: flex; align-items: center; gap: 6px;
        list-style: none; margin: 0; padding: 0;
    }
    .lw-page {
        min-width: 34px; height: 34px; padding: 0 10px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 0; border-radius: 8px;
        background: rgba(var(--secondary), 0.08);
        color: #46505e; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: background .12s ease, color .12s ease;
    }
    .lw-page .ti { font-size: 15px; }
    button.lw-page:hover { background: rgba(var(--primary), 0.12); color: rgba(var(--primary), 1); }
    .lw-page.is-active {
        background: rgba(var(--primary), 1); color: #fff;
        box-shadow: 0 4px 10px rgba(var(--primary), 0.35);
    }
    .lw-page.is-dots, .lw-page.is-disabled { background: transparent; color: #bcc3cd; }
    @media (max-width: 575px) {
        .lw-pager { justify-content: center; }
        .lw-pager-info { width: 100%; text-align: center; }
    }

</style>
@vite(['public/assets/scss/style.scss'])
@livewireStyles
