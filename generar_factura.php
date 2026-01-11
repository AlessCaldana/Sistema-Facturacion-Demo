<?php
ob_start();
error_reporting(E_ALL & ~E_DEPRECATED);
session_start();

require_once __DIR__ . '/guard.php';
require_perm('generar', 'write');

require __DIR__ . '/conexion.php';
require_once __DIR__ . '/libs/phpqrcode/qrlib.php';

if (!file_exists(__DIR__ . '/fpdf/fpdf.php')) { die('No se encuentra fpdf/fpdf.php'); }
require_once __DIR__ . '/fpdf/fpdf.php';

/* ====== Barcode 5.3 (vector, SIN Composer) ====== */
require_once __DIR__ . '/barcode/barcode128_53.php';

/* ====== AJUSTE “IMAGEN 2” (tamaño y posición) ======
   - Ancho visible: 80 mm (compacto)
   - Alto barras:   13 mm (más bajo)
   - Quiet zone:    10 módulos
   - Alineado a la izquierda bajo el talón (no centrado)
====================================================== */
define('BAR_W_OBJ',  80.0); // ancho final que se ve (mm)
define('BAR_H_MM',   13.0); // altura barras (mm)
define('BAR_Q_MOD',  10);   // quiet zone (módulos)
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

/* ===== Helpers de texto/códigos ===== */
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

/* ====== Logo: detectar JPG/JPEG/PNG ====== */
function resolveLogoPath() {
  $candidates = array(
    __DIR__ . '/img/LogoEMDT.jpg',
    __DIR__ . '/img/LogoEMD.jpg',
    __DIR__ . '/img/logoemdt.jpg',
    __DIR__ . '/img/logoemd.jpg',
    __DIR__ . '/img/LogoEMDT.jpeg',
    __DIR__ . '/img/LogoEMD.jpeg',
    __DIR__ . '/img/logoemdt.jpeg',
    __DIR__ . '/img/logoemd.jpeg',
    __DIR__ . '/img/LogoEMDT.png',
    __DIR__ . '/img/LogoEMD.png',
    __DIR__ . '/img/logoemdt.png',
    __DIR__ . '/img/logoemd.png',
  );
  foreach ($candidates as $p) {
    if (file_exists($p) && is_readable($p)) return $p;
  }
  return null;
}
$empresaLogoPath = resolveLogoPath();

