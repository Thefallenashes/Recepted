<?php
require_once __DIR__ . '/includes/page_bootstrap.php';

$mensaje_debug = '';
$mensaje_cookies = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cookies'])) {
    try {
        foreach ($_COOKIE as $cookieName => $cookieValue) {
            setcookie($cookieName, '', time() - 3600, '/');
            unset($_COOKIE[$cookieName]);
        }

        header('Location: landing.php?cookies=cleared');
        exit();
    } catch (Exception $e) {
        $mensaje_cookies = 'No se pudieron eliminar las cookies.';
        error_log('Error al borrar cookies en landing: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug'])) {
    try {
        $pdo = getPDO();
        if (function_exists('login_as_debug')) {
            login_as_debug($pdo);
            if (function_exists('record_audit_log')) {
                record_audit_log($pdo, 'debug_mode_enabled', 'warning', 'Activado desde landing.php');
            }
            header('Location: index.php');
            exit();
        }
        $mensaje_debug = 'No se encontró la función de modo debug.';
    } catch (Exception $e) {
        $mensaje_debug = 'No se pudo activar el modo debug.';
        error_log('Error en debug guest landing: ' . $e->getMessage());
    }
}

if (isset($_GET['cookies']) && $_GET['cookies'] === 'cleared') {
    $mensaje_cookies = 'Cookies eliminadas correctamente.';
}

$cookie_activa = !empty($_COOKIE['remember']);
$rol_sesion = strtolower(trim((string)($_SESSION['usuario_rol'] ?? '')));
$debug_activo = !empty($_SESSION['debug_mode']) || !empty($_SESSION['is_superadmin']) || $rol_sesion === 'superadmin';

$paginas_debug = [];
if ($debug_activo) {
    $archivos = glob(__DIR__ . '/*.php');
    if (is_array($archivos)) {
        foreach ($archivos as $archivo) {
            $nombre = basename($archivo);
            $requiere_parametro = in_array($nombre, ['scripts/download.php', 'scripts/delete_upload.php'], true);
            $paginas_debug[] = [
                'nombre' => $nombre,
                'requiere_parametro' => $requiere_parametro,
            ];
        }

        usort($paginas_debug, function ($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });
    }
}

