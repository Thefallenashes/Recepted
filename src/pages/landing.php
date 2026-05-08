<?php
require_once __DIR__ . '/includes/page_bootstrap.php';
require_once __DIR__ . '/includes/debug_helpers.php';

$debug_context = build_debug_page_context('landing.php', __DIR__);
$mensaje_cookies = $debug_context['mensaje_cookies'];
$mensaje_debug = $debug_context['mensaje_debug'];
$debug_activo = $debug_context['debug_activo'];
$paginas_debug = $debug_context['paginas_debug'];
$total_users = $debug_context['total_users'];
$total_uploads = $debug_context['total_uploads'];

$cookie_activa = !empty($_COOKIE['remember']);
$usuario_autenticado = $cookie_activa || isset($_SESSION['usuario_id']);
$hero_cta_href = $usuario_autenticado ? 'home.php' : 'register.php';
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
            --brand-green: #0b8f97;
            --brand-green-dark: #0a7379;
        }

        body.landing-page {
            margin: 0;
        }

        .landing-hero {
            position: relative;
            width: 100%;
            min-height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #334155 100%);
        }

        .landing-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, rgba(201, 168, 76, 0.24), transparent 58%);
        }

        .landing-hero-overlay {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            padding: 96px 6%;
            color: #f8fafc;
        }

        .landing-hero-copy {
            max-width: 580px;
            text-align: left;
            margin-left: auto;
            transform: translateY(-8vh);
        }

        .landing-hero-copy h1 {
            font-size: clamp(2.8rem, 6vw, 5rem);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .landing-hero-copy p {
            margin: 0 0 2rem;
            font-size: clamp(1rem, 1.8vw, 1.25rem);
            line-height: 1.6;
            color: rgba(248, 250, 252, 0.88);
        }

        .landing-hero-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 56px;
            padding: 0 28px;
            border-radius: 12px;
            background: var(--brand-green);
            border: 1px solid var(--brand-green);
            color: #f8fafc;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 16px 30px rgba(11, 143, 151, 0.28);
            transition: background 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
        }

        .landing-hero-cta:hover {
            background: var(--brand-green-dark);
            border-color: var(--brand-green-dark);
            color: #f8fafc;
            transform: translateY(-1px);
            text-decoration: none;
        }

        .landing-hero-visual {
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .landing-hero-image-frame {
            width: min(100%, 520px);
            padding: 32px;
            background: transparent;
            transform: translateY(-8vh);
        }

        .landing-hero-image {
            display: block;
            width: 100%;
            height: auto;
            object-fit: contain;
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

        @media (max-width: 768px) {
            .landing-hero-overlay {
                padding: 80px 10%;
            }

            .landing-hero-copy {
                text-align: center;
                max-width: none;
                margin-left: 0;
                transform: translateY(-8vh);
            }

            .landing-hero-cta {
                width: 100%;
            }

            .landing-hero-image-frame {
                width: min(100%, 380px);
                padding: 24px;
                transform: translateY(-8vh);
            }

            .landing-hero-visual {
                justify-content: center;
            }
        }
    </style>
</head>

<body class="landing-page">
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

    <div class="landing-hero container-fluid px-0">
        <div class="landing-hero-overlay container-fluid">
            <div class="row align-items-center justify-content-between min-vh-100 gx-5 gy-5">
                <div class="col-12 col-lg-6">
                    <div class="landing-hero-copy">
                        <h1>Recepted</h1>
                        <p>Controla tus ingresos, organiza tus archivos y toma decisiones con una vista clara de tus finanzas desde el primer momento.</p>
                        <a href="<?php echo htmlspecialchars($hero_cta_href, ENT_QUOTES, 'UTF-8'); ?>" class="landing-hero-cta">¡Comienza a mejorar tus finanzas!</a>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="landing-hero-visual">
                        <div class="landing-hero-image-frame">
                            <img src="../images/logo_pagina.png" alt="Logo de Recepted" class="landing-hero-image">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

