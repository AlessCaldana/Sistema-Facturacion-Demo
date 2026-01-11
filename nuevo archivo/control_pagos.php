<?php
/* control_pagos.php — Marcar PAGADA (sin tocar entrega)
   Compatible PHP 5.3
*/
session_start();
require_once __DIR__ . '/conexion.php';
if (file_exists(__DIR__ . '/guard.php')) require_once __DIR__ . '/guard.php';
if (function_exists('require_perm')) { require_perm('control_pagos'); }

$usuario_actual = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';

/* ===== Helpers ===== */
function fmt_dmyhi($iso) {
  if (!$iso || $iso==='0000-00-00' || $iso==='0000-00-00 00:00:00') return '—';
  $t = @strtotime($iso);
  return $t ? date('d/m/Y H:i', $t) : '—';
}
function es_pagada($estado){
  return (strtoupper(trim((string)$estado)) === 'PAGADA');
}
function obtenerFactura($pdo, $id) {
  $sql = "SELECT 
            v.VENTRECI, v.VENTCLIE, v.VENTTOTA, v.VENTFEVE, v.VENTFEEN,
            f.FACTESTA, f.FACTFPAG, f.FACTENTR,
            c.CLIENOMB
          FROM ventas v
          LEFT JOIN facturas f ON f.FACTRECI = v.VENTRECI
          LEFT JOIN clientes  c ON c.CLIEDOCU = v.VENTCLIE
          WHERE v.VENTRECI = ?";
  $st = $pdo->prepare($sql);
  $st->execute(array($id));
  return $st->fetch(PDO::FETCH_ASSOC);
}
function factura_pdf_path($id) {
  $rel = "facturas_pdf/factura_{$id}.pdf";
  return file_exists(__DIR__ . '/' . $rel) ? $rel : null;
}

/* ===== Estado de página ===== */
$mensaje = '';
$factura = null;

/* GET — Mensaje retorno */
if (isset($_GET['ok']) && isset($_GET['factura']) && ctype_digit($_GET['factura'])) {
  $factura = obtenerFactura($pdo, (int)$_GET['factura']);
  if ($factura) $mensaje = "✅ Pago registrado correctamente.";
}

/* POST — Buscar */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['factura']) && !isset($_POST['marcar_pagada'])) {
  $n = trim($_POST['factura']);
  if ($n !== '' && ctype_digit($n)) {
    $factura = obtenerFactura($pdo, (int)$n);
    $mensaje = $factura ? (es_pagada($factura['FACTESTA']) ? "ℹ️ Factura encontrada: ya está pagada." : "✅ Factura encontrada, pendiente de pago.") : "⚠️ No existe esa factura.";
  } else { $mensaje = "⚠️ Número no válido."; }
}

