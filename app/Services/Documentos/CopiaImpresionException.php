<?php

namespace App\Services\Documentos;

use RuntimeException;

/** No se pudo producir la copia de impresión (Ghostscript ausente, falló o se pasó de tiempo). */
class CopiaImpresionException extends RuntimeException {}
