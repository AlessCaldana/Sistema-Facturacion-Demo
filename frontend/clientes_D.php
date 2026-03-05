<?php
session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/demo_config.php';

$demo_mode = (defined('DEMO_PUBLICO') && DEMO_PUBLICO);
$max_clientes = (defined('DEMO_MAX_CLIENTES') ? (int)DEMO_MAX_CLIENTES : 3);

$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';

function h($v){ return htmlspecialchars((string)(isset($v)?$v:''), ENT_QUOTES, 'UTF-8'); }
function is_digit_str($s){ return ($s !== '' && preg_match('/^\d+$/', $s)); }

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

$ok  = isset($_GET['ok'])  ? $_GET['ok']  : '';
$err = isset($_GET['err']) ? $_GET['err'] : '';
$mensaje_error_global = '';
$errores = array();

$old = array('CLIEDOCU'=>'','CLIENOMB'=>'','CLIEDIRE'=>'','CLIETELE'=>'','CLIEMAIL'=>'');

function go_redirect($okMsg = '', $errMsg = '') {
  $q = array();
  if ($okMsg  !== '') $q[] = 'ok='  . urlencode($okMsg);
  if ($errMsg !== '') $q[] = 'err=' . urlencode($errMsg);
  header('Location: clientes_D.php' . (empty($q) ? '' : ('?' . implode('&', $q))));
  exit;
}

$clientes = array();
$accionForm = 'insertar';

$clientesTableOk = table_exists($pdo, 'clientes');
$cols = $clientesTableOk ? get_table_columns($pdo, 'clientes') : array();

$colDoc  = pick_column($cols, array('CLIEDOCU', 'cliente_doc', 'documento', 'doc', 'id_cliente', 'id'));
$colNom  = pick_column($cols, array('CLIENOMB', 'cliente_nombre', 'nombre', 'razon_social', 'nombres'));
$colDir  = pick_column($cols, array('CLIEDIRE', 'direccion', 'dir', 'domicilio'));
$colTel  = pick_column($cols, array('CLIETELE', 'telefono', 'tel', 'celular', 'movil'));
$colMail = pick_column($cols, array('CLIEMAIL', 'email', 'correo', 'mail'));

$schema_ok = ($clientesTableOk && $colDoc && $colNom);
if (!$clientesTableOk) {
  $mensaje_error_global = 'La tabla clientes no existe en la base de datos.';
} elseif (!$schema_ok) {
  $mensaje_error_global = 'La tabla clientes no tiene columnas compatibles (documento y nombre).';
}

if ($schema_ok && isset($_GET['editar'])) {
  $sql = 'SELECT `' . $colDoc . '` AS CLIEDOCU, `' . $colNom . '` AS CLIENOMB';
  $sql .= $colDir  ? ', `' . $colDir . '` AS CLIEDIRE'  : ", '' AS CLIEDIRE";
  $sql .= $colTel  ? ', `' . $colTel . '` AS CLIETELE'  : ", '' AS CLIETELE";
  $sql .= $colMail ? ', `' . $colMail . '` AS CLIEMAIL' : ", '' AS CLIEMAIL";
  $sql .= ' FROM clientes WHERE `' . $colDoc . '` = ? LIMIT 1';

  $st = $pdo->prepare($sql);
  $st->execute(array($_GET['editar']));
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $old['CLIEDOCU'] = isset($row['CLIEDOCU']) ? $row['CLIEDOCU'] : '';
    $old['CLIENOMB'] = isset($row['CLIENOMB']) ? $row['CLIENOMB'] : '';
    $old['CLIEDIRE'] = isset($row['CLIEDIRE']) ? $row['CLIEDIRE'] : '';
    $old['CLIETELE'] = isset($row['CLIETELE']) ? $row['CLIETELE'] : '';
    $old['CLIEMAIL'] = isset($row['CLIEMAIL']) ? $row['CLIEMAIL'] : '';
    $accionForm = 'actualizar';
  }
}

