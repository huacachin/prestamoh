<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        body, table, td, th { font-family: Calibri, Arial, sans-serif; font-size: 10.0pt; }
        /* Todo como texto (igual que el legacy): fechas d/m/Y y montos formateados
           se muestran tal cual, sin que Excel los reinterprete como serial/número. */
        td, th { mso-number-format: "\@"; vertical-align: middle; }
        .txt { mso-number-format: "\@"; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
