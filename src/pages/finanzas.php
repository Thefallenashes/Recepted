<?php
session_start();
require_once __DIR__ . '/../utils/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$mensaje = '';
$tipo = '';

try {
    $pdo = getPDO();
    // Obtener registro de finanzas del usuario
    $stmt = $pdo->prepare('SELECT * FROM finanzas WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
    $finanzas = $stmt->fetch();

    // Procesar actualización si se envía el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $balance = floatval($_POST['balance'] ?? 0);
        $income = floatval($_POST['income'] ?? 0);
        $expenses = floatval($_POST['expenses'] ?? 0);

        $stmt = $pdo->prepare('UPDATE finanzas SET balance = :balance, income = :income, expenses = :expenses WHERE user_id = :user_id');
        $stmt->execute([
            'balance' => $balance,
            'income' => $income,
            'expenses' => $expenses,
            'user_id' => $_SESSION['usuario_id']
        ]);

        $tipo = 'exito';
        $mensaje = 'Finanzas actualizadas.';

        // Recargar valores
        $stmt = $pdo->prepare('SELECT * FROM finanzas WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
        $finanzas = $stmt->fetch();
    }
} catch (PDOException $e) {
    error_log('Error finanzas: ' . $e->getMessage());
    $tipo = 'error';
    $mensaje = 'No se pudo obtener la información financiera.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanzas</title>
    <link rel="stylesheet" href="../css/finanzas.css">
</head>
<body>
    <div class="finanzas-container">
        <h1>Finanzas</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if ($finanzas): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="balance">Balance:</label>
                    <input type="number" step="0.01" name="balance" id="balance" value="<?php echo htmlspecialchars($finanzas['balance']); ?>">
                </div>
                <div class="form-group">
                    <label for="income">Ingresos:</label>
                    <input type="number" step="0.01" name="income" id="income" value="<?php echo htmlspecialchars($finanzas['income']); ?>">
                </div>
                <div class="form-group">
                    <label for="expenses">Gastos:</label>
                    <input type="number" step="0.01" name="expenses" id="expenses" value="<?php echo htmlspecialchars($finanzas['expenses']); ?>">
                </div>
                <button class="btn" type="submit">Actualizar</button>
            </form>
        <?php else: ?>
            <p>No hay información financiera. Contacta con soporte.</p>
        <?php endif; ?>

        <p><a href="home.php">Volver al inicio</a></p>
    </div>
</body>
</html>
