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

$logoutIconDataUri = 'data:image/png;base64,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';
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
    <header class="sticky-page-menu">
        <div class="sticky-page-menu-inner">
            <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
                <span class="icon-home" aria-hidden="true">⌂</span>
                <span>Inicio</span>
            </a>

            <nav class="sticky-links">
                <ul>
                    <li><a href="finanzas.php">Finanzas</a></li>
                    <li><a href="tickets.php">Tickets</a></li>
                    <li><a href="upload.php">Subir archivo</a></li>
                    <li><a href="mis_uploads.php">Mis archivos</a></li>
                    <li><a href="config.php">Configuración</a></li>
                </ul>
            </nav>

            <a class="menu-icon-btn logout-btn" href="logout.php" aria-label="Cerrar sesión">
                <img src="<?php echo htmlspecialchars($logoutIconDataUri); ?>" alt="Cerrar sesión" class="logout-icon">
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
</body>
</html>
