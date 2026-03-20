<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = getPDO();
    if (function_exists('can_manage_all_resources') && can_manage_all_resources()) {
        $stmt = $pdo->query('SELECT * FROM uploads ORDER BY uploaded_at DESC');
    } else {
        $stmt = $pdo->prepare('SELECT * FROM uploads WHERE user_id = :user_id ORDER BY uploaded_at DESC');
        $stmt->execute(['user_id' => $_SESSION['usuario_id']]);
    }
    $uploads = $stmt->fetchAll();
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
    <header class="sticky-home-menu is-collapsed" data-sticky-menu data-icon-collapsed="../images/MostrarMenuDesplegable.PNG" data-icon-expanded="../images/OcultarMenuDesplegable.PNG">
        <div class="sticky-home-menu-inner">
            <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
                <img src="../images/Home.PNG" alt="Inicio" class="icon-home">
                <span>Inicio</span>
            </a>

            <a class="menu-icon-btn logout-btn" href="scripts/logout.php" aria-label="Cerrar sesión">
                <img src="../images/BotonLogOut.PNG" alt="Cerrar sesión" class="logout-icon">
                <span>Cerrar sesión</span>
            </a>

            <button type="button" class="menu-icon-btn menu-toggle-btn" data-menu-toggle aria-label="Mostrar menu desplegable" aria-expanded="false">
                <img src="../images/MostrarMenuDesplegable.PNG" alt="Mostrar menu desplegable" class="menu-toggle-icon" data-menu-toggle-icon>
            </button>

            <nav class="sticky-links">
                <ul>
                    <li><a href="finanzas.php">Finanzas</a></li>
                    <li><a href="perfil.php">Perfil</a></li>
                    <li><a href="tickets.php">Tickets</a></li>
                    <li><a href="config.php">Configuración</a></li>
                    <?php if (function_exists('has_min_role') && has_min_role('admin')): ?>
                        <li><a href="admin_panel.php">panel de administrador</a></li>
                    <?php endif; ?>
                    <?php if (function_exists('has_min_role') && has_min_role('superadmin')): ?>
                        <li><a href="superadmin_console.php">Consola</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

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

        <p><a href="scripts/upload.php">Subir nuevo archivo</a> | <a href="analizar_excel.php">Analizar Excel</a> | <a href="perfil.php">Volver al perfil</a> | <a href="scripts/logout.php">Cerrar Sesión</a></p>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>
</html>


