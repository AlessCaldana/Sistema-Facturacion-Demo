<?php
ob_start();
error_reporting(E_ALL & ~E_DEPRECATED);
session_start();

require_once __DIR__ . '/guard.php';
require_perm('generar', 'write');

require __DIR__ . '/conexion.php';
require_once dirname(__DIR__) . '/libs/phpqrcode/qrlib.php';

if (!file_exists(dirname(__DIR__) . '/fpdf/fpdf.php')) { die('No se encuentra fpdf/fpdf.php'); }
require_once dirname(__DIR__) . '/fpdf/fpdf.php';

require_once dirname(__DIR__) . '/barcode/barcode128_53.php';

define('BAR_W_OBJ',  80.0);
define('BAR_H_MM',   13.0);
define('BAR_Q_MOD',  10);
define('BAR_X_MIN',   0.45);
define('BAR_X_MAX',   0.55);

date_default_timezone_set('America/Bogota');

/* ==== Fechas ==== */
$ahora_bogota = '';
try {
  $dtz = new DateTimeZone('America/Bogota');
  $dt  = new DateTime('now', $dtz);
  $ahora_bogota = $dt->format('Y-m-d\TH:i');
} catch (Exception $e) {
  $ahora_bogota = date('Y-m-d\TH:i');
}

/* ===== Helpers ===== */
if (!function_exists('toLatin1')) {
  function toLatin1($s){
    $s = (string)$s;
    if (function_exists('iconv')) {
      $out = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
      if ($out !== false) return $out;
      $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
      if ($out !== false) return $out;
    }
    if (preg_match('//u', $s)) return utf8_decode($s);
    return $s;
  }
}

if (!function_exists('flattenToJpeg')) {
  function flattenToJpeg($srcPath){
    if (!$srcPath || !file_exists($srcPath)) return $srcPath;
    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    if ($ext !== 'png') return $srcPath;
    if (!function_exists('imagecreatefrompng')) return $srcPath;
    $im = @imagecreatefrompng($srcPath); if (!$im) return $srcPath;
    $w = imagesx($im); $h = imagesy($im);
    $bg = imagecreatetruecolor($w,$h);
    $white = imagecolorallocate($bg,255,255,255);
    imagefilledrectangle($bg,0,0,$w,$h,$white);
    imagecopy($bg,$im,0,0,0,0,$w,$h);
    $tmp = sys_get_temp_dir() . '/logo_flat_'.md5($srcPath).'.jpg';
    imagejpeg($bg,$tmp,92);
    imagedestroy($im); imagedestroy($bg);
    return $tmp;
  }
}

if (!function_exists('fmt_dmy')) {
  function fmt_dmy($iso,$with_time=false){
    if (!$iso) return '';
    try {
      $dt=new DateTime($iso,new DateTimeZone('America/Bogota'));
      return $with_time ? $dt->format('d-m-Y H:i') : $dt->format('d-m-Y');
    } catch(Exception $e){ return $iso; }
  }
}

if (!function_exists('luhn_dv')) {
  function luhn_dv($s){
    $sum=0; $alt=false;
    for($i=strlen($s)-1;$i>=0;$i--){
      $n=(int)$s[$i];
      if($alt){ $n*=2; if($n>9)$n-=9; }
      $sum+=$n; $alt=!$alt;
    }
    return (string)((10-($sum%10))%10);
  }
}

/* ====== Logo ====== */
function resolveLogoPath() {
  $candidates = array(
    dirname(__DIR__) . '/img/LogoEMDT.jpg',
    dirname(__DIR__) . '/img/LogoEMD.jpg',
    dirname(__DIR__) . '/img/logoemdt.jpg',
    dirname(__DIR__) . '/img/logoemd.jpg',
    dirname(__DIR__) . '/img/LogoEMDT.jpeg',
    dirname(__DIR__) . '/img/LogoEMD.jpeg',
    dirname(__DIR__) . '/img/logoemdt.jpeg',
    dirname(__DIR__) . '/img/logoemd.jpeg',
    dirname(__DIR__) . '/img/LogoEMDT.png',
    dirname(__DIR__) . '/img/LogoEMD.png',
    dirname(__DIR__) . '/img/logoemdt.png',
    dirname(__DIR__) . '/img/logoemd.png',
  );
  foreach ($candidates as $p) {
    if (file_exists($p) && is_readable($p)) return $p;
  }
  return null;
}
$empresaLogoPath = resolveLogoPath();

/* ===== Firma ===== */
function resolveFirmaPath() {
  $candidates = array(
    dirname(__DIR__) . '/img/Firma_Gerente.png',
    dirname(__DIR__) . '/img/firma_gerente.png',
    dirname(__DIR__) . '/img/Firma_Gerente.jpg',
    dirname(__DIR__) . '/img/firma_gerente.jpg',
    dirname(__DIR__) . '/img/Firma_Gerente.jpeg',
    dirname(__DIR__) . '/img/firma_gerente.jpeg',
  );
  foreach ($candidates as $p) {
    if (file_exists($p) && is_readable($p)) return $p;
  }
  return null;
}