/* POST — Marcar Pagada */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['marcar_pagada']) && isset($_POST['factura_id']) && ctype_digit($_POST['factura_id'])) {
  $id = (int)$_POST['factura_id'];

  $fact = $pdo->prepare("SELECT FACTRECI FROM facturas WHERE FACTRECI=?");
  $fact->execute(array($id));
  if ($fact->fetch(PDO::FETCH_ASSOC)) {
    $pdo->beginTransaction();
    try {
      $u = $pdo->prepare("UPDATE facturas SET FACTESTA='PAGADA', FACTFPAG=NOW() WHERE FACTRECI=?");
      $u->execute(array($id));
      $pdo->commit();
      header("Location: control_pagos.php?ok=1&factura=".$id);
      exit;
    } catch(Exception $e){
      $pdo->rollBack();
      $mensaje="❌ Error: ".$e->getMessage();
    }
  } else { $mensaje="⚠️ La factura no existe."; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Control de Pagos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{
  --azul:#2980b9; --azul-osc:#1f6691; --celeste:#6dd5fa;
  --verde-osc:#146c43; --verde-borde:#0f5132;
  --amarillo:#ffc107; --rojo:#dc3545; --texto:#0b4a6a;
  --txt:#0b3850; --danger:#dc3545; --danger-2:#b52b27;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;font-family:"Segoe UI",Arial,sans-serif;background:#fff;color:#333;
  min-height:100vh;display:flex;flex-direction:column;
}
header{
  background:var(--azul-osc);
  color:#fff;
  padding:22px 20px;
  display:flex;
  align-items:center;
  justify-content:center;
  position:relative;
  box-shadow:0 2px 8px rgba(0,0,0,.15);
}
header h2{
  margin:0;font-size:1.7rem;font-weight:700;
}
header a.back{
  position:absolute;left:20px;background:#fff;color:var(--azul-osc);
  padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600;transition:.3s;
}
header a.back:hover{ background:#e5f4ff; }

/* ===== Usuario / Dropdown ===== */
.user-pill{
  position:absolute; right:20px; top:50%; transform:translateY(-50%);
  display:inline-flex; align-items:center; gap:8px;
  padding:8px 12px; border-radius:9999px; background:#1f6691; color:#fff; font-weight:700;
  cursor:pointer; user-select:none;
}
.user-menu{
  display:none; position:absolute; right:20px; top:64px; min-width:220px;
  background:#fff; color:var(--txt); border:1px solid #e5e7eb; border-radius:12px; padding:6px;
  box-shadow:0 12px 28px rgba(0,0,0,.2); z-index:9999;
}
.user-item{
  display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:8px;
  font-weight:600; text-decoration:none; color:var(--danger);
}
.user-item:hover{ background:#ffe5e5; color:var(--danger-2); }

.container{width:100%;max-width:1200px;margin:24px auto;padding:0 16px;flex:1}
.content{width:100%;max-width:980px;margin:0 auto}
.card{background:#fff;border:1px solid #e9f1f8;border-radius:14px;padding:18px;box-shadow:0 8px 18px rgba(0,0,0,.08)}
h4{margin:0 0 12px 0;color:var(--texto)}

.btn{
  border:none;border-radius:8px;padding:8px 16px;font-weight:700;cursor:pointer;
  display:inline-flex;align-items:center;gap:8px;line-height:1;transition:.2s;font-size:.95rem;
}
.btn-primary{background:var(--azul);color:#fff}
.btn-primary:hover{background:var(--celeste);color:#000}
.btn-danger{background:var(--rojo);color:#fff}
.btn-danger:hover{filter:brightness(0.95)}
.btn-pdf{background:var(--azul);color:#fff}
.btn-pdf:hover{background:var(--celeste);color:#000}

.alert{padding:10px;border-radius:8px;margin-bottom:12px;font-weight:600}
.alert-success{background:#e8ffef;color:#0b6b2f}
.alert-info{background:#e8f5ff;color:#0b4a6a}
.alert-warning{background:#fff7e6;color:#8a5c00}

/* Chip */
.chip-paid{
  background:var(--verde-osc); color:#fff; border:1px solid var(--verde-borde);
  padding:4px 10px; border-radius:999px; font-weight:600; font-size:.83rem;
  display:inline-flex; align-items:center; gap:6px; white-space:nowrap;
}
.chip-yellow{
  background:#fff3cd;color:#664d03;border:1px solid #ffecb5;
  padding:4px 10px;border-radius:999px;font-weight:600;font-size:.83rem;
  display:inline-flex;align-items:center;gap:6px
}

/* Detalle en 2 columnas */
.info-grid{display:grid;grid-template-columns:1fr 1fr;grid-column-gap:28px;grid-row-gap:8px;margin-top:6px}
.info-item b{color:var(--texto)}
@media(max-width:820px){.info-grid{grid-template-columns:1fr}}

/* ===== Modal mínimo (sin librerías) ===== */
.modal{
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.5);
  align-items:center; justify-content:center; z-index:10000;
}
.modal .box{
  width:95%; max-width:420px; background:#fff; border-radius:12px; overflow:hidden;
  box-shadow:0 20px 60px rgba(0,0,0,.25);
}
.modal .hd{
  padding:12px 14px; background:linear-gradient(135deg,#ffefef,#ffe5e5); border-bottom:1px solid #f3c7c7;
  display:flex; align-items:center; justify-content:space-between;
}
.modal .bd{ padding:16px; }
.modal .ft{ padding:12px 14px; background:#fafbfd; border-top:1px solid #eef1f5; text-align:right; }
.modal .btnx{ background:transparent; border:none; font-size:18px; cursor:pointer; }
footer{background:var(--azul-osc);color:#fff;text-align:center;padding:12px;margin-top:auto}
</style>
</head>
<body>

<header>
  <a href="menu_principal.php" class="back"><i class="bi bi-arrow-left-circle"></i> Volver</a>
  <h2>Control de Pagos</h2>

  <!-- Usuario + dropdown -->
  <div class="user-pill" id="userBtn">
    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_actual); ?>
  </div>
  <div class="user-menu" id="userMenu" aria-hidden="true">
    <a href="#" id="openLogout" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
  </div>
</header>

<div class="container">
  <div class="content">

    <?php if($mensaje): ?>
      <div class="alert <?php echo (strpos($mensaje,'✅')===0?'alert-success':(strpos($mensaje,'ℹ️')===0?'alert-info':'alert-warning')); ?>">
        <?php echo htmlspecialchars($mensaje); ?>
      </div>
    <?php endif; ?>

    <!-- Buscador -->
    <div class="card" style="margin-bottom:18px;">
      <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:320px">
          <label>Número de Factura</label>
          <input type="text" name="factura" required style="width:100%;padding:10px;border-radius:8px;border:1px solid #cfd8e3">
        </div>
        <button class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        <a href="control_pagos.php" class="btn btn-danger"><i class="bi bi-x-circle"></i> Cancelar</a>
      </form>
    </div>

    <!-- Resultado -->
    <?php if($factura): ?>
      <?php
        $isPagada = es_pagada(isset($factura['FACTESTA']) ? $factura['FACTESTA'] : '');
        $rutaPdf  = factura_pdf_path((int)$factura['VENTRECI']);
        $fVenta   = fmt_dmyhi(isset($factura['VENTFEVE']) ? $factura['VENTFEVE'] : null);
        $fEnt     = fmt_dmyhi(isset($factura['FACTENTR']) && $factura['FACTENTR'] ? $factura['FACTENTR'] : (isset($factura['VENTFEEN'])?$factura['VENTFEEN']:null));
        $fPago    = fmt_dmyhi(isset($factura['FACTFPAG']) ? $factura['FACTFPAG'] : null);
      ?>
      <div class="card">

        <!-- Encabezado con PDF + chip -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <h4>Factura #<?php echo htmlspecialchars($factura['VENTRECI']); ?></h4>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <?php if ($rutaPdf): ?>
              <a class="btn btn-pdf" href="<?php echo htmlspecialchars($rutaPdf); ?>" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf"></i> Ver PDF
              </a>
            <?php endif; ?>

            <?php if ($isPagada): ?>
              <span class="chip-paid"><i class="bi bi-cash-coin"></i> Pagada</span>
            <?php else: ?>
              <span class="chip-yellow"><i class="bi bi-exclamation-circle"></i> Pago: Pendiente</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Detalle -->
        <div class="info-grid">
          <div class="info-item"><b>Documento Cliente:</b> <?php echo htmlspecialchars($factura['VENTCLIE']); ?></div>
          <div class="info-item"><b>Nombre:</b> <?php echo htmlspecialchars($factura['CLIENOMB'] ? $factura['CLIENOMB'] : $factura['VENTCLIE']); ?></div>

          <div class="info-item"><b>Total:</b> $<?php echo number_format((float)$factura['VENTTOTA'],0,',','.'); ?></div>
          <div class="info-item"><b>Fecha Emisión:</b> <?php echo htmlspecialchars($fVenta); ?></div>

          <div class="info-item"><b>Fecha Pago:</b> <?php echo htmlspecialchars($fPago); ?></div>
        </div>

        <hr style="margin:12px 0">

        <!-- Acciones -->
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php if(!$isPagada): ?>
            <form method="POST">
              <input type="hidden" name="factura_id" value="<?php echo htmlspecialchars($factura['VENTRECI']); ?>">
              <button class="btn btn-primary" name="marcar_pagada"><i class="bi bi-cash-coin"></i> Marcar Pagada</button>
            </form>
          <?php endif; ?>
          <a href="control_pagos.php" class="btn btn-danger"><i class="bi bi-arrow-repeat"></i> Nueva búsqueda</a>
        </div>

      </div>
    <?php endif; ?>

  </div>
</div>

<footer>
  © <?php echo date('Y'); ?> - Sistema de Facturación
</footer>

<!-- ===== Modal Logout (sin librerías) ===== -->
<div class="modal" id="logoutModal" aria-hidden="true">
  <div class="box">
    <div class="hd">
      <strong><i class="bi bi-exclamation-triangle-fill" style="color:#dc3545"></i> Confirmar cierre de sesión</strong>
      <button class="btnx" type="button" data-close="logoutModal">&times;</button>
    </div>
    <div class="bd">
      ¿Seguro que deseas cerrar tu sesión?
    </div>
    <div class="ft">
      <button class="btn btn-primary" type="button" data-close="logoutModal">Cancelar</button>
      <a class="btn btn-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Sí, salir</a>
    </div>
  </div>
</div>

<script>
// ===== Dropdown usuario =====
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if(!btn || !menu) return;

  function close(){ menu.style.display='none'; menu.setAttribute('aria-hidden','true'); }
  function toggle(e){
    e && e.preventDefault();
    var show = menu.style.display !== 'block';
    menu.style.display = show ? 'block' : 'none';
    menu.setAttribute('aria-hidden', show ? 'false' : 'true');
  }
  btn.addEventListener('click', toggle, false);
  document.addEventListener('click', function(e){ if(!btn.contains(e.target) && !menu.contains(e.target)) close(); }, false);
})();

// ===== Modal mínimo (abre/cierra con JS plano) =====
(function(){
  function $(id){ return document.getElementById(id); }
  var openLogout = document.getElementById('openLogout');
  var modal = $('logoutModal');

  if(openLogout && modal){
    openLogout.addEventListener('click', function(e){
      e.preventDefault();
      modal.style.display='flex';
      modal.setAttribute('aria-hidden','false');
    }, false);
  }

  document.addEventListener('click', function(e){
    var t = e.target || e.srcElement;
    // cerrar por botón con data-close
    if (t && t.getAttribute && t.getAttribute('data-close') === 'logoutModal'){
      modal.style.display='none';
      modal.setAttribute('aria-hidden','true');
    }
    // cerrar si hace click fuera de la caja
    if (t === modal){
      modal.style.display='none';
      modal.setAttribute('aria-hidden','true');
    }
  }, false);

  // cerrar con ESC
  document.addEventListener('keydown', function(e){
    e = e || window.event;
    var k = e.key || e.keyCode;
    if (k === 'Escape' || k === 27){
      if (modal && modal.style.display === 'flex'){
        modal.style.display='none';
        modal.setAttribute('aria-hidden','true');
      }
    }
  }, false);
})();
</script>
</body>
</html>
