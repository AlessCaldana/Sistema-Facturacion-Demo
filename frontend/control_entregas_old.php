<?php
/* control_entregas.php â€” SOLO marcar ENTREGADA (no toca pago)
   PHP 5.3 compatible â€¢ PRG (Post/Redirect/Get)
*/
session_start();
require_once __DIR__ . '/conexion.php';
if (file_exists(__DIR__ . '/guard.php')) require_once __DIR__ . '/guard.php';
if (function_exists('require_perm')) { require_perm('entregas'); }

$usuario_actual = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';

/* ===== Helpers ===== */
function es_pendiente_entrega($fechaEntrega) {
  if ($fechaEntrega === null) return true;
  $f = trim((string)$fechaEntrega);
  return ($f === '' || $f === '0000-00-00' || $f === '0000-00-00 00:00:00');
}
function fmt_dmyhi($iso) {
  if (!$iso || $iso==='0000-00-00' || $iso==='0000-00-00 00:00:00') return '-';
  $t = @strtotime($iso);
  return $t ? date('d/m/Y H:i', $t) : '-';
}
function obtenerFactura($pdo, $id) {
  $sql = "SELECT 
            f.id AS factura_id,
            f.cliente_id,
            f.fecha,
            f.total,
            f.estado,
            f.placa,
            COALESCE(f.fecha_entrega, NULL) AS fecha_entrega,
            COALESCE(f.usuario_entrega, NULL) AS usuario_entrega,
            f.fecha_pago,
            c.nombre AS cliente_nombre,
            v.cantidad,
            p.PRODDESC AS producto
          FROM facturas f
          LEFT JOIN clientes c ON c.id = f.cliente_id
          LEFT JOIN ventas v ON v.factura_id = f.id
          LEFT JOIN productos p ON p.PRODCODI = v.producto_id
          WHERE f.id = ?
          LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute(array($id));
  return $st->fetch(PDO::FETCH_ASSOC);
}
function factura_pdf_path($id) {
  $rel = "facturas_pdf/factura_{$id}.pdf";
  return file_exists(__DIR__ . '/' . $rel) ? $rel : null;
}

/* ===== Estado ===== */
$mensaje = '';
$factura = null;

/* GET: mensaje tras PRG */
if (isset($_GET['ok']) && isset($_GET['factura']) && ctype_digit($_GET['factura'])) {
  $factura = obtenerFactura($pdo, (int)$_GET['factura']);
  if ($factura) $mensaje = "âœ… Entrega registrada correctamente.";
}

/* POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  /* Buscar */
  if (isset($_POST['factura']) && !isset($_POST['entregar'])) {
    $num = trim($_POST['factura']);
    if ($num !== '' && ctype_digit($num)) {
      $factura = obtenerFactura($pdo, (int)$num);
      if ($factura) {
        $entregada = !es_pendiente_entrega(isset($factura['VENTFEEN']) ? $factura['VENTFEEN'] : null);
        $mensaje = $entregada
          ? "â„¹ï¸ Factura encontrada: ya fue entregada."
          : "âœ… Factura encontrada: pendiente de entrega.";
      } else {
        $mensaje = "âš ï¸ No se encontrÃ³ una factura con ese nÃºmero.";
      }
    } else {
      $mensaje = "âš ï¸ Debes ingresar un nÃºmero de factura vÃ¡lido.";
    }
  }

  /* Marcar ENTREGA (sin tocar pago) */
  if (isset($_POST['entregar']) && isset($_POST['factura_id']) && ctype_digit($_POST['factura_id'])) {
    $factura_id = (int)$_POST['factura_id'];
    $usuario    = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';

    $venta = $pdo->prepare("SELECT VENTRECI, VENTFEEN FROM ventas WHERE VENTRECI = ?");
    $venta->execute(array($factura_id));
    $v = $venta->fetch(PDO::FETCH_ASSOC);

    $fact  = $pdo->prepare("SELECT FACTRECI, FACTENTR FROM facturas WHERE FACTRECI = ?");
    $fact->execute(array($factura_id));
    $f = $fact->fetch(PDO::FETCH_ASSOC);

    if (!$v) {
      $mensaje = "âš ï¸ La factura indicada no existe en VENTAS.";
    } elseif (!$f) {
      $mensaje = "âš ï¸ No existe registro en FACTURAS para esa venta.";
    } else {
      $pdo->beginTransaction();
      try {
        // VENTAS: fecha y usuario de entrega si estaba pendiente
        $sqlV = "UPDATE ventas 
                 SET VENTFEEN = NOW(), VENTUSEN = :u
                 WHERE VENTRECI = :id
                   AND (VENTFEEN IS NULL OR VENTFEEN = '' 
                        OR VENTFEEN = '0000-00-00' OR VENTFEEN = '0000-00-00 00:00:00')";
        $updV = $pdo->prepare($sqlV);
        $updV->execute(array(':u'=>$usuario, ':id'=>$factura_id));

        // FACTURAS: fecha y usuario de entrega si estaba pendiente
        $sqlFent = "UPDATE facturas 
                    SET FACTENTR = NOW(), FACTENUS = :u
                    WHERE FACTRECI = :id
                      AND (FACTENTR IS NULL OR FACTENTR = '' 
                           OR FACTENTR = '0000-00-00' OR FACTENTR = '0000-00-00 00:00:00')";
        $updFe = $pdo->prepare($sqlFent);
        $updFe->execute(array(':u'=>$usuario, ':id'=>$factura_id));

        $pdo->commit();
        header('Location: control_entregas.php?ok=1&factura='.((int)$factura_id));
        exit;

      } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "âŒ Error al registrar la entrega: " . $e->getMessage();
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Control de Entregas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  html,body{margin:0 !important;padding:0 !important;} 
  header,.topbar{margin-top:0 !important;}
:root{
  --azul:#2980b9; --azul-osc:#1f6691; --celeste:#6dd5fa;
  --texto:#0b4a6a; --gris:#475569; --rojo:#dc3545;
  --verde-osc:#146c43; --verde-borde:#0f5132;
  --danger:#dc3545; --danger-2:#b52b27; --txt:#0b3850;
}
*{ box-sizing:border-box; }
html,body{height:100%}
body{
  margin:0;font-family:"Segoe UI",Arial,sans-serif;background:#fff;color:#333;
  min-height:100vh;display:flex;flex-direction:column;
}

/* Header */
header{
  background:var(--azul-osc);color:#fff;
  padding:22px 20px;display:flex;align-items:center;justify-content:center;
  position:relative;box-shadow:0 3px 6px rgba(0,0,0,.2)
}
header h2{margin:0;font-size:1.7rem;font-weight:700}
.back{
  position:absolute;left:20px;
  background:rgba(255,255,255,.10);
  color:#fff;
  border:1px solid rgba(255,255,255,.45);
  border-radius:10px;padding:10px 18px;text-decoration:none;
  font-weight:700;display:inline-flex;align-items:center;gap:6px;transition:.2s;
}
.back:hover{background:rgba(255,255,255,.18);color:#fff}

/* Usuario + dropdown */
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

/* Layout */
.container{width:100%;max-width:1200px;margin:24px auto;padding:0 16px;flex:1}
.content{width:100%;max-width:980px;margin:0 auto}
.card{background:#fff;border:1px solid #e9f1f8;border-radius:14px;padding:18px;box-shadow:0 8px 18px rgba(0,0,0,.08)}
h4{margin:0 0 12px 0;color:var(--texto)}

/* Botones */
.btn{
  border:none;border-radius:8px;padding:8px 16px;font-weight:700;cursor:pointer;
  display:inline-flex;align-items:center;gap:8px;line-height:1;transition:.2s;font-size:.95rem
}
.btn-primary{background:var(--azul);color:#fff}
.btn-primary:hover{background:var(--celeste);color:#000}
.btn-danger{background:var(--rojo);color:#fff}
.btn-danger:hover{filter:brightness(0.95)}
.btn-pdf{background:var(--azul);color:#fff}
.btn-pdf:hover{background:var(--celeste);color:#000}

/* Alerts */
.alert{padding:10px;border-radius:8px;margin-bottom:12px;font-weight:600}
.alert-success{background:#e8ffef;color:#0b6b2f}
.alert-info{background:#e8f5ff;color:#0b4a6a}
.alert-warning{background:#fff7e6;color:#8a5c00}

/* Chips (solo de Entrega) */
.chip{
  display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;
  font-size:.83rem;font-weight:700;border:1px solid transparent;white-space:nowrap
}
.chip-green{background:var(--verde-osc);color:#fff;border-color:var(--verde-borde)}
.small-note{font-size:.85rem;color:var(--gris);display:inline-flex;align-items:center;gap:6px;white-space:nowrap}

/* Etiquetas en AZUL */
b{color:var(--texto)}
.line{border:0;border-top:1px solid #e5e7eb;margin:10px 0}

/* Modal Logout mÃ­nimo */
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
</style>
</head>
<body>

<header>
  <a href="menu_principal.php" class="back"><i class="bi bi-arrow-left-circle"></i> Volver</a>
  <h2>Control de Entregas</h2>

  <!-- Usuario + dropdown -->
  <div class="user-pill" id="userBtn">
    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_actual); ?>
  </div>
  <div class="user-menu" id="userMenu" aria-hidden="true">
    <a href="#" id="openLogout" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesiÃ³n</a>
  </div>
</header>

<div class="container">
  <div class="content">

    <?php if ($mensaje): ?>
      <div class="alert <?php echo (strpos($mensaje,'âœ…')===0?'alert-success':(strpos($mensaje,'â„¹ï¸')===0?'alert-info':'alert-warning')); ?>">
        <?php echo htmlspecialchars($mensaje); ?>
      </div>
    <?php endif; ?>

    <!-- Buscador -->
    <div class="card" style="margin-bottom:16px;">
      <h4>Buscar factura para gestionar entrega</h4>
      <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:320px">
          <label>NÃºmero de Factura</label>
          <input type="text" name="factura" inputmode="numeric" pattern="[0-9]*" required style="width:100%;padding:10px;border-radius:8px;border:1px solid #cfd8e3">
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        <a href="control_entregas.php" class="btn btn-danger"><i class="bi bi-x-circle"></i> Cancelar</a>
      </form>
    </div>

    <?php if ($factura): ?>
      <?php
        $entregada  = !es_pendiente_entrega(isset($factura['VENTFEEN']) ? $factura['VENTFEEN'] : null);
        $rutaPdf    = factura_pdf_path((int)$factura['VENTRECI']);
      ?>
      <div class="card">

        <!-- Encabezado: PDF + chip Entrega -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <h4>Factura #<?php echo htmlspecialchars($factura['VENTRECI']); ?></h4>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <?php if ($rutaPdf): ?>
              <a class="btn btn-pdf" href="<?php echo htmlspecialchars($rutaPdf); ?>" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf"></i> Ver PDF
              </a>
            <?php endif; ?>

            <?php if ($entregada): ?>
              <span class="chip chip-green"><i class="bi bi-check2-circle"></i>Entregada</span>
            <?php else: ?>
              <span class="chip" style="background:#fff3cd;color:#664d03;border-color:#ffecb5">
                <i class="bi bi-clock-history"></i> Entrega: Pendiente
              </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Info dos columnas -->
        <div style="display:grid;grid-template-columns:1fr 1fr;grid-column-gap:36px;grid-row-gap:8px">
          <div>
            <div><b>Documento Cliente:</b> <?php echo htmlspecialchars($factura['VENTCLIE']); ?></div>
            <div><b>Total:</b> $<?php echo number_format((float)(isset($factura['VENTTOTA'])?$factura['VENTTOTA']:0),0,',','.'); ?></div>
            <div><b>Fecha Pago:</b> <?php
              $fp = isset($factura['FACTFPAG']) ? $factura['FACTFPAG'] : null;
              echo fmt_dmyhi($fp);
            ?></div>
          </div>
          <div>
            <div><b>Nombre:</b> <?php echo htmlspecialchars(!empty($factura['CLIENOMB']) ? $factura['CLIENOMB'] : $factura['VENTCLIE']); ?></div>
            <div><b>Fecha EmisiÃ³n:</b> <?php echo fmt_dmyhi(isset($factura['VENTFEVE']) ? $factura['VENTFEVE'] : null); ?></div>
            <div><b>Fecha Entrega:</b> <?php echo fmt_dmyhi(isset($factura['VENTFEEN']) ? $factura['VENTFEEN'] : null); ?></div>
          </div>
        </div>

        <hr class="line">

        <!-- Acciones -->
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php if (!$entregada): ?>
            <form method="POST">
              <input type="hidden" name="factura_id" value="<?php echo htmlspecialchars($factura['VENTRECI']); ?>">
              <button type="submit" name="entregar" class="btn btn-primary">
                <i class="bi bi-check2-circle"></i> Entregar
              </button>
            </form>
          <?php endif; ?>
          <a href="control_entregas.php" class="btn btn-danger"><i class="bi bi-arrow-repeat"></i> Nueva bÃºsqueda</a>
        </div>

      </div>
    <?php endif; ?>

  </div>
</div>

<footer style="background:var(--azul-osc);color:#fff;text-align:center;padding:12px;margin-top:auto">
  Â© <?php echo date('Y'); ?> - Sistema de FacturaciÃ³n
</footer>

<!-- Modal Logout -->
<div class="modal" id="logoutModal" aria-hidden="true">
  <div class="box">
    <div class="hd">
      <strong><i class="bi bi-exclamation-triangle-fill" style="color:#dc3545"></i> Confirmar cierre de sesiÃ³n</strong>
      <button class="btnx" type="button" data-close="logoutModal">&times;</button>
    </div>
    <div class="bd">
      Â¿Seguro que deseas cerrar tu sesiÃ³n?
    </div>
    <div class="ft">
      <button class="btn btn-primary" type="button" data-close="logoutModal">Cancelar</button>
      <a class="btn btn-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> SÃ­, salir</a>
    </div>
  </div>
</div>

<script>
// Dropdown usuario
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

// Modal Logout (JS plano)
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
    if (t && t.getAttribute && t.getAttribute('data-close') === 'logoutModal'){
      modal.style.display='none';
      modal.setAttribute('aria-hidden','true');
    }
    if (t === modal){
      modal.style.display='none';
      modal.setAttribute('aria-hidden','true');
    }
  }, false);

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


