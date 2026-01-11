<?php
/* conexion.php — PDO para PHP 5.3 (MySQL)
   - Usa la BD del server: emdupargov_facturacion
   - Forza UTF-8; si no hay utf8mb4, cae a utf8
*/

$host    = 'localhost';
$db      = 'emdupargov_facturacion'; // nombre EXACTO (cuidado mayúsculas/minúsculas en Linux)
$user    = 'root';                    // ajusta para tu server
$pass    = '';                        // ajusta para tu server
$charset = 'utf8mb4';                 // fallback a 'utf8' si hace falta

/* Opciones PDO (PHP 5.3 => array(), no []) */
$options = array(
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
);

/* Intento 1: con utf8mb4 */
$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
  // Asegura charset en conexiones antiguas
  $pdo->exec("SET NAMES '{$charset}'");
} catch (PDOException $e1) {
  // Intento 2: caer a utf8 en servidores viejos (< MySQL 5.5.3)
  if (strpos($e1->getMessage(), 'charset') !== false || strpos($e1->getMessage(), 'utf8mb4') !== false) {
    $charset = 'utf8';
    $dsn2 = "mysql:host={$host};dbname={$db};charset={$charset}";
    try {
      $pdo = new PDO($dsn2, $user, $pass, $options);
      $pdo->exec("SET NAMES '{$charset}'");
    } catch (PDOException $e2) {
      die('❌ Error de conexión (fallback utf8): ' . $e2->getMessage());
    }
  } else {
    die('❌ Error de conexión: ' . $e1->getMessage());
  }
}

/* Opcional: ajustes útiles (no fallan si no aplican) */
try {
  // Evita modos estrictos raros en hosting
  $pdo->exec("SET sql_mode=''");
  // Zona horaria (si tu server usa Bogotá)
  $pdo->exec("SET time_zone='-05:00'");
} catch (Exception $e) { /* ignorar */ }


try {
  $v = $pdo->query('SELECT VERSION() AS v')->fetch();
  // echo 'Conectado. MySQL: ' . $v['v']; // debug
} catch (Exception $e) {
  // echo 'Ping falló: ' . $e->getMessage();
}

