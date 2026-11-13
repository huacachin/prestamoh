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
           valor crudo (1234.50) y Excel lo pinta "1,234.50". */
        td.num, th.num { mso-number-format: "#,##0.00"; }
        /* Monto que se queda a la izquierda (una celda sin alineación explícita se
           veía a la izquierda como texto; como número Excel la pondría a la
           derecha). UNA sola clase: Excel no aplica las reglas a una celda con
           dos clases ("num" + "izq" quedaba como texto "3135.77", sin coma ni
           SUMA: los TOTAL/SALDO de Caja 3, 13/11). El estilo inline manda. */
        td.num-izq, th.num-izq { mso-number-format: "#,##0.00"; text-align: left; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
