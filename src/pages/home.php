<?php
session_start();

require_once __DIR__ . '/../utils/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener información del usuario desde la base de datos
try {
    $pdo = getPDO();

    // Obtener datos del usuario
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();

    // Obtener datos de finanzas
    $stmt = $pdo->prepare('SELECT * FROM finanzas WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
    $finanzas = $stmt->fetch();
} catch (PDOException $e) {
    echo "Error al conectar: " . htmlspecialchars($e->getMessage());
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Dashboard</title>
    <link rel="stylesheet" href="../css/home.css">
</head>
<body>
    <div class="home-container">
        <header class="header">
            <h1>Bienvenido, <?php echo htmlspecialchars($usuario['nombre']); ?></h1>
            <nav class="navbar">
                <ul>
                    <li><a href="home.php">Inicio</a></li>
                    <li><a href="finanzas.php">Finanzas</a></li>
                    <li><a href="perfil.php">Perfil</a></li>
                    <li><a href="config.php">Configuración</a></li>
                    <li><a href="logout.php">Cerrar Sesión</a></li>
                </ul>
            </nav>
        </header>

        <main class="content">
            <section class="dashboard">
                <div class="user-info">
                    <h2>Información del Usuario</h2>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?></p>
                    <p><strong>Correo:</strong> <?php echo htmlspecialchars($usuario['correo']); ?></p>
                    <p><strong>Edad:</strong> <?php echo htmlspecialchars($usuario['edad']); ?> años</p>
                    <p><strong>Miembro desde:</strong> <?php echo date('d/m/Y', strtotime($usuario['created_at'])); ?></p>
                </div>

                <div class="finance-summary">
                    <h2>Resumen Financiero</h2>
                    <?php if ($finanzas): ?>
                        <div class="finance-box">
                            <div class="finance-item">
                                <label>Balance:</label>
                                <span class="balance"><?php echo number_format($finanzas['balance'], 2); ?> <?php echo $finanzas['currency']; ?></span>
                            </div>
                            <div class="finance-item">
                                <label>Ingresos:</label>
                                <span class="income"><?php echo number_format($finanzas['income'], 2); ?> <?php echo $finanzas['currency']; ?></span>
                            </div>
                            <div class="finance-item">
                                <label>Gastos:</label>
                                <span class="expenses"><?php echo number_format($finanzas['expenses'], 2); ?> <?php echo $finanzas['currency']; ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>No hay información financiera disponible.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
