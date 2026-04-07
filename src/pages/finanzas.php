<?php
require_once __DIR__ . '/includes/page_bootstrap.php';
require_once __DIR__ . '/../utils/schema.php';

$userId = require_authenticated_user('login.php');

$mensaje = '';
$tipo = '';

if (isset($_SESSION['finanzas_flash']) && is_array($_SESSION['finanzas_flash'])) {
    $mensaje = (string)($_SESSION['finanzas_flash']['mensaje'] ?? '');
    $tipo = (string)($_SESSION['finanzas_flash']['tipo'] ?? '');
    unset($_SESSION['finanzas_flash']);
}

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
    assert_finanzas_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

        if ($action === 'add_category') {
            $categoryName = trim($_POST['category_name'] ?? '');
            $categoryType = $_POST['category_type'] ?? 'expense';
            if (!in_array($categoryType, ['income', 'expense'], true)) {
                $categoryType = 'expense';
            }

            if ($categoryName === '') {
                $tipo = 'error';
                $mensaje = 'El nombre de la categoría es necesario.';
            } else {
                try {
                       $stmt = $pdo->prepare('INSERT INTO expense_categories (user_id, type, name) VALUES (:user_id, :type, :name)');
                    $stmt->execute([
                        'user_id' => $userId,
                        'type' => $categoryType,
                        'name' => $categoryName,
                    ]);
                    $tipo = 'exito';
                    $mensaje = 'Categoría creada correctamente.';
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $tipo = 'error';
                        $mensaje = 'Esta categoría ya existe.';
                    } else {
                        throw $e;
                    }
                }
            }
        }

        if ($action === 'delete_category') {
            $deleteCategoryId = (int)($_POST['selected_category_id'] ?? 0);

            if ($deleteCategoryId <= 0) {
                $tipo = 'error';
                $mensaje = 'Selecciona una categoría válida para eliminar.';
            } else {
                $stmt = $pdo->prepare('UPDATE transactions SET category_id = NULL WHERE user_id = :user_id AND category_id = :category_id');
                $stmt->execute([
                    'user_id' => $userId,
                    'category_id' => $deleteCategoryId,
                ]);

                $stmt = $pdo->prepare('DELETE FROM expense_categories WHERE id = :id AND user_id = :user_id');
                $stmt->execute([
                    'id' => $deleteCategoryId,
                    'user_id' => $userId,
                ]);

                if ($stmt->rowCount() > 0) {
                    $tipo = 'exito';
                    $mensaje = 'Categoría eliminada correctamente.';
                } else {
                    $tipo = 'error';
                    $mensaje = 'No se pudo eliminar la categoría seleccionada.';
                }
            }
        }

        if ($action === 'add_transaction') {
            $type = $_POST['type'] ?? 'expense';
            if (!in_array($type, ['income', 'expense'], true)) {
                $type = 'expense';
            }

            $amount = (float)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $categoryId = $type === 'income'
                ? (int)($_POST['category_id_income'] ?? 0)
                : (int)($_POST['category_id_expense'] ?? 0);

            if ($amount <= 0 || $categoryId <= 0) {
                $tipo = 'error';
                $mensaje = 'Debes seleccionar una categoría y un importe mayor que 0.';
            } else {
                $categoryIdToUse = $categoryId;
                $category = categorize_transaction($description, $type);

                if ($categoryIdToUse !== null) {
                    $stmt = $pdo->prepare('SELECT name FROM expense_categories WHERE id = :id AND user_id = :user_id AND type = :type LIMIT 1');
                    $stmt->execute([
                        'id' => $categoryIdToUse,
                        'user_id' => $userId,
                        'type' => $type,
                    ]);
                    $customCategoryName = $stmt->fetchColumn();
                    if (is_string($customCategoryName) && $customCategoryName !== '') {
                        $category = $customCategoryName;
                    } else {
                        $tipo = 'error';
                        $mensaje = 'La categoría seleccionada no es válida para el tipo de transacción.';
                        $categoryIdToUse = 0;
                    }
                }

                if ($categoryIdToUse > 0) {
                    $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, category, category_id, description) VALUES (:user_id, :type, :amount, :category, :category_id, :description)');
                    $stmt->execute([
                        'user_id' => $userId,
                        'type' => $type,
                        'amount' => $amount,
                        'category' => $category,
                        'category_id' => $categoryIdToUse,
                        'description' => $description,
                    ]);

                    if (function_exists('record_audit_log')) {
                        record_audit_log($pdo, 'transaction_created', 'info', 'Transacción registrada y categorizada automáticamente');
                    }

                    $tipo = 'exito';
                    $mensaje = 'Transacción añadida correctamente.';
                }
            }
        }

        if ($action === 'delete_transaction') {
            $transactionId = (int)($_POST['transaction_id'] ?? 0);

            if ($transactionId <= 0) {
                $tipo = 'error';
                $mensaje = 'ID de transacción inválido.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = :id AND user_id = :user_id');
                $stmt->execute([
                    'id' => $transactionId,
                    'user_id' => $userId,
                ]);

                if ($stmt->rowCount() > 0) {
                    if (function_exists('record_audit_log')) {
                        record_audit_log($pdo, 'transaction_deleted', 'info', 'Transacción eliminada');
                    }
                    $tipo = 'exito';
                    $mensaje = 'Transacción eliminada correctamente.';
                } else {
                    $tipo = 'error';
                    $mensaje = 'No se pudo eliminar la transacción.';
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok' => $tipo === 'exito',
                    'mensaje' => $mensaje,
                    'transaction_id' => $transactionId,
                ], JSON_UNESCAPED_UNICODE);
                exit();
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

        if (!$isAjax) {
            $_SESSION['finanzas_flash'] = [
                'tipo' => $tipo,
                'mensaje' => $mensaje,
            ];
            header('Location: finanzas.php');
            exit();
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
    if (!$finanzas) {
        $finanzas = [
            'balance' => 0,
            'income' => 0,
            'expenses' => 0,
            'currency' => 'EUR',
        ];
    }

    // Obtener categorías personalizadas del usuario
    $stmt = $pdo->prepare('SELECT id, name, color, type FROM expense_categories WHERE user_id = :user_id ORDER BY type ASC, name ASC');
    $stmt->execute(['user_id' => $userId]);
    $personalCategories = $stmt->fetchAll();
    $expenseCategories = [];
    $incomeCategories = [];
    foreach ($personalCategories as $cat) {
        if (($cat['type'] ?? 'expense') === 'income') {
            $incomeCategories[] = $cat;
        } else {
            $expenseCategories[] = $cat;
        }
    }

    $stmt = $pdo->prepare("SELECT t.id, t.type, t.amount, t.category, t.description, t.created_at, t.category_id, COALESCE(ec.name, t.category) AS display_category
        FROM transactions t
        LEFT JOIN expense_categories ec ON ec.id = t.category_id AND ec.user_id = :category_owner
        WHERE t.user_id = :transaction_owner
        ORDER BY t.created_at DESC
        LIMIT 20");
    $stmt->execute([
        'category_owner' => $userId,
        'transaction_owner' => $userId,
    ]);
    $transactions = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT category, SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense' GROUP BY category ORDER BY total DESC");
    $stmt->execute(['user_id' => $userId]);
    $expenseByCategory = $stmt->fetchAll();

    $maxCategoryTotal = 0.0;
    foreach ($expenseByCategory as $row) {
        $maxCategoryTotal = max($maxCategoryTotal, (float)$row['total']);
    }

    // Obtener gastos agrupados por categoría personalizada
    $stmt = $pdo->prepare("
        SELECT 
            ec.id,
            ec.name,
            COALESCE(SUM(t.amount), 0) AS total,
            COUNT(t.id) AS transaction_count
                FROM expense_categories ec
        LEFT JOIN transactions t ON ec.id = t.category_id AND t.type = 'expense'
        WHERE ec.user_id = :user_id
                    AND ec.type = 'expense'
           GROUP BY ec.id, ec.name
        ORDER BY total DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $expensesByPersonalCategory = $stmt->fetchAll();

    // Obtener transacciones por categoría personalizada para expandir
    $transactionsByCategory = [];
    if (!empty($expensesByPersonalCategory)) {
        foreach ($expensesByPersonalCategory as $cat) {
            $catId = (int)$cat['id'];
            $stmt = $pdo->prepare("
                SELECT id, amount, description, created_at 
                FROM transactions 
                WHERE category_id = :category_id AND type = 'expense'
                ORDER BY created_at DESC
            ");
            $stmt->execute(['category_id' => $catId]);
            $transactionsByCategory[$catId] = $stmt->fetchAll();
        }
    }

    $chartSeriesByCategory = [];
    foreach ($expensesByPersonalCategory as $cat) {
        $catId = (int)$cat['id'];
        $series = [];
        $runningTotal = 0.0;
        if (!empty($transactionsByCategory[$catId])) {
            $txAsc = array_reverse($transactionsByCategory[$catId]);
            foreach ($txAsc as $txItem) {
                $runningTotal += (float)$txItem['amount'];
                $series[] = [
                    'label' => substr((string)$txItem['created_at'], 0, 10),
                    'value' => $runningTotal,
                ];
            }
        }
        if (empty($series)) {
            $series[] = ['label' => 'Sin datos', 'value' => 0];
        }
        $chartSeriesByCategory[$catId] = $series;
    }

    $stmt = $pdo->prepare('SELECT id, name, target_amount, current_amount, target_date FROM savings_goals WHERE user_id = :user_id ORDER BY created_at DESC');
    $stmt->execute(['user_id' => $userId]);
    $goals = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error finanzas: ' . $e->getMessage());
    $tipo = 'error';
    $mensaje = 'No se pudo procesar la información financiera avanzada.';
    $finanzas = [
        'balance' => 0,
        'income' => 0,
        'expenses' => 0,
        'currency' => 'EUR',
    ];
    $personalCategories = [];
    $expenseCategories = [];
    $incomeCategories = [];
    $transactions = [];
    $expenseByCategory = [];
    $maxCategoryTotal = 0.0;
    $expensesByPersonalCategory = [];
    $transactionsByCategory = [];
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
    <?php
    render_sticky_menu([
        'container_class' => 'sticky-page-menu',
        'inner_class' => 'sticky-page-menu-inner',
        'home_href' => 'home.php',
        'logout_href' => 'scripts/logout.php',
        'nav_items' => [
            ['href' => 'finanzas.php', 'label' => 'Finanzas'],
            ['href' => 'tickets.php', 'label' => 'Tickets'],
            ['href' => 'config.php', 'label' => 'Configuración'],
        ],
    ]);
    ?>

    <div class="finanzas-container card">
        <div class="card-header">
            <h1>Finanzas</h1>
        </div>
        <div class="card-body">

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo === 'exito' ? 'success' : 'error'; ?>">
                <span class="alert-icon">ℹ️</span>
                <span><?php echo $mensaje; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($finanzas): ?>
            <div class="grid grid-cols-3">
                <div class="stat-box primary">
                    <div class="stat-label">Balance</div>
                    <p class="stat-value"><?php echo number_format((float)$finanzas['balance'], 2); ?></p>
                    <small><?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></small>
                </div>
                <div class="stat-box success">
                    <div class="stat-label">Ingresos</div>
                    <p class="stat-value"><?php echo number_format((float)$finanzas['income'], 2); ?></p>
                    <small><?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></small>
                </div>
                <div class="stat-box danger">
                    <div class="stat-label">Gastos</div>
                    <p class="stat-value"><?php echo number_format((float)$finanzas['expenses'], 2); ?></p>
                    <small><?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></small>
                </div>
            </div>

            <h2 class="mt-4 mb-2">Nueva transacción</h2>
            <form method="POST" action="" class="panel-form">
                <input type="hidden" name="action" value="add_transaction">
                <div class="form-group">
                    <label for="type">Tipo</label>
                    <select id="type" name="type" onchange="updateCategorySelector()">
                        <option value="expense" selected>Gasto</option>
                        <option value="income">Ingreso</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">Importe</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label for="category_id_expense">Categoría (gasto)</label>
                    <select id="category_id_expense" name="category_id_expense">
                        <option value="" selected disabled hidden></option>
                        <?php foreach ($expenseCategories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="income-category-group" style="display: none;">
                    <label for="category_id_income">Categoría (ingreso)</label>
                    <select id="category_id_income" name="category_id_income">
                        <option value="" selected disabled hidden></option>
                        <?php foreach ($incomeCategories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Descripción</label>
                    <input id="description" name="description">
                </div>
                <button class="btn" type="submit">Guardar transacción</button>
            </form>

            <h2>Gastos por categoría</h2>
            
            <div style="margin-bottom: 20px;">
                <form method="POST" action="" class="panel-form" style="margin: 0;">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label for="category_type">Tipo de categoría</label>
                        <select id="category_type" name="category_type" required>
                            <option value="expense" selected>Gasto</option>
                            <option value="income">Ingreso</option>
                        </select>
                    </div>
                       <div style="display: grid; grid-template-columns: 1fr 120px; gap: 10px; align-items: flex-end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="category_name">Nombre</label>
                            <input id="category_name" name="category_name" required>
                        </div>
                        <button class="btn" type="submit" style="margin: 0;">Crear</button>
                    </div>
                </form>
            </div>
            <h2>Categorías:</h2>
            <div class="panel-form" style="margin-bottom: 20px;">
                <?php if (empty($personalCategories)): ?>
                <?php else: ?>
                    <div class="category-selector-row">
                        <select id="selected_category_id" name="selected_category_id" class="category-select" onchange="renderCategoryLineChart()">
                            <option value="" selected disabled>Elije una categoria</option>
                            <?php foreach ($personalCategories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?> (<?php echo ($cat['type'] ?? 'expense') === 'income' ? 'Ingreso' : 'Gasto'; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <form method="POST" action="" onsubmit="return confirm('¿Eliminar la categoría seleccionada?');">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="selected_category_id" id="delete_selected_category_id" value="">
                            <button type="submit" class="tx-delete-btn" id="delete_selected_category_btn" disabled>Eliminar categoría</button>
                        </form>
                    </div>
                    <canvas id="categoryLineChart" height="190"></canvas>
                <?php endif; ?>
            </div>

            <?php if (empty($expensesByPersonalCategory)): ?>
            <?php else: ?>
                <div class="category-expenses-list">
                    <?php foreach ($expensesByPersonalCategory as $index => $cat): ?>
                        <?php
                            $catId = (int)$cat['id'];
                            $total = (float)$cat['total'];
                            $hasTransactions = !empty($transactionsByCategory[$catId]) && count($transactionsByCategory[$catId]) > 0;
                        ?>
                           <div class="category-card">
                            <div class="category-header" onclick="toggleCategory(<?php echo $catId; ?>)" style="cursor: pointer;">
                                <div class="category-title-bar">
                                       <span class="category-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                                    <span class="toggle-icon" id="toggle-<?php echo $catId; ?>">▼</span>
                                </div>
                            </div>
                            <?php if ($hasTransactions): ?>
                                <div class="category-details" id="details-<?php echo $catId; ?>" style="display: none;">
                                    <div class="transactions-list">
                                        <?php foreach ($transactionsByCategory[$catId] as $tx): ?>
                                            <div class="transaction-item">
                                                <span class="tx-date"><?php echo htmlspecialchars(substr($tx['created_at'], 0, 10)); ?></span>
                                                <span class="tx-description"><?php echo htmlspecialchars($tx['description']); ?></span>
                                                <span class="tx-amount"><?php echo number_format((float)$tx['amount'], 2); ?></span>
                                                <form method="POST" action="" class="js-delete-transaction-form" style="display: inline; margin: 0;">
                                                    <input type="hidden" name="action" value="delete_transaction">
                                                    <input type="hidden" name="transaction_id" value="<?php echo (int)$tx['id']; ?>">
                                                    <button type="submit" class="tx-delete-btn" onclick="return confirm('¿Eliminar esta transacción?');">Eliminar</button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="category-stats">
                                        <strong>Total: <?php echo number_format($total, 2); ?> EUR</strong> | 
                                        <strong>Transacciones: <?php echo (int)$cat['transaction_count']; ?></strong>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="category-details" id="details-<?php echo $catId; ?>" style="display: none;">
                                    <p style="padding: 10px; color: #999;">No hay transacciones en esta categoría</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2>Metas de ahorro</h2>
            <form method="POST" action="" class="panel-form">
                <input type="hidden" name="action" value="add_goal">
                <div class="form-group">
                    <label for="goal_name">Nombre de la meta</label>
                    <input id="goal_name" name="goal_name">
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
                                <td><?php echo htmlspecialchars($tx['display_category']); ?></td>
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
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
    <script src="../js/mobile-menu-enhancements.js" defer></script>
    <script src="../js/animation-manager.js" defer></script>
    <script>
        const categorySeries = <?php echo json_encode($chartSeriesByCategory, JSON_UNESCAPED_UNICODE); ?>;
        const expenseCategoriesCount = <?php echo (int)count($expenseCategories); ?>;
        const incomeCategoriesCount = <?php echo (int)count($incomeCategories); ?>;

        function toggleCategory(categoryId) {
            const details = document.getElementById('details-' + categoryId);
            const toggle = document.getElementById('toggle-' + categoryId);
            
            if (details.style.display === 'none' || details.style.display === '') {
                details.style.display = 'block';
                toggle.textContent = '▲';
            } else {
                details.style.display = 'none';
                toggle.textContent = '▼';
            }
        }

        function updateCategorySelector() {
            const typeSelect = document.getElementById('type');
            const expenseSelect = document.getElementById('category_id_expense');
            const incomeSelect = document.getElementById('category_id_income');
            const incomeGroup = document.getElementById('income-category-group');

            if (!typeSelect || !expenseSelect || !incomeSelect || !incomeGroup) {
                return;
            }

            if (typeSelect.value === 'expense') {
                expenseSelect.parentElement.style.display = 'block';
                incomeGroup.style.display = 'none';
                expenseSelect.required = true;
                incomeSelect.required = false;
                incomeSelect.value = '';
            } else {
                expenseSelect.parentElement.style.display = 'none';
                incomeGroup.style.display = 'block';
                expenseSelect.required = false;
                incomeSelect.required = true;
                expenseSelect.value = '';
            }

            if (typeSelect.value === 'expense' && expenseCategoriesCount === 0) {
                expenseSelect.setCustomValidity('Primero crea una categoría de gasto.');
            } else if (typeSelect.value === 'income' && incomeCategoriesCount === 0) {
                incomeSelect.setCustomValidity('Primero crea una categoría de ingreso.');
            } else {
                expenseSelect.setCustomValidity('');
                incomeSelect.setCustomValidity('');
            }
        }

        function renderCategoryLineChart() {
            const selector = document.getElementById('selected_category_id');
            const deleteHidden = document.getElementById('delete_selected_category_id');
            const deleteButton = document.getElementById('delete_selected_category_btn');
            const canvas = document.getElementById('categoryLineChart');

            if (!selector || !canvas) {
                return;
            }

            deleteHidden.value = selector.value;
            if (deleteButton) {
                deleteButton.disabled = selector.value === '';
            }

            const ctx = canvas.getContext('2d');
            const dpr = window.devicePixelRatio || 1;
            const cssWidth = canvas.clientWidth || 640;
            const cssHeight = 190;

            canvas.width = Math.floor(cssWidth * dpr);
            canvas.height = Math.floor(cssHeight * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            ctx.clearRect(0, 0, cssWidth, cssHeight);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, cssWidth, cssHeight);

            if (selector.value === '') {
                return;
            }

            const points = categorySeries[selector.value] || [{ label: 'Sin datos', value: 0 }];

            const padding = 24;
            const plotWidth = cssWidth - padding * 2;
            const plotHeight = cssHeight - padding * 2;
            const values = points.map((p) => Number(p.value || 0));
            const maxVal = Math.max(...values, 1);

            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(padding, cssHeight - padding);
            ctx.lineTo(cssWidth - padding, cssHeight - padding);
            ctx.moveTo(padding, padding);
            ctx.lineTo(padding, cssHeight - padding);
            ctx.stroke();

            if (points.length === 1) {
                const x = padding + plotWidth / 2;
                const y = cssHeight - padding - (values[0] / maxVal) * plotHeight;
                ctx.fillStyle = '#0ea5a8';
                ctx.beginPath();
                ctx.arc(x, y, 4, 0, Math.PI * 2);
                ctx.fill();
                return;
            }

            ctx.strokeStyle = '#0ea5a8';
            ctx.lineWidth = 2;
            ctx.beginPath();
            points.forEach((p, i) => {
                const x = padding + (i / (points.length - 1)) * plotWidth;
                const y = cssHeight - padding - (Number(p.value || 0) / maxVal) * plotHeight;
                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            ctx.stroke();

            ctx.fillStyle = '#0ea5a8';
            points.forEach((p, i) => {
                const x = padding + (i / (points.length - 1)) * plotWidth;
                const y = cssHeight - padding - (Number(p.value || 0) / maxVal) * plotHeight;
                ctx.beginPath();
                ctx.arc(x, y, 3, 0, Math.PI * 2);
                ctx.fill();
            });
        }

        function bindDynamicDeleteTransaction() {
            const forms = document.querySelectorAll('.js-delete-transaction-form');
            forms.forEach((form) => {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const button = form.querySelector('.tx-delete-btn');
                    if (!button) {
                        return;
                    }

                    button.disabled = true;
                    const formData = new FormData(form);
                    formData.append('ajax', '1');

                    try {
                        const response = await fetch('finanzas.php', {
                            method: 'POST',
                            body: formData,
                        });

                        if (!response.ok) {
                            throw new Error('Error al eliminar transacción');
                        }

                        const data = await response.json();
                        if (data && data.ok) {
                            const row = form.closest('.transaction-item');
                            if (row) {
                                const list = row.closest('.transactions-list');
                                row.remove();

                                if (list && list.children.length === 0) {
                                    list.innerHTML = '<p style="padding: 10px; color: #999;">No hay transacciones en esta categoría</p>';
                                }
                            }
                        } else {
                            button.disabled = false;
                        }
                    } catch (error) {
                        button.disabled = false;
                    }
                });
            });
        }

        // Inicializar al cargar la página
        window.addEventListener('DOMContentLoaded', function() {
            updateCategorySelector();
            renderCategoryLineChart();
            bindDynamicDeleteTransaction();
        });
    </script>
</body>
</html>


