<?php
require_once __DIR__ . '/includes/page_bootstrap.php';

$userId = require_authenticated_user('login.php');

$mensaje = '';
$tipo = '';

// Configuracion de subida
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
$uploadDir = dirname(__DIR__) . '/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_multiple'])) {
    try {
        $pdo = getPDO();
        $files = $_FILES['files'] ?? null;

        if (!$files || !isset($files['name']) || !is_array($files['name'])) {
            $tipo = 'error';
            $mensaje = 'No se han recibido archivos para subir.';
        } else {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Supera el limite configurado en el servidor.',
                UPLOAD_ERR_FORM_SIZE => 'Supera el tamaño maximo permitido.',
                UPLOAD_ERR_PARTIAL => 'Se subio de forma incompleta.',
                UPLOAD_ERR_NO_FILE => 'No se selecciono archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal del servidor.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir en disco.',
                UPLOAD_ERR_EXTENSION => 'Una extension de PHP detuvo la subida.',
            ];

            $successCount = 0;
            $errorCount = 0;
            $errorDetails = [];

            $total = count($files['name']);
            for ($i = 0; $i < $total; $i++) {
                $originalName = (string)($files['name'][$i] ?? '');
                $tmpName = (string)($files['tmp_name'][$i] ?? '');
                $size = (int)($files['size'][$i] ?? 0);
                $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                $clientMime = strtolower((string)($files['type'][$i] ?? ''));

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK) {
                    $errorCount++;
                    $errorDetails[] = $originalName . ': ' . ($uploadErrors[$error] ?? 'Error inesperado en la subida.');
                    continue;
                }

                if ($size > $maxSize) {
                    $errorCount++;
                    $errorDetails[] = $originalName . ': supera 10MB.';
                    continue;
                }

                if (!is_uploaded_file($tmpName)) {
                    $errorCount++;
                    $errorDetails[] = $originalName . ': archivo temporal no valido.';
                    continue;
                }

                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);

                if ($safeExt === '' || !in_array($safeExt, $allowedExtensions, true)) {
                    $errorCount++;
                    $errorDetails[] = $originalName . ': extension no permitida.';
                    continue;
                }

                $detectedMime = null;
                if (class_exists('finfo')) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $detectedMime = $finfo->file($tmpName) ?: null;
                }

                $effectiveMime = strtolower((string)($detectedMime ?: $clientMime));
                $allowedMimes = $allowedMimeTypesByExtension[$safeExt];
                $mimeLooksValid = $effectiveMime === '' || in_array($effectiveMime, $allowedMimes, true);

                if (!$mimeLooksValid && $clientMime !== '' && !in_array($clientMime, $allowedMimes, true)) {
                    $errorCount++;
                    $errorDetails[] = $originalName . ': tipo MIME no coincide con la extension.';
                    continue;
                }

                try {
                    $randomSuffix = bin2hex(random_bytes(6));
                } catch (Exception $e) {
                    $randomSuffix = substr(sha1(uniqid((string)mt_rand(), true)), 0, 12);
                }

                $newName = $userId . '_' . time() . '_' . $randomSuffix . '.' . $safeExt;
                $destination = $uploadDir . $newName;

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $errorCount++;
                    $errorDetails[] = $originalName . ': no se pudo crear carpeta de destino.';
                    continue;
                }

                if (!is_writable($uploadDir)) {
                    $errorCount++;
                    $errorDetails[] = $originalName . ': carpeta de subidas sin permisos de escritura.';
                    continue;
                }

                if (!move_uploaded_file($tmpName, $destination)) {
                    $errorCount++;
                    $errorDetails[] = $originalName . ': fallo al mover archivo al destino.';
                    continue;
                }

                try {
                    $stmt = $pdo->prepare('INSERT INTO uploads (user_id, filename, filepath, mime, size) VALUES (:user_id, :filename, :filepath, :mime, :size)');
                    $stmt->execute([
                        'user_id' => $userId,
                        'filename' => basename($originalName),
                        'filepath' => 'uploads/' . $newName,
                        'mime' => $effectiveMime !== '' ? $effectiveMime : ($clientMime !== '' ? $clientMime : 'application/octet-stream'),
                        'size' => $size,
                    ]);
                    $successCount++;
                } catch (PDOException $e) {
                    @unlink($destination);
                    $errorCount++;
                    $errorDetails[] = $originalName . ': error al registrar en base de datos.';
                    error_log('Upload DB error: ' . $e->getMessage());
                }
            }

            if ($successCount > 0 && $errorCount === 0) {
                $tipo = 'exito';
                $mensaje = 'Se subieron correctamente ' . $successCount . ' archivo(s).';
            } elseif ($successCount > 0 && $errorCount > 0) {
                $tipo = 'error';
                $mensaje = 'Se subieron ' . $successCount . ' archivo(s), pero ' . $errorCount . ' fallaron. ' . implode(' | ', $errorDetails);
            } else {
                $tipo = 'error';
                $mensaje = 'No se pudo subir ningun archivo. ' . implode(' | ', $errorDetails);
            }
        }

        $uploads = fetch_uploads_visible_for_user($pdo, $userId);
    } catch (PDOException $e) {
        error_log('Error processing uploads: ' . $e->getMessage());
        $tipo = 'error';
        $mensaje = 'Ocurrio un error interno al procesar la subida.';
        $uploads = [];
    }
} else {
    try {
        $pdo = getPDO();
        $uploads = fetch_uploads_visible_for_user($pdo, $userId);
    } catch (PDOException $e) {
        error_log('Error fetching uploads: ' . $e->getMessage());
        $uploads = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis archivos</title>
    <link rel="stylesheet" href="../css/index.css">
    <style>
        .upload-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 16px;
        }

        .upload-modal.hidden {
            display: none;
        }

        .upload-dialog {
            width: min(680px, 100%);
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #dbe1ea;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.26);
            padding: 20px;
        }

        .upload-dialog h2 {
            margin: 0 0 14px;
            color: #0f172a;
        }

        .drop-zone {
            min-height: 220px;
            border: 2px dashed #93c5fd;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            cursor: pointer;
            background: #eff6ff;
            color: #1e293b;
            font-weight: 800;
            user-select: none;
            transition: border-color 0.15s ease, background 0.15s ease, transform 0.15s ease;
        }

        .drop-zone:hover,
        .drop-zone.is-dragover {
            border-color: #2563eb;
            background: #dbeafe;
            transform: translateY(-1px);
        }

        .selected-files {
            list-style: none;
            margin: 12px 0 0;
            padding: 0;
            max-height: 180px;
            overflow: auto;
        }

        .selected-files li {
            margin: 0 0 8px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid #dbe1ea;
            background: #f8fafc;
            font-size: 0.95rem;
        }

        .modal-actions {
            margin-top: 16px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .btn-secondary {
            background: #e2e8f0;
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .btn-danger {
            background: #ef4444;
            border-color: #dc2626;
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.35);
        }

        .btn-danger:hover {
            background: #dc2626;
            box-shadow: 0 14px 24px rgba(220, 38, 38, 0.45);
        }

        .confirm-text {
            margin: 0;
            color: #1e293b;
            line-height: 1.45;
        }

        @media (max-width: 700px) {
            .drop-zone {
                min-height: 170px;
                font-size: 0.96rem;
            }
        }
    </style>
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

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <p>
            <button type="button" id="openUploadModalBtn" class="btn">Subir archivos</button>
        </p>

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
                                | <a href="finanzas.php">Analizar</a>
                                <?php endif; ?>
                                |
                                <form method="POST" action="scripts/delete_upload.php" style="display:inline" class="delete-upload-form" data-filename="<?php echo htmlspecialchars($up['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo $up['id']; ?>">
                                    <button type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="uploadModal" class="upload-modal hidden" aria-hidden="true">
        <div class="upload-dialog" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
            <h2 id="uploadModalTitle">Subir archivos</h2>

            <form id="uploadMultipleForm" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="upload_multiple" value="1">

                <input
                    type="file"
                    id="hiddenFileInput"
                    name="files[]"
                    accept=".csv,.xls,.xlsx,.xlsm,.xlsb,.xltx"
                    multiple
                    style="display:none;"
                >

                <div id="dropZone" class="drop-zone" tabindex="0" aria-label="Area de subida de archivos">
                    ¡Ház click para elegir el archivo!
                </div>

                <ul id="selectedFilesList" class="selected-files"></ul>

                <div class="modal-actions">
                    <button type="button" id="cancelUploadBtn" class="btn-secondary">Cancelar</button>
                    <button type="submit" id="confirmUploadBtn" class="btn" disabled>Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteConfirmModal" class="upload-modal hidden" aria-hidden="true">
        <div class="upload-dialog" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
            <h2 id="deleteConfirmTitle">Confirmar eliminación</h2>
            <p id="deleteConfirmText" class="confirm-text">¿Seguro que quieres eliminar este archivo?</p>
            <div class="modal-actions">
                <button type="button" id="cancelDeleteBtn" class="btn-secondary">Cancelar</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Eliminar archivo</button>
            </div>
        </div>
    </div>
    <script src="../js/sticky-menu-toggle.js" defer></script>
    <script>
        (function() {
            'use strict';

            const modal = document.getElementById('uploadModal');
            const openBtn = document.getElementById('openUploadModalBtn');
            const cancelBtn = document.getElementById('cancelUploadBtn');
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('hiddenFileInput');
            const list = document.getElementById('selectedFilesList');
            const confirmBtn = document.getElementById('confirmUploadBtn');
            const form = document.getElementById('uploadMultipleForm');
            const deleteModal = document.getElementById('deleteConfirmModal');
            const deleteForms = document.querySelectorAll('.delete-upload-form');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const deleteConfirmText = document.getElementById('deleteConfirmText');

            let selected = [];
            let pendingDeleteForm = null;

            function fileKey(file) {
                return [file.name, file.size, file.lastModified].join('__');
            }

            function syncInputWithSelected() {
                const dt = new DataTransfer();
                selected.forEach(file => dt.items.add(file));
                fileInput.files = dt.files;
            }

            function renderSelected() {
                list.innerHTML = '';
                if (selected.length === 0) {
                    const li = document.createElement('li');
                    li.textContent = 'No hay archivos seleccionados.';
                    list.appendChild(li);
                    confirmBtn.disabled = true;
                    return;
                }

                selected.forEach(file => {
                    const li = document.createElement('li');
                    const kb = (file.size / 1024).toFixed(2);
                    li.textContent = file.name + ' (' + kb + ' KB)';
                    list.appendChild(li);
                });
                confirmBtn.disabled = false;
            }

            function addFiles(fileList) {
                const incoming = Array.from(fileList || []);
                if (incoming.length === 0) return;

                const existing = new Set(selected.map(fileKey));
                incoming.forEach(file => {
                    const k = fileKey(file);
                    if (!existing.has(k)) {
                        selected.push(file);
                        existing.add(k);
                    }
                });

                syncInputWithSelected();
                renderSelected();
            }

            function openModal() {
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
            }

            openBtn.addEventListener('click', openModal);

            cancelBtn.addEventListener('click', function() {
                closeModal();
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });

            dropZone.addEventListener('click', function() {
                fileInput.click();
            });

            dropZone.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    fileInput.click();
                }
            });

            fileInput.addEventListener('change', function() {
                addFiles(fileInput.files);
                fileInput.value = '';
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.remove('is-dragover');
                });
            });

            dropZone.addEventListener('drop', function(e) {
                addFiles(e.dataTransfer.files);
            });

            form.addEventListener('submit', function(e) {
                if (selected.length === 0) {
                    e.preventDefault();
                    alert('Selecciona al menos un archivo antes de confirmar.');
                }
            });

            function openDeleteModal(targetForm) {
                pendingDeleteForm = targetForm;
                const filename = targetForm.getAttribute('data-filename') || 'este archivo';
                deleteConfirmText.textContent = '¿Seguro que quieres eliminar "' + filename + '"? Esta acción no se puede deshacer.';
                deleteModal.classList.remove('hidden');
                deleteModal.setAttribute('aria-hidden', 'false');
            }

            function closeDeleteModal() {
                deleteModal.classList.add('hidden');
                deleteModal.setAttribute('aria-hidden', 'true');
                pendingDeleteForm = null;
            }

            deleteForms.forEach(function(deleteForm) {
                deleteForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    openDeleteModal(deleteForm);
                });
            });

            cancelDeleteBtn.addEventListener('click', function() {
                closeDeleteModal();
            });

            confirmDeleteBtn.addEventListener('click', function() {
                if (pendingDeleteForm) {
                    pendingDeleteForm.submit();
                }
            });

            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    closeDeleteModal();
                }
            });

            renderSelected();
        })();
    </script>
</body>
</html>


