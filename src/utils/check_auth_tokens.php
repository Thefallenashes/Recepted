<?php
require_once __DIR__ . '/db.php';

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = :db AND table_name = 'auth_tokens'");
    $dbname = getenv('DB_NAME') ?: 'tfg_db';
    $stmt->execute(['db' => $dbname]);
    $exists = (int)$stmt->fetchColumn() > 0;

    if ($exists) {
        $c = $pdo->query('SELECT COUNT(*) FROM auth_tokens')->fetchColumn();
        echo "auth_tokens exists. rows=" . (int)$c . "\n";
    } else {
        echo "auth_tokens does NOT exist.\n";
    }
} catch (Exception $e) {
    echo "Error checking auth_tokens: " . $e->getMessage() . "\n";
}

?>