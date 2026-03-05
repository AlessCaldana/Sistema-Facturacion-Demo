<?php
/* control_pagos.php - Marcar PAGADA */
session_start();
require_once __DIR__ . '/conexion.php';
if (file_exists(__DIR__ . '/guard.php')) require_once __DIR__ . '/guard.php';
if (function_exists('require_perm')) { require_perm('control_pagos'); }

$usuario_actual = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';

/* ===== Helpers ===== */
function fmt_dmyhi($iso) {
  if (!$iso || $iso==='0000-00-00' || $iso==='0000-00-00 00:00:00') return '-';
  $t = @strtotime($iso);
  return $t ? date('d/m/Y H:i', $t) : '-';
}
function es_pagada($estado){
  return (strtoupper(trim((string)$estado)) === 'PAGADA');
}
function factura_pdf_path($id) {
  $rel = "facturas_pdf/factura_{$id}.pdf";
  return file_exists(__DIR__ . '/' . $rel) ? $rel : null;
}

/* ===== Obtener factura con columnas reales ===== */
function obtenerFactura($pdo, $id) {
  // Detectar columnas reales de clientes
  $colsC = array();
  try {
    $q = $pdo->query("SHOW COLUMNS FROM `clientes`");
    while($r=$q->fetch(PDO::FETCH_ASSOC)){ $colsC[]=$r['Field']; }
  } catch(Exception $e) { }

  $col_cli_id  = in_array('id',$colsC)         ? 'id'        : (in_array('CLIEDOCU',$colsC) ? 'CLIEDOCU' : 'id');
  $col_cli_nom = in_array('nombre',$colsC)      ? 'nombre'    : (in_array('CLIENOMB',$colsC) ? 'CLIENOMB' : 'nombre');
  $col_cli_dir = in_array('direccion',$colsC)   ? 'direccion' : (in_array('CLIEDIRE',$colsC) ? 'CLIEDIRE' : 'direccion');

  // Detectar columnas reales de facturas
  $colsF = array();
  try {
    $q2 = $pdo->query("SHOW COLUMNS FROM `facturas`");
    while($r=$q2->fetch(PDO::FETCH_ASSOC)){ $colsF[]=$r['Field']; }
  } catch(Exception $e) { }

  $tiene_placa    = in_array('placa', $colsF);
  $tiene_ref      = in_array('referencia', $colsF);
  $tiene_usunomb  = in_array('usuario_nombre', $colsF);
  $col_fecha      = in_array('fecha', $colsF)  ? 'fecha'  : (in_array('FACTFECH',$colsF) ? 'FACTFECH' : 'fecha');
  $col_total      = in_array('total', $colsF)  ? 'total'  : (in_array('FACTVALO',$colsF) ? 'FACTVALO' : 'total');
  $col_estado     = in_array('estado', $colsF) ? 'estado' : (in_array('FACTESTA',$colsF) ? 'FACTESTA' : 'estado');
  $col_fpag       = in_array('fecha_pago', $colsF) ? 'fecha_pago' : (in_array('FACTFPAG',$colsF) ? 'FACTFPAG' : null);
  $col_cli_fk     = in_array('cliente_id', $colsF) ? 'cliente_id' : (in_array('FACTCLIE',$colsF) ? 'FACTCLIE' : 'cliente_id');

  $sel_placa  = $tiene_placa   ? 'f.placa'          : "'' AS placa";
  $sel_ref    = $tiene_ref     ? 'f.referencia'      : "'' AS referencia";
  $sel_usua   = $tiene_usunomb ? 'f.usuario_nombre'  : "'' AS usuario_nombre";
  $sel_fpag   = $col_fpag      ? "f.`{$col_fpag}`"  : "NULL";

  // Detectar columnas de ventas
  $colsV = array();
  try {
    $q3 = $pdo->query("SHOW COLUMNS FROM `ventas`");
    while($r=$q3->fetch(PDO::FETCH_ASSOC)){ $colsV[]=$r['Field']; }
  } catch(Exception $e) { }
  $col_vcant = in_array('cantidad',$colsV)   ? 'cantidad'   : (in_array('VENTCANT',$colsV) ? 'VENTCANT' : 'cantidad');
  $col_vsubt = in_array('subtotal',$colsV)   ? 'subtotal'   : (in_array('VENTTOTA',$colsV) ? 'VENTTOTA' : 'subtotal');
  $col_vprod = in_array('producto_id',$colsV)? 'producto_id': (in_array('VENTPROD',$colsV) ? 'VENTPROD' : 'producto_id');
  $col_vfact = in_array('factura_id',$colsV) ? 'factura_id' : (in_array('VENTRECI',$colsV) ? 'VENTRECI' : 'factura_id');

  $sql = "
    SELECT
      f.id                        AS factura_id,
      f.`{$col_fecha}`            AS fecha,
      f.`{$col_total}`            AS total,
      f.`{$col_estado}`           AS estado,
      {$sel_fpag}                 AS fecha_pago,
      {$sel_placa},
      {$sel_ref},
      {$sel_usua},
      c.`{$col_cli_nom}`          AS cliente_nombre,
      IFNULL(c.`{$col_cli_dir}`,'') AS cliente_direccion,
      f.`{$col_cli_fk}`           AS cliente_id,
      v.`{$col_vcant}`            AS cantidad,
      p.PRODDESC                  AS producto
    FROM facturas f
    LEFT JOIN clientes  c ON c.`{$col_cli_id}` = f.`{$col_cli_fk}`
    LEFT JOIN ventas    v ON v.`{$col_vfact}`  = f.id
    LEFT JOIN productos p ON p.PRODCODI        = v.`{$col_vprod}`
    WHERE f.id = ?
    LIMIT 1
  ";

  try {
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC);
  } catch(Exception $e) {
    return null;
  }
}

