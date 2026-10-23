<?php
declare(strict_types=1);

namespace App\Views;

$title = 'Productos';
require dirname(__DIR__) . '/layouts/header.php';
?>
<h2>Productos registrados</h2>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php elseif ($productos === []): ?>
    <p>No hay productos registrados.</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Precio de venta</th>
            <th>Stock actual</th>
            <th>Stock mínimo</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($productos as $producto): ?>
            <tr class="<?= $producto->stockActual <= $producto->stockMinimo ? 'stock-bajo' : '' ?>">
                <td><?= htmlspecialchars($producto->codigo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($producto->nombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($producto->categoria, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td>S/ <?= htmlspecialchars(number_format($producto->precioVenta, 2, '.', ','), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $producto->stockActual, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $producto->stockMinimo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
