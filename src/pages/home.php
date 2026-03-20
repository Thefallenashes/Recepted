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

    // Obtener las 5 transacciones más recientes
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5');
    $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
    $recent_transactions = $stmt->fetchAll();
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
    <title>Panel de control</title>
    <link rel="stylesheet" href="../css/home.css">
</head>

<body>
    <header class="sticky-home-menu is-collapsed" data-sticky-menu data-icon-collapsed="../images/MostrarMenuDesplegable.PNG" data-icon-expanded="../images/OcultarMenuDesplegable.PNG">
        <div class="sticky-home-menu-inner">
            <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
                <img src="../images/Home.PNG" alt="Inicio" class="icon-home">
                <span>Inicio</span>
            </a>

            <button type="button" class="menu-icon-btn menu-toggle-btn" data-menu-toggle aria-label="Mostrar menu desplegable" aria-expanded="false">
                <img src="../images/MostrarMenuDesplegable.PNG" alt="Mostrar menu desplegable" class="menu-toggle-icon" data-menu-toggle-icon>
            </button>

            <nav class="navbar sticky-links">
                <ul>
                    <li><a href="finanzas.php">Finanzas</a></li>
                    <li><a href="tickets.php">Tickets</a></li>
                    <?php if (function_exists('has_min_role') && has_min_role('admin')): ?>
                        <li><a href="admin_panel.php">Panel de administracion</a></li>
                    <?php endif; ?>
                    <?php if (function_exists('has_min_role') && has_min_role('superadmin')): ?>
                        <li><a href="superadmin_console.php">Consola</a></li>
                    <?php endif; ?>
                    <li><a href="config.php">Configuración</a></li>
                </ul>
            </nav>

            <a class="menu-icon-btn logout-btn" href="scripts/logout.php" aria-label="Cerrar sesión">
                <img src="../images/BotonLogOut.PNG" alt="Cerrar sesión" class="logout-icon">
                <span>Cerrar sesión</span>
            </a>
        </div>
    </header>

    <div class="home-container">
        <header class="header">
            <h1>Bienvenido, <?php echo htmlspecialchars($usuario['nombre']); ?></h1>
            <form method="POST" action="" style="margin: 10px 0;">
                <button type="submit" name="debug" value="1">Modo de desarollo</button>
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

                    <h3>Últimas transacciones</h3>
                    <?php if (!empty($recent_transactions)): ?>
                        <table class="recent-transactions">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th>Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $tx): ?>
                                    <tr class="tx-<?php echo htmlspecialchars($tx['type']); ?>">
                                        <td><?php echo date('d/m/Y', strtotime($tx['created_at'])); ?></td>
                                        <td><?php echo $tx['type'] === 'income' ? 'Ingreso' : 'Gasto'; ?></td>
                                        <td><?php echo htmlspecialchars($tx['category']); ?></td>
                                        <td><?php echo htmlspecialchars($tx['description'] ?? '—'); ?></td>
                                        <td class="tx-amount"><?php echo ($tx['type'] === 'income' ? '+' : '-') . number_format($tx['amount'], 2); ?> <?php echo $finanzas ? htmlspecialchars($finanzas['currency']) : ''; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No hay transacciones registradas.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>

</html>


