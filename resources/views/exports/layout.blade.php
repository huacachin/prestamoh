<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        body, table, td, th { font-family: Calibri, Arial, sans-serif; font-size: 10.0pt; }
        /* Todo como texto (igual que el legacy): fechas d/m/Y, DNI, códigos… se
           muestran tal cual, sin que Excel los reinterprete como serial/número. */
        td, th { mso-number-format: "\@"; vertical-align: middle; }
        .txt { mso-number-format: "\@"; }
        /* 02/10 (Antony): los MONTOS van como número con formato, no como texto:
           con "@" Excel los mostraba bien pero =SUMA() daba 0. La celda imprime el
           valor crudo (1234.50) y Excel lo pinta "1,234.50". El estilo va también
           inline en cada celda de monto, que es lo que Excel respeta sin falta. */
        td.num, th.num { mso-number-format: "#,##0.00"; }
        /* Una celda sin alineación explícita se veía a la izquierda (texto); como
           número Excel la pondría a la derecha. Para que no varíe nada, se deja
           a la izquierda. (El estilo inline del tag, si lo hay, manda.) */
        td.izq, th.izq { text-align: left; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
