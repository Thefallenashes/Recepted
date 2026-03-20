<?php
/**
 * Sirve el contenido binario de un archivo subido para su procesamiento en cliente
 * Solo permite archivos con extensiones de Excel.
 */
require_once __DIR__ . '/script_bootstrap.php';

$userId = require_script_user('http403');

$allowedExts = ['csv', 'xlsb', 'xltx', 'xls', 'xlsm', 'xlsx'];

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido.');
}

try {
    $pdo = getPDO();
    $row = get_accessible_upload($pdo, $id, $userId);
    if (!$row) {
        http_response_code(404);
        exit('Archivo no encontrado o sin permisos.');
    }

    // Verificar extensión 
    $ext = strtolower(pathinfo($row['filename'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        http_response_code(400);
        exit('Tipo de archivo no permitido para análisis.');
    }

    $realFile = resolve_upload_realpath((string)$row['filepath']);
    if ($realFile === null) {
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
