<?php
// recaudo_helpers.php (PHP 5.3-safe)
if (!ini_get('date.timezone')) { date_default_timezone_set('America/Bogota'); }

function luhn_dv($s){
  $sum = 0; $alt = false;
  for($i=strlen($s)-1;$i>=0;$i--){
    $n = (int)$s[$i];
    if($alt){ $n *= 2; if($n>9) $n -= 9; }
    $sum += $n; $alt = !$alt;
  }
  return (string)((10 - ($sum % 10)) % 10);
}

/**
 * Construye referencia LOCAL (solo dígitos)
 * SERIE(2)+FACT(8)+FECHA(8:YYYYMMDD)+VALOR(10)+CLIENTE(10)+DV(1) => 39 dígitos
 */
function build_ref_local($factNo, $fechaIso, $valorEntero, $clienteDoc) {
  $serie   = '01';
  $factN   = str_pad((string)$factNo, 8, '0', STR_PAD_LEFT);
  $dt      = new DateTime($fechaIso);
  $fecha   = $dt->format('Ymd');
  $valor10 = str_pad((string)$valorEntero, 10, '0', STR_PAD_LEFT);
  $cli10   = str_pad(preg_replace('/\D/','',(string)$clienteDoc), 10, '0', STR_PAD_LEFT);
  $base = $serie.$factN.$fecha.$valor10.$cli10;
  $dv   = luhn_dv($base);
  return $base.$dv;
}

/**
 * Placeholder SUPERGIROS: reemplazar cuando envíen anexo oficial.
 * CONVENIO + FACT(8) + YYYYMMDD + VALOR(10) + DV(Luhn)
 */
function build_ref_supergiros($convenio, $factNo, $fechaIso, $valorEntero) {
  if (!$convenio) $convenio = '00000000';
  $factN   = str_pad((string)$factNo, 8, '0', STR_PAD_LEFT);
  $dt      = new DateTime($fechaIso);
  $fecha   = $dt->format('Ymd');
  $valor10 = str_pad((string)$valorEntero, 10, '0', STR_PAD_LEFT);

  $base = $convenio.$factN.$fecha.$valor10;
  $dv   = luhn_dv($base);
  return $base.$dv;
}

/**
 * Crea referencia según modo y retorna metadatos para BD.
 * $ctx = array('factNo'=>..., 'fechaIso'=>..., 'valorEntero'=>..., 'clienteDoc'=>...)
 */
function build_barcode_reference($cfg, $ctx) {
  $mode = (isset($cfg['BARCODE_MODE']) ? $cfg['BARCODE_MODE'] : 'local');

  if ($mode === 'supergiros') {
    $conv = (isset($cfg['CONVENIO_CODE']) ? $cfg['CONVENIO_CODE'] : null);
    $ref  = build_ref_supergiros($conv, $ctx['factNo'], $ctx['fechaIso'], $ctx['valorEntero']);
    return array('ref' => $ref, 'conv' => $conv, 'venc' => null);
  } else {
    $ref  = build_ref_local($ctx['factNo'], $ctx['fechaIso'], $ctx['valorEntero'], $ctx['clienteDoc']);
    return array('ref' => $ref, 'conv' => null, 'venc' => null);
  }
}
