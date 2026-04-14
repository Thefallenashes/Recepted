<?php
require_once __DIR__ . '/includes/page_bootstrap.php';

require_min_role('superadmin', 'home.php');

function strip_leading_sql_comments(string $query): string
{
    $clean = ltrim($query);

    while (true) {
        $before = $clean;
        $clean = preg_replace('/\A\/\*.*?\*\//s', '', $clean);
        $clean = preg_replace('/\A--[^\r\n]*(\r\n|\r|\n)?/', '', $clean);
        $clean = preg_replace('/\A#[^\r\n]*(\r\n|\r|\n)?/', '', $clean);
        $clean = ltrim((string)$clean);

        if ($clean === $before) {
            break;
        }
    }

    return $clean;
}

function is_readonly_sql_allowed(string $query): bool
{
    $clean = strip_leading_sql_comments($query);
    if ($clean === '') {
        return false;
    }

    if (preg_match('/[;\x00]/', $clean)) {
        return false;
    }

    if (!preg_match('/\A(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $clean)) {
        return false;
    }

    if (preg_match('/\b(INTO\s+OUTFILE|INTO\s+DUMPFILE|LOAD_FILE\s*\(|INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|REPLACE|GRANT|REVOKE|SET|USE|CALL|DO|HANDLER|LOCK|UNLOCK)\b/i', $clean)) {
        return false;
    }

    return true;
}

function apply_readonly_limit(string $query, int $maxRows = 100): string
{
    $clean = trim($query);
    if (!preg_match('/\A(SELECT|SHOW)\b/i', strip_leading_sql_comments($clean))) {
        return $clean;
    }

    if (preg_match('/\bLIMIT\b/i', $clean)) {
        return $clean;
    }

    return $clean . ' LIMIT ' . max(1, $maxRows);
}

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
            record_audit_log($pdo, 'superadmin_mfa_verified', 'warning', 'Segundo factor validado en Consola');
            $tipo = 'exito';
            $mensaje = 'MFA validado correctamente.';
        } else {
            $tipo = 'error';
            $mensaje = 'Código MFA inválido o expirado.';
        }
    }

    if (!empty($_SESSION['mfa_superadmin_verified']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_query'])) {
        $query = trim($_POST['sql_query'] ?? '');

        if (!is_readonly_sql_allowed($query)) {
            $tipo = 'error';
            $mensaje = 'Consulta no permitida. Solo lectura sin comentarios de evasión ni multisentencias.';
        } else {
            $queryToRun = apply_readonly_limit($query, 100);
            $stmt = $pdo->query($queryToRun);
            $queryResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($queryResult)) {
                $queryColumns = array_keys($queryResult[0]);
            }
            record_audit_log($pdo, 'superadmin_sql_query', 'critical', 'Consulta de solo lectura ejecutada en consola SQL superadmin');
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
    <title>Consola</title>
    <link rel="stylesheet" href="../css/index.css">
</head>

<body>
    <?php
    render_sticky_menu([
        'container_class' => 'sticky-home-menu',
        'inner_class' => 'sticky-home-menu-inner',
        'home_href' => 'landing.php',
        'logout_href' => 'scripts/logout.php',
        'nav_items' => [
            ['href' => 'finanzas.php', 'label' => 'Finanzas'],
            ['href' => 'tickets.php', 'label' => 'Tickets'],
            ['href' => 'config.php', 'label' => 'Configuración'],
            ['href' => 'admin_panel.php', 'label' => 'Panel de administracion'],
        ],
    ]);
    ?>

    <div class="index-container">

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo htmlspecialchars($tipo); ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (empty($_SESSION['mfa_superadmin_verified'])): ?>
            <h2>Autenticación multifactor (MFA)</h2>
            <p>Introduce el código MFA para desbloquear la consola.</p>
            <p><strong>Código MFA demo:</strong> <?php echo htmlspecialchars((string)($_SESSION['mfa_superadmin_code'] ?? '')); ?> (expira en 5 minutos)</p>
            <form method="POST" action="">
                <?php echo csrf_input_field(); ?>
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
                    <thead>
                        <tr>
                            <th>Nivel</th>
                            <th>Total</th>
                        </tr>
                    </thead>
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
                <?php echo csrf_input_field(); ?>
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
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>Nivel</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
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

            <p><a href="admin_panel.php">Panel de administrador</a> | <a href="home.php">Volver al inicio</a></p>
        <?php endif; ?>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>

</html>

