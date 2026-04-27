<?php
require_once __DIR__ . '/includes/page_bootstrap.php';
require_once __DIR__ . '/includes/debug_helpers.php';

$userId = require_authenticated_user('landing.php');

$mensaje_cookies = handle_clear_cookies_request('index.php') ?: resolve_cookies_cleared_message();
$mensaje_debug   = handle_debug_mode_request('index.php');

$cookie_activa = !empty($_COOKIE['remember']);
$rol_sesion    = strtolower(trim((string)($_SESSION['usuario_rol'] ?? '')));
$debug_activo  = !empty($_SESSION['debug_mode']) || !empty($_SESSION['is_superadmin']) || $rol_sesion === 'superadmin';

$paginas_debug = build_debug_pages_list(
    $debug_activo,
    __DIR__,
    ['index.php', 'landing.php', 'login.php', 'perfil.php', 'register.php']
);

// Página pública: mostrar estadísticas básicas
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
    <title>Índice Interno</title>
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
            ['href' => 'home.php', 'label' => 'Panel de control'],
            ['href' => 'finanzas.php', 'label' => 'Finanzas'],
            ['href' => 'tickets.php', 'label' => 'Tickets'],
            ['href' => 'admin_panel.php', 'label' => 'Panel de administracion', 'min_role' => 'admin'],
            ['href' => 'superadmin_console.php', 'label' => 'Consola', 'min_role' => 'superadmin'],
            ['href' => 'config.php', 'label' => 'Configuración'],
        ],
    ]);
    ?>

    <div class="index-container">
        <?php if (!empty($mensaje_cookies)): ?>
            <p><?php echo htmlspecialchars($mensaje_cookies); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_debug)): ?>
            <p><?php echo htmlspecialchars($mensaje_debug); ?></p>
        <?php endif; ?>
        <p>Usuarios registrados: <?php echo (int)$total_users; ?></p>
        <p>Archivos subidos: <?php echo (int)$total_uploads; ?></p>
        <form method="POST" action="">
            <?php echo csrf_input_field(); ?>
            <button type="submit" name="debug" value="1" class="btn">Modo de desarollo</button>
        </form>
        <form method="POST" action="">
            <?php echo csrf_input_field(); ?>
            <button type="submit" name="clear_cookies" value="1" class="btn">Borrar cookies</button>
        </form>

        <?php if ($debug_activo): ?>
            <h2>Enlaces</h2>
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


