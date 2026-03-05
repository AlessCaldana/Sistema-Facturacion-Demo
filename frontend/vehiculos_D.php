<?php
session_start();
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/demo_config.php';
require_once __DIR__ . '/conexion.php';

if (function_exists('require_perm')) {
  require_perm('tablas', 'write');
}

$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';
$demo_mode = (defined('DEMO_PUBLICO') && DEMO_PUBLICO);
$max_vehiculos = (defined('DEMO_MAX_VEHICULOS') ? (int)DEMO_MAX_VEHICULOS : 2);
$mensaje = '';
$error_esquema = '';
$schema_notes = array();

function h($v) { return htmlspecialchars((string)(isset($v) ? $v : ''), ENT_QUOTES, 'UTF-8'); }

function table_exists($pdo, $table){
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $st->execute(array($table));
    return ((int)$st->fetchColumn() > 0);
  } catch (Exception $e) {
    return false;
  }
}

function get_table_columns($pdo, $table){
  $cols = array();
  try {
    $q = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    if ($q) {
      foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($r['Field'])) $cols[] = $r['Field'];
      }
    }
  } catch (Exception $e) {}
  return $cols;
}

function pick_column($cols, $candidates){
  foreach ($candidates as $c) {
    if (in_array($c, $cols, true)) return $c;
  }
  return null;
}

function add_column_if_missing($pdo, $table, $column, $definition){
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $st->execute(array($table, $column));
    if ((int)$st->fetchColumn() > 0) {
      return false;
    }
    $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    return true;
  } catch (Exception $e) {
    return false;
  }
}

$schema_ok = false;
$cols = array();

$colPlaca = null;
$colMarca = null;
$colModelo = null;
$colConductor = null;
$colTipoDoc = null;
$colDocumento = null;
$colAnio = null;

if (!table_exists($pdo, 'vehiculos')) {
  $error_esquema = 'La tabla vehiculos no existe en la base de datos.';
} else {
  $cols = get_table_columns($pdo, 'vehiculos');

  if (pick_column($cols, array('VEHINOCO', 'conductor', 'nombre_conductor')) === null) {
    if (add_column_if_missing($pdo, 'vehiculos', 'conductor', 'VARCHAR(150) NULL')) {
      $schema_notes[] = 'Se agrego columna conductor.';
    }
  }
  if (pick_column($cols, array('VEHITDOC', 'tipo_doc', 'tipodoc')) === null) {
    if (add_column_if_missing($pdo, 'vehiculos', 'tipo_doc', 'VARCHAR(10) NULL')) {
      $schema_notes[] = 'Se agrego columna tipo_doc.';
    }
  }
  if (pick_column($cols, array('VEHIDOCU', 'doc', 'numero_doc', 'documento')) === null) {
    if (add_column_if_missing($pdo, 'vehiculos', 'documento', 'VARCHAR(30) NULL')) {
      $schema_notes[] = 'Se agrego columna documento.';
    }
  }

  if (!empty($schema_notes)) {
    $cols = get_table_columns($pdo, 'vehiculos');
  }

  $colPlaca     = pick_column($cols, array('VEHIPLAC', 'placa'));
  $colMarca     = pick_column($cols, array('VEHIMARC', 'marca'));
  $colModelo    = pick_column($cols, array('VEHIMODE', 'modelo'));
  $colConductor = pick_column($cols, array('VEHINOCO', 'conductor', 'nombre_conductor'));
  $colTipoDoc   = pick_column($cols, array('VEHITDOC', 'tipo_doc', 'tipodoc'));
  $colDocumento = pick_column($cols, array('VEHIDOCU', 'doc', 'numero_doc', 'documento'));
  $colAnio      = pick_column($cols, array('anio', 'VEHIANIO'));

  $schema_ok = ($colPlaca !== null);
  if (!$schema_ok) {
    $error_esquema = 'La tabla vehiculos no tiene una columna de placa compatible.';
  }
}