$success        = false;
$errorMsg       = '';
$rutaArchivo    = '';
$usuario_actual = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';
$demo_mode      = (defined('DEMO_PUBLICO') && DEMO_PUBLICO);
$max_facturas   = (defined('DEMO_MAX_FACTURAS') ? (int)DEMO_MAX_FACTURAS : 3);

/* ==== Conexion MySQLi ==== */
$conn = @new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { $errorMsg = "Error de conexion: " . $conn->connect_error; }
if (function_exists('mysqli_set_charset')) { @mysqli_set_charset($conn, 'utf8'); }

/* ==== Agregar columna referencia si no existe ==== */
if (empty($errorMsg)) {
  $checkCol = $conn->query("SHOW COLUMNS FROM `facturas` LIKE 'referencia'");
  if ($checkCol && $checkCol->num_rows === 0) {
    $conn->query("ALTER TABLE `facturas` ADD COLUMN `referencia` VARCHAR(50) NULL DEFAULT NULL");
  }
  // Tambien agregar columna placa y usuario_nombre si no existen
  $checkPlaca = $conn->query("SHOW COLUMNS FROM `facturas` LIKE 'placa'");
  if ($checkPlaca && $checkPlaca->num_rows === 0) {
    $conn->query("ALTER TABLE `facturas` ADD COLUMN `placa` VARCHAR(20) NULL DEFAULT NULL");
  }
  $checkUsua = $conn->query("SHOW COLUMNS FROM `facturas` LIKE 'usuario_nombre'");
  if ($checkUsua && $checkUsua->num_rows === 0) {
    $conn->query("ALTER TABLE `facturas` ADD COLUMN `usuario_nombre` VARCHAR(100) NULL DEFAULT NULL");
  }
}

/* ===== Helpers de negocio ===== */
if (!function_exists('obtenerProductoGeneral')) {
  function obtenerProductoGeneral($conn,$productoId){
    $id = (int)$productoId;
    if ($id <= 0) return null;
    $st = $conn->prepare("SELECT PRODDESC, IFNULL(PRODPREC,0) AS PRODPREC FROM productos WHERE PRODCODI=? LIMIT 1");
    if(!$st) return null;
    $st->bind_param("i",$id);
    if(!$st->execute()){ $st->close(); return null; }
    $st->bind_result($desc,$prec);
    $ok = $st->fetch();
    $st->close();
    if(!$ok) return null;
    return array('desc'=>(string)$desc,'prec'=>(float)$prec);
  }
}

if (!function_exists('tableColsMysqli')) {
  function tableColsMysqli($conn, $table){
    $cols = array();
    $q = $conn->query("SHOW COLUMNS FROM `".$table."`");
    if($q){ while($r = $q->fetch_assoc()){ if(isset($r['Field'])) $cols[] = $r['Field']; } }
    return $cols;
  }
}

if (!function_exists('pickColMysqli')) {
  function pickColMysqli($cols, $candidates){
    foreach($candidates as $c){ if(in_array($c,$cols,true)) return $c; }
    return null;
  }
}

$colsClientes  = tableColsMysqli($conn, 'clientes');
$colsVehiculos = tableColsMysqli($conn, 'vehiculos');
$colsProductos = tableColsMysqli($conn, 'productos');

// Columnas reales detectadas:
// clientes:  id, nombre, documento, email, direccion
// vehiculos: VEHIPLAC (u otra - se detecta dinamicamente)
// productos: PRODCODI, PRODDESC, PRODPREC
$cliColId    = pickColMysqli($colsClientes,  array('id','CLIEDOCU','documento'));
$cliColNom   = pickColMysqli($colsClientes,  array('nombre','CLIENOMB'));
$cliColDir   = pickColMysqli($colsClientes,  array('direccion','CLIEDIRE'));
$cliColEmail = pickColMysqli($colsClientes,  array('email','CLIEMAIL'));
$vehColPlaca = pickColMysqli($colsVehiculos, array('VEHIPLAC','placa'));
$prodColId   = pickColMysqli($colsProductos, array('PRODCODI','id'));
$prodColDesc = pickColMysqli($colsProductos, array('PRODDESC','descripcion','nombre'));
$prodColPrec = pickColMysqli($colsProductos, array('PRODPREC','precio'));

$clientes_opts = array();
if ($cliColId && $cliColNom) {
  $sql = "SELECT `{$cliColId}` AS CID, `{$cliColNom}` AS CNOM FROM clientes ORDER BY `{$cliColNom}`";
  $res = $conn->query($sql);
  if($res){ while($row=$res->fetch_assoc()){ $clientes_opts[]=$row; } }
}

$vehiculos_opts = array();
if ($vehColPlaca) {
  $sql = "SELECT `{$vehColPlaca}` AS VPLACA FROM vehiculos ORDER BY `{$vehColPlaca}`";
  $res = $conn->query($sql);
  if($res){ while($row=$res->fetch_assoc()){ $vehiculos_opts[]=$row; } }
}

