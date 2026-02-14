<?php
require_once __DIR__ . '/../utils/db.php';

// Página pública: mostrar estadísticas básicas
try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT COUNT(*) AS total_users FROM users');
    $total_users = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) AS total_uploads FROM uploads');
    $total_uploads = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_users = 0;
    $total_uploads = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Índice</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
    <div class="index-container">
        <h1>Índice</h1>
        <p>Usuarios registrados: <?php echo (int)$total_users; ?></p>
        <p>Archivos subidos: <?php echo (int)$total_uploads; ?></p>
        <p><a href="login.php">Iniciar sesión</a> | <a href="register.php">Registrarse</a></p>
    </div>
</body>
</html>
