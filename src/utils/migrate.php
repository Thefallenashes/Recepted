<?php
// Ejecuta migraciones para auth_tokens, roles y módulos de administración/soporte.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';

try {
    $pdo = getPDO();
    ensure_application_schema($pdo);
    echo "Migration completed: roles, auth_tokens, audit_logs, support_tickets, expense_categories, transactions and savings_goals ensured.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>