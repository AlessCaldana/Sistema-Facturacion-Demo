<?php
session_start();
require 'conexion.php';
require_once __DIR__ . '/demo_config.php';

if (!ini_get('date.timezone')) { date_default_timezone_set('America/Bogota'); }
$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';
$demo_mode = (defined('DEMO_PUBLICO') && DEMO_PUBLICO);
$max_productos = (defined('DEMO_MAX_PRODUCTOS') ? (int)DEMO_MAX_PRODUCTOS : 5);

$uploads_dir_fs = __DIR__ . '/uploads/productos';

$script_name = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
$base_path = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
if ($base_path === '.' || $base_path === '/') { $base_path = ''; }
if (substr($base_path, -9) === '/frontend') {
  $base_path = substr($base_path, 0, -9);
}
$uploads_dir_web = ($base_path !== '' ? $base_path : '') . '/frontend/uploads/productos';

function h($v){ return htmlspecialchars((string)(isset($v)?$v:''), ENT_QUOTES, 'UTF-8'); }

function ensure_dir($path){
  if (!is_dir($path)) { @mkdir($path, 0777, true); }
  return is_dir($path);
}

function limpiar_imagen_producto($baseDir, $id){
  $id = (int)$id;
  $exts = array('jpg', 'jpeg', 'png', 'webp', 'gif');
  foreach ($exts as $ext) {
    $f = $baseDir . '/producto_' . $id . '.' . $ext;
    if (is_file($f)) { @unlink($f); }
  }
}

function guardar_imagen_producto($file, $productoId, $baseDir, &$errorOut){
  $errorOut = '';
  if (!isset($file) || !is_array($file)) return '';

  $uploadError = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
  if ($uploadError === UPLOAD_ERR_NO_FILE) return '';
  if ($uploadError !== UPLOAD_ERR_OK) {
    $errorOut = 'No se pudo subir la imagen (codigo ' . $uploadError . ').';
    return '';
  }

  $size = isset($file['size']) ? (int)$file['size'] : 0;
  if ($size <= 0 || $size > (2 * 1024 * 1024)) {
    $errorOut = 'La imagen debe pesar maximo 2 MB.';
    return '';
  }

  $tmp = isset($file['tmp_name']) ? $file['tmp_name'] : '';
  if ($tmp === '' || !is_uploaded_file($tmp)) {
    $errorOut = 'Archivo de imagen invalido.';
    return '';
  }

  $mime = '';
  if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
      $mime = (string)finfo_file($fi, $tmp);
      finfo_close($fi);
    }
  }

  $allowed = array(
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
  );

  $ext = '';
  if ($mime !== '' && isset($allowed[$mime])) {
    $ext = $allowed[$mime];
  } else {
    $name = isset($file['name']) ? strtolower((string)$file['name']) : '';
    $tryExt = pathinfo($name, PATHINFO_EXTENSION);
    if (in_array($tryExt, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true)) {
      $ext = $tryExt;
    }
  }

  if ($ext === '') {
    $errorOut = 'Formato no permitido. Usa JPG, PNG, WEBP o GIF.';
    return '';
  }

  if (!ensure_dir($baseDir)) {
    $errorOut = 'No se pudo crear la carpeta de imagenes.';
    return '';
  }

  $id = (int)$productoId;
  limpiar_imagen_producto($baseDir, $id);

  $filename = 'producto_' . $id . '.' . $ext;
  $dest = $baseDir . '/' . $filename;

  if (!move_uploaded_file($tmp, $dest)) {
    $errorOut = 'No se pudo guardar la imagen en el servidor.';
    return '';
  }

  return $filename;
}

function obtener_url_imagen_producto($productoId, $baseDirFs, $baseDirWeb){
  $id = (int)$productoId;
  $exts = array('jpg', 'jpeg', 'png', 'webp', 'gif');
  foreach ($exts as $ext) {
    $f = $baseDirFs . '/producto_' . $id . '.' . $ext;
    if (is_file($f)) {
      $v = @filemtime($f);
      return $baseDirWeb . '/producto_' . $id . '.' . $ext . '?v=' . (int)$v;
    }
  }
  return '';
}

$mensaje='';
$schema_error='';

try {
  $pdo->query("SELECT PRODCODI, PRODDESC, PRODPREC, PRODCANT FROM productos LIMIT 1");
} catch (Exception $e) {
  $schema_error = 'La tabla productos no coincide con el esquema general (PRODCODI, PRODDESC, PRODPREC, PRODCANT).';
}

