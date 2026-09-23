<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Copia para imprimir no disponible</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f6f7f9; color: #222; margin: 0; padding: 40px 16px; }
        .caja { max-width: 560px; margin: 40px auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 28px 32px; }
        h1 { font-size: 20px; margin: 0 0 12px; color: #b02a37; }
        p { line-height: 1.5; margin: 0 0 12px; }
        a.boton { display: inline-block; margin-top: 8px; padding: 10px 18px; background: #dc3545; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; }
        small { color: #666; }
    </style>
</head>
<body>
    <div class="caja">
        <h1>No se pudo preparar la copia rápida para imprimir</h1>
        <p>El documento <strong>{{ $nombre }}</strong> existe, pero el servidor no pudo convertirlo a hojas de imagen. Puedes abrir el PDF normal, aunque en la fotocopiadora tardará más por hoja.</p>
        <p><a class="boton" href="{{ $urlPdf }}">Abrir el PDF normal</a></p>
        <small>El motivo técnico quedó registrado en el sistema. Avisa a soporte si vuelve a pasar.</small>
    </div>
</body>
</html>
