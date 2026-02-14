<?php
session_start();

require_once __DIR__ . '/../utils/db.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar el formulario cuando se envíe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y sanitizar los datos del formulario
    $correo = trim($_POST['correo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $edad = intval($_POST['edad'] ?? 0);
    $contraseña = $_POST['contraseña'] ?? '';
    $confirmar_contraseña = $_POST['confirmar_contraseña'] ?? '';

    // Validaciones
    $errores = [];

    if (empty($correo)) {
        $errores[] = "El correo es requerido";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo no es válido";
    }

    if (empty($nombre)) {
        $errores[] = "El nombre es requerido";
    }

    if (empty($apellidos)) {
        $errores[] = "Los apellidos son requeridos";
    }

    if ($edad < 13 || $edad > 120) {
        $errores[] = "La edad debe estar entre 13 y 120 años";
    }

    if (empty($contraseña)) {
        $errores[] = "La contraseña es requerida";
    } elseif (strlen($contraseña) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres";
    }

    if ($contraseña !== $confirmar_contraseña) {
        $errores[] = "Las contraseñas no coinciden";
    }

    if (empty($errores)) {
        try {
            $pdo = getPDO();

            // Verificar si el correo ya existe
            $stmt = $pdo->prepare('SELECT id FROM users WHERE correo = :correo');
            $stmt->execute(['correo' => $correo]);
            $usuario_existe = $stmt->fetch();

            if ($usuario_existe) {
                $tipo_mensaje = 'error';
                $mensaje = 'El correo ya está registrado';
            } else {
                // Encriptar la contraseña
                $contraseña_encriptada = password_hash($contraseña, PASSWORD_DEFAULT);

                // Insertar nuevo usuario
                $stmt = $pdo->prepare('
                    INSERT INTO users (correo, nombre, apellidos, edad, password)
                    VALUES (:correo, :nombre, :apellidos, :edad, :password)
                ');
                $stmt->execute([
                    'correo' => $correo,
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'edad' => $edad,
                    'password' => $contraseña_encriptada
                ]);

                $user_id = $pdo->lastInsertId();

                // Crear registro en finanzas (1:1)
                $stmt = $pdo->prepare('
                    INSERT INTO finanzas (user_id, balance, income, expenses, currency)
                    VALUES (:user_id, 0.00, 0.00, 0.00, :currency)
                ');
                $stmt->execute([
                    'user_id' => $user_id,
                    'currency' => 'EUR'
                ]);

                // Iniciar sesión automáticamente y crear cookie persistente
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $user_id;
                $_SESSION['usuario_correo'] = $correo;
                $_SESSION['usuario_nombre'] = $nombre;
                $_SESSION['usuario_apellidos'] = $apellidos;
                $_SESSION['usuario_edad'] = $edad;

                // Crear cookie persistente si el usuario marca 'recordarme' al registrarse
                $remember = !empty($_POST['remember']);
                if ($remember && function_exists('create_remember_token')) {
                    create_remember_token($pdo, (int)$user_id);
                }

                $tipo_mensaje = 'exito';
                $mensaje = 'Cuenta creada y sesión iniciada. Redirigiendo...';
                echo '<meta http-equiv="refresh" content="1.5;url=home.php">';
            }
        } catch (PDOException $e) {
            $tipo_mensaje = 'error';
            $mensaje = 'Error al registrar la cuenta. Intenta nuevamente.';
            error_log('Error en registro: ' . $e->getMessage());
        }
    } else {
        $tipo_mensaje = 'error';
        $mensaje = implode('<br>', $errores);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <link rel="stylesheet" href="../css/register.css">
</head>
<body>
    <div class="register-container">
        <h1>Crear Cuenta</h1>

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
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required
                       value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="apellidos">Apellidos:</label>
                <input type="text" id="apellidos" name="apellidos" required
                       value="<?php echo htmlspecialchars($_POST['apellidos'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="edad">Edad:</label>
                <input type="number" id="edad" name="edad" min="13" max="120" required
                       value="<?php echo htmlspecialchars($_POST['edad'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="contraseña">Contraseña:</label>
                <input type="password" id="contraseña" name="contraseña" required>
            </div>

            <div class="form-group">
                <label for="confirmar_contraseña">Confirmar Contraseña:</label>
                <input type="password" id="confirmar_contraseña" name="confirmar_contraseña" required>
            </div>

            <button type="submit" class="btn">Registrarse</button>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember" value="1" checked> Recordarme por 3 días
                </label>
            </div>
        </form>

        <p class="link-login">¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
    </div>
</body>
</html>
