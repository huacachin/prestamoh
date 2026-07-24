<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Modo
    |--------------------------------------------------------------------------
    | "local"     → imprime directo en la impresora del servidor (dev/desktop)
    | "broadcast" → encola un PrintJob y emite evento por Reverb (futuro)
    */
    'mode' => env('PRINTER_MODE', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Conector
    |--------------------------------------------------------------------------
    | "cups"    → cola CUPS local (macOS/Linux, recomendado)
    | "windows" → impresora compartida en Windows (LPT1, "TICKETERA",
    |             smb://host/printer)
    | "network" → IP:puerto (típicamente 9100)
    | "file"    → device path tipo /dev/usb/lp0
    */
    'driver' => env('PRINTER_DRIVER', 'cups'),

    /*
    | Nombre exacto de la cola CUPS (lpstat -p).
    | Para ESC/POS conviene una cola RAW dedicada para evitar el filtro
    | PostScript del driver Mac. El connector usa `lp -o raw` para forzarlo
    | igual sobre cualquier cola.
    */
    'cups_name' => env('PRINTER_CUPS_NAME', 'ticket_raw'),

    /*
    | Para Windows: nombre del recurso compartido (basta con el nombre,
    | la librería resuelve la PC actual con gethostname). También admite
    | "LPT1"/"COM1" o "smb://host/printer".
    */
    'windows_path' => env('PRINTER_WINDOWS_PATH', 'TICKETERA'),

    'network_host' => env('PRINTER_HOST', '192.168.1.50'),
    'network_port' => (int) env('PRINTER_PORT', 9100),

    'file_path' => env('PRINTER_FILE_PATH', '/dev/usb/lp0'),

    /*
    | Ancho de impresión: 32 cols para 58mm, 48 cols para 80mm.
    */
    'columns' => (int) env('PRINTER_COLUMNS', 48),

    /*
    | Cabecera del ticket. Si la empresa tiene esto en BD, se puede
    | sobrescribir desde el TicketPrinter.
    */
    'company_name' => env('PRINTER_COMPANY_NAME', 'HUACACHIN'),
    'company_ruc'  => env('PRINTER_COMPANY_RUC', ''),
    'company_addr' => env('PRINTER_COMPANY_ADDR', ''),

    /*
    | Logo (opcional). Path RELATIVO a storage/app/public/.
    | Por defecto: storage/app/public/printer/logo.png
    | Si el archivo no existe, el ticket arranca directo con el nombre.
    | Formatos: png, jpg, gif. Recomendado PNG b/n con fondo blanco.
    */
    'print_logo'     => filter_var(env('PRINTER_PRINT_LOGO', true), FILTER_VALIDATE_BOOLEAN),
    'logo_path'      => env('PRINTER_LOGO_PATH', 'printer/logo.png'),
    'logo_max_width' => (int) env('PRINTER_LOGO_MAX_WIDTH', 300),
];
