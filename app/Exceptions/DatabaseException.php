<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Representa un fallo de persistencia que no debe mostrarse en detalle al usuario.
 */
class DatabaseException extends \RuntimeException
{
}
