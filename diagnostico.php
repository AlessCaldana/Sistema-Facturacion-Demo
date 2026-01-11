<?php
/* diagnostico.php — Diagnóstico rápido del entorno y BD (compatible PHP 5.3) */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Diagnóstico rápido</h2>";

/* ===== Conexión ===== */
$host = 'localhost'; $user = 'root'; $pass = ''; $db = null;
if (file_exists(__DIR__ . '/conexion.php')) {
  require __DIR__ . '/conexion.php';
}
if (!$db) {
  $db = 'emdupargov_facturacion'; // <-- Ajusta según tu BD
}

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
  die("<b>ERROR conexión:</b> {$mysqli->connect_error}");
}
$mysqli->set_charset('utf8mb4');

echo "<p>✓ Conexión OK a <b>{$db}</b></p>";

/* ===== php.ini / extensiones ===== */
echo "<h3>PHP / Entorno</h3><ul>";
echo "<li>PHP: ".PHP_VERSION."</li>";
echo "<li>Loaded ini: <code>".(php_ini_loaded_file() ?: '(desconocido)')."</code></li>";
echo "<li>extension_dir: <code>".ini_get('extension_dir')."</code></li>";
echo "<li>ext/mysqli: ".(extension_loaded('mysqli')?'sí':'no')."</li>";
echo "<li>ext/gd: ".(extension_loaded('gd')?'sí':'no')."</li>";
echo "<li>ext/mbstring: ".(extension_loaded('mbstring')?'sí':'no')."</li>";
echo "</ul>";

/* ===== SQL_MODE ===== */
$res = $mysqli->query("SELECT @@sql_mode AS m");
$row = $res ? $res->fetch_assoc() : array('m'=>'(desconocido)');
echo "<p>SQL_MODE: <code>{$row['m']}</code></p>";

/* ===== Tablas ===== */
$mustHave = array('usuarios','clientes','productos','vehiculos','ventas','pagos','facturas','kardex');

$res = $mysqli->query("SHOW TABLES");
$tables = array();
while ($r = $res->fetch_array()) { $tables[] = $r[0]; }

echo "<h3>Tablas detectadas (".count($tables).")</h3><pre>".implode("\n",$tables)."</pre>";

$missing = array_diff($mustHave, $tables);
if ($missing) echo "<p><b>FALTAN tablas:</b> ".implode(', ', $missing)."</p>";
else echo "<p>✓ Todas las tablas base existen.</p>";

/* ===== Columnas ===== */
$columnsCheck = array(
  'usuarios'  => array('USUADOCU','USUANOMB','USUACLAV','USUAROLE','USUAESTA'),
  'clientes'  => array('CLIEDOCU','CLIENOMB','CLIEDIRE'),
  'productos' => array('PRODCODI','PRODDESC','PRODCANT'),
  'vehiculos' => array('VEHIPLAC'),
  'ventas'    => array('VENTRECI','VENTCLIE','VENTPLAC','VENTPROD','VENTCANT','VENTTOTA','VENTFEVE'),
  'pagos'     => array('PAGOFACT','PAGOFEPA','PAGOVAL'),
  'facturas'  => array('FACTRECI','FACTCLIE','FACTVALO','FACTESTA','FACTFECH','FACTUSUA','FACTREF'),
  'kardex'    => array('prodcodi','movimiento','cantidad','ref'),
);

echo "<h3>Columnas esperadas</h3>";
foreach ($columnsCheck as $t => $cols) {
  if (!in_array($t,$tables,true)) { echo "<p>- {$t}: (tabla no existe)</p>"; continue; }
  $r = $mysqli->query("SHOW COLUMNS FROM `$t`");
  if (!$r) { echo "<p>⚠ {$t}: ".$mysqli->error."</p>"; continue; }
  $have = array(); while ($c = $r->fetch_assoc()) $have[] = $c['Field'];
  $faltan = array_diff($cols,$have);
  echo $faltan ? "<p>⚠ {$t}: faltan => ".implode(', ',$faltan)."</p>" : "<p>✓ {$t}: columnas OK</p>";
}

/* ===== Conteos ===== */
echo "<h3>Prueba consultas</h3>";
function countOrErr($mysqli,$t){
  $q = $mysqli->query("SELECT COUNT(*) c FROM `$t`");
  return $q ? (int)$q->fetch_assoc()['c'] : 'ERROR';
}
echo "<p>usuarios: ".(in_array('usuarios',$tables)?countOrErr($mysqli,'usuarios'):'no')."</p>";
echo "<p>ventas: ".(in_array('ventas',$tables)?countOrErr($mysqli,'ventas'):'no')."</p>";
echo "<p>facturas: ".(in_array('facturas',$tables)?countOrErr($mysqli,'facturas'):'no')."</p>";

/* ===== Diferencias de totales ===== */
if (in_array('ventas',$tables,true)) {
  $sqlDif = "SELECT COUNT(*) c FROM ventas WHERE VENTOTAL IS NULL OR CAST(VENTOTAL AS DECIMAL(13,2)) <> CAST(VENTTOTA AS DECIMAL(13,2))";
  if ($r = $mysqli->query($sqlDif)) {
    $dif = (int)$r->fetch_assoc()['c'];
    echo $dif === 0
      ? "<p>✓ VENTOTAL coincide en todas</p>"
      : "<p>• Hay <b>{$dif}</b> filas con diferencia. Ejecutar:<br><code>UPDATE ventas SET VENTOTAL = VENTTOTA;</code></p>";
  }
}

/* ===== Sesión (compatible PHP 5.3) ===== */
if (session_id() === '') { session_start(); }
$_SESSION['__diag__'] = 'ok';

echo "<h3>Sesión</h3>";
echo "<p>Estado: ".(session_id()!==''?'activa':'inactiva')."</p>";
echo "<p>session.save_path: ".htmlspecialchars(ini_get('session.save_path') ?: '(no definido)')."</p>";

echo "<hr><p style='color:#666'>Diagnóstico finalizado.</p>";
