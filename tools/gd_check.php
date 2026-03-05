<?php
echo 'Loaded ini: ', php_ini_loaded_file(), "<br>";
echo 'extension_dir: ', ini_get('extension_dir'), "<br>";
echo 'imagecreate exists? ', function_exists('imagecreate') ? 'YES' : 'NO', "<br>";
?>
