<?php
declare(strict_types=1);

namespace App\Views;

$title = 'Registrar producto';
require dirname(__DIR__) . '/layouts/header.php';
?>
<h2>Registrar producto</h2>
<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($success !== null): ?>
    <p class="success" role="status"><?= htmlspecialchars($success, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="index.php?accion=guardar-producto">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <label>
        Código
        <input name="codigo" maxlength="50" required value="<?= htmlspecialchars($formData['codigo'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label>
    <label>
        Nombre
        <input name="nombre" maxlength="150" required value="<?= htmlspecialchars($formData['nombre'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label>
    <label>
        Categoría
        <input name="categoria" maxlength="80" required value="<?= htmlspecialchars($formData['categoria'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label>
    <label>
        Precio de venta (S/)
        <input name="precio_venta" type="number" min="0" step="0.01" required value="<?= htmlspecialchars($formData['precio_venta'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label>
    <label>
        Stock actual
        <input name="stock_actual" type="number" min="0" step="1" required value="<?= htmlspecialchars($formData['stock_actual'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label>
    <label>
        Stock mínimo
        <input name="stock_minimo" type="number" min="0" step="1" required value="<?= htmlspecialchars($formData['stock_minimo'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label>
    <button type="submit">Guardar producto</button>
</form>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
