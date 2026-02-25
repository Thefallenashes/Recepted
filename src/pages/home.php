<?php
session_start();

require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug'])) {
    try {
        $pdo = getPDO();
        if (function_exists('login_as_debug')) {
            login_as_debug($pdo);
            if (function_exists('record_audit_log')) {
                record_audit_log($pdo, 'debug_mode_enabled', 'warning', 'Activado desde home.php');
            }
            header('Location: index.php');
            exit();
        }
    } catch (Exception $e) {
        error_log('Error en debug guest home: ' . $e->getMessage());
    }
}

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

$logoutIconDataUri = 'data:image/png;base64,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';
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
    <header class="sticky-home-menu">
        <div class="sticky-home-menu-inner">
            <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
                <span class="icon-home" aria-hidden="true">⌂</span>
                <span>Inicio</span>
            </a>

            <nav class="navbar sticky-links">
                <ul>
                    <li><a href="finanzas.php">Finanzas</a></li>
                    <li><a href="perfil.php">Perfil</a></li>
                    <li><a href="tickets.php">Tickets</a></li>
                    <?php if (function_exists('has_min_role') && has_min_role('admin')): ?>
                        <li><a href="admin_panel.php">Panel Admin</a></li>
                    <?php endif; ?>
                    <?php if (function_exists('has_min_role') && has_min_role('superadmin')): ?>
                        <li><a href="superadmin_console.php">Consola Superadmin</a></li>
                    <?php endif; ?>
                    <li><a href="config.php">Configuración</a></li>
                </ul>
            </nav>

            <a class="menu-icon-btn logout-btn" href="logout.php" aria-label="Cerrar sesión">
                <img src="<?php echo htmlspecialchars($logoutIconDataUri); ?>" alt="Cerrar sesión" class="logout-icon">
                <span>Cerrar sesión</span>
            </a>
        </div>
    </header>

    <div class="home-container">
        <header class="header">
            <h1>Bienvenido, <?php echo htmlspecialchars($usuario['nombre']); ?></h1>
            <form method="POST" action="" style="margin: 10px 0;">
                <button type="submit" name="debug" value="1">Debug</button>
            </form>
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