<?php

namespace App\Services\Documentos\Ocr;

use RuntimeException;

/** La lectura del voucher falló: el operador transcribe a mano y sigue. */
class VoucherIlegible extends RuntimeException {}
