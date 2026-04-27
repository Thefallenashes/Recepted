<?php
require_once __DIR__ . '/script_bootstrap.php';

$userId = require_script_user('redirect', '../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../mis_uploads.php');
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect('../mis_uploads.php');
}

try {
    $pdo = getPDO();
    $row = get_accessible_upload($pdo, $id, $userId);
    if (!$row) {
        redirect('../mis_uploads.php');
    }

    // Borrar archivo físico
    $file = resolve_upload_realpath((string)$row['filepath']);
    if ($file !== null) {
        @unlink($file);
    }

    // Borrar registro
    $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $id]);

    redirect('../mis_uploads.php');
} catch (PDOException $e) {
    error_log('Delete upload error: ' . $e->getMessage());
    redirect('../mis_uploads.php');
}