try {
    $pdo = getPDO();
    $total_users = fetch_total_users($pdo);
    $total_uploads = fetch_total_uploads($pdo);
} catch (PDOException $e) {
    $total_users = 0;
    $total_uploads = 0;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing</title>
    <link rel="stylesheet" href="../css/index.css">
    <style>
        :root {
            --gold: #C9A84C;
        }

        .landing-sections-wrapper {
            width: 100%;
        }

        /* Fila de sección: contiene el padding lateral */
        .landing-section {
            width: 100%;
            box-sizing: border-box;
            padding: 0 6%;
            overflow: hidden;
        }

        /* Inner: flex container con los bordes dorados */
        .section-inner {
            width: 100%;
            display: flex;
            align-items: stretch;
            border-bottom: 4px solid var(--gold);
        }

        .landing-section:first-child .section-inner {
            border-top: 4px solid var(--gold);
        }

        /* Texto: 60% desde el lado de entrada */
        .section-content {
            flex: 0 0 60%;
            box-sizing: border-box;
            padding: 101px 5%;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .section-content h2 {
            margin-bottom: 1rem;
        }

        /* Imagen: ocupa el espacio restante (40%) */
        .section-image {
            flex: 1 1 0;
            min-height: 0;
            background-color: #2a2a2a;
            background-image: repeating-linear-gradient(
                45deg,
                rgba(201,168,76,0.08) 0px,
                rgba(201,168,76,0.08) 1px,
                transparent 1px,
                transparent 12px
            );
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            opacity: 0.6;
        }

        /* Orden: izquierda → contenido primero, imagen después */
        .landing-section.from-left .section-content { order: 1; }
        .landing-section.from-left .section-image   { order: 2; }

        /* Orden: derecha → imagen primero, contenido después */
        .landing-section.from-right .section-content { order: 2; }
        .landing-section.from-right .section-image   { order: 1; }

        /* La imagen dentro de from-right ocupa el side-inner correctamente */
        .landing-section.from-right .section-inner { flex-direction: row; }

        /* Animaciones: sólo el bloque de contenido se desplaza */
        .fade-from-left {
            opacity: 0;
            transform: translateX(-120px);
            transition: opacity 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .fade-from-right {
            opacity: 0;
            transform: translateX(120px);
            transition: opacity 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .fade-from-left.is-visible,
        .fade-from-right.is-visible {
            opacity: 1;
            transform: translateX(0);
        }

        .landing-bottom-controls {
            padding: 40px 10%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>

<body>
    <?php
    $landingNavItems = [];
    if ($cookie_activa || isset($_SESSION['usuario_id'])) {
        $landingNavItems[] = ['href' => 'home.php', 'label' => 'Panel de usuario'];
    } else {
        $landingNavItems[] = ['href' => 'login.php', 'label' => 'Iniciar sesión'];
        $landingNavItems[] = ['href' => 'register.php', 'label' => 'Registrarse'];
    }

    render_sticky_menu([
        'container_class' => 'sticky-home-menu',
        'inner_class' => 'sticky-home-menu-inner',
        'home_href' => 'landing.php',
        'show_logout' => isset($_SESSION['usuario_id']),
        'logout_href' => 'scripts/logout.php',
        'nav_items' => $landingNavItems,
    ]);
    ?>

    <div class="landing-sections-wrapper">

        <div class="landing-section from-left">
            <div class="section-inner">
                <div class="section-content fade-from-left">
                    <h2>Título 1</h2>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                </div>
                <div class="section-image">imagen</div>
            </div>
        </div>

        <div class="landing-section from-right">
            <div class="section-inner">
                <div class="section-content fade-from-right">
                    <h2>Título 2</h2>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                </div>
                <div class="section-image">imagen</div>
            </div>
        </div>

        <div class="landing-section from-left">
            <div class="section-inner">
                <div class="section-content fade-from-left">
                    <h2>Título 3</h2>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                </div>
                <div class="section-image">imagen</div>
            </div>
        </div>

        <div class="landing-section from-right">
            <div class="section-inner">
                <div class="section-content fade-from-right">
                    <h2>Título 4</h2>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                </div>
                <div class="section-image">imagen</div>
            </div>
        </div>

        <div class="landing-section from-left">
            <div class="section-inner">
                <div class="section-content fade-from-left">
                    <h2>Título 5</h2>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                </div>
                <div class="section-image">imagen</div>
            </div>
        </div>

    </div>

    <div class="landing-bottom-controls">
        <?php if (!empty($mensaje_cookies)): ?>
            <p><?php echo htmlspecialchars($mensaje_cookies); ?></p>
        <?php endif; ?>
        <?php if (!empty($mensaje_debug)): ?>
            <p><?php echo htmlspecialchars($mensaje_debug); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_input_field(); ?>
            <button type="submit" name="debug" value="1" class="btn">Modo de desarollo</button>
        </form>
        <form method="POST" action="">
            <?php echo csrf_input_field(); ?>
            <button type="submit" name="clear_cookies" value="1" class="btn">Borrar cookies</button>
        </form>

        <?php if ($debug_activo): ?>
            <h2>Enlaces Modo de desarollo</h2>
            <ul>
                <?php foreach ($paginas_debug as $pagina): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($pagina['nombre']); ?>"><?php echo htmlspecialchars($pagina['nombre']); ?></a>
                        <?php if ($pagina['requiere_parametro']): ?>
                            (requiere parámetros)
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <script>
        (function () {
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });

            document.querySelectorAll('.section-content.fade-from-left, .section-content.fade-from-right').forEach(function (el) {
                observer.observe(el);
            });
        })();
    </script>
</body>

</html>