if ($schema_ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $placa = strtoupper(trim(isset($_POST['placa']) ? $_POST['placa'] : ''));
  $marca = trim(isset($_POST['marca']) ? $_POST['marca'] : '');
  $modelo = trim(isset($_POST['modelo']) ? $_POST['modelo'] : '');
  $anio = isset($_POST['anio']) ? (int)$_POST['anio'] : 0;
  $conductor = trim(isset($_POST['conductor']) ? $_POST['conductor'] : '');
  $tipo_doc = strtoupper(trim(isset($_POST['tipo_doc']) ? $_POST['tipo_doc'] : 'CC'));
  $documento = trim(isset($_POST['documento']) ? $_POST['documento'] : '');

  if ($placa === '') {
    $mensaje = 'La placa es obligatoria.';
  } elseif (!in_array($tipo_doc, array('CC', 'CE', 'PAS'), true)) {
    $mensaje = 'Tipo de documento invalido.';
  } elseif ($demo_mode && function_exists('demo_limite_alcanzado') && demo_limite_alcanzado($pdo, 'vehiculos', $max_vehiculos, '', array())) {
    $mensaje = 'Limite demo alcanzado: maximo ' . $max_vehiculos . ' vehiculos.';
  } else {
    try {
      $insertCols = array($colPlaca);
      $insertVals = array($placa);

      if ($colMarca !== null)     { $insertCols[] = $colMarca;     $insertVals[] = $marca; }
      if ($colModelo !== null)    { $insertCols[] = $colModelo;    $insertVals[] = $modelo; }
      if ($colAnio !== null)      { $insertCols[] = $colAnio;      $insertVals[] = ($anio > 0 ? $anio : null); }
      if ($colConductor !== null) { $insertCols[] = $colConductor; $insertVals[] = $conductor; }
      if ($colTipoDoc !== null)   { $insertCols[] = $colTipoDoc;   $insertVals[] = $tipo_doc; }
      if ($colDocumento !== null) { $insertCols[] = $colDocumento; $insertVals[] = $documento; }

      $colSql = '`' . implode('`,`', $insertCols) . '`';
      $phSql = implode(',', array_fill(0, count($insertVals), '?'));

      $st = $pdo->prepare('INSERT INTO vehiculos (' . $colSql . ') VALUES (' . $phSql . ')');
      $st->execute($insertVals);
      $mensaje = 'Vehiculo creado.';
    } catch (PDOException $e) {
      if ($e->getCode() === '23000') {
        $mensaje = 'La placa ya existe.';
      } else {
        $mensaje = 'Error: ' . $e->getMessage();
      }
    }
  }
}

if ($mensaje === '' && !empty($schema_notes)) {
  $mensaje = implode(' ', $schema_notes);
}

