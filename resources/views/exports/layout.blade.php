<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        body, table, td, th { font-family: Calibri, Arial, sans-serif; font-size: 10.0pt; }
        td, th { mso-number-format: "General"; vertical-align: middle; }
        /* Columnas de texto (DNI/celular): no perder ceros a la izquierda */
        .txt { mso-number-format: "\@"; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
