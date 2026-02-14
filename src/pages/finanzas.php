<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$mensaje = '';
$tipo = '';

function categorize_transaction(string $description, string $type): string
{
    $text = mb_strtolower(trim($description));

    if ($type === 'income') {
        if (preg_match('/nomina|salario|sueldo|payroll|trabajo/', $text)) {
            return 'salario';
        }
        if (preg_match('/freelance|cliente|proyecto/', $text)) {
            return 'freelance';
        }
        if (preg_match('/interes|dividendo|inversion/', $text)) {
            return 'inversiones';
        }
        return 'otros_ingresos';
    }

    if (preg_match('/super|mercado|compra|aliment|restaurante|cafe/', $text)) {
        return 'alimentacion';
    }
    if (preg_match('/uber|taxi|bus|metro|tren|gasolina|combustible/', $text)) {
        return 'transporte';
    }
    if (preg_match('/alquiler|hipoteca|luz|agua|internet|hogar/', $text)) {
        return 'hogar';
    }
    if (preg_match('/cine|netflix|spotify|ocio|viaje|vacaciones/', $text)) {
        return 'ocio';
    }
    if (preg_match('/medico|farmacia|salud|seguro/', $text)) {
        return 'salud';
    }
    return 'otros_gastos';
}

try {
    $pdo = getPDO();

    // Asegurar tablas para funcionalidad avanzada
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        type ENUM('income','expense') NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        category VARCHAR(80) NOT NULL,
        description VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_transactions_user (user_id),
        INDEX idx_transactions_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS savings_goals (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        target_amount DECIMAL(14,2) NOT NULL,
        current_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        target_date DATE NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_goals_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $userId = (int)$_SESSION['usuario_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_transaction') {
            $type = $_POST['type'] ?? 'expense';
            if (!in_array($type, ['income', 'expense'], true)) {
                $type = 'expense';
            }

            $amount = (float)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($amount <= 0 || $description === '') {
                $tipo = 'error';
                $mensaje = 'Debes indicar descripción y un importe mayor que 0.';
            } else {
                $category = categorize_transaction($description, $type);
                $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, category, description) VALUES (:user_id, :type, :amount, :category, :description)');
                $stmt->execute([
                    'user_id' => $userId,
                    'type' => $type,
                    'amount' => $amount,
                    'category' => $category,
                    'description' => $description,
                ]);

                if (function_exists('record_audit_log')) {
                    record_audit_log($pdo, 'transaction_created', 'info', 'Transacción registrada y categorizada automáticamente');
                }

                $tipo = 'exito';
                $mensaje = 'Transacción añadida correctamente.';
            }
        }

        if ($action === 'add_goal') {
            $name = trim($_POST['goal_name'] ?? '');
            $targetAmount = (float)($_POST['target_amount'] ?? 0);
            $targetDate = trim($_POST['target_date'] ?? '');
            $targetDate = $targetDate !== '' ? $targetDate : null;

            if ($name === '' || $targetAmount <= 0) {
                $tipo = 'error';
                $mensaje = 'La meta requiere nombre e importe objetivo mayor que 0.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO savings_goals (user_id, name, target_amount, current_amount, target_date) VALUES (:user_id, :name, :target_amount, 0.00, :target_date)');
                $stmt->execute([
                    'user_id' => $userId,
                    'name' => $name,
                    'target_amount' => $targetAmount,
                    'target_date' => $targetDate,
                ]);
                $tipo = 'exito';
                $mensaje = 'Meta de ahorro creada.';
            }
        }

        if ($action === 'add_goal_progress') {
            $goalId = (int)($_POST['goal_id'] ?? 0);
            $addAmount = (float)($_POST['add_amount'] ?? 0);

            if ($goalId <= 0 || $addAmount <= 0) {
                $tipo = 'error';
                $mensaje = 'Importe inválido para actualizar meta.';
            } else {
                $stmt = $pdo->prepare('UPDATE savings_goals SET current_amount = current_amount + :add_amount WHERE id = :id AND user_id = :user_id');
                $stmt->execute([
                    'add_amount' => $addAmount,
                    'id' => $goalId,
                    'user_id' => $userId,
                ]);
                $tipo = 'exito';
                $mensaje = 'Avance de meta actualizado.';
            }
        }
    }

    // Recalcular resumen financiero automáticamente desde transacciones
    $incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = :user_id AND type = 'income'");
    $incomeStmt->execute(['user_id' => $userId]);
    $income = (float)$incomeStmt->fetchColumn();

    $expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = :user_id AND type = 'expense'");
    $expenseStmt->execute(['user_id' => $userId]);
    $expenses = (float)$expenseStmt->fetchColumn();

    $balance = $income - $expenses;

    $stmt = $pdo->prepare('UPDATE finanzas SET balance = :balance, income = :income, expenses = :expenses WHERE user_id = :user_id');
    $stmt->execute([
        'balance' => $balance,
        'income' => $income,
        'expenses' => $expenses,
        'user_id' => $userId,
    ]);

    // Datos para UI
    $stmt = $pdo->prepare('SELECT * FROM finanzas WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $finanzas = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT id, type, amount, category, description, created_at FROM transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20');
    $stmt->execute(['user_id' => $userId]);
    $transactions = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT category, SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense' GROUP BY category ORDER BY total DESC");
    $stmt->execute(['user_id' => $userId]);
    $expenseByCategory = $stmt->fetchAll();

    $maxCategoryTotal = 0.0;
    foreach ($expenseByCategory as $row) {
        $maxCategoryTotal = max($maxCategoryTotal, (float)$row['total']);
    }

    $stmt = $pdo->prepare('SELECT id, name, target_amount, current_amount, target_date FROM savings_goals WHERE user_id = :user_id ORDER BY created_at DESC');
    $stmt->execute(['user_id' => $userId]);
    $goals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error finanzas: ' . $e->getMessage());
    $tipo = 'error';
    $mensaje = 'No se pudo procesar la información financiera avanzada.';
    $finanzas = null;
    $transactions = [];
    $expenseByCategory = [];
    $maxCategoryTotal = 0.0;
    $goals = [];
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
            <div class="summary-grid">
                <div class="summary-card">
                    <h2>Balance</h2>
                    <p><?php echo number_format((float)$finanzas['balance'], 2); ?> <?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></p>
                </div>
                <div class="summary-card">
                    <h2>Ingresos</h2>
                    <p><?php echo number_format((float)$finanzas['income'], 2); ?> <?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></p>
                </div>
                <div class="summary-card">
                    <h2>Gastos</h2>
                    <p><?php echo number_format((float)$finanzas['expenses'], 2); ?> <?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></p>
                </div>
            </div>

            <h2>Nueva transacción</h2>
            <form method="POST" action="" class="panel-form">
                <input type="hidden" name="action" value="add_transaction">
                <div class="form-group">
                    <label for="type">Tipo</label>
                    <select id="type" name="type">
                        <option value="expense" selected>Gasto</option>
                        <option value="income">Ingreso</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">Importe</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label for="description">Descripción</label>
                    <input id="description" name="description" required placeholder="Ejemplo: Supermercado semanal">
                </div>
                <button class="btn" type="submit">Guardar transacción</button>
            </form>

            <h2>Gastos por categoría</h2>
            <?php if (empty($expenseByCategory)): ?>
                <p>Todavía no hay gastos categorizados.</p>
            <?php else: ?>
                <div class="chart-list">
                    <?php foreach ($expenseByCategory as $item): ?>
                        <?php
                            $total = (float)$item['total'];
                            $width = $maxCategoryTotal > 0 ? ($total / $maxCategoryTotal) * 100 : 0;
                        ?>
                        <div class="chart-row">
                            <span class="chart-label"><?php echo htmlspecialchars($item['category']); ?></span>
                            <div class="chart-bar-wrap">
                                <div class="chart-bar" style="width: <?php echo number_format($width, 2, '.', ''); ?>%;"></div>
                            </div>
                            <span class="chart-value"><?php echo number_format($total, 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2>Metas de ahorro</h2>
            <form method="POST" action="" class="panel-form">
                <input type="hidden" name="action" value="add_goal">
                <div class="form-group">
                    <label for="goal_name">Nombre de la meta</label>
                    <input id="goal_name" name="goal_name" required placeholder="Viaje de verano">
                </div>
                <div class="form-group">
                    <label for="target_amount">Importe objetivo</label>
                    <input id="target_amount" name="target_amount" type="number" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="target_date">Fecha objetivo</label>
                    <input id="target_date" name="target_date" type="date">
                </div>
                <button class="btn" type="submit">Crear meta</button>
            </form>

            <?php if (empty($goals)): ?>
                <p>No tienes metas registradas.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Meta</th>
                            <th>Objetivo</th>
                            <th>Actual</th>
                            <th>Progreso</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): ?>
                            <?php
                                $goalTarget = (float)$goal['target_amount'];
                                $goalCurrent = (float)$goal['current_amount'];
                                $goalProgress = $goalTarget > 0 ? min(100, ($goalCurrent / $goalTarget) * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($goal['name']); ?></td>
                                <td><?php echo number_format($goalTarget, 2); ?></td>
                                <td><?php echo number_format($goalCurrent, 2); ?></td>
                                <td><?php echo number_format($goalProgress, 2); ?>%</td>
                                <td>
                                    <form method="POST" action="" style="display:inline">
                                        <input type="hidden" name="action" value="add_goal_progress">
                                        <input type="hidden" name="goal_id" value="<?php echo (int)$goal['id']; ?>">
                                        <input type="number" name="add_amount" min="0.01" step="0.01" placeholder="+ importe" required>
                                        <button type="submit">Añadir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Últimas transacciones</h2>
            <?php if (empty($transactions)): ?>
                <p>No hay transacciones registradas todavía.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Importe</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tx['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($tx['type']); ?></td>
                                <td><?php echo number_format((float)$tx['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($tx['category']); ?></td>
                                <td><?php echo htmlspecialchars($tx['description'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php else: ?>
            <p>No hay información financiera. Contacta con soporte.</p>
        <?php endif; ?>

        <p><a href="home.php">Volver al inicio</a></p>
    </div>
</body>
</html>
