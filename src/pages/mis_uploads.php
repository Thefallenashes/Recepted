<?php
session_start();
require_once __DIR__ . '/../utils/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM uploads WHERE user_id = :user_id ORDER BY uploaded_at DESC');
    $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
    $uploads = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching uploads: ' . $e->getMessage());
    $uploads = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis archivos</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
    <div class="index-container">
        <h1>Mis archivos</h1>

        <?php if (empty($uploads)): ?>
            <p>No has subido archivos todavía.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tamaño</th>
                        <th>Tipo</th>
                        <th>Subido</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploads as $up): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($up['filename']); ?></td>
                            <td><?php echo number_format($up['size'] / 1024, 2); ?> KB</td>
                            <td><?php echo htmlspecialchars($up['mime']); ?></td>
                            <td><?php echo htmlspecialchars($up['uploaded_at']); ?></td>
                            <td>
                                <a href="download.php?id=<?php echo $up['id']; ?>">Descargar</a>
                                |
                                <form method="POST" action="delete_upload.php" style="display:inline" onsubmit="return confirm('¿Eliminar archivo?');">
                                    <input type="hidden" name="id" value="<?php echo $up['id']; ?>">
                                    <button type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><a href="upload.php">Subir nuevo archivo</a> | <a href="perfil.php">Volver al perfil</a> | <a href="logout.php">Cerrar Sesión</a></p>
    </div>
</body>
</html>
