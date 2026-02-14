<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

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
            $canEdit = role_level($myRole) > role_level($targetCurrentRole);

            if ($myRole === 'admin' && $newRole === 'superadmin') {
                $canEdit = false;
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

    $usersCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $uploadsCount = (int)$pdo->query('SELECT COUNT(*) FROM uploads')->fetchColumn();
    $openTickets = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status <> 'closed'")->fetchColumn();

    $users = $pdo->query('SELECT id, correo, nombre, apellidos, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();

    $reportStmt = $pdo->prepare("SELECT category, SUM(amount) AS total FROM transactions WHERE type = 'expense' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY category ORDER BY total DESC LIMIT 10");
    $reportStmt->execute();
    $expenseReport = $reportStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error admin panel: ' . $e->getMessage());
    $tipo = 'error';
    $mensaje = 'No se pudo cargar el panel de administración.';
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
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
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
                                        <option value="user">user</option>
                                        <option value="admin">admin</option>
                                        <?php if (has_min_role('superadmin')): ?>
                                            <option value="superadmin">superadmin</option>
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
                | <a href="superadmin_console.php">Consola superadmin</a>
            <?php endif; ?>
            | <a href="home.php">Volver al inicio</a>
        </p>
    </div>
</body>
</html>
