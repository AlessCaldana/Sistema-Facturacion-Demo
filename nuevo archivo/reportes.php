<?php
/* reportes.php — Ventas/Entregas (con columnas Producto y Cantidad)
   - Muestra y exporta Producto (por detalle o por código en ventas).
   - Nueva columna "Cantidad".
   - Ordena por alias "Producto" y "Cantidad".
   - Si NO existe la tabla de detalle, no usa el alias pr (y Cantidad=1).
   - Exporta CSV y PDF (FPDF).
   - Permisos: rep_ventas / rep_entregas (lectura).
   - Header con título centrado + pill de usuario y menú “Cerrar sesión”.
*/

ob_start();
session_start();
date_default_timezone_set('America/Bogota');

require_once 'conexion.php';             // Debe crear $pdo (PDO)
require_once __DIR__ . '/guard.php';     // can(), require_perm(), etc.

/* ===== DEBUG ===== */
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($pdo) && $pdo instanceof PDO) {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

/* ===== Helpers ===== */
function tableExists($pdo, $table) {
  $st = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = ?
  ");
  $st->execute(array($table));
  return (bool)$st->fetchColumn();
}
function columnExists($pdo, $table, $column) {
  $st = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
  ");
  $st->execute(array($table, $column));
  return (bool)$st->fetchColumn();
}

/* === Logo robusto para FPDF (acepta PNG/JPG y “aplana” si el PNG está raro) === */
function logoSeguroFPDF($rutaPng){
  $rutaPng = (string)$rutaPng;
  $dir = dirname($rutaPng);
  $jpg = $dir . '/LogoEMDT.jpg';

  if (file_exists($jpg) && is_readable($jpg)) return $jpg;

  if (file_exists($rutaPng) && is_readable($rutaPng)) {
    if (function_exists('imagecreatefrompng')) {
      $im = @imagecreatefrompng($rutaPng);
      if ($im !== false) {
        imagedestroy($im);
        return $rutaPng;
      }
    }
    if (function_exists('imagecreatefrompng')) {
      $im = @imagecreatefrompng($rutaPng);
      if ($im !== false) {
        $w = imagesx($im); $h = imagesy($im);
        $bg = imagecreatetruecolor($w,$h);
        $white = imagecolorallocate($bg,255,255,255);
        imagefilledrectangle($bg,0,0,$w,$h,$white);
        imagecopy($bg,$im,0,0,0,0,$w,$h);
        $tmp = sys_get_temp_dir().'/logo_flat_'.md5($rutaPng).'.jpg';
        imagejpeg($bg,$tmp,92);
        imagedestroy($im);
        imagedestroy($bg);
        return $tmp;
      }
    }
  }

  return $jpg; // si no existe, FPDF simplemente no dibuja
}

/* ===== Usuario y permisos ===== */
$usuario_doc = (isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '');
$canVentas   = can('rep_ventas', 'read');
$canEntregas = can('rep_entregas', 'read');

if (!$canVentas && !$canEntregas) {
  header('HTTP/1.1 403 Forbidden');
  $qs = http_build_query(array(
    'm'=>'reportes',
    'a'=>'read',
    'r'=>function_exists('current_role')?current_role():'',
    'u'=>function_exists('current_user')?current_user():''
  ));
  header("Location: acceso_denegado.php?$qs");
  exit;
}

/* ===== Tabs / filtros ===== */
$postedTab = (isset($_POST['active_tab']) ? $_POST['active_tab'] : 'ventas');
if ($postedTab==='ventas' && !$canVentas)     $postedTab = $canEntregas ? 'entregas' : $postedTab;
if ($postedTab==='entregas' && !$canEntregas) $postedTab = $canVentas ? 'ventas' : $postedTab;
$activeTab = $postedTab;

$pv_desde_ventas  = (isset($_POST['desde_ventas']) ? $_POST['desde_ventas'] : '');
$pv_hasta_ventas  = (isset($_POST['hasta_ventas']) ? $_POST['hasta_ventas'] : '');
$pv_usuario_ventas= (isset($_POST['usuario_ventas']) ? $_POST['usuario_ventas'] : '');
$pv_sortf_ventas  = (isset($_POST['sort_field_ventas']) ? $_POST['sort_field_ventas'] : 'fecha');
$pv_sortd_ventas  = (isset($_POST['sort_dir_ventas']) ? $_POST['sort_dir_ventas'] : 'desc');

$pv_desde_entregas   = (isset($_POST['desde_entregas']) ? $_POST['desde_entregas'] : '');
$pv_hasta_entregas   = (isset($_POST['hasta_entregas']) ? $_POST['hasta_entregas'] : '');
$pv_usuario_entregas = (isset($_POST['usuario_entregas']) ? $_POST['usuario_entregas'] : '');
$pv_sortf_entregas   = (isset($_POST['sort_field_entregas']) ? $_POST['sort_field_entregas'] : 'fecha');
$pv_sortd_entregas   = (isset($_POST['sort_dir_entregas']) ? $_POST['sort_dir_entregas'] : 'desc');

/* ===== ORDER BY (producto/cantidad) ===== */
function mapVentasOrder($field, $dir) {
  $fields = array(
    'factura'  => 'v.VENTRECI',
    'producto' => 'Producto',
    'cantidad' => 'Cantidad',
    'fecha'    => 'v.VENTFEVE',
    'usuario'  => 'u.USUANOMB',
    'valor'    => 'v.VENTTOTA'
  );
  $dirs = array('asc'=>'ASC','desc'=>'DESC');
  return array(
    (isset($fields[$field]) ? $fields[$field] : 'v.VENTFEVE'),
    (isset($dirs[strtolower($dir)]) ? $dirs[strtolower($dir)] : 'DESC')
  );
}
function mapEntregasOrder($field, $dir) {
  $fields = array(
    'factura'  => 'v.VENTRECI',
    'producto' => 'Producto',
    'cantidad' => 'Cantidad',
    'fecha'    => 'v.VENTFEEN',
    'usuario'  => 'u.USUANOMB'
  );
  $dirs = array('asc'=>'ASC','desc'=>'DESC');
  return array(
    (isset($fields[$field]) ? $fields[$field] : 'v.VENTFEEN'),
    (isset($dirs[strtolower($dir)]) ? $dirs[strtolower($dir)] : 'DESC')
  );
}

/* ===== Definición de "Producto" (descripción y cantidad) ===== */
$DET_TABLE = 'ventas_det';
$HAS_DET   = tableExists($pdo, $DET_TABLE);

/* Detecta automáticamente el nombre de la columna de cantidad en ventas_det */
$qtyCandidates = array('DETCANT','CANTIDAD','VENCANT','CANT','CANT_UNI','CANTIDAD_UNIDADES');
$DET_QTY_COL = '';
for ($i=0; $i<count($qtyCandidates); $i++){
  if (columnExists($pdo, $DET_TABLE, $qtyCandidates[$i])) { $DET_QTY_COL = $qtyCandidates[$i]; break; }
}
/* Si no existe ninguna, contamos filas (1 por ítem) */
$DET_QTY_EXPR = ($DET_QTY_COL !== '') ? ('vd.`'.$DET_QTY_COL.'`') : '1';

$prodSub = "
  SELECT
    vd.VENTRECI,
    GROUP_CONCAT(DISTINCT p.PRODDESC ORDER BY p.PRODDESC SEPARATOR ' + ') AS PRODUCTO,
    SUM($DET_QTY_EXPR) AS CANTIDAD
  FROM {$DET_TABLE} vd
  JOIN productos p ON p.PRODCODI = vd.PRODCODI
  GROUP BY vd.VENTRECI
";

$baseProdDesc = "(SELECT p2.PRODDESC FROM productos p2 WHERE p2.PRODCODI = v.VENTPROD LIMIT 1)";

if ($HAS_DET) {
  $prodJoin    = "LEFT JOIN ($prodSub) pr ON pr.VENTRECI = v.VENTRECI";
  $prodExpr    = "COALESCE(pr.PRODUCTO, $baseProdDesc, '—')";
  $qtyExpr     = "COALESCE(pr.CANTIDAD, 0)";
} else {
  $prodJoin    = "";
  $prodExpr    = "COALESCE($baseProdDesc, '—')";
  $qtyExpr     = "1";  // sin detalle, contamos 1 por factura
}

/* ===================== EXPORTADOR (PDF o CSV) ===================== */
if (isset($_POST['do_export']) && in_array((isset($_POST['which']) ? $_POST['which'] : ''), array('ventas','entregas'), true)) {

  $which  = $_POST['which'];
  $format = (isset($_POST['format']) && $_POST['format'] === 'pdf') ? 'pdf' : 'excel';
  $now    = date('Ymd_His');

  if ($which==='ventas')   require_perm('rep_ventas', 'read');
  if ($which==='entregas') require_perm('rep_entregas', 'read');

  if ($which === 'ventas') {
    $tmpfld = (isset($_POST['sort_field_ventas']) ? $_POST['sort_field_ventas'] : 'fecha');
    $tmpdir = (isset($_POST['sort_dir_ventas'])   ? $_POST['sort_dir_ventas']   : 'desc');
    list($fld,$dir) = mapVentasOrder($tmpfld,$tmpdir);

    $sql = "
      SELECT
        v.VENTRECI AS Factura,
        $prodExpr    AS Producto,
        $qtyExpr     AS Cantidad,
        DATE_FORMAT(v.VENTFEVE, '%d/%m/%Y %H:%i') AS Fecha,
        u.USUANOMB  AS Usuario,
        v.VENTTOTA  AS Valor
      FROM ventas v
      JOIN usuarios u ON v.VENTUSVE = u.USUADOCU
      $prodJoin
      WHERE DATE(v.VENTFEVE) BETWEEN ? AND ?
    ";

    $params = array(
      (isset($_POST['desde_ventas']) ? $_POST['desde_ventas'] : ''),
      (isset($_POST['hasta_ventas']) ? $_POST['hasta_ventas'] : '')
    );

    if (trim((isset($_POST['usuario_ventas']) ? $_POST['usuario_ventas'] : ''))!=='') {
      $sql .= " AND v.VENTUSVE = ?";
      $params[] = trim($_POST['usuario_ventas']);
    }

    $sql .= " ORDER BY $fld $dir";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $title    = "Reporte de Ventas";
    $file     = "ventas_$now";
    $headers  = array('Factura','Producto','Cantidad','Fecha','Usuario','Valor');
    $subtitle = 'Desde '.(isset($params[0]) ? $params[0] : '').
                ' hasta '.(isset($params[1]) ? $params[1] : '').
                (isset($params[2]) ? (' • Usuario: '.(isset($_POST['usuario_ventas']) ? $_POST['usuario_ventas'] : '')):'');

    /* ==== Subtotales por tipo ==== */
    $totalAguas = 0.0; // AGUA CRUDA + AGUA TRATADA
    $totalSup   = 0.0; // SUPERVISION INSTALACION DE MEDIDORES

    for ($i=0; $i<count($rows); $i++){
      $prod = isset($rows[$i]['Producto']) ? strtoupper($rows[$i]['Producto']) : '';
      $val  = isset($rows[$i]['Valor']) ? (float)$rows[$i]['Valor'] : 0.0;

      if (strpos($prod, 'AGUA CRUDA') !== false || strpos($prod, 'AGUA TRATADA') !== false) {
        $totalAguas += $val;
      }
      if (strpos($prod, 'SUPERVISION INSTALACION DE MEDIDORES') !== false) {
        $totalSup += $val;
      }
    }

  } else {
    $tmpfld = (isset($_POST['sort_field_entregas']) ? $_POST['sort_field_entregas'] : 'fecha');
    $tmpdir = (isset($_POST['sort_dir_entregas'])   ? $_POST['sort_dir_entregas']   : 'desc');
    list($fld,$dir) = mapEntregasOrder($tmpfld,$tmpdir);

    $sql = "
      SELECT
        v.VENTRECI AS Factura,
        $prodExpr    AS Producto,
        $qtyExpr     AS Cantidad,
        DATE_FORMAT(v.VENTFEEN, '%d/%m/%Y %H:%i') AS `Fecha Entrega`,
        u.USUANOMB  AS Usuario
      FROM ventas v
      JOIN usuarios u ON v.VENTUSEN = u.USUADOCU
      $prodJoin
      WHERE DATE(v.VENTFEEN) BETWEEN ? AND ?
    ";

    $params = array(
      (isset($_POST['desde_entregas']) ? $_POST['desde_entregas'] : ''),
      (isset($_POST['hasta_entregas']) ? $_POST['hasta_entregas'] : '')
    );

    if (trim((isset($_POST['usuario_entregas']) ? $_POST['usuario_entregas'] : ''))!=='') {
      $sql .= " AND v.VENTUSEN = ?";
      $params[] = trim($_POST['usuario_entregas']);
    }

    $sql .= " ORDER BY $fld $dir";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $title    = "Reporte de Entregas";
    $file     = "entregas_$now";
    $headers  = array('Factura','Producto','Cantidad','Fecha Entrega','Usuario');
    $subtitle = 'Desde '.(isset($params[0]) ? $params[0] : '').
                ' hasta '.(isset($params[1]) ? $params[1] : '').
                (isset($params[2]) ? (' • Usuario: '.(isset($_POST['usuario_entregas']) ? $_POST['usuario_entregas'] : '')):'');
  }

  if ($format === 'excel') {
    /* ===================== CSV (Excel) =====================
       - BOM UTF-8
       - Separador ; (locale ES)
       - Factura forzada como texto ="...": preserva ceros a la izquierda.
       - Subtotales SIN number_format: Excel los lee como número real.
    */
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$file.'.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    fputcsv($out, $headers, ';');

    foreach ($rows as $r) {
      $line = array();
      for ($i=0; $i<count($headers); $i++){
        $h    = $headers[$i];
        $cell = isset($r[$h]) ? $r[$h] : '';
        if ($h === 'Factura') { $cell = '="'.(string)$cell.'"'; }
        $line[] = $cell;
      }
      fputcsv($out, $line, ';');
    }

    if ($which==='ventas' && in_array('Valor',$headers,true)) {
      // Headers: Factura,Producto,Cantidad,Fecha,Usuario,Valor  (6 columnas)
      fputcsv($out, array('', 'TOTAL AGUA (Cruda + Tratada)', '', '', '', $totalAguas), ';');
      fputcsv($out, array('', 'TOTAL SUPERVISIÓN',            '', '', '', $totalSup),   ';');
    }

    fclose($out);
    exit;

  } else {
    require_once 'fpdf/fpdf.php';

    function enc($s){
      $s=(string)$s;
      $x=@iconv('UTF-8','ISO-8859-1//TRANSLIT',$s);
      return $x===false?$s:$x;
    }

    class PrettyPDF extends FPDF {
      public $title='';
      public $subtitle='';
      public $logoPathResolved='';

      function Header(){
        $this->SetFillColor(31,102,145);
        $this->Rect(0,0,$this->w,20,'F');

        if ($this->logoPathResolved && file_exists($this->logoPathResolved)) {
          $this->Image($this->logoPathResolved, 10, 3.5, 16);
        }

        $this->SetTextColor(255,255,255);
        $this->SetFont('Arial','B',13);
        $this->SetXY(0,4);
        $this->Cell(0,7,enc($this->title),0,1,'C');

        $this->SetFont('Arial','',9);
        $this->Cell(0,6,enc($this->subtitle),0,1,'C');

        $this->Ln(3);
      }

      function Footer(){
        $this->SetY(-15);
        $this->SetDrawColor(230,236,244);
        $this->Line(10,$this->GetY(), $this->w-10, $this->GetY());
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(90,90,90);
        $this->Cell(0,10,enc('Generado: '.date('d/m/Y H:i').' • Página '.$this->PageNo().'/{nb}'),0,0,'R');
      }

      function renderTable($headers, $rows, $extraTotals){
        $usable = $this->w - $this->lMargin - $this->rMargin;

        $weights = array(
          'Factura'=>0.9,
          'Producto'=>2.4,
          'Cantidad'=>1.0,
          'Fecha'=>2.0,
          'Fecha Entrega'=>2.2,
          'Usuario'=>2.0,
          'Valor'=>1.1
        );

        $sum=0;
        for ($i=0; $i<count($headers); $i++){
          $h=$headers[$i];
          $sum += (isset($weights[$h]) ? $weights[$h] : 1);
        }

        $W=array();
        for ($i=0; $i<count($headers); $i++){
          $h=$headers[$i];
          $W[$h] = $usable * ((isset($weights[$h]) ? $weights[$h] : 1) / max($sum,0.01));
        }

        $this->SetFillColor(41,128,185);
        $this->SetTextColor(255,255,255);
        $this->SetDrawColor(220,230,240);
        $this->SetLineWidth(0.3);
        $this->SetFont('Arial','B',9);

        for ($i=0; $i<count($headers); $i++){
          $h=$headers[$i];
          $this->Cell($W[$h],8,enc($h),1,0,'C',true);
        }
        $this->Ln();

        $this->SetFont('Arial','',9);
        $this->SetTextColor(40,40,40);
        $fill=false;
        $total=0;

        for ($rI=0; $rI<count($rows); $rI++){
          $r = $rows[$rI];
          $this->SetFillColor($fill?245:255,$fill?249:255,$fill?253:255);

          for ($i=0; $i<count($headers); $i++){
            $h   = $headers[$i];
            $txt = isset($r[$h]) ? (string)$r[$h] : '';
            $align = ($h==='Valor') ? 'R' : 'L';
            $this->Cell($W[$h],7,enc($txt),1,0,$align,true);
          }

          if (isset($r['Valor'])) {
            $num=(float)$r['Valor'];
            if ($num>0) $total+=$num;
          }

          $this->Ln();
          $fill=!$fill;
        }

        if (in_array('Valor',$headers,true)) {
          $this->SetFont('Arial','B',9);
          $this->SetFillColor(233,247,238);
          $this->SetTextColor(11,107,47);

          $left=0;
          for ($i=0; $i<count($headers); $i++){
            $h=$headers[$i];
            if ($h==='Valor') break;
            $left += $W[$h];
          }

          $this->Cell($left,8,enc('TOTAL'),1,0,'R',true);
          $this->Cell($W['Valor'],8,enc('$'.number_format($total,0,',','.')),1,0,'R',true);
          $this->Ln();

          if (is_array($extraTotals) && count($extraTotals)>0) {
            $this->SetFillColor(246,249,255);
            $this->SetTextColor(31,102,145);

            for ($j=0; $j<count($extraTotals); $j++){
              $lbl = isset($extraTotals[$j]['label']) ? $extraTotals[$j]['label'] : '';
              $val = isset($extraTotals[$j]['value']) ? (float)$extraTotals[$j]['value'] : 0.0;

              $this->Cell($left,7,enc($lbl),1,0,'R',true);
              $this->Cell($W['Valor'],7,enc('$'.number_format($val,0,',','.')),1,0,'R',true);
              $this->Ln();
            }
          }
        }
      }
    }

    if (ob_get_length()) {
      ob_clean();
    }

    $pdf = new PrettyPDF('L','mm','A4');
    $pdf->AliasNbPages();
    $pdf->title = $title;
    $pdf->subtitle = $subtitle;
    $pdf->logoPathResolved = logoSeguroFPDF(__DIR__ . '/img/LogoEMDT.png');
    $pdf->SetMargins(10,25,10);
    $pdf->AddPage();

    $extraTotals = array();
    if ($which==='ventas' && isset($totalAguas) && isset($totalSup)) {
      $extraTotals[] = array('label'=>'TOTAL AGUA (Cruda + Tratada)', 'value'=>$totalAguas);
      $extraTotals[] = array('label'=>'TOTAL SUPERVISIÓN',            'value'=>$totalSup);
    }

    $pdf->renderTable($headers, $rows, $extraTotals);
    $pdf->Output('D', $file.'.pdf');
    exit;
  }
}

/* ===================== CONSULTAS PARA PANTALLA ===================== */
$ventas_rows   = array();
$entregas_rows = array();

/* Ventas */
if (isset($_POST['consultar_ventas']) && $canVentas) {
  require_perm('rep_ventas', 'read');

  list($fld,$dir) = mapVentasOrder($pv_sortf_ventas,$pv_sortd_ventas);

  $sql = "
    SELECT
      v.VENTRECI,
      $prodExpr    AS Producto,
      $qtyExpr     AS Cantidad,
      DATE_FORMAT(v.VENTFEVE, '%d/%m/%Y %H:%i') AS FECHA_FMT,
      u.USUANOMB,
      v.VENTTOTA
    FROM ventas v
    JOIN usuarios u ON v.VENTUSVE = u.USUADOCU
    $prodJoin
    WHERE DATE(v.VENTFEVE) BETWEEN ? AND ?
    ORDER BY $fld $dir
  ";

  $params = array($pv_desde_ventas,$pv_hasta_ventas);
  if (trim($pv_usuario_ventas)!==''){
    $sql = str_replace("ORDER BY","AND v.VENTUSVE = ? ORDER BY",$sql);
    $params[]=$pv_usuario_ventas;
  }

  $stmt=$pdo->prepare($sql);
  $stmt->execute($params);
  $ventas_rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Entregas */
if (isset($_POST['consultar_entregas']) && $canEntregas) {
  require_perm('rep_entregas', 'read');

  list($fld,$dir) = mapEntregasOrder($pv_sortf_entregas,$pv_sortd_entregas);

  $sql = "
    SELECT
      v.VENTRECI,
      $prodExpr    AS Producto,
      $qtyExpr     AS Cantidad,
      DATE_FORMAT(v.VENTFEEN, '%d/%m/%Y %H:%i') AS FECHA_FMT,
      u.USUANOMB
    FROM ventas v
    JOIN usuarios u ON v.VENTUSEN = u.USUADOCU
    $prodJoin
    WHERE DATE(v.VENTFEEN) BETWEEN ? AND ?
    ORDER BY $fld $dir
  ";

  $params = array($pv_desde_entregas,$pv_hasta_entregas);
  if (trim($pv_usuario_entregas)!==''){
    $sql = str_replace("ORDER BY","AND v.VENTUSEN = ? ORDER BY",$sql);
    $params[]=$pv_usuario_entregas;
  }

  $stmt=$pdo->prepare($sql);
  $stmt->execute($params);
  $entregas_rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Módulo de Reportes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --azul:#2980b9;
      --azul-oscuro:#1f6691;
      --celeste:#6dd5fa;
      --texto:#2f3a45;
      --bg:#ffffff;
    }
    body{
      background: var(--bg);
      color: var(--texto);
      font-family: "Segoe UI", Arial, sans-serif;
    }
    /* Header base */
    header{
      background: var(--azul-oscuro);
      color:#fff;
      padding: 14px 24px;
    }
    .container-xxl{ max-width: 1380px; }
    .card{
      overflow: visible;
      border-radius: 14px;
      border:1px solid #e6f0fa;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .nav-pills .nav-link{
      border-radius:9999px;
      color:#fff;
      background:#2d7fb2;
      margin:0 .25rem;
    }
    .nav-pills .nav-link.active{
      background:#6dd5fa;
      color:#0b3850;
      font-weight:700;
    }
    .section-title{
      color:#156aa0;
      font-weight:800;
    }
    .table thead th{
      background:#eaf3fb;
      user-select:none;
      cursor:pointer;
    }
    .table thead th .arrow{
      margin-left:.35rem;
      opacity:.7;
    }
    /* Pill de usuario + dropdown (no rompe diseño) */
    .user-pill{
      margin-left:auto;
      display:flex;
      align-items:center;
      gap:6px;
      font-weight:700;
      font-size:16px;
      color:#fff;
      padding:6px 12px;
      border-radius:9999px;
      background:rgba(255,255,255,.12);
      cursor:pointer;
      position:relative;
    }
    .user-pill:hover{ background:rgba(255,255,255,.18); }
    .user-menu{
      position:absolute;
      right:24px;
      top:56px;
      min-width:210px;
      background:#fff;
      border:1px solid #e6eef8;
      border-radius:10px;
      box-shadow:0 10px 24px rgba(0,0,0,.12);
      padding:6px;
      display:none;
      z-index:50;
    }
    .user-menu.show{ display:block; }
    .user-item{
      display:flex;
      align-items:center;
      gap:8px;
      padding:10px 12px;
      border-radius:8px;
      text-decoration:none;
      color:#0b3850;
      font-weight:600;
    }
    .user-item:hover{ background:#f4f8fd; }

    /* Botones redondos reutilizados */
    .btn-icon{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:42px;
      height:42px;
      border-radius:9999px;
      padding:0;
      box-shadow:0 2px 6px rgba(0,0,0,.08);
    }
  </style>
</head>
<body>

  <!-- Header: botón Volver (izq), título centrado absoluto, usuario (der) -->
  <header class="d-flex align-items-center position-relative">
    <a href="menu_principal.php" class="btn btn-light">
      <i class="bi bi-arrow-left-circle"></i> Volver
    </a>

    <h2 class="m-0" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);margin:0;font-weight:700;">
      Módulo de Reportes
    </h2>

    <div class="user-pill" id="userBtn">
      <i class="bi bi-person-circle"></i>
      <?php echo htmlspecialchars($usuario_doc ? $usuario_doc : ''); ?>
    </div>

    <div class="user-menu" id="userMenu" aria-hidden="true">
      <a href="#" id="openLogout" class="user-item">
        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
      </a>
    </div>
  </header>

  <div class="container-xxl my-4">
    <div class="card p-3">

      <!-- Tabs -->
      <ul class="nav nav-pills justify-content-center mb-3" role="tablist">
        <?php if ($canVentas): ?>
          <li class="nav-item">
            <button class="nav-link <?php echo ($activeTab==='ventas'?'active':''); ?>"
                    data-bs-toggle="pill" data-bs-target="#ventas" type="button">
              Ventas
            </button>
          </li>
        <?php endif; ?>

        <?php if ($canEntregas): ?>
          <li class="nav-item">
            <button class="nav-link <?php echo ($activeTab==='entregas'?'active':''); ?>"
                    data-bs-toggle="pill" data-bs-target="#entregas" type="button">
              Entregas
            </button>
          </li>
        <?php endif; ?>
      </ul>

      <div class="tab-content">

        <!-- ===================== Ventas ===================== -->
        <?php if ($canVentas): ?>
          <div class="tab-pane fade <?php echo ($activeTab==='ventas'?'show active':''); ?>" id="ventas">
            <h5 class="section-title mb-2"><i class="bi bi-cart-check"></i> Reporte de Ventas</h5>

            <form method="post" class="row g-3 align-items-end" id="form_ventas">
              <input type="hidden" name="active_tab" value="ventas">
              <input type="hidden" name="consultar_ventas" value="1">
              <input type="hidden" name="sort_field_ventas" id="sort_field_ventas" value="<?php echo htmlspecialchars($pv_sortf_ventas); ?>">
              <input type="hidden" name="sort_dir_ventas"   id="sort_dir_ventas"   value="<?php echo htmlspecialchars($pv_sortd_ventas); ?>">

              <div class="col-sm-6 col-lg-3">
                <label class="form-label">Desde</label>
                <input type="date" name="desde_ventas" id="desde_ventas" class="form-control" required
                       value="<?php echo htmlspecialchars($pv_desde_ventas); ?>">
              </div>

              <div class="col-sm-6 col-lg-3">
                <label class="form-label">Hasta</label>
                <input type="date" name="hasta_ventas" id="hasta_ventas" class="form-control" required
                       value="<?php echo htmlspecialchars($pv_hasta_ventas); ?>">
              </div>

              <div class="col-sm-6 col-lg-3">
                <label class="form-label">Usuario (doc) <span class="text-muted">(opcional)</span></label>
                <input type="text" name="usuario_ventas" id="usuario_ventas" class="form-control"
                       value="<?php echo htmlspecialchars($pv_usuario_ventas); ?>">
              </div>

              <div class="col-sm-6 col-lg-3 d-flex gap-2 justify-content-start justify-content-lg-end">
                <button type="button" class="btn btn-primary btn-icon" title="Consultar"
                        onclick="document.getElementById('form_ventas').requestSubmit();">
                  <i class="bi bi-search"></i>
                </button>

                <button type="button" class="btn btn-secondary btn-icon" title="Limpiar" onclick="clearTab('ventas')">
                  <i class="bi bi-eraser"></i>
                </button>

                <button type="button" class="btn btn-success btn-icon" title="Descargar" onclick="openDownload('ventas')">
                  <i class="bi bi-download"></i>
                </button>

                <?php if ($canVentas || $canEntregas): ?>
                  <a href="dashboard.php" class="btn btn-info btn-icon" title="Ir al Dashboard">
                    <i class="bi bi-bar-chart-line"></i>
                  </a>
                <?php endif; ?>
              </div>
            </form>

            <?php if (isset($_POST['consultar_ventas'])): ?>
              <?php if ($ventas_rows): ?>
                <div class="table-responsive mt-3">
                  <table class="table table-striped table-bordered align-middle">
                    <thead>
                      <tr>
                        <th onclick="sortBy('ventas','factura')">
                          Factura
                          <?php echo ($pv_sortf_ventas==='factura' ? '<span class="arrow">'.($pv_sortd_ventas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th onclick="sortBy('ventas','producto')">
                          Producto
                          <?php echo ($pv_sortf_ventas==='producto' ? '<span class="arrow">'.($pv_sortd_ventas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th onclick="sortBy('ventas','cantidad')">
                          Cantidad
                          <?php echo ($pv_sortf_ventas==='cantidad' ? '<span class="arrow">'.($pv_sortd_ventas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th onclick="sortBy('ventas','fecha')">
                          Fecha
                          <?php echo ($pv_sortf_ventas==='fecha' ? '<span class="arrow">'.($pv_sortd_ventas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th onclick="sortBy('ventas','usuario')">
                          Usuario
                          <?php echo ($pv_sortf_ventas==='usuario' ? '<span class="arrow">'.($pv_sortd_ventas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th class="text-end" onclick="sortBy('ventas','valor')">
                          Valor
                          <?php echo ($pv_sortf_ventas==='valor' ? '<span class="arrow">'.($pv_sortd_ventas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php for ($i=0; $i<count($ventas_rows); $i++): $r=$ventas_rows[$i]; ?>
                        <tr>
                          <td><?php echo htmlspecialchars($r['VENTRECI']); ?></td>
                          <td><?php echo htmlspecialchars(isset($r['Producto']) ? $r['Producto'] : '—'); ?></td>
                          <td><?php echo htmlspecialchars(isset($r['Cantidad']) ? $r['Cantidad'] : '0'); ?></td>
                          <td><?php echo htmlspecialchars($r['FECHA_FMT']); ?></td>
                          <td><?php echo htmlspecialchars($r['USUANOMB']); ?></td>
                          <td class="text-end"><?php echo number_format((float)$r['VENTTOTA'], 0, ',', '.'); ?></td>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-warning mt-3">No hay resultados.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- ===================== Entregas ===================== -->
        <?php if ($canEntregas): ?>
          <div class="tab-pane fade <?php echo ($activeTab==='entregas'?'show active':''); ?>" id="entregas">
            <h5 class="section-title mb-2"><i class="bi bi-truck"></i> Reporte de Entregas</h5>

            <form method="post" class="row g-3 align-items-end" id="form_entregas">
              <input type="hidden" name="active_tab" value="entregas">
              <input type="hidden" name="consultar_entregas" value="1">
              <input type="hidden" name="sort_field_entregas" id="sort_field_entregas" value="<?php echo htmlspecialchars($pv_sortf_entregas); ?>">
              <input type="hidden" name="sort_dir_entregas"   id="sort_dir_entregas"   value="<?php echo htmlspecialchars($pv_sortd_entregas); ?>">

              <div class="col-sm-6 col-lg-3">
                <label class="form-label">Desde</label>
                <input type="date" name="desde_entregas" id="desde_entregas" class="form-control" required
                       value="<?php echo htmlspecialchars($pv_desde_entregas); ?>">
              </div>

              <div class="col-sm-6 col-lg-3">
                <label class="form-label">Hasta</label>
                <input type="date" name="hasta_entregas" id="hasta_entregas" class="form-control" required
                       value="<?php echo htmlspecialchars($pv_hasta_entregas); ?>">
              </div>

              <div class="col-sm-6 col-lg-3">
                <label class="form-label">Usuario (doc) <span class="text-muted">(opcional)</span></label>
                <input type="text" name="usuario_entregas" id="usuario_entregas" class="form-control"
                       value="<?php echo htmlspecialchars($pv_usuario_entregas); ?>">
              </div>

              <div class="col-sm-6 col-lg-3 d-flex gap-2 justify-content-start justify-content-lg-end">
                <button type="button" class="btn btn-primary btn-icon" title="Consultar"
                        onclick="document.getElementById('form_entregas').requestSubmit();">
                  <i class="bi bi-search"></i>
                </button>

                <button type="button" class="btn btn-secondary btn-icon" title="Limpiar" onclick="clearTab('entregas')">
                  <i class="bi bi-eraser"></i>
                </button>

                <button type="button" class="btn btn-success btn-icon" title="Descargar" onclick="openDownload('entregas')">
                  <i class="bi bi-download"></i>
                </button>

                <?php if ($canVentas || $canEntregas): ?>
                  <a href="dashboard.php" class="btn btn-info btn-icon" title="Ir al Dashboard">
                    <i class="bi bi-bar-chart-line"></i>
                  </a>
                <?php endif; ?>
              </div>
            </form>

            <?php if (isset($_POST['consultar_entregas'])): ?>
              <?php if ($entregas_rows): ?>
                <div class="table-responsive mt-3">
                  <table class="table table-striped table-bordered align-middle">
                    <thead>
                      <tr>
                        <th onclick="sortBy('entregas','factura')">
                          Factura
                          <?php echo ($pv_sortf_entregas==='factura' ? '<span class="arrow">'.($pv_sortd_entregas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th onclick="sortBy('entregas','producto')">
                          Producto
                          <?php echo ($pv_sortf_entregas==='producto' ? '<span class="arrow">'.($pv_sortd_entregas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th onclick="sortBy('entregas','cantidad')">
                          Cantidad
                          <?php echo ($pv_sortf_entregas==='cantidad' ? '<span class="arrow">'.($pv_sortd_entregas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th onclick="sortBy('entregas','fecha')">
                          Fecha Entrega
                          <?php echo ($pv_sortf_entregas==='fecha' ? '<span class="arrow">'.($pv_sortd_entregas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                        <th onclick="sortBy('entregas','usuario')">
                          Usuario
                          <?php echo ($pv_sortf_entregas==='usuario' ? '<span class="arrow">'.($pv_sortd_entregas==='asc'?'↑':'↓').'</span>' : ''); ?>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php for ($i=0; $i<count($entregas_rows); $i++): $r=$entregas_rows[$i]; ?>
                        <tr>
                          <td><?php echo htmlspecialchars($r['VENTRECI']); ?></td>
                          <td><?php echo htmlspecialchars(isset($r['Producto']) ? $r['Producto'] : '—'); ?></td>
                          <td><?php echo htmlspecialchars(isset($r['Cantidad']) ? $r['Cantidad'] : '0'); ?></td>
                          <td><?php echo htmlspecialchars($r['FECHA_FMT']); ?></td>
                          <td><?php echo htmlspecialchars($r['USUANOMB']); ?></td>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-warning mt-3">No hay resultados.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <!-- Modal de descarga -->
  <div class="modal fade" id="downloadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-download"></i> Descargar reporte</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">¿En qué formato deseas descargarlo?</p>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger btn-icon" id="btnExportPDF" title="PDF">
              <i class="bi bi-file-earmark-pdf"></i>
            </button>
            <button type="button" class="btn btn-success btn-icon" id="btnExportExcel" title="Excel (CSV)">
              <i class="bi bi-file-earmark-excel"></i>
            </button>
          </div>
          <small class="text-muted d-block mt-2">Incluye filtros y orden actuales.</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de Cerrar sesión -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header" style="background:#1f6691;color:#fff;">
          <h5 class="modal-title"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">¿Deseas salir del sistema?</div>
        <div class="modal-footer" style="background:#f7fbff;">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <a class="btn btn-danger" href="logout.php">
            <i class="bi bi-door-open"></i> Sí, salir
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Form oculto export -->
  <form id="exportForm" method="post" style="display:none;">
    <input type="hidden" name="do_export" value="1">
    <input type="hidden" name="which"  id="exp_which">
    <input type="hidden" name="format" id="exp_format">

    <!-- Ventas -->
    <input type="hidden" name="desde_ventas"        id="exp_desde_ventas">
    <input type="hidden" name="hasta_ventas"        id="exp_hasta_ventas">
    <input type="hidden" name="usuario_ventas"      id="exp_usuario_ventas">
    <input type="hidden" name="sort_field_ventas"   id="exp_sort_field_ventas">
    <input type="hidden" name="sort_dir_ventas"     id="exp_sort_dir_ventas">

    <!-- Entregas -->
    <input type="hidden" name="desde_entregas"        id="exp_desde_entregas">
    <input type="hidden" name="hasta_entregas"        id="exp_hasta_entregas">
    <input type="hidden" name="usuario_entregas"      id="exp_usuario_entregas">
    <input type="hidden" name="sort_field_entregas"   id="exp_sort_field_entregas">
    <input type="hidden" name="sort_dir_entregas"     id="exp_sort_dir_entregas">
  </form>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    /* Persistencia de filtros */
    var persistMap = {
      'ventas':   ['desde_ventas','hasta_ventas','usuario_ventas'],
      'entregas': ['desde_entregas','hasta_entregas','usuario_entregas']
    };

    function loadPersisted(){
      for (var tab in persistMap){
        var ids = persistMap[tab];
        for (var i=0;i<ids.length;i++){
          var id = ids[i];
          var el = document.getElementById(id);
          if(!el) continue;
          var key = 'reportes_'+tab+'_'+id, saved = localStorage.getItem(key);
          if(saved!==null && el.value==='') el.value=saved;
          el.addEventListener('change', (function(k,elm){
            return function(){ localStorage.setItem(k, elm.value); };
          })(key,el));
        }
      }
    }

    function clearTab(tab){
      var ids = persistMap[tab]||[];
      for (var i=0;i<ids.length;i++){
        var el=document.getElementById(ids[i]);
        if(el){
          el.value='';
          localStorage.removeItem('reportes_'+tab+'_'+ids[i]);
        }
      }
      if (tab==='ventas'){
        document.getElementById('sort_field_ventas').value='fecha';
        document.getElementById('sort_dir_ventas').value='desc';
      }
      if (tab==='entregas'){
        document.getElementById('sort_field_entregas').value='fecha';
        document.getElementById('sort_dir_entregas').value='desc';
      }
    }

    function sortBy(tab, field){
      var fField,fDir,form;
      if (tab==='ventas'){
        fField=sort_field_ventas;
        fDir  =sort_dir_ventas;
        form  =form_ventas;
      } else {
        fField=sort_field_entregas;
        fDir  =sort_dir_entregas;
        form  =form_entregas;
      }

      if (fField.value===field){
        fDir.value = (fDir.value==='asc'?'desc':'asc');
      } else {
        fField.value=field;
        fDir.value='asc';
      }

      var h=document.createElement('input');
      h.type='hidden';
      h.name=(tab==='ventas'?'consultar_ventas':'consultar_entregas');
      h.value='1';
      form.appendChild(h);
      form.submit();
    }

    /* Modal descarga */
    var downloadTab='ventas';
    var modal=new bootstrap.Modal(document.getElementById('downloadModal'));

    function openDownload(tab){
      downloadTab=tab;
      modal.show();
    }

    document.getElementById('btnExportPDF').addEventListener('click', function(){
      submitExport('pdf');
    });
    document.getElementById('btnExportExcel').addEventListener('click', function(){
      submitExport('excel');
    });

    function submitExport(fmt){
      document.getElementById('exp_which').value  = downloadTab;
      document.getElementById('exp_format').value = fmt;

      function copy(a,b){
        var s=document.getElementById(a), d=document.getElementById(b);
        if(s&&d) d.value=s.value;
      }

      if(downloadTab==='ventas'){
        copy('desde_ventas','exp_desde_ventas');
        copy('hasta_ventas','exp_hasta_ventas');
        copy('usuario_ventas','exp_usuario_ventas');
        copy('sort_field_ventas','exp_sort_field_ventas');
        copy('sort_dir_ventas','exp_sort_dir_ventas');
      } else {
        copy('desde_entregas','exp_desde_entregas');
        copy('hasta_entregas','exp_hasta_entregas');
        copy('usuario_entregas','exp_usuario_entregas');
        copy('sort_field_entregas','exp_sort_field_entregas');
        copy('sort_dir_entregas','exp_sort_dir_entregas');
      }

      modal.hide();
      document.getElementById('exportForm').submit();
    }

    /* ==== Menú de usuario + Cerrar sesión ==== */
    (function(){
      var userBtn  = document.getElementById('userBtn');
      var userMenu = document.getElementById('userMenu');
      var openLogout   = document.getElementById('openLogout');
      var logoutModalEl= document.getElementById('logoutModal');
      var logoutModal  = logoutModalEl ? new bootstrap.Modal(logoutModalEl) : null;

      function closeMenu(){
        if(userMenu){
          userMenu.classList.remove('show');
          userMenu.setAttribute('aria-hidden','true');
        }
      }

      function toggleMenu(){
        if(!userMenu) return;
        if (userMenu.classList.contains('show')) closeMenu();
        else {
          userMenu.classList.add('show');
          userMenu.setAttribute('aria-hidden','false');
        }
      }

      if (userBtn) userBtn.addEventListener('click', function(e){
        e.stopPropagation();
        toggleMenu();
      });

      document.addEventListener('click', function(e){
        if (!userMenu) return;
        if (!userMenu.contains(e.target) && !userBtn.contains(e.target)) closeMenu();
      });

      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeMenu();
      });

      if (openLogout){
        openLogout.addEventListener('click', function(e){
          e.preventDefault();
          closeMenu();
          if (logoutModal) logoutModal.show();
          else window.location.href = 'logout.php';
        });
      }
    })();

    document.addEventListener('DOMContentLoaded', loadPersisted);
  </script>
</body>
</html>
