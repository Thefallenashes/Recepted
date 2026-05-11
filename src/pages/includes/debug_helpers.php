<?php

/**
 * Handles the "clear cookies" POST action.
 * Redirects to {$selfPage}?cookies=cleared on success, returns error string on failure.
 * Returns empty string if the request is not a clear_cookies POST.
 */
function handle_clear_cookies_request(string $selfPage): string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['clear_cookies'])) {
        return '';
    }

    try {
        foreach ($_COOKIE as $cookieName => $cookieValue) {
            setcookie($cookieName, '', time() - 3600, '/');
            unset($_COOKIE[$cookieName]);
        }
        header('Location: ' . $selfPage . '?cookies=cleared');
        exit();
    } catch (Exception $e) {
        error_log('Error al borrar cookies en ' . $selfPage . ': ' . $e->getMessage());
        return 'No se pudieron eliminar las cookies.';
    }
}

function can_access_debug_mode(): bool
{
    if (empty($_SESSION['usuario_id'])) {
        return true;
    }

    return is_admin_user() || is_superadmin_user() || !empty($_SESSION['debug_mode']);
}

/**
 * Handles the "debug mode" POST action.
 * Redirects to index.php on success, returns error string on failure.
 * Returns empty string if the request is not a debug POST.
 */
function handle_debug_mode_request(string $sourcePage): string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['debug'])) {
        return '';
    }

    if (!can_access_debug_mode()) {
        return 'Solo administradores o superadministradores pueden activar el modo debug desde una cuenta autenticada.';
    }

    try {
        $pdo = getPDO();
        if (function_exists('login_as_debug')) {
            login_as_debug($pdo);
            if (function_exists('record_audit_log')) {
                record_audit_log($pdo, 'debug_mode_enabled', 'warning', 'Activado desde ' . $sourcePage);
            }
            header('Location: index.php');
            exit();
        }
        return 'No se encontró la función de modo debug.';
    } catch (Exception $e) {
        error_log('Error en debug guest ' . $sourcePage . ': ' . $e->getMessage());
        return 'No se pudo activar el modo debug.';
    }
}

/**
 * Returns 'Cookies eliminadas correctamente.' if the ?cookies=cleared query param is present.
 */
function resolve_cookies_cleared_message(): string
{
    if (isset($_GET['cookies']) && $_GET['cookies'] === 'cleared') {
        return 'Cookies eliminadas correctamente.';
    }
    return '';
}

/**
 * Builds the list of debug pages available in $pagesDir.
 * Returns an empty array when debug is not active.
 *
 * @param string[]                                    $excludedPages Base filenames to skip (e.g. ['index.php'])
 * @return array<int, array{nombre: string, requiere_parametro: bool}>
 */
function build_debug_pages_list(bool $debugActivo, string $pagesDir, array $excludedPages = []): array
{
    if (!$debugActivo) {
        return [];
    }

    $archivos = glob($pagesDir . '/*.php');
    if (!is_array($archivos)) {
        return [];
    }

    $pages = [];
    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);
        if ($excludedPages !== [] && in_array($nombre, $excludedPages, true)) {
            continue;
        }
        $requiere_parametro = in_array($nombre, ['download.php', 'delete_upload.php'], true);
        $pages[] = [
            'nombre' => $nombre,
            'requiere_parametro' => $requiere_parametro,
        ];
    }

    usort($pages, static function (array $a, array $b): int {
        return strcmp($a['nombre'], $b['nombre']);
    });

    return $pages;
}

/**
 * Builds shared page context used by landing/index debug controls.
 *
 * @param string[] $excludedPages
 * @return array{
 *   mensaje_cookies: string,
 *   mensaje_debug: string,
 *   puede_activar_debug: bool,
 *   debug_activo: bool,
 *   paginas_debug: array<int, array{nombre: string, requiere_parametro: bool}>,
 *   total_users: int,
 *   total_uploads: int
 * }
 */
function build_debug_page_context(string $selfPage, string $pagesDir, array $excludedPages = []): array
{
    $mensaje_cookies = handle_clear_cookies_request($selfPage) ?: resolve_cookies_cleared_message();
    $mensaje_debug = handle_debug_mode_request($selfPage);
    $puede_activar_debug = can_access_debug_mode();

    $rol_sesion = strtolower(trim((string)($_SESSION['usuario_rol'] ?? '')));
    $debug_activo = !empty($_SESSION['debug_mode']) || !empty($_SESSION['is_superadmin']) || $rol_sesion === 'superadmin';
    $paginas_debug = build_debug_pages_list($debug_activo, $pagesDir, $excludedPages);

    try {
        $pdo = getPDO();
        $total_users = fetch_total_users($pdo);
        $total_uploads = fetch_total_uploads($pdo);
    } catch (PDOException $e) {
        $total_users = 0;
        $total_uploads = 0;
    }

    return [
        'mensaje_cookies' => $mensaje_cookies,
        'mensaje_debug' => $mensaje_debug,
        'puede_activar_debug' => $puede_activar_debug,
        'debug_activo' => $debug_activo,
        'paginas_debug' => $paginas_debug,
        'total_users' => $total_users,
        'total_uploads' => $total_uploads,
    ];
}
