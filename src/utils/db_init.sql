-- Inicialización de la base de datos para TFG
-- Crea la base de datos y las tablas: users, finanzas (1:1 con users), uploads

CREATE DATABASE IF NOT EXISTS `tfg_db`
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

USE `tfg_db`;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `correo` VARCHAR(255) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(150) NOT NULL,
  `edad` TINYINT UNSIGNED NOT NULL,
  `role` ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user',
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_correo` (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de finanzas (relación 1:1 con users)
CREATE TABLE IF NOT EXISTS `finanzas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `income` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `expenses` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'EUR',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user` (`user_id`),
  CONSTRAINT `fk_finanzas_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de uploads para archivos subidos
CREATE TABLE IF NOT EXISTS `uploads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `filename` VARCHAR(255) NOT NULL,
  `filepath` VARCHAR(1024) NOT NULL,
  `mime` VARCHAR(100) DEFAULT NULL,
  `size` BIGINT UNSIGNED DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user` (`user_id`),
  CONSTRAINT `fk_uploads_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ejemplo: insertar datos de prueba (opcional)
-- INSERT INTO `users` (`correo`, `nombre`, `apellidos`, `edad`, `role`, `password`) VALUES
-- ('test@example.com', 'Test', 'Usuario', 30, 'user', '$2y$10$...');

-- Tabla para tokens de inicio de sesión persistente (remember me)
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

-- Tabla de auditoría de seguridad y cumplimiento
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

-- Tabla de tickets de soporte/incidencias
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

-- Tabla de transacciones para analítica de gastos/ingresos
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

-- Tabla de metas de ahorro
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
