<?php
session_start();

require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/query_helpers.php';
require_once __DIR__ . '/includes/sticky_menu.php';

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

    $userId = (int)$_SESSION['usuario_id'];
    $usuario = fetch_user_by_id($pdo, $userId);
    $finanzas = fetch_user_finanzas($pdo, $userId);
    $recent_transactions = fetch_recent_user_transactions($pdo, $userId, 5);
    $uploads = fetch_uploads_visible_for_user($pdo, $userId);
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
    <?php
    render_sticky_menu([
        'container_class' => 'sticky-home-menu',
        'inner_class' => 'sticky-home-menu-inner',
        'nav_class' => 'navbar sticky-links',
        'home_href' => 'home.php',
        'logout_href' => 'scripts/logout.php',
        'nav_items' => [
            ['href' => 'finanzas.php', 'label' => 'Finanzas'],
            ['href' => 'tickets.php', 'label' => 'Tickets'],
            ['href' => 'admin_panel.php', 'label' => 'Panel de administracion', 'min_role' => 'admin'],
            ['href' => 'superadmin_console.php', 'label' => 'Consola', 'min_role' => 'superadmin'],
            ['href' => 'config.php', 'label' => 'Configuración'],
        ],
    ]);
    ?>

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
        <!-- #region           <h2>Resumen Financiero</h2>
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
-->
                    <h2>Últimas transacciones</h2>
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

                    <h3>Archivos</h3>
                    <?php if (empty($uploads)): ?>
                        <div class="uploads-cta">
                            <p>¡Comienza a analizar tus finanzas! Para comenzar sube y analiza tus archivos Excel</p>
                            <a href="mis_uploads.php" class="btn-uploads">Ir a mis archivos →</a>
                        </div>
                    <?php else: ?>
                        <p class="uploads-link">Tienes <?php echo count($uploads); ?> archivo<?php echo count($uploads) !== 1 ? 's' : ''; ?> subido<?php echo count($uploads) !== 1 ? 's' : ''; ?>. <a href="mis_uploads.php">Ver mis archivos</a></p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>

</html>


