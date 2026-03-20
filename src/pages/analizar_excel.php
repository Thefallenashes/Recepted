<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$allowedExts = ['csv', 'xlsb', 'xltx', 'xls', 'xlsm', 'xlsx'];

try {
    $pdo    = getPDO();
    $userId = (int) $_SESSION['usuario_id'];

    $isAdmin = function_exists('can_manage_all_resources') && can_manage_all_resources();
    if ($isAdmin) {
        $stmt = $pdo->query('SELECT id, filename, uploaded_at FROM uploads ORDER BY uploaded_at DESC');
    } else {
        $stmt = $pdo->prepare('SELECT id, filename, uploaded_at FROM uploads WHERE user_id = :uid ORDER BY uploaded_at DESC');
        $stmt->execute(['uid' => $userId]);
    }

    $allUploads = $stmt->fetchAll();
    $excelUploads = array_values(array_filter($allUploads, function ($u) use ($allowedExts) {
        return in_array(strtolower(pathinfo($u['filename'], PATHINFO_EXTENSION)), $allowedExts, true);
    }));
} catch (PDOException $e) {
    error_log('analizar_excel: ' . $e->getMessage());
    $excelUploads = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analizar Excel</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/analizar_excel.css">
</head>
<body>

<header class="sticky-home-menu is-collapsed" data-sticky-menu
        data-icon-collapsed="../images/MostrarMenuDesplegable.PNG"
        data-icon-expanded="../images/OcultarMenuDesplegable.PNG">
    <div class="sticky-home-menu-inner">
        <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
            <img src="../images/Home.PNG" alt="Inicio" class="icon-home">
            <span>Inicio</span>
        </a>

        <a class="menu-icon-btn logout-btn" href="scripts/logout.php" aria-label="Cerrar sesión">
            <img src="../images/BotonLogOut.PNG" alt="Cerrar sesión" class="logout-icon">
            <span>Cerrar sesión</span>
        </a>

        <button type="button" class="menu-icon-btn menu-toggle-btn" data-menu-toggle
                aria-label="Mostrar menu desplegable" aria-expanded="false">
            <img src="../images/MostrarMenuDesplegable.PNG" alt="Mostrar menu desplegable"
                 class="menu-toggle-icon" data-menu-toggle-icon>
        </button>

        <nav class="sticky-links">
            <ul>
                <li><a href="finanzas.php">Finanzas</a></li>
                <li><a href="mis_uploads.php">Mis archivos</a></li>
                <li><a href="tickets.php">Tickets</a></li>
                <li><a href="config.php">Configuración</a></li>
                <?php if (function_exists('has_min_role') && has_min_role('admin')): ?>
                    <li><a href="admin_panel.php">Panel de administracion</a></li>
                <?php endif; ?>
                <?php if (function_exists('has_min_role') && has_min_role('superadmin')): ?>
                    <li><a href="superadmin_console.php">Consola</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<div class="ae-container">
    <h1>Analizar archivo Excel</h1>

    <?php if (empty($excelUploads)): ?>
        <p>No tienes archivos de Excel subidos aún.
           <a href="scripts/upload.php">Ház click aquí para subir uno, </a> y vuelve aquí para analizarlo.</p>
    <?php else: ?>

        <!-- Panel de controles -->
        <div class="ae-controls">
            <h2>Configuración</h2>
            <div class="ae-control-row">
                <label class="ae-field">
                    Archivo a analizar
                    <select id="aeFileSelect">
                        <option value="">— Elige un archivo —</option>
                        <?php foreach ($excelUploads as $up): ?>
                            <option value="<?php echo (int) $up['id']; ?>">
                                <?php echo htmlspecialchars($up['filename']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button id="aeLoadBtn" class="ae-btn ae-btn-primary" disabled>
                    Cargar y analizar
                </button>
            </div>

            <!-- Botones de tipo de gráfico (ocultos hasta cargar datos) -->
            <div class="ae-chart-types" id="aeChartTypes" style="display:none">
                <button class="ae-type-btn active" data-type="bar">Gráfico de barras</button>
                <button class="ae-type-btn"        data-type="pie">Gráfico de sectores</button>
                <button class="ae-type-btn"        data-type="line">Gráfico de líneas</button>
                <button class="ae-type-btn"        data-type="text">Texto simple</button>
            </div>
        </div>

        <!-- Área de gráfico/texto -->
        <div class="ae-chart-area" id="aeChartArea">
            <p class="ae-msg">Selecciona un archivo y pulsa <strong>Cargar y analizar</strong>.</p>
        </div>

    <?php endif; ?>
</div>

<!-- SheetJS para leer CSV, XLS, XLSX, XLSM, XLTX, XLSB -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<!-- Chart.js para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script src="../js/sticky-menu-toggle.js" defer></script>

<script>
(function () {
    'use strict';

    /* ============================================================
       Estado global
    ============================================================ */
    let currentType    = 'bar';
    let chartInstance  = null;
    let analysedData   = null; // { labels, gastos, beneficios, gastosNames, beneficiosNames }

    /* ============================================================
       Elementos DOM
    ============================================================ */
    const fileSelect   = document.getElementById('aeFileSelect');
    const loadBtn      = document.getElementById('aeLoadBtn');
    const chartArea    = document.getElementById('aeChartArea');
    const chartTypes   = document.getElementById('aeChartTypes');

    /* ============================================================
       Eventos de UI
    ============================================================ */
    fileSelect.addEventListener('change', () => {
        loadBtn.disabled = !fileSelect.value;
    });

    document.querySelectorAll('.ae-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ae-type-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentType = btn.dataset.type;
            if (analysedData) renderView(analysedData, currentType);
        });
    });

    loadBtn.addEventListener('click', async () => {
        const id = fileSelect.value;
        if (!id) return;

        loadBtn.disabled = true;
        chartArea.innerHTML = '<p class="ae-msg">Cargando archivo…</p>';

        try {
            const resp = await fetch('scripts/get_upload_raw.php?id=' + encodeURIComponent(id));
            if (!resp.ok) throw new Error('Error del servidor: ' + resp.status);

            const buffer = await resp.arrayBuffer();

            // Leer con SheetJS (cellDates: true para obtener Date en celdas de fecha)
            const wb = XLSX.read(buffer, { type: 'array', cellDates: true });
            if (!wb.SheetNames.length) throw new Error('El archivo no contiene hojas.');

            const ws   = wb.Sheets[wb.SheetNames[0]];
            const raw  = XLSX.utils.sheet_to_json(ws, { header: 1, defval: null });

            // Filtrar filas completamente vacías
            const rows = raw.filter(r => r.some(c => c !== null && c !== ''));
            if (!rows.length) throw new Error('La hoja parece estar vacía.');

            analysedData = analyseData(rows);

            if (!analysedData.labels.length) {
                throw new Error('No se encontraron datos numéricos válidos en el archivo.');
            }

            chartTypes.style.display = 'flex';
            renderView(analysedData, currentType);

        } catch (err) {
            chartArea.innerHTML = '<p class="ae-msg-error">⚠ ' + esc(err.message) + '</p>';
        } finally {
            loadBtn.disabled = false;
        }
    });

    /* ============================================================
       Análisis de datos
    ============================================================ */
    function analyseData(rows) {
        /* ── 1. Detectar fila de cabeceras ── */
        const firstRow   = rows[0];
        const hasHeaders = firstRow.some(c =>
            c !== null && typeof c === 'string' && isNaN(parseFloat(String(c).replace(',', '.')))
        );

        let headers, dataRows;
        if (hasHeaders) {
            headers  = firstRow.map(c => (c !== null ? String(c).trim() : ''));
            dataRows = rows.slice(1);
        } else {
            headers  = null;
            dataRows = rows;
        }

        const numCols = Math.max(...rows.map(r => r.length));

        /* ── 2. Clasificar columnas ── */
        // Clasificaciones: 'date', 'gastos', 'beneficios', 'mixed', 'text', 'empty'
        const colClass = [];
        let   dateColIdx = -1;

        const reGastos     = /gasto|expense|coste|cost|salida|egreso|deuda|pago|debe/i;
        const reBeneficios = /beneficio|ingreso|income|revenue|entrada|ganancia|venta|cobro|salario|sueldo|haber/i;

        for (let c = 0; c < numCols; c++) {
            const vals = dataRows.map(r => r[c]).filter(v => v !== null && v !== '');

            if (!vals.length) { colClass.push('empty'); continue; }

            // ¿Es columna de fecha?
            const dateCount = vals.filter(isDateLike).length;
            if (dateCount / vals.length >= 0.6 && dateColIdx === -1) {
                colClass.push('date');
                dateColIdx = c;
                continue;
            }

            // ¿Es columna numérica?
            const numCount = vals.filter(isNumericLike).length;
            if (numCount / vals.length < 0.7) {
                colClass.push('text');
                continue;
            }

            // Tiene nombre reconocible
            const hdr = headers ? headers[c] : '';
            if (reGastos.test(hdr)) {
                colClass.push('gastos');
                continue;
            }
            if (reBeneficios.test(hdr)) {
                colClass.push('beneficios');
                continue;
            }

            // Auto-detección por signo
            const nums     = vals.map(parseNum).filter(v => !isNaN(v));
            const negCount = nums.filter(v => v < 0).length;
            const posCount = nums.filter(v => v > 0).length;
            const total    = negCount + posCount || 1;

            if (negCount / total > 0.65) {
                colClass.push('gastos');
            } else if (posCount / total > 0.65) {
                colClass.push('beneficios');
            } else if (negCount > 0 && posCount > 0) {
                colClass.push('mixed'); // negativo → gasto, positivo → beneficio
            } else if (posCount > 0) {
                colClass.push('beneficios');
            } else {
                colClass.push('gastos');
            }
        }

        // Si ninguna columna fue clasificada como gasto o beneficio, usar todas las numéricas como mixed
        const hasFinancialCol = colClass.some(c => ['gastos', 'beneficios', 'mixed'].includes(c));
        if (!hasFinancialCol) {
            for (let c = 0; c < numCols; c++) {
                if (colClass[c] === 'text' || colClass[c] === 'date' || colClass[c] === 'empty') continue;
                colClass[c] = 'mixed';
            }
        }

        /* ── 3. Nombres de columnas clasificadas (para info) ── */
        const gastosNames     = [];
        const beneficiosNames = [];
        for (let c = 0; c < numCols; c++) {
            const name = headers ? (headers[c] || 'Col. ' + (c + 1)) : 'Col. ' + (c + 1);
            if (colClass[c] === 'gastos'     || colClass[c] === 'mixed') gastosNames.push(name);
            if (colClass[c] === 'beneficios' || colClass[c] === 'mixed') beneficiosNames.push(name);
        }

        /* ── 4. Agregar datos por período ── */
        let labels       = [];
        let gastosData   = [];
        let beneficiosData = [];

        if (dateColIdx >= 0) {
            // Agrupar por mes
            const monthMap = new Map(); // clave: 'YYYY-MM'

            for (const row of dataRows) {
                const key = getMonthKey(row[dateColIdx]);
                if (!key) continue;

                if (!monthMap.has(key)) monthMap.set(key, { g: 0, b: 0 });
                const entry = monthMap.get(key);
                accumulateRow(row, colClass, numCols, entry);
            }

            const sorted = Array.from(monthMap.keys()).sort();
            labels         = sorted.map(formatMonth);
            gastosData     = sorted.map(k => round2(monthMap.get(k).g));
            beneficiosData = sorted.map(k => round2(monthMap.get(k).b));

        } else {
            // Sin columna de fecha: cada fila es un punto
            dataRows.forEach((row, i) => {
                const entry = { g: 0, b: 0 };
                accumulateRow(row, colClass, numCols, entry);
                if (entry.g > 0 || entry.b > 0) {
                    labels.push('Fila ' + (i + 1));
                    gastosData.push(round2(entry.g));
                    beneficiosData.push(round2(entry.b));
                }
            });
        }

        return { labels, gastos: gastosData, beneficios: beneficiosData, gastosNames, beneficiosNames };
    }

    /** Suma los valores de gastos/beneficios de una fila en entry.g / entry.b */
    function accumulateRow(row, colClass, numCols, entry) {
        for (let c = 0; c < numCols; c++) {
            const cls = colClass[c];
            if (!['gastos', 'beneficios', 'mixed'].includes(cls)) continue;
            const v = parseNum(row[c]);
            if (isNaN(v)) continue;

            if (cls === 'gastos') {
                entry.g += Math.abs(v);
            } else if (cls === 'beneficios') {
                entry.b += Math.abs(v);
            } else { // mixed
                if (v < 0) entry.g += Math.abs(v);
                else        entry.b += v;
            }
        }
    }

    /* ============================================================
       Renderizado
    ============================================================ */
    function renderView(data, type) {
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
        chartArea.innerHTML = '';

        const totalG = data.gastos.reduce((a, b) => a + b, 0);
        const totalB = data.beneficios.reduce((a, b) => a + b, 0);
        const net    = totalB - totalG;

        /* -- Barra de totales -- */
        const netClass = net > 0 ? 'pos' : net < 0 ? 'neg' : 'neu';
        const netLabel = net >= 0 ? 'Beneficios' : 'Pérdidas';
        const totalsEl = doc(`
            <div class="ae-totals">
                <div class="ae-total-item">
                    <span class="ae-total-label">Gastos totales</span>
                    <span class="ae-total-value neg">-${fmt(totalG)} €</span>
                </div>
                <div class="ae-total-item">
                    <span class="ae-total-label">Beneficios totales</span>
                    <span class="ae-total-value pos">+${fmt(totalB)} €</span>
                </div>
                <div class="ae-total-item">
                    <span class="ae-total-label">Resultado global</span>
                    <span class="ae-total-value ${netClass}">${netLabel}: ${net >= 0 ? '+' : ''}${fmt(net)} €</span>
                </div>
            </div>`);

        /* -- Información de columnas detectadas -- */
        const colInfoEl = doc(`
            <div class="ae-col-info">
                Columnas de <strong>gastos</strong>: ${esc(data.gastosNames.join(', ') || 'ninguna detectada')}
                &nbsp;·&nbsp;
                Columnas de <strong>beneficios</strong>: ${esc(data.beneficiosNames.join(', ') || 'ninguna detectada')}
            </div>`);

        chartArea.appendChild(totalsEl);
        chartArea.appendChild(colInfoEl);

        /* ── Vista de texto ── */
        if (type === 'text') {
            const textDiv = document.createElement('div');
            textDiv.className = 'ae-text-view';

            data.labels.forEach((label, i) => {
                const g    = data.gastos[i];
                const b    = data.beneficios[i];
                const n    = b - g;
                const isOk = n >= 0;
                const block = doc(`
                    <div class="ae-month-block">
                        <h3>${esc(label)}</h3>
                        <div class="ae-stat-row">
                            <span class="ae-stat-label">Gastos</span>
                            <span class="ae-stat-val neg">-${fmt(g)} €</span>
                        </div>
                        <div class="ae-stat-row">
                            <span class="ae-stat-label">Beneficios totales</span>
                            <span class="ae-stat-val pos">+${fmt(b)} €</span>
                        </div>
                        <div class="ae-stat-row">
                            <span class="ae-stat-label">Resultado</span>
                            <span class="ae-stat-val ${isOk ? 'pos' : 'neg'}">
                                ${isOk ? 'Beneficios' : 'Pérdidas'}: ${isOk ? '+' : ''}${fmt(n)} €
                            </span>
                        </div>
                    </div>`);
                textDiv.appendChild(block);
            });

            chartArea.appendChild(textDiv);
            return;
        }

        /* ── Vista de gráfico ── */
        const wrap   = document.createElement('div');
        wrap.className = 'ae-canvas-wrap';
        const canvas = document.createElement('canvas');
        canvas.id    = 'aeMainChart';
        wrap.appendChild(canvas);
        chartArea.appendChild(wrap);

        const ctx = canvas.getContext('2d');

        const colG = 'rgba(220,38,38,0.75)';
        const colB = 'rgba(22,163,74,0.75)';
        const colGb = 'rgba(220,38,38,1)';
        const colBb = 'rgba(22,163,74,1)';

        if (type === 'bar') {
            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Gastos',
                            data: data.gastos,
                            backgroundColor: colG,
                            borderColor: colGb,
                            borderWidth: 1
                        },
                        {
                            label: 'Beneficios',
                            data: data.beneficios,
                            backgroundColor: colB,
                            borderColor: colBb,
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: ctx => ' ' + ctx.dataset.label + ': ' + fmt(ctx.raw) + ' €'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => fmt(v) + ' €'
                            }
                        }
                    }
                }
            });

        } else if (type === 'pie') {
            const total = totalG + totalB || 1;
            chartInstance = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Gastos', 'Beneficios'],
                    datasets: [{
                        data: [round2(totalG), round2(totalB)],
                        backgroundColor: [colG, colB],
                        borderColor: [colGb, colBb],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const pct = ((ctx.raw / total) * 100).toFixed(1);
                                    return ' ' + fmt(ctx.raw) + ' € (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });

        } else if (type === 'line') {
            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Gastos',
                            data: data.gastos,
                            borderColor: colGb,
                            backgroundColor: 'rgba(220,38,38,0.08)',
                            tension: 0.35,
                            fill: true,
                            pointRadius: 4
                        },
                        {
                            label: 'Beneficios',
                            data: data.beneficios,
                            borderColor: colBb,
                            backgroundColor: 'rgba(22,163,74,0.08)',
                            tension: 0.35,
                            fill: true,
                            pointRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: ctx => ' ' + ctx.dataset.label + ': ' + fmt(ctx.raw) + ' €'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => fmt(v) + ' €' }
                        }
                    }
                }
            });
        }
    }

    /* ============================================================
       Funciones auxiliares
    ============================================================ */
    function isDateLike(v) {
        if (v instanceof Date) return !isNaN(v.getTime());
        if (typeof v === 'string') {
            return /^\d{1,4}[-\/\.]\d{1,2}[-\/\.]\d{1,4}/.test(v.trim()) ||
                   /^\d{1,2}[-\/]\d{4}$/.test(v.trim());
        }
        return false;
    }

    function isNumericLike(v) {
        if (typeof v === 'number') return isFinite(v);
        if (typeof v === 'string') {
            const cleaned = v.replace(/[€$£%\s]/g, '').replace(',', '.');
            return cleaned !== '' && !isNaN(parseFloat(cleaned));
        }
        return false;
    }

    function parseNum(v) {
        if (v === null || v === undefined || v === '') return NaN;
        if (typeof v === 'number') return v;
        if (typeof v === 'string') {
            const cleaned = v.replace(/[€$£%\s]/g, '').replace(',', '.');
            return parseFloat(cleaned);
        }
        return NaN;
    }

    function getMonthKey(v) {
        if (v instanceof Date && !isNaN(v.getTime())) {
            return v.getFullYear() + '-' + String(v.getMonth() + 1).padStart(2, '0');
        }
        if (typeof v === 'string') {
            // Intentar fecha estándar
            const d = new Date(v);
            if (!isNaN(d.getTime())) {
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            }
            // DD/MM/YYYY o DD-MM-YYYY o DD.MM.YYYY
            const m = v.match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/);
            if (m) {
                const year = m[3].length === 2 ? '20' + m[3] : m[3];
                return year + '-' + m[2].padStart(2, '0');
            }
        }
        return null;
    }

    function formatMonth(key) {
        const MONTHS = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        const [y, m] = key.split('-');
        return MONTHS[parseInt(m, 10) - 1] + ' ' + y;
    }

    function fmt(n) {
        return Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    /** Crea un elemento DOM a partir de HTML (usa innerHTML seguro para contenido ya escapado) */
    function doc(html) {
        const t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstElementChild;
    }

    /** Escapa HTML para prevenir XSS */
    function esc(str) {
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

})();
</script>

</body>
</html>
