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

        $collapsedIcon = $imageBasePath . '/MostrarMenuDesplegable.PNG';
        $expandedIcon = $imageBasePath . '/OcultarMenuDesplegable.PNG';
        $homeIcon = $imageBasePath . '/Home.PNG';
        $logoutIcon = $imageBasePath . '/BotonLogOut.PNG';

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
        ?>
        <header class="<?php echo htmlspecialchars($containerClass, ENT_QUOTES, 'UTF-8'); ?> is-collapsed" data-sticky-menu data-icon-collapsed="<?php echo htmlspecialchars($collapsedIcon, ENT_QUOTES, 'UTF-8'); ?>" data-icon-expanded="<?php echo htmlspecialchars($expandedIcon, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="<?php echo htmlspecialchars($innerClass, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($showHome): ?>
                    <a class="menu-icon-btn" href="<?php echo htmlspecialchars($homeHref, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Inicio">
                        <img src="<?php echo htmlspecialchars($homeIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="Inicio" class="icon-home">
                        <span><?php echo htmlspecialchars($homeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endif; ?>

                <button type="button" class="menu-icon-btn menu-toggle-btn" data-menu-toggle aria-label="<?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false">
                    <img src="<?php echo htmlspecialchars($collapsedIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?>" class="menu-toggle-icon" data-menu-toggle-icon>
                </button>

                <?php if (!empty($filteredItems)): ?>
                    <nav class="<?php echo htmlspecialchars($navClass !== '' ? $navClass : 'sticky-links', ENT_QUOTES, 'UTF-8'); ?>">
                        <ul>
                            <?php foreach ($filteredItems as $item): ?>
                                <li><a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

                <?php if ($showLogout): ?>
                    <a class="menu-icon-btn logout-btn" href="<?php echo htmlspecialchars($logoutHref, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($logoutIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8'); ?>" class="logout-icon">
                        <span><?php echo htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </header>
        <?php
    }
}
