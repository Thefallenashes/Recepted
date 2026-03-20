<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/query_helpers.php';
require_once __DIR__ . '/includes/sticky_menu.php';

require_min_role('admin', 'home.php');

$mensaje = '';
$tipo = '';

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_rol'])) {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newRole = normalize_user_role($_POST['new_role'] ?? 'user');
        $myRole = normalize_user_role($_SESSION['usuario_rol'] ?? 'user');

        $stmt = $pdo->prepare('SELECT id, role, correo FROM users WHERE id = :id');
        $stmt->execute(['id' => $targetUserId]);
        $target = $stmt->fetch();

        if (!$target || $targetUserId <= 0) {
            $tipo = 'error';
            $mensaje = 'Usuario objetivo no válido.';
        } else {
            $targetCurrentRole = normalize_user_role($target['role'] ?? 'user');

            if ($targetUserId === (int)$_SESSION['usuario_id']) {
                // No se puede cambiar el propio rol
                $canEdit = false;
            } elseif ($myRole === 'superadmin') {
                // Un superadmin puede editar a cualquier otro usuario, incluyendo otros superadmins
                $canEdit = true;
            } else {
                $canEdit = role_level($myRole) > role_level($targetCurrentRole);
                if ($myRole === 'admin' && $newRole === 'superadmin') {
                    $canEdit = false;
                }
            }

            if (!$canEdit) {
                $tipo = 'error';
                $mensaje = 'No tienes permisos para cambiar este rol.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                $stmt->execute(['role' => $newRole, 'id' => $targetUserId]);
                record_audit_log($pdo, 'user_role_updated', 'warning', 'Cambio de rol de usuario #' . $targetUserId . ' a ' . $newRole, $targetUserId);
                $tipo = 'exito';
                $mensaje = 'Rol actualizado correctamente.';
            }
        }
    }

    $usersCount = fetch_total_users($pdo);
    $uploadsCount = fetch_total_uploads($pdo);
    $openTickets = fetch_open_tickets_count($pdo);
    $users = fetch_admin_users_list($pdo);
    $expenseReport = fetch_expense_report_last_days($pdo, 30, 10);
} catch (PDOException $e) {
    error_log('Error admin panel: ' . $e->getMessage());
    $tipo = 'error';
    $mensaje = 'No se pudo cargar el panel de administrador.';
    $usersCount = 0;
    $uploadsCount = 0;
    $openTickets = 0;
    $users = [];
    $expenseReport = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador</title>
    <link rel="stylesheet" href="../css/index.css">
</head>

<body>
    <?php
    render_sticky_menu([
        'container_class' => 'sticky-home-menu',
        'inner_class' => 'sticky-home-menu-inner',
        'home_href' => 'home.php',
        'logout_href' => 'scripts/logout.php',
        'nav_items' => [
            ['href' => 'finanzas.php', 'label' => 'Finanzas'],
            ['href' => 'tickets.php', 'label' => 'Tickets'],
            ['href' => 'config.php', 'label' => 'Configuración'],
            ['href' => 'admin_panel.php', 'label' => 'Panel de administracion'],
            ['href' => 'superadmin_console.php', 'label' => 'Consola', 'min_role' => 'superadmin'],
        ],
    ]);
    ?>

    <div class="index-container">
        <h1>Panel de Administrador</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo htmlspecialchars($tipo); ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <h2>Métricas</h2>
        <p>Usuarios registrados: <?php echo $usersCount; ?></p>
        <p>Archivos subidos: <?php echo $uploadsCount; ?></p>
        <p>Tickets abiertos: <?php echo $openTickets; ?></p>

        <h2>Gestión de usuarios y roles</h2>
        <?php if (empty($users)): ?>
            <p>No hay usuarios para mostrar.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Correo</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Creado</th>
                        <th>Actualizar rol</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo (int)$user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['correo']); ?></td>
                            <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellidos']); ?></td>
                            <td><?php echo htmlspecialchars($user['role'] ?? 'user'); ?></td>
                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            <td>
                                <form method="POST" action="" style="display:inline">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int)$user['id']; ?>">
                                    <select name="new_role">
                                        <option value="user" <?php echo ($user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>user</option>
                                        <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>admin</option>
                                        <?php if (has_min_role('superadmin')): ?>
                                            <option value="superadmin" <?php echo ($user['role'] ?? '') === 'superadmin' ? 'selected' : ''; ?>>superadmin</option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="submit" name="actualizar_rol" value="1">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Reporte de gastos (últimos 30 días)</h2>
        <?php if (empty($expenseReport)): ?>
            <p>No hay datos de transacciones para generar reportes.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenseReport as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo number_format((float)$row['total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p>
            <a href="tickets.php">Gestionar tickets</a>
            <?php if (has_min_role('superadmin')): ?>
                | <a href="superadmin_console.php">Consola</a>
            <?php endif; ?>
            | <a href="home.php">Volver al inicio</a>
        </p>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>

</html>

