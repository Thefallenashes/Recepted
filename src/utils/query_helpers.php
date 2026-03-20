<?php

if (!function_exists('fetch_user_by_id')) {
    /**
     * @return array<string, mixed>|false
     */
    function fetch_user_by_id(PDO $pdo, int $userId)
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch();
    }
}

if (!function_exists('fetch_user_profile_by_id')) {
    /**
     * @return array<string, mixed>|false
     */
    function fetch_user_profile_by_id(PDO $pdo, int $userId)
    {
        $stmt = $pdo->prepare('SELECT id, correo, nombre, apellidos, edad, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch();
    }
}

if (!function_exists('update_user_profile')) {
    function update_user_profile(PDO $pdo, int $userId, string $nombre, string $apellidos, int $edad): void
    {
        $stmt = $pdo->prepare('UPDATE users SET nombre = :nombre, apellidos = :apellidos, edad = :edad WHERE id = :id');
        $stmt->execute([
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'edad' => $edad,
            'id' => $userId,
        ]);
    }
}

if (!function_exists('fetch_user_finanzas')) {
    /**
     * @return array<string, mixed>|false
     */
    function fetch_user_finanzas(PDO $pdo, int $userId)
    {
        $stmt = $pdo->prepare('SELECT * FROM finanzas WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }
}

if (!function_exists('fetch_recent_user_transactions')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function fetch_recent_user_transactions(PDO $pdo, int $userId, int $limit = 5): array
    {
        $limit = max(1, $limit);
        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT ' . $limit);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('update_user_currency')) {
    function update_user_currency(PDO $pdo, int $userId, string $currency): void
    {
        $stmt = $pdo->prepare('UPDATE finanzas SET currency = :currency WHERE user_id = :user_id');
        $stmt->execute(['currency' => $currency, 'user_id' => $userId]);
    }
}

if (!function_exists('fetch_user_currency')) {
    function fetch_user_currency(PDO $pdo, int $userId, string $default = 'EUR'): string
    {
        $stmt = $pdo->prepare('SELECT currency FROM finanzas WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $conf = $stmt->fetch();
        return (string)($conf['currency'] ?? $default);
    }
}

if (!function_exists('fetch_uploads_visible_for_user')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function fetch_uploads_visible_for_user(PDO $pdo, int $userId): array
    {
        $isAdmin = function_exists('can_manage_all_resources') && can_manage_all_resources();
        if ($isAdmin) {
            $stmt = $pdo->query('SELECT * FROM uploads ORDER BY uploaded_at DESC');
            return $stmt->fetchAll();
        }

        $stmt = $pdo->prepare('SELECT * FROM uploads WHERE user_id = :user_id ORDER BY uploaded_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('fetch_total_users')) {
    function fetch_total_users(PDO $pdo): int
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}

if (!function_exists('fetch_total_uploads')) {
    function fetch_total_uploads(PDO $pdo): int
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM uploads')->fetchColumn();
    }
}

if (!function_exists('fetch_open_tickets_count')) {
    function fetch_open_tickets_count(PDO $pdo): int
    {
        return (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status <> 'closed'")->fetchColumn();
    }
}

if (!function_exists('fetch_admin_users_list')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function fetch_admin_users_list(PDO $pdo): array
    {
        return $pdo->query('SELECT id, correo, nombre, apellidos, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
    }
}

if (!function_exists('fetch_expense_report_last_days')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function fetch_expense_report_last_days(PDO $pdo, int $days = 30, int $limit = 10): array
    {
        $days = max(1, $days);
        $limit = max(1, $limit);
        $sql = "SELECT category, SUM(amount) AS total FROM transactions WHERE type = 'expense' AND created_at >= DATE_SUB(NOW(), INTERVAL " . $days . " DAY) GROUP BY category ORDER BY total DESC LIMIT " . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
