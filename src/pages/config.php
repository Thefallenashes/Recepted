<?php
session_start();
require_once __DIR__ . '/../utils/db.php';

// For now basic config page with session check
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$mensaje = '';
$tipo = '';

// Example: update user preferred currency (stored in finanzas.currency)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currency = trim($_POST['currency'] ?? 'EUR');
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('UPDATE finanzas SET currency = :currency WHERE user_id = :user_id');
        $stmt->execute(['currency' => $currency, 'user_id' => $_SESSION['usuario_id']]);
        $tipo = 'exito';
        $mensaje = 'Configuración guardada.';
    } catch (PDOException $e) {
        error_log('Error config save: ' . $e->getMessage());
        $tipo = 'error';
        $mensaje = 'No se pudo guardar la configuración.';
    }
}

// Obtener configuración actual
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT currency FROM finanzas WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
    $conf = $stmt->fetch();
    $current_currency = $conf['currency'] ?? 'EUR';
} catch (PDOException $e) {
    $current_currency = 'EUR';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración</title>
    <link rel="stylesheet" href="../css/config.css">
</head>
<body>
    <div class="config-container">
        <h1>Configuración</h1>
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="currency">Moneda preferida:</label>
                <input id="currency" name="currency" value="<?php echo htmlspecialchars($current_currency); ?>">
            </div>
            <button class="btn" type="submit">Guardar</button>
        </form>

        <p><a href="home.php">Volver al inicio</a></p>
    </div>
</body>
</html>
