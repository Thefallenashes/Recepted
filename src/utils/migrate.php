<?php
// Ejecuta migraciones para auth_tokens, roles y módulos de administración/soporte.
require_once __DIR__ . '/db.php';

$createAuthTokens = <<<SQL
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

$createAuditLogs = <<<SQL
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

$createSupportTickets = <<<SQL
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

$createTransactions = <<<SQL
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('income','expense') NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_transactions_user` (`user_id`),
  INDEX `idx_transactions_category` (`category`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$createSavingsGoals = <<<SQL
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

try {
    $pdo = getPDO();
  $pdo->exec($createAuthTokens);
  $pdo->exec($createAuditLogs);
  $pdo->exec($createSupportTickets);
  $pdo->exec($createTransactions);
  $pdo->exec($createSavingsGoals);

  $dbName = getenv('DB_NAME') ?: 'tfg_db';
  $checkRole = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :db AND table_name = 'users' AND column_name = 'role'");
  $checkRole->execute(['db' => $dbName]);
  $roleExists = (int)$checkRole->fetchColumn() > 0;

  if (!$roleExists) {
    $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user' AFTER edad");
  }

  echo "Migration completed: roles, auth_tokens, audit_logs, support_tickets, transactions and savings_goals ensured.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>