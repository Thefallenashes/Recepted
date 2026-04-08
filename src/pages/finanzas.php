<?php
require_once __DIR__ . '/includes/page_bootstrap.php';
require_once __DIR__ . '/../utils/schema.php';
require_once __DIR__ . '/../utils/currencies.php';
require_once __DIR__ . '/../utils/rates.php';

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

$comparatorCurrencies = [];

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

            if ($categoryName === '') {
                $tipo = 'error';
                $mensaje = 'El nombre de la categoría es necesario.';
            } else {
                try {
                       $stmt = $pdo->prepare('INSERT INTO expense_categories (user_id, type, name) VALUES (:user_id, :type, :name)');
                    $stmt->execute([
                        'user_id' => $userId,
                        'type' => 'mixed',
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
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('DELETE FROM transactions WHERE user_id = :user_id AND category_id = :category_id');
                    $stmt->execute([
                        'user_id' => $userId,
                        'category_id' => $deleteCategoryId,
                    ]);
                    $deletedTransactions = (int)$stmt->rowCount();

                    $stmt = $pdo->prepare('DELETE FROM expense_categories WHERE id = :id AND user_id = :user_id');
                    $stmt->execute([
                        'id' => $deleteCategoryId,
                        'user_id' => $userId,
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $pdo->commit();
                        $tipo = 'exito';
                        $mensaje = 'Categoría eliminada correctamente junto con ' . $deletedTransactions . ' transacción(es) asociada(s).';
                    } else {
                        $pdo->rollBack();
                        $tipo = 'error';
                        $mensaje = 'No se pudo eliminar la categoría seleccionada.';
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
        }

        if ($action === 'add_transaction') {
            $type = 'expense';

            $amount = (float)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);

            if ($amount <= 0 || $categoryId <= 0) {
                $tipo = 'error';
                $mensaje = 'Debes seleccionar una categoría y un importe mayor que 0.';
            } else {
                $categoryIdToUse = $categoryId;
                $category = categorize_transaction($description, $type);

                if ($categoryIdToUse !== null) {
                    $stmt = $pdo->prepare('SELECT name FROM expense_categories WHERE id = :id AND user_id = :user_id LIMIT 1');
                    $stmt->execute([
                        'id' => $categoryIdToUse,
                        'user_id' => $userId,
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
    $stmt = $pdo->prepare('SELECT id, name, color, type FROM expense_categories WHERE user_id = :user_id ORDER BY id ASC');
    $stmt->execute(['user_id' => $userId]);
    $personalCategories = $stmt->fetchAll();
    $expenseCategories = $personalCategories;
    $incomeCategories = [];

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

    // Obtener saldo neto agrupado por categoría personalizada (ingresos - gastos)
    $stmt = $pdo->prepare("
        SELECT 
            ec.id,
            ec.name,
            COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END), 0) AS total,
            COUNT(t.id) AS transaction_count
                FROM expense_categories ec
        LEFT JOIN transactions t ON ec.id = t.category_id
        WHERE ec.user_id = :user_id
           GROUP BY ec.id, ec.name
        ORDER BY ec.id ASC
    ");
    $stmt->execute(['user_id' => $userId]);
    $expensesByPersonalCategory = $stmt->fetchAll();

    // Obtener transacciones por categoría personalizada para expandir
    $transactionsByCategory = [];
    if (!empty($expensesByPersonalCategory)) {
        foreach ($expensesByPersonalCategory as $cat) {
            $catId = (int)$cat['id'];
            $stmt = $pdo->prepare("
                SELECT id, type, amount, description, created_at 
                FROM transactions 
                WHERE category_id = :category_id
                ORDER BY created_at ASC, id ASC
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
            foreach ($transactionsByCategory[$catId] as $txItem) {
                $signedAmount = (($txItem['type'] ?? 'expense') === 'income')
                    ? (float)$txItem['amount']
                    : -(float)$txItem['amount'];
                $runningTotal += $signedAmount;
                $series[] = [
                    'label' => substr((string)$txItem['created_at'], 0, 10),
                    'value' => $runningTotal,
                    'tx_type' => (string)($txItem['type'] ?? 'expense'),
                    'tx_amount' => (float)($txItem['amount'] ?? 0),
                    'tx_signed_amount' => $signedAmount,
                    'tx_description' => (string)($txItem['description'] ?? ''),
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

    $supportedCurrencies = get_supported_currencies();
    $currencyCatalog = get_currency_list();
    $currencyByCode = [];
    foreach ($currencyCatalog as $item) {
        $currencyByCode[$item['code']] = $item;
    }
    if (is_array($supportedCurrencies)) {
        foreach ($supportedCurrencies as $code => $apiName) {
            $meta = $currencyByCode[$code] ?? null;
            $comparatorCurrencies[] = [
                'code' => $code,
                'name' => $meta['name'] ?? (is_string($apiName) ? $apiName : $code),
                'symbol' => $meta['symbol'] ?? $code,
            ];
        }
        usort($comparatorCurrencies, static function ($a, $b) {
            return strcmp($a['code'], $b['code']);
        });
    }
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
        'home_href' => 'landing.php',
        'logout_href' => 'scripts/logout.php',
        'nav_items' => [
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
                <?php $cur_symbol = get_currency_symbol($finanzas['currency'] ?? 'EUR'); ?>
                <div class="stat-box primary">
                    <div class="stat-label">Balance</div>
                    <p class="stat-value"><?php echo htmlspecialchars($cur_symbol) . ' ' . number_format((float)$finanzas['balance'], 2); ?></p>
                    <small><?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></small>
                </div>
                <div class="stat-box success">
                    <div class="stat-label">Ingresos</div>
                    <p class="stat-value"><?php echo htmlspecialchars($cur_symbol) . ' ' . number_format((float)$finanzas['income'], 2); ?></p>
                    <small><?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></small>
                </div>
                <div class="stat-box danger">
                    <div class="stat-label">Gastos</div>
                    <p class="stat-value"><?php echo htmlspecialchars($cur_symbol) . ' ' . number_format((float)$finanzas['expenses'], 2); ?></p>
                    <small><?php echo htmlspecialchars($finanzas['currency'] ?? 'EUR'); ?></small>
                </div>
            </div>

            <section class="finance-section">
                        <h2>Categorías</h2>
                        <div class="panel-form section-surface">
                            <?php if (empty($personalCategories)): ?>
                            <?php else: ?>
                                <div class="category-selector-row">
                                    <select id="selected_category_id" name="selected_category_id" class="category-select" onchange="renderCategoryLineChart()">
                                        <option value="" selected disabled>Elije una categoria</option>
                                        <?php foreach ($personalCategories as $cat): ?>
                                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <form method="POST" action="" onsubmit="return confirm('¿Eliminar la categoría seleccionada?');">
                                        <?php echo csrf_input_field(); ?>
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="selected_category_id" id="delete_selected_category_id" value="">
                                        <button type="submit" class="tx-delete-btn" id="delete_selected_category_btn" disabled>Eliminar categoría</button>
                                    </form>
                                </div>
                                <div id="categoryLineChartWrap" class="category-chart-wrap">
                                    <canvas id="categoryLineChart" height="57"></canvas>
                                </div>
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
                                                            <span class="tx-amount"><?php echo (($tx['type'] ?? 'expense') === 'income' ? '+' : '-') . number_format((float)$tx['amount'], 2); ?></span>
                                                            <form method="POST" action="" class="js-delete-transaction-form" style="display: inline; margin: 0;">
                                                                <?php echo csrf_input_field(); ?>
                                                                <input type="hidden" name="action" value="delete_transaction">
                                                                <input type="hidden" name="transaction_id" value="<?php echo (int)$tx['id']; ?>">
                                                                <button type="submit" class="tx-delete-btn" onclick="return confirm('¿Eliminar esta transacción?');">Eliminar</button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="category-stats">
                                                    <strong>Balance neto: <?php echo ($total >= 0 ? '+' : '-') . number_format(abs($total), 2); ?> EUR</strong> |
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
                    </section>

                    <section class="finance-section">
                        <h2>Metas de ahorro</h2>
                        <?php if (empty($goals)): ?>
                            <p>No tienes metas registradas.</p>
                        <?php else: ?>
                            <div class="goals-grid">
                                <?php foreach ($goals as $goal): ?>
                                    <?php
                                        $goalTarget = (float)$goal['target_amount'];
                                        $goalCurrent = (float)$goal['current_amount'];
                                        $goalProgress = $goalTarget > 0 ? min(100, ($goalCurrent / $goalTarget) * 100) : 0;
                                    ?>
                                    <article class="goal-card">
                                        <div class="goal-card-head">
                                            <h3><?php echo htmlspecialchars($goal['name']); ?></h3>
                                            <span class="goal-progress-label"><?php echo number_format($goalProgress, 2); ?>%</span>
                                        </div>
                                        <div class="goal-values-row">
                                            <span>Actual: <?php echo number_format($goalCurrent, 2); ?></span>
                                            <span>Objetivo: <?php echo number_format($goalTarget, 2); ?></span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo number_format($goalProgress, 2, '.', ''); ?>%;"></div>
                                        </div>
                                        <form method="POST" action="" class="goal-progress-form">
                                            <?php echo csrf_input_field(); ?>
                                            <input type="hidden" name="action" value="add_goal_progress">
                                            <input type="hidden" name="goal_id" value="<?php echo (int)$goal['id']; ?>">
                                            <input type="number" name="add_amount" min="0.01" step="0.01" placeholder="+ importe" required>
                                            <button type="submit">Añadir</button>
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="finance-section">
                        <h2>Últimas transacciones</h2>
                        <?php if (empty($transactions)): ?>
                            <p>No hay transacciones registradas todavía.</p>
                        <?php else: ?>
                            <div class="table-scroll-wrap">
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
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="finance-section">
                        <h2 class="mt-4 mb-2">Comparador de monedas</h2>
                        <div class="currency-comparator-block">
                            <div class="form-group">
                                <label for="tx-selector">Selecciona una transacción:</label>
                                <select id="tx-selector" class="tx-selector">
                                    <option value="">— Elige una transacción —</option>
                                    <?php foreach ($transactions as $tx): ?>
                                        <option value="<?php echo (int)$tx['id']; ?>" data-amount="<?php echo htmlspecialchars($tx['amount']); ?>" data-currency="<?php echo htmlspecialchars($finanzas['currency']); ?>">
                                            <?php echo htmlspecialchars(substr($tx['created_at'], 0, 10)); ?> - <?php echo htmlspecialchars($tx['description'] ?? $tx['display_category']); ?> (<?php echo number_format((float)$tx['amount'], 2); ?> <?php echo htmlspecialchars($finanzas['currency']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="currency-comparator-info" id="comparator-info" style="display: none;">
                                <div class="comparator-original">
                                    <p class="comparator-label">Monto original:</p>
                                    <p class="comparator-value">
                                        <span id="original-symbol"><?php echo htmlspecialchars(get_currency_symbol($finanzas['currency'])); ?></span>
                                        <span id="original-amount">0.00</span>
                                        <span id="original-currency"><?php echo htmlspecialchars($finanzas['currency']); ?></span>
                                    </p>
                                </div>

                                <div class="comparator-arrow">→</div>

                                <div class="comparator-target">
                                    <label for="target-currency-select">Convertir a:</label>
                                    <select id="target-currency-select" class="target-currency-select">
                                        <option value="">— Elige moneda destino —</option>
                                        <?php foreach ($comparatorCurrencies as $curr): ?>
                                            <option value="<?php echo htmlspecialchars($curr['code']); ?>"><?php echo htmlspecialchars($curr['code']); ?> - <?php echo htmlspecialchars($curr['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="comparator-result" id="comparator-result" style="display: none;">
                                    <p class="comparator-label">Equivalente en:</p>
                                    <p class="comparator-value result-value">
                                        <span id="result-symbol">€</span>
                                        <span id="result-amount">0.00</span>
                                        <span id="result-currency">EUR</span>
                                    </p>
                                    <p class="comparator-rate" id="comparator-rate"></p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="finance-section">
                        <h2 class="mt-4 mb-2" id="excel-analizar">Analizar archivo Excel</h2>

                        <?php if (empty($excelUploads)): ?>
                            <p>No tienes archivos de Excel subidos aún.
                               <a href="scripts/upload.php">Haz click aquí para subir uno</a> y vuelve aquí para analizarlo.</p>
                        <?php else: ?>

                            <div class="panel-form section-surface">
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
            </section>

            <section class="finance-section">
                <h2 class="mt-4 mb-2">Nueva transacción</h2>
                <form method="POST" action="" class="panel-form section-surface multi-field-form">
                            <?php echo csrf_input_field(); ?>
                            <input type="hidden" name="action" value="add_transaction">
                            <div class="form-group">
                                <label for="amount">Importe</label>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="category_id">Categoría</label>
                                <select id="category_id" name="category_id" required>
                                    <option value="" selected disabled hidden></option>
                                    <?php foreach ($expenseCategories as $cat): ?>
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
            </section>

            <section class="finance-section">
                <h2>Crear categoria</h2>
                <form method="POST" action="" class="panel-form section-surface category-create-form">
                            <?php echo csrf_input_field(); ?>
                            <input type="hidden" name="action" value="add_category">
                            <div class="form-row">
                                <div class="form-group no-margin">
                                    <label for="category_name">Nombre</label>
                                    <input id="category_name" name="category_name" required>
                                </div>
                                <div class="category-create-action">
                                    <button class="btn category-create-btn" type="submit">Crear</button>
                                </div>
                            </div>
                </form>
            </section>

            <section class="finance-section">
                <h2>Crear meta de ahorro</h2>
                <form method="POST" action="" class="panel-form section-surface multi-field-form">
                            <?php echo csrf_input_field(); ?>
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
            </section>

        <?php else: ?>
            <p>No hay información financiera. Contacta con soporte.</p>
        <?php endif; ?>
        </div>
    </div>

    <script src="../js/sticky-menu-toggle.js" defer></script>
    <script src="../js/mobile-menu-enhancements.js" defer></script>
    <script src="../js/animation-manager.js" defer></script>
    <script>
        window.__csrfToken = <?php echo json_encode(get_csrf_token(), JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script>
        const categorySeries = <?php echo json_encode($chartSeriesByCategory, JSON_UNESCAPED_UNICODE); ?>;
        let categoryLineChart = null;

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

        function renderCategoryLineChart() {
            const selector = document.getElementById('selected_category_id');
            const deleteHidden = document.getElementById('delete_selected_category_id');
            const deleteButton = document.getElementById('delete_selected_category_btn');
            const canvas = document.getElementById('categoryLineChart');
            const chartWrap = document.getElementById('categoryLineChartWrap');

            if (!selector || !canvas) {
                return;
            }

            deleteHidden.value = selector.value;
            if (deleteButton) {
                deleteButton.disabled = selector.value === '';
            }

            if (categoryLineChart) {
                categoryLineChart.destroy();
                categoryLineChart = null;
            }

            if (selector.value === '') {
                return;
            }

            const points = categorySeries[selector.value] || [{ label: 'Sin datos', value: 0 }];
            const values = points.map((p) => Number(p.value || 0));
            const minVal = Math.min(...values);
            const maxVal = Math.max(...values);
            const yRange = Math.max(maxVal - minVal, 1);
            const yPadding = yRange * 0.12;
            const yMin = minVal - yPadding;
            const yMax = maxVal + yPadding;
            const yStep = yRange / 8;

            if (typeof Chart === 'undefined') {
                return;
            }

            const hasCategoryData = points.some((p) => Math.abs(Number(p.value || 0)) > 0);
            const chartHeight = hasCategoryData ? 190 : 57;

            // Mantener altura fija para evitar crecimiento acumulado del canvas.
            if (chartWrap) {
                chartWrap.style.height = `${chartHeight}px`;
                chartWrap.style.maxHeight = `${chartHeight}px`;
            }
            canvas.style.height = `${chartHeight}px`;
            canvas.style.maxHeight = `${chartHeight}px`;

            const ctx = canvas.getContext('2d');
            categoryLineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: points.map((p) => p.label || ''),
                    datasets: [{
                        label: 'Balance acumulado',
                        data: points.map((p) => Number(p.value || 0)),
                        borderColor: '#0ea5a8',
                        backgroundColor: 'rgba(14,165,168,0.12)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 3,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => {
                                    const i = items[0]?.dataIndex ?? 0;
                                    const p = points[i] || {};
                                    return p.label ? `Fecha: ${p.label}` : 'Transacción';
                                },
                                label: (ctxItem) => {
                                    const i = ctxItem.dataIndex;
                                    const p = points[i] || {};
                                    const txType = (p.tx_type || 'expense') === 'income' ? 'Ingreso' : 'Gasto';
                                    const signed = Number(p.tx_signed_amount || 0);
                                    const signedText = `${signed >= 0 ? '+' : '-'}${Math.abs(signed).toFixed(2)}`;
                                    const desc = String(p.tx_description || '').trim();
                                    const lines = [
                                        `${txType}: ${signedText}`,
                                        `Balance acumulado: ${Number(p.value || 0).toFixed(2)}`,
                                    ];
                                    if (desc !== '') {
                                        lines.push(`Detalle: ${desc}`);
                                    }
                                    return lines;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            min: yMin,
                            max: yMax,
                            beginAtZero: false,
                            ticks: {
                                stepSize: yStep,
                            },
                        }
                    }
                }
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
    let uploadId       = null;

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
        uploadId = fileSelect.value;
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

        // ======= GRÁFICO CONSOLIDADO =======
        if (isMulti || sheetsData.length > 0) {
            renderConsolidatedChart(sheetsData, type);
        }

        // ======= GRÁFICOS POR HOJA =======
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
                    const totalG = data.gastos.reduce((a, b) => a + b, 0);
                    const totalB = data.beneficios.reduce((a, b) => a + b, 0);
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
       GRÁFICO CONSOLIDADO
    ============================================================ */
    function renderConsolidatedChart(sheetsData, type) {
        // Calcular totales consolidados
        let totalGastosConsolidado = 0;
        let totalBeneficiosConsolidado = 0;

        sheetsData.forEach(data => {
            totalGastosConsolidado += data.gastos.reduce((a, b) => a + b, 0);
            totalBeneficiosConsolidado += data.beneficios.reduce((a, b) => a + b, 0);
        });

        const balanceNeto = totalBeneficiosConsolidado - totalGastosConsolidado;
        const balanceClass = balanceNeto > 0 ? 'pos' : balanceNeto < 0 ? 'neg' : 'neu';
        const balanceLabel = balanceNeto >= 0 ? 'Ahorrado: +' : 'Pérdida: ';

        // Crear contenedor del consolidado
        const consolidadoSection = document.createElement('div');
        consolidadoSection.className = 'ae-consolidated-section';
        consolidadoSection.style.marginBottom = '30px';
        consolidadoSection.style.padding = '20px';
        consolidadoSection.style.backgroundColor = '#f9fafb';
        consolidadoSection.style.borderRadius = '8px';
        consolidadoSection.style.borderLeft = '4px solid #0ea5a8';

        const title = document.createElement('h2');
        title.textContent = 'Análisis Consolidado';
        title.style.marginTop = '0';
        title.style.marginBottom = '15px';
        consolidadoSection.appendChild(title);

        // Totales
        const totalsEl = doc(`
            <div class="ae-totals" style="margin-bottom: 20px;">
                <div class="ae-total-item">
                    <span class="ae-total-label">Gastos totales (todas las hojas)</span>
                    <span class="ae-total-value neg">-${fmt(totalGastosConsolidado)} €</span>
                </div>
                <div class="ae-total-item">
                    <span class="ae-total-label">Beneficios totales (todas las hojas)</span>
                    <span class="ae-total-value pos">+${fmt(totalBeneficiosConsolidado)} €</span>
                </div>
                <div class="ae-total-item">
                    <span class="ae-total-label">Balance Total</span>
                    <span class="ae-total-value ${balanceClass}" style="font-size: 1.2em; font-weight: bold;">
                        ${balanceLabel}${fmt(Math.abs(balanceNeto))} €
                    </span>
                </div>
            </div>`);

        consolidadoSection.appendChild(totalsEl);

        // Gráfico consolidado
        if (type !== 'text') {
            const canvasWrap = document.createElement('div');
            canvasWrap.className = 'ae-canvas-wrap';
            const canvas = document.createElement('canvas');
            canvas.id = 'aeConsolidatedChart';
            canvasWrap.appendChild(canvas);
            consolidadoSection.appendChild(canvasWrap);

            const ctx = canvas.getContext('2d');

            const colG  = 'rgba(220,38,38,0.75)';
            const colB  = 'rgba(22,163,74,0.75)';
            const colGb = 'rgba(220,38,38,1)';
            const colBb = 'rgba(22,163,74,1)';

            if (type === 'bar') {
                const ci = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Total'],
                        datasets: [
                            { label: 'Gastos', data: [totalGastosConsolidado], backgroundColor: colG, borderColor: colGb, borderWidth: 1 },
                            { label: 'Beneficios', data: [totalBeneficiosConsolidado], backgroundColor: colB, borderColor: colBb, borderWidth: 1 }
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
                chartInstances.push(ci);
            } else if (type === 'pie') {
                const total = totalGastosConsolidado + totalBeneficiosConsolidado || 1;
                const ci = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Gastos', 'Beneficios'],
                        datasets: [{ data: [round2(totalGastosConsolidado), round2(totalBeneficiosConsolidado)], backgroundColor: [colG, colB], borderColor: [colGb, colBb], borderWidth: 1 }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: { callbacks: { label: ctx => { const pct = ((ctx.raw / total) * 100).toFixed(1); return ' ' + fmt(ctx.raw) + ' € (' + pct + '%)'; } } }
                        }
                    }
                });
                chartInstances.push(ci);
            } else if (type === 'line') {
                const ci = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Total'],
                        datasets: [
                            { label: 'Gastos', data: [totalGastosConsolidado], borderColor: colGb, backgroundColor: 'rgba(220,38,38,0.08)', tension: 0.35, fill: true, pointRadius: 4 },
                            { label: 'Beneficios', data: [totalBeneficiosConsolidado], borderColor: colBb, backgroundColor: 'rgba(22,163,74,0.08)', tension: 0.35, fill: true, pointRadius: 4 }
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
                chartInstances.push(ci);
            }
        }

        // Botón guardar
        const btnContainer = document.createElement('div');
        btnContainer.style.marginTop = '20px';
        btnContainer.style.textAlign = 'center';

        const saveBtn = document.createElement('button');
        saveBtn.textContent = 'Guardar Análisis';
        saveBtn.className = 'btn';
        saveBtn.style.padding = '12px 30px';
        saveBtn.style.fontSize = '1em';
        saveBtn.onclick = async () => {
            await showConfirmationModal(sheetsData, totalGastosConsolidado, totalBeneficiosConsolidado, balanceNeto);
        };

        btnContainer.appendChild(saveBtn);
        consolidadoSection.appendChild(btnContainer);

        // Insertar al inicio
        chartArea.insertBefore(consolidadoSection, chartArea.firstChild);
    }

    /* ============================================================
       MODAL DE CONFIRMACIÓN
    ============================================================ */
    async function showConfirmationModal(sheetsData, totalGastos, totalBeneficios, balanceNeto) {
        const filename = fileSelect.options[fileSelect.selectedIndex].textContent;
        const categoryName = filename.split('.')[0];

        // Crear modal
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        `;

        const content = document.createElement('div');
        content.style.cssText = `
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        `;

        const title = document.createElement('h2');
        title.textContent = 'Confirmar Guardado de Análisis';
        title.style.marginTop = '0';

        const summary = document.createElement('div');
        summary.style.marginBottom = '20px';
        summary.style.padding = '15px';
        summary.style.backgroundColor = '#f0f9ff';
        summary.style.borderRadius = '6px';
        summary.innerHTML = `
            <p><strong>Archivo:</strong> ${esc(filename)}</p>
            <p><strong>Categoría a crear:</strong> ${esc(categoryName)}</p>
            <p><strong>Número de hojas:</strong> ${sheetsData.length}</p>
            <p><strong>Gastos totales:</strong> <span style="color: #dc2626;">-${fmt(totalGastos)} €</span></p>
            <p><strong>Beneficios totales:</strong> <span style="color: #16a34a;">+${fmt(totalBeneficios)} €</span></p>
            <p style="font-weight: bold; font-size: 1.1em;">
                <strong>Balance Total:</strong> 
                <span style="color: ${balanceNeto > 0 ? '#16a34a' : balanceNeto < 0 ? '#dc2626' : '#666'};">
                    ${balanceNeto >= 0 ? 'Ahorrado: +' : 'Pérdida: -'}${fmt(Math.abs(balanceNeto))} €
                </span>
            </p>
        `;

        const details = document.createElement('div');
        details.style.marginBottom = '20px';
        details.style.padding = '15px';
        details.style.backgroundColor = '#f9fafb';
        details.style.borderRadius = '6px';
        details.innerHTML = `
            <p style="margin: 0 0 10px 0;"><strong>Transacciones que se crearán:</strong></p>
            <ul style="margin: 0; padding-left: 20px;">
                ${sheetsData.map((sheet, i) => {
                    const gastos = sheet.gastos.reduce((a, b) => a + b, 0);
                    const beneficios = sheet.beneficios.reduce((a, b) => a + b, 0);
                    const balance = beneficios - gastos;
                    if (balance === 0) {
                        return '';
                    }
                    const txType = balance > 0 ? 'Ingreso' : 'Gasto';
                    return `<li>${esc(categoryName)}-${esc(sheet.sheetName)} (${txType}: ${balance > 0 ? '+' : '-'}${fmt(Math.abs(balance))} €)</li>`;
                }).join('')}
            </ul>
        `;

        const warning = document.createElement('p');
        warning.style.cssText = `
            padding: 10px;
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            margin: 15px 0;
            font-size: 0.9em;
        `;
        warning.innerHTML = '⚠️ <strong>Nota:</strong> Esta acción creará las transacciones y no se puede deshacer directamente desde aquí.';

        const buttons = document.createElement('div');
        buttons.style.cssText = `
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        `;

        const cancelBtn = document.createElement('button');
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.className = 'btn';
        cancelBtn.style.cssText = `
            background: #e5e7eb;
            color: #374151;
            cursor: pointer;
        `;
        cancelBtn.onclick = () => modal.remove();

        const confirmBtn = document.createElement('button');
        confirmBtn.textContent = 'Guardar Análisis';
        confirmBtn.className = 'btn';
        confirmBtn.style.cssText = `
            background: #0ea5a8;
            color: white;
            cursor: pointer;
        `;
        confirmBtn.onclick = async () => {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Guardando...';
            await saveAnalysis(sheetsData, filename);
            modal.remove();
        };

        buttons.appendChild(cancelBtn);
        buttons.appendChild(confirmBtn);

        content.appendChild(title);
        content.appendChild(summary);
        content.appendChild(details);
        content.appendChild(warning);
        content.appendChild(buttons);
        modal.appendChild(content);
        document.body.appendChild(modal);
    }

    /* ============================================================
       GUARDAR ANÁLISIS
    ============================================================ */
    async function saveAnalysis(sheetsData, filename) {
        try {
            const payload = {
                filename: filename,
                sheets: sheetsData.map(sheet => ({
                    sheetName: sheet.sheetName,
                    gastos_total: sheet.gastos.reduce((a, b) => a + b, 0),
                    beneficios_total: sheet.beneficios.reduce((a, b) => a + b, 0)
                }))
            };

            const response = await fetch('scripts/save_excel_analysis.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=UTF-8',
                    'X-CSRF-Token': window.__csrfToken || ''
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                alert(`✅ Análisis guardado correctamente!\n\nBalance total: ${data.totalBalance >= 0 ? '+' : ''}${fmt(data.totalBalance)} €\nTransacciones creadas: ${data.transactionsCreated.length}`);
                
                // Recargar la página para actualizar datos
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                alert('❌ Error al guardar: ' + (data.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Save error:', error);
            alert('❌ Error al guardar el análisis: ' + error.message);
        }
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
        uploadId = fileSelect.value;
        setTimeout(() => {
            document.getElementById('excel-analizar').scrollIntoView({ behavior: 'smooth' });
            loadBtn.click();
        }, 150);
    }

})();
    </script>

    <!-- Currency Comparator Script -->
    <script>
    (function () {
        const txSelector = document.getElementById('tx-selector');
        const targetCurrencySelect = document.getElementById('target-currency-select');
        const comparatorInfo = document.getElementById('comparator-info');
        const comparatorResult = document.getElementById('comparator-result');
        const originalAmount = document.getElementById('original-amount');
        const originalSymbol = document.getElementById('original-symbol');
        const originalCurrency = document.getElementById('original-currency');
        const resultAmount = document.getElementById('result-amount');
        const resultSymbol = document.getElementById('result-symbol');
        const resultCurrency = document.getElementById('result-currency');
        const comparatorRate = document.getElementById('comparator-rate');

        // Helper to get currency symbol using PHP data
        const currencySymbols = <?php 
            $symbolMap = [];
            foreach (get_currency_list() as $c) {
                $symbolMap[$c['code']] = $c['symbol'] ?: $c['code'];
            }
            echo json_encode($symbolMap, JSON_UNESCAPED_UNICODE);
        ?>;

        function showInfo() {
            if (txSelector.value) {
                comparatorInfo.style.display = 'grid';
            } else {
                comparatorInfo.style.display = 'none';
                comparatorResult.style.display = 'none';
            }
        }

        function updateDisplay() {
            const selected = txSelector.options[txSelector.selectedIndex];
            if (!selected || !txSelector.value) {
                showInfo();
                return;
            }

            const amount = parseFloat(selected.dataset.amount) || 0;
            const currency = selected.dataset.currency || 'EUR';

            originalAmount.textContent = amount.toFixed(2);
            originalSymbol.textContent = currencySymbols[currency] || currency;
            originalCurrency.textContent = currency;

            showInfo();

            // Reset target and result when transaction changes
            targetCurrencySelect.value = '';
            comparatorResult.style.display = 'none';
        }

        async function performConversion() {
            if (!txSelector.value || !targetCurrencySelect.value) {
                comparatorResult.style.display = 'none';
                return;
            }

            const selected = txSelector.options[txSelector.selectedIndex];
            const amount = parseFloat(selected.dataset.amount) || 0;
            const fromCurrency = selected.dataset.currency || 'EUR';
            const toCurrency = targetCurrencySelect.value;

            if (fromCurrency === toCurrency) {
                resultAmount.textContent = amount.toFixed(2);
                resultSymbol.textContent = currencySymbols[toCurrency] || toCurrency;
                resultCurrency.textContent = toCurrency;
                comparatorRate.textContent = 'Tasa: 1:1 (misma moneda)';
                comparatorResult.style.display = 'block';
                return;
            }

            try {
                const response = await fetch('scripts/convert_currency.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': window.__csrfToken || ''
                    },
                    body: new URLSearchParams({
                        amount: amount,
                        from: fromCurrency,
                        to: toCurrency
                    })
                });

                const data = await response.json();

                if (data.success) {
                    resultAmount.textContent = parseFloat(data.converted).toFixed(2);
                    resultSymbol.textContent = currencySymbols[toCurrency] || toCurrency;
                    resultCurrency.textContent = toCurrency;
                    comparatorRate.textContent = `Tasa: 1 ${fromCurrency} = ${(data.rate).toFixed(4)} ${toCurrency}`;
                    comparatorResult.style.display = 'block';
                } else {
                    comparatorResult.style.display = 'none';
                    alert('Error en la conversión: ' + (data.error || 'Desconocido'));
                }
            } catch (error) {
                comparatorResult.style.display = 'none';
                console.error('Conversion error:', error);
            }
        }

        txSelector.addEventListener('change', updateDisplay);
        targetCurrencySelect.addEventListener('change', performConversion);

        // Init
        showInfo();
    })();
    </script>
</body>
</html>