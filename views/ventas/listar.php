<?php
declare(strict_types=1);

namespace App\Views;

$title = 'Ventas del día';
require dirname(__DIR__) . '/layouts/header.php';
?>
<h2>Ventas del día</h2>
<p>Fecha: <?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php elseif ($ventas === []): ?>
    <p>No hay ventas registradas hoy.</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Precio unitario</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($ventas as $venta): ?>
            <tr>
                <td><?= htmlspecialchars($venta->fechaHora ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($venta->productoNombre ?? 'Producto', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $venta->cantidad, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td>S/ <?= htmlspecialchars(number_format($venta->precioUnitario, 2, '.', ','), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td>S/ <?= htmlspecialchars(number_format($venta->total, 2, '.', ','), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<p><strong>Total vendido: S/ <?= htmlspecialchars(number_format($total, 2, '.', ','), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></p>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
