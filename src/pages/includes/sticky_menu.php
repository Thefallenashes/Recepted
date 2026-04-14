<?php

if (!function_exists('render_sticky_menu')) {
    /**
     * Renderiza el menú reutilizable para las páginas.
     *
     * @param array<string, mixed> $options
     */
    function render_sticky_menu(array $options = []): void
    {
        $defaults = [
            'container_class' => 'sticky-home-menu',
            'inner_class' => 'sticky-home-menu-inner',
            'image_base_path' => '../images',
            'home_href' => 'home.php',
            'home_label' => 'Inicio',
            'show_home' => true,
            'logout_href' => 'scripts/logout.php',
            'logout_label' => 'Cerrar sesión',
            'show_logout' => true,
            'nav_items' => [],
            'nav_class' => 'sticky-links',
            'toggle_label' => 'Mostrar menu desplegable',
        ];

        $config = array_merge($defaults, $options);

        $containerClass = (string)$config['container_class'];
        $innerClass = (string)$config['inner_class'];
        $imageBasePath = rtrim((string)$config['image_base_path'], '/');
        $homeHref = (string)$config['home_href'];
        $homeLabel = (string)$config['home_label'];
        $showHome = (bool)$config['show_home'];
        $logoutHref = (string)$config['logout_href'];
        $logoutLabel = (string)$config['logout_label'];
        $showLogout = (bool)$config['show_logout'];
        $navClass = trim((string)$config['nav_class']);
        $toggleLabel = (string)$config['toggle_label'];

        $homeIcon = $imageBasePath . '/Home.PNG';
        $logoutIcon = $imageBasePath . '/BotonLogOut.PNG';

        static $bootstrapJsInjected = false;

        $filteredItems = [];
        foreach ((array)$config['nav_items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $href = isset($item['href']) ? trim((string)$item['href']) : '';
            $label = isset($item['label']) ? trim((string)$item['label']) : '';
            $requiredRole = isset($item['min_role']) ? trim((string)$item['min_role']) : '';

            if ($href === '' || $label === '') {
                continue;
            }

            if ($requiredRole !== '' && function_exists('has_min_role') && !has_min_role($requiredRole)) {
                continue;
            }

            $filteredItems[] = [
                'href' => $href,
                'label' => $label,
            ];
        }
        $hasCollapsibleContent = !empty($filteredItems) || $showLogout;
        $collapseId = 'menuCollapse-' . substr(md5($containerClass . $innerClass . (string)count($filteredItems) . microtime(true)), 0, 8);
        $resolvedNavClass = trim(($navClass !== '' ? $navClass : 'sticky-links') . ' sticky-links navbar-collapse collapse');
        ?>
        <header class="<?php echo htmlspecialchars($containerClass, ENT_QUOTES, 'UTF-8'); ?> menu-bootstrap-nav navbar navbar-expand-lg bg-primary" data-menu="bootstrap">
            <div class="<?php echo htmlspecialchars($innerClass, ENT_QUOTES, 'UTF-8'); ?> menu-bootstrap-nav-inner container-fluid navbar-dark bg-dark">
                <?php if ($showHome): ?>
                    <a class="menu-icon-btn navbar-brand" href="<?php echo htmlspecialchars($homeHref, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Inicio">
                        <img src="<?php echo htmlspecialchars($homeIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="Inicio" class="icon-home">
                        <span><?php echo htmlspecialchars($homeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endif; ?>

                <?php if ($hasCollapsibleContent): ?>
                    <button type="button" class="menu-icon-btn menu-toggle-btn navbar-toggler" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>" aria-controls="<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false" aria-label="<?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <nav id="<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($resolvedNavClass, ENT_QUOTES, 'UTF-8'); ?>">
                        <ul class="navbar-nav ms-lg-auto sticky-links-list">
                            <?php foreach ($filteredItems as $item): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($showLogout): ?>
                                <li class="nav-item">
                                    <a class="menu-icon-btn logout-btn nav-link" href="<?php echo htmlspecialchars($logoutHref, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                        <img src="<?php echo htmlspecialchars($logoutIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8'); ?>" class="logout-icon">
                                        <span><?php echo htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </header>
        <?php if (!$bootstrapJsInjected): ?>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
            <?php $bootstrapJsInjected = true; ?>
        <?php endif; ?>
        <?php
    }
}
