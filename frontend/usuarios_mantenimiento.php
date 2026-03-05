<?php
session_start();
require_once 'conexion.php';
require_once __DIR__ . '/demo_config.php';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
  header('Location: login.php'); exit;
}

$rol_sesion = strtolower(trim((string)$_SESSION['rol']));
$roles_denegados = array('despachador','vendedor','consulta');
$acceso_denegado = in_array($rol_sesion, $roles_denegados, true);

$demo_mode = (defined('DEMO_PUBLICO') && DEMO_PUBLICO);
$max_demo_usuarios = (defined('DEMO_MAX_USUARIOS_CREADOS') ? (int)DEMO_MAX_USUARIOS_CREADOS : 1);

function h($v){ return htmlspecialchars((isset($v)?$v:''), ENT_QUOTES, 'UTF-8'); }
function only_digits($s){ return (preg_match('/^\d+$/',(string)$s)===1); }

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

$mensaje = '';
$usuarios = array();

if (!$acceso_denegado) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion    = $_POST['accion'];
    $documento = isset($_POST['USUADOCU'])?trim($_POST['USUADOCU']):'';
    $nombre    = isset($_POST['USUANOMB'])?trim($_POST['USUANOMB']):'';
    $rol       = isset($_POST['USUAROLE'])?trim($_POST['USUAROLE']):'consulta';
    $estado    = isset($_POST['USUAESTA'])?trim($_POST['USUAESTA']):'A';
    $clave_raw = isset($_POST['USUACLAV'])?$_POST['USUACLAV']:'';
    $clave     = ($clave_raw!=='')?bcrypt_hash_53($clave_raw):null;

    if ($demo_mode && $accion !== 'insertar') {
      $mensaje='En entorno demo solo se permite crear 1 usuario (sin editar ni eliminar).';
    } elseif ($accion==='insertar') {
      if ($documento===''||!only_digits($documento)||(int)$documento<=0) {
        $mensaje='Documento invalido.';
      } elseif ($nombre==='') {
        $mensaje='Nombre requerido.';
      } elseif ($clave===null) {
        $mensaje='Contrasena requerida.';
      } else {
        $stDemo = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE USUADOCU NOT IN (?, ?)');
        $stDemo->execute(array('admin', DEMO_USUARIO_DOC));
        $creados = (int)$stDemo->fetchColumn();
        if ($demo_mode && $creados >= $max_demo_usuarios) {
          $mensaje='Limite demo alcanzado: solo se permite crear '.$max_demo_usuarios.' usuario.';
        } else {
          try {
            $st=$pdo->prepare("INSERT INTO usuarios (USUADOCU,USUANOMB,USUACLAV,USUAROLE,USUAESTA,USUAFECR) VALUES (?,?,?,?,?,NOW())");
            $st->execute(array($documento,$nombre,$clave,strtolower($rol),$estado));
            $mensaje='Usuario insertado correctamente.';
          } catch (PDOException $e) {
            $mensaje='Error: '.$e->getMessage();
          }
        }
      }
    }
  }

  $usuarios_stmt = $pdo->query("SELECT USUADOCU,USUANOMB,USUAROLE,USUAESTA,USUAFECR FROM usuarios ORDER BY USUANOMB");
  $usuarios = $usuarios_stmt ? $usuarios_stmt->fetchAll(PDO::FETCH_ASSOC) : array();
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Mantenimiento de Usuarios</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body>
<div class="container py-4">
  <?php if ($demo_mode): ?><div class="alert alert-warning">ENTORNO DEMO: solo 1 usuario nuevo permitido.</div><?php endif; ?>
  <?php if (!empty($mensaje)): ?><div class="alert alert-info"><?php echo h($mensaje); ?></div><?php endif; ?>
  <?php if ($acceso_denegado): ?><div class="alert alert-danger">Acceso denegado para este rol.</div><?php else: ?>
  <form method="POST" class="row g-2 mb-3">
    <input type="hidden" name="accion" value="insertar">
    <div class="col-md-3"><input class="form-control" name="USUADOCU" placeholder="Documento" required></div>
    <div class="col-md-3"><input class="form-control" name="USUANOMB" placeholder="Nombre" required></div>
    <div class="col-md-2"><select class="form-select" name="USUAROLE"><option value="consulta">Consulta</option><option value="admin">Admin</option><option value="vendedor">Vendedor</option><option value="despachador">Despachador</option></select></div>
    <div class="col-md-2"><select class="form-select" name="USUAESTA"><option value="A">Activo</option><option value="I">Inactivo</option></select></div>
    <div class="col-md-2"><input type="password" class="form-control" name="USUACLAV" placeholder="Contrasena" required></div>
    <div class="col-12"><button class="btn btn-primary">Crear usuario</button></div>
  </form>
  <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Doc</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>
  <?php if(!empty($usuarios)): foreach($usuarios as $u): ?><tr><td><?php echo h($u['USUADOCU']); ?></td><td><?php echo h($u['USUANOMB']); ?></td><td><?php echo h($u['USUAROLE']); ?></td><td><?php echo h($u['USUAESTA']); ?></td><td><?php echo h($u['USUAFECR']); ?></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="text-center">Sin usuarios</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
</body></html>