if ($schema_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
  $accion = $_POST['accion'];

  foreach ($old as $k => $v) {
    $old[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : '';
  }

  if ($accion === 'insertar' || $accion === 'actualizar') {
    if ($old['CLIEDOCU']==='' || !is_digit_str($old['CLIEDOCU']) || strlen($old['CLIEDOCU'])>20) {
      $errores['CLIEDOCU'] = 'Documento invalido: solo digitos (max. 20).';
    }
    if ($old['CLIENOMB']==='') {
      $errores['CLIENOMB'] = 'El nombre es obligatorio.';
    }
    if ($old['CLIETELE']!=='' && (!is_digit_str($old['CLIETELE']) || strlen($old['CLIETELE'])<7 || strlen($old['CLIETELE'])>15)) {
      $errores['CLIETELE'] = 'Telefono invalido: 7 a 15 digitos.';
    }
    if ($old['CLIEMAIL']!=='' && !filter_var($old['CLIEMAIL'], FILTER_VALIDATE_EMAIL)) {
      $errores['CLIEMAIL'] = 'Correo electronico invalido.';
    }
  }

  if (empty($errores)) {
    try {
      if ($accion === 'insertar') {
        if ($demo_mode && function_exists('demo_limite_alcanzado') && demo_limite_alcanzado($pdo, 'clientes', $max_clientes, '', array())) {
          go_redirect('', 'Limite demo alcanzado: maximo ' . $max_clientes . ' clientes.');
        }
        $insertCols = array($colDoc, $colNom);
        $insertVals = array($old['CLIEDOCU'], $old['CLIENOMB']);

        if ($colDir)  { $insertCols[] = $colDir;  $insertVals[] = $old['CLIEDIRE']; }
        if ($colTel)  { $insertCols[] = $colTel;  $insertVals[] = $old['CLIETELE']; }
        if ($colMail) { $insertCols[] = $colMail; $insertVals[] = $old['CLIEMAIL']; }

        $colSql = '`' . implode('`,`', $insertCols) . '`';
        $phSql  = implode(',', array_fill(0, count($insertVals), '?'));
        $sql = 'INSERT INTO clientes (' . $colSql . ') VALUES (' . $phSql . ')';
        $st = $pdo->prepare($sql);
        $st->execute($insertVals);
        go_redirect('Cliente registrado.');

      } elseif ($accion === 'actualizar') {
        $set = array('`'.$colNom.'` = ?');
        $vals = array($old['CLIENOMB']);

        if ($colDir)  { $set[] = '`'.$colDir.'` = ?';  $vals[] = $old['CLIEDIRE']; }
        if ($colTel)  { $set[] = '`'.$colTel.'` = ?';  $vals[] = $old['CLIETELE']; }
        if ($colMail) { $set[] = '`'.$colMail.'` = ?'; $vals[] = $old['CLIEMAIL']; }

        $vals[] = $old['CLIEDOCU'];
        $sql = 'UPDATE clientes SET ' . implode(', ', $set) . ' WHERE `'.$colDoc.'` = ?';
        $st = $pdo->prepare($sql);
        $st->execute($vals);
        go_redirect('Cliente actualizado.');

      } elseif ($accion === 'eliminar') {
        $doc = isset($_POST['CLIEDOCU']) ? trim($_POST['CLIEDOCU']) : '';
        $st = $pdo->prepare('DELETE FROM clientes WHERE `'.$colDoc.'` = ?');
        $st->execute(array($doc));
        go_redirect('Cliente eliminado.');
      }
    } catch (PDOException $e) {
      $msg = ($e->getCode()==='23000') ? 'Registro duplicado o relacionado.' : ('Error: '.$e->getMessage());
      go_redirect('', $msg);
    }
  }
}

if ($schema_ok) {
  $orderCol = $colNom ? $colNom : $colDoc;
  $sql = 'SELECT `' . $colDoc . '` AS CLIEDOCU, `' . $colNom . '` AS CLIENOMB';
  $sql .= $colDir  ? ', `' . $colDir . '` AS CLIEDIRE'  : ", '' AS CLIEDIRE";
  $sql .= $colTel  ? ', `' . $colTel . '` AS CLIETELE'  : ", '' AS CLIETELE";
  $sql .= $colMail ? ', `' . $colMail . '` AS CLIEMAIL' : ", '' AS CLIEMAIL";
  $sql .= ' FROM clientes ORDER BY `' . $orderCol . '`';

  $st = $pdo->query($sql);
  $clientes = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestion de Clientes</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  html,body{margin:0 !important;padding:0 !important;} 
  header,.topbar{margin-top:0 !important;}
  :root{
    --azul:#1f6691;
    --azul-2:#2f83b5;
    --azul-3:#154c6d;
    --bg:#eef3f8;
    --txt:#153549;
    --line:#d9e5ef;
    --danger:#d9534f;
    --danger-2:#b63733;
  }
  body{
    margin:0;
    min-height:100vh;
    background: linear-gradient(180deg, #f3f7fb 0%, var(--bg) 100%);
    font-family: "Segoe UI", Tahoma, Arial, sans-serif;
    color:var(--txt);
  }

  .topbar{
    position: sticky;
    top:0;
    z-index:1000;
    background: linear-gradient(135deg, var(--azul), var(--azul-2));
    box-shadow:0 8px 18px rgba(13,53,79,.18);
  }
  .topbar-inner{
    max-width:1320px;
    margin:0 auto;
    padding:10px 14px;
    display:grid;
    grid-template-columns:auto 1fr auto;
    align-items:center;
    gap:10px;
  }
  .nav-link-ghost{
    display:inline-flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
    color:#fff;
    border:1px solid rgba(255,255,255,.40);
    border-radius:10px;
    padding:8px 12px;
    font-weight:800;
    background:rgba(255,255,255,.08);
    transition:.2s ease;
  }
  .nav-link-ghost:hover{
    color:#fff;
    background:rgba(255,255,255,.16);
    border-color:rgba(255,255,255,.55);
  }
  .topbar h1{
    margin:0;
    color:#fff;
    text-align:center;
    font-size:1.65rem;
    font-weight:900;
  }
  .user-pill{
    justify-self:end;
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:#fff;
    padding:7px 10px;
    border-radius:12px;
    position:relative;
    cursor:pointer;
  }
  .user-pill:hover{ background:rgba(255,255,255,.10); }
  .user-menu{
    position:absolute;
    right:0;
    top:calc(100% + 8px);
    min-width:185px;
    display:none;
    background:#fff;
    border:1px solid var(--line);
    border-radius:12px;
    padding:6px;
    box-shadow:0 14px 26px rgba(0,0,0,.16);
    z-index:1200;
  }
  .user-item{
    display:flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
    padding:10px 12px;
    border-radius:10px;
    color:var(--danger);
    font-weight:800;
  }
  .user-item:hover{ background:#ffe6e5; color:var(--danger-2); }

  .main-wrap{ max-width:1220px; margin:28px auto; padding:0 14px; }

  .card-soft{
    border-radius:16px;
    border:1px solid #e3ecf4;
    box-shadow:0 12px 28px rgba(17,58,84,.10);
    background:#fff;
  }
  .section-title{
    font-weight:900;
    margin-bottom:14px;
    color:#1a4762;
  }
  .form-label{ font-weight:700; color:#244f67; }
  .form-control{
    border-radius:10px;
    border:1px solid #cfdfea;
    min-height:42px;
  }
  .btn-main{
    background:linear-gradient(135deg,var(--azul),var(--azul-2));
    border:none;
    color:#fff;
    border-radius:10px;
    font-weight:800;
    min-height:42px;
  }
  .btn-main:hover{ color:#fff; filter:brightness(1.06); }
  .btn-cancel{
    border-radius:10px;
    font-weight:700;
  }

  .table-wrap{
    border:1px solid var(--line);
    border-radius:14px;
    overflow:hidden;
    background:#fff;
  }
  .table thead th{
    background:#f3f8fc;
    color:#204760;
    font-weight:800;
    border-bottom:1px solid var(--line);
  }
  .btn-edit{
    background:#f0a500;
    color:#fff;
    border:none;
    font-weight:700;
  }
  .btn-edit:hover{ background:#d18f00; color:#fff; }

  .alert{ border-radius:12px; }
  footer{
    margin-top:22px;
    text-align:center;
    color:#3f647c;
    padding:16px 10px 20px;
    font-weight:600;
  }

  @media (max-width: 900px){
    .topbar h1{ font-size:1.25rem; }
  }
  @media (max-width: 768px){
    .topbar-inner{ grid-template-columns:1fr; gap:8px; }
    .user-pill,.nav-link-ghost{ justify-self:center; }
    .topbar h1{ font-size:1.12rem; }
  }
</style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="mantenimiento_tablas_D.php" class="nav-link-ghost"><i class="bi bi-arrow-left-circle"></i> Volver</a>
    <h1>Gestion de Clientes</h1>
    <div class="user-pill" id="userBtn">
      <i class="bi bi-person-circle"></i> <?php echo h($usuario); ?>
      <div class="user-menu" id="userMenu">
        <a href="logout.php" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesion</a>
      </div>
    </div>
  </div>
</header>

<div class="main-wrap">
  <?php if ($ok || $err): ?>
    <div class="alert <?php echo $ok ? 'alert-success' : 'alert-danger'; ?> mb-3"><?php echo h($ok ? $ok : $err); ?></div>
  <?php endif; ?>

  <?php if ($mensaje_error_global): ?>
    <div class="alert alert-danger mb-3"><?php echo h($mensaje_error_global); ?></div>
  <?php endif; ?>

  <?php if ($schema_ok): ?>
  <div class="card-soft p-3 p-md-4 mb-4">
    <h4 class="section-title"><?php echo ($accionForm==='actualizar') ? 'Editar Cliente' : 'Registrar Cliente'; ?></h4>
    <form method="POST" class="row g-3" novalidate>
      <input type="hidden" name="accion" value="<?php echo h($accionForm); ?>">

      <div class="col-md-4">
        <label class="form-label">Documento</label>
        <input type="text" name="CLIEDOCU" class="form-control<?php echo isset($errores['CLIEDOCU']) ? ' is-invalid' : ''; ?>" value="<?php echo h($old['CLIEDOCU']); ?>" <?php echo ($accionForm==='actualizar') ? 'readonly' : ''; ?> required>
        <?php if (isset($errores['CLIEDOCU'])): ?><div class="invalid-feedback"><?php echo h($errores['CLIEDOCU']); ?></div><?php endif; ?>
      </div>

      <div class="col-md-4">
        <label class="form-label">Nombre</label>
        <input type="text" name="CLIENOMB" class="form-control<?php echo isset($errores['CLIENOMB']) ? ' is-invalid' : ''; ?>" value="<?php echo h($old['CLIENOMB']); ?>" required>
        <?php if (isset($errores['CLIENOMB'])): ?><div class="invalid-feedback"><?php echo h($errores['CLIENOMB']); ?></div><?php endif; ?>
      </div>

      <div class="col-md-4">
        <label class="form-label">Direccion</label>
        <input type="text" name="CLIEDIRE" class="form-control" value="<?php echo h($old['CLIEDIRE']); ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Telefono</label>
        <input type="text" name="CLIETELE" class="form-control<?php echo isset($errores['CLIETELE']) ? ' is-invalid' : ''; ?>" value="<?php echo h($old['CLIETELE']); ?>">
        <?php if (isset($errores['CLIETELE'])): ?><div class="invalid-feedback"><?php echo h($errores['CLIETELE']); ?></div><?php endif; ?>
      </div>

      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="CLIEMAIL" class="form-control<?php echo isset($errores['CLIEMAIL']) ? ' is-invalid' : ''; ?>" value="<?php echo h($old['CLIEMAIL']); ?>">
        <?php if (isset($errores['CLIEMAIL'])): ?><div class="invalid-feedback"><?php echo h($errores['CLIEMAIL']); ?></div><?php endif; ?>
      </div>

      <div class="col-md-4 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-main"><i class="bi bi-save me-1"></i> Guardar</button>
        <?php if ($accionForm==='actualizar'): ?><a href="clientes_D.php" class="btn btn-secondary btn-cancel">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card-soft p-3 p-md-4">
    <h4 class="section-title">Lista de Clientes</h4>
    <div class="table-wrap">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Documento</th>
              <th>Nombre</th>
              <th>Direccion</th>
              <th>Telefono</th>
              <th>Email</th>
              <th style="width:190px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($clientes)): foreach($clientes as $c): ?>
            <tr>
              <td><?php echo h($c['CLIEDOCU']); ?></td>
              <td><?php echo h($c['CLIENOMB']); ?></td>
              <td><?php echo h($c['CLIEDIRE']); ?></td>
              <td><?php echo h($c['CLIETELE']); ?></td>
              <td><?php echo h($c['CLIEMAIL']); ?></td>
              <td>
                <a href="clientes_D.php?editar=<?php echo urlencode($c['CLIEDOCU']); ?>" class="btn btn-edit btn-sm"><i class="bi bi-pencil-square"></i> Editar</a>
                <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar cliente?');">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="CLIEDOCU" value="<?php echo h($c['CLIEDOCU']); ?>">
                  <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Sin registros.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<footer><small>&copy; <?php echo date('Y'); ?> - Sistema de Facturacion</small></footer>

<script>
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if (!btn || !menu) return;

  btn.addEventListener('click', function(e){
    e.preventDefault();
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
  });

  document.addEventListener('click', function(e){
    if (!btn.contains(e.target)) menu.style.display = 'none';
  });
})();
</script>

</body>
</html>


