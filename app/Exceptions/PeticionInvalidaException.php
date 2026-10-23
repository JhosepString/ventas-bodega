<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Indica que una petición modificadora no superó la validación CSRF.
 */
class PeticionInvalidaException extends \RuntimeException
{
}
