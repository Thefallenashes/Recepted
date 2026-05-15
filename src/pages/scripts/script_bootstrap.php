<?php

require_once __DIR__ . '/../includes/auth_bootstrap.php';

get_csrf_token();
enforce_csrf_protection('http403');

/**
 * Emite una redirección HTTP y termina la ejecución.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit();
}

/**
 * Exige sesión autenticada para scripts y devuelve el user_id actual.
 */
function require_script_user(string $mode = 'redirect', string $redirectTo = '../login.php'): int
{
    if (isset($_SESSION['usuario_id'])) {
        return (int)$_SESSION['usuario_id'];
    }

    if ($mode === 'http403') {
        http_response_code(403);
        exit('Acceso denegado.');
    }

    return require_authenticated_user($redirectTo);
}

/**
 * Obtiene un upload por id si el usuario actual tiene acceso.
 *
 * @return array<string, mixed>|null
 */
function get_accessible_upload(PDO $pdo, int $uploadId, int $userId): ?array
{
    if ($uploadId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $uploadId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $isAdmin = function_exists('can_manage_all_resources') && can_manage_all_resources();
    if ((int)$row['user_id'] !== $userId && !$isAdmin) {
        return null;
    }

    return $row;
}

/**
 * Resuelve una ruta de upload asegurando que está dentro de src/uploads.
 */
function resolve_upload_realpath(string $relativePath): ?string
{
    $base = realpath(dirname(__DIR__, 2) . '/uploads');
    if ($base === false) {
        return null;
    }

    $candidates = [];
    $normalizedPath = str_replace('\\', '/', trim($relativePath));

    if ($normalizedPath === '') {
        return null;
    }

    if (preg_match('/^[A-Za-z]:\//', $normalizedPath) === 1 || str_starts_with($normalizedPath, '/')) {
        $candidates[] = $normalizedPath;
    }

    $normalizedPath = ltrim($normalizedPath, '/');

    if ($normalizedPath !== '') {
        $candidates[] = dirname(__DIR__, 2) . '/' . $normalizedPath;
        $candidates[] = dirname(__DIR__, 2) . '/uploads/' . basename($normalizedPath);

        if (str_starts_with($normalizedPath, 'src/uploads/')) {
            $candidates[] = dirname(__DIR__, 2) . '/' . substr($normalizedPath, 4);
        }

        if (str_starts_with($normalizedPath, 'uploads/')) {
            $candidates[] = dirname(__DIR__, 2) . '/' . $normalizedPath;
        }
    }

    foreach (array_unique($candidates) as $candidatePath) {
        $realFile = realpath($candidatePath);
        if ($realFile === false || !is_file($realFile)) {
            continue;
        }

        if (strpos($realFile, $base) !== 0) {
            continue;
        }

        return $realFile;
    }

    return null;
}

/**
 * Envía un archivo binario de forma segura evitando contaminación de buffers.
 */
function send_binary_file_response(string $filePath, string $contentType = 'application/octet-stream', ?string $downloadName = null, bool $asAttachment = true): never
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }

    $size = filesize($filePath);
    if (!is_int($size) || $size < 0) {
        http_response_code(500);
        exit('No se pudo determinar el tamaño del archivo.');
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (function_exists('session_write_close')) {
        @session_write_close();
    }

    @ini_set('zlib.output_compression', '0');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }

    if (headers_sent()) {
        http_response_code(500);
        exit('No se pudieron enviar cabeceras de descarga.');
    }

    $dispositionType = $asAttachment ? 'attachment' : 'inline';
    $safeName = null;
    $encodedName = null;

    if (is_string($downloadName) && $downloadName !== '') {
        $baseName = basename($downloadName);
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName);
        if (!is_string($safeName) || $safeName === '') {
            $safeName = 'archivo';
        }
        $encodedName = rawurlencode($baseName);
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $contentType);
    if ($safeName !== null && $encodedName !== null) {
        header('Content-Disposition: ' . $dispositionType . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . $encodedName);
    } else {
        header('Content-Disposition: ' . $dispositionType);
    }
    header('Content-Transfer-Encoding: binary');
    header('X-Content-Type-Options: nosniff');
    header('Expires: 0');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Content-Length: ' . $size);

    if (readfile($filePath) === false) {
        error_log('send_binary_file_response readfile failed: ' . $filePath);
        http_response_code(500);
        exit('Error al enviar el archivo.');
    }

    exit();
}
