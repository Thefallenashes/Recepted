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

$logoutIconDataUri = 'data:image/png;base64,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';
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
    <header class="sticky-page-menu">
        <div class="sticky-page-menu-inner">
            <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
                <span class="icon-home" aria-hidden="true">⌂</span>
                <span>Inicio</span>
            </a>

            <nav class="sticky-links">
                <ul>
                    <li><a href="finanzas.php">Finanzas</a></li>
                    <li><a href="perfil.php">Perfil</a></li>
                    <li><a href="tickets.php">Tickets</a></li>
                    <li><a href="config.php">Configuración</a></li>
                </ul>
            </nav>

            <a class="menu-icon-btn logout-btn" href="logout.php" aria-label="Cerrar sesión">
                <img src="<?php echo htmlspecialchars($logoutIconDataUri); ?>" alt="Cerrar sesión" class="logout-icon">
                <span>Cerrar sesión</span>
            </a>
        </div>
    </header>

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

    </div>
</body>
</html>
