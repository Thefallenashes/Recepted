<?php
// Conexión a la base de datos usando PDO
// IMPORTANTE: Para producción, usa variables de entorno

$config = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: 3306,
    'dbname' => getenv('DB_NAME') ?: 'tfg_db',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

function getPDO(): PDO
{
    global $config;

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'], $config['port'], $config['dbname'], $config['charset']);

    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Para producción, no mostrar detalles del error
        error_log('Database connection error: ' . $e->getMessage());
        
        if (getenv('APP_DEBUG') === 'false' || getenv('APP_DEBUG') === '0') {
            echo "Error de conexión a la base de datos. Por favor, intenta más tarde.";
        } else {
            echo "Error de conexión a la base de datos: " . htmlspecialchars($e->getMessage());
        }
        exit;
    }
}
// Intento de auto-login mediante cookie "remember"
try {
    $pdo_for_autologin = getPDO();
    $auth_file = __DIR__ . '/auth.php';
    if (file_exists($auth_file)) {
        require_once $auth_file;
        // No evitar errores si algo falla en el proceso
        login_from_remember_cookie($pdo_for_autologin);
    }
} catch (Exception $e) {
    // noop: si hay problemas con la base de datos, no interfiere con la página pública
}

?>
