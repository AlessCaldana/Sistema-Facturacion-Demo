<?php
/* prueba_end_to_end.php — SOLO LECTURA (compatible con PDO y PHP 5.3) */
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!ini_get('date.timezone')) { date_default_timezone_set('America/Bogota'); }

session_start();
echo "<h2>Prueba técnica — End to End (solo lectura)</h2>";

if (empty($_SESSION['usuario'])) {
  echo "<p style='color:#c00'>✗ No hay sesión. Inicia sesión y recarga.</p>";
  exit;
}
echo "<p style='color:#060'>✓ Sesión activa como <b>".htmlspecialchars($_SESSION['usuario'])."</b></p>";

/* ===== Conexión PDO (usa tu conexion.php) ===== */
$pdo = null;
if (file_exists(__DIR__.'/conexion.php')) {
  require __DIR__.'/conexion.php';   // este archivo debe definir $pdo
  if (isset($pdo) && $pdo instanceof PDO) {
    // Asegura atributos
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "<p style='color:#060'>✓ Conexión PDO detectada</p>";
  }
}
if (!$pdo) {
  echo "<p style='color:#c00'>✗ No se pudo obtener la conexión PDO desde conexion.php</p>";
  exit;
}

/* Helpers */
function tableExists($pdo, $t) {
  $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $stmt->execute(array($t));
  return (bool)$stmt->fetchColumn();
}
function countTable($pdo, $t) {
  $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `".$t."`");
  $row  = $stmt->fetch(PDO::FETCH_ASSOC);
  return (int)$row['c'];
}

/* ===== Chequeo de tablas clave ===== */
$tablas = array('usuarios','clientes','ventas','pagos');
$todoOk = true;
echo "<h3>Tablas clave</h3>";
foreach ($tablas as $t) {
  if (tableExists($pdo,$t)) {
    echo "✓ ".htmlspecialchars($t)."<br>";
  } else {
    echo "<span style='color:#c00'>✗ Falta ".htmlspecialchars($t)."</span><br>";
    $todoOk = false;
  }
}

/* ===== Métricas básicas ===== */
try {
  $ventas   = tableExists($pdo,'ventas')   ? countTable($pdo,'ventas')   : 0;
  $usuarios = tableExists($pdo,'usuarios') ? countTable($pdo,'usuarios') : 0;
  echo "<p>Ventas: <b>".(int)$ventas."</b> | Usuarios: <b>".(int)$usuarios."</b></p>";
} catch (Exception $e) {
  echo "<p style='color:#c00'>✗ No pude contar registros: ".htmlspecialchars($e->getMessage())."</p>";
  $todoOk = false;
}

/* ===== Diferencias de totales (VENTTOTA vs VENTOTAL) ===== */
if (tableExists($pdo,'ventas')) {
  try {
    $sql = "SELECT COUNT(*) AS c
            FROM ventas
            WHERE VENTOTAL IS NULL
               OR CAST(VENTOTAL AS DECIMAL(13,2)) <> CAST(VENTTOTA AS DECIMAL(13,2))";
    $res = $pdo->query($sql);
    $row = $res->fetch(PDO::FETCH_ASSOC);
    $dif = (int)$row['c'];

    if ($dif === 0) {
      echo "<p style='color:#060'>✓ VENTOTAL coincide con VENTTOTA en todas las filas</p>";
    } else {
      echo "<p style='color:#d58512'>• Aviso: hay <b>".$dif."</b> filas donde <b>VENTOTAL</b> difiere de <b>VENTTOTA</b>.</p>";
      echo "<p>Si autorizan sincronizar: <code>UPDATE ventas SET VENTOTAL = VENTTOTA;</code></p>";
    }
  } catch (Exception $e) {
    echo "<p style='color:#c00'>✗ No pude comparar totales: ".htmlspecialchars($e->getMessage())."</p>";
    $todoOk = false;
  }
}

/* ===== Ejemplo de venta para mostrar en demo ===== */
if (tableExists($pdo,'ventas')) {
  try {
    $stmt = $pdo->query("SELECT VENTRECI, VENTCLIE, VENTPLAC, VENTPROD, VENTCANT, VENTTOTA, VENTOTAL, VENTFEVE
                         FROM ventas ORDER BY VENTRECI DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      echo "<h3>Última venta</h3><pre>".htmlspecialchars(print_r($row, true))."</pre>";
    }
  } catch (Exception $e) {
    echo "<p style='color:#c00'>✗ No pude traer última venta: ".htmlspecialchars($e->getMessage())."</p>";
    $todoOk = false;
  }
}

/* ===== Resultado final ===== */
echo $todoOk
  ? "<h3 style='color:#060'>✅ LISTO PARA DEMO</h3>"
  : "<h3 style='color:#c00'>❌ PENDIENTE — revisar notas de arriba</h3>";
