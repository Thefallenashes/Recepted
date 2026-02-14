Instrucciones para crear la base de datos y conectar la aplicación

1) Importar el script SQL

- Usando phpMyAdmin: abre phpMyAdmin, selecciona "Importar" y sube `src/utils/db_init.sql`.
- Usando línea de comandos MySQL (desde la carpeta del proyecto en Windows PowerShell):

```bash
mysql -u root -p < "c:/xampp/htdocs/TFG/src/utils/db_init.sql"
```

(ajusta `-u` y el path según tu entorno)

2) Configurar la conexión en `src/utils/db.php`

- Edita las credenciales en el array `$config` (usuario, contraseña, host, puerto).

3) Uso en tus páginas PHP

- Incluye la conexión y usa PDO:

```php
require_once __DIR__ . '/utils/db.php';
$pdo = getPDO();
// ejemplo: obtener usuario por correo
$stmt = $pdo->prepare('SELECT * FROM users WHERE correo = :correo');
$stmt->execute(['correo' => $email]);
$user = $stmt->fetch();
```

4) Notas de diseño

- `finanzas.user_id` tiene una restricción UNIQUE para asegurar la relación 1:1 con `users`.
- `uploads.user_id` es nullable y se pone a NULL si el usuario es eliminado.
- Las contraseñas deben almacenarse usando `password_hash()` en el código de registro (actualmente tu `register.php` usa archivo JSON). Considera migrar el registro al uso de la base de datos.

5) Siguiente paso recomendado

- Migrar `register.php` y `login.php` para usar la base de datos en lugar del archivo JSON (`data/usuarios.json`).