$productos_opts = array();
if ($prodColId && $prodColDesc && $prodColPrec) {
  $sql = "SELECT `{$prodColId}` AS PID, `{$prodColDesc}` AS PDESC, IFNULL(`{$prodColPrec}`,0) AS PPREC FROM productos ORDER BY `{$prodColDesc}`";
  $res = $conn->query($sql);
  if($res){ while($row=$res->fetch_assoc()){ $productos_opts[]=$row; } }
}

/* ====== DIBUJO Code128 ====== */
if (!function_exists('pdfDrawCode128HiRes')) {
  function pdfDrawCode128HiRes($pdf,$code,$xMm,$yMm,$maxWidthMm,$targetWidthMm,$center){
    $quietM = (defined('BAR_Q_MOD') ? BAR_Q_MOD : 10);
    $totalModules = c128_total_modules($code) + 2*$quietM;
    $mCalc = $targetWidthMm / $totalModules;
    $module = max( (defined('BAR_X_MIN')?BAR_X_MIN:0.45),
                   min( (defined('BAR_X_MAX')?BAR_X_MAX:0.55), $mCalc) );
    $needMm = $totalModules * $module;
    if ($needMm > $maxWidthMm){
      $module = max( (defined('BAR_X_MIN')?BAR_X_MIN:0.45), $maxWidthMm / $totalModules );
      $needMm = $totalModules * $module;
    }
    $height = (defined('BAR_H_MM') ? BAR_H_MM : 13.0);
    fpdf_barcode128($pdf,$xMm,$yMm,$module,$height,$quietM,$code);
    return array($needMm,$height);
  }
}

/* ==== Clase PDF ==== */
class FacturaPDF extends FPDF {
  public $marginLeft = 10;
  public $marginRight = 10;
  public $labelCliente = 'Cliente';

  function H3($txt){ $this->SetFont('Arial','B',10.2); $this->Cell(0,6,toLatin1($txt),0,1); }

  function Row2Grid($l1,$v1,$l2,$v2,$blockW=98,$labelW=28,$h=5.6){
    $valW = $blockW - $labelW;
    $this->SetFont('Arial','B',8.4); $this->Cell($labelW,$h,toLatin1($l1),1,0);
    $this->SetFont('Arial','',8.4);  $this->Cell($valW,$h,toLatin1($v1),1,0);
    $this->SetFont('Arial','B',8.4); $this->Cell($labelW,$h,toLatin1($l2),1,0);
    $this->SetFont('Arial','',8.4);  $this->Cell($valW,$h,toLatin1($v2),1,1);
  }

  function Money($n){ return '$'.number_format((float)$n,0,',','.'); }

  function TalonPago($y,$subtitulo,$cliente,$factNo,$valor,$codeString){
    $altoEstimado = 40;
    if ($y + $altoEstimado > ($this->GetPageHeight() - 8)) { $this->AddPage(); $y = 12; }
    $this->SetY($y);

    $this->SetFont('Arial','B',8.2);
    $this->Cell(0,4.6,toLatin1('TALON DE PAGO - '.$subtitulo.' (Cortar por la linea punteada)'),0,1);

    $x1 = $this->marginLeft;
    $x2 = $this->GetPageWidth() - $this->marginRight;
    $yL = $this->GetY();
    for($x=$x1;$x<$x2;$x+=3){ $this->Line($x,$yL,$x+1.5,$yL); }
    $this->Ln(1.2);

    $this->Row2Grid($this->labelCliente,$cliente,'No.',$factNo,98,28,5.2);
    $this->SetFont('Arial','B',8.2); $this->Cell(45,5.2,toLatin1(' '),1,0);
    $this->SetFont('Arial','',8.2);  $this->Cell(53,5.2,toLatin1(' '),1,0);
    $this->SetFont('Arial','B',8.2); $this->Cell(30,5.2,toLatin1('Valor a pagar'),1,0);
    $this->SetFont('Arial','',8.2);  $this->Cell(68,5.2,$this->Money($valor),1,1);

    $xBar        = $this->marginLeft + 4.0;
    $yBar        = $this->GetY() + 2.2;
    $usableW     = 105.0;
    $targetWidth = (defined('BAR_W_OBJ') ? BAR_W_OBJ : 80.0);

    // Quitar barcode
    // $wh = pdfDrawCode128HiRes(
    //   $this, $codeString,
    //   $xBar, $yBar,
    //   $usableW, $targetWidth, false
    // );

    // $hmm = $wh ? $wh[1] : (defined('BAR_H_MM') ? BAR_H_MM : 13.0);
    $hmm = (defined('BAR_H_MM') ? BAR_H_MM : 13.0);

    $firmaPath = resolveFirmaPath();
    if ($firmaPath) {
      $firmaSrc = flattenToJpeg($firmaPath);
      // $barWidth = $wh ? (float)$wh[0] : (defined('BAR_W_OBJ') ? (float)BAR_W_OBJ : 80.0);
      $barWidth = (defined('BAR_W_OBJ') ? (float)BAR_W_OBJ : 80.0);
      $padX   = 6.0;
      $xSig   = $xBar + $barWidth + $padX;
      $ySig   = $yBar + 1.0;
      $xMax   = $this->GetPageWidth() - $this->marginRight;
      $maxW   = max(10.0, $xMax - $xSig);
      $sigW   = min(60.0, $maxW);
      $sigH   = 16.0;

      // Quitar firma
      // if ($sigW >= 10.0) {
      //   $this->Image($firmaSrc, $xSig, $ySig, $sigW, $sigH);
      // }
    }

    // Quitar firma y representante legal
    // if (isset($sigW) && $sigW >= 10.0) {
    //   $this->SetDrawColor(180,180,180);
    //   $this->Line($xSig, $ySig + $sigH + 0.8, $xSig + $sigW, $ySig + $sigH + 0.8);
    //   $this->SetFont('Arial','',7.2);
    //   $this->SetTextColor(60,60,60);
    //   $this->SetXY($xSig, $ySig + $sigH + 1.4);
    //   $this->Cell($sigW, 3.4, toLatin1('Representante legal'), 0, 0, 'C');
    // }

    $this->SetY($yBar + $hmm + 1.8);
    $this->SetFont('Arial','',8.2);
    $talonWidth = 95.0;
    $xStart = $this->marginLeft + 17.0;
    $this->SetX($xStart);
    $this->Cell($talonWidth, 3.6, toLatin1($codeString), 0, 1, 'C');

    // Quitar texto "Presente este codigo en la zona de pago."
    // $this->Ln(0.6);
    // $this->SetFont('Arial','I',7.0);
    // $this->SetX($xStart);
    // $this->Cell($talonWidth, 3.0, toLatin1('Presente este codigo en la zona de pago.'), 0, 1, 'C');

    return $this->GetY();
  }
}

