<?php
declare(strict_types=1);

namespace App\Views;

$title = 'Registrar venta';
require dirname(__DIR__) . '/layouts/header.php';
?>
<h2>Registrar venta</h2>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($success !== null): ?>
    <p class="success" role="status"><?= htmlspecialchars($success, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($productos === []): ?>
    <p>No hay productos disponibles para vender.</p>
<?php else: ?>
    <form method="post" action="index.php?accion=guardar-venta">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <label>
            Producto
            <select name="producto_id" required>
                <option value="">Seleccione un producto</option>
                <?php foreach ($productos as $producto): ?>
                    <option
                        value="<?= htmlspecialchars((string) $producto->id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        <?= ($formData['producto_id'] ?? '') === (string) $producto->id ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($producto->codigo . ' — ' . $producto->nombre . ' (stock: ' . $producto->stockActual . ')', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Cantidad
            <input name="cantidad" type="number" min="1" step="1" required value="<?= htmlspecialchars($formData['cantidad'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </label>
        <button type="submit">Registrar venta</button>
    </form>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
