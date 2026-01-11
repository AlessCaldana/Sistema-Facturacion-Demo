<?php
session_start();
require_once __DIR__ . '/conexion.php'; // Debe exponer $pdo (PDO)

/* ===== Helpers ===== */
function h($v){
  $v = isset($v) ? $v : '';
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function is_digit_str($s){
  return ($s !== '' && preg_match('/^\d+$/', $s));
}

/* ===== Estado inicial ===== */
$ok  = isset($_GET['ok'])  ? $_GET['ok']  : '';
$err = isset($_GET['err']) ? $_GET['err'] : '';

$errores = array();
$mensaje_error_global = '';

$old = array(
  'CLIEDOCU' => '',
  'CLIENOMB' => '',
  'CLIEDIRE' => '',
  'CLIETELE' => '',
  'CLIEMAIL' => '',
);

/* ===== Redirigir con mensajes (compatible 5.3) ===== */
function go_redirect($okMsg = '', $errMsg = '') {
  $q = array();
  if ($okMsg  !== '') $q[] = 'ok='  . urlencode($okMsg);
  if ($errMsg !== '') $q[] = 'err=' . urlencode($errMsg);
  header('Location: clientes_D.php' . (empty($q) ? '' : ('?' . implode('&', $q))));
  exit;
}

/* ===== Cargar para edición ===== */
$clienteEditar = null;
if (isset($_GET['editar'])) {
  $stmt = $pdo->prepare("SELECT * FROM clientes WHERE CLIEDOCU = ?");
  $stmt->execute(array($_GET['editar']));
  $clienteEditar = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($clienteEditar) {
    foreach ($old as $k => $dummy) {
      if (isset($clienteEditar[$k])) $old[$k] = $clienteEditar[$k];
    }
  }
}

/* ===== CRUD ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
  $accion = $_POST['accion'];

  foreach ($old as $k => $dummy) {
    $old[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : '';
  }

  if ($accion === 'insertar' || $accion === 'actualizar') {
    if ($old['CLIEDOCU']==='' || !is_digit_str($old['CLIEDOCU']) || strlen($old['CLIEDOCU'])>20) {
      $errores['CLIEDOCU'] = 'Documento inválido: solo dígitos (máx. 20).';
    }
    if ($old['CLIENOMB']==='') {
      $errores['CLIENOMB'] = 'El nombre es obligatorio.';
    }
    if ($old['CLIETELE']!=='') {
      if (!is_digit_str($old['CLIETELE']) || strlen($old['CLIETELE'])<7 || strlen($old['CLIETELE'])>15) {
        $errores['CLIETELE'] = 'Teléfono inválido: 7 a 15 dígitos.';
      }
    }
    if ($old['CLIEMAIL']!=='') {
      if (!filter_var($old['CLIEMAIL'], FILTER_VALIDATE_EMAIL)) {
        $errores['CLIEMAIL'] = 'Correo electrónico inválido.';
      }
    }
  }

  if (empty($errores)) {
    try {
      if ($accion === 'insertar') {
        $stmt = $pdo->prepare("INSERT INTO clientes (CLIEDOCU, CLIENOMB, CLIEDIRE, CLIETELE, CLIEMAIL)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(array($old['CLIEDOCU'], $old['CLIENOMB'], $old['CLIEDIRE'], $old['CLIETELE'], $old['CLIEMAIL']));
        go_redirect('✅ Cliente registrado.');

      } elseif ($accion === 'actualizar') {
        $stmt = $pdo->prepare("UPDATE clientes
                               SET CLIENOMB = ?, CLIEDIRE = ?, CLIETELE = ?, CLIEMAIL = ?
                               WHERE CLIEDOCU = ?");
        $stmt->execute(array($old['CLIENOMB'], $old['CLIEDIRE'], $old['CLIETELE'], $old['CLIEMAIL'], $old['CLIEDOCU']));
        go_redirect('✅ Cliente actualizado.');

      } elseif ($accion === 'eliminar') {
        $doc = isset($_POST['CLIEDOCU']) ? trim($_POST['CLIEDOCU']) : '';
        $check = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE FACTCLIE = ?");
        $check->execute(array($doc));
        $tiene = (int)$check->fetchColumn();
        if ($tiene > 0) {
          go_redirect('', "❌ No se puede borrar: tiene {$tiene} factura(s).");
        }
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE CLIEDOCU = ?");
        $stmt->execute(array($doc));
        go_redirect('✅ Cliente eliminado.');
      }
    } catch (PDOException $e) {
      $msg = ($e->getCode()==='23000') ? '❌ Ya existe un cliente con ese documento.' : ('❌ Error: '.$e->getMessage());
      go_redirect('', $msg);
    }
  }
}

/* ===== Listado ===== */
$clientesStmt = $pdo->query("SELECT * FROM clientes ORDER BY CLIENOMB");
$clientes     = $clientesStmt ? $clientesStmt->fetchAll(PDO::FETCH_ASSOC) : array();
$accionForm   = $clienteEditar ? 'actualizar' : 'insertar';
if (isset($_POST['accion'])) $accionForm = $_POST['accion'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Clientes</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{
  --azul:#1f6691; --txt:#0b3850;
  --danger:#d9534f; --danger-2:#b52b27;
}
body{ min-height:100vh; display:flex; flex-direction:column; background:#fff; color:#333; }
header{
  background:var(--azul); color:#fff; padding:12px 16px;
  display:grid; grid-template-columns:auto 1fr auto; align-items:center;
}
header h2{ margin:0; text-align:center; }
footer{ background:var(--azul); color:#fff; text-align:center; padding:12px; margin-top:auto; }
.card{ border-radius:14px; box-shadow:0 8px 18px rgba(0,0,0,.08); }
input.no-spin::-webkit-outer-spin-button,
input.no-spin::-webkit-inner-spin-button{ -webkit-appearance:none; margin:0; }
input.no-spin{ -moz-appearance:textfield; }
.is-invalid{ border-width:2px; }

/* === Usuario (dropdown) === */
.user-btn{
  justify-self:end; display:inline-flex; align-items:center; gap:8px; cursor:pointer;
  color:#fff; padding:6px 10px; border-radius:10px; position:relative;
}
.user-avatar{
  width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center;
  background:rgba(255,255,255,.15);
}
.user-menu{
  position:absolute; right:0; top:100%; margin-top:8px; min-width:190px; display:none;
  background:#fff; color:var(--txt); border:1px solid #e5e7eb; border-radius:12px; padding:6px;
  box-shadow:0 12px 28px rgba(0,0,0,.20); z-index:10000;
}
.user-item{ display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:10px;
  color:var(--txt); text-decoration:none; font-weight:600; }
.user-item:hover{ background:#f5f7fb; }
/* Cerrar sesión rojo */
.user-item.logout{ color:var(--danger) !important; font-weight:700; }
.user-item.logout:hover{ background:#ffe5e5 !important; color:var(--danger-2) !important; }

/* === Modal logout bonito (usaremos Bootstrap + estilos) === */
.modal-header.grad{
  background:linear-gradient(135deg,#ffefef,#ffe5e5); border-bottom:1px solid #f3c7c7;
}
.modal-header .icon-wrap{
  width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center;
  background:#ffe5e5; color:var(--danger); margin-right:8px;
}
.modal-footer.soft{ background:#fafbfd; border-top:1px solid #eef1f5; }
</style>
</head>
<body>

<header>
  <a href="mantenimiento_tablas_D.php" class="btn btn-light"><i class="bi bi-arrow-left-circle"></i> Volver</a>
  <h2>Gestión de Clientes</h2>

  <!-- Dropdown usuario con Cerrar sesión -->
  <div class="user-btn" id="userBtn">
    <div class="user-avatar"><i class="bi bi-person-fill" style="color:#fff;"></i></div>
    <span><?php echo h(isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado'); ?></span>
    <div class="user-menu" id="userMenu">
      <a href="logout.php" data-href="logout.php" class="user-item logout" id="btnOpenLogout">
        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
      </a>
    </div>
  </div>
</header>

<div class="container my-4 flex-grow-1">

  <?php if ($ok || $err): ?>
    <div class="alert <?php echo $ok ? 'alert-success' : 'alert-danger'; ?> alert-dismissible shadow-sm border-0" role="alert"
         style="border-left:6px solid <?php echo $ok ? '#28a745' : '#dc3545'; ?>; background:#fff;">
      <div class="d-flex align-items-center">
        <i class="bi <?php echo $ok ? 'bi-check-circle-fill text-success' : 'bi-exclamation-octagon-fill text-danger'; ?> fs-4 me-2"></i>
        <div class="fw-semibold"><?php echo h($ok ? $ok : $err); ?></div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endif; ?>

  <?php if ($mensaje_error_global): ?>
    <div class="alert alert-danger"><?php echo h($mensaje_error_global); ?></div>
  <?php endif; ?>

  <!-- Formulario -->
  <div class="card p-3 mb-4">
    <h4 class="mb-3"><?php echo ($accionForm==='actualizar') ? 'Editar Cliente' : 'Registrar Cliente'; ?></h4>

    <form method="POST" class="row g-3" novalidate>
      <input type="hidden" name="accion" value="<?php echo h($accionForm); ?>">

      <div class="col-md-4">
        <label class="form-label">Documento</label>
        <input type="text" inputmode="numeric" pattern="\d*" maxlength="20"
               name="CLIEDOCU"
               class="form-control no-spin<?php echo isset($errores['CLIEDOCU']) ? ' is-invalid' : ''; ?>"
               value="<?php echo h($old['CLIEDOCU']); ?>"
               <?php echo ($accionForm==='actualizar') ? 'readonly' : ''; ?>
               oninput="digitsFilter(this)" required>
        <?php if (isset($errores['CLIEDOCU'])): ?>
          <div class="invalid-feedback"><?php echo h($errores['CLIEDOCU']); ?></div>
        <?php endif; ?>
      </div>

      <div class="col-md-4">
        <label class="form-label">Nombre</label>
        <input type="text" name="CLIENOMB"
               class="form-control<?php echo isset($errores['CLIENOMB']) ? ' is-invalid' : ''; ?>"
               value="<?php echo h($old['CLIENOMB']); ?>" required>
        <?php if (isset($errores['CLIENOMB'])): ?>
          <div class="invalid-feedback"><?php echo h($errores['CLIENOMB']); ?></div>
        <?php endif; ?>
      </div>

      <div class="col-md-4">
        <label class="form-label">Dirección</label>
        <input type="text" name="CLIEDIRE" class="form-control" value="<?php echo h($old['CLIEDIRE']); ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input type="text" inputmode="numeric" pattern="\d{7,15}" maxlength="15"
               name="CLIETELE"
               class="form-control no-spin<?php echo isset($errores['CLIETELE']) ? ' is-invalid' : ''; ?>"
               value="<?php echo h($old['CLIETELE']); ?>"
               oninput="digitsFilter(this)" placeholder="Solo dígitos (7 a 15)">
        <?php if (isset($errores['CLIETELE'])): ?>
          <div class="invalid-feedback"><?php echo h($errores['CLIETELE']); ?></div>
        <?php endif; ?>
      </div>

      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="CLIEMAIL"
               class="form-control<?php echo isset($errores['CLIEMAIL']) ? ' is-invalid' : ''; ?>"
               value="<?php echo h($old['CLIEMAIL']); ?>">
        <?php if (isset($errores['CLIEMAIL'])): ?>
          <div class="invalid-feedback"><?php echo h($errores['CLIEMAIL']); ?></div>
        <?php endif; ?>
      </div>

      <div class="col-md-4 align-self-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
        <?php if ($accionForm==='actualizar'): ?>
          <a href="clientes_D.php" class="btn btn-secondary">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Tabla -->
  <div class="card p-3">
    <h4 class="mb-3">Lista de Clientes</h4>
    <div class="table-responsive">
      <table class="table table-bordered table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Nombre</th>
            <th>Dirección</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th style="width: 180px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($clientes)): ?>
          <?php foreach ($clientes as $c): ?>
          <tr>
            <td><?php echo h($c['CLIEDOCU']); ?></td>
            <td><?php echo h($c['CLIENOMB']); ?></td>
            <td><?php echo h($c['CLIEDIRE']); ?></td>
            <td><?php echo h($c['CLIETELE']); ?></td>
            <td><?php echo h($c['CLIEMAIL']); ?></td>
            <td>
              <div class="d-flex flex-wrap gap-2">
                <a href="clientes_D.php?editar=<?php echo urlencode($c['CLIEDOCU']); ?>" class="btn btn-warning btn-sm">
                  <i class="bi bi-pencil-square"></i> Editar
                </a>
                <button class="btn btn-danger btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#confirmDeleteModal"
                        data-doc="<?php echo h($c['CLIEDOCU']); ?>"
                        data-name="<?php echo h($c['CLIENOMB']); ?>">
                  <i class="bi bi-trash"></i> Eliminar
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted">Sin registros.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Confirmar Eliminación (tu modal) -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#1f6691,#2980b9); color:#fff;">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirmar eliminación</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">¿Seguro que deseas eliminar este cliente?</p>
        <p class="fw-bold mb-2" id="delName">—</p>
        <p class="small text-muted">Documento: <span id="delDoc">—</span></p>
        <div class="alert alert-warning d-flex align-items-center mb-0" role="alert">
          <i class="bi bi-info-circle me-2"></i>
          Esta acción no se puede deshacer.
        </div>
      </div>
      <div class="modal-footer" style="background:#f7fbff; border-top:1px solid #e6f0fb;">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <form method="POST" class="m-0">
          <input type="hidden" name="accion" value="eliminar">
          <input type="hidden" name="CLIEDOCU" id="delDocInput">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash"></i> Sí, eliminar
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Logout BONITO -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header grad">
        <div class="icon-wrap"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <h5 class="modal-title">¿Cerrar sesión?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p>Vas a salir del sistema. Si continúas, se cerrará tu sesión actual y volverás al inicio de sesión.</p>
      </div>
      <div class="modal-footer soft">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Sí, cerrar sesión</a>
      </div>
    </div>
  </div>
</div>

<footer>
  <small>&copy; <?php echo date('Y'); ?> - Sistema de Facturación</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filtro de dígitos
function digitsFilter(el){ el.value = el.value.replace(/\D+/g,''); }

// Modal eliminar: pasar datos
var modal = document.getElementById('confirmDeleteModal');
if (modal) {
  modal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget || event.srcElement;
    var doc  = button.getAttribute('data-doc')  || '—';
    var name = button.getAttribute('data-name') || 'Este cliente';
    modal.querySelector('#delName').textContent = name;
    modal.querySelector('#delDoc').textContent  = doc;
    modal.querySelector('#delDocInput').value   = doc;
  });
}

// Dropdown usuario
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if(!btn || !menu) return;

  function openM(){ menu.style.display='block'; }
  function closeM(){ menu.style.display='none'; }
  function toggleM(e){ if(e) e.preventDefault(); (menu.style.display==='block')?closeM():openM(); }

  btn.onclick = toggleM;
  document.addEventListener('click', function(e){ if(!btn.contains(e.target)) closeM(); }, false);
})();

// Abrir modal de logout desde el item del menú
(function(){
  var openBtn = document.getElementById('btnOpenLogout');
  if(!openBtn) return;
  openBtn.addEventListener('click', function(e){
    e.preventDefault();
    var myModal = new bootstrap.Modal(document.getElementById('logoutModal'));
    myModal.show();
  }, false);
})();
</script>
</body>
</html>
