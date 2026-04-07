<?php
require_once __DIR__ . '/includes/page_bootstrap.php';

$userId = require_authenticated_user('login.php');

try {
    $pdo = getPDO();
    $uploads = fetch_uploads_visible_for_user($pdo, $userId);
} catch (PDOException $e) {
    error_log('Error fetching uploads: ' . $e->getMessage());
    $uploads = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis archivos</title>
    <link rel="stylesheet" href="../css/index.css">
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
            ['href' => 'config.php', 'label' => 'Configuración'],
            ['href' => 'admin_panel.php', 'label' => 'Panel de administracion', 'min_role' => 'admin'],
            ['href' => 'superadmin_console.php', 'label' => 'Consola', 'min_role' => 'superadmin'],
        ],
    ]);
    ?>

    <div class="index-container">
        <h1>Mis archivos</h1>

        <?php if (empty($uploads)): ?>
            <p>Aún no hay ningun archivo ¡Sube uno y comienza a mejorar tus finazas!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tamaño</th>
                        <th>Tipo</th>
                        <th>Subido</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploads as $up): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($up['filename']); ?></td>
                            <td><?php echo number_format($up['size'] / 1024, 2); ?> KB</td>
                            <td><?php echo htmlspecialchars($up['mime']); ?></td>
                            <td><?php echo htmlspecialchars($up['uploaded_at']); ?></td>
                            <td>
                                <a href="scripts/download.php?id=<?php echo $up['id']; ?>">Descargar</a>
                                <?php
                                $excelExts = ['csv','xlsb','xltx','xls','xlsm','xlsx'];
                                $fileExt   = strtolower(pathinfo($up['filename'], PATHINFO_EXTENSION));
                                if (in_array($fileExt, $excelExts, true)):
                                ?>
                                | <a href="analizar_excel.php?id=<?php echo $up['id']; ?>">Analizar</a>
                                <?php endif; ?>
                                |
                                <form method="POST" action="scripts/delete_upload.php" style="display:inline" onsubmit="return confirm('¿Eliminar archivo?');">
                                    <input type="hidden" name="id" value="<?php echo $up['id']; ?>">
                                    <button type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><a href="scripts/upload.php">Subir nuevo archivo</a> | <a href="analizar_excel.php">Analizar Excel</a></p>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>
</html>


