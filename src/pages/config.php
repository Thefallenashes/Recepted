<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/query_helpers.php';
require_once __DIR__ . '/includes/sticky_menu.php';

// For now basic config page with session check
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Generate one-time access token for perfil.php and clear any active perfil session
$perfil_token = bin2hex(random_bytes(16));
$_SESSION['perfil_token'] = $perfil_token;
unset($_SESSION['perfil_active']);

$mensaje = '';
$tipo = '';

// Example: update user preferred currency (stored in finanzas.currency)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currency = trim($_POST['currency'] ?? 'EUR');
    try {
        $pdo = getPDO();
        update_user_currency($pdo, (int)$_SESSION['usuario_id'], $currency);
        $tipo = 'exito';
        $mensaje = 'Configuración guardada.';
    } catch (PDOException $e) {
        error_log('Error config save: ' . $e->getMessage());
        $tipo = 'error';
        $mensaje = 'No se pudo guardar la configuración.';
    }
}

// Obtener configuración actual
try {
    $pdo = getPDO();
    $current_currency = fetch_user_currency($pdo, (int)$_SESSION['usuario_id'], 'EUR');
} catch (PDOException $e) {
    $current_currency = 'EUR';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/config.css">
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
            ['href' => 'admin_panel.php', 'label' => 'Panel de administracion', 'min_role' => 'admin'],
            ['href' => 'superadmin_console.php', 'label' => 'Consola', 'min_role' => 'superadmin'],
        ],
    ]);
    ?>

    <div class="config-container">
        <h1>Configuración</h1>
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="currency">Moneda preferida:</label>
                <input id="currency" name="currency" value="<?php echo htmlspecialchars($current_currency); ?>">
            </div>
            <button class="btn" type="submit">Guardar</button>
        </form>

        <div class="config-section">
            <h2>Información de la cuenta</h2>
            <p>Actualiza tu nombre, apellidos y edad.</p>
            <a class="btn" href="perfil.php?t=<?php echo htmlspecialchars($perfil_token); ?>">Actualizar informacion de la cuenta</a>
        </div>

        <p><a href="home.php">Volver al inicio</a></p>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>
</html>