$rows = array();
if ($schema_ok) {
  $sql = 'SELECT `' . $colPlaca . '` AS PLACA';
  $sql .= $colMarca     ? ', `' . $colMarca . '` AS MARCA'         : ", '' AS MARCA";
  $sql .= $colModelo    ? ', `' . $colModelo . '` AS MODELO'       : ", '' AS MODELO";
  $sql .= $colAnio      ? ', `' . $colAnio . '` AS ANIO'           : ", '' AS ANIO";
  $sql .= $colConductor ? ', `' . $colConductor . '` AS CONDUCTOR' : ", '' AS CONDUCTOR";
  $sql .= $colTipoDoc   ? ', `' . $colTipoDoc . '` AS TIPODOC'     : ", '' AS TIPODOC";
  $sql .= $colDocumento ? ', `' . $colDocumento . '` AS DOCUMENTO' : ", '' AS DOCUMENTO";
  $sql .= ' FROM vehiculos ORDER BY `' . $colPlaca . '`';

  $q = $pdo->query($sql);
  $rows = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gestion de Vehiculos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    html,body{margin:0 !important;padding:0 !important;}
    header,.topbar{margin-top:0 !important;}
    :root{--azul:#1f6691;--azul-2:#2f83b5;--azul-3:#154c6d;--bg:#eef3f8;--txt:#153549;--line:#d9e5ef;--danger:#d9534f;--danger-2:#b63733;}
    body{min-height:100vh;background:linear-gradient(180deg,#f3f7fb 0%,var(--bg) 100%);font-family:"Segoe UI",Tahoma,Arial,sans-serif;color:var(--txt);}
    .topbar{position:sticky;top:0;z-index:1100;background:linear-gradient(135deg,var(--azul),var(--azul-2));box-shadow:0 8px 18px rgba(13,53,79,.18);}
    .topbar-inner{max-width:1320px;margin:0 auto;padding:10px 14px;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:10px;}
    .btn-volver{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:#fff;border:1px solid rgba(255,255,255,.40);border-radius:10px;padding:8px 12px;font-weight:800;background:rgba(255,255,255,.08);transition:.2s ease;}
    .btn-volver:hover{color:#fff;background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.55);}
    .topbar-title{margin:0;color:#fff;text-align:center;font-size:1.55rem;font-weight:900;letter-spacing:.2px;}
    .user-btn{justify-self:end;display:inline-flex;align-items:center;gap:8px;cursor:pointer;color:#fff;padding:7px 10px;border-radius:12px;position:relative;user-select:none;}
    .user-btn:hover{background:rgba(255,255,255,.10);}
    .user-menu{position:absolute;right:0;top:calc(100% + 8px);min-width:190px;display:none;background:#fff;border:1px solid var(--line);border-radius:12px;padding:6px;box-shadow:0 14px 26px rgba(0,0,0,.16);z-index:1200;}
    .user-item{display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:10px;text-decoration:none;font-weight:700;color:var(--danger);}
    .user-item:hover{background:#ffe6e5;color:var(--danger-2);}
    .main-wrap{max-width:1220px;margin:28px auto;padding:0 14px;}
    .card-soft{border-radius:16px;border:1px solid #e3ecf4;box-shadow:0 12px 28px rgba(17,58,84,.10);background:#fff;}
    .section-title{font-weight:900;margin-bottom:14px;color:#1a4762;}
    .form-label{font-weight:700;color:#244f67;}
    .form-control,.form-select{border-radius:10px;border:1px solid #cfdfea;min-height:42px;}
    .btn-main{background:linear-gradient(135deg,var(--azul),var(--azul-2));border:none;color:#fff;border-radius:10px;font-weight:800;min-height:42px;}
    .btn-main:hover{color:#fff;filter:brightness(1.06);}
    .table-wrap{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#fff;}
    .table thead th{background:#f3f8fc;color:#204760;font-weight:800;border-bottom:1px solid var(--line);}
    .alert{border-radius:12px;}
    @media (max-width: 900px){ .topbar-title{ font-size:1.25rem; } }
    @media (max-width: 768px){.topbar-inner{grid-template-columns:1fr;gap:8px;}.btn-volver,.user-btn{justify-self:center;}.topbar-title{font-size:1.1rem;}}
  </style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a href="mantenimiento_tablas_D.php" class="btn-volver"><i class="bi bi-arrow-left-circle"></i> Volver</a>
    <h1 class="topbar-title">Gestion de Vehiculos</h1>
    <div class="user-btn" id="userBtn"><i class="bi bi-person-circle"></i> <?php echo h($usuario); ?>
      <div class="user-menu" id="userMenu"><a href="logout.php" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesion</a></div>
    </div>
  </div>
</header>

<div class="main-wrap">
  <?php if ($demo_mode): ?><div class="alert alert-warning mb-3">ENTORNO DEMO: maximo <?php echo (int)$max_vehiculos; ?> vehiculos. Solo crear.</div><?php endif; ?>
  <?php if ($mensaje !== ''): ?><div class="alert alert-info mb-3"><?php echo h($mensaje); ?></div><?php endif; ?>
  <?php if ($error_esquema !== ''): ?><div class="alert alert-danger mb-3"><?php echo h($error_esquema); ?></div><?php else: ?>

  <div class="card-soft p-3 p-md-4 mb-4">
    <h4 class="section-title">Nuevo Vehiculo</h4>
    <form method="post" class="row g-3">
      <div class="col-md-3"><label class="form-label">Placa</label><input name="placa" class="form-control" maxlength="20" required></div>
      <div class="col-md-3"><label class="form-label">Marca</label><input name="marca" class="form-control" maxlength="100"></div>
      <div class="col-md-2"><label class="form-label">Modelo</label><input name="modelo" class="form-control" maxlength="100"></div>
      <div class="col-md-2"><label class="form-label">Año</label><input name="anio" class="form-control" type="number" min="1950" max="2100"></div>
      <div class="col-md-4"><label class="form-label">Conductor</label><input name="conductor" class="form-control" maxlength="150"></div>
      <div class="col-md-2"><label class="form-label">Tipo Doc.</label>
        <select name="tipo_doc" class="form-select"><option value="CC">CC - Cedula</option><option value="CE">CE - Cedula Extranjera</option><option value="PAS">PAS - Pasaporte</option></select>
      </div>
      <div class="col-md-3"><label class="form-label">Numero Doc.</label><input name="documento" class="form-control" maxlength="30"></div>
      <div class="col-md-3 d-flex align-items-end"><button class="btn btn-main w-100"><i class="bi bi-save me-1"></i> Guardar</button></div>
    </form>
  </div>

  <div class="card-soft p-3 p-md-4">
    <h4 class="section-title">Lista de Vehiculos</h4>
    <div class="table-wrap"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Placa</th><th>Marca</th><th>Modelo</th><th>Año</th><th>Conductor</th><th>Tipo Doc.</th><th>Numero Doc.</th></tr></thead>
      <tbody>
      <?php if (!empty($rows)): foreach ($rows as $r): ?>
        <tr>
          <td><?php echo h(isset($r['PLACA']) ? $r['PLACA'] : ''); ?></td>
          <td><?php echo h(isset($r['MARCA']) ? $r['MARCA'] : ''); ?></td>
          <td><?php echo h(isset($r['MODELO']) ? $r['MODELO'] : ''); ?></td>
          <td><?php echo h(isset($r['ANIO']) ? $r['ANIO'] : ''); ?></td>
          <td><?php echo h(isset($r['CONDUCTOR']) ? $r['CONDUCTOR'] : ''); ?></td>
          <td><?php echo h(isset($r['TIPODOC']) ? $r['TIPODOC'] : ''); ?></td>
          <td><?php echo h(isset($r['DOCUMENTO']) ? $r['DOCUMENTO'] : ''); ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No hay vehiculos registrados.</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div></div>
  </div>

  <?php endif; ?>
</div>

<script>
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if (!btn || !menu) return;
  btn.addEventListener('click', function(e){ e.preventDefault(); menu.style.display = (menu.style.display === 'block') ? 'none' : 'block'; });
  document.addEventListener('click', function(e){ if (!btn.contains(e.target)) menu.style.display = 'none'; });
})();
</script>
</body>
</html>
