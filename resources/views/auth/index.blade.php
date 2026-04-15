<!DOCTYPE html>
<html lang="es">
<head>
    @include('layout.head')
    @include('layout.css')
</head>
<body>

<livewire:auth.login />

@livewireScripts
<script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
