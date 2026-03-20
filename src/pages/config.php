<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

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
        $stmt = $pdo->prepare('UPDATE finanzas SET currency = :currency WHERE user_id = :user_id');
        $stmt->execute(['currency' => $currency, 'user_id' => $_SESSION['usuario_id']]);
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
    $stmt = $pdo->prepare('SELECT currency FROM finanzas WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
    $conf = $stmt->fetch();
    $current_currency = $conf['currency'] ?? 'EUR';
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
    <header class="sticky-home-menu is-collapsed" data-sticky-menu data-icon-collapsed="../images/MostrarMenuDesplegable.PNG" data-icon-expanded="../images/OcultarMenuDesplegable.PNG">
        <div class="sticky-home-menu-inner">
            <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
                <img src="../images/Home.PNG" alt="Inicio" class="icon-home">
                <span>Inicio</span>
            </a>

            <a class="menu-icon-btn logout-btn" href="scripts/logout.php" aria-label="Cerrar sesión">
                <img src="../images/BotonLogOut.PNG" alt="Cerrar sesión" class="logout-icon">
                <span>Cerrar sesión</span>
            </a>

            <button type="button" class="menu-icon-btn menu-toggle-btn" data-menu-toggle aria-label="Mostrar menu desplegable" aria-expanded="false">
                <img src="../images/MostrarMenuDesplegable.PNG" alt="Mostrar menu desplegable" class="menu-toggle-icon" data-menu-toggle-icon>
            </button>

            <nav class="sticky-links">
                <ul>
                    <li><a href="finanzas.php">Finanzas</a></li>
                    <li><a href="tickets.php">Tickets</a></li>
                    <li><a href="config.php">Configuración</a></li>
                    <?php if (function_exists('has_min_role') && has_min_role('admin')): ?>
                        <li><a href="admin_panel.php">Panel de administracion</a></li>
                    <?php endif; ?>
                    <?php if (function_exists('has_min_role') && has_min_role('superadmin')): ?>
                        <li><a href="superadmin_console.php">Consola</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

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


