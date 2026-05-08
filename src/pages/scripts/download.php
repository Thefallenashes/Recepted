<?php
require_once __DIR__ . '/script_bootstrap.php';

$userId = require_script_user('http403');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'ID inválido.';
    exit();
}

try {
    $pdo = getPDO();
    $row = get_accessible_upload($pdo, $id, $userId);
    if (!$row) {
        http_response_code(404);
        echo 'Archivo no encontrado o sin permisos.';
        exit();
    }

    $file = resolve_upload_realpath((string)$row['filepath']);
    if ($file === null) {
        http_response_code(404);
        echo 'Archivo físico no encontrado.';
        exit();
    }

    $originalName = (string)($row['filename'] ?? 'archivo');
    $mime = (string)($row['mime'] ?: 'application/octet-stream');
    send_binary_file_response($file, $mime, $originalName, true);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error al procesar la descarga.';
    error_log('Download error: ' . $e->getMessage());
    exit();
}
