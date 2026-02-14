<?php
session_start();
require_once __DIR__ . '/../utils/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mis_uploads.php');
    exit();
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: mis_uploads.php');
    exit();
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row || $row['user_id'] != $_SESSION['usuario_id']) {
        header('Location: mis_uploads.php');
        exit();
    }

    // Borrar archivo físico
    $file = dirname(__DIR__) . '/' . $row['filepath'];
    if (is_file($file)) {
        @unlink($file);
    }

    // Borrar registro
    $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $id]);

    header('Location: mis_uploads.php');
    exit();
} catch (PDOException $e) {
    error_log('Delete upload error: ' . $e->getMessage());
    header('Location: mis_uploads.php');
    exit();
}
?>