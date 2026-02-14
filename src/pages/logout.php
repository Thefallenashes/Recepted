<?php
session_start();

require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

$user_id = $_SESSION['usuario_id'] ?? null;

// Limpiar tokens persistentes si los hay
try {
	$pdo = getPDO();
	if ($user_id !== null) {
		clear_remember_tokens($pdo, (int)$user_id);
	} else {
		clear_remember_tokens($pdo, null);
	}
} catch (Exception $e) {
	// noop
}

// Destruir la sesión
session_unset();
session_destroy();

// Redirigir al login
header('Location: login.php');
exit();
