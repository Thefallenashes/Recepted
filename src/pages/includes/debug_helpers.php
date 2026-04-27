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