/* ===== Estado de pagina ===== */
$mensaje     = '';
$msg_tipo    = '';
$factura     = null;

/* GET - Mensaje retorno */
if (isset($_GET['ok']) && isset($_GET['factura']) && ctype_digit($_GET['factura'])) {
  $factura  = obtenerFactura($pdo, (int)$_GET['factura']);
  if ($factura) { $mensaje = "Pago registrado correctamente."; $msg_tipo = 'success'; }
}

/* POST - Buscar */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['factura']) && !isset($_POST['marcar_pagada'])) {
  $n = trim($_POST['factura']);
  if ($n !== '' && ctype_digit($n)) {
    $factura = obtenerFactura($pdo, (int)$n);
    if ($factura) {
      $msg_tipo = es_pagada($factura['estado']) ? 'info' : 'success';
      $mensaje  = es_pagada($factura['estado']) ? "Factura encontrada: ya esta pagada." : "Factura encontrada, pendiente de pago.";
    } else {
      $mensaje = "No existe esa factura."; $msg_tipo = 'warning';
    }
  } else { $mensaje = "Numero no valido."; $msg_tipo = 'warning'; }
}

/* POST - Marcar Pagada */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['marcar_pagada']) && isset($_POST['factura_id']) && ctype_digit($_POST['factura_id'])) {
  $id = (int)$_POST['factura_id'];

  // Detectar columna estado y fecha_pago en facturas
  $colsF2 = array();
  try {
    $qf = $pdo->query("SHOW COLUMNS FROM `facturas`");
    while($r=$qf->fetch(PDO::FETCH_ASSOC)){ $colsF2[]=$r['Field']; }
  } catch(Exception $e) { }
  $col_est2  = in_array('estado',$colsF2)     ? 'estado'     : 'FACTESTA';
  $col_fpag2 = in_array('fecha_pago',$colsF2) ? 'fecha_pago' : (in_array('FACTFPAG',$colsF2) ? 'FACTFPAG' : null);

  try {
    $check = $pdo->prepare("SELECT id FROM facturas WHERE id=?");
    $check->execute([$id]);
    $exists = $check->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
      $pdo->beginTransaction();
      try {
        if ($col_fpag2) {
          $u = $pdo->prepare("UPDATE facturas SET `{$col_est2}`='PAGADA', `{$col_fpag2}`=NOW() WHERE id=?");
        } else {
          $u = $pdo->prepare("UPDATE facturas SET `{$col_est2}`='PAGADA' WHERE id=?");
        }
        $u->execute([$id]);
        $pdo->commit();
        header("Location: control_pagos.php?ok=1&factura=".$id);
        exit;
      } catch(Exception $e){
        $pdo->rollBack();
        $mensaje = "Error: ".$e->getMessage(); $msg_tipo = 'danger';
      }
    } else { $mensaje = "La factura no existe."; $msg_tipo = 'warning'; }
  } catch(Exception $e) {
    $mensaje = "Error: ".$e->getMessage(); $msg_tipo = 'danger';
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Control de Pagos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  html,body{margin:0 !important;padding:0 !important;} 
  header,.topbar{margin-top:0 !important;}

  :root{
    --azul:#1e5f99;
    --azul-hover:#154a73;
    --borde-input:#dce6f3;
    --texto:#333;
    --bg:#fff;
    --danger:#dc3545;
    --danger-2:#b52b27;
    --txt:#0b3850;
  }

  body{
    margin:0;
    font-family:"Segoe UI",Arial,sans-serif;
    background:#f5f7fa;
    color:var(--texto);
    display:flex;
    flex-direction:column;
    min-height:100vh;
  }

  header{
    background:var(--azul-hover);
    color:#fff;
    padding:14px 22px;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
  }

  header h2{
    margin:0;
    font-size:1.2rem;
    font-weight:600;
  }

  .header-left{
    position:absolute;
    left:16px;
  }

  .header-right{
    position:absolute;
    right:16px;
    top:50%;
    transform:translateY(-50%);
  }

  .btn-sec{
    display:inline-flex;
    align-items:center;
    gap:6px;
    text-decoration:none;
    color:#fff;
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.3);
    padding:8px 14px;
    border-radius:8px;
    font-weight:600;
    font-size:.9rem;
    transition:.2s ease;
  }

  .btn-sec:hover{
    background:rgba(255,255,255,.25);
    border-color:rgba(255,255,255,.5);
  }

  .user-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:9999px;
    background:#0f5c87;
    color:#fff;
    font-weight:600;
    cursor:pointer;
    user-select:none;
    font-size:.9rem;
  }

  .user-menu{
    display:none;
    position:absolute;
    right:16px;
    top:54px;
    min-width:220px;
    background:#fff;
    color:var(--txt);
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:6px;
    box-shadow:0 12px 28px rgba(0,0,0,.2);
    z-index:9999;
  }

  .user-item{
    display:flex;
    align-items:center;
    gap:8px;
    padding:10px 12px;
    border-radius:8px;
    font-weight:600;
    text-decoration:none;
    color:var(--danger);
    font-size:.9rem;
  }

  .user-item:hover{
    background:#ffe5e5;
    color:var(--danger-2);
  }

  main{
    flex:1;
  }

  footer{
    background:var(--azul-hover);
    color:#fff;
    text-align:center;
    padding:12px;
    font-size:.9rem;
  }

  .card{
    border:1px solid #e9f1f8;
    border-radius:14px;
    padding:24px;
    max-width:1000px;
    margin:30px auto;
    box-shadow:0 8px 16px rgba(0,0,0,0.08);
    background:#fff;
  }

  .card h3{
    text-align:center;
    color:var(--azul-hover);
    margin:0 0 20px;
    font-weight:700;
    font-size:1.1rem;
  }

  .search-form{
    display:flex;
    gap:10px;
    align-items:flex-end;
    flex-wrap:wrap;
    justify-content:center;
    margin-bottom:20px;
  }

  .form-group{
    display:flex;
    flex-direction:column;
    gap:6px;
  }

  .form-group label{
    font-weight:600;
    font-size:.93rem;
    color:var(--txt);
  }

  input[type="text"]{
    padding:11px 14px;
    border-radius:10px;
    border:2px solid var(--borde-input) !important;
    background:#fff;
    font-size:1rem;
    font-family:inherit;
    min-width:200px;
  }

  input[type="text"]:focus{
    border-color:var(--azul) !important;
    box-shadow:0 0 6px rgba(41,128,185,.25) !important;
    outline:none;
  }

  button{
    background:var(--azul);
    color:#fff;
    border:none;
    padding:12px 24px;
    border-radius:6px;
    cursor:pointer;
    font-weight:600;
    font-size:.95rem;
    display:inline-flex;
    align-items:center;
    gap:6px;
    transition:.2s ease;
    min-width:120px;
    justify-content:center;
  }

  button:hover{
    background:var(--azul-hover);
  }

  button:disabled{
    opacity:.6;
    cursor:not-allowed;
  }

  .alert{
    padding:14px 16px;
    border-radius:10px;
    margin-bottom:20px;
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:500;
  }

  .alert-error{
    background:#ffe5e5;
    color:#b30000;
    border-left:4px solid #dc3545;
  }

  .alert-ok{
    background:#e8f8ff;
    color:#0b4a6a;
    border-left:4px solid #2980b9;
  }

  .alert-info{
    background:#fef9e7;
    color:#744210;
    border-left:4px solid #facc15;
  }

  .resultado{
    border:1px solid #e9f1f8;
    border-radius:12px;
    margin-top:20px;
    background:#fff;
    overflow:hidden;
  }

  .resultado__header{
    background:linear-gradient(135deg, var(--azul), #6dd5fa);
    color:#08324a;
    padding:14px 16px;
    font-weight:700;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:10px;
  }

  .grid{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap:16px;
    padding:20px;
  }

  .grid-item{
    display:flex;
    flex-direction:column;
    gap:4px;
    padding:12px;
    background:#f9f9f9;
    border-radius:8px;
    border:1px solid #e9f1f8;
  }

  .grid-item strong{
    color:#0b4a6a;
    font-weight:700;
    font-size:.9rem;
  }

  .grid-item span{
    color:var(--texto);
    font-size:.95rem;
  }

  .btn-group{
    padding:16px 20px;
    border-top:1px solid #e9f1f8;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  .btn-primary{
    background:var(--azul);
    color:#fff;
  }

  .btn-primary:hover{
    background:var(--azul-hover);
  }

  .btn-success{
    background:#10b981;
    color:#fff;
  }

  .btn-success:hover{
    background:#059669;
  }

  .btn-outline{
    background:#f2f6fb;
    border:1px solid #cfe1f5;
    color:#0b4a6a;
    padding:12px 24px;
    border-radius:6px;
    min-width:120px;
    justify-content:center;
  }

  .btn-outline:hover{
    background:#eaf3ff;
  }

  .badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 12px;
    border-radius:999px;
    font-weight:700;
    font-size:.85rem;
  }

  .badge-success{
    background:#d1fae5;
    color:#065f46;
    border:1px solid #6ee7b7;
  }

  .badge-warning{
    background:#fef9c3;
    color:#744210;
    border:1px solid #fde047;
  }

  .badge-info{
    background:#cffafe;
    color:#164e63;
    border:1px solid #67e8f9;
  }

  .modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.5);
    align-items:center;
    justify-content:center;
    z-index:10000;
  }

  .modal.show{
    display:flex;
  }

  .modal-content{
    width:95%;
    max-width:420px;
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 20px 60px rgba(0,0,0,.25);
  }

  .modal-header{
    padding:14px 16px;
    background:linear-gradient(135deg,#ffefef,#ffe5e5);
    border-bottom:1px solid #f3c7c7;
    display:flex;
    align-items:center;
    justify-content:space-between;
    font-weight:700;
    color:#b30000;
  }

  .modal-body{
    padding:16px;
    color:var(--texto);
  }

  .modal-footer{
    padding:12px 16px;
    background:#fafbfd;
    border-top:1px solid #eef1f5;
    text-align:right;
    display:flex;
    gap:8px;
    justify-content:flex-end;
  }

  @media(max-width:768px){
    header h2{
      font-size:1rem;
    }
    .grid{
      grid-template-columns:1fr;
    }
    .search-form{
      flex-direction:column;
    }
    .form-group{
      width:100%;
    }
    input[type="text"]{
      width:100%;
      min-width:unset;
    }
  }
</style>
</head>
<body>

<header>
  <div class="header-left">
    <a href="menu_principal.php" class="btn-sec">
      <i class="bi bi-arrow-left-circle"></i> Menu
    </a>
  </div>
  <h2>Control de Pagos</h2>
  <div class="header-right">
    <div class="user-pill" id="userBtn" title="Click para opciones">
      <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_actual); ?>
    </div>
    <div class="user-menu" id="userMenu" style="position:absolute;right:0;top:100%;">
      <a href="#" id="openLogout" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesion</a>
    </div>
  </div>
