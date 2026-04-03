@section('title', 'Iniciar Sesión | Taxivan')
@include('layout.head')

@include('layout.css')

<body>

<livewire:auth.login />
@livewireScripts
</body>
@section('script')
    <!-- Bootstrap js-->
    <script src="{{asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')}}"></script>
@endsection
