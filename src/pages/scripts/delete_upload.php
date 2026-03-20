<?php
require_once __DIR__ . '/script_bootstrap.php';

$userId = require_script_user('redirect', '../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../mis_uploads.php');
    exit();
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ../mis_uploads.php');
    exit();
}

try {
    $pdo = getPDO();
    $row = get_accessible_upload($pdo, $id, $userId);
    if (!$row) {
        header('Location: ../mis_uploads.php');
        exit();
    }

    // Borrar archivo físico
    $file = resolve_upload_realpath((string)$row['filepath']);
    if ($file !== null) {
        @unlink($file);
    }

    // Borrar registro
    $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $id]);

    header('Location: ../mis_uploads.php');
    exit();
} catch (PDOException $e) {
    error_log('Delete upload error: ' . $e->getMessage());
    header('Location: ../mis_uploads.php');
    exit();
}
?>
