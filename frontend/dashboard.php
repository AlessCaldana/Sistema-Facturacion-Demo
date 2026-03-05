<?php
/* =====================  EMDUPAR Â· Dashboard (PHP 5.3)  ===================== */
session_start();
require_once __DIR__ . '/demo_config.php';

if ((!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '') && defined('DEMO_PUBLICO') && DEMO_PUBLICO) {
  $_SESSION['usuario'] = (defined('DEMO_USUARIO_DOC') ? DEMO_USUARIO_DOC : 'demo');
  $_SESSION['nombre']  = (defined('DEMO_USUARIO_NOMBRE') ? DEMO_USUARIO_NOMBRE : 'Usuario Demo');
  $_SESSION['rol']     = strtolower((defined('DEMO_USUARIO_ROL') ? DEMO_USUARIO_ROL : 'admin'));
}

if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '') { header('Location: login.php'); exit; }

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/guard.php';

/* ================= Helpers ================= */
function qcol($pdo, $sql, $params = array()){ $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchColumn(); }
function qall($pdo, $sql, $params = array()){ $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
function cop($n){ return '$'.number_format((float)$n, 0, ',', '.'); }
function badge($e){
  $map = array('PAGADA'=>'#16a34a','PENDIENTE'=>'#f59e0b','ANULADA'=>'#ef4444');
  $key = strtoupper((string)$e);
  $c = isset($map[$key]) ? $map[$key] : '#64748b';
  return '<span style="background:'.$c.';color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;">'.htmlspecialchars($e).'</span>';
}
function sane_date($s){
  if(!$s) return '';
  $d=DateTime::createFromFormat('Y-m-d',$s);
  return ($d && $d->format('Y-m-d')===$s) ? $s : '';
}
function tableExists($pdo, $table){
  $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
  $st->execute(array($table));
  return (bool)$st->fetchColumn();
}

/* ====== Rango de fechas ====== */
if (isset($_POST['filtrar'])) {
  $desde = sane_date(isset($_POST['desde']) ? $_POST['desde'] : '');
  $hasta = sane_date(isset($_POST['hasta']) ? $_POST['hasta'] : '');
  if ($desde==='') $desde=date('Y-m-01');
  if ($hasta==='') $hasta=date('Y-m-t');
  $_SESSION['dash_desde']=$desde; $_SESSION['dash_hasta']=$hasta;
} elseif (isset($_GET['desde']) || isset($_GET['hasta'])) {
  $desde = sane_date(isset($_GET['desde']) ? $_GET['desde'] : '');
  $hasta = sane_date(isset($_GET['hasta']) ? $_GET['hasta'] : '');
  if ($desde==='') $desde=date('Y-m-01');
  if ($hasta==='') $hasta=date('Y-m-t');
  $_SESSION['dash_desde']=$desde; $_SESSION['dash_hasta']=$hasta;
} else {
  $desde = sane_date(isset($_SESSION['dash_desde']) ? $_SESSION['dash_desde'] : '');
  $hasta = sane_date(isset($_SESSION['dash_hasta']) ? $_SESSION['dash_hasta'] : '');
  if ($desde==='') $desde=date('Y-m-01');
  if ($hasta==='') $hasta=date('Y-m-t');
}
$desdeFull = $desde . ' 00:00:00';
$hastaFull = $hasta . ' 23:59:59';

/* ===== Links ===== */
$linkReportes = 'reportes.php?tab=ventas&desde_ventas='.urlencode($desde).'&hasta_ventas='.urlencode($hasta).'#ventas';
$linkPagadas  = 'consultar_factura.php?estado=PAGADA';
$linkPend     = 'consultar_factura.php?estado=PENDIENTE';

/* ===== Bandera detalle ===== */
$HAS_DET = tableExists($pdo, 'ventas_det');

/* ===== Patrones de categorÃ­as ===== */
$AC  = '%AGUA CRUDA%';
$AT  = '%AGUA TRATADA%';
$SV1 = '%SUPERVISION%';
$SV2 = '%SUPERVISIÃ“N%';

/* ================= CÃ¡lculo de KPIs por rango ================= */
/* AGUA (Cruda+Tratada) y SUPERVISIÃ“N â€” usando SIEMPRE COALESCE(v.VENTFEVE, f.FACTFECH) */
if ($HAS_DET){
  $sqlK = "
    SELECT
      SUM(
        CASE WHEN (
          EXISTS(SELECT 1
                 FROM ventas_det vd
                 JOIN productos p ON p.PRODCODI=vd.PRODCODI
                 WHERE vd.VENTRECI=f.FACTRECI AND (p.PRODDESC LIKE ? OR p.PRODDESC LIKE ?))
          OR (
            NOT EXISTS(SELECT 1 FROM ventas_det vdz WHERE vdz.VENTRECI=f.FACTRECI)
            AND EXISTS(SELECT 1
                       FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
                       WHERE p2.PRODCODI=v2.VENTPROD AND (p2.PRODDESC LIKE ? OR p2.PRODDESC LIKE ?))
          )
        ) THEN f.FACTVALO ELSE 0 END
      ) AS agua,
      SUM(
        CASE WHEN (
          EXISTS(SELECT 1
                 FROM ventas_det vd
                 JOIN productos p ON p.PRODCODI=vd.PRODCODI
                 WHERE vd.VENTRECI=f.FACTRECI AND (p.PRODDESC LIKE ? OR p.PRODDESC LIKE ?))
          OR (
            NOT EXISTS(SELECT 1 FROM ventas_det vdz WHERE vdz.VENTRECI=f.FACTRECI)
            AND EXISTS(SELECT 1
                       FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
                       WHERE p2.PRODCODI=v2.VENTPROD AND (p2.PRODDESC LIKE ? OR p2.PRODDESC LIKE ?))
          )
        ) THEN f.FACTVALO ELSE 0 END
      ) AS sup
    FROM facturas f
    LEFT JOIN ventas v ON v.VENTRECI=f.FACTRECI
    WHERE COALESCE(v.VENTFEVE, f.FACTFECH) BETWEEN ? AND ?
  ";
  $k = qall($pdo, $sqlK, array($AC,$AT,$AC,$AT, $SV1,$SV2,$SV1,$SV2, $desdeFull,$hastaFull));
} else {
  $sqlK = "
    SELECT
      SUM(
        CASE WHEN EXISTS(
          SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
          WHERE p2.PRODCODI=v2.VENTPROD AND (p2.PRODDESC LIKE ? OR p2.PRODDESC LIKE ?)
        ) THEN f.FACTVALO ELSE 0 END
      ) AS agua,
      SUM(
        CASE WHEN EXISTS(
          SELECT 1 FROM productos p3 JOIN ventas v3 ON v3.VENTRECI=f.FACTRECI
          WHERE p3.PRODCODI=v3.VENTPROD AND (p3.PRODDESC LIKE ? OR p3.PRODDESC LIKE ?)
        ) THEN f.FACTVALO ELSE 0 END
      ) AS sup
    FROM facturas f
    LEFT JOIN ventas v ON v.VENTRECI=f.FACTRECI
    WHERE COALESCE(v.VENTFEVE, f.FACTFECH) BETWEEN ? AND ?
  ";
  $k = qall($pdo, $sqlK, array($AC,$AT, $SV1,$SV2, $desdeFull,$hastaFull));
}
$ventasRangoAgua = isset($k[0]['agua']) ? (float)$k[0]['agua'] : 0.0;
$ventasRangoSup  = isset($k[0]['sup'])  ? (float)$k[0]['sup']  : 0.0;

/* ===== Facturas / Pagadas / Pendientes ===== */
$facturasPeriodo = (int) qcol($pdo, "
  SELECT COUNT(*)
  FROM facturas f
  LEFT JOIN ventas v ON v.VENTRECI = f.FACTRECI
  WHERE COALESCE(v.VENTFEVE, f.FACTFECH) >= ?
    AND COALESCE(v.VENTFEVE, f.FACTFECH) <= ?
", array($desdeFull,$hastaFull));

$pagadas = (int) qcol($pdo, "
  SELECT COUNT(*)
  FROM facturas f
  LEFT JOIN ventas v ON v.VENTRECI = f.FACTRECI
  WHERE f.FACTESTA='PAGADA'
    AND COALESCE(v.VENTFEVE, f.FACTFECH) >= ?
    AND COALESCE(v.VENTFEVE, f.FACTFECH) <= ?
", array($desdeFull,$hastaFull));

$pendientes = (int) qcol($pdo, "
  SELECT COUNT(*)
  FROM facturas f
  LEFT JOIN ventas v ON v.VENTRECI = f.FACTRECI
  WHERE f.FACTESTA='PENDIENTE'
    AND COALESCE(v.VENTFEVE, f.FACTFECH) >= ?
    AND COALESCE(v.VENTFEVE, f.FACTFECH) <= ?
", array($desdeFull,$hastaFull));

/* ===== DONA: PENDIENTES por categorÃ­a (AC / AT / SV) â€” YA RESPETA EL RANGO ===== */
if ($HAS_DET){
  $sqlDonut = "
    SELECT
      SUM(
        CASE WHEN (
          EXISTS(SELECT 1 FROM ventas_det vd JOIN productos p ON p.PRODCODI=vd.PRODCODI
                 WHERE vd.VENTRECI=f.FACTRECI AND p.PRODDESC LIKE ?)
          OR (
            NOT EXISTS(SELECT 1 FROM ventas_det vdz WHERE vdz.VENTRECI=f.FACTRECI)
            AND EXISTS(SELECT 1 FROM productos px JOIN ventas vx ON vx.VENTRECI=f.FACTRECI
                       WHERE px.PRODCODI=vx.VENTPROD AND px.PRODDESC LIKE ?)
          )
        ) THEN 1 ELSE 0 END
      ) AS ac,
      SUM(
        CASE WHEN (
          EXISTS(SELECT 1 FROM ventas_det vd JOIN productos p ON p.PRODCODI=vd.PRODCODI
                 WHERE vd.VENTRECI=f.FACTRECI AND p.PRODDESC LIKE ?)
          OR (
            NOT EXISTS(SELECT 1 FROM ventas_det vdz WHERE vdz.VENTRECI=f.FACTRECI)
            AND EXISTS(SELECT 1 FROM productos px JOIN ventas vx ON vx.VENTRECI=f.FACTRECI
                       WHERE px.PRODCODI=vx.VENTPROD AND px.PRODDESC LIKE ?)
          )
        ) THEN 1 ELSE 0 END
      ) AS at,
      SUM(
        CASE WHEN (
          EXISTS(SELECT 1 FROM ventas_det vd JOIN productos p ON p.PRODCODI=vd.PRODCODI
                 WHERE vd.VENTRECI=f.FACTRECI AND (p.PRODDESC LIKE ? OR p.PRODDESC LIKE ?))
          OR (
            NOT EXISTS(SELECT 1 FROM ventas_det vdz WHERE vdz.VENTRECI=f.FACTRECI)
            AND EXISTS(SELECT 1 FROM productos px JOIN ventas vx ON vx.VENTRECI=f.FACTRECI
                       WHERE px.PRODCODI=vx.VENTPROD AND (px.PRODDESC LIKE ? OR px.PRODDESC LIKE ?))
          )
        ) THEN 1 ELSE 0 END
      ) AS sv
    FROM facturas f
    LEFT JOIN ventas v ON v.VENTRECI=f.FACTRECI
    WHERE f.FACTESTA='PENDIENTE'
      AND COALESCE(v.VENTFEVE, f.FACTFECH) BETWEEN ? AND ?
  ";
  $d = qall($pdo, $sqlDonut, array($AC,$AC, $AT,$AT, $SV1,$SV2,$SV1,$SV2, $desdeFull,$hastaFull));
} else {
  $sqlDonut = "
    SELECT
      SUM(CASE WHEN EXISTS(
        SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
        WHERE p2.PRODCODI=v2.VENTPROD AND p2.PRODDESC LIKE ?
      ) THEN 1 ELSE 0 END) AS ac,
      SUM(CASE WHEN EXISTS(
        SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
        WHERE p2.PRODCODI=v2.VENTPROD AND p2.PRODDESC LIKE ?
      ) THEN 1 ELSE 0 END) AS at,
      SUM(CASE WHEN EXISTS(
        SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
        WHERE p2.PRODCODI=v2.VENTPROD AND (p2.PRODDESC LIKE ? OR p2.PRODDESC LIKE ?)
      ) THEN 1 ELSE 0 END) AS sv
    FROM facturas f
    LEFT JOIN ventas v ON v.VENTRECI=f.FACTRECI
    WHERE f.FACTESTA='PENDIENTE'
      AND COALESCE(v.VENTFEVE, f.FACTFECH) BETWEEN ? AND ?
  ";
  $d = qall($pdo, $sqlDonut, array($AC,$AT,$SV1,$SV2,$desdeFull,$hastaFull));
}
$pendAC = isset($d[0]['ac']) ? (int)$d[0]['ac'] : 0;
$pendAT = isset($d[0]['at']) ? (int)$d[0]['at'] : 0;
$pendSV = isset($d[0]['sv']) ? (int)$d[0]['sv'] : 0;

/* ===== Serie por meses DENTRO DEL RANGO SELECCIONADO =====
   Agrupa por YYYY-MM entre $desdeFull y $hastaFull. */
if ($HAS_DET){
  $sql6 = "
    SELECT
      DATE_FORMAT(COALESCE(v.VENTFEVE, f.FACTFECH),'%Y-%m') AS ym,
      SUM(CASE WHEN (
        EXISTS(SELECT 1 FROM ventas_det vd
               JOIN productos p ON p.PRODCODI=vd.PRODCODI
               WHERE vd.VENTRECI=f.FACTRECI AND p.PRODDESC LIKE ?)
        OR (
          NOT EXISTS(SELECT 1 FROM ventas_det x WHERE x.VENTRECI=f.FACTRECI)
          AND EXISTS(SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
                     WHERE p2.PRODCODI=v2.VENTPROD AND p2.PRODDESC LIKE ?)
        )
      ) THEN f.FACTVALO ELSE 0 END) AS ac,
      SUM(CASE WHEN (
        EXISTS(SELECT 1 FROM ventas_det vd
               JOIN productos p ON p.PRODCODI=vd.PRODCODI
               WHERE vd.VENTRECI=f.FACTRECI AND p.PRODDESC LIKE ?)
        OR (
          NOT EXISTS(SELECT 1 FROM ventas_det x WHERE x.VENTRECI=f.FACTRECI)
          AND EXISTS(SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
                     WHERE p2.PRODCODI=v2.VENTPROD AND p2.PRODDESC LIKE ?)
        )
      ) THEN f.FACTVALO ELSE 0 END) AS at,
      SUM(CASE WHEN (
        EXISTS(SELECT 1 FROM ventas_det vd
               JOIN productos p ON p.PRODCODI=vd.PRODCODI
               WHERE vd.VENTRECI=f.FACTRECI AND (p.PRODDESC LIKE ? OR p.PRODDESC LIKE ?))
        OR (
          NOT EXISTS(SELECT 1 FROM ventas_det x WHERE x.VENTRECI=f.FACTRECI)
          AND EXISTS(SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
                     WHERE p2.PRODCODI=v2.VENTPROD AND (p2.PRODDESC LIKE ? OR p2.PRODDESC LIKE ?))
        )
      ) THEN f.FACTVALO ELSE 0 END) AS sv
    FROM facturas f
    LEFT JOIN ventas v ON v.VENTRECI=f.FACTRECI
    WHERE COALESCE(v.VENTFEVE, f.FACTFECH) BETWEEN ? AND ?
    GROUP BY ym
    ORDER BY ym ASC
  ";
  $s6 = qall($pdo, $sql6, array($AC,$AC, $AT,$AT, $SV1,$SV2,$SV1,$SV2, $desdeFull,$hastaFull));
} else {
  $sql6 = "
    SELECT
      DATE_FORMAT(COALESCE(v.VENTFEVE, f.FACTFECH),'%Y-%m') AS ym,
      SUM(CASE WHEN EXISTS(
        SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
        WHERE p2.PRODCODI=v2.VENTPROD AND p2.PRODDESC LIKE ?
      ) THEN f.FACTVALO ELSE 0 END) AS ac,
      SUM(CASE WHEN EXISTS(
        SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
        WHERE p2.PRODCODI=v2.VENTPROD AND p2.PRODDESC LIKE ?
      ) THEN f.FACTVALO ELSE 0 END) AS at,
      SUM(CASE WHEN EXISTS(
        SELECT 1 FROM productos p2 JOIN ventas v2 ON v2.VENTRECI=f.FACTRECI
        WHERE p2.PRODCODI=v2.VENTPROD AND (p2.PRODDESC LIKE ? OR p2.PRODDESC LIKE ?)
      ) THEN f.FACTVALO ELSE 0 END) AS sv
    FROM facturas f
    LEFT JOIN ventas v ON v.VENTRECI=f.FACTRECI
    WHERE COALESCE(v.VENTFEVE, f.FACTFECH) BETWEEN ? AND ?
    GROUP BY ym
    ORDER BY ym ASC
  ";
  $s6 = qall($pdo, $sql6, array($AC,$AT,$SV1,$SV2,$desdeFull,$hastaFull));
}
$labels6 = array(); $ac6=array(); $at6=array(); $sv6=array();
foreach($s6 as $r){
  $labels6[] = isset($r['ym']) ? $r['ym'] : '';
  $ac6[] = isset($r['ac']) ? (float)$r['ac'] : 0.0;
  $at6[] = isset($r['at']) ? (float)$r['at'] : 0.0;
  $sv6[] = isset($r['sv']) ? (float)$r['sv'] : 0.0;
}

/* ===== Actividad reciente ===== */
$recientes = qall($pdo, "
  SELECT f.FACTRECI, DATE_FORMAT(COALESCE(v.VENTFEVE, f.FACTFECH),'%Y-%m-%d') AS fecha,
         COALESCE(c.CLIENOMB, f.FACTCLIE) AS cliente, f.FACTESTA, f.FACTVALO
  FROM facturas f
  LEFT JOIN ventas v ON v.VENTRECI=f.FACTRECI
  LEFT JOIN clientes c ON c.CLIEDOCU = f.FACTCLIE
  WHERE COALESCE(v.VENTFEVE, f.FACTFECH) BETWEEN ? AND ?
  ORDER BY COALESCE(v.VENTFEVE, f.FACTFECH) DESC, f.FACTRECI DESC
  LIMIT 10
", array($desdeFull,$hastaFull));

$usuario_doc = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>EMDUPAR Â· Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="icon" type="image/x-icon" href="img/emdupar.ico">
<link rel="icon" type="image/png" sizes="32x32" href="img/emdupar-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="img/emdupar-180.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  html,body{margin:0 !important;padding:0 !important;} 
  header,.topbar{margin-top:0 !important;}
:root{ --azul:#1f6691; --bg:#eef3f8; --card:#ffffff; --borde:#e6eef6; --danger:#e74c3c; }
body{ background:var(--bg); font-family: Segoe UI, Arial, sans-serif; }
.topbar{ background:var(--azul); padding:10px 12px; }
.topbar-inner{ max-width:1200px; margin:0 auto; display:grid; grid-template-columns:1fr auto 1fr; align-items:center; }
.btn-volver{ justify-self:start; display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.10); color:#fff; border:1px solid rgba(255,255,255,.45); border-radius:9999px; padding:8px 14px; font-weight:700; text-decoration:none; box-shadow:none; transition:.2s; }
.topbar-title{ margin:0; color:#fff; font-size:22px; font-weight:800; text-align:center; }

/* User pill + dropdown (solo Cerrar sesiÃ³n) */
.user-box{ justify-self:end; position:relative; }
.user-pill{ display:flex; align-items:center; gap:8px; color:#fff; font-weight:700; cursor:pointer; padding:6px 10px; border-radius:9999px; background:rgba(255,255,255,.12); }
.user-menu{ position:absolute; right:0; top:42px; width:200px; background:#fff; border:1px solid #e7eef6; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.12); padding:6px; display:none; z-index:20; }
.user-menu.show{ display:block; }
.user-danger{ display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:10px; color:var(--danger); font-weight:700; cursor:pointer; }
.user-danger:hover{ background:#fdecea; }

.container-xxl{ max-width:1200px; }
.card{ border-radius:14px; border:1px solid var(--borde); background:var(--card); box-shadow:0 3px 10px rgba(0,0,0,.06); }
.kpi .label{ font-size:12px; color:#6b7a8a; }
.kpi .value{ font-size:22px; font-weight:800; color:#1f6691; }
.table thead th{ background:#f4f8fc; }
.table td:last-child, .table th:last-child{ text-align:right; }
</style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="reportes.php" class="btn-volver"><i class="bi bi-arrow-left-circle"></i> Volver</a>
    <h1 class="topbar-title">Panel de Control â€” EMDUPAR</h1>

    <div class="user-box">
      <div id="userBtn" class="user-pill">
        <i class="bi bi-person-circle"></i>
        <span><?php echo htmlspecialchars($usuario_doc); ?></span>
      </div>
      <div id="userMenu" class="user-menu" aria-hidden="true">
        <!-- Solo cerrar sesiÃ³n (en rojo) -->
        <a class="user-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesiÃ³n</a>
      </div>
    </div>
  </div>
</header>

<div class="container-xxl my-4">

  <!-- FILTROS -->
  <form method="post" class="card p-3 mb-3">
    <div class="row g-3 align-items-end">
      <div class="col-sm-6 col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" name="desde" value="<?php echo htmlspecialchars($desde); ?>">
      </div>
      <div class="col-sm-6 col-md-3">
        <label class="form-label">Hasta</label>
        <div class="input-group">
          <input type="date" class="form-control" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>">
          <span class="input-group-text"><i class="bi bi-calendar-date"></i></span>
        </div>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary px-4" name="filtrar" value="1"><i class="bi bi-funnel"></i> Aplicar</button>
      </div>
    </div>
  </form>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card p-3 kpi">
        <div class="label">Ventas AGUA (rango)</div>
        <div class="value"><?php echo cop($ventasRangoAgua); ?></div>
        <small class="text-muted">Agua Cruda + Tratada Â· <?php echo htmlspecialchars($desde); ?> â€” <?php echo htmlspecialchars($hasta); ?></small>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card p-3 kpi">
        <div class="label">Ventas SUPERVISIÃ“N (rango)</div>
        <div class="value"><?php echo cop($ventasRangoSup); ?></div>
        <small class="text-muted">InstalaciÃ³n de medidores Â· <?php echo htmlspecialchars($desde); ?> â€” <?php echo htmlspecialchars($hasta); ?></small>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card p-3 kpi">
        <div class="label">Facturas del perÃ­odo</div>
        <div class="value"><a href="<?php echo htmlspecialchars($linkReportes); ?>"><?php echo $facturasPeriodo; ?></a></div>
        <small class="text-muted">Abrir en Reportes Â· Ventas</small>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card p-3 kpi">
        <div class="label">Pagadas</div>
        <div class="value"><a href="<?php echo htmlspecialchars($linkPagadas); ?>"><?php echo $pagadas; ?></a></div>
        <small class="text-muted"><?php echo htmlspecialchars($desde); ?> â€” <?php echo htmlspecialchars($hasta); ?></small>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card p-3 kpi">
        <div class="label">Pendientes</div>
        <div class="value"><a href="<?php echo htmlspecialchars($linkPend); ?>"><?php echo $pendientes; ?></a></div>
        <small class="text-muted"><?php echo htmlspecialchars($desde); ?> â€” <?php echo htmlspecialchars($hasta); ?></small>
      </div>
    </div>
  </div>

  <!-- GRÃFICAS -->
  <div class="row g-3 mt-1">
    <div class="col-lg-8">
      <div class="card p-3">
        <h5 class="mb-3">Ventas por mes (segÃºn rango)</h5>
        <canvas id="cCombinado"></canvas>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card p-3">
        <h5 class="mb-3">Pendientes por categorÃ­a</h5>
        <canvas id="cDonaPend"></canvas>
      </div>
    </div>
  </div>

  <!-- Tres grÃ¡ficas separadas -->
  <div class="row g-3 mt-1">
    <div class="col-lg-4">
      <div class="card p-3">
        <h5 class="mb-3">Agua Cruda â€” en rango</h5>
        <canvas id="cAguaCruda"></canvas>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card p-3">
        <h5 class="mb-3">Agua Tratada â€” en rango</h5>
        <canvas id="cAguaTratada"></canvas>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card p-3">
        <h5 class="mb-3">SupervisiÃ³n â€” en rango</h5>
        <canvas id="cSupervision"></canvas>
      </div>
    </div>
  </div>

  <!-- ACTIVIDAD RECIENTE -->
  <div class="card p-3 mt-3">
    <h5 class="mb-3">Actividad Reciente</h5>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead><tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Valor</th></tr></thead>
        <tbody>
          <?php foreach ($recientes as $r):
            $pdf = 'facturas_pdf/factura_'.(int)$r['FACTRECI'].'.pdf';
          ?>
          <tr>
            <td><a href="<?php echo htmlspecialchars($pdf); ?>" target="_blank"><?php echo htmlspecialchars($r['FACTRECI']); ?></a></td>
            <td><?php echo htmlspecialchars($r['fecha']); ?></td>
            <td><?php echo htmlspecialchars($r['cliente']); ?></td>
            <td><?php echo badge($r['FACTESTA']); ?></td>
            <td><?php echo cop($r['FACTVALO']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
/* Toggle menÃº usuario */
(function(){
  var btn=document.getElementById('userBtn'), menu=document.getElementById('userMenu');
  if(!btn || !menu) return;
  btn.addEventListener('click', function(e){ e.stopPropagation(); menu.classList.toggle('show'); });
  document.addEventListener('click', function(){ menu.classList.remove('show'); });
  document.addEventListener('keydown', function(ev){ if(ev.key==='Escape') menu.classList.remove('show'); });
})();

/* PHP -> JS */
var L6  = <?php echo json_encode($labels6); ?>;
var AC6 = <?php echo json_encode($ac6); ?>;   // Agua Cruda
var AT6 = <?php echo json_encode($at6); ?>;   // Agua Tratada
var SV6 = <?php echo json_encode($sv6); ?>;   // SupervisiÃ³n
var PEND = <?php echo json_encode(array($pendAC,$pendAT,$pendSV)); ?>;

/* Formato COP */
var fmtCOP = new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0});

/* Combinado (en rango) */
new Chart(document.getElementById('cCombinado').getContext('2d'), {
  type:'bar',
  data:{
    labels:L6,
    datasets:[
      { label:'Agua Cruda',   data:AC6, borderWidth:1 },
      { label:'Agua Tratada', data:AT6, borderWidth:1 },
      { label:'SupervisiÃ³n',  data:SV6, borderWidth:1 }
    ]
  },
  options:{
    responsive:true,
    scales:{ y:{ beginAtZero:true, ticks:{ callback:function(v){ return fmtCOP.format(v); } } } },
    plugins:{
      legend:{ position:'top' },
      tooltip:{ callbacks:{ label:function(ctx){ return ctx.dataset.label+': '+fmtCOP.format(ctx.parsed.y||0); } } }
    }
  }
});

/* Dona de pendientes por categorÃ­a (en rango) */
var ctxD=document.getElementById('cDonaPend').getContext('2d');
new Chart(ctxD,{
  type:'doughnut',
  data:{ labels:['Pend. Agua Cruda','Pend. Agua Tratada','Pend. SupervisiÃ³n'], datasets:[{ data:PEND, borderWidth:1 }] },
  options:{ plugins:{ legend:{ position:'bottom' } }, cutout:'55%' }
});

/* GrÃ¡ficas individuales (en rango) */
function simpleBar(canvasId, label, data){
  new Chart(document.getElementById(canvasId).getContext('2d'), {
    type:'bar',
    data:{ labels:L6, datasets:[{ label:label, data:data, borderWidth:1 }] },
    options:{ scales:{ y:{ beginAtZero:true, ticks:{ callback:function(v){ return fmtCOP.format(v); } } } },
      plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:function(c){ return fmtCOP.format(c.parsed.y||0); } } } } }
  });
}
simpleBar('cAguaCruda','Agua Cruda',AC6);
simpleBar('cAguaTratada','Agua Tratada',AT6);
simpleBar('cSupervision','SupervisiÃ³n',SV6);
</script>
</body>
</html>


