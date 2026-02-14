<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

require_min_role('superadmin', 'home.php');

$mensaje = '';
$tipo = '';
$queryResult = [];
$queryColumns = [];

try {
    $pdo = getPDO();

    if (empty($_SESSION['mfa_superadmin_expires']) || time() > (int)$_SESSION['mfa_superadmin_expires']) {
        $_SESSION['mfa_superadmin_code'] = (string)random_int(100000, 999999);
        $_SESSION['mfa_superadmin_expires'] = time() + 300;
        $_SESSION['mfa_superadmin_verified'] = false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_mfa'])) {
        $mfaInput = trim($_POST['mfa_code'] ?? '');
        $expected = (string)($_SESSION['mfa_superadmin_code'] ?? '');
        $expires = (int)($_SESSION['mfa_superadmin_expires'] ?? 0);

        if ($mfaInput !== '' && hash_equals($expected, $mfaInput) && time() <= $expires) {
            $_SESSION['mfa_superadmin_verified'] = true;
            record_audit_log($pdo, 'superadmin_mfa_verified', 'warning', 'Segundo factor validado en consola superadmin');
            $tipo = 'exito';
            $mensaje = 'MFA validado correctamente.';
        } else {
            $tipo = 'error';
            $mensaje = 'Código MFA inválido o expirado.';
        }
    }

    if (!empty($_SESSION['mfa_superadmin_verified']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_query'])) {
        $query = trim($_POST['sql_query'] ?? '');
        $upper = strtoupper($query);

        $allowed = str_starts_with($upper, 'SELECT') || str_starts_with($upper, 'SHOW') || str_starts_with($upper, 'DESCRIBE');
        if (!$allowed) {
            $tipo = 'error';
            $mensaje = 'Solo se permiten consultas SELECT, SHOW o DESCRIBE.';
        } else {
            $stmt = $pdo->query($query . ' LIMIT 100');
            $queryResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($queryResult)) {
                $queryColumns = array_keys($queryResult[0]);
            }
            record_audit_log($pdo, 'superadmin_sql_query', 'critical', 'Consulta ejecutada en consola SQL superadmin');
            $tipo = 'exito';
            $mensaje = 'Consulta ejecutada.';
        }
    }

    $logCounts = $pdo->query("SELECT level, COUNT(*) AS total FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY level")->fetchAll();
    $recentLogs = $pdo->query('SELECT created_at, action, level, details FROM audit_logs ORDER BY created_at DESC LIMIT 20')->fetchAll();
} catch (PDOException $e) {
    error_log('Error superadmin console: ' . $e->getMessage());
    $tipo = 'error';
    $mensaje = 'No se pudo cargar la consola de superadministración.';
    $logCounts = [];
    $recentLogs = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consola Superadmin</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
    <div class="index-container">
        <h1>Consola de Super-Administrador</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo htmlspecialchars($tipo); ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (empty($_SESSION['mfa_superadmin_verified'])): ?>
            <h2>Autenticación multifactor (MFA)</h2>
            <p>Introduce el código MFA para desbloquear la consola.</p>
            <p><strong>Código MFA demo:</strong> <?php echo htmlspecialchars((string)($_SESSION['mfa_superadmin_code'] ?? '')); ?> (expira en 5 minutos)</p>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="mfa_code">Código MFA</label>
                    <input id="mfa_code" name="mfa_code" required>
                </div>
                <button class="btn" type="submit" name="verify_mfa" value="1">Validar MFA</button>
            </form>
            <p><a href="home.php">Volver al inicio</a></p>
        <?php else: ?>
            <h2>Métricas de seguridad y cumplimiento (24h)</h2>
            <?php if (empty($logCounts)): ?>
                <p>Sin eventos recientes en auditoría.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Nivel</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($logCounts as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['level']); ?></td>
                                <td><?php echo (int)$row['total']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Acceso directo MySQL (solo lectura)</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="sql_query">Consulta SQL</label>
                    <textarea id="sql_query" name="sql_query" rows="4" placeholder="SELECT * FROM users" required><?php echo htmlspecialchars($_POST['sql_query'] ?? ''); ?></textarea>
                </div>
                <button class="btn" type="submit" name="run_query" value="1">Ejecutar</button>
            </form>

            <?php if (!empty($queryColumns)): ?>
                <h3>Resultado</h3>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($queryColumns as $column): ?>
                                <th><?php echo htmlspecialchars($column); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queryResult as $row): ?>
                            <tr>
                                <?php foreach ($queryColumns as $column): ?>
                                    <td><?php echo htmlspecialchars((string)($row[$column] ?? '')); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Logs recientes</h2>
            <?php if (empty($recentLogs)): ?>
                <p>No hay logs recientes.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Fecha</th><th>Acción</th><th>Nivel</th><th>Detalle</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['level']); ?></td>
                                <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p><a href="admin_panel.php">Panel admin</a> | <a href="home.php">Volver al inicio</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
