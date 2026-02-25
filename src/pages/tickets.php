<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$mensaje = '';
$tipo = '';

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['crear_ticket'])) {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = trim($_POST['priority'] ?? 'medium');
            if (!in_array($priority, ['low', 'medium', 'high'], true)) {
                $priority = 'medium';
            }

            if ($title === '' || $description === '') {
                $tipo = 'error';
                $mensaje = 'Debes completar título y descripción del ticket.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO support_tickets (created_by, title, description, priority, status) VALUES (:created_by, :title, :description, :priority, :status)');
                $stmt->execute([
                    'created_by' => $_SESSION['usuario_id'],
                    'title' => $title,
                    'description' => $description,
                    'priority' => $priority,
                    'status' => 'open'
                ]);

                record_audit_log($pdo, 'ticket_created', 'info', 'Nuevo ticket creado desde panel tickets');
                $tipo = 'exito';
                $mensaje = 'Ticket creado correctamente.';
            }
        }

        if (isset($_POST['actualizar_ticket']) && has_min_role('admin')) {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $status = trim($_POST['status'] ?? 'open');
            if (!in_array($status, ['open', 'in_progress', 'closed'], true)) {
                $status = 'open';
            }

            if ($ticketId > 0) {
                $stmt = $pdo->prepare('UPDATE support_tickets SET status = :status, assigned_to = :assigned_to WHERE id = :id');
                $stmt->execute([
                    'status' => $status,
                    'assigned_to' => $_SESSION['usuario_id'],
                    'id' => $ticketId,
                ]);
                record_audit_log($pdo, 'ticket_updated', 'info', 'Ticket #' . $ticketId . ' actualizado a estado ' . $status);
                $tipo = 'exito';
                $mensaje = 'Ticket actualizado.';
            }
        }
    }

    if (has_min_role('admin')) {
        $stmt = $pdo->query('SELECT t.*, u.correo AS created_by_email, a.correo AS assigned_to_email
            FROM support_tickets t
            JOIN users u ON u.id = t.created_by
            LEFT JOIN users a ON a.id = t.assigned_to
            ORDER BY t.updated_at DESC');
        $tickets = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare('SELECT t.*, u.correo AS created_by_email, a.correo AS assigned_to_email
            FROM support_tickets t
            JOIN users u ON u.id = t.created_by
            LEFT JOIN users a ON a.id = t.assigned_to
            WHERE t.created_by = :user_id
            ORDER BY t.updated_at DESC');
        $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
        $tickets = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Error tickets: ' . $e->getMessage());
    $tipo = 'error';
    $mensaje = 'No se pudo cargar el sistema de tickets.';
    $tickets = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets de Soporte</title>
    <link rel="stylesheet" href="../css/index.css">
</head>

<body>
    <div class="index-container">
        <h1>Tickets de Soporte</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo htmlspecialchars($tipo); ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <h2>Crear incidencia</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="title">Título</label>
                <input id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="description">Descripción</label>
                <textarea id="description" name="description" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <label for="priority">Prioridad</label>
                <select id="priority" name="priority">
                    <option value="low">Baja</option>
                    <option value="medium" selected>Media</option>
                    <option value="high">Alta</option>
                </select>
            </div>
            <button class="btn" type="submit" name="crear_ticket" value="1">Crear ticket</button>
        </form>

        <h2>Listado de tickets</h2>
        <?php if (empty($tickets)): ?>
            <p>No hay tickets para mostrar.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Creador</th>
                        <th>Asignado</th>
                        <?php if (has_min_role('admin')): ?>
                            <th>Acción</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><?php echo (int)$ticket['id']; ?></td>
                            <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['status']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['priority']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['created_by_email']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['assigned_to_email'] ?? '-'); ?></td>
                            <?php if (has_min_role('admin')): ?>
                                <td>
                                    <form method="POST" action="" style="display:inline">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                                        <select name="status">
                                            <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>open</option>
                                            <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>in_progress</option>
                                            <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>closed</option>
                                        </select>
                                        <button type="submit" name="actualizar_ticket" value="1">Guardar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><a href="home.php">Volver al inicio</a></p>
    </div>
</body>

</html>