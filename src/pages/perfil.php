<?php
session_start();
require_once __DIR__ . '/../utils/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$mensaje = '';
$tipo = '';

try {
    $pdo = getPDO();
    // Obtener datos del usuario
    $stmt = $pdo->prepare('SELECT id, correo, nombre, apellidos, edad, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();

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
            $stmt = $pdo->prepare('UPDATE users SET nombre = :nombre, apellidos = :apellidos, edad = :edad WHERE id = :id');
            $stmt->execute(['nombre' => $nombre, 'apellidos' => $apellidos, 'edad' => $edad, 'id' => $_SESSION['usuario_id']]);
            $tipo = 'exito';
            $mensaje = 'Perfil actualizado.';

            // Actualizar sesión y variable usuario
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['usuario_apellidos'] = $apellidos;
            $_SESSION['usuario_edad'] = $edad;

            // Refrescar datos
            $stmt = $pdo->prepare('SELECT id, correo, nombre, apellidos, edad, created_at FROM users WHERE id = :id');
            $stmt->execute(['id' => $_SESSION['usuario_id']]);
            $usuario = $stmt->fetch();
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
    <header class="sticky-page-menu is-collapsed" data-sticky-menu data-icon-collapsed="../images/MostrarMenuDesplegable.PNG" data-icon-expanded="../images/OcultarMenuDesplegable.PNG">
        <div class="sticky-page-menu-inner">
            <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
                <img src="../images/Home.PNG" alt="Inicio" class="icon-home">
                <span>Inicio</span>
            </a>

            <button type="button" class="menu-icon-btn menu-toggle-btn" data-menu-toggle aria-label="Mostrar menu desplegable" aria-expanded="false">
                <img src="../images/MostrarMenuDesplegable.PNG" alt="Mostrar menu desplegable" class="menu-toggle-icon" data-menu-toggle-icon>
            </button>

            <nav class="sticky-links">
                <ul>
                    <li><a href="finanzas.php">Finanzas</a></li>
                    <li><a href="tickets.php">Tickets</a></li>
                    <li><a href="scripts/upload.php">Subir archivo</a></li>
                    <li><a href="mis_uploads.php">Mis archivos</a></li>
                    <li><a href="config.php">Configuración</a></li>
                </ul>
            </nav>

            <a class="menu-icon-btn logout-btn" href="logout.php" aria-label="Cerrar sesión">
                <img src="../images/BotonLogOut.PNG" alt="Cerrar sesión" class="logout-icon">
                <span>Cerrar sesión</span>
            </a>
        </div>
    </header>

    <div class="perfil-container">
        <h1>Perfil de Usuario</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (!empty($usuario)): ?>
            <form method="POST" action="">
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
            </form>
        <?php else: ?>
            <p>Usuario no encontrado.</p>
        <?php endif; ?>

    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>
</html>


