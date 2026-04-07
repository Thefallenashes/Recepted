<?php
require_once __DIR__ . '/includes/page_bootstrap.php';
require_once __DIR__ . '/../utils/schema.php';

$userId = require_authenticated_user('login.php');

$preloadId = (int)($_GET['analizar'] ?? 0);

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

    // Cargar uploads de Excel para la sección de análisis
    $allowedExts = ['csv', 'xlsb', 'xltx', 'xls', 'xlsm', 'xlsx'];
    $isAdminUploads = function_exists('can_manage_all_resources') && can_manage_all_resources();
    if ($isAdminUploads) {
        $stmtUploads = $pdo->query('SELECT id, filename, uploaded_at FROM uploads ORDER BY uploaded_at DESC');
    } else {
        $stmtUploads = $pdo->prepare('SELECT id, filename, uploaded_at FROM uploads WHERE user_id = :uid ORDER BY uploaded_at DESC');
        $stmtUploads->execute(['uid' => $userId]);
    }
    $excelUploads = array_values(array_filter($stmtUploads->fetchAll(), fn($u) =>
        in_array(strtolower(pathinfo($u['filename'], PATHINFO_EXTENSION)), $allowedExts, true)
    ));

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
    $excelUploads = [];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanzas</title>
    <link rel="stylesheet" href="../css/finanzas.css">
    <link rel="stylesheet" href="../css/analizar_excel.css">
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
            ['href' => 'mis_uploads.php', 'label' => 'Mis archivos'],
            ['href' => 'tickets.php', 'label' => 'Tickets'],
            ['href' => 'config.php', 'label' => 'Configuración'],
            ['href' => 'admin_panel.php', 'label' => 'Panel de administracion', 'min_role' => 'admin'],
            ['href' => 'superadmin_console.php', 'label' => 'Consola', 'min_role' => 'superadmin'],
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

            <h2 class="mt-4 mb-2" id="excel-analizar">Analizar archivo Excel</h2>

            <?php if (empty($excelUploads)): ?>
                <p>No tienes archivos de Excel subidos aún.
                   <a href="scripts/upload.php">Haz click aquí para subir uno</a> y vuelve aquí para analizarlo.</p>
            <?php else: ?>

                <div class="panel-form">
                    <div class="form-group">
                        <label for="aeFileSelect">Archivo a analizar</label>
                        <select id="aeFileSelect">
                            <option value="">— Elige un archivo —</option>
                            <?php foreach ($excelUploads as $up): ?>
                                <option value="<?php echo (int) $up['id']; ?>"<?php echo ($preloadId === (int)$up['id']) ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($up['filename']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button id="aeLoadBtn" class="btn" disabled>Cargar y analizar</button>
                </div>

                <div class="ae-chart-types" id="aeChartTypes" style="display:none">
                    <button class="ae-type-btn active" data-type="bar">Gráfico de barras</button>
                    <button class="ae-type-btn"        data-type="pie">Gráfico de sectores</button>
                    <button class="ae-type-btn"        data-type="line">Gráfico de líneas</button>
                    <button class="ae-type-btn"        data-type="text">Texto simple</button>
                </div>

                <div class="ae-chart-area" id="aeChartArea">
                    <p class="ae-msg">Selecciona un archivo y pulsa <strong>Cargar y analizar</strong>.</p>
                </div>

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

    <!-- SheetJS y Chart.js para el análisis de Excel -->
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <script>
(function () {
    'use strict';

    /* ============================================================
       Estado global
    ============================================================ */
    let currentType    = 'bar';
    let chartInstances = [];
    let analysedData   = null;

    /* ============================================================
       Elementos DOM
    ============================================================ */
    const fileSelect   = document.getElementById('aeFileSelect');
    const loadBtn      = document.getElementById('aeLoadBtn');
    const chartArea    = document.getElementById('aeChartArea');
    const chartTypes   = document.getElementById('aeChartTypes');

    if (!fileSelect || !loadBtn || !chartArea) return;

    /* ============================================================
       Eventos de UI
    ============================================================ */
    fileSelect.addEventListener('change', () => {
        loadBtn.disabled = !fileSelect.value;
    });

    document.querySelectorAll('.ae-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ae-type-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentType = btn.dataset.type;
            if (analysedData) renderView(analysedData, currentType);
        });
    });

    loadBtn.addEventListener('click', async () => {
        const id = fileSelect.value;
        if (!id) return;

        loadBtn.disabled = true;
        chartArea.innerHTML = '<p class="ae-msg">Cargando archivo…</p>';

        try {
            const resp = await fetch('scripts/get_upload_raw.php?id=' + encodeURIComponent(id));
            if (!resp.ok) throw new Error('Error del servidor: ' + resp.status);

            const buffer = await resp.arrayBuffer();

            const wb = XLSX.read(buffer, { type: 'array', cellDates: true });
            if (!wb.SheetNames.length) throw new Error('El archivo no contiene hojas.');

            const sheetsData = [];
            for (const sheetName of wb.SheetNames) {
                const ws  = wb.Sheets[sheetName];
                const raw = XLSX.utils.sheet_to_json(ws, { header: 1, defval: null });
                const rows = raw.filter(r => r.some(c => c !== null && c !== ''));
                if (!rows.length) continue;
                const data = analyseData(rows);
                if (!data.labels.length) continue;
                sheetsData.push({ sheetName, ...data });
            }

            if (!sheetsData.length) {
                throw new Error('No se encontraron datos numéricos válidos en el archivo.');
            }

            analysedData = sheetsData;
            if (chartTypes) chartTypes.style.display = 'flex';
            renderView(analysedData, currentType);

        } catch (err) {
            chartArea.innerHTML = '<p class="ae-msg-error">⚠ ' + esc(err.message) + '</p>';
        } finally {
            loadBtn.disabled = false;
        }
    });

    /* ============================================================
       Análisis de datos
    ============================================================ */
    function analyseData(rows) {
        const firstRow   = rows[0];
        const hasHeaders = firstRow.some(c =>
            c !== null && typeof c === 'string' && isNaN(parseFloat(String(c).replace(',', '.')))
        );

        let headers, dataRows;
        if (hasHeaders) {
            headers  = firstRow.map(c => (c !== null ? String(c).trim() : ''));
            dataRows = rows.slice(1);
        } else {
            headers  = null;
            dataRows = rows;
        }

        const numCols = Math.max(...rows.map(r => r.length));

        const colClass = [];
        let   dateColIdx = -1;

        const reGastos     = /gasto|expense|coste|cost|salida|egreso|deuda|pago|debe/i;
        const reBeneficios = /beneficio|ingreso|income|revenue|entrada|ganancia|venta|cobro|salario|sueldo|haber/i;

        for (let c = 0; c < numCols; c++) {
            const vals = dataRows.map(r => r[c]).filter(v => v !== null && v !== '');

            if (!vals.length) { colClass.push('empty'); continue; }

            const dateCount = vals.filter(isDateLike).length;
            if (dateCount / vals.length >= 0.6 && dateColIdx === -1) {
                colClass.push('date');
                dateColIdx = c;
                continue;
            }

            const numCount = vals.filter(isNumericLike).length;
            if (numCount / vals.length < 0.7) {
                colClass.push('text');
                continue;
            }

            const hdr = headers ? headers[c] : '';
            if (reGastos.test(hdr)) { colClass.push('gastos'); continue; }
            if (reBeneficios.test(hdr)) { colClass.push('beneficios'); continue; }

            const nums     = vals.map(parseNum).filter(v => !isNaN(v));
            const negCount = nums.filter(v => v < 0).length;
            const posCount = nums.filter(v => v > 0).length;
            const total    = negCount + posCount || 1;

            if (negCount / total > 0.65) {
                colClass.push('gastos');
            } else if (posCount / total > 0.65) {
                colClass.push('beneficios');
            } else if (negCount > 0 && posCount > 0) {
                colClass.push('mixed');
            } else if (posCount > 0) {
                colClass.push('beneficios');
            } else {
                colClass.push('gastos');
            }
        }

        const hasFinancialCol = colClass.some(c => ['gastos', 'beneficios', 'mixed'].includes(c));
        if (!hasFinancialCol) {
            for (let c = 0; c < numCols; c++) {
                if (colClass[c] === 'text' || colClass[c] === 'date' || colClass[c] === 'empty') continue;
                colClass[c] = 'mixed';
            }
        }

        const gastosNames     = [];
        const beneficiosNames = [];
        for (let c = 0; c < numCols; c++) {
            const name = headers ? (headers[c] || 'Col. ' + (c + 1)) : 'Col. ' + (c + 1);
            if (colClass[c] === 'gastos'     || colClass[c] === 'mixed') gastosNames.push(name);
            if (colClass[c] === 'beneficios' || colClass[c] === 'mixed') beneficiosNames.push(name);
        }

        let labels       = [];
        let gastosData   = [];
        let beneficiosData = [];

        if (dateColIdx >= 0) {
            const monthMap = new Map();

            for (const row of dataRows) {
                const key = getMonthKey(row[dateColIdx]);
                if (!key) continue;

                if (!monthMap.has(key)) monthMap.set(key, { g: 0, b: 0 });
                const entry = monthMap.get(key);
                accumulateRow(row, colClass, numCols, entry);
            }

            const sorted = Array.from(monthMap.keys()).sort();
            labels         = sorted.map(formatMonth);
            gastosData     = sorted.map(k => round2(monthMap.get(k).g));
            beneficiosData = sorted.map(k => round2(monthMap.get(k).b));

        } else {
            dataRows.forEach((row, i) => {
                const entry = { g: 0, b: 0 };
                accumulateRow(row, colClass, numCols, entry);
                if (entry.g > 0 || entry.b > 0) {
                    labels.push('Fila ' + (i + 1));
                    gastosData.push(round2(entry.g));
                    beneficiosData.push(round2(entry.b));
                }
            });
        }

        return { labels, gastos: gastosData, beneficios: beneficiosData, gastosNames, beneficiosNames };
    }

    function accumulateRow(row, colClass, numCols, entry) {
        for (let c = 0; c < numCols; c++) {
            const cls = colClass[c];
            if (!['gastos', 'beneficios', 'mixed'].includes(cls)) continue;
            const v = parseNum(row[c]);
            if (isNaN(v)) continue;

            if (cls === 'gastos') {
                entry.g += Math.abs(v);
            } else if (cls === 'beneficios') {
                entry.b += Math.abs(v);
            } else {
                if (v < 0) entry.g += Math.abs(v);
                else        entry.b += v;
            }
        }
    }

    /* ============================================================
       Renderizado
    ============================================================ */
    function renderView(sheetsData, type) {
        chartInstances.forEach(c => c.destroy());
        chartInstances = [];
        chartArea.innerHTML = '';

        const isMulti = sheetsData.length > 1;
        const multiWrap = document.createElement('div');
        multiWrap.className = isMulti ? 'ae-multi-sheet' : 'ae-single-sheet';
        chartArea.appendChild(multiWrap);

        sheetsData.forEach(data => {
            const section = document.createElement('div');
            section.className = 'ae-sheet-section';

            if (isMulti) {
                const title = document.createElement('h3');
                title.className = 'ae-sheet-title';
                title.textContent = data.sheetName;
                section.appendChild(title);
            }

            const totalG = data.gastos.reduce((a, b) => a + b, 0);
            const totalB = data.beneficios.reduce((a, b) => a + b, 0);
            const net    = totalB - totalG;

            const netClass = net > 0 ? 'pos' : net < 0 ? 'neg' : 'neu';
            const netLabel = net >= 0 ? 'Beneficios' : 'Pérdidas';
            const totalsEl = doc(`
                <div class="ae-totals">
                    <div class="ae-total-item">
                        <span class="ae-total-label">Gastos totales</span>
                        <span class="ae-total-value neg">-${fmt(totalG)} €</span>
                    </div>
                    <div class="ae-total-item">
                        <span class="ae-total-label">Beneficios totales</span>
                        <span class="ae-total-value pos">+${fmt(totalB)} €</span>
                    </div>
                    <div class="ae-total-item">
                        <span class="ae-total-label">Resultado global</span>
                        <span class="ae-total-value ${netClass}">${netLabel}: ${net >= 0 ? '+' : ''}${fmt(net)} €</span>
                    </div>
                </div>`);

            const colInfoEl = doc(`
                <div class="ae-col-info">
                    Columnas de <strong>gastos</strong>: ${esc(data.gastosNames.join(', ') || 'ninguna detectada')}
                    &nbsp;·&nbsp;
                    Columnas de <strong>beneficios</strong>: ${esc(data.beneficiosNames.join(', ') || 'ninguna detectada')}
                </div>`);

            section.appendChild(totalsEl);
            section.appendChild(colInfoEl);

            if (type === 'text') {
                const textDiv = document.createElement('div');
                textDiv.className = 'ae-text-view';

                data.labels.forEach((label, i) => {
                    const g    = data.gastos[i];
                    const b    = data.beneficios[i];
                    const n    = b - g;
                    const isOk = n >= 0;
                    const block = doc(`
                        <div class="ae-month-block">
                            <h3>${esc(label)}</h3>
                            <div class="ae-stat-row">
                                <span class="ae-stat-label">Gastos</span>
                                <span class="ae-stat-val neg">-${fmt(g)} €</span>
                            </div>
                            <div class="ae-stat-row">
                                <span class="ae-stat-label">Beneficios totales</span>
                                <span class="ae-stat-val pos">+${fmt(b)} €</span>
                            </div>
                            <div class="ae-stat-row">
                                <span class="ae-stat-label">Resultado</span>
                                <span class="ae-stat-val ${isOk ? 'pos' : 'neg'}">
                                    ${isOk ? 'Beneficios' : 'Pérdidas'}: ${isOk ? '+' : ''}${fmt(n)} €
                                </span>
                            </div>
                        </div>`);
                    textDiv.appendChild(block);
                });

                section.appendChild(textDiv);
            } else {
                const wrap   = document.createElement('div');
                wrap.className = 'ae-canvas-wrap';
                const canvas = document.createElement('canvas');
                canvas.id    = 'aeChart_' + Math.random().toString(36).slice(2);
                wrap.appendChild(canvas);
                section.appendChild(wrap);

                const ctx = canvas.getContext('2d');

                const colG  = 'rgba(220,38,38,0.75)';
                const colB  = 'rgba(22,163,74,0.75)';
                const colGb = 'rgba(220,38,38,1)';
                const colBb = 'rgba(22,163,74,1)';

                let ci;
                if (type === 'bar') {
                    ci = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [
                                { label: 'Gastos', data: data.gastos, backgroundColor: colG, borderColor: colGb, borderWidth: 1 },
                                { label: 'Beneficios', data: data.beneficios, backgroundColor: colB, borderColor: colBb, borderWidth: 1 }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'top' },
                                tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ': ' + fmt(ctx.raw) + ' €' } }
                            },
                            scales: { y: { beginAtZero: true, ticks: { callback: v => fmt(v) + ' €' } } }
                        }
                    });
                } else if (type === 'pie') {
                    const total = totalG + totalB || 1;
                    ci = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: ['Gastos', 'Beneficios'],
                            datasets: [{ data: [round2(totalG), round2(totalB)], backgroundColor: [colG, colB], borderColor: [colGb, colBb], borderWidth: 1 }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'top' },
                                tooltip: { callbacks: { label: ctx => { const pct = ((ctx.raw / total) * 100).toFixed(1); return ' ' + fmt(ctx.raw) + ' € (' + pct + '%)'; } } }
                            }
                        }
                    });
                } else if (type === 'line') {
                    ci = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                { label: 'Gastos', data: data.gastos, borderColor: colGb, backgroundColor: 'rgba(220,38,38,0.08)', tension: 0.35, fill: true, pointRadius: 4 },
                                { label: 'Beneficios', data: data.beneficios, borderColor: colBb, backgroundColor: 'rgba(22,163,74,0.08)', tension: 0.35, fill: true, pointRadius: 4 }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'top' },
                                tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ': ' + fmt(ctx.raw) + ' €' } }
                            },
                            scales: { y: { beginAtZero: true, ticks: { callback: v => fmt(v) + ' €' } } }
                        }
                    });
                }

                if (ci) chartInstances.push(ci);
            }

            multiWrap.appendChild(section);
        });
    }

    /* ============================================================
       Funciones auxiliares
    ============================================================ */
    function isDateLike(v) {
        if (v instanceof Date) return !isNaN(v.getTime());
        if (typeof v === 'string') {
            return /^\d{1,4}[-\/\.]\d{1,2}[-\/\.]\d{1,4}/.test(v.trim()) ||
                   /^\d{1,2}[-\/]\d{4}$/.test(v.trim());
        }
        return false;
    }

    function isNumericLike(v) {
        if (typeof v === 'number') return isFinite(v);
        if (typeof v === 'string') {
            const cleaned = v.replace(/[€$£%\s]/g, '').replace(',', '.');
            if (cleaned !== '' && isFinite(parseFloat(cleaned))) return true;
            const parts = v.split(/\s*\+\s*/);
            if (parts.length > 1) {
                return parts.every(p => {
                    const c = p.replace(/[€$£%\s]/g, '').replace(',', '.');
                    return c !== '' && isFinite(parseFloat(c));
                });
            }
            return false;
        }
        return false;
    }

    function parseNum(v) {
        if (v === null || v === undefined || v === '') return NaN;
        if (typeof v === 'number') return v;
        if (typeof v === 'string') {
            const parts = v.split(/\s*\+\s*/);
            if (parts.length > 1) {
                let sum = 0;
                for (const part of parts) {
                    const c = part.replace(/[€$£%\s]/g, '').replace(',', '.');
                    const n = parseFloat(c);
                    if (!isFinite(n)) return NaN;
                    sum += n;
                }
                return sum;
            }
            const cleaned = v.replace(/[€$£%\s]/g, '').replace(',', '.');
            return parseFloat(cleaned);
        }
        return NaN;
    }

    function getMonthKey(v) {
        if (v instanceof Date && !isNaN(v.getTime())) {
            return v.getFullYear() + '-' + String(v.getMonth() + 1).padStart(2, '0');
        }
        if (typeof v === 'string') {
            const d = new Date(v);
            if (!isNaN(d.getTime())) {
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            }
            const m = v.match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/);
            if (m) {
                const year = m[3].length === 2 ? '20' + m[3] : m[3];
                return year + '-' + m[2].padStart(2, '0');
            }
        }
        return null;
    }

    function formatMonth(key) {
        const MONTHS = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        const [y, m] = key.split('-');
        return MONTHS[parseInt(m, 10) - 1] + ' ' + y;
    }

    function fmt(n) {
        return Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    function doc(html) {
        const t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstElementChild;
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // Auto-cargar si hay un archivo preseleccionado desde la URL
    if (fileSelect.value) {
        loadBtn.disabled = false;
        setTimeout(() => {
            document.getElementById('excel-analizar').scrollIntoView({ behavior: 'smooth' });
            loadBtn.click();
        }, 150);
    }

})();
    </script>
</body>
</html>


