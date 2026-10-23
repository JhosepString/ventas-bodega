<?php
declare(strict_types=1);

namespace App\Views;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'La Esquina', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem auto; max-width: 960px; padding: 0 1rem; color: #222; }
        nav { display: flex; gap: 1rem; margin-bottom: 2rem; }
        table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
        th, td { border: 1px solid #ccc; padding: .6rem; text-align: left; }
        .stock-bajo { background: #fff1c2; }
        .error { background: #ffe2e2; padding: .8rem; }
        .success { background: #e2f7e8; padding: .8rem; }
        label { display: block; margin: .8rem 0; }
        input, select { display: block; max-width: 28rem; padding: .45rem; width: 100%; }
    </style>
</head>
<body>
<header>
    <h1>Minimarket «La Esquina»</h1>
    <nav aria-label="Navegación principal">
        <a href="index.php?accion=productos">Productos</a>
        <a href="index.php?accion=crear-producto">Registrar producto</a>
        <a href="index.php?accion=registrar-venta">Registrar venta</a>
        <a href="index.php?accion=ventas">Ventas del día</a>
    </nav>
</header>
<main>
