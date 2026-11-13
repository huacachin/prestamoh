<?php

namespace App\Services\Documentos\Ocr;

use RuntimeException;

/** La lectura del formulario del SIGM falló: los datos se escriben a mano y se sigue. */
class FormularioSigmIlegible extends RuntimeException {}