if ($schema_error==='' && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion'])) {
  $accion = isset($_POST['accion']) ? $_POST['accion'] : 'insertar';
  $id     = isset($_POST['PRODCODI']) ? (int)$_POST['PRODCODI'] : 0;
  $desc   = trim(isset($_POST['PRODDESC']) ? $_POST['PRODDESC'] : '');
  $prec   = isset($_POST['PRODPREC']) ? (float)$_POST['PRODPREC'] : 0;
  $cant   = isset($_POST['PRODCANT']) ? (float)$_POST['PRODCANT'] : 0;

  if ($demo_mode && $accion !== 'insertar') {
    $mensaje = 'En entorno demo solo se permite crear productos (sin editar ni eliminar).';
  } elseif ($demo_mode && $accion==='insertar' && demo_limite_alcanzado($pdo, 'productos', $max_productos, '', array())) {
    $mensaje = 'Limite demo alcanzado: maximo '.$max_productos.' productos.';
  } elseif ($accion !== 'eliminar' && $desc==='') {
    $mensaje = 'La descripcion del producto es obligatoria.';
  } elseif ($accion !== 'eliminar' && $prec < 0) {
    $mensaje = 'El precio no puede ser negativo.';
  } elseif ($accion !== 'eliminar' && $cant < 0) {
    $mensaje = 'El stock no puede ser negativo.';
  } else {
    try {
      if ($accion==='insertar') {
        $st = $pdo->prepare("INSERT INTO productos (PRODDESC, PRODPREC, PRODCANT) VALUES (?, ?, ?)");
        $st->execute(array($desc, $prec, $cant));
        $newId = (int)$pdo->lastInsertId();

        $imgErr = '';
        $imgName = guardar_imagen_producto(isset($_FILES['PRODIMG']) ? $_FILES['PRODIMG'] : null, $newId, $uploads_dir_fs, $imgErr);
        if ($imgErr !== '') {
          $mensaje = 'Producto creado, pero la imagen no se guardo: ' . $imgErr;
        } else {
          $mensaje = ($imgName !== '') ? 'Producto creado con imagen.' : 'Producto creado.';
        }
      } elseif ($accion==='actualizar' && $id>0) {
        $st = $pdo->prepare("UPDATE productos SET PRODDESC=?, PRODPREC=?, PRODCANT=? WHERE PRODCODI=?");
        $st->execute(array($desc, $prec, $cant, $id));

        $imgErr = '';
        $imgName = guardar_imagen_producto(isset($_FILES['PRODIMG']) ? $_FILES['PRODIMG'] : null, $id, $uploads_dir_fs, $imgErr);
        if ($imgErr !== '') {
          $mensaje = 'Producto actualizado, pero la imagen no se guardo: ' . $imgErr;
        } else {
          $mensaje = ($imgName !== '') ? 'Producto actualizado con nueva imagen.' : 'Producto actualizado.';
        }
      } elseif ($accion==='eliminar' && $id>0) {
        $st = $pdo->prepare("DELETE FROM productos WHERE PRODCODI=?");
        $st->execute(array($id));
        if ($st->rowCount()>0) {
          limpiar_imagen_producto($uploads_dir_fs, $id);
          $mensaje = 'Producto eliminado.';
        } else {
          $mensaje = 'Producto no encontrado.';
        }
      }
    } catch (PDOException $e) {
      if ($e->getCode()==='23000') {
        $mensaje = 'No se puede eliminar/guardar por relacion con otros registros.';
      } else {
        $mensaje = 'Error: '.$e->getMessage();
      }
    }
  }
}

$productos = array();
if ($schema_error==='') {
  $st = $pdo->query("SELECT PRODCODI, PRODDESC, PRODPREC, PRODCANT FROM productos ORDER BY PRODDESC, PRODCODI");
  $productos = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
}

$editar = null;
if ($schema_error==='' && !$demo_mode && isset($_GET['editar']) && ctype_digit($_GET['editar'])) {
  $st = $pdo->prepare("SELECT PRODCODI, PRODDESC, PRODPREC, PRODCANT FROM productos WHERE PRODCODI=?");
  $st->execute(array((int)$_GET['editar']));
  $editar = $st->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestion de Productos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
  html,body{margin:0 !important;padding:0 !important;}
  header,.topbar{margin-top:0 !important;}
  :root{
    --azul:#1f6691;
    --azul-2:#2f83b5;
    --azul-3:#154c6d;
    --bg:#eef3f8;
    --txt:#153549;
    --line:#d9e5ef;
    --danger:#d9534f;
    --danger-2:#b63733;
  }
  body{
    margin:0;
    min-height:100vh;
    background: linear-gradient(180deg, #f3f7fb 0%, var(--bg) 100%);
    font-family: "Segoe UI", Tahoma, Arial, sans-serif;
    color: var(--txt);
  }
  .topbar{
    position: sticky;
    top: 0;
    z-index: 1000;
    background: linear-gradient(135deg, var(--azul), var(--azul-2));
    box-shadow: 0 8px 18px rgba(13,53,79,.18);
  }
  .topbar-inner{
    max-width: 1320px;
    margin: 0 auto;
    padding: 10px 14px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap:10px;
    align-items: center;
  }
  .btn-volver{
    display:inline-flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
    color:#fff;
    border:1px solid rgba(255,255,255,.40);
    border-radius:10px;
    padding:8px 12px;
    font-weight:800;
    background:rgba(255,255,255,.08);
    transition:.2s ease;
  }
  .btn-volver:hover{
    color:#fff;
    background:rgba(255,255,255,.16);
    border-color:rgba(255,255,255,.55);
  }
  .topbar-title{
    margin:0;
    text-align:center;
    color:#fff;
    font-weight:900;
    font-size:1.55rem;
  }
  .user-pill{
    justify-self:end;
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:#fff;
    padding:7px 10px;
    border-radius:12px;
    position:relative;
    cursor:pointer;
  }
  .user-pill:hover{ background:rgba(255,255,255,.10); }
  .user-menu{
    position:absolute;
    right:0;
    top:calc(100% + 8px);
    min-width:185px;
    display:none;
    background:#fff;
    border:1px solid var(--line);
    border-radius:12px;
    padding:6px;
    box-shadow:0 14px 26px rgba(0,0,0,.16);
  }
  .user-item{
    display:flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
    padding:10px 12px;
    border-radius:10px;
    color:var(--danger);
    font-weight:800;
  }
  .user-item:hover{ background:#ffe6e5; color:var(--danger-2); }

  .main-wrap{ max-width: 1120px; margin: 28px auto; padding: 0 14px; }
  .panel{
    background:#fff;
    border:1px solid #e3ecf4;
    border-radius:16px;
    box-shadow:0 12px 30px rgba(17,58,84,.10);
    overflow:hidden;
  }
  .panel-head{
    background:linear-gradient(135deg,var(--azul),var(--azul-2));
    color:#fff;
    padding:16px 20px;
  }
  .panel-head h3{ margin:0; font-weight:900; font-size:1.35rem; }
  .panel-head p{ margin:5px 0 0; opacity:.95; }
  .panel-body{ padding:18px; }

  .form-card{
    border:1px solid var(--line);
    border-radius:14px;
    background:#fdfefe;
    padding:16px;
    margin-bottom:14px;
  }
  .form-label{ font-weight:700; color:#264f66; }
  .form-control, .form-select{
    border-radius:10px;
    border:1px solid #cfdfea;
    min-height:42px;
  }
  .btn-main{
    background:linear-gradient(135deg,var(--azul),var(--azul-2));
    color:#fff;
    font-weight:800;
    border:none;
    border-radius:10px;
    min-height:42px;
  }
  .btn-main:hover{ color:#fff; filter:brightness(1.06); }

  .table-card{
    border:1px solid var(--line);
    border-radius:14px;
    overflow:hidden;
    background:#fff;
  }
  .table thead th{
    background:#f3f8fc;
    color:#204760;
    font-weight:800;
    border-bottom:1px solid var(--line);
  }
  .thumb{
    width:56px;
    height:56px;
    object-fit:cover;
    border-radius:10px;
    border:1px solid #d7e4ee;
    background:#f6fbff;
  }
  .thumb-empty{
    width:56px;
    height:56px;
    border-radius:10px;
    border:1px dashed #bfd4e3;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#7aa0b8;
    background:#f6fbff;
    font-size:20px;
  }
  .btn-edit{
    background:#f0a500;
    color:#fff;
    border:none;
    font-weight:700;
  }
  .btn-edit:hover{ background:#d18f00; color:#fff; }

  .alert{ border-radius:12px; }

  @media (max-width: 768px){
    .topbar-inner{ grid-template-columns:1fr; }
    .btn-volver,.user-pill{ justify-self:center; }
    .topbar-title{ font-size:1.2rem; }
  }
</style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="mantenimiento_tablas_D.php" class="btn-volver"><i class="bi bi-arrow-left-circle"></i> Volver</a>
    <h1 class="topbar-title">Gestion de Productos</h1>
    <div class="user-pill" id="userBtn">
      <i class="bi bi-person-circle"></i> <?php echo h($usuario); ?>
      <div class="user-menu" id="userMenu">
        <a href="logout.php" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesion</a>
      </div>
    </div>
  </div>
</header>

<div class="main-wrap">
  <section class="panel">
    <div class="panel-head">
      <h3>Catalogo de Productos</h3>
      <p>Administra descripcion, precio, stock e imagen del inventario.</p>
    </div>

    <div class="panel-body">
      <?php if ($demo_mode): ?>
        <div class="alert alert-warning">ENTORNO DEMO: maximo <?php echo (int)$max_productos; ?> productos. Solo crear, sin editar ni eliminar.</div>
      <?php endif; ?>

      <?php if ($mensaje!==''): ?>
        <div class="alert alert-info"><?php echo h($mensaje); ?></div>
      <?php endif; ?>

      <?php if ($schema_error!==''): ?>
        <div class="alert alert-danger"><?php echo h($schema_error); ?></div>
      <?php endif; ?>

      <?php if ($schema_error===''): ?>
      <div class="form-card">
        <h5 class="mb-3"><?php echo $editar ? 'Editar Producto' : 'Nuevo Producto'; ?></h5>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="accion" value="<?php echo $editar ? 'actualizar' : 'insertar'; ?>">
          <?php if($editar): ?><input type="hidden" name="PRODCODI" value="<?php echo h($editar['PRODCODI']); ?>"><?php endif; ?>

          <div class="col-md-4">
            <label class="form-label">Descripcion</label>
            <input type="text" class="form-control" name="PRODDESC" required value="<?php echo h($editar?$editar['PRODDESC']:''); ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label">Precio</label>
            <input type="number" step="0.01" min="0" class="form-control" name="PRODPREC" required value="<?php echo h($editar?$editar['PRODPREC']:'0'); ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label">Stock</label>
            <input type="number" step="0.01" min="0" class="form-control" name="PRODCANT" required value="<?php echo h($editar?$editar['PRODCANT']:'0'); ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label">Imagen</label>
            <input type="file" class="form-control" name="PRODIMG" accept="image/jpeg,image/png,image/webp,image/gif">
          </div>

          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-main w-100"><i class="bi bi-save2 me-1"></i> Guardar</button>
          </div>
        </form>
      </div>

      <div class="table-card">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width:100px;">ID</th>
                <th style="width:100px;">Imagen</th>
                <th>Descripcion</th>
                <th style="width:180px;">Precio</th>
                <th style="width:160px;">Stock</th>
                <?php if(!$demo_mode): ?><th style="width:140px;">Acciones</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
            <?php if(!empty($productos)): foreach($productos as $p): ?>
              <?php $imgUrl = obtener_url_imagen_producto((int)$p['PRODCODI'], $uploads_dir_fs, $uploads_dir_web); ?>
              <tr>
                <td><?php echo h($p['PRODCODI']); ?></td>
                <td>
                  <?php if ($imgUrl !== ''): ?>
                    <img src="<?php echo h($imgUrl); ?>" alt="Producto" class="thumb">
                  <?php else: ?>
                    <span class="thumb-empty"><i class="bi bi-image"></i></span>
                  <?php endif; ?>
                </td>
                <td><?php echo h($p['PRODDESC']); ?></td>
                <td><?php echo number_format((float)$p['PRODPREC'],2,',','.'); ?></td>
                <td><?php echo number_format((float)$p['PRODCANT'],2,',','.'); ?></td>
                <?php if(!$demo_mode): ?>
                  <td><a class="btn btn-edit btn-sm" href="productos_D.php?editar=<?php echo urlencode($p['PRODCODI']); ?>"><i class="bi bi-pencil-square"></i> Editar</a></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="<?php echo $demo_mode?5:6; ?>" class="text-center text-muted py-4">Sin registros.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if (!btn || !menu) return;

  btn.addEventListener('click', function(e){
    e.preventDefault();
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
  });

  document.addEventListener('click', function(e){
    if (!btn.contains(e.target)) menu.style.display = 'none';
  });
})();
</script>

</body>
</html>

