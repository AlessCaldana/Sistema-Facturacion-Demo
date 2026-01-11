<?php
// config_recaudo.php
return array(
  // local: usa tu payload interno con Luhn (lo que ya tienes)
  // supergiros: usa la referencia exacta del anexo (cuando llegue)
  'BARCODE_MODE'   => 'local', // 'local' | 'supergiros'
  'CONVENIO_CODE'  => null,    // ej. '90012345' (cuando SuperGIROS te lo entregue)
  'BAR_WIDTH_MM'   => 170,     // ancho en mm en el PDF
  'BAR_HEIGHT_MM'  => 16,      // alto en mm en el PDF
  'QR_SIZE_MM'     => 25,
);
