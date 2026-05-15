<?php
require_once __DIR__ . '/includes/auth_bootstrap.php';
require_once __DIR__ . '/includes/sticky_menu.php';
require_once __DIR__ . '/../utils/email_verification.php';
require_once __DIR__ . '/../utils/schema.php';

$mensaje = '';
$tipo_mensaje = '';
$registro_completado = false;
$mensaje_enlace_verificacion = '';

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

    if ($edad < 18 || $edad > 120) {
        $errores[] = "La edad debe estar entre 18 y 120 años";
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
            ensure_application_schema($pdo);

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
                $verificationToken = generate_email_verification_token();
                $verificationTokenHash = email_verification_token_hash($verificationToken);
                $verificationExpiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

                $pdo->beginTransaction();

                // Insertar nuevo usuario
                $stmt = $pdo->prepare('
                    INSERT INTO users (correo, nombre, apellidos, edad, role, password, email_verified_at, email_verification_token_hash, email_verification_expires_at)
                    VALUES (:correo, :nombre, :apellidos, :edad, :role, :password, NULL, :email_verification_token_hash, :email_verification_expires_at)
                ');
                $stmt->execute([
                    'correo' => $correo,
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'edad' => $edad,
                    'role' => 'user',
                    'password' => $contraseña_encriptada,
                    'email_verification_token_hash' => $verificationTokenHash,
                    'email_verification_expires_at' => $verificationExpiresAt,
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

                $emailEnviado = send_account_verification_email($correo, $verificationToken);

                $pdo->commit();

                if (function_exists('record_audit_log')) {
                    record_audit_log($pdo, 'register_success', 'info', 'Cuenta creada desde formulario de registro. Verificacion de correo pendiente.');
                }

                if ($emailEnviado) {
                    $tipo_mensaje = 'exito';
                    $mensaje = 'Cuenta creada. Te enviamos un correo de verificacion. Revisa tu bandeja de entrada para activar tu cuenta.';
                } else {
                    $tipo_mensaje = 'error';
                    $mensaje = 'Cuenta creada, pero no se pudo enviar el correo automaticamente desde este entorno.';
                    $mensaje_enlace_verificacion = build_email_verification_link($verificationToken);
                    error_log('Registro con verificacion manual para: ' . $correo);
                }

                $registro_completado = true;
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $tipo_mensaje = 'error';
            $mensaje = 'Error al registrar la cuenta. Intenta nuevamente.';
            error_log('Error en registro: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $tipo_mensaje = 'error';
            $mensaje = $e->getMessage();
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
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/register.css">
</head>
<body>
    <?php
    render_sticky_menu([
        'container_class' => 'sticky-home-menu sticky-auth-menu',
        'inner_class' => 'sticky-home-menu-inner sticky-auth-menu-inner',
        'home_href' => 'landing.php',
        'home_label' => 'Inicio',
        'show_logout' => false,
        'nav_items' => [
            ['href' => 'login.php', 'label' => 'Iniciar sesión'],
        ],
    ]);
    ?>

    <div class="register-container auth-container">
        <h1>Crear Cuenta</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_enlace_verificacion)): ?>
            <div class="mensaje exito">
                <p>Usa este enlace para verificar manualmente tu cuenta:</p>
                <p><a href="<?php echo htmlspecialchars($mensaje_enlace_verificacion); ?>"><?php echo htmlspecialchars($mensaje_enlace_verificacion); ?></a></p>
            </div>
        <?php endif; ?>

        <?php if (!$registro_completado): ?>
            <form method="POST" action="" class="auth-form">
                <?php echo csrf_input_field(); ?>
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
                          <input type="number" id="edad" name="edad" min="18" max="120" required
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

                <button type="submit" class="btn auth-submit-btn">Registrarse</button>
            </form>
        <?php endif; ?>

        <p class="link-login auth-helper-link">¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
    </div>
</body>
</html>

