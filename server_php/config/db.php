<?php
// ============================================================
//  Geo-Ecomers | Configuración de Base de Datos
// ============================================================

define('DB_HOST',   'localhost');
define('DB_NAME',   'corporat_ecommerce_geo');
define('DB_USER',   'corporat_ecommerce_user'); // Usuario de la aplicación

define('DB_PASS',   'YcZQVfi95C8GcWX'); // Contraseña del usuario corporat_ecommerce_user
define('DB_CHARSET','utf8mb4');

// URL base del sitio (ajustar en producción)
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Connection Error: ' . $e->getMessage());
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#c00">
                <h2>Error de conexión a la base de datos</h2>
                <p>Por favor verifica la configuración en <code>config/db.php</code></p>
                </div>');
        }
    }
    return $pdo;
}
?>
