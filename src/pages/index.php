<?php
session_start();

require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

$mensaje_debug = '';
$mensaje_cookies = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cookies'])) {
    try {
        foreach ($_COOKIE as $cookieName => $cookieValue) {
            setcookie($cookieName, '', time() - 3600, '/');
            unset($_COOKIE[$cookieName]);
        }

        header('Location: index.php?cookies=cleared');
        exit();
    } catch (Exception $e) {
        $mensaje_cookies = 'No se pudieron eliminar las cookies.';
        error_log('Error al eliminar cookies en index: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug_guest'])) {
    try {
        $pdo = getPDO();
        if (function_exists('login_as_debug_guest')) {
            login_as_debug_guest($pdo);
            if (function_exists('record_audit_log')) {
                record_audit_log($pdo, 'debug_mode_enabled', 'warning', 'Activado desde index.php');
            }
            header('Location: index.php');
            exit();
        }
        $mensaje_debug = 'No se encontró la función de modo debug.';
    } catch (Exception $e) {
        $mensaje_debug = 'No se pudo activar el modo debug.';
        error_log('Error en debug guest index: ' . $e->getMessage());
    }
}

if (isset($_GET['cookies']) && $_GET['cookies'] === 'cleared') {
    $mensaje_cookies = 'Cookies eliminadas correctamente.';
}

$cookie_activa = !empty($_COOKIE['remember']);
$rol_sesion = strtolower(trim((string)($_SESSION['usuario_rol'] ?? '')));
$debug_activo = !empty($_SESSION['debug_mode']) || !empty($_SESSION['is_superadmin']) || $rol_sesion === 'superadmin';

$paginas_debug = [];
if ($debug_activo) {
    $archivos = glob(__DIR__ . '/*.php');
    if (is_array($archivos)) {
        foreach ($archivos as $archivo) {
            $nombre = basename($archivo);
            $requiere_parametro = in_array($nombre, ['download.php', 'delete_upload.php'], true);
            $paginas_debug[] = [
                'nombre' => $nombre,
                'requiere_parametro' => $requiere_parametro,
            ];
        }

        usort($paginas_debug, function ($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });
    }
}

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
        <?php if (!empty($mensaje_cookies)): ?>
            <p><?php echo htmlspecialchars($mensaje_cookies); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_debug)): ?>
            <p><?php echo htmlspecialchars($mensaje_debug); ?></p>
        <?php endif; ?>
        <p>Usuarios registrados: <?php echo (int)$total_users; ?></p>
        <p>Archivos subidos: <?php echo (int)$total_uploads; ?></p>
        <?php if ($cookie_activa): ?>
            <p><a href="home.php">Ir a Home</a></p>
        <?php else: ?>
            <p><a href="login.php">Iniciar sesión</a> | <a href="register.php">Registrarse</a></p>
        <?php endif; ?>
        <form method="POST" action="">
            <button type="submit" name="debug_guest" value="1" class="btn">Debug</button>
        </form>
        <form method="POST" action="">
            <button type="submit" name="clear_cookies" value="1" class="btn">Eliminar cookies</button>
        </form>

        <?php if ($debug_activo): ?>
            <h2>Enlaces Debug</h2>
            <ul>
                <?php foreach ($paginas_debug as $pagina): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($pagina['nombre']); ?>"><?php echo htmlspecialchars($pagina['nombre']); ?></a>
                        <?php if ($pagina['requiere_parametro']): ?>
                            (requiere parámetros)
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>
