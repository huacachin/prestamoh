<?php

namespace App\Services\Credits;

use RuntimeException;

/** El crédito no se puede borrar tal como está (tiene garantía o contrato del área legal). */
class CreditoNoEliminableException extends RuntimeException {}
