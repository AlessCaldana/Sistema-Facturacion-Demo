<?php
/* ============================================================
   auditoria_bd.php — Auditoría avanzada (solo lectura) — PHP 5.3
   Requiere: conexion.php que exponga $pdo (PDO)
   ============================================================ */
session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: text/html; charset=UTF-8');

/* ===== Compat: ENT_SUBSTITUTE no existe en PHP 5.3 ===== */
if (!defined('ENT_SUBSTITUTE')) {
  define('ENT_SUBSTITUTE', 0);
}

/* ========= Helpers DB ========= */
/** @param PDO $pdo */
function qcol($pdo, $sql) {
  $st = $pdo->query($sql);
  return $st ? $st->fetchColumn() : null;
}
/** @param PDO $pdo */
function qall($pdo, $sql) {
  $st = $pdo->query($sql);
  return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
}
/** Devuelve la primera columna existente en $table de la lista $candidates */
function tryCol($pdo, $table, $candidates) {
  $cols = qall($pdo, "SHOW COLUMNS FROM `{$table}`");
  $have = array();
  foreach ($cols as $r) { $have[] = $r['Field']; }
  foreach ($candidates as $c) { if (in_array($c, $have, true)) return $c; }
  return null;
}

/* ========= Helpers UI ========= */
function badge($ok) {
  return $ok
    ? '<span style="background:#16a34a;color:#fff;padding:2px 8px;border-radius:999px;font-size:12px">PASS</span>'
    : '<span style="background:#dc2626;color:#fff;padding:2px 8px;border-radius:999px;font-size:12px">FAIL</span>';
}
function row($title, $ok, $detailHtml) {
  echo "<tr>
    <td style='padding:10px 12px;font-weight:600'>".$title."</td>
    <td style='padding:10px 12px;text-align:center'>".badge($ok)."</td>
    <td style='padding:10px 12px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px'>".$detailHtml."</td>
  </tr>";
}
function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ========= Tablas presentes ========= */
$_tmpRows = qall($pdo, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
$tables = array();
foreach ($_tmpRows as $r) { $tables[] = $r['TABLE_NAME']; }
$has = function($t) use ($tables) { return in_array($t, $tables, true); };

/* ========= Metadatos ========= */
$dbName   = esc((string)qcol($pdo,"SELECT DATABASE()"));
$version  = esc((string)qcol($pdo,"SELECT VERSION()"));
$sqlMode  = esc((string)qcol($pdo,"SELECT @@sql_mode"));
$collDb   = esc((string)qcol($pdo,"SELECT @@collation_database"));
$tz       = esc((string)qcol($pdo,"SELECT @@time_zone"));
$engine   = esc((string)qcol($pdo,"SELECT @@default_storage_engine"));
$autocomm = esc((string)qcol($pdo,"SELECT @@autocommit"));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Auditoría avanzada de base de datos</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#f5f7fb;margin:0}
  header{background:#1f6691;color:#fff;padding:16px 24px;box-shadow:0 2px 6px rgba(0,0,0,.15)}
  main{max-width:1100px;margin:24px auto;background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.08);overflow:hidden}
  table{width:100%;border-collapse:collapse}
  thead th{background:#f0f4f8;text-align:left;padding:12px}
  tbody tr+tr td{border-top:1px solid #eef2f7}
  code{background:#f6f8fa;padding:2px 6px;border-radius:6px}
  .small{color:#64748b;font-size:12px}
  .meta{padding:12px 16px;border-bottom:1px solid #eef2f7;background:#fbfdff}
  .meta b{color:#0b4a6a}
</style>
</head>
<body>
<header>
  <h2 style="margin:0">Auditoría avanzada — <?php echo $dbName; ?></h2>
  <div class="small">Solo lectura · <?php echo date('Y-m-d H:i:s'); ?></div>
</header>

<main>
  <div class="meta">
    <div><b>MySQL:</b> <?php echo $version; ?> · <b>Engine:</b> <?php echo $engine; ?> · <b>Autocommit:</b> <?php echo $autocomm; ?></div>
    <div><b>SQL_MODE:</b> <code><?php echo ($sqlMode ? $sqlMode : '(vacío)'); ?></code></div>
    <div><b>Collation DB:</b> <code><?php echo $collDb; ?></code> · <b>Time Zone:</b> <code><?php echo $tz; ?></code></div>
  </div>

  <table>
    <thead>
      <tr><th>Chequeo</th><th>Estado</th><th>Detalle</th></tr>
    </thead>
    <tbody>
<?php
/* ===== 1) USUARIOS ===== */
if ($has('usuarios')) {
  // 1.1 Duplicados por documento
  $dups = qall($pdo, "
    SELECT USUADOCU, COUNT(*) c
    FROM usuarios
    GROUP BY USUADOCU
    HAVING c > 1
    ORDER BY c DESC
  ");
  $ok = count($dups) === 0;
  $det = $ok ? 'Sin duplicados por <code>USUADOCU</code>'
             : ('Duplicados: <br><pre>'.esc(print_r($dups,true)).'</pre>');
  row('Usuarios: documentos duplicados', $ok, $det);

  // 1.2 Roles en catálogo (normalizamos posibles valores)
  $rolesValidos = array("Admin","Despachador","Vendedor","Consulta","admin","despachador","vendedor","consulta");
  $inv = qall($pdo, "
    SELECT USUADOCU, USUAROLE
    FROM usuarios
    WHERE (USUAROLE IS NULL OR TRIM(USUAROLE) = '' OR USUAROLE NOT IN ('".implode("','",$rolesValidos)."'))
    LIMIT 100
  ");
  $ok = count($inv) === 0;
  $det = $ok ? 'Roles OK. Catálogo: <code>Admin, Despachador, Vendedor, Consulta</code>'
             : ('Fuera de catálogo (máx 100):<pre>'.esc(print_r($inv,true)).'</pre>');
  row('Usuarios: roles válidos', $ok, $det);

  // 1.3 Estado válido (A/I) — tolera variantes de nombre
  $colEstado = tryCol($pdo, 'usuarios', array('USUAESTA','USUAESta','estado','ESTADO'));
  if ($colEstado) {
    $invE = qall($pdo, "
      SELECT USUADOCU, {$colEstado} AS estado
      FROM usuarios
      WHERE ({$colEstado} IS NULL OR {$colEstado} NOT IN ('A','I'))
      LIMIT 100
    ");
    row('Usuarios: estados (A/I)', count($invE)===0,
        count($invE)===0 ? 'OK' : 'Estados inválidos:<pre>'.esc(print_r($invE,true)).'</pre>');
  } else {
    row('Usuarios: estados (A/I)', false, 'No se encontró columna de estado en <code>usuarios</code>.');
  }

  // 1.4 Hash de password (bcrypt $2y$)
  $colPass = tryCol($pdo, 'usuarios', array('USUACLAV','USUAPASS','PASSWORD','clave'));
  if ($colPass) {
    $invP = qall($pdo, "
      SELECT USUADOCU, LEFT({$colPass},4) prefijo
      FROM usuarios
      WHERE ({$colPass} IS NULL OR LEFT({$colPass},4) <> '\\$2y$')
      LIMIT 100
    ");
    row('Usuarios: contraseñas hasheadas', count($invP)===0,
        count($invP)===0 ? 'Aparentan estar en bcrypt.' : 'No-bcrypt o nulas (máx 100):<pre>'.esc(print_r($invP,true)).'</pre>');
  } else {
    row('Usuarios: contraseñas hasheadas', false, 'No se encontró columna de password.');
  }
} else {
  row('Usuarios', false, 'La tabla <code>usuarios</code> no existe.');
}

/* ===== 2) CLIENTES ===== */
if ($has('clientes')) {
  // 2.1 Duplicados
  $dups = qall($pdo, "
    SELECT CLIEDOCU, COUNT(*) c
    FROM clientes
    GROUP BY CLIEDOCU
    HAVING c > 1
  ");
  row('Clientes: documentos duplicados', count($dups)===0,
      count($dups)===0 ? 'Sin duplicados' : 'Duplicados:<pre>'.esc(print_r($dups,true)).'</pre>');

  // 2.2 Emails mal formados (opcional)
  $colMail = tryCol($pdo, 'clientes', array('CLIEMAIL','email','correo'));
  if ($colMail) {
    $bad = qall($pdo, "
      SELECT CLIEDOCU, {$colMail} AS email
      FROM clientes
      WHERE {$colMail} IS NOT NULL AND {$colMail} <> '' AND {$colMail} NOT LIKE '%@%.%'
      LIMIT 100
    ");
    row('Clientes: correos aparentes válidos', count($bad)===0,
        count($bad)===0 ? 'OK' : 'Correos sospechosos (máx 100):<pre>'.esc(print_r($bad,true)).'</pre>');
  }
}

/* ===== 3) FACTURAS ===== */
if ($has('facturas')) {
  // 3.1 Facturas con cliente inexistente
  $orphan = qall($pdo, "
    SELECT f.FACTRECI, f.FACTCLIE
    FROM facturas f
    LEFT JOIN clientes c ON c.CLIEDOCU = f.FACTCLIE
    WHERE c.CLIEDOCU IS NULL
    LIMIT 100
  ");
  $ok = count($orphan)===0;
  $det = $ok ? 'Todas referencian clientes existentes.'
             : 'Sin cliente (máx 100):<pre>'.esc(print_r($orphan,true)).'</pre>';
  row('Facturas: referencia a cliente', $ok, $det);

  // 3.2 Valores no negativos
  $bad = (int) qcol($pdo, "SELECT COUNT(*) FROM facturas WHERE IFNULL(FACTVALO,0) < 0");
  row('Facturas: valores no negativos', $bad===0, $bad===0?'OK':"Registros con FACTVALO &lt; 0: <b>{$bad}</b>");

  // 3.3 Estados válidos
  $estValid = array("PENDIENTE","PAGADA","ANULADA","ENTREGADA","pendiente","pagada","anulada","entregada");
  $inv = qall($pdo, "
    SELECT FACTRECI, FACTESTA
    FROM facturas
    WHERE (FACTESTA IS NULL OR FACTESTA NOT IN ('".implode("','",$estValid)."'))
    LIMIT 100
  ");
  row('Facturas: estado en catálogo', count($inv)===0,
      count($inv)===0 ? 'OK (Catálogo: <code>PENDIENTE, PAGADA, ANULADA, ENTREGADA</code>)'
                      : 'Estados inválidos:<pre>'.esc(print_r($inv,true)).'</pre>');

  // 3.4 Fechas futuras
  $fut = (int) qcol($pdo, "SELECT COUNT(*) FROM facturas WHERE FACTFECH > CURRENT_DATE + INTERVAL 1 DAY");
  row('Facturas: fechas no futuras', $fut===0, $fut===0?'OK':"Fechas futuras: <b>{$fut}</b>");

  // 3.5 Duplicados por referencia (si existe FACTREF)
  $hasRef = tryCol($pdo, 'facturas', array('FACTREF','ref'));
  if ($hasRef) {
    $dupr = qall($pdo, "
      SELECT {$hasRef} AS ref, COUNT(*) c
      FROM facturas
      WHERE {$hasRef} IS NOT NULL AND {$hasRef} <> ''
      GROUP BY {$hasRef}
      HAVING c>1
      LIMIT 100
    ");
    row('Facturas: referencias únicas', count($dupr)===0,
        count($dupr)===0 ? 'OK' : 'Referencias duplicadas (máx 100):<pre>'.esc(print_r($dupr,true)).'</pre>');
  }
}

/* ===== 4) PAGOS ===== */
if ($has('pagos')) {
  // Normalizar nombre de columnas (tolerar PAGOVAL vs PAGOVALOR)
  $colVal = tryCol($pdo, 'pagos', array('PAGOVAL','PAGOVALOR','valor'));
  $colRec = tryCol($pdo, 'pagos', array('PAGORECI','PAGOFACT','FACTURA','PAGOFACT'));
  $colFec = tryCol($pdo, 'pagos', array('PAGOFEPA','fecha'));

  // 4.1 Pagos ligados a factura existente (buscamos por referencia típica)
  $orphan = qall($pdo, "
    SELECT p.*
    FROM pagos p
    LEFT JOIN facturas f ON f.FACTRECI = p.PAGOFACT
    WHERE f.FACTRECI IS NULL
    LIMIT 100
  ");
  row('Pagos: referencia a factura', count($orphan)===0,
      count($orphan)===0?'OK':'Pagos sin factura (máx 100):<pre>'.esc(print_r($orphan,true)).'</pre>');

  // 4.2 Montos negativos (si conocemos la columna)
  if ($colVal) {
    $neg = (int) qcol($pdo, "SELECT COUNT(*) FROM pagos WHERE IFNULL({$colVal},0) < 0");
    row('Pagos: montos no negativos', $neg===0, $neg===0?'OK':"Pagos con monto negativo: <b>{$neg}</b>");
  } else {
    row('Pagos: montos no negativos', false, 'No se encontró columna de valor en <code>pagos</code>.');
  }

  // 4.3 Duplicados exactos (si tenemos factura+fecha+valor)
  if ($colRec && $colFec && $colVal) {
    $dups = qall($pdo, "
      SELECT {$colRec} AS factura, {$colFec} AS fecha, {$colVal} AS valor, COUNT(*) c
      FROM pagos
      GROUP BY {$colRec}, {$colFec}, {$colVal}
      HAVING c>1
      ORDER BY c DESC
      LIMIT 100
    ");
    row('Pagos: duplicados exactos', count($dups)===0,
        count($dups)===0 ? 'Sin duplicados' : 'Duplicados (máx 100):<pre>'.esc(print_r($dups,true)).'</pre>');
  }
}

/* ===== 5) PRODUCTOS ===== */
if ($has('productos')) {
  $neg = (int) qcol($pdo, "SELECT COUNT(*) FROM productos WHERE IFNULL(PRODVAL,0) < 0");
  row('Productos: precio no negativo', $neg===0, $neg===0?'OK':"Con precio negativo: <b>{$neg}</b>");

  $vacios = (int) qcol($pdo, "SELECT COUNT(*) FROM productos WHERE TRIM(IFNULL(PRODDESC,'')) = ''");
  row('Productos: descripción no vacía', $vacios===0, $vacios===0?'OK':"Sin descripción: <b>{$vacios}</b>");

  // Stock negativos si existe PRODCANT
  $haveCant = tryCol($pdo, 'productos', array('PRODCANT','stock','cantidad'));
  if ($haveCant) {
    $negS = (int) qcol($pdo, "SELECT COUNT(*) FROM productos WHERE IFNULL({$haveCant},0) < 0");
    row('Productos: stock no negativo', $negS===0, $negS===0?'OK':"Stock negativo: <b>{$negS}</b>");
  }
}

/* ===== 6) ÍNDICES recomendados ===== */
$recomendaciones = array();
if ($has('facturas'))  $recomendaciones[] = "Índice en <code>facturas(FACTCLIE)</code> para consultas por cliente.";
if ($has('pagos'))     $recomendaciones[] = "Índice en <code>pagos(PAGOFACT)</code> para sumar pagos por factura.";
if ($has('usuarios'))  $recomendaciones[] = "Índice único en <code>usuarios(USUADOCU)</code> para evitar duplicados.";
if ($has('clientes'))  $recomendaciones[] = "Índice único en <code>clientes(CLIEDOCU)</code>.";
row('Sugerencias de índices', true, $recomendaciones ? ('<ul><li>'.implode('</li><li>',$recomendaciones).'</li></ul>') : 'Sin sugerencias');

/* ===== 7) Seguridad de sesión ===== */
$cookieFlags = array();
$cookieFlags[] = ini_get('session.cookie_httponly') ? 'HttpOnly=ON' : 'HttpOnly=OFF';
$cookieFlags[] = ini_get('session.cookie_secure') ? 'Secure=ON' : 'Secure=OFF (solo con HTTPS)';
row('Sesión: flags de cookie', true, 'Recomendado: HttpOnly ON, Secure ON (si usas HTTPS). Estado actual: '.implode(' · ',$cookieFlags));

/* ===== 8) Integridad básica (foreign keys) — opcional si usas InnoDB ===== */
$fkWarn = array();
if ($has('facturas') && $has('clientes')) {
  $nFk = (int)qcol($pdo, "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME='facturas'
                            AND REFERENCED_TABLE_NAME IS NOT NULL");
  $fkWarn[] = "FK declaradas en <code>facturas</code>: <b>{$nFk}</b>";
}
row('Integridad referencial', true, $fkWarn ? implode(' · ',$fkWarn) : 'No se detectaron FK declaradas (solo informativo).');

?>
    </tbody>
  </table>

  <div style="padding:16px 18px;color:#475569">
    <p class="small">Nota: si algún nombre de columna difiere en tu esquema real, este script intenta detectarlo (por ejemplo <code>PAGOVAL</code> vs <code>PAGOVALOR</code>). Si algo no calza, dime los nombres exactos y lo ajusto 1:1.</p>
  </div>
</main>
</body>
</html>
