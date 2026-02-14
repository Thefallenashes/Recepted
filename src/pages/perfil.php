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

        <p>
            <a href="home.php">Volver al inicio</a> |
            <a href="upload.php">Subir archivo</a> |
            <a href="mis_uploads.php">Mis archivos</a> |
            <a href="logout.php">Cerrar Sesión</a>
        </p>
    </div>
</body>
</html>
