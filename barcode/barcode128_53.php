<?php
/*  Code128 (vector) para PHP 5.3 + FPDF
 *  - Sin Composer, sin GD, sin namespaces.
 *  - Dibuja con FPDF::Rect() (vectorial).
 *  - Autoelige:
 *      * Set C si todo son dígitos y longitud par.
 *      * Si todo son dígitos y longitud IMPAR → primer dígito en Set B,
 *        luego con CODE C (99) cambia a Set C para pares restantes.
 *      * En cualquier otro caso usa Set B.
 *  - Expone:
 *      c128_total_modules($txt)  → total de módulos (sin quiet zone)
 *      fpdf_barcode128($pdf, $xMm, $yMm, $moduleMm, $heightMm, $quietModules, $txt)
 */

if (!class_exists('FPDF')) {
  // carga FPDF si aún no está cargado
  $fp = dirname(__FILE__) . '/../fpdf/fpdf.php';
  if (file_exists($fp)) require_once $fp;
}

/* ===================== Tabla oficial Code128 ===================== */
/*  0..102: data; 103:Start A, 104:Start B, 105:Start C, 106:Stop  */
function _c128_table() {
  static $T128 = null;
  if ($T128 !== null) return $T128;

  $T128 = array(
    array(2,1,2,2,2,2),array(2,2,2,1,2,2),array(2,2,2,2,2,1),array(1,2,1,2,2,3),array(1,2,1,3,2,2),
    array(1,3,1,2,2,2),array(1,2,2,2,1,3),array(1,2,2,3,1,2),array(1,3,2,2,1,2),array(2,2,1,2,1,3),
    array(2,2,1,3,1,2),array(2,3,1,2,1,2),array(1,1,2,2,3,2),array(1,2,2,1,3,2),array(1,2,2,2,3,1),
    array(1,1,3,2,2,2),array(1,2,3,1,2,2),array(1,2,3,2,2,1),array(2,2,3,2,1,1),array(2,2,1,1,3,2),
    array(2,2,1,2,3,1),array(2,1,3,2,1,2),array(2,2,3,1,1,2),array(3,1,2,1,3,1),array(3,1,1,2,2,2),
    array(3,2,1,1,2,2),array(3,2,1,2,2,1),array(3,1,2,2,1,2),array(3,2,2,1,1,2),array(3,2,2,2,1,1),
    array(2,1,2,1,2,3),array(2,1,2,3,2,1),array(2,3,2,1,2,1),array(1,1,1,3,2,3),array(1,3,1,1,2,3),
    array(1,3,1,3,2,1),array(1,1,2,3,1,3),array(1,3,2,1,1,3),array(1,3,2,3,1,1),array(2,1,1,3,1,3),
    array(2,3,1,1,1,3),array(2,3,1,3,1,1),array(1,1,2,1,3,3),array(1,1,2,3,3,1),array(1,3,2,1,3,1),
    array(1,1,3,1,2,3),array(1,1,3,3,2,1),array(1,3,3,1,2,1),array(3,1,3,1,2,1),array(2,1,1,3,3,1),
    array(2,3,1,1,3,1),array(2,1,3,1,1,3),array(2,1,3,3,1,1),array(2,1,3,1,3,1),array(3,1,1,1,2,3),
    array(3,1,1,3,2,1),array(3,3,1,1,2,1),array(3,1,2,1,1,3),array(3,1,2,3,1,1),array(3,3,2,1,1,1),
    array(3,1,4,1,1,1),array(2,2,1,4,1,1),array(4,3,1,1,1,1),array(1,1,1,2,2,4),array(1,1,1,4,2,2),
    array(1,2,1,1,2,4),array(1,2,1,4,2,1),array(1,4,1,1,2,2),array(1,4,1,2,2,1),array(1,1,2,2,1,4),
    array(1,1,2,4,1,2),array(1,2,2,1,1,4),array(1,2,2,4,1,1),array(1,4,2,1,1,2),array(1,4,2,2,1,1),
    array(2,4,1,2,1,1),array(2,2,1,1,1,4),array(4,1,3,1,1,1),array(2,4,1,1,1,2),array(1,3,4,1,1,1),
    array(1,1,1,2,4,2),array(1,2,1,1,4,2),array(1,2,1,2,4,1),array(1,1,4,2,1,2),array(1,2,4,1,1,2),
    array(1,2,4,2,1,1),array(4,1,1,2,1,2),array(4,2,1,1,1,2),array(4,2,1,2,1,1),array(2,1,2,1,4,1),
    array(2,1,4,1,2,1),array(4,1,2,1,2,1),array(1,1,1,1,4,3),array(1,1,1,3,4,1),array(1,3,1,1,4,1),
    array(1,1,4,1,1,3),array(1,1,4,3,1,1),array(4,1,1,1,1,3),array(4,1,1,3,1,1),array(1,1,3,1,4,1),
    array(1,1,4,1,3,1),array(3,1,1,1,4,1),array(4,1,1,1,3,1),
    array(2,1,1,4,1,2), // 103 Start A
    array(2,1,1,2,1,4), // 104 Start B
    array(2,1,1,2,3,2), // 105 Start C
    array(2,3,3,1,1,1,2) // 106 Stop (7 módulos)
  );
  return $T128;
}

/* ========== Secuencia con optimización: B→C si longitud impar de dígitos ========== */
function _c128_build_sequence($txt) {
  $len = strlen($txt);

  // ¿Todos dígitos?
  $allDigits = ($len > 0);
  for ($i=0; $i<$len; $i++) {
    $o = ord($txt[$i]);
    if ($o < 48 || $o > 57) { $allDigits = false; break; }
  }

  $codes = array();

  if ($allDigits) {
    if (($len % 2) == 0) {
      // Todo dígito y par → START C y pares
      $codes[] = 105; // START C
      for ($i=0; $i<$len; $i+=2) { $codes[] = intval(substr($txt,$i,2),10); }
      $checksum = 105;
      for ($i=1; $i<count($codes); $i++) { $checksum += $codes[$i] * $i; }
    } else {
      // Todo dígito y IMPAR → primer dígito en B, luego cambiar a C (99) y continuar en pares
      $codes[] = 104;                 // START B
      $codes[] = ord($txt[0]) - 32;   // primer dígito como char en B
      $codes[] = 99;                  // CODE C (cambio de set)
      for ($i=1; $i<$len; $i+=2) { $codes[] = intval(substr($txt,$i,2),10); }
      $checksum = 104;
      for ($i=1; $i<count($codes); $i++) { $checksum += $codes[$i] * $i; }
    }
  } else {
    // Texto general → Set B
    $codes[] = 104; // START B
    for ($i=0; $i<$len; $i++) { $codes[] = max(0, min(95, ord($txt[$i]) - 32)); }
    $checksum = 104;
    for ($i=1; $i<count($codes); $i++) { $checksum += $codes[$i] * $i; }
  }

  $codes[] = ($checksum % 103);
  $codes[] = 106; // STOP
  return $codes;
}

/* ========== Total de módulos (sin quiet zone) ========== */
function c128_total_modules($txt) {
  $T128  = _c128_table();
  $codes = _c128_build_sequence($txt);
  $modules = 0;
  for ($i=0; $i<count($codes); $i++) {
    $pat = $T128[$codes[$i]];
    $plen = count($pat);
    for ($k=0; $k<$plen; $k++) { $modules += $pat[$k]; }
  }
  return $modules;
}

/* ========== Dibujo vectorial en FPDF ========== */
function fpdf_barcode128($pdf, $xMm, $yMm, $moduleMm, $heightMm, $quietModules, $txt) {
  if (!($pdf instanceof FPDF)) return;

  $T128  = _c128_table();
  $codes = _c128_build_sequence($txt);

  // Quiet zone inicial
  $curX = $xMm + ($quietModules * $moduleMm);
  $pdf->SetFillColor(0);

  for ($i=0; $i<count($codes); $i++) {
    $pat = $T128[$codes[$i]];
    $plen = count($pat);
    for ($k=0; $k<$plen; $k++) {
      $w = $pat[$k] * $moduleMm;
      if (($k % 2) == 0) {
        // barra
        $pdf->Rect($curX, $yMm, $w, $heightMm, 'F');
      }
      $curX += $w; // espacio o barra
    }
  }

  // Quiet zone final (solo avance)
  $curX += ($quietModules * $moduleMm);
  return $curX;
}