</header>

<main>
  <div class="card">
    <h3><i class="bi bi-search me-2"></i>Buscar Factura</h3>

    <form method="POST" class="search-form">
      <div class="form-group" style="width:100%;max-width:300px;">
        <label>Numero de Factura</label>
        <input type="text" name="factura" inputmode="numeric" pattern="[0-9]*" 
               placeholder="Ej: 1, 2, 3..." required
               value="<?php echo isset($_POST['factura']) ? htmlspecialchars($_POST['factura']) : ''; ?>" />
      </div>
      <button type="submit" class="btn-primary">
        <i class="bi bi-search"></i> Buscar
      </button>
      <a href="control_pagos.php" class="btn-outline">
        <i class="bi bi-x-circle"></i> Limpiar
      </a>
    </form>

    <?php if ($mensaje): ?>
      <div class="alert alert-<?php echo $msg_tipo === 'success' ? 'ok' : ($msg_tipo === 'warning' ? 'info' : 'error'); ?>">
        <?php if ($msg_tipo==='success'): ?><i class="bi bi-check-circle-fill"></i>
        <?php elseif($msg_tipo==='warning'): ?><i class="bi bi-exclamation-triangle-fill"></i>
        <?php elseif($msg_tipo==='danger'): ?><i class="bi bi-x-circle-fill"></i>
        <?php else: ?><i class="bi bi-info-circle-fill"></i><?php endif; ?>
        <span><?php echo htmlspecialchars($mensaje); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($factura): ?>
      <?php
        $isPagada = es_pagada($factura['estado']);
        $rutaPdf  = factura_pdf_path((int)$factura['factura_id']);
        $fFecha   = fmt_dmyhi($factura['fecha']);
        $fPago    = fmt_dmyhi($factura['fecha_pago']);
      ?>
      <div class="resultado">
        <div class="resultado__header">
          <div style="font-size:1.2rem; font-weight:700;">
            <i class="bi bi-file-earmark-text"></i> Factura #<?php echo htmlspecialchars($factura['factura_id']); ?>
          </div>
          <div style="display:flex;gap:10px;align-items:center;">
            <?php if ($rutaPdf): ?>
              <a href="<?php echo htmlspecialchars($rutaPdf); ?>" target="_blank" class="btn-sec" style="background:rgba(0,0,0,.2);padding:6px 12px;font-size:.85rem;">
                <i class="bi bi-file-earmark-pdf"></i> PDF
              </a>
            <?php endif; ?>
            <?php if ($isPagada): ?>
              <span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Pagada</span>
            <?php else: ?>
              <span class="badge badge-warning"><i class="bi bi-clock-fill"></i> Pendiente</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="grid">
          <div class="grid-item">
            <strong>Cliente:</strong>
            <span><?php echo htmlspecialchars($factura['cliente_nombre'] ?: '-'); ?></span>
          </div>
          <div class="grid-item">
            <strong>Documento:</strong>
            <span><?php echo htmlspecialchars($factura['cliente_id'] ?: '-'); ?></span>
          </div>
          <div class="grid-item">
            <strong>Producto:</strong>
            <span><?php echo htmlspecialchars($factura['producto'] ?: '-'); ?></span>
          </div>
          <div class="grid-item">
            <strong>Cantidad:</strong>
            <span><?php echo htmlspecialchars($factura['cantidad'] ?: '-'); ?></span>
          </div>
          <div class="grid-item">
            <strong>Total:</strong>
            <span style="font-weight:700;color:var(--azul);font-size:1.05rem;">
              $<?php echo number_format((float)$factura['total'], 0, ',', '.'); ?>
            </span>
          </div>
          <div class="grid-item">
            <strong>Fecha Emision:</strong>
            <span><?php echo htmlspecialchars($fFecha); ?></span>
          </div>
          <?php if ($factura['placa']): ?>
          <div class="grid-item">
            <strong>Placa Vehiculo:</strong>
            <span><?php echo htmlspecialchars($factura['placa']); ?></span>
          </div>
          <?php endif; ?>
          <?php if ($isPagada && $fPago): ?>
          <div class="grid-item">
            <strong>Fecha de Pago:</strong>
            <span><?php echo htmlspecialchars($fPago); ?></span>
          </div>
          <?php endif; ?>
        </div>

        <div class="btn-group">
          <?php if (!$isPagada): ?>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="factura_id" value="<?php echo htmlspecialchars($factura['factura_id']); ?>">
              <button name="marcar_pagada" id="btnPagar" class="btn-success">
                <i class="bi bi-cash-coin"></i> Marcar como Pagada
              </button>
            </form>
          <?php endif; ?>
          <a href="control_pagos.php" class="btn-outline">
            <i class="bi bi-search"></i> Nueva busqueda
          </a>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main>
