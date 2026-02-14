<?php
session_start();

require_once __DIR__ . '/../utils/db.php';

$mensaje = '';
$tipo_mensaje = '';

// Si el usuario ya está autenticado, redirigir al home
if (isset($_SESSION['usuario_id'])) {
    header('Location: home.php');
    exit();
}

// Procesar el formulario cuando se envíe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $contraseña = $_POST['contraseña'] ?? '';

    // Validaciones
    if (empty($correo)) {
        $tipo_mensaje = 'error';
        $mensaje = 'El correo es requerido';
    } elseif (empty($contraseña)) {
        $tipo_mensaje = 'error';
        $mensaje = 'La contraseña es requerida';
    } else {
        try {
            $pdo = getPDO();

            // Buscar usuario por correo
            $stmt = $pdo->prepare('SELECT * FROM users WHERE correo = :correo');
            $stmt->execute(['correo' => $correo]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($contraseña, $usuario['password'])) {
                // Inicio de sesión exitoso
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_correo'] = $usuario['correo'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_apellidos'] = $usuario['apellidos'];
                $_SESSION['usuario_edad'] = $usuario['edad'];

                // Crear token persistente (cookie) para recordar al usuario 3 días si pidió recordarme
                $remember = !empty($_POST['remember']);
                if ($remember && function_exists('create_remember_token')) {
                    create_remember_token($pdo, (int)$usuario['id']);
                }

                $tipo_mensaje = 'exito';
                $mensaje = 'Sesión iniciada correctamente. Redirigiendo...';
                echo '<meta http-equiv="refresh" content="1.5;url=home.php">';
            } else {
                $tipo_mensaje = 'error';
                $mensaje = 'Correo o contraseña incorrectos';
            }
        } catch (PDOException $e) {
            $tipo_mensaje = 'error';
            $mensaje = 'Error al conectar con la base de datos. Intenta nuevamente.';
            error_log('Error en login: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="login-container">
        <h1>Iniciar Sesión</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="correo">Correo Electrónico:</label>
                <input type="email" id="correo" name="correo" required
                       value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="contraseña">Contraseña:</label>
                <input type="password" id="contraseña" name="contraseña" required>
            </div>

            <button type="submit" class="btn">Iniciar Sesión</button>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember" value="1" checked> Recordarme por 3 días
                </label>
            </div>
        </form>

        <p class="link-registro">¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
    </div>
</body>
</html>
