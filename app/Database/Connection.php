<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

/**
 * Adapta PDO a la interfaz de transacciones de la aplicación.
 */
final class Connection implements TransaccionInterface
{
    /**
     * Recibe la conexión PDO ya configurada.
     *
     * @param PDO $pdo Conexión a MySQL con excepciones habilitadas.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Devuelve la conexión PDO compartida por los repositorios.
     *
     * @return PDO Conexión configurada a la base de datos.
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Inicia una transacción en PDO.
     *
     * @return void
     */
    public function begin(): void
    {
        if (!$this->pdo->beginTransaction()) {
            throw new RuntimeException('No se pudo iniciar la transacción.');
        }
    }

    /**
     * Confirma la transacción activa.
     *
     * @return void
     */
    public function commit(): void
    {
        if (!$this->pdo->commit()) {
            throw new RuntimeException('No se pudo confirmar la transacción.');
        }
    }

    /**
     * Revierte la transacción activa.
     *
     * @return void
     */
    public function rollBack(): void
    {
        if ($this->pdo->inTransaction() && !$this->pdo->rollBack()) {
            throw new RuntimeException('No se pudo revertir la transacción.');
        }
    }
}
