<?php
require_once __DIR__ . '/includes/page_bootstrap.php';

$userId = require_authenticated_user('login.php');

// Only accessible via the button in config.php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $t = $_GET['t'] ?? '';
    if (!empty($t) && isset($_SESSION['perfil_token']) && hash_equals($_SESSION['perfil_token'], $t)) {
        $_SESSION['perfil_active'] = true;
    } elseif (empty($_SESSION['perfil_active'])) {
        header('Location: config.php');
        exit();
    }
} elseif (!isset($_SESSION['perfil_active'])) {
    header('Location: config.php');
    exit();
}

$mensaje = '';
$tipo = '';

try {
    $pdo = getPDO();
    $usuario = fetch_user_profile_by_id($pdo, $userId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $edad = intval($_POST['edad'] ?? 0);

        // Validaciones simples
        $errors = [];
        if (empty($nombre)) $errors[] = 'El nombre es requerido.';
        if (empty($apellidos)) $errors[] = 'Los apellidos son requeridos.';
        if ($edad < 13 || $edad > 120) $errors[] = 'Edad inválida.';

        if (empty($errors)) {
            update_user_profile($pdo, $userId, $nombre, $apellidos, $edad);
            $tipo = 'exito';
            $mensaje = 'Perfil actualizado.';

            // Actualizar sesión y variable usuario
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['usuario_apellidos'] = $apellidos;
            $_SESSION['usuario_edad'] = $edad;

            // Refrescar datos
            $usuario = fetch_user_profile_by_id($pdo, $userId);
        } else {
            $tipo = 'error';
            $mensaje = implode('<br>', $errors);
        }
    }
} catch (PDOException $e) {
    error_log('Error perfil: ' . $e->getMessage());
    $tipo = 'error';
    $mensaje = 'No se pudo obtener la información del perfil.';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil</title>
    <link rel="stylesheet" href="../css/perfil.css">
</head>
<body>
    <?php
    render_sticky_menu([
        'container_class' => 'sticky-page-menu',
        'inner_class' => 'sticky-page-menu-inner',
        'home_href' => 'home.php',
        'logout_href' => 'scripts/logout.php',
        'nav_items' => [
            ['href' => 'finanzas.php', 'label' => 'Finanzas'],
            ['href' => 'tickets.php', 'label' => 'Tickets'],
            ['href' => 'scripts/upload.php', 'label' => 'Subir archivo'],
            ['href' => 'mis_uploads.php', 'label' => 'Mis archivos'],
            ['href' => 'config.php', 'label' => 'Configuración'],
        ],
    ]);
    ?>

    <div class="perfil-container">
        <h1>Perfil de Usuario</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (!empty($usuario)): ?>
            <form method="POST" action="">
                <?php echo csrf_input_field(); ?>
                <div class="form-group">
                    <label>Correo:</label>
                    <div><?php echo htmlspecialchars($usuario['correo']); ?></div>
                </div>
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                </div>
                <div class="form-group">
                    <label for="apellidos">Apellidos:</label>
                    <input id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($usuario['apellidos']); ?>">
                </div>
                <div class="form-group">
                    <label for="edad">Edad:</label>
                    <input id="edad" name="edad" type="number" min="13" max="120" value="<?php echo htmlspecialchars($usuario['edad']); ?>">
                </div>
                <button class="btn" type="submit">Guardar</button>
                <a class="btn" href="config.php">Volver a Configuración</a>
            </form>
        <?php else: ?>
            <p>Usuario no encontrado.</p>
        <?php endif; ?>

    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>
</html>


