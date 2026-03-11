<?php
session_start();
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/auth.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$mensaje = '';
$tipo = '';

// Configuración
$maxSize = 10 * 1024 * 1024; // 10 MB
$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf', 'text/plain',
    'application/zip', 'application/vnd.ms-excel',
];
$uploadDir = __DIR__ . '/../uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $tipo = 'error';
        $mensaje = 'Error en la subida o no se seleccionó archivo.';
    } else {
        $file = $_FILES['file'];
        if ($file['size'] > $maxSize) {
            $tipo = 'error';
            $mensaje = 'El archivo supera el tamaño máximo permitido (10MB).';
        } elseif (!in_array($file['type'], $allowedTypes)) {
            $tipo = 'error';
            $mensaje = 'Tipo de archivo no permitido.';
        } else {
            // Generar nombre único
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            $newName = $_SESSION['usuario_id'] . '_' . time() . '_' . bin2hex(random_bytes(6)) . ($safeExt ? '.' . $safeExt : '');
            $destination = $uploadDir . $newName;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Registrar en la base de datos
                try {
                    $pdo = getPDO();
                    $stmt = $pdo->prepare('INSERT INTO uploads (user_id, filename, filepath, mime, size) VALUES (:user_id, :filename, :filepath, :mime, :size)');
                    // Guardar ruta relativa a la carpeta `src/` para facilitar acceso
                    $relativePath = 'uploads/' . $newName;
                    $stmt->execute([
                        'user_id' => $_SESSION['usuario_id'],
                        'filename' => $file['name'],
                        'filepath' => $relativePath,
                        'mime' => $file['type'],
                        'size' => $file['size']
                    ]);

                    $tipo = 'exito';
                    $mensaje = 'Archivo subido y registrado correctamente.';
                } catch (PDOException $e) {
                    error_log('Upload DB error: ' . $e->getMessage());
                    // Borrar archivo si fallo registro
                    @unlink($destination);
                    $tipo = 'error';
                    $mensaje = 'Ocurrió un error al registrar el archivo.';
                }
            } else {
                $tipo = 'error';
                $mensaje = 'No se pudo mover el archivo al destino.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subir archivo</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
    <header class="sticky-home-menu is-collapsed" data-sticky-menu data-icon-collapsed="../images/MostrarMenuDesplegable.PNG" data-icon-expanded="../images/OcultarMenuDesplegable.PNG">
        <div class="sticky-home-menu-inner">
            <a class="menu-icon-btn" href="home.php" aria-label="Inicio">
                <img src="../images/Home.PNG" alt="Inicio" class="icon-home">
                <span>Inicio</span>
            </a>

            <a class="menu-icon-btn logout-btn" href="logout.php" aria-label="Cerrar sesión">
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
                        <li><a href="admin_panel.php">Panel Admin</a></li>
                    <?php endif; ?>
                    <?php if (function_exists('has_min_role') && has_min_role('superadmin')): ?>
                        <li><a href="superadmin_console.php">Consola Superadmin</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="index-container">
        <h1>Subir archivo</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="file">Archivo:</label>
                <input type="file" id="file" name="file" required>
            </div>
            <button class="btn" type="submit">Subir</button>
        </form>

        <p><a href="perfil.php">Volver al perfil</a> | <a href="logout.php">Cerrar Sesión</a></p>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
</body>
</html>
