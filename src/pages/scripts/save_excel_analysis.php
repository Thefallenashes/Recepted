<?php
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../../utils/schema.php';

$userId = require_authenticated_user('login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

header('Content-Type: application/json; charset=UTF-8');

try {
    // Recibir datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['filename']) || !isset($input['sheets'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Datos inválidos: se requieren filename y sheets'
        ]);
        exit();
    }

    $filename = trim($input['filename']);
    $sheets = $input['sheets'];

    if (empty($filename) || !is_array($sheets) || empty($sheets)) {
        echo json_encode([
            'success' => false,
            'error' => 'Archivo o hojas vacíos'
        ]);
        exit();
    }

    // Extraer nombre del archivo sin extensión
    $categoryName = pathinfo($filename, PATHINFO_FILENAME);

    $pdo = getPDO();
    assert_finanzas_schema($pdo);

    // 1. Crear o obtener la categoría
    $stmt = $pdo->prepare('SELECT id FROM expense_categories WHERE user_id = :user_id AND name = :name LIMIT 1');
    $stmt->execute([
        'user_id' => $userId,
        'name' => $categoryName,
    ]);
    $categoryId = $stmt->fetchColumn();

    if (!$categoryId) {
        $stmt = $pdo->prepare('INSERT INTO expense_categories (user_id, type, name) VALUES (:user_id, :type, :name)');
        $stmt->execute([
            'user_id' => $userId,
            'type' => 'mixed',
            'name' => $categoryName
        ]);
        $categoryId = $pdo->lastInsertId();
    }

    // 2. Crear una transacción neta por hoja
    $totalBalance = 0;
    $transactionsCreated = [];

    $insertTxStmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, category, category_id, description) VALUES (:user_id, :type, :amount, :category, :category_id, :description)');

    foreach ($sheets as $sheet) {
        $sheetName = trim($sheet['sheetName'] ?? '');
        $gastos = (float)($sheet['gastos_total'] ?? 0);
        $beneficios = (float)($sheet['beneficios_total'] ?? 0);
        $balance = $beneficios - $gastos;

        if (empty($sheetName)) {
            continue;
        }

        $txType = 'expense';
        $txAmount = abs($balance);

        if ($balance > 0) {
            $txType = 'income';
            $txAmount = $balance;
        } elseif ($balance < 0) {
            $txType = 'expense';
            $txAmount = abs($balance);
        }

        // Si el balance es 0 no crea transacción de hoja
        if ($txAmount > 0) {
            $insertTxStmt->execute([
                'user_id' => $userId,
                'type' => $txType,
                'amount' => $txAmount,
                'category' => $categoryName,
                'category_id' => $categoryId,
                'description' => $categoryName . '-' . $sheetName,
            ]);

            $transactionsCreated[] = [
                'sheet' => $sheetName,
                'type' => $txType,
                'amount' => $txAmount,
            ];
        }

        $totalBalance += $balance;
    }

    // 4. Actualizar balance en tabla finanzas
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = :user_id AND type = 'income'");
    $stmt->execute(['user_id' => $userId]);
    $income = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = :user_id AND type = 'expense'");
    $stmt->execute(['user_id' => $userId]);
    $expenses = (float)$stmt->fetchColumn();

    $newBalance = $income - $expenses;

    $stmt = $pdo->prepare('UPDATE finanzas SET balance = :balance, income = :income, expenses = :expenses WHERE user_id = :user_id');
    $stmt->execute([
        'balance' => $newBalance,
        'income' => $income,
        'expenses' => $expenses,
        'user_id' => $userId
    ]);

    if (function_exists('record_audit_log')) {
        record_audit_log($pdo, 'excel_analysis_saved', 'info', "Análisis de Excel guardado: $filename con " . count($sheets) . " hojas");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Análisis guardado correctamente',
        'categoryId' => $categoryId,
        'categoryName' => $categoryName,
        'totalBalance' => $totalBalance,
        'transactionsCreated' => $transactionsCreated,
        'newBalance' => $newBalance,
        'newIncome' => $income,
        'newExpenses' => $expenses
    ]);

} catch (PDOException $e) {
    error_log('Excel analysis error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar el análisis: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Excel analysis error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error inesperado: ' . $e->getMessage()
    ]);
}
