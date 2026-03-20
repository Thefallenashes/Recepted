<?php
/**
 * Sirve el contenido binario de un archivo subido para su
 * procesamiento en cliente (SheetJS). Solo permite archivos
 * con extensiones Excel admitidas.
 */
session_start();
require_once __DIR__ . '/../../utils/db.php';
require_once __DIR__ . '/../../utils/auth.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$allowedExts = ['csv', 'xlsb', 'xltx', 'xls', 'xlsm', 'xlsx'];

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido.');
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }

    // Verificar propietario o rol admin/superadmin
    $isAdmin = function_exists('can_manage_all_resources') && can_manage_all_resources();
    if ($row['user_id'] != $_SESSION['usuario_id'] && !$isAdmin) {
        http_response_code(403);
        exit('Sin permiso para acceder a este archivo.');
    }

    // Verificar extensión permitida
    $ext = strtolower(pathinfo($row['filename'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        http_response_code(400);
        exit('Tipo de archivo no permitido para análisis.');
    }

    $file = dirname(__DIR__) . '/' . $row['filepath'];
    // Asegurar que la ruta resuelta esté dentro del directorio de uploads
    $uploadsBase = realpath(dirname(__DIR__) . '/uploads');
    $realFile    = realpath($file);
    if ($realFile === false || strpos($realFile, $uploadsBase) !== 0) {
        http_response_code(403);
        exit('Ruta de archivo inválida.');
    }

    if (!is_file($realFile)) {
        http_response_code(404);
        exit('Archivo físico no encontrado.');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($realFile));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    flush();
    readfile($realFile);
    exit();

} catch (PDOException $e) {
    error_log('get_upload_raw error: ' . $e->getMessage());
    http_response_code(500);
    exit('Error interno del servidor.');
}