function cutLine($pdf,$y){
  $x1 = $pdf->marginLeft;
  $x2 = $pdf->GetPageWidth() - $pdf->marginRight;
  $pdf->SetY($y);
  for($x=$x1;$x<$x2;$x+=3){ $pdf->Line($x,$y,$x+1.5,$y); }
  $pdf->SetFont('Arial','I',7.2);
  $pdf->SetXY($x1,$y+1.6);
  $pdf->Cell(0,4,toLatin1('Cortar por la linea punteada'),0,1,'C');
}

function drawFacturaBloque(
  $pdf,$fact,$valorUnit,$subtotal,$codeString,
  $consultaURL,$empresaLogoPath,$fechaEmisionDMYHI,
  $qr_file,$tituloCantidad,$esSupervision,
  $tituloPrincipal,$labelCliente,
  $yStart=8,$mostrarQR=true
){
  $pdf->labelCliente = $labelCliente;
  $pdf->SetY($yStart);
  $usableW = $pdf->GetPageWidth() - $pdf->marginLeft - $pdf->marginRight;

  $topY = $yStart;
  // Logo arriba a la izquierda como texto
  $pdf->SetXY($pdf->marginLeft, $topY);
  $pdf->SetFont('Arial','B',10);
  $pdf->Cell(50,5,toLatin1('Logo Empresa'),0,0,'L');
  $qrW = 24;
  if ($mostrarQR && file_exists($qr_file)) {
    $qrX = $pdf->GetPageWidth() - $pdf->marginRight - $qrW;
    $qrY = $topY;
    $pdf->Image($qr_file,$qrX,$qrY,$qrW,$qrW);
    if (method_exists($pdf,'Link') && !empty($consultaURL)) {
      $pdf->Link($qrX,$qrY,$qrW,$qrW,$consultaURL);
    }
  }

  $pdf->SetXY($pdf->marginLeft,$topY);
  $pdf->SetFont('Arial','B',11.5);
  $pdf->Cell($usableW,5,toLatin1($tituloPrincipal),0,1,'C');

  $headerAlto = max(18,$qrW+2);
  $pdf->SetY($topY + $headerAlto);

  $pdf->Row2Grid('Factura No.',$fact['FACTRECI'],'Fecha',$fechaEmisionDMYHI,98,28,5.6);
  $pdf->Row2Grid($labelCliente,$fact['CLIENOMB'],'Direccion',$fact['CLIEDIRE'],98,28,5.6);
  $pdf->Row2Grid('Placa vehiculo',$fact['VENTPLAC'],'Conductor',(!empty($fact['VEHINOCO'])?$fact['VEHINOCO']:'-'),98,28,5.6);

  $pdf->Ln(0.6);
  $pdf->H3('LIQUIDACION DEL SERVICIO');
  $pdf->SetFont('Arial','B',8.4);
  $pdf->Cell(100,6,toLatin1('Concepto'),1,0,'C');
  $pdf->Cell(36,6,toLatin1($tituloCantidad),1,0,'C');
  $pdf->Cell(30,6,toLatin1('V. Unitario'),1,0,'C');
  $pdf->Cell(30,6,toLatin1('Subtotal'),1,1,'C');

  $pdf->SetFont('Arial','',8.2);
  $pdf->Cell(100,6,toLatin1($fact['PRODDESC']),1,0);
  $pdf->Cell(36,6,toLatin1((int)$fact['VENTCANT']),1,0,'R');
  $pdf->Cell(30,6,toLatin1($pdf->Money($valorUnit)),1,0,'R');
  $pdf->Cell(30,6,toLatin1($pdf->Money($subtotal)),1,1,'R');

  $pdf->Ln(0.6);
  $pdf->SetFont('Arial','',7.6);
  $nota = "Esta factura prestara merito ejecutivo de acuerdo con las normas de Derecho Civil y Comercial. Ley 142 de 1994. Art. 130 (mod. Art. 18 de la Ley 689 de 2001).";
  $pdf->MultiCell(0,3.6,toLatin1($nota));

  $yTalon = $pdf->GetY() + 1.6;
  $pdf->TalonPago($yTalon,'Corresponsales',$fact['CLIENOMB'],$fact['FACTRECI'],$subtotal,$codeString);

  return $pdf->GetY();
}

