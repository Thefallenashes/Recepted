<?php
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../../utils/rates.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

header('Content-Type: application/json; charset=UTF-8');

try {
    $amount = (float)($_POST['amount'] ?? 0);
    $from = trim($_POST['from'] ?? '');
    $to = trim($_POST['to'] ?? '');

    // Validate inputs
    if ($amount <= 0 || !$from || !$to) {
        echo json_encode([
            'success' => false,
            'error' => 'Parámetros inválidos'
        ]);
        exit();
    }

    // Perform conversion
    $converted = convert_currency($amount, $from, $to);

    if ($converted === null) {
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo obtener la tasa de cambio'
        ]);
        exit();
    }

    $rate = get_exchange_rate($from, $to);

    echo json_encode([
        'success' => true,
        'converted' => $converted,
        'rate' => $rate,
        'from' => $from,
        'to' => $to,
        'original' => $amount
    ]);
} catch (Exception $e) {
    error_log('Currency conversion error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la conversión'
    ]);
}