</main>

<footer>&copy; <?php echo date('Y'); ?> - Sistema de Facturacion</footer>

<!-- Modal Logout -->
<div id="logoutModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <div style="display:flex;align-items:center;gap:8px;">
        <i class="bi bi-exclamation-triangle-fill"></i> Confirmar cierre de sesion
      </div>
      <button type="button" id="closeLogoutBtn" style="background:transparent;border:none;font-size:20px;cursor:pointer;color:inherit;">
        &times;
      </button>
    </div>
    <div class="modal-body">
      Seguro que deseas cerrar tu sesion?
    </div>
    <div class="modal-footer">
      <button type="button" id="cancelLogoutBtn" class="btn-outline" style="text-decoration:none;padding:10px 16px;">
        Cancelar
      </button>
      <a href="logout.php" class="btn-primary" style="text-decoration:none;padding:10px 16px;">
        <i class="bi bi-box-arrow-right"></i> Si, salir
      </a>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){

  // Dropdown usuario
  var userBtn = document.getElementById('userBtn');
  var userMenu = document.getElementById('userMenu');
  
  if (userBtn && userMenu) {
    userBtn.addEventListener('click', function(e){
      e.stopPropagation();
      userMenu.style.display = userMenu.style.display === 'block' ? 'none' : 'block';
    });
    
    document.addEventListener('click', function(e){
      if (!userBtn.contains(e.target) && !userMenu.contains(e.target)) {
        userMenu.style.display = 'none';
      }
    });
  }

  // Modal Logout
  var openLogout = document.getElementById('openLogout');
  var logoutModal = document.getElementById('logoutModal');
  var closeLogoutBtn = document.getElementById('closeLogoutBtn');
  var cancelLogoutBtn = document.getElementById('cancelLogoutBtn');

  if (openLogout && logoutModal) {
    openLogout.addEventListener('click', function(e){
      e.preventDefault();
      logoutModal.classList.add('show');
    });
  }

  function closeModal() {
    logoutModal.classList.remove('show');
  }

  if (closeLogoutBtn) {
    closeLogoutBtn.addEventListener('click', closeModal);
  }

  if (cancelLogoutBtn) {
    cancelLogoutBtn.addEventListener('click', closeModal);
  }

  if (logoutModal) {
    logoutModal.addEventListener('click', function(e){
      if (e.target === logoutModal) {
        closeModal();
      }
    });
  }

  // Evitar doble click en "Marcar Pagada"
  var btnPagar = document.getElementById('btnPagar');
  if (btnPagar) {
    btnPagar.closest('form').addEventListener('submit', function(){
      btnPagar.disabled = true;
      btnPagar.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';
    });
  }
});
</script>
</body>
</html>