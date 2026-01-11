<?php
// consultar_factura.php (PHP 5.3 compatible)
require_once __DIR__ . '/conexion.php';
session_start();

$usuario_actual = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';

/* ================= Helpers ================= */
function fmt_fecha($s){
  if (!$s || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') return '—';
  $ts = @strtotime($s);
  return $ts ? date('d/m/Y H:i', $ts) : '—';
}
function fmt_moneda($n){
  if ($n===null || $n==='') return '—';
  return number_format((float)$n, 0, ',', '.');
}
function clean_est($e){
  $e = strtoupper(trim((string)$e));
  if ($e === '') return 'SIN ESTADO';
  if (in_array($e, array('P','PEND','PENDIENTE'), true)) return 'PENDIENTE';
  if (in_array($e, array('E','ENTREGADA','ENTREGADO'), true)) return 'ENTREGADA';
  if (in_array($e, array('PAGADA','PAGADO'), true)) return 'PAGADA';
  return $e;
}
function badge_html($estado){
  $e = clean_est($estado);
  $map = array(
    'PENDIENTE' => '#f59e0b', // ámbar
    'PAGADA'    => '#16a34a', // verde
    'ENTREGADA' => '#0ea5e9', // celeste
    'SIN ESTADO'=> '#64748b', // gris
  );
  $c = isset($map[$e]) ? $map[$e] : '#64748b';
  return '<span style="background:'.$c.';color:#fff;padding:4px 10px;border-radius:999px;font-weight:700;font-size:.9rem">'.$e.'</span>';
}
function es_supervision($prod){
  return strtoupper(trim((string)$prod)) === 'SUPERVISION INSTALACION DE MEDIDORES';
}

/* ================= Estado del request ================= */
$modo_lista = false;
$estado_filtro = '';
$mensaje = '';
$error = '';
$resultado = null;
$lista = array();
$lim = 20;
$pag = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pag < 1) $pag = 1;
$off = ($pag - 1) * $lim;
$total_reg = 0;

/* ================= Rutas PDF ================= */
function factura_pdf_webpath($id){
  return 'facturas_pdf/factura_'.$id.'.pdf';
}
function factura_pdf_exists($id){
  return file_exists(__DIR__ . '/'. factura_pdf_webpath($id));
}

/* ================= 1) Modo: abrir por n=? (GET) ================= */
if (isset($_GET['n']) && ctype_digit($_GET['n'])) {
  $_POST['FACTRECI'] = $_GET['n']; // reutilizamos el flujo POST
  $_SERVER['REQUEST_METHOD'] = 'POST';
}

/* ================= 2) Modo: listado por estado (GET) ============= */
if (isset($_GET['estado'])) {
  $estado_filtro = strtoupper(trim((string)$_GET['estado']));
  if (in_array($estado_filtro, array('PAGADA','PENDIENTE','ENTREGADA'), true)) {
    $modo_lista = true;

    $stC = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE UPPER(FACTESTA)=?");
    $stC->execute(array($estado_filtro));
    $total_reg = (int)$stC->fetchColumn();

    $sql = "
      SELECT f.FACTRECI AS fact, f.FACTFECH AS fecha, f.FACTCLIE AS doc,
             COALESCE(c.CLIENOMB, f.FACTCLIE) AS nomb,
             f.FACTVALO AS valor, f.FACTESTA AS est, f.FACTENTR AS entr, f.FACTFPAG AS fepa
      FROM facturas f
      LEFT JOIN clientes c ON c.CLIEDOCU = f.FACTCLIE
      WHERE UPPER(f.FACTESTA) = ?
      ORDER BY f.FACTFECH DESC, f.FACTRECI DESC
      LIMIT $lim OFFSET $off
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array($estado_filtro));
    $lista = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lista as &$row) {
      $estN = strtoupper(trim((string)$row['est']));
      if ((empty($row['fepa']) || $row['fepa']==='0000-00-00 00:00:00') &&
          !empty($row['entr']) &&
          in_array($estN, array('PAGADA','PAGADO','ENTREGADA','ENTREGADO'), true)) {
        $row['fepa'] = $row['entr'];
      }
    }
    unset($row);
  } else {
    $mensaje = 'Filtro de estado no válido. Usa: PAGADA, PENDIENTE o ENTREGADA.';
  }
}

/* ================= 3) Modo: búsqueda puntual (POST) ============== */
if (!$modo_lista && $_SERVER["REQUEST_METHOD"] === "POST") {
  $numero = isset($_POST['FACTRECI']) ? trim($_POST['FACTRECI']) : '';
  if ($numero !== '' && ctype_digit($numero)) {

    // Unimos facturas -> ventas -> vehiculos (+ cantidad y producto)
    $sql = "
      SELECT 
          f.FACTRECI AS fact,
          f.FACTFECH AS fecha,
          f.FACTCLIE AS doc,
          c.CLIENOMB AS nomb,
          f.FACTVALO AS valor,
          f.FACTESTA AS est,
          f.FACTENTR AS entr,
          lp.ult_pago AS fepa,
          v.VENTPLAC AS placa,
          v.VENTCANT AS cant,
          p.PRODDESC AS prod,
          vh.VEHIDOCU AS ced_conductor,
          vh.VEHINOCO AS nom_conductor
      FROM facturas f
      LEFT JOIN clientes c ON c.CLIEDOCU = f.FACTCLIE
      LEFT JOIN (
          SELECT PAGORECI, MAX(PAGOFEPA) AS ult_pago
          FROM pagos
          GROUP BY PAGORECI
      ) lp ON lp.PAGORECI = f.FACTRECI
      LEFT JOIN ventas v     ON v.VENTRECI = f.FACTRECI
      LEFT JOIN productos p  ON p.PRODCODI = v.VENTPROD
      LEFT JOIN vehiculos vh ON vh.VEHIPLAC = v.VENTPLAC
      WHERE f.FACTRECI = ?
      LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($numero));
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resultado) {
      $error = "No se encontró ninguna factura con ese número.";
    } else {
      $estN = strtoupper(trim((string)$resultado['est']));
      if ((empty($resultado['fepa']) || $resultado['fepa']==='0000-00-00 00:00:00') &&
          !empty($resultado['entr']) &&
          in_array($estN, array('PAGADA','PAGADO','ENTREGADA','ENTREGADO'), true)) {
        $resultado['fepa'] = $resultado['entr'];
      }

      if (!isset($resultado['ced_conductor']) || trim((string)$resultado['ced_conductor'])==='') $resultado['ced_conductor'] = '—';
      if (!isset($resultado['nom_conductor']) || trim((string)$resultado['nom_conductor'])==='') $resultado['nom_conductor'] = '—';
      if (!isset($resultado['placa']) || trim((string)$resultado['placa'])==='') $resultado['placa'] = '—';
      if (!isset($resultado['cant'])  || (int)$resultado['cant']<=0) $resultado['cant'] = 0;
      if (!isset($resultado['prod'])) $resultado['prod'] = '';
    }
  } else {
    $error = "Debe ingresar un número de factura válido.";
  }
}

/* ================= Paginación helper ================= */
function pager($total, $lim, $pag, $baseHref) {
  if ($total <= $lim) return '';
  $tp = (int)ceil($total / $lim);
  $html = '<div style="display:flex;gap:8px;justify-content:center;margin-top:14px">';
  for ($i=1;$i<=$tp;$i++){
    $active = ($i === $pag) ? 'background:#1f6691;color:#fff' : 'background:#f2f6fb;color:#0b4a6a';
    $html .= '<a style="padding:6px 10px;border-radius:8px;text-decoration:none;border:1px solid #cfe1f5;'.$active.'" href="'.$baseHref.'&p='.$i.'">'.$i.'</a>';
  }
  $html .= '</div>';
  return $html;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Consulta de Factura</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{
  --azul:#2980b9;
  --azul-hover:#1f6691;
  --borde-input:#dce6f3;
  --texto:#333;
  --bg:#fff;
  --danger:#dc3545; --danger-2:#b52b27; --txt:#0b3850;
}
body{ margin:0; font-family:"Segoe UI",Arial,sans-serif; background:var(--bg); color:var(--texto); display:flex; flex-direction:column; min-height:100vh; }
header{ background:var(--azul-hover); color:#fff; padding:14px 22px; display:flex; align-items:center; justify-content:center; position:relative; }
header h2{ margin:0; font-size:1.2rem; }

.header-left{ position:absolute; left:16px; }
.header-right{ position:absolute; right:16px; top:50%; transform:translateY(-50%); }
.user-pill{ display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:9999px; background:#0f5c87; color:#fff; font-weight:700; cursor:pointer; user-select:none; }
.user-menu{ display:none; position:absolute; right:16px; top:54px; min-width:220px; background:#fff; color:var(--txt); border:1px solid #e5e7eb; border-radius:12px; padding:6px; box-shadow:0 12px 28px rgba(0,0,0,.2); z-index:9999; }
.user-item{ display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:8px; font-weight:600; text-decoration:none; color:var(--danger); }
.user-item:hover{ background:#ffe5e5; color:var(--danger-2); }

main{ flex:1; }
footer{ background:var(--azul-hover); color:#fff; text-align:center; padding:10px; }

.card{ border:1px solid #e9f1f8; border-radius:14px; padding:24px; max-width:1000px; margin:30px auto; box-shadow:0 8px 16px rgba(0,0,0,0.08); }
.card h3{ text-align:center; color:var(--azul-hover); margin:0 0 18px; }

input[type="text"]{ width:200px; padding:10px; border-radius:10px; border:2px solid var(--borde-input) !important; background:#fff; }
input[type="text"]:focus{ border-color:var(--azul) !important; box-shadow:0 0 4px rgba(41,128,185,.35) !important; outline:none; }
button{ background:var(--azul); color:#fff; border:none; padding:10px 20px; border-radius:10px; cursor:pointer; font-weight:600; }
button:hover{ background:var(--azul-hover); }

.alert{padding:12px;border-radius:10px;margin-top:12px;}
.alert-error{background:#ffe5e5;color:#b30000;}
.alert-ok{background:#e8f8ff;color:#0b4a6a;}

.resultado{border:1px solid #e9f1f8;border-radius:12px;margin-top:18px;background:#fff;}
.resultado__header{ background:linear-gradient(135deg, var(--azul), #6dd5fa); color:#08324a;padding:12px;font-weight:700;border-radius:12px 12px 0 0; }

.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:16px;}
.item b{color:#0b4a6a;}
.btn-sec{ background:#f2f6fb;border:1px solid #cfe1f5;padding:8px 12px;border-radius:8px;text-decoration:none;font-weight:600;color:#0b4a6a; }
.btn-sec:hover{background:#eaf3ff;}
.btn-linklike{ background:var(--azul);padding:10px 14px;border-radius:10px;color:#fff;text-decoration:none;font-weight:700; }
.btn-linklike:hover{background:#1f6691;}

.table{ width:100%; border-collapse:collapse; margin-top:6px; }
.table th, .table td{ border:1px solid #e9f1f8; padding:10px; }
.table thead th{ background:#f4f8fc; text-align:left; }
.table td:last-child, .table th:last-child{ text-align:right; }
.row-actions a{ margin-left:8px; }

.modal{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); align-items:center; justify-content:center; z-index:10000; }
.modal .box{ width:95%; max-width:420px; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal .hd{ padding:12px 14px; background:linear-gradient(135deg,#ffefef,#ffe5e5); border-bottom:1px solid #f3c7c7; display:flex; align-items:center; justify-content:space-between; }
.modal .bd{ padding:16px; }
.modal .ft{ padding:12px 14px; background:#fafbfd; border-top:1px solid #eef1f5; text-align:right; }
.modal .btnx{ background:transparent; border:none; font-size:18px; cursor:pointer; }
</style>
</head>
<body>

<header>
  <div class="header-left">
    <a href="menu_principal.php" class="btn-sec" style="border-radius:8px;display:inline-flex;align-items:center;gap:6px;">
      <i class="bi bi-arrow-left-circle"></i> Menú
    </a>
  </div>

  <h2>Consulta de Factura</h2>

  <div class="header-right">
    <div class="user-pill" id="userBtn">
      <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_actual); ?>
    </div>
    <div class="user-menu" id="userMenu" aria-hidden="true">
      <a href="#" id="openLogout" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
  </div>
</header>

<main>
  <div class="card">
    <h3><?php echo $modo_lista ? 'Listado por estado' : 'Buscar por número de factura'; ?></h3>

    <?php if (!$modo_lista): ?>
      <form method="POST" style="display:flex;gap:10px;align-items:center;justify-content:center;">
        <label for="FACTRECI" style="margin:0;">Número de factura</label>
        <input type="text" id="FACTRECI" name="FACTRECI" inputmode="numeric" pattern="[0-9]*" required autofocus>
        <button type="submit">Consultar</button>
      </form>
    <?php else: ?>
      <div style="text-align:center;margin-bottom:10px">
        Mostrando: <?php echo badge_html($estado_filtro); ?>
      </div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
      <div class="alert alert-ok"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!$modo_lista && $resultado): ?>
      <?php
        $estadoN = clean_est($resultado['est']);
        $pdf_ok  = factura_pdf_exists($resultado['fact']);
        $webPath = factura_pdf_webpath($resultado['fact']);
        $lblCant = es_supervision($resultado['prod']) ? 'Cantidad' : 'Cantidad MT³';
      ?>
      <div class="alert alert-ok">Resultado encontrado ✅</div>
      <div class="resultado">
        <div class="resultado__header">
          Factura Nº <?php echo htmlspecialchars($resultado['fact']); ?> &nbsp;<?php echo badge_html($estadoN); ?>
        </div>

        <!-- ✅ Orden como en la imagen guía -->
        <div class="grid">
          <!-- Fila 1 -->
          <div class="item"><b>Fecha Emisión:</b> <?php echo fmt_fecha($resultado['fecha']); ?></div>
          <div class="item"><b>Fecha Entrega:</b> <?php echo fmt_fecha($resultado['entr']); ?></div>

          <!-- Fila 2 -->
          <div class="item"><b>Fecha Pago:</b> <?php echo fmt_fecha($resultado['fepa']); ?></div>
          <div class="item"><b>Placa:</b> <?php echo htmlspecialchars($resultado['placa']); ?></div>

          <!-- Fila 3 -->
          <div class="item"><b>Cédula del Conductor:</b> <?php echo htmlspecialchars($resultado['ced_conductor']); ?></div>
          <div class="item"><b>Nombre del Conductor:</b> <?php echo htmlspecialchars($resultado['nom_conductor']); ?></div>

          <!-- Fila 4 -->
          <div class="item"><b>Producto:</b> <?php echo htmlspecialchars($resultado['prod']); ?></div>
          <div class="item"><b><?php echo $lblCant; ?>:</b> <?php echo (int)$resultado['cant']; ?></div>

          <!-- Fila 5 -->
          <div class="item"><b>Total Factura:</b> $<?php echo fmt_moneda($resultado['valor']); ?></div>
          <div class="item"></div>
        </div>

        <div style="display:flex;justify-content:space-between;padding:16px;">
          <a class="btn-sec" href="consultar_factura.php"><i class="bi bi-search"></i> Nueva consulta</a>
          <?php if ($pdf_ok): ?>
            <a class="btn-linklike" href="<?php echo htmlspecialchars($webPath); ?>" target="_blank">
              <i class="bi bi-box-arrow-up-right"></i> Abrir PDF
            </a>
          <?php else: ?>
            <span style="opacity:.8">No hay PDF generado para esta factura.</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($modo_lista): ?>
      <?php if (empty($lista)): ?>
        <div class="alert alert-ok">No hay facturas con ese estado.</div>
      <?php else: ?>
        <div class="alert alert-ok">Total: <?php echo (int)$total_reg; ?> &middot; Página <?php echo (int)$pag; ?></div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th>Pago</th>
                <th style="text-align:right">Valor</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lista as $r):
                $pdf_ok = factura_pdf_exists($r['fact']);
                $webPath = factura_pdf_webpath($r['fact']);
              ?>
              <tr>
                <td>
                  <?php echo (int)$r['fact']; ?>
                  <span class="row-actions">
                    <a class="btn-sec" href="consultar_factura.php?n=<?php echo (int)$r['fact']; ?>">ver</a>
                    <?php if ($pdf_ok): ?>
                      <a class="btn-sec" href="<?php echo htmlspecialchars($webPath); ?>" target="_blank">PDF</a>
                    <?php endif; ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars(date('Y-m-d', @strtotime($r['fecha']))); ?></td>
                <td><?php echo htmlspecialchars($r['nomb']); ?></td>
                <td><?php echo badge_html($r['est']); ?></td>
                <td><?php echo fmt_fecha($r['fepa']); ?></td>
                <td style="text-align:right">$<?php echo fmt_moneda($r['valor']); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php echo pager($total_reg, $lim, $pag, 'consultar_factura.php?estado='.urlencode($estado_filtro)); ?>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</main>

<footer>© <?php echo date('Y'); ?> - Sistema de Facturación</footer>

<!-- Modal Logout -->
<div class="modal" id="logoutModal" aria-hidden="true">
  <div class="box">
    <div class="hd">
      <strong><i class="bi bi-exclamation-triangle-fill" style="color:#dc3545"></i> Confirmar cierre de sesión</strong>
      <button class="btnx" type="button" data-close="logoutModal">&times;</button>
    </div>
    <div class="bd">¿Seguro que deseas cerrar tu sesión?</div>
    <div class="ft">
      <button class="btn-sec" type="button" data-close="logoutModal"><i class="bi bi-x-circle"></i> Cancelar</button>
      <a class="btn-linklike" style="background:#dc3545" href="logout.php"><i class="bi bi-box-arrow-right"></i> Sí, salir</a>
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

// Modal Logout
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
