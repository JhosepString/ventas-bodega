<?php
declare(strict_types=1);

namespace App\Database;

/**
 * Define el contrato mínimo para controlar una transacción.
 */
interface TransaccionInterface
{
    /**
     * Inicia una transacción.
     *
     * @return void
     */
    public function begin(): void;

    /**
     * Confirma la transacción actual.
     *
     * @return void
     */
    public function commit(): void;

    /**
     * Revierte la transacción actual.
     *
     * @return void
     */
    public function rollBack(): void;
}