/* ==== POST ==== */
if ($_SERVER['REQUEST_METHOD']==='POST' && empty($errorMsg)) {

  $client_id = isset($_POST['VENTCLIE']) ? (int)$_POST['VENTCLIE'] : 0;
  $vehiculo  = isset($_POST['VENTPLAC']) ? trim($_POST['VENTPLAC']) : '';
  $producto  = isset($_POST['VENTPROD']) ? (int)$_POST['VENTPROD'] : 0;
  $cantidad  = (int)(isset($_POST['VENTCANT']) ? $_POST['VENTCANT'] : 0);
  $fecha     = isset($_POST['VENTFEVE']) ? $_POST['VENTFEVE'] : $ahora_bogota;
  $usua      = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';

  // Obtener usuario_id desde sesion o buscar en BD
  $usuario_id = null;
  if (isset($_SESSION['usuario_id'])) {
    $usuario_id = (int)$_SESSION['usuario_id'];
  } else {
    // Intentar buscar el id del usuario por nombre en la tabla usuarios
    $stU = $conn->prepare("SELECT id FROM usuarios WHERE usuario=? OR nombre=? LIMIT 1");
    if ($stU) {
      $stU->bind_param("ss", $usua, $usua);
      $stU->execute();
      $stU->bind_result($uid);
      if ($stU->fetch()) $usuario_id = (int)$uid;
      $stU->close();
    }
    // Si no se encuentra o la tabla no existe, se deja NULL
  }

  if ($demo_mode) {
    $qDemo = $conn->query("SELECT COUNT(*) AS c FROM facturas");
    $rowDemo = $qDemo ? $qDemo->fetch_assoc() : array('c'=>0);
    if ((int)$rowDemo['c'] >= $max_facturas) {
      $errorMsg = "Limite demo alcanzado: maximo ".$max_facturas." facturas.";
    }
  }

  if (!$client_id || !$producto || $cantidad < 1) {
    $errorMsg = "Datos incompletos. Verifique cliente, producto y cantidad.";
  }

  $valor_unit = 0;
  $prodNombre = '';

  if (!$errorMsg) {
    $prodInfo = obtenerProductoGeneral($conn, $producto);
    if (!$prodInfo) {
      $errorMsg = "Producto no valido o sin precio configurado.";
    } else {
      $prodNombre = $prodInfo['desc'];
      $valor_unit = (float)$prodInfo['prec'];
      if ($valor_unit <= 0) $errorMsg = "Producto no valido o sin precio configurado.";
    }
  }

  if (!$errorMsg && !$vehiculo) {
    $errorMsg = "Seleccione un vehiculo valido.";
  }

  if (!$errorMsg) {
    $total = $valor_unit * $cantidad;

    $conn->autocommit(false);
    $conn->query("START TRANSACTION");

    try {

      /* ===================================================
         1. INSERT en facturas
            Columnas reales: id, cliente_id, usuario_id,
                             fecha, total, estado,
                             referencia (nueva), placa (nueva),
                             usuario_nombre (nueva)
         =================================================== */
      $q_fact = "INSERT INTO facturas
                   (cliente_id, usuario_id, fecha, total, estado, placa, usuario_nombre)
                 VALUES (?, ?, ?, ?, 'PENDIENTE', ?, ?)";
      $st_fact = $conn->prepare($q_fact);
      if (!$st_fact) {
        throw new Exception("Error al preparar facturas: " . $conn->error);
      }
      $st_fact->bind_param("iidsss",
        $client_id,
        $usuario_id,
        $fecha,
        $total,
        $vehiculo,
        $usua
      );
      if (!$st_fact->execute()) {
        throw new Exception("Error al crear la factura: " . $st_fact->error);
      }
      $id_factura = $st_fact->insert_id;
      $st_fact->close();

      /* ===================================================
         2. INSERT en ventas
            Columnas reales: id, factura_id, producto_id,
                             cantidad, precio_unitario, subtotal
         =================================================== */
      $q_venta = "INSERT INTO ventas
                    (factura_id, producto_id, cantidad, precio_unitario, subtotal)
                  VALUES (?, ?, ?, ?, ?)";
      $st_venta = $conn->prepare($q_venta);
      if (!$st_venta) {
        throw new Exception("Error al preparar ventas: " . $conn->error);
      }
      $st_venta->bind_param("iiidd",
        $id_factura,
        $producto,
        $cantidad,
        $valor_unit,
        $total
      );
      if (!$st_venta->execute()) {
        throw new Exception("Error al registrar venta: " . $st_venta->error);
      }
      $st_venta->close();

      /* ===================================================
         3. Obtener datos completos para el PDF
         =================================================== */
      // Construir query con columnas reales detectadas dinamicamente
      $col_cli_id    = $cliColId    ?: 'id';
      $col_cli_nom   = $cliColNom   ?: 'nombre';
      $col_cli_dir   = $cliColDir   ?: 'direccion';
      $col_cli_email = $cliColEmail ?: 'email';

      $sqlInfo = "
        SELECT
          f.id                          AS FACTRECI,
          f.fecha                       AS FACTFECH,
          f.total                       AS FACTVALO,
          c.`{$col_cli_nom}`            AS CLIENOMB,
          IFNULL(c.`{$col_cli_dir}`,'') AS CLIEDIRE,
          IFNULL(c.`{$col_cli_email}`,'') AS CLIEMAIL,
          IFNULL(f.placa,'')            AS VENTPLAC,
          v.cantidad                    AS VENTCANT,
          f.fecha                       AS VENTFEVE,
          p.PRODDESC,
          NULL                          AS VEHINOCO
        FROM facturas f
        JOIN clientes  c ON c.`{$col_cli_id}` = f.cliente_id
        JOIN ventas    v ON v.factura_id = f.id
        JOIN productos p ON p.PRODCODI   = v.producto_id
        WHERE f.id = ?
        LIMIT 1
      ";

      $qi = $conn->prepare($sqlInfo);
      if (!$qi) {
        throw new Exception("Error preparando consulta de informacion: " . $conn->error);
      }
      $qi->bind_param("i", $id_factura);
      if (!$qi->execute()) {
        throw new Exception("No se pudo obtener informacion de la factura.");
      }
      $qi->bind_result(
        $FACTRECI, $FACTFECH, $FACTVALO,
        $CLIENOMB, $CLIEDIRE, $CLIEEMAIL,
        $VENTPLAC, $VENTCANT, $VENTFEVE,
        $PRODDESC, $VEHINOCO
      );
      $hasInfo = $qi->fetch();
      $qi->close();
      if (!$hasInfo) {
        throw new Exception("No se encontraron datos de la factura.");
      }

      $fact = array(
        'FACTRECI' => $FACTRECI,
        'FACTFECH' => $FACTFECH,
        'FACTVALO' => $FACTVALO,
        'CLIENOMB' => $CLIENOMB,
        'CLIEDIRE' => $CLIEDIRE,
        'CLIEMAIL' => $CLIEEMAIL,
        'VENTPLAC' => $VENTPLAC,
        'VENTCANT' => $VENTCANT,
        'VENTFEVE' => $VENTFEVE,
        'PRODDESC' => $PRODDESC,
        'VEHINOCO' => $VEHINOCO,
      );

      $mt3       = (int)$fact['VENTCANT'];
      $valorUnit = (float)$valor_unit;
      $subtotal  = $valorUnit * $mt3;
      $fechaEmisionDMYHI = date('d-m-Y H:i');

      /* ===================================================
         4. Generar codigo de barras (cadena numerica)
         =================================================== */
      $serie   = '01';
      $factN   = str_pad((string)$fact['FACTRECI'], 8, '0', STR_PAD_LEFT);
      $fechaYmd= (new DateTime($fact['FACTFECH']))->format('Ymd');
      $valor10 = str_pad((string)(int)round($subtotal), 10, '0', STR_PAD_LEFT);
      $cli10   = str_pad(preg_replace('/\D/', '', (string)$client_id), 10, '0', STR_PAD_LEFT);
      $base    = $serie . $factN . $fechaYmd . $valor10 . $cli10;
      $dv      = luhn_dv($base);
      $codeString = '0100000006-00011130000000000100000000013';

      /* Guardar referencia en facturas */
      $stRef = $conn->prepare("UPDATE facturas SET referencia=? WHERE id=?");
      if ($stRef) {
        $stRef->bind_param("si", $codeString, $id_factura);
        $stRef->execute();
        $stRef->close();
      }

      /* ===================================================
         5. QR
         =================================================== */
      $host_srv  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
      $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
      $consultaURL = $scheme . '://' . $host_srv . $scriptDir . '/consultar_factura.php?n=' . urlencode($fact['FACTRECI']);

      if (!is_dir(dirname(__DIR__).'/qrcodes')) {
        @mkdir(dirname(__DIR__).'/qrcodes', 0775, true);
      }
      $qr_file = dirname(__DIR__).'/qrcodes/qr_fact_'.$fact['FACTRECI'].'.png';
      QRcode::png($consultaURL, $qr_file, QR_ECLEVEL_M, 4, 2);

      /* ===================================================
         6. Generar PDF
         =================================================== */
      $pdf = new FacturaPDF('P', 'mm', 'Letter');
      $pdf->SetMargins(10, 6, 10);
      $pdf->AddPage();
      $pdf->marginLeft  = 10;
      $pdf->marginRight = 10;

      $tituloCant  = 'Cantidad';
      $tituloPrin  = 'FACTURA DE SERVICIO';
      $labelCliente = 'Cliente';
      $isSuperPDF  = false;

      drawFacturaBloque(
        $pdf, $fact, $valorUnit, $subtotal, $codeString,
        $consultaURL, $empresaLogoPath, $fechaEmisionDMYHI,
        $qr_file, $tituloCant, $isSuperPDF, $tituloPrin,
        $labelCliente, 8, true
      );

      $lineY  = 139.7;
      cutLine($pdf, $lineY);
      $start2 = $lineY + 4.8;

      drawFacturaBloque(
        $pdf, $fact, $valorUnit, $subtotal, $codeString,
        $consultaURL, $empresaLogoPath, $fechaEmisionDMYHI,
        $qr_file, $tituloCant, $isSuperPDF, $tituloPrin,
        $labelCliente, $start2, true
      );

      if (!is_dir(__DIR__.'/facturas_pdf')) {
        @mkdir(__DIR__.'/facturas_pdf', 0775, true);
      }
      $dirBase = dirname(__DIR__);
      $rutaRelativa = 'facturas_pdf/factura_' . $fact['FACTRECI'] . '.pdf';
      $pdf->Output('F', $dirBase . '/' . $rutaRelativa);
      $rutaArchivo = '/' . basename($dirBase) . '/' . $rutaRelativa;

      if (file_exists($qr_file)) { @unlink($qr_file); }

      $conn->commit();
      $conn->autocommit(true);
      $success = true;

    } catch (Exception $e) {
      $conn->rollback();
      $conn->autocommit(true);
      $errorMsg = $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Generar Factura</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
  html,body{margin:0 !important;padding:0 !important;}
  header,.topbar{margin-top:0 !important;}
:root{
  --azul-oscuro:#1f6691; --azul:#2980b9; --texto:#333; --celeste:#6dd5fa;
  --txt:#0b3850; --danger:#d9534f; --danger-2:#b52b27;
}
body{ background:#ffffff; color:var(--texto); display:flex; min-height:100vh; flex-direction:column; }
header{
  background:linear-gradient(135deg,var(--azul-oscuro),var(--azul)); color:#fff; padding:12px 24px;
  display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:10px;
  box-shadow:0 8px 18px rgba(13,53,79,.18);
}
header h2{ margin:0; font-size:1.6rem; text-align:center; font-weight:900; }
.btn-volver{ display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:#fff; border:1px solid rgba(255,255,255,.40); border-radius:10px; padding:8px 12px; font-weight:800; background:rgba(255,255,255,.08); transition:.2s ease; }
.btn-volver:hover{ color:#fff; background:rgba(255,255,255,.16); border-color:rgba(255,255,255,.55);}

.user-pill{
  justify-self:end; display:flex; align-items:center; gap:8px; padding:6px 12px;
  border-radius:9999px; background:rgba(255,255,255,.10); color:#fff; font-weight:700;
  cursor:pointer; position:relative;
}
.user-menu{
  display:none; position:absolute; right:0; top:110%; min-width:200px;
  background:#fff; color:var(--txt); border:1px solid #e5e7eb; border-radius:12px; padding:6px;
  box-shadow:0 12px 28px rgba(0,0,0,.2); z-index:9999;
}
.user-item{
  display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:8px;
  font-weight:600; text-decoration:none; color:var(--danger);
}
.user-item:hover{ background:#ffe5e5; color:var(--danger-2); }

.card-glass{ background:#fff; border:1px solid #e9f1f8; border-radius:14px; box-shadow:0 8px 18px rgba(0,0,0,.06); }
.btn-primary{ background: var(--azul); border-color: var(--azul); }
.btn-primary:hover{ background: var(--celeste); color: var(--texto); border-color: var(--celeste); }
label{ font-weight:600; }
.readonly-box{ background:#f7fbff; }
main{ flex:1; }
footer{ background: var(--azul-oscuro); color:#fff; text-align:center; padding:10px; }
.modal-header.grad{ background:linear-gradient(135deg,#ffefef,#ffe5e5); border-bottom:1px solid #f3c7c7; }
.modal-footer.soft{ background:#fafbfd; border-top:1px solid #eef1f5; }
</style>
</head>
<body>

<header>
  <a href="menu_principal.php" class="btn-volver"><i class="bi bi-arrow-left-circle"></i> Volver</a>
  <h2>Generar Factura</h2>

  <div class="user-pill" id="userBtn">
    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_actual); ?>
    <div class="user-menu" id="userMenu">
      <a href="#" id="openLogout" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesion</a>
    </div>
  </div>
</header>

<main>
  <div class="container my-4">
    <?php if ($demo_mode): ?>
      <div class="alert alert-warning">ENTORNO DEMO: maximo <?php echo (int)$max_facturas; ?> facturas.</div>
    <?php endif; ?>

    <div class="card card-glass p-3">
      <form method="POST" class="row g-3" autocomplete="off" id="formFactura">

        <div class="col-md-6">
          <label class="form-label">Cliente</label>
          <select name="VENTCLIE" class="form-select" required>
            <option value="">Seleccione...</option>
            <?php foreach($clientes_opts as $row): ?>
              <option value="<?php echo htmlspecialchars($row['CID']); ?>"><?php echo htmlspecialchars($row['CNOM']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Vehiculo</label>
          <select name="VENTPLAC" id="VENTPLAC_SELECT" class="form-select" required>
            <option value="">Seleccione...</option>
            <?php foreach($vehiculos_opts as $row): ?>
              <option value="<?php echo htmlspecialchars($row['VPLACA']); ?>"><?php echo htmlspecialchars($row['VPLACA']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Producto</label>
          <select name="VENTPROD" id="VENTPROD" class="form-select" required>
            <option value="">Seleccione...</option>
            <?php foreach($productos_opts as $row): ?>
              <option value="<?php echo htmlspecialchars($row['PID']); ?>"
                      data-precio="<?php echo htmlspecialchars($row['PPREC']); ?>">
                <?php echo htmlspecialchars($row['PDESC']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Cantidad</label>
          <input type="number" name="VENTCANT" id="VENTCANT" min="1" step="1" class="form-control" required value="1">
        </div>

        <div class="col-md-3">
          <label class="form-label">Total</label>
          <input type="text" id="VAL_TOTAL" class="form-control readonly-box" readonly>
        </div>

        <div class="col-md-3">
          <label class="form-label">Fecha (Bogota)</label>
          <input type="datetime-local" name="VENTFEVE" class="form-control"
                 value="<?php echo htmlspecialchars($ahora_bogota); ?>" readonly>
        </div>

        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary" id="btnSubmit">
            <i class="bi bi-file-earmark-plus"></i> Generar Factura
          </button>
          <a href="generar_factura.php" class="btn btn-secondary">
            <i class="bi bi-arrow-counterclockwise"></i> Limpiar
          </a>
        </div>
      </form>
    </div>
  </div>
</main>

<footer>&copy; 2025 - Sistema de Facturacion</footer>

<!-- Modal Exito -->
<div class="modal fade" id="modalExito" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-success">
    <div class="modal-header bg-success text-white">
      <h5 class="modal-title"><i class="bi bi-check-circle-fill"></i> Factura generada</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p>La factura fue generada correctamente.</p>
      <?php if (!empty($rutaArchivo)): ?>
        <a href="<?php echo htmlspecialchars($rutaArchivo); ?>" target="_blank" class="btn btn-outline-success">
          <i class="bi bi-box-arrow-up-right"></i> Ver PDF
        </a>
      <?php endif; ?>
      <a href="generar_factura.php" class="btn btn-success ms-2"><i class="bi bi-plus-circle"></i> Nueva factura</a>
    </div>
  </div></div>
</div>

<!-- Modal Error -->
<div class="modal fade" id="modalError" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-danger">
    <div class="modal-header bg-danger text-white">
      <h5 class="modal-title"><i class="bi bi-x-circle-fill"></i> Error</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body"><p class="mb-0"><?php echo htmlspecialchars($errorMsg); ?></p></div>
  </div></div>
</div>

<!-- Modal Logout -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header grad">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Confirmar cierre de sesion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Seguro que deseas cerrar tu sesion?</div>
      <div class="modal-footer soft">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Si, salir</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var fmt = new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0});

function precioProductoSeleccionado(){
  var p = document.getElementById('VENTPROD');
  if (!p || !p.options[p.selectedIndex]) return 0;
  var v = parseFloat(p.options[p.selectedIndex].getAttribute('data-precio') || '0');
  return isNaN(v) ? 0 : v;
}

function updateTotal(){
  var c = Math.max(0, parseInt(document.getElementById('VENTCANT').value || '0', 10));
  var u = precioProductoSeleccionado();
  document.getElementById('VAL_TOTAL').value = fmt.format(u * c);
}

document.addEventListener('DOMContentLoaded', function(){

  // Dropdown usuario
  var btn  = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if (btn && menu) {
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      menu.style.display = (menu.style.display === 'block' ? 'none' : 'block');
    });
    document.addEventListener('click', function(e){
      if (!btn.contains(e.target)) menu.style.display = 'none';
    }, false);
  }

  // Logout modal
  var openLogout = document.getElementById('openLogout');
  if (openLogout) {
    openLogout.addEventListener('click', function(e){
      e.preventDefault();
      new bootstrap.Modal(document.getElementById('logoutModal')).show();
    }, false);
  }

  // Total
  updateTotal();
  document.getElementById('VENTPROD').addEventListener('change', updateTotal);
  document.getElementById('VENTCANT').addEventListener('input', updateTotal);

  // Evitar doble submit
  var f    = document.getElementById('formFactura');
  var btnS = document.getElementById('btnSubmit');
  f.addEventListener('submit', function(e){
    if (btnS.disabled){ e.preventDefault(); return; }
    btnS.disabled = true;
    btnS.innerText = 'Generando...';
  });

  <?php if ($success): ?>
  new bootstrap.Modal(document.getElementById('modalExito')).show();
  <?php elseif (!$success && !empty($errorMsg)): ?>
  new bootstrap.Modal(document.getElementById('modalError')).show();
  <?php endif; ?>
});
</script>
</body>
</html>
