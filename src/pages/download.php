<?php
session_start();
require_once __DIR__ . '/../utils/db.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit();
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'ID inválido.';
    exit();
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo 'Archivo no encontrado.';
        exit();
    }

    // Verificar propietario
    if ($row['user_id'] != $_SESSION['usuario_id']) {
        http_response_code(403);
        echo 'No tienes permiso para descargar este archivo.';
        exit();
    }

    $relative = $row['filepath']; // e.g. uploads/abc.png
    $file = dirname(__DIR__) . '/' . $relative; // points to src/uploads/...

    if (!is_file($file)) {
        http_response_code(404);
        echo 'Archivo físico no encontrado.';
        exit();
    }

    // For safety: set headers and read file
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