<?php
/**
 * code39.php — Render Code 39 (3:1) para FPDF — Compatible PHP 5.3
 * Uso:
 *   require 'fpdf/fpdf.php';
 *   require 'code39.php';
 *   $pdf = new FPDF();
 *   $pdf->AddPage();
 *   Code39($pdf, 20, 30, 'A123-45', 0.5, 18, true); // x,y,código,módulo,alto,mostrar texto
 *   $pdf->Output();
 *
 * Caracteres permitidos: 0-9 A-Z - . espacio $ / + %
 * (Convierte a mayúsculas y agrega automáticamente *start/stop*)
 */

function _c39_table(){
  static $TAB = null;
  if ($TAB) return $TAB;
  // n = narrow (1x), w = wide (3x). Cada carácter: 9 módulos (5 barras y 4 espacios, empezando en barra)
  $TAB = array(
    '0'=>'nnnwwnwnn','1'=>'wnnwnnnnw','2'=>'nnwwnnnnw','3'=>'wnwwnnnnn','4'=>'nnnwwnnnw','5'=>'wnnwwnnnn',
    '6'=>'nnwwwnnnn','7'=>'nnnwnnwnw','8'=>'wnnwnnwnn','9'=>'nnwwnnwnn','A'=>'wnnnnwnnw','B'=>'nnwnnwnnw',
    'C'=>'wnwnnwnnn','D'=>'nnnnwwnnw','E'=>'wnnnwwnnn','F'=>'nnwnwwnnn','G'=>'nnnnnwwnw','H'=>'wnnnnwwnn',
    'I'=>'nnwnnwwnn','J'=>'nnnnwwwnn','K'=>'wnnnnnnww','L'=>'nnwnnnnww','M'=>'wnwnnnnwn','N'=>'nnnnwnnww',
    'O'=>'wnnnwnnwn','P'=>'nnwnwnnwn','Q'=>'nnnnnnwww','R'=>'wnnnnnwwn','S'=>'nnwnnnwwn','T'=>'nnnnwnwwn',
    'U'=>'wwnnnnnnw','V'=>'nwwnnnnnw','W'=>'wwwnnnnnn','X'=>'nwnnwnnnw','Y'=>'wwnnwnnnn','Z'=>'nwwnwnnnn',
    '-'=>'nwnnnnwnw','.'=>'wwnnnnwnn',' '=>'nwwnnnwnn','*'=>'nwnnwnwnn','$'=>'nwnwnwnnn','/'=>'nwnwnnnwn',
    '+'=>'nwnnnwnwn','%'=>'nnnwnwnwn'
  );
  return $TAB;
}

/**
 * Dibuja Code39 en FPDF
 * @param FPDF  $pdf
 * @param float $x        X en mm
 * @param float $y        Y en mm (parte superior del código)
 * @param string $code    Texto a codificar (se auto-encierra con * *)
 * @param float $module   Ancho base (mm) de barra/espacio "narrow" (default 0.5)
 * @param float $height   Alto del código (mm)
 * @param bool  $showText Mostrar el texto debajo
 * @return float          Ancho total renderizado (mm)
 */
function Code39($pdf, $x, $y, $code, $module=0.5, $height=15.0, $showText=true){
  $TAB = _c39_table();

  // Normaliza
  $text = strtoupper((string)$code);
  // Valida caracteres
  for ($i=0; $i<strlen($text); $i++){
    $ch = $text[$i];
    if (!isset($TAB[$ch]) && !preg_match('/[0-9A-Z\-\.\ \$\/\+\%]/', $ch)) {
      // Muestra error y aborta
      $pdf->SetFont('Arial','',8);
      $pdf->Text($x, $y + $height + 4, "Carácter inválido en Code39: '{$ch}'");
      return 0;
    }
  }

  // Agrega start/stop
  $enc = '*'.$text.'*';

  // Geometría 3:1
  $narrow = $module;
  $wide   = $module * 3.0;
  $gap    = $narrow; // espacio entre caracteres

  // Dibujo
  $curX = $x;
  $pdf->SetFillColor(0);
  for ($i=0; $i<strlen($enc); $i++){
    $ch = $enc[$i];
    $seq = $TAB[$ch];            // 9 módulos: barra/espacio alternados (empieza barra)
    for ($m=0; $m<9; $m++){
      $w = ($seq[$m] === 'n') ? $narrow : $wide;
      if ($m % 2 === 0) {        // barras en los índices pares
        $pdf->Rect($curX, $y, $w, $height, 'F');
      }
      $curX += $w;
    }
    $curX += $gap;               // kerning entre caracteres
  }

  // Texto legible (opcional)
  if ($showText){
    // Centramos el texto bajo el código
    $fullWidth = $curX - $x;
    $pdf->SetFont('Arial','',9);
    $txt = $text;                // sin asteriscos visibles
    $tw = $pdf->GetStringWidth($txt);
    $pdf->Text($x + ($fullWidth - $tw)/2, $y + $height + 4, $txt);
  }

  return $curX - $x;
}

/**
 * Calcula el ancho total (mm) de un Code39 dado (para layout previo)
 * @param string $code
 * @param float  $module
 * @return float
 */
function Code39Width($code, $module=0.5){
  $TAB = _c39_table();
  $text = strtoupper((string)$code);
  $enc  = '*'.$text.'*';

  $narrow = $module;
  $wide   = $module * 3.0;
  $gap    = $narrow;

  $wTotal = 0.0;
  for ($i=0; $i<strlen($enc); $i++){
    $seq = $TAB[$enc[$i]];
    for ($m=0; $m<9; $m++){
      $wTotal += ($seq[$m] === 'n') ? $narrow : $wide;
    }
    $wTotal += $gap;
  }
  return $wTotal;
}
