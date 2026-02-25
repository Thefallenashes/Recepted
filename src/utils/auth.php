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

function normalize_user_role(?string $role): string
{
    $normalized = strtolower(trim((string)$role));
    if (!in_array($normalized, ['user', 'admin', 'superadmin'], true)) {
        return 'user';
    }
    return $normalized;
}

function role_level(string $role): int
{
    $normalized = normalize_user_role($role);
    if ($normalized === 'superadmin') {
        return 3;
    }
    if ($normalized === 'admin') {
        return 2;
    }
    return 1;
}

function has_min_role(string $minRole): bool
{
    $currentRole = normalize_user_role($_SESSION['usuario_rol'] ?? 'user');
    return role_level($currentRole) >= role_level($minRole);
}

function require_min_role(string $minRole, string $redirect = 'home.php'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (empty($_SESSION['usuario_id']) || !has_min_role($minRole)) {
        header('Location: ' . $redirect);
        exit();
    }
}

function record_audit_log(PDO $pdo, string $action, string $level = 'info', ?string $details = null, ?int $targetUserId = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, role, action, level, details, target_user_id, ip_address, user_agent) VALUES (:user_id, :role, :action, :level, :details, :target_user_id, :ip_address, :user_agent)');
        $stmt->execute([
            'user_id' => $_SESSION['usuario_id'] ?? null,
            'role' => normalize_user_role($_SESSION['usuario_rol'] ?? 'user'),
            'action' => $action,
            'level' => $level,
            'details' => $details,
            'target_user_id' => $targetUserId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Exception $e) {
        // noop
    }
}

function hydrate_user_session(array $user, bool $isGuest = false): void
{
    $role = normalize_user_role($user['role'] ?? 'user');

    $_SESSION['usuario_id'] = $user['id'];
    $_SESSION['usuario_correo'] = $user['correo'];
    $_SESSION['usuario_nombre'] = $user['nombre'];
    $_SESSION['usuario_apellidos'] = $user['apellidos'];
    $_SESSION['usuario_edad'] = $user['edad'];
    $_SESSION['usuario_rol'] = $role;
    $_SESSION['is_admin'] = in_array($role, ['admin', 'superadmin'], true);
    $_SESSION['is_superadmin'] = ($role === 'superadmin');
    $_SESSION['is_guest'] = $isGuest;
    $_SESSION['debug_mode'] = $isGuest;
}

function is_admin_user(): bool
{
    return !empty($_SESSION['is_admin']) || in_array(($_SESSION['usuario_rol'] ?? 'user'), ['admin', 'superadmin'], true);
}

function is_superadmin_user(): bool
{
    return !empty($_SESSION['is_superadmin']) || (($_SESSION['usuario_rol'] ?? 'user') === 'superadmin');
}

function can_manage_all_resources(): bool
{
    return is_admin_user();
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

        hydrate_user_session($user, false);

        // Rotar token: eliminar el antiguo y crear uno nuevo
        $del = $pdo->prepare('DELETE FROM auth_tokens WHERE id = :id');
        $del->execute(['id' => $token['id']]);
        create_remember_token($pdo, (int)$user['id']);
    } catch (Exception $e) {
        // no-op
    }
}

function login_as_debug(PDO $pdo): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $guest_email = 'invitado.debug@local';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE correo = :correo LIMIT 1');
    $stmt->execute(['correo' => $guest_email]);
    $guest_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guest_user) {
        $random_password = bin2hex(random_bytes(16));
        $password_hash = password_hash($random_password, PASSWORD_DEFAULT);

        try {
            $insert_user = $pdo->prepare('INSERT INTO users (correo, nombre, apellidos, edad, role, password) VALUES (:correo, :nombre, :apellidos, :edad, :role, :password)');
            $insert_user->execute([
                'correo' => $guest_email,
                'nombre' => 'Invitado',
                'apellidos' => 'Debug',
                'edad' => 30,
                'role' => 'superadmin',
                'password' => $password_hash
            ]);
        } catch (PDOException $e) {
            $insert_user = $pdo->prepare('INSERT INTO users (correo, nombre, apellidos, edad, password) VALUES (:correo, :nombre, :apellidos, :edad, :password)');
            $insert_user->execute([
                'correo' => $guest_email,
                'nombre' => 'Invitado',
                'apellidos' => 'Debug',
                'edad' => 30,
                'password' => $password_hash
            ]);
        }

        $guest_id = (int)$pdo->lastInsertId();

        $insert_finanzas = $pdo->prepare('INSERT INTO finanzas (user_id, balance, income, expenses, currency) VALUES (:user_id, 0.00, 0.00, 0.00, :currency)');
        $insert_finanzas->execute([
            'user_id' => $guest_id,
            'currency' => 'EUR'
        ]);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $guest_id]);
        $guest_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $role = normalize_user_role($guest_user['role'] ?? 'user');
        if ($role !== 'superadmin') {
            try {
                $updateRole = $pdo->prepare("UPDATE users SET role = 'superadmin' WHERE id = :id");
                $updateRole->execute(['id' => $guest_user['id']]);
            } catch (PDOException $e) {
                // Si no existe columna role, mantenemos permisos por sesión debug.
            }
            $guest_user['role'] = 'superadmin';
        }
    }

    if (!$guest_user) {
        throw new RuntimeException('No se pudo crear la cuenta invitada debug');
    }

    session_regenerate_id(true);
    hydrate_user_session($guest_user, true);
    $_SESSION['usuario_rol'] = 'superadmin';
    $_SESSION['is_admin'] = true;
    $_SESSION['is_superadmin'] = true;
    $_SESSION['debug_mode'] = true;
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
