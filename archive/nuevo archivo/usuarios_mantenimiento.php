<?php
/* ===== usuarios_mantenimiento.php — PHP 5.3 compatible ===== */
session_start();
require_once 'conexion.php';

/* ==== Control de acceso ==== */
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
  header('Location: login.php'); exit;
}
$rol_sesion = strtolower(trim((string)$_SESSION['rol']));
$roles_denegados = array('despachador','vendedor','consulta');
$acceso_denegado = in_array($rol_sesion, $roles_denegados, true);

/* ==== Helpers ==== */
function h($v){ return htmlspecialchars((isset($v)?$v:''), ENT_QUOTES, 'UTF-8'); }
function only_digits($s){ return (preg_match('/^\d+$/',(string)$s)===1); }

/* ==== Hash seguro (bcrypt) compatible PHP 5.3 ==== */
function bcrypt_hash_53($plain,$cost=10){
  $plain=(string)$plain;
  if(function_exists('openssl_random_pseudo_bytes')){
    $salt=substr(strtr(base64_encode(openssl_random_pseudo_bytes(16)),'+','.'),0,22);
  }else{
    $salt=substr(strtr(base64_encode(sha1(uniqid(mt_rand(),true),true)),'+','.'),0,22);
  }
  $prefix=(version_compare(PHP_VERSION,'5.3.7','>='))?'$2y$':'$2a$';
  $cost2=str_pad((string)max(4,min(12,(int)$cost)),2,'0',STR_PAD_LEFT);
  $hash=crypt($plain,$prefix.$cost2.'$'.$salt);
  if(!is_string($hash)||strlen($hash)<20){ $hash=crypt($plain,$prefix.'10$'.$salt); }
  return $hash;
}

/* ===== Datos de cabecera ===== */
$usuario_doc = trim(isset($_SESSION['usuario'])?$_SESSION['usuario']:'');
$usuario_nom = trim(isset($_SESSION['nombre'])?$_SESSION['nombre']:'');
$usuario_header = ($usuario_doc!=='') ? ($usuario_nom!==''?($usuario_nom.' ('.$usuario_doc.')'):$usuario_doc) : 'Invitado';

/* ===== Lógica principal (si hay acceso) ===== */
$mensaje = '';

