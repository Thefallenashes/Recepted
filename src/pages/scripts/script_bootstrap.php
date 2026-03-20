<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../utils/db.php';
require_once __DIR__ . '/../../utils/auth.php';

/**
 * Exige sesión autenticada para scripts y devuelve el user_id actual.
 */
function require_script_user(string $mode = 'redirect', string $redirectTo = '../login.php'): int
{
    if (isset($_SESSION['usuario_id'])) {
        return (int)$_SESSION['usuario_id'];
    }

    if ($mode === 'http403') {
        http_response_code(403);
        exit('Acceso denegado.');
    }

    header('Location: ' . $redirectTo);
    exit();
}

/**
 * Obtiene un upload por id si el usuario actual tiene acceso.
 *
 * @return array<string, mixed>|null
 */
function get_accessible_upload(PDO $pdo, int $uploadId, int $userId): ?array
{
    if ($uploadId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $uploadId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $isAdmin = function_exists('can_manage_all_resources') && can_manage_all_resources();
    if ((int)$row['user_id'] !== $userId && !$isAdmin) {
        return null;
    }

    return $row;
}

/**
 * Resuelve una ruta de upload asegurando que está dentro de src/uploads.
 */
function resolve_upload_realpath(string $relativePath): ?string
{
    $base = realpath(dirname(__DIR__, 2) . '/uploads');
    if ($base === false) {
        return null;
    }

    $realFile = realpath(dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/'));
    if ($realFile === false || strpos($realFile, $base) !== 0 || !is_file($realFile)) {
        return null;
    }

    return $realFile;
}
