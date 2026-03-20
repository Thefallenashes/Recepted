<?php
require_once __DIR__ . '/script_bootstrap.php';
require_once __DIR__ . '/../includes/sticky_menu.php';

$userId = require_script_user('redirect', '../login.php');

$mensaje = '';
$tipo = '';

// Configuración
$maxSize = 10 * 1024 * 1024; // 10 MB
$allowedMimeTypesByExtension = [
    'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'xlsm' => ['application/vnd.ms-excel.sheet.macroenabled.12', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'xlsb' => ['application/vnd.ms-excel.sheet.binary.macroenabled.12', 'application/octet-stream'],
    'xltx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.template', 'application/zip', 'application/octet-stream'],
];
$allowedExtensions = array_keys($allowedMimeTypesByExtension);
$uploadDir = dirname(__DIR__, 2) . '/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file'])) {
        $tipo = 'error';
        $mensaje = 'No se ha seleccionado ningún archivo.';
    } else {
        $file = $_FILES['file'];
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo supera el límite configurado en el servidor.',
            UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió de forma incompleta.',
            UPLOAD_ERR_NO_FILE => 'No se ha seleccionado ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo.',
        ];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $tipo = 'error';
            $mensaje = $uploadErrors[$file['error']] ?? 'Se produjo un error inesperado durante la subida.';
        } elseif ($file['size'] > $maxSize) {
            $tipo = 'error';
            $mensaje = 'El archivo supera el tamaño máximo permitido (10MB).';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $tipo = 'error';
            $mensaje = 'El archivo recibido no es válido.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);

            $detectedMime = null;
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($file['tmp_name']) ?: null;
            }

            $clientMime = strtolower((string) ($file['type'] ?? ''));
            $effectiveMime = strtolower((string) ($detectedMime ?: $clientMime));

            if ($safeExt === '' || !in_array($safeExt, $allowedExtensions, true)) {
                $tipo = 'error';
                $mensaje = 'Extensión no permitida. Solo se admiten archivos Excel: csv, xls, xlsx, xlsm, xlsb y xltx.';
            } else {
                $allowedMimes = $allowedMimeTypesByExtension[$safeExt];
                $mimeLooksValid = $effectiveMime === '' || in_array($effectiveMime, $allowedMimes, true);

                if (!$mimeLooksValid && $clientMime !== '' && !in_array($clientMime, $allowedMimes, true)) {
                    $tipo = 'error';
                    $mensaje = 'El tipo de archivo detectado no coincide con la extensión seleccionada.';
                } else {
                    try {
                        $randomSuffix = bin2hex(random_bytes(6));
                    } catch (Exception $e) {
                        $randomSuffix = bin2hex((string) mt_rand()) . bin2hex((string) microtime(true));
                    }

                    $newName = $userId . '_' . time() . '_' . $randomSuffix . '.' . $safeExt;
                    $destination = $uploadDir . $newName;

                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                        $tipo = 'error';
                        $mensaje = 'No se pudo crear la carpeta de destino para la subida.';
                    } elseif (!is_writable($uploadDir)) {
                        $tipo = 'error';
                        $mensaje = 'La carpeta de subidas no tiene permisos de escritura.';
                    } elseif (move_uploaded_file($file['tmp_name'], $destination)) {
                        try {
                            $pdo = getPDO();
                            $stmt = $pdo->prepare('INSERT INTO uploads (user_id, filename, filepath, mime, size) VALUES (:user_id, :filename, :filepath, :mime, :size)');
                            $relativePath = 'uploads/' . $newName;
                            $stmt->execute([
                                'user_id' => $userId,
                                'filename' => basename((string) $file['name']),
                                'filepath' => $relativePath,
                                'mime' => $effectiveMime !== '' ? $effectiveMime : ($clientMime !== '' ? $clientMime : 'application/octet-stream'),
                                'size' => $file['size']
                            ]);

                            $tipo = 'exito';
                            $mensaje = 'Archivo subido y registrado correctamente.';
                        } catch (PDOException $e) {
                            error_log('Upload DB error: ' . $e->getMessage());
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
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subir archivo</title>
    <link rel="stylesheet" href="../../css/index.css">
</head>
<body>
    <?php
    render_sticky_menu([
        'container_class' => 'sticky-home-menu',
        'inner_class' => 'sticky-home-menu-inner',
        'image_base_path' => '../../images',
        'home_href' => '../home.php',
        'logout_href' => '../scripts/logout.php',
        'nav_items' => [
            ['href' => '../finanzas.php', 'label' => 'Finanzas'],
            ['href' => '../tickets.php', 'label' => 'Tickets'],
            ['href' => '../config.php', 'label' => 'Configuración'],
            ['href' => '../admin_panel.php', 'label' => 'Panel de administracion', 'min_role' => 'admin'],
            ['href' => '../superadmin_console.php', 'label' => 'Consola', 'min_role' => 'superadmin'],
        ],
    ]);
    ?>

    <div class="index-container">
        <h1>Subir archivo</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="file">Archivo:</label>
                <input type="file" id="file" name="file" accept=".csv,.xls,.xlsx,.xlsm,.xlsb,.xltx" required>
            </div>
            <button class="btn" type="submit">Subir</button>
        </form>

        <p><a href="../mis_uploads.php" class="btn">Ir a mis archivos</a></p>
    </div>
    <script src="../../js/sticky-menu-toggle.js" defer></script>
</body>
</html>


