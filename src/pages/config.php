<?php
require_once __DIR__ . '/includes/page_bootstrap.php';
require_once __DIR__ . '/../utils/currencies.php';

// For now basic config page with session check
$userId = require_authenticated_user('login.php');

// Generate one-time access token for perfil.php and clear any active perfil session
$perfil_token = bin2hex(random_bytes(16));
$_SESSION['perfil_token'] = $perfil_token;
unset($_SESSION['perfil_active']);

$mensaje = '';
$tipo = '';

// Example: update user preferred currency (stored in finanzas.currency)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currency = trim($_POST['currency'] ?? 'EUR');
    try {
        $pdo = getPDO();
        update_user_currency($pdo, $userId, $currency);
        $tipo = 'exito';
        $mensaje = 'Configuración guardada.';
    } catch (PDOException $e) {
        error_log('Error config save: ' . $e->getMessage());
        $tipo = 'error';
        $mensaje = 'No se pudo guardar la configuración.';
    }
}

// Obtener configuración actual
try {
    $pdo = getPDO();
    $current_currency = fetch_user_currency($pdo, $userId, 'EUR');
} catch (PDOException $e) {
    $current_currency = 'EUR';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/config.css">
</head>
<body>
    <?php
    render_sticky_menu([
        'container_class' => 'sticky-home-menu',
        'inner_class' => 'sticky-home-menu-inner',
        'home_href' => 'home.php',
        'logout_href' => 'scripts/logout.php',
        'nav_items' => [
            ['href' => 'finanzas.php', 'label' => 'Finanzas'],
            ['href' => 'tickets.php', 'label' => 'Tickets'],
            ['href' => 'admin_panel.php', 'label' => 'Panel de administracion', 'min_role' => 'admin'],
            ['href' => 'superadmin_console.php', 'label' => 'Consola', 'min_role' => 'superadmin'],
        ],
    ]);
    ?>

    <div class="config-container">
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="config-form">
            <?php echo csrf_input_field(); ?>
            <div class="form-group">
                <label for="currency-search">Moneda preferida:</label>
                <div class="currency-picker">
                    <div class="currency-picker-display" id="currency-picker-display">
                        <span class="currency-picker-symbol" id="currency-display-symbol"></span>
                        <span class="currency-picker-label" id="currency-display-label"></span>
                    </div>
                    <input
                        type="text"
                        id="currency-search"
                        class="currency-search-input"
                        placeholder="Buscar por nombre o código..."
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <input type="hidden" id="currency" name="currency" value="<?php echo htmlspecialchars($current_currency); ?>">
                    <div id="currency-dropdown" class="currency-dropdown" role="listbox" aria-label="Monedas disponibles"></div>
                </div>
            </div>
            <button class="btn" type="submit">Guardar</button>
        </form>

        <?php
        $currency_json = json_encode(get_currency_list(), JSON_UNESCAPED_UNICODE);
        ?>
        <script>
        (function () {
            const currencies = <?php echo $currency_json; ?>;
            const hiddenInput  = document.getElementById('currency');
            const searchInput  = document.getElementById('currency-search');
            const dropdown     = document.getElementById('currency-dropdown');
            const dispSymbol   = document.getElementById('currency-display-symbol');
            const dispLabel    = document.getElementById('currency-display-label');
            const pickerDisplay= document.getElementById('currency-picker-display');

            function findCurrency(code) {
                return currencies.find(c => c.code === code) || null;
            }

            function renderDisplay(entry) {
                if (!entry) return;
                dispSymbol.textContent = entry.symbol || entry.code;
                dispLabel.textContent  = entry.code + ' — ' + entry.name;
            }

            function buildOption(entry) {
                const div = document.createElement('div');
                div.className = 'currency-option';
                div.setAttribute('role', 'option');
                div.dataset.code = entry.code;

                const sym = document.createElement('span');
                sym.className = 'currency-option-symbol';
                sym.textContent = entry.symbol || entry.code;

                const info = document.createElement('span');
                info.className = 'currency-option-info';

                const code = document.createElement('strong');
                code.textContent = entry.code;

                const name = document.createElement('span');
                name.textContent = ' — ' + entry.name;

                info.appendChild(code);
                info.appendChild(name);
                div.appendChild(sym);
                div.appendChild(info);

                div.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectCurrency(entry);
                });

                return div;
            }

            function populateDropdown(filter) {
                dropdown.innerHTML = '';
                const q = (filter || '').toLowerCase().trim();
                const filtered = q
                    ? currencies.filter(c =>
                        c.code.toLowerCase().includes(q) ||
                        c.name.toLowerCase().includes(q) ||
                        c.symbol.toLowerCase().includes(q)
                      )
                    : currencies;

                if (filtered.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'currency-option currency-empty';
                    empty.textContent = 'Sin resultados';
                    dropdown.appendChild(empty);
                } else {
                    filtered.forEach(c => dropdown.appendChild(buildOption(c)));
                }
            }

            function showDropdown() {
                populateDropdown(searchInput.value);
                dropdown.style.display = 'block';
                pickerDisplay.style.display = 'none';
                searchInput.style.display = 'block';
                searchInput.focus();
                scrollToSelected();
            }

            function hideDropdown() {
                dropdown.style.display = 'none';
                searchInput.style.display = 'none';
                searchInput.value = '';
                pickerDisplay.style.display = '';
            }

            function selectCurrency(entry) {
                hiddenInput.value = entry.code;
                renderDisplay(entry);
                hideDropdown();
            }

            function scrollToSelected() {
                const selected = dropdown.querySelector('[data-code="' + hiddenInput.value + '"]');
                if (selected) selected.scrollIntoView({ block: 'nearest' });
            }

            pickerDisplay.addEventListener('click', showDropdown);

            searchInput.addEventListener('input', function () {
                populateDropdown(this.value);
            });

            searchInput.addEventListener('blur', function () {
                setTimeout(hideDropdown, 150);
            });

            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { hideDropdown(); return; }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const first = dropdown.querySelector('.currency-option:not(.currency-empty)');
                    if (first) {
                        const entry = findCurrency(first.dataset.code);
                        if (entry) selectCurrency(entry);
                    }
                }
            });

            // Init display with current saved currency
            const initial = findCurrency(hiddenInput.value) || findCurrency('EUR');
            if (initial) renderDisplay(initial);
            hideDropdown();
        })();
        </script>

        <div class="config-section">
            <h2>Información de la cuenta</h2>
            <p>Actualiza tu nombre, apellidos y edad.</p>
            <a class="btn" href="perfil.php?t=<?php echo htmlspecialchars($perfil_token); ?>" style="display:inline-flex;justify-content:center;">Actualizar informacion de la cuenta</a>
        </div>

        <p><a href="home.php">Volver al inicio</a></p>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>
</html>


