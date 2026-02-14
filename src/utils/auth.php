<?php
// Funciones para manejo de "remember me" (cookies persistentes)
// No requiere automáticamente la conexión; recibe un PDO como parámetro.

function set_secure_cookie(string $name, string $value, int $expire)
{
    $params = [
        'expires' => $expire,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    if (PHP_VERSION_ID < 70300) {
        setcookie($name, $value, $expire, '/', '', $params['secure'], $params['httponly']);
    } else {
        setcookie($name, $value, $params);
    }
}

function create_remember_token(PDO $pdo, int $user_id): void
{
    // Generar selector y validator
    $selector = bin2hex(random_bytes(18)); // 36 hex chars
    $validator = bin2hex(random_bytes(32)); // 64 hex chars
    $token_hash = hash('sha256', $validator);
    $expires_at = (new DateTime('+3 days'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at) VALUES (:user_id, :selector, :token_hash, :expires_at)');
    $stmt->execute([
        'user_id' => $user_id,
        'selector' => $selector,
        'token_hash' => $token_hash,
        'expires_at' => $expires_at
    ]);

    $cookie_value = $selector . ':' . $validator;
    $expire_time = time() + 3 * 24 * 60 * 60;
    set_secure_cookie('remember', $cookie_value, $expire_time);

    // Limpiar tokens expirados ocasionalmente
    try {
        $pdo->exec("DELETE FROM auth_tokens WHERE expires_at < NOW()");
    } catch (Exception $e) {
        // noop
    }
}

function login_from_remember_cookie(PDO $pdo): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (!empty($_SESSION['usuario_id'])) {
        return;
    }

    if (empty($_COOKIE['remember'])) {
        return;
    }

    $parts = explode(':', $_COOKIE['remember']);
    if (count($parts) !== 2) {
        return;
    }

    $selector = preg_replace('/[^a-f0-9]/', '', $parts[0]);
    $validator = $parts[1];

    if (empty($selector) || empty($validator)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM auth_tokens WHERE selector = :selector AND expires_at > NOW()');
        $stmt->execute(['selector' => $selector]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$token) {
            // Token no existe o expiró
            return;
        }

        if (!hash_equals($token['token_hash'], hash('sha256', $validator))) {
            // Token inválido: posible robo. Borrar este selector.
            $del = $pdo->prepare('DELETE FROM auth_tokens WHERE id = :id');
            $del->execute(['id' => $token['id']]);
            set_secure_cookie('remember', '', time() - 3600);
            return;
        }

        // Token válido: iniciar sesión
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $token['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return;
        }

        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_correo'] = $user['correo'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_apellidos'] = $user['apellidos'];
        $_SESSION['usuario_edad'] = $user['edad'];

        // Rotar token: eliminar el antiguo y crear uno nuevo
        $del = $pdo->prepare('DELETE FROM auth_tokens WHERE id = :id');
        $del->execute(['id' => $token['id']]);
        create_remember_token($pdo, (int)$user['id']);
    } catch (Exception $e) {
        // no-op
    }
}

function clear_remember_tokens(PDO $pdo, ?int $user_id = null): void
{
    try {
        if ($user_id !== null) {
            $stmt = $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $user_id]);
        } elseif (!empty($_COOKIE['remember'])) {
            $parts = explode(':', $_COOKIE['remember']);
            if (count($parts) === 2) {
                $selector = preg_replace('/[^a-f0-9]/', '', $parts[0]);
                if (!empty($selector)) {
                    $stmt = $pdo->prepare('DELETE FROM auth_tokens WHERE selector = :selector');
                    $stmt->execute(['selector' => $selector]);
                }
            }
        }
    } catch (Exception $e) {
        // noop
    }

    set_secure_cookie('remember', '', time() - 3600);
}

?>
