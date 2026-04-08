<?php

if (!function_exists('get_current_database_name')) {
    function get_current_database_name(PDO $pdo): string
    {
        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName !== '') {
            return $dbName;
        }

        return (string)(getenv('DB_NAME') ?: 'tfg_db');
    }
}

if (!function_exists('schema_table_exists')) {
    function schema_table_exists(PDO $pdo, string $tableName, ?string $dbName = null): bool
    {
        $database = $dbName ?: get_current_database_name($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :table_name');
        $stmt->execute([
            'db' => $database,
            'table_name' => $tableName,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('schema_column_exists')) {
    function schema_column_exists(PDO $pdo, string $tableName, string $columnName, ?string $dbName = null): bool
    {
        $database = $dbName ?: get_current_database_name($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :db AND table_name = :table_name AND column_name = :column_name');
        $stmt->execute([
            'db' => $database,
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('schema_index_exists')) {
    function schema_index_exists(PDO $pdo, string $tableName, string $indexName, ?string $dbName = null): bool
    {
        $database = $dbName ?: get_current_database_name($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = :db AND table_name = :table_name AND index_name = :index_name');
        $stmt->execute([
            'db' => $database,
            'table_name' => $tableName,
            'index_name' => $indexName,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('ensure_application_schema')) {
    function ensure_application_schema(PDO $pdo): void
    {
        $createAuthTokens = <<<'SQL'
CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `selector` CHAR(36) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_selector` (`selector`),
  INDEX `idx_user_id` (`user_id`),
  CONSTRAINT `fk_auth_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $createAuditLogs = <<<'SQL'
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `target_user_id` BIGINT UNSIGNED NULL,
  `role` ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user',
  `action` VARCHAR(120) NOT NULL,
  `level` ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info',
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_target_user` (`target_user_id`),
  INDEX `idx_audit_action` (`action`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $createSupportTickets = <<<'SQL'
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `assigned_to` BIGINT UNSIGNED NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `priority` ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ticket_created_by` (`created_by`),
  INDEX `idx_ticket_assigned_to` (`assigned_to`),
  INDEX `idx_ticket_status` (`status`),
  CONSTRAINT `fk_ticket_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ticket_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $createExpenseCategories = <<<'SQL'
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('income','expense','mixed') NOT NULL DEFAULT 'mixed',
  `name` VARCHAR(80) NOT NULL,
  `color` VARCHAR(7) NOT NULL DEFAULT '#4CAF50',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_categories_user` (`user_id`),
  INDEX `idx_categories_type` (`type`),
  UNIQUE KEY `unique_user_category` (`user_id`, `type`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $createTransactions = <<<'SQL'
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('income','expense') NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `category_id` BIGINT UNSIGNED NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_transactions_user` (`user_id`),
  INDEX `idx_transactions_category` (`category`),
  INDEX `idx_transactions_category_id` (`category_id`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $createSavingsGoals = <<<'SQL'
CREATE TABLE IF NOT EXISTS `savings_goals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `target_amount` DECIMAL(14,2) NOT NULL,
  `current_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `target_date` DATE NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_goals_user` (`user_id`),
  CONSTRAINT `fk_goals_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $pdo->exec($createAuthTokens);
        $pdo->exec($createAuditLogs);
        $pdo->exec($createSupportTickets);
        $pdo->exec($createExpenseCategories);
        $pdo->exec($createTransactions);
        $pdo->exec($createSavingsGoals);

        $dbName = get_current_database_name($pdo);

        if (!schema_column_exists($pdo, 'users', 'role', $dbName)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user' AFTER edad");
        }

        if (!schema_column_exists($pdo, 'transactions', 'category_id', $dbName)) {
            $pdo->exec('ALTER TABLE transactions ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER category');
        }

        if (!schema_index_exists($pdo, 'transactions', 'idx_transactions_category_id', $dbName)) {
            $pdo->exec('ALTER TABLE transactions ADD INDEX idx_transactions_category_id (category_id)');
        }

        if (!schema_column_exists($pdo, 'expense_categories', 'type', $dbName)) {
            $pdo->exec("ALTER TABLE expense_categories ADD COLUMN type ENUM('income','expense','mixed') NOT NULL DEFAULT 'mixed' AFTER user_id");
        }

        // Garantizar que la columna type soporte categorías mixtas en instalaciones existentes.
        $pdo->exec("ALTER TABLE expense_categories MODIFY COLUMN type ENUM('income','expense','mixed') NOT NULL DEFAULT 'mixed'");

        if (schema_index_exists($pdo, 'expense_categories', 'unique_user_category', $dbName)) {
            try {
                $pdo->exec('ALTER TABLE expense_categories DROP INDEX unique_user_category');
            } catch (PDOException $e) {
                // noop
            }
        }

        if (!schema_index_exists($pdo, 'expense_categories', 'unique_user_category', $dbName)) {
            $pdo->exec('ALTER TABLE expense_categories ADD UNIQUE KEY unique_user_category (user_id, type, name)');
        }
    }
}

if (!function_exists('assert_finanzas_schema')) {
    function assert_finanzas_schema(PDO $pdo): void
    {
        $dbName = get_current_database_name($pdo);
        $hasRequiredTables = schema_table_exists($pdo, 'expense_categories', $dbName)
            && schema_table_exists($pdo, 'transactions', $dbName)
            && schema_table_exists($pdo, 'savings_goals', $dbName);

        $hasRequiredColumns = schema_column_exists($pdo, 'expense_categories', 'type', $dbName)
            && schema_column_exists($pdo, 'transactions', 'category_id', $dbName);

        if (!$hasRequiredTables || !$hasRequiredColumns) {
            throw new RuntimeException('Falta el esquema financiero requerido. Ejecuta src/utils/migrate.php antes de usar finanzas.');
        }
    }
}