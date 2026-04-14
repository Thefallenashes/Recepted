<?php
require_once __DIR__ . '/includes/page_bootstrap.php';

$mensaje_debug = '';
$mensaje_cookies = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cookies'])) {
    try {
        foreach ($_COOKIE as $cookieName => $cookieValue) {
            setcookie($cookieName, '', time() - 3600, '/');
            unset($_COOKIE[$cookieName]);
        }

        header('Location: landing.php?cookies=cleared');
        exit();
    } catch (Exception $e) {
        $mensaje_cookies = 'No se pudieron eliminar las cookies.';
        error_log('Error al borrar cookies en landing: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug'])) {
    try {
        $pdo = getPDO();
        if (function_exists('login_as_debug')) {
            login_as_debug($pdo);
            if (function_exists('record_audit_log')) {
                record_audit_log($pdo, 'debug_mode_enabled', 'warning', 'Activado desde landing.php');
            }
            header('Location: index.php');
            exit();
        }
        $mensaje_debug = 'No se encontró la función de modo debug.';
    } catch (Exception $e) {
        $mensaje_debug = 'No se pudo activar el modo debug.';
        error_log('Error en debug guest landing: ' . $e->getMessage());
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
            $requiere_parametro = in_array($nombre, ['scripts/download.php', 'scripts/delete_upload.php'], true);
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

try {
    $pdo = getPDO();
    $total_users = fetch_total_users($pdo);
    $total_uploads = fetch_total_uploads($pdo);
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
    <title>Landing</title>
    <link rel="stylesheet" href="../css/index.css">
</head>

<body>
    <?php
    $landingNavItems = [];
    if ($cookie_activa || isset($_SESSION['usuario_id'])) {
        $landingNavItems[] = ['href' => 'home.php', 'label' => 'Panel de usuario'];
    } else {
        $landingNavItems[] = ['href' => 'login.php', 'label' => 'Iniciar sesión'];
        $landingNavItems[] = ['href' => 'register.php', 'label' => 'Registrarse'];
    }

    render_sticky_menu([
        'container_class' => 'sticky-home-menu',
        'inner_class' => 'sticky-home-menu-inner',
        'home_href' => 'landing.php',
        'show_logout' => isset($_SESSION['usuario_id']),
        'logout_href' => 'scripts/logout.php',
        'nav_items' => $landingNavItems,
    ]);
    ?>

    <div class="index-container">
        <?php if (!empty($mensaje_cookies)): ?>
            <p><?php echo htmlspecialchars($mensaje_cookies); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_debug)): ?>
            <p><?php echo htmlspecialchars($mensaje_debug); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_input_field(); ?>
            <button type="submit" name="debug" value="1" class="btn">Modo de desarollo</button>
        </form>
        <form method="POST" action="">
            <?php echo csrf_input_field(); ?>
            <button type="submit" name="clear_cookies" value="1" class="btn">Borrar cookies</button>
        </form>

        <?php if ($debug_activo): ?>
            <h2>Enlaces Modo de desarollo</h2>
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
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>

</html>

