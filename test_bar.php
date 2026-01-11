<?php

/* ==== RUTAS ==== */
require_once __DIR__ . '/barcode/src/BarcodeGenerator.php';
require_once __DIR__ . '/barcode/src/BarcodeGeneratorPNG.php';

/* ==== TYPES ==== */
require_once __DIR__ . '/barcode/src/Types/TypeInterface.php';
require_once __DIR__ . '/barcode/src/Types/TypeCode128.php';

/* ==== RENDERERS ==== */
require_once __DIR__ . '/barcode/src/Renderers/RendererInterface.php';
require_once __DIR__ . '/barcode/src/Renderers/PngRenderer.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

/* ==== TEST ==== */
$gen = new BarcodeGeneratorPNG();
$png = $gen->getBarcode('123456789', $gen::TYPE_CODE_128, 2, 40);
file_put_contents(__DIR__ . '/test_bar.png', $png);

echo "✅ Listo -> test_bar.png generado";
