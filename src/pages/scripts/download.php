-<?php
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

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($row['mime'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($row['filename']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));

    // Limpiar buffers
    flush();
    readfile($file);
    exit();
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error al procesar la descarga.';
    error_log('Download error: ' . $e->getMessage());
    exit();
}
?>
