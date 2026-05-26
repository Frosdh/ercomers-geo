<?php
// ============================================================
//  Geo-Ecomers | Test de Conexión - Diagnóstico completo
//  ELIMINAR ESTE ARCHIVO en producción
// ============================================================

$host    = 'localhost';
$name    = 'corporat_ecommerce_geo';
$user    = 'corporat_ecomers_user';
$pass    = '?bPE(0P$4kgC@C.q';
$charset = 'utf8mb4';

echo '<style>body{font-family:sans-serif;padding:30px;max-width:700px}
      .ok{color:green;font-weight:bold} .err{color:#c00;font-weight:bold}
      pre{background:#f4f4f4;padding:15px;border-radius:6px;overflow-x:auto}</style>';

echo '<h2>🔍 Diagnóstico de conexión — Geo-Ecomers</h2>';
echo '<p><b>Host:</b> '   . $host . '</p>';
echo '<p><b>Base:</b> '   . $name . '</p>';
echo '<p><b>Usuario:</b> '. $user . '</p>';
echo '<p><b>Password:</b> ' . str_repeat('*', strlen($pass)) . ' (' . strlen($pass) . ' caracteres)</p>';

// Intento 1: localhost
echo '<h3>Intento 1 — host: localhost</h3>';
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname={$name};charset={$charset}",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo '<p class="ok">✅ Conexión exitosa con localhost</p>';
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo '<p>Tablas encontradas: <b>' . count($tables) . '</b></p>';
    echo '<pre>' . implode("\n", $tables) . '</pre>';
} catch (PDOException $e) {
    echo '<p class="err">❌ Falló con localhost</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';

    // Intento 2: 127.0.0.1
    echo '<h3>Intento 2 — host: 127.0.0.1</h3>';
    try {
        $pdo2 = new PDO(
            "mysql:host=127.0.0.1;dbname={$name};charset={$charset}",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo '<p class="ok">✅ Conexión exitosa con 127.0.0.1</p>';
        $tables2 = $pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo '<p>Tablas encontradas: <b>' . count($tables2) . '</b></p>';
        echo '<pre>' . implode("\n", $tables2) . '</pre>';
        echo '<p><b>→ Cambia DB_HOST a 127.0.0.1 en db.php</b></p>';
    } catch (PDOException $e2) {
        echo '<p class="err">❌ Falló con 127.0.0.1 también</p>';
        echo '<pre>' . htmlspecialchars($e2->getMessage()) . '</pre>';
        echo '<hr><h3>⚠️ Posibles causas:</h3>
        <ul>
          <li>El usuario <b>corporat_ecomers_user</b> no está asignado a la base en cPanel</li>
          <li>La contraseña no coincide</li>
          <li>La base de datos no existe con ese nombre exacto</li>
        </ul>
        <h3>✅ Solución en cPanel:</h3>
        <ol>
          <li>Ir a <b>cPanel → Bases de datos MySQL</b></li>
          <li>Sección <b>"Agregar usuario a base de datos"</b></li>
          <li>Seleccionar usuario <b>corporat_ecomers_user</b> y base <b>corporat_ecommerce_geo</b></li>
          <li>Marcar <b>TODOS LOS PRIVILEGIOS</b> y guardar</li>
        </ol>';
    }
}
?>
