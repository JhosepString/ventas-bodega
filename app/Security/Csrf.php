<?php
declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Genera y valida tokens CSRF almacenados en la sesión.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Devuelve el token de la sesión, generándolo si aún no existe.
     *
     * @return string Token impredecible para formularios modificadores.
     */
    public function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('La sesión debe iniciarse antes de generar un token CSRF.');
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Compara el token recibido con el token almacenado en la sesión.
     *
     * @param string $token Token enviado por el formulario.
     *
     * @return bool True si el token coincide; false en caso contrario.
     */
    public function verify(string $token): bool
    {
        if (
            session_status() !== PHP_SESSION_ACTIVE
            || !isset($_SESSION[self::SESSION_KEY])
            || !is_string($_SESSION[self::SESSION_KEY])
        ) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }
}