if (!$acceso_denegado) {
  if (!ini_get('date.timezone')) { date_default_timezone_set('America/Bogota'); }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_POST = array(
      'accion'   => 'insertar',
      'USUADOCU' => '',
      'USUANOMB' => '',
      'USUAROLE' => 'consulta',
      'USUAESTA' => 'A',
      'USUACLAV' => ''
    );
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion    = $_POST['accion'];
    $documento = isset($_POST['USUADOCU'])?trim($_POST['USUADOCU']):'';
    $nombre    = isset($_POST['USUANOMB'])?trim($_POST['USUANOMB']):'';
    $rol       = isset($_POST['USUAROLE'])?trim($_POST['USUAROLE']):'consulta';
    $estado    = isset($_POST['USUAESTA'])?trim($_POST['USUAESTA']):'A';
    $clave_raw = isset($_POST['USUACLAV'])?$_POST['USUACLAV']:'';
    $clave     = ($clave_raw!=='')?bcrypt_hash_53($clave_raw):null;

    if (!in_array($accion,array('activar','inactivar','eliminar'),true)) {
      if ($documento===''||!only_digits($documento)||(int)$documento<=0) {
        $mensaje="❌ Documento inválido. Debe ser numérico y mayor a 0.";
      } elseif ($nombre==='') {
        $mensaje="❌ Nombre requerido.";
      } elseif (!in_array($rol,array('admin','vendedor','despachador','consulta'),true)) {
        $mensaje="❌ Rol inválido.";
      } elseif (!in_array($estado,array('A','I'),true)) {
        $mensaje="❌ Estado inválido.";
      }
    } else {
      if ($documento===''||!only_digits($documento)) $mensaje="❌ Documento inválido.";
    }

    if ($mensaje===""){
      try{
        if($accion==='insertar'){
          $st=$pdo->prepare("INSERT INTO usuarios (USUADOCU,USUANOMB,USUACLAV,USUAROLE,USUAESTA,USUAFECR)
                             VALUES (?,?,?,?,?,NOW())");
          $st->execute(array($documento,$nombre,$clave,strtolower($rol),$estado));
          $mensaje="✅ Usuario insertado correctamente.";
        }elseif($accion==='modificar'){
          if($clave!==null){
            $st=$pdo->prepare("UPDATE usuarios SET USUANOMB=?,USUACLAV=?,USUAROLE=?,USUAESTA=? WHERE USUADOCU=?");
            $st->execute(array($nombre,$clave,strtolower($rol),$estado,$documento));
          }else{
            $st=$pdo->prepare("UPDATE usuarios SET USUANOMB=?,USUAROLE=?,USUAESTA=? WHERE USUADOCU=?");
            $st->execute(array($nombre,strtolower($rol),$estado,$documento));
          }
          $mensaje="✅ Usuario actualizado correctamente.";
        }elseif($accion==='activar'||$accion==='inactivar'){
          $nuevo = ($accion==='activar')?'A':'I';
          $st=$pdo->prepare("UPDATE usuarios SET USUAESTA=? WHERE USUADOCU=?");
          $st->execute(array($nuevo,$documento));
          $mensaje="✅ Usuario actualizado a estado ".$nuevo.".";
        }elseif($accion==='eliminar'){
          $st=$pdo->prepare("DELETE FROM usuarios WHERE USUADOCU=?");
          $st->execute(array($documento));
          $mensaje = ($st->rowCount()>0) ? "✅ Usuario eliminado correctamente." : "ℹ️ No se encontró el usuario para eliminar.";
          $_POST = array('accion'=>'insertar','USUADOCU'=>'','USUANOMB'=>'','USUAROLE'=>'consulta','USUAESTA'=>'A','USUACLAV'=>'');
        }
      }catch(PDOException $e){
        if($e->getCode()==='23000' && strpos($e->getMessage(),'Duplicate entry')!==false){
          $mensaje="❌ El usuario ya existe (documento duplicado).";
        }elseif($e->getCode()==='23000'){
          $mensaje="❌ No se puede eliminar: el usuario está asociado a otros registros.";
        }else{
          $mensaje="❌ Error: ".$e->getMessage();
        }
      }
    }
  }

  $usuarios_stmt = $pdo->query("SELECT USUADOCU,USUANOMB,USUAROLE,USUAESTA,USUAFECR FROM usuarios ORDER BY USUANOMB");
  $usuarios = $usuarios_stmt ? $usuarios_stmt->fetchAll(PDO::FETCH_ASSOC) : array();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mantenimiento de Usuarios</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{ --azul-oscuro:#1f6691; --azul:#2980b9; --texto:#333; --celeste:#6dd5fa; --txt:#0b3850; --danger:#dc3545; --danger-2:#b52b27; }
body{ background:#f5f7fb; }
header{ background: var(--azul-oscuro); color:#fff; padding:12px 24px; position:relative; }
.card-glass{ background:#ffffff; border-radius:14px; box-shadow:0 8px 18px rgba(0,0,0,.08); }
.btn-primary{ background: var(--azul); border-color: var(--azul); }
.btn-primary:hover{ background: var(--celeste); color:#111; border-color: var(--celeste); }
.table thead{ background:#e8f2fb; }
.badge-A{ background:#28a745; } .badge-I{ background:#ffc107; color:#212529; }

/* ===== Vista ACCESO DENEGADO ===== */
.deny-wrap{ display:flex; justify-content:center; padding:36px 16px; }
.deny-card{ width:860px; background:#fff; border:1px solid #e6ebf2; border-radius:12px; padding:22px 24px; box-shadow:0 12px 28px rgba(0,0,0,.06); }
.deny-title{ display:flex; align-items:center; gap:12px; color:#c0392b; font-weight:800; font-size:1.9rem; margin:0 0 6px 0; }
.deny-meta{ color:#334155; font-weight:600; margin:8px 0 14px 0; }
.deny-meta span{ margin-right:18px; }
.deny-text{ color:#0b3850; margin-bottom:16px; }
.deny-btn{ background:#287bb6; border:none; border-radius:10px; padding:10px 16px; color:#fff; font-weight:700; text-decoration:none; display:inline-block; }
.deny-btn:hover{ background:#1f6691; }

/* ===== Botón de usuario (pill) y dropdown ===== */
.user-pill{ margin-left:auto; position:relative; }
#userMenuBtn{
  cursor:pointer; display:flex; align-items:center; gap:8px;
  padding:8px 14px;
  background:none; /* ← QUITA EL AURA */
  border-radius:20px; color:#fff; font-weight:600;
}
#userMenu{
  position:absolute; top:48px; right:0;
  background:#fff; border-radius:14px; padding:12px 18px;
  box-shadow:0 8px 20px rgba(0,0,0,.15);
  min-width:160px; display:none; z-index:999;
}
#userMenu a{ text-decoration:none; color:#c0392b; font-weight:bold; display:flex; align-items:center; gap:8px; }
#userMenu a:hover{ color:#e74c3c; }
</style>
</head>
<body>

<?php if (!$acceso_denegado): ?>
<header class="d-flex align-items-center justify-content-center position-relative" style="background:#1f6691; color:#fff; padding:12px 24px;">
  
  <a href="menu_principal.php" class="btn btn-light position-absolute" style="left:20px;">
    <i class="bi bi-arrow-left-circle"></i> Volver
  </a>

  <h2 class="m-0 fw-bold">Mantenimiento de Usuarios</h2>

  <div class="user-pill position-absolute" style="right:20px;">
    <div id="userMenuBtn">
      <i class="bi bi-person-circle"></i> <?php echo h($usuario_header); ?>
    </div>
    <div id="userMenu">
      <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
  </div>

</header>
<?php endif; ?>

<?php if ($acceso_denegado): ?>
  <!-- ===== Vista de Acceso Denegado ===== -->
  <div class="deny-wrap">
    <div class="deny-card">
      <div class="deny-title">
        <svg width="36" height="36" viewBox="0 0 36 36" aria-hidden="true">
          <circle cx="18" cy="18" r="16" fill="#fff" stroke="#000" stroke-width="3"/>
          <circle cx="18" cy="18" r="14" fill="none" stroke="#e53935" stroke-width="4"/>
          <line x1="8" y1="28" x2="28" y2="8" stroke="#e53935" stroke-width="4" stroke-linecap="round"/>
        </svg>
        Acceso denegado
      </div>
      <div class="deny-meta">
        <span>Usuario: <?php echo h($usuario_doc!==''?$usuario_doc:'—'); ?></span>
        <span>Rol: <?php echo h($rol_sesion); ?></span>
      </div>
      <p class="deny-text">No cuentas con permisos para acceder a este recurso.</p>
      <a class="deny-btn" href="menu_principal.php">Volver al menú</a>
    </div>
  </div>
</body></html>
<?php exit; ?>
<?php endif; ?>

<div class="container py-4">
  <?php if (!empty($mensaje)): ?>
    <?php $cls = (strpos($mensaje,'✅')===0)?'alert-success':((strpos($mensaje,'❌')===0)?'alert-danger':'alert-info'); ?>
    <div class="alert <?php echo $cls; ?>"><?php echo h($mensaje); ?></div>
  <?php endif; ?>

  <div class="card card-glass p-3">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="form-tab" data-bs-toggle="tab" data-bs-target="#form" type="button">Formulario</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="lista-tab" data-bs-toggle="tab" data-bs-target="#lista" type="button">Lista de Usuarios</button>
      </li>
    </ul>

    <div class="tab-content mt-3" id="myTabContent">
      <!-- Formulario -->
      <div class="tab-pane fade show active" id="form" role="tabpanel">
        <form method="POST" class="row g-3" novalidate>
          <input type="hidden" name="accion" id="accion" value="<?php echo h(isset($_POST['accion'])?$_POST['accion']:'insertar'); ?>">

          <div class="col-md-4">
            <label class="form-label">Documento</label>
            <input type="text" name="USUADOCU" id="USUADOCU" class="form-control"
                   inputmode="numeric" pattern="\d*" maxlength="20"
                   value="<?php echo h(isset($_POST['USUADOCU'])?$_POST['USUADOCU']:''); ?>"
                   <?php echo ((isset($_POST['accion']) && $_POST['accion']==='modificar') ? 'readonly' : 'required'); ?>>
          </div>

          <div class="col-md-4">
            <label class="form-label">Nombre</label>
            <input type="text" name="USUANOMB" id="USUANOMB" class="form-control" required
                   value="<?php echo h(isset($_POST['USUANOMB'])?$_POST['USUANOMB']:''); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Rol</label>
            <select name="USUAROLE" id="USUAROLE" class="form-select">
              <?php
                $rol_sel = isset($_POST['USUAROLE'])?$_POST['USUAROLE']:'consulta';
                $roles = array('admin'=>'Admin','vendedor'=>'Vendedor','despachador'=>'Despachador','consulta'=>'Consulta');
                foreach($roles as $v=>$t){ $sel=($rol_sel===$v)?'selected':''; echo '<option value="'.$v.'" '.$sel.'>'.$t.'</option>'; }
              ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <select name="USUAESTA" id="USUAESTA" class="form-select">
              <?php
                $est_sel = isset($_POST['USUAESTA'])?$_POST['USUAESTA']:'A';
                $ests = array('A'=>'Activo','I'=>'Inactivo');
                foreach($ests as $v=>$t){ $sel=($est_sel===$v)?'selected':''; echo '<option value="'.$v.'" '.$sel.'>'.$t.'</option>'; }
              ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Contraseña</label>
            <input type="password" name="USUACLAV" id="USUACLAV" class="form-control"
                   placeholder="<?php echo ((isset($_POST['accion']) && $_POST['accion']==='modificar')?'Deja vacío para no cambiar':''); ?>">
          </div>

          <div class="col-12">
            <button type="submit" class="btn btn-primary" id="btnSubmit">
              <?php echo ((isset($_POST['accion']) && $_POST['accion']==='modificar')?'<i class="bi bi-save"></i> Actualizar':'<i class="bi bi-save"></i> Guardar'); ?>
            </button>
            <?php if (isset($_POST['accion']) && $_POST['accion']==='modificar'): ?>
              <a href="usuarios_mantenimiento.php" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Lista -->
      <div class="tab-pane fade" id="lista" role="tabpanel">
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Documento</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Fecha Creación</th><th style="width:320px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!empty($usuarios)): foreach($usuarios as $u): ?>
              <tr>
                <td><?php echo h($u['USUADOCU']); ?></td>
                <td><?php echo h($u['USUANOMB']); ?></td>
                <td><?php echo h(ucfirst($u['USUAROLE'])); ?></td>
                <td><span class="badge <?php echo 'badge-'.$u['USUAESTA']; ?>"><?php echo ($u['USUAESTA']==='A'?'Activo':'Inactivo'); ?></span></td>
                <td><?php echo h($u['USUAFECR']); ?></td>
                <td>
                  <div class="d-flex flex-wrap gap-2">
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="USUADOCU" value="<?php echo h($u['USUADOCU']); ?>">
                      <input type="hidden" name="accion" value="activar">
                      <button class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Activar</button>
                    </form>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="USUADOCU" value="<?php echo h($u['USUADOCU']); ?>">
                      <input type="hidden" name="accion" value="inactivar">
                      <button class="btn btn-sm btn-warning"><i class="bi bi-pause-circle"></i> Inactivar</button>
                    </form>
                    <button class="btn btn-sm btn-secondary"
                            onclick="editarUsuario('<?php echo h($u['USUADOCU']); ?>','<?php echo h($u['USUANOMB']); ?>','<?php echo h($u['USUAROLE']); ?>','<?php echo h($u['USUAESTA']); ?>')">
                      <i class="bi bi-pencil-square"></i> Modificar
                    </button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                            data-doc="<?php echo h($u['USUADOCU']); ?>" data-name="<?php echo h($u['USUANOMB']); ?>">
                      <i class="bi bi-trash"></i> Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" class="text-center text-muted">No hay usuarios registrados.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div><!-- /lista -->
    </div><!-- /tab-content -->
  </div><!-- /card -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Dropdown del usuario */
(function(){
  var btn = document.getElementById('userMenuBtn');
  var menu = document.getElementById('userMenu');
  if(btn && menu){
    btn.onclick = function(){ menu.style.display = (menu.style.display==='block')?'none':'block'; };
    document.addEventListener('click', function(e){
      if(!btn.contains(e.target)){ menu.style.display='none'; }
    });
  }
})();

function editarUsuario(doc,nombre,rol,estado){
  document.getElementById('accion').value='modificar';
  var d=document.getElementById('USUADOCU'); d.value=doc; d.readOnly=true;
  document.getElementById('USUANOMB').value=nombre;
  document.getElementById('USUAROLE').value=rol;
  document.getElementById('USUAESTA').value=estado;
  document.getElementById('USUACLAV').value='';
  var t=document.querySelector('[data-bs-target="#form"]');
  if(window.bootstrap&&bootstrap.Tab) new bootstrap.Tab(t).show();
}
</script>
</body>
</html>
