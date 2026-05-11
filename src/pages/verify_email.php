<?php
require_once __DIR__ . '/includes/auth_bootstrap.php';
require_once __DIR__ . '/includes/sticky_menu.php';
require_once __DIR__ . '/../utils/email_verification.php';

$mensaje = '';
$tipo_mensaje = 'error';
$token = trim((string)($_GET['token'] ?? ''));

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $mensaje = 'El enlace de verificacion no es valido.';
} else {
    try {
        $pdo = getPDO();
        $tokenHash = email_verification_token_hash($token);

        $stmt = $pdo->prepare('SELECT id, correo FROM users WHERE email_verification_token_hash = :token_hash AND email_verification_expires_at IS NOT NULL AND email_verification_expires_at > NOW() LIMIT 1');
        $stmt->execute(['token_hash' => $tokenHash]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $mensaje = 'El enlace de verificacion ha expirado o ya fue utilizado.';
        } else {
            $update = $pdo->prepare('UPDATE users SET email_verified_at = NOW(), email_verification_token_hash = NULL, email_verification_expires_at = NULL WHERE id = :id');
            $update->execute(['id' => $usuario['id']]);

            if (function_exists('record_audit_log')) {
                record_audit_log($pdo, 'email_verified', 'info', 'Correo verificado para ' . $usuario['correo'], (int)$usuario['id']);
            }

            $tipo_mensaje = 'success';
            $mensaje = 'Tu correo fue verificado correctamente. Ya puedes iniciar sesion.';
        }
    } catch (Exception $e) {
        $mensaje = 'No se pudo verificar el correo en este momento.';
        error_log('Error verificando correo: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificacion de Correo</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/login.css">
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
            ['href' => 'login.php', 'label' => 'Iniciar sesion'],
            ['href' => 'register.php', 'label' => 'Registrarse'],
        ],
    ]);
    ?>

    <div class="login-container auth-container">
        <h1>Verificacion de Correo</h1>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
        <p class="link-registro auth-helper-link"><a href="login.php">Ir a iniciar sesion</a></p>
    </div>
</body>

</html>