/* ===== Firma del gerente: detectar ruta/extension y aplanar si es PNG ===== */
function resolveFirmaPath() {
  $candidates = array(
    __DIR__ . '/img/Firma_Gerente.png',
    __DIR__ . '/img/firma_gerente.png',
    __DIR__ . '/img/Firma_Gerente.jpg',
    __DIR__ . '/img/firma_gerente.jpg',
    __DIR__ . '/img/Firma_Gerente.jpeg',
    __DIR__ . '/img/firma_gerente.jpeg',
    __DIR__ . '/img/Firma_Gerente',
    __DIR__ . '/img/firma_gerente',
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

/* ==== Conexión MySQLi ==== */
$conn = @new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { $errorMsg = "Error de conexión: " . $conn->connect_error; }
if (function_exists('mysqli_set_charset')) { @mysqli_set_charset($conn, 'utf8'); }

/* ===== Helpers de negocio ===== */
if (!function_exists('precioFijoProducto')) {
  function precioFijoProducto($conn,$productoId){
    $desc=''; $st=$conn->prepare("SELECT PRODDESC FROM productos WHERE PRODCODI=?");
    if(!$st) return 0;
    $st->bind_param("i",$productoId);
    if(!$st->execute()){ $st->close(); return 0; }
    $st->bind_result($desc); $ok=$st->fetch(); $st->close();
    if(!$ok || !isset($desc)) return 0;
    $n = strtoupper(trim($desc));
    if (strpos($n,'AGUA CRUDA')!==false)   return 10000;
    if (strpos($n,'AGUA TRATADA')!==false) return 15000;
    if ($n==='SUPERVISION INSTALACION DE MEDIDORES') return 43710;
    return 0;
  }
}
if (!function_exists('unidadProducto')) {
  function unidadProducto($nombre){
    return (strtoupper(trim($nombre))==='SUPERVISION INSTALACION DE MEDIDORES')?'unidades':'MT³';
  }
}
if (!function_exists('esSupervision')) {
  function esSupervision($nombre){
    return (strtoupper(trim($nombre))==='SUPERVISION INSTALACION DE MEDIDORES');
  }
}

/* ====== DIBUJO Code128 (con ancho objetivo y alineado izquierda) ====== */
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

    // Título talón + línea punteada
    $this->SetFont('Arial','B',8.2);
    $this->Cell(0,4.6,toLatin1('TALÓN DE PAGO - '.$subtitulo.' (Cortar por la línea punteada)'),0,1);

    $x1 = $this->marginLeft;
    $x2 = $this->GetPageWidth() - $this->marginRight;
    $yL = $this->GetY();
    for($x=$x1;$x<$x2;$x+=3){ $this->Line($x,$yL,$x+1.5,$yL); }
    $this->Ln(1.2);

    // Fila resumen
    $this->Row2Grid($this->labelCliente,$cliente,'No.',$factNo,98,28,5.2);
    $this->SetFont('Arial','B',8.2); $this->Cell(45,5.2,toLatin1(' '),1,0);
    $this->SetFont('Arial','',8.2);  $this->Cell(53,5.2,toLatin1(' '),1,0);
    $this->SetFont('Arial','B',8.2); $this->Cell(30,5.2,toLatin1('Valor a pagar'),1,0);
    $this->SetFont('Arial','',8.2);  $this->Cell(68,5.2,$this->Money($valor),1,1);

    /* === Código de barras === */
    $xBar        = $this->marginLeft + 4.0;
    $yBar        = $this->GetY() + 2.2;
    $usableW     = 105.0;
    $targetWidth = (defined('BAR_W_OBJ') ? BAR_W_OBJ : 80.0);

    $wh = pdfDrawCode128HiRes(
      $this, $codeString,
      $xBar, $yBar,
      $usableW, $targetWidth, false
    );

    $hmm = $wh ? $wh[1] : (defined('BAR_H_MM') ? BAR_H_MM : 13.0);

    /* === Firma del gerente a la derecha del código (sin mover el código) === */
    $firmaPath = resolveFirmaPath();
    if ($firmaPath) {
      $firmaSrc = flattenToJpeg($firmaPath);
      $barWidth = $wh ? (float)$wh[0] : (defined('BAR_W_OBJ') ? (float)BAR_W_OBJ : 80.0);
      $padX   = 6.0;
      $xSig   = $xBar + $barWidth + $padX;
      $ySig   = $yBar + 1.0;
      $xMax   = $this->GetPageWidth() - $this->marginRight;
      $maxW   = max(10.0, $xMax - $xSig);
      $sigW   = min(60.0, $maxW);
      $sigH   = 16.0; // alto fijo, armoniza con las barras

      if ($sigW >= 10.0) {
        $this->Image($firmaSrc, $xSig, $ySig, $sigW, $sigH);
      }
    }

    /* — Texto bajo la firma: “Representante legal” — */
    if (isset($sigW) && $sigW >= 10.0) {
      // (opcional) una línea fina encima del texto
      $this->SetDrawColor(180,180,180);
      $this->Line($xSig, $ySig + $sigH + 0.8, $xSig + $sigW, $ySig + $sigH + 0.8);

      // etiqueta centrada
      $this->SetFont('Arial','',7.2);
      $this->SetTextColor(60,60,60);
      $this->SetXY($xSig, $ySig + $sigH + 1.4);
      $this->Cell($sigW, 3.4, toLatin1('Representante legal'), 0, 0, 'C');
    }

    /* === Texto bajo código === */
    $this->SetY($yBar + $hmm + 1.8);
    $this->SetFont('Arial','',8.2);
    $talonWidth = 95.0;
    $xStart = $this->marginLeft + 17.0;
    $this->SetX($xStart);
    $this->Cell($talonWidth, 3.6, toLatin1($codeString), 0, 1, 'C');

    $this->Ln(0.6);
    $this->SetFont('Arial','I',7.0);
    $this->SetX($xStart);
    $this->Cell($talonWidth, 3.0, toLatin1('Presente este código en la zona de pago.'), 0, 1, 'C');

    

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
  $pdf->Cell(0,4,toLatin1('Cortar por la línea punteada'),0,1,'C');
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
  if ($empresaLogoPath && file_exists($empresaLogoPath)) {
    $logoSrc = flattenToJpeg($empresaLogoPath);
    $pdf->Image($logoSrc,$pdf->marginLeft,$topY,20);
  }
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
  $pdf->Row2Grid($labelCliente,$fact['CLIENOMB'],'Dirección',$fact['CLIEDIRE'],98,28,5.6);

  if (!$esSupervision) {
    $pdf->Row2Grid('Placa vehículo',$fact['VENTPLAC'],'Conductor',(!empty($fact['VEHINOCO'])?$fact['VEHINOCO']:'-'),98,28,5.6);
  }

  $pdf->Ln(0.6);
  $pdf->H3('LIQUIDACIÓN DEL SERVICIO');
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
  $nota="Esta factura prestará mérito ejecutivo de acuerdo con las normas de Derecho Civil y Comercial. Ley 142 de 1994. Art. 130 (mod. Art. 18 de la Ley 689 de 2001).";
  $pdf->MultiCell(0,3.6,toLatin1($nota));

  // Talón (con barcode estilo “imagen 2”)
  $yTalon = $pdf->GetY() + 1.6;
  $pdf->TalonPago($yTalon,'Corresponsales',$fact['CLIENOMB'],$fact['FACTRECI'],$subtotal,$codeString);

  return $pdf->GetY();
}

/* ==== POST ==== */
if ($_SERVER['REQUEST_METHOD']==='POST' && empty($errorMsg)) {

  $client_id  = isset($_POST['VENTCLIE']) ? $_POST['VENTCLIE'] : '';
  $vehiculo   = isset($_POST['VENTPLAC']) ? $_POST['VENTPLAC'] : '';
  $producto   = isset($_POST['VENTPROD']) ? $_POST['VENTPROD'] : '';
  $cantidad   = (int)(isset($_POST['VENTCANT']) ? $_POST['VENTCANT'] : 0);
  $fecha      = isset($_POST['VENTFEVE']) ? $_POST['VENTFEVE'] : $ahora_bogota;
  $usua       = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';

  if (!$client_id || !$producto || $cantidad < 1) {
    $errorMsg = "Datos incompletos. Verifique cliente/constructora, producto y cantidad.";
  }

  $valor_unit=0; $prodNombre=''; $isSuper=false;

  if (!$errorMsg) {
    $stN=$conn->prepare("SELECT PRODDESC FROM productos WHERE PRODCODI=?");
    $stN->bind_param("i",$producto); $stN->execute(); $stN->bind_result($prodNombre);
    $okN=$stN->fetch(); $stN->close(); if(!$okN){ $prodNombre=''; }

    $isSuper = esSupervision($prodNombre);
    $valor_unit = precioFijoProducto($conn,(int)$producto);
    if ($valor_unit<=0) $errorMsg="Producto no válido o sin precio configurado.";
  }

  // Si es Supervisión, se fuerza PROPIO
  if (!$errorMsg && $isSuper) { $vehiculo='PROPIO'; }

  // Guardia: PROPIO NO permitido si NO es Supervisión
  if (!$errorMsg && !$isSuper && strtoupper(trim($vehiculo))==='PROPIO') {
    $errorMsg = "Seleccione un vehículo válido.";
  }

  if (!$errorMsg) {
    $total = $valor_unit * $cantidad;

    $conn->autocommit(false);
    $conn->query("START TRANSACTION");
    try {
      $q1="INSERT INTO ventas (VENTCLIE,VENTPLAC,VENTPROD,VENTCANT,VENTTOTA,VENTFEVE,VENTUSVE)
           VALUES (?,?,?,?,?,?,?)";
      $st=$conn->prepare($q1);
      if(!$st){ throw new Exception("Error al preparar inserción en ventas: ".$conn->error); }
      $st->bind_param("isiiiss",$client_id,$vehiculo,$producto,$cantidad,$total,$fecha,$usua);
      if(!$st->execute()){ throw new Exception("Error al registrar venta: ".$st->error); }
      $id_factura = $st->insert_id; $st->close();

      $q2="INSERT INTO facturas (factreci,factclie,factvalo,factesta,factfech,factusua)
           VALUES (?,?,?,'pendiente',?,?)";
      $st2=$conn->prepare($q2);
      if(!$st2){ throw new Exception("Error al preparar inserción en facturas: ".$conn->error); }
      $st2->bind_param("iidss",$id_factura,$client_id,$total,$fecha,$usua);
      if(!$st2->execute()){ throw new Exception("Error al crear la factura: ".$st2->error); }
      $st2->close();

      $qi=$conn->prepare("
        SELECT f.FACTRECI,f.FACTFECH,f.FACTVALO,
               c.CLIENOMB,c.CLIEDIRE,c.CLIEMAIL,
               v.VENTPLAC,v.VENTCANT,v.VENTFEVE,
               p.PRODDESC,h.VEHINOCO
        FROM facturas f
        JOIN clientes  c ON c.CLIEDOCU=f.FACTCLIE
        JOIN ventas    v ON v.VENTCLIE=f.FACTCLIE AND v.VENTFEVE=f.FACTFECH
        JOIN productos p ON p.PRODCODI=v.VENTPROD
        LEFT JOIN vehiculos h ON h.VEHIPLAC=v.VENTPLAC
        WHERE f.FACTRECI=? LIMIT 1
      ");
      if(!$qi){ throw new Exception("Error preparando consulta de información: ".$conn->error); }
      $qi->bind_param("i",$id_factura);
      if(!$qi->execute()){ throw new Exception("No se pudo obtener información de la factura."); }
      $qi->bind_result($FACTRECI,$FACTFECH,$FACTVALO,$CLIENOMB,$CLIEDIRE,$CLIEEMAIL,$VENTPLAC,$VENTCANT,$VENTFEVE,$PRODDESC,$VEHINOCO);
      $hasInfo=$qi->fetch(); $qi->close();
      if(!$hasInfo){ throw new Exception("No se encontraron datos de la factura."); }

      $fact=array(
        'FACTRECI'=>$FACTRECI,'FACTFECH'=>$FACTFECH,'FACTVALO'=>$FACTVALO,
        'CLIENOMB'=>$CLIENOMB,'CLIEDIRE'=>$CLIEDIRE,'CLIEMAIL'=>isset($CLIEEMAIL)?$CLIEEMAIL:'',
        'VENTPLAC'=>$VENTPLAC,'VENTCANT'=>$VENTCANT,'VENTFEVE'=>$VENTFEVE,
        'PRODDESC'=>$PRODDESC,'VEHINOCO'=>$VEHINOCO
      );

      $mt3=(int)$fact['VENTCANT']; $valorUnit=(float)$valor_unit; $subtotal=$valorUnit*$mt3;
      $fechaEmisionDMYHI = fmt_dmy($fact['FACTFECH'],true);

      // Cadena numérica (Code Set C)
      $serie   = '01';
      $factN   = str_pad((string)$fact['FACTRECI'], 8, '0', STR_PAD_LEFT);
      $fechaYmd= (new DateTime($fact['FACTFECH']))->format('Ymd');
      $valor10 = str_pad((string)(int)round($subtotal), 10, '0', STR_PAD_LEFT);
      $cli10   = str_pad(preg_replace('/\D/','',(string)$client_id), 10, '0', STR_PAD_LEFT);
      $base = $serie.$factN.$fechaYmd.$valor10.$cli10;
      $dv   = luhn_dv($base);
      $codeString = $base.$dv;

      @$conn->query("UPDATE facturas SET FACTREF='". $conn->real_escape_string($codeString) ."' WHERE FACTRECI=".$id_factura);

      // QR
      $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
      $consultaURL = $scheme.'://'.$host.'/FacturaCT/consultar_factura.php?n='.urlencode($fact['FACTRECI']);

      if (!is_dir(__DIR__.'/qrcodes')) { @mkdir(__DIR__.'/qrcodes',0775,true); }
      $qr_file = __DIR__.'/qrcodes/qr_fact_'.$fact['FACTRECI'].'.png';
      QRcode::png($consultaURL,$qr_file,QR_ECLEVEL_M,4,2);

      // PDF
      $pdf = new FacturaPDF('P','mm','Letter'); // Carta
      $pdf->SetMargins(10,6,10);
      $pdf->AddPage();
      $pdf->marginLeft=10; $pdf->marginRight=10;

      $isSuperPDF  = esSupervision($fact['PRODDESC']);
      $tituloCant  = $isSuperPDF ? 'Cantidad' : 'Cantidad MT³';
      $tituloPrin  = $isSuperPDF ? 'FACTURA DE SERVICIO' : 'FACTURA DE SERVICIO - CARRO TANQUE';
      $labelCliente= $isSuperPDF ? 'Constructora' : 'Cliente';

      // Bloque superior
      drawFacturaBloque(
        $pdf,$fact,$valorUnit,$subtotal,$codeString,
        $consultaURL,$empresaLogoPath,$fechaEmisionDMYHI,
        $qr_file,$tituloCant,$isSuperPDF,$tituloPrin,
        $labelCliente,8,true
      );

      // Línea de corte y bloque inferior
      $lineY = 139.7;
      cutLine($pdf,$lineY);
      $start2 = $lineY + 4.8;

      drawFacturaBloque(
        $pdf,$fact,$valorUnit,$subtotal,$codeString,
        $consultaURL,$empresaLogoPath,$fechaEmisionDMYHI,
        $qr_file,$tituloCant,$isSuperPDF,$tituloPrin,
        $labelCliente,$start2,true
      );

      if (!is_dir(__DIR__.'/facturas_pdf')) { @mkdir(__DIR__.'/facturas_pdf',0775,true); }
      $rutaArchivo = 'facturas_pdf/factura_'.$fact['FACTRECI'].'.pdf';
      if (!is_dir(__DIR__ . '/facturas_pdf')) { mkdir(__DIR__ . '/facturas_pdf', 0777, true); }
      $pdf->Output('F', __DIR__ . '/' . $rutaArchivo);

      if (file_exists($qr_file)) { @unlink($qr_file); }

      $conn->commit(); $conn->autocommit(true); $success=true;

    } catch (Exception $e) {
      $conn->rollback(); $conn->autocommit(true);
      $errorMsg=$e->getMessage();
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
:root{
  --azul-oscuro:#1f6691; --azul:#2980b9; --texto:#333; --celeste:#6dd5fa;
  --txt:#0b3850; --danger:#d9534f; --danger-2:#b52b27;
}
body{ background:#ffffff; color:var(--texto); display:flex; min-height:100vh; flex-direction:column; }
header{
  background: var(--azul-oscuro); color:#fff; padding:12px 24px;
  display:flex; align-items:center; justify-content:center; position:relative;
}
header h2{ margin:0; font-size:1.4rem; }
.back-btn{ position:absolute; left:24px; }

/* ===== Usuario / Dropdown ===== */
.user-pill{
  position:absolute; right:24px; top:50%; transform:translateY(-50%);
  display:flex; align-items:center; gap:8px; padding:6px 12px;
  border-radius:9999px; background:#1f6691; color:#fff; font-weight:700;
  cursor:pointer;
}
.user-menu{
  display:none; position:absolute; right:24px; top:58px; min-width:200px;
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
  <a href="menu_principal.php" class="btn btn-light back-btn"><i class="bi bi-arrow-left-circle"></i> Volver</a>
  <h2>Generar Factura</h2>

  <!-- ===== Usuario con dropdown y cerrar sesión ===== -->
  <div class="user-pill" id="userBtn">
    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_actual); ?>
    <div class="user-menu" id="userMenu" aria-hidden="true">
      <a href="#" id="openLogout" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
  </div>
</header>

<main>
  <div class="container my-4">
    <div class="card card-glass p-3">
      <form method="POST" class="row g-3" autocomplete="off" id="formFactura">
        <div class="col-md-6">
          <label class="form-label" id="lblCliente">Cliente</label>
          <select name="VENTCLIE" class="form-select" required>
            <?php if (empty($errorMsg)):
              $res=$conn->query("SELECT CLIEDOCU, CLIENOMB FROM clientes ORDER BY CLIENOMB");
              if($res){ while($row=$res->fetch_assoc()): ?>
              <option value="<?php echo htmlspecialchars($row['CLIEDOCU']); ?>"><?php echo htmlspecialchars($row['CLIENOMB']); ?></option>
            <?php endwhile; } endif; ?>
          </select>
          <small id="helpCliente" class="text-muted d-block mt-1">Seleccione el cliente</small>
        </div>

        <div class="col-md-6" id="vehiculoWrap">
          <label class="form-label">Vehículo</label>
          <select name="VENTPLAC" id="VENTPLAC_SELECT" class="form-select" required>
            <?php if (empty($errorMsg)):
              $res=$conn->query("SELECT VEHIPLAC FROM vehiculos WHERE UPPER(VEHIPLAC) <> 'PROPIO' ORDER BY VEHIPLAC");
              if($res){ while($row=$res->fetch_assoc()):
                $placa = $row['VEHIPLAC']; ?>
                <option value="<?php echo htmlspecialchars($placa); ?>"><?php echo htmlspecialchars($placa); ?></option>
            <?php endwhile; } endif; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Producto</label>
          <select name="VENTPROD" id="VENTPROD" class="form-select" required>
            <?php if (empty($errorMsg)):
              $res=$conn->query("SELECT PRODCODI, PRODDESC FROM productos ORDER BY PRODDESC");
              if($res){ while($row=$res->fetch_assoc()): ?>
              <option value="<?php echo htmlspecialchars($row['PRODCODI']); ?>"><?php echo htmlspecialchars($row['PRODDESC']); ?></option>
            <?php endwhile; } endif; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label" id="lblCantidad">Cantidad</label>
          <input type="number" name="VENTCANT" id="VENTCANT" min="1" step="1" class="form-control" required value="1">
        </div>

        <div class="col-md-3">
          <label class="form-label">Total</label>
          <input type="text" id="VAL_TOTAL" class="form-control readonly-box" readonly>
        </div>

        <div class="col-md-3">
          <label class="form-label">Fecha (Bogotá)</label>
          <input type="datetime-local" name="VENTFEVE" class="form-control" value="<?php echo htmlspecialchars($ahora_bogota); ?>" readonly>
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

<footer>© 2025 - Sistema de Facturación</footer>

<!-- ===== Modal Éxito ===== -->
<div class="modal fade" id="modalExito" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-success">
    <div class="modal-header bg-success text-white">
      <h5 class="modal-title"><i class="bi bi-check-circle-fill"></i> ¡Factura generada!</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p>La factura fue generada correctamente 🎉</p>
      <?php if (!empty($rutaArchivo)): ?>
      <a href="<?php echo htmlspecialchars($rutaArchivo); ?>" target="_blank" class="btn btn-outline-success">
        <i class="bi bi-box-arrow-up-right"></i> Ver PDF
      </a>
      <?php endif; ?>
      <a href="generar_factura.php" class="btn btn-success ms-2"><i class="bi bi-plus-circle"></i> Nueva factura</a>
    </div>
  </div></div>
</div>

<!-- ===== Modal Error ===== -->
<div class="modal fade" id="modalError" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-danger">
    <div class="modal-header bg-danger text-white">
      <h5 class="modal-title"><i class="bi bi-x-circle-fill"></i> Error</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body"><p class="mb-0"><?php echo htmlspecialchars($errorMsg); ?></p></div>
  </div></div>
</div>

<!-- ===== Modal Confirmar Logout ===== -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header grad">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Confirmar cierre de sesión</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">¿Seguro que deseas cerrar tu sesión?</div>
      <div class="modal-footer soft">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Sí, salir</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var fmt=new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0});
function isSupervision(t){return String(t||'').toUpperCase()==='SUPERVISION INSTALACION DE MEDIDORES';}
function precioFijoFront(t){
  t=String(t||'').toUpperCase();
  if(t.indexOf('AGUA CRUDA')!==-1)return 10000;
  if(t.indexOf('AGUA TRATADA')!==-1)return 15000;
  if(t==='SUPERVISION INSTALACION DE MEDIDORES')return 43710;
  return 0;
}
function unidadProductoFront(t){return isSupervision(t)?'Unidad(es)':'MT³';}
function toggleVehiculoByProducto(){
  var p=document.getElementById('VENTPROD'); var txt=p.options[p.selectedIndex]?p.options[p.selectedIndex].text:''; 
  var wrap=document.getElementById('vehiculoWrap'); var sel=document.getElementById('VENTPLAC_SELECT');
  if(isSupervision(txt)){ 
    wrap.style.display='none'; 
    if(sel) sel.setAttribute('name','VENTPLAC_UNUSED'); 
  } else { 
    wrap.style.display=''; 
    if(sel) sel.setAttribute('name','VENTPLAC'); 
  }
}
function updateClienteLabelByProducto(){
  var p=document.getElementById('VENTPROD'); var txt=p.options[p.selectedIndex]?p.options[p.selectedIndex].text:''; 
  var es=isSupervision(txt);
  document.getElementById('lblCliente').textContent= es?'Constructora':'Cliente';
  document.getElementById('helpCliente').textContent= es?'Seleccione la constructora':'Seleccione el cliente';
}
function updateTotal(){
  var c=Math.max(0,parseInt(document.getElementById('VENTCANT').value||'0',10));
  var p=document.getElementById('VENTPROD'); var t=(p&&p.options[p.selectedIndex])?p.options[p.selectedIndex].text:''; 
  var u=precioFijoFront(t); document.getElementById('VAL_TOTAL').value=fmt.format(u*c);
}
function updateUI(){
  var sel=document.getElementById('VENTPROD'); var inp=document.getElementById('VENTCANT'); var lbl=document.getElementById('lblCantidad');
  var opt=sel.options[sel.selectedIndex]; var t=opt?opt.text:''; var unitLbl=unidadProductoFront(t);
  lbl.textContent='Cantidad '+(unitLbl==='MT³'?'MT³':'(unidades)');
  inp.min=1; inp.removeAttribute('max');
  if(!inp.value||parseInt(inp.value,10)<1) inp.value=1;
  toggleVehiculoByProducto(); updateClienteLabelByProducto(); updateTotal();
}

document.addEventListener('DOMContentLoaded',function(){
  // Dropdown usuario
  var btn=document.getElementById('userBtn');
  var menu=document.getElementById('userMenu');
  if(btn && menu){
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      menu.style.display = (menu.style.display==='block' ? 'none' : 'block');
    });
    document.addEventListener('click', function(e){
      if(!btn.contains(e.target)) menu.style.display='none';
    }, false);
  }
  var openLogout=document.getElementById('openLogout');
  if(openLogout){
    openLogout.addEventListener('click', function(e){
      e.preventDefault();
      var m = new bootstrap.Modal(document.getElementById('logoutModal'));
      m.show();
    }, false);
  }

  // Limpieza extra: por si alguna vez quedó PROPIO en el DOM
  var selV=document.getElementById('VENTPLAC_SELECT');
  if(selV){
    for(var i=selV.options.length-1;i>=0;i--){
      var o=selV.options[i];
      if(String(o.value||'').toUpperCase()==='PROPIO'){ selV.remove(i); }
    }
  }

  updateUI();
  document.getElementById('VENTPROD').addEventListener('change',updateUI);
  document.getElementById('VENTCANT').addEventListener('input',updateUI);

  var f=document.getElementById('formFactura'); var btnS=document.getElementById('btnSubmit');
  f.addEventListener('submit',function(e){
    var p=document.getElementById('VENTPROD'); 
    var t=(p&&p.options[p.selectedIndex])?p.options[p.selectedIndex].text:''; 
    var es=isSupervision(t);
    var sel=document.getElementById('VENTPLAC_SELECT');

    if(es){
      if(sel) sel.setAttribute('name','VENTPLAC_UNUSED');
      var h=document.getElementById('VENTPLAC_HIDDEN_RUNTIME');
      if(!h){ 
        h=document.createElement('input'); 
        h.type='hidden'; h.name='VENTPLAC'; h.id='VENTPLAC_HIDDEN_RUNTIME'; 
        h.value='PROPIO'; f.appendChild(h); 
      } else { 
        h.name='VENTPLAC'; h.value='PROPIO'; 
      }
    } else {
      if(sel) sel.setAttribute('name','VENTPLAC');
      var h2=document.getElementById('VENTPLAC_HIDDEN_RUNTIME'); if(h2) h2.parentNode.removeChild(h2);
    }

    if(btnS.disabled){ e.preventDefault(); return; }
    btnS.disabled=true; btnS.innerText='Generando...';
  });
});
<?php if ($success): ?>
document.addEventListener('DOMContentLoaded',function(){ new bootstrap.Modal(document.getElementById('modalExito')).show(); });
<?php elseif(!$success && !empty($errorMsg)): ?>
document.addEventListener('DOMContentLoaded',function(){ new bootstrap.Modal(document.getElementById('modalError')).show(); });
<?php endif; ?>
</script>
</body>
</html>