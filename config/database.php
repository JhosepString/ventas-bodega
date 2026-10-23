<?php
declare(strict_types=1);

/**
 * Carga la configuración de MySQL desde el archivo .env de la raíz.
 *
 * @return array{host: string, port: int, name: string, user: string, pass: string, charset: string}
 */
$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

if (!is_file($envPath) || !is_readable($envPath)) {
    throw new RuntimeException('No se encontró un archivo .env legible en la raíz del proyecto.');
}

$envValues = [];
$envLines = file($envPath, FILE_IGNORE_NEW_LINES);

if ($envLines === false) {
    throw new RuntimeException('No se pudo leer la configuración de la base de datos.');
}

foreach ($envLines as $line) {
    $trimmedLine = trim($line);

    if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
        continue;
    }

    if (!preg_match('/^([A-Z_]+)=(.*)$/', $trimmedLine, $matches)) {
        throw new RuntimeException('El archivo .env contiene una línea de configuración inválida.');
    }

    $envValues[$matches[1]] = trim($matches[2]);
}

$requiredKeys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];

foreach ($requiredKeys as $requiredKey) {
    if (!array_key_exists($requiredKey, $envValues)) {
        throw new RuntimeException('Falta una variable de configuración requerida en .env.');
    }
}

foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $nonEmptyKey) {
    if ($envValues[$nonEmptyKey] === '') {
        throw new RuntimeException('Una variable de conexión requerida en .env está vacía.');
    }
}

if (!ctype_digit($envValues['DB_PORT']) || (int) $envValues['DB_PORT'] < 1 || (int) $envValues['DB_PORT'] > 65535) {
    throw new RuntimeException('DB_PORT en .env debe ser un puerto numérico válido.');
}

return [
    'host' => $envValues['DB_HOST'],
    'port' => (int) $envValues['DB_PORT'],
    'name' => $envValues['DB_NAME'],
    'user' => $envValues['DB_USER'],
    'pass' => $envValues['DB_PASS'],
    'charset' => 'utf8mb4',
];
