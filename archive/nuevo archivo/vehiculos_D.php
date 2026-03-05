<?php
/* ================== sesión + conexión ================== */
session_start();
date_default_timezone_set('America/Bogota'); // evitar warning en PHP 5.3
require_once __DIR__ . '/conexion.php'; // Debe exponer $pdo (PDO)

/* ================== helpers ================== */
function only_digits($s){ return preg_replace('/\D+/', '', (string)$s); }
function to_upper_spaces($s){ return strtoupper(trim(preg_replace('/\s{2,}/',' ',(string)$s))); }

/* ================== CRUD ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

  // helper para redirigir con mensajes (compatible 5.3)
  $go = function($ok = '', $err = '') {
    $q = array();
    if ($ok !== '')  $q[] = 'ok='  . urlencode($ok);
    if ($err !== '') $q[] = 'err=' . urlencode($err);
    header('Location: vehiculos_D.php' . (empty($q) ? '' : ('?' . implode('&', $q))));
    exit;
  };

  $accion  = $_POST['accion'];
  $placa   = strtoupper(trim((isset($_POST['VEHIPLAC']) ? $_POST['VEHIPLAC'] : '')));
  $placa_o = strtoupper(trim((isset($_POST['VEHIPLAC_ORIG']) ? $_POST['VEHIPLAC_ORIG'] : $placa))); // usado solo en editar
  $marca   = to_upper_spaces((isset($_POST['VEHIMARC']) ? $_POST['VEHIMARC'] : ''));
  $modelo  = trim((isset($_POST['VEHIMODE']) ? $_POST['VEHIMODE'] : ''));
  $cap     = trim((isset($_POST['VEHICAPA']) ? $_POST['VEHICAPA'] : ''));
  $condu   = to_upper_spaces((isset($_POST['VEHINOCO']) ? $_POST['VEHINOCO'] : ''));
  $tdoc = strtoupper(trim((isset($_POST['VEHITDOC']) ? $_POST['VEHITDOC'] : ''))); // CC/CE/PA (opcional)

  $ndoc_raw = (isset($_POST['VEHIDOCU']) ? $_POST['VEHIDOCU'] : '');
  if ($tdoc === 'PA') {
    // Pasaporte: alfanumérico, sin espacios, en mayúsculas
    $ndoc = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $ndoc_raw));
  } else {
    // CC/CE: solo dígitos
    $ndoc = only_digits($ndoc_raw);
  }

  /* Compatibilidad con columna int(11) VEHICOND:
     - Para CC/CE seguimos guardando el mismo número.
     - Para PA (alfanumérico) NO lo guardamos en VEHICOND para evitar error de tipo.
  */
  $docCompat = ($tdoc === 'PA') ? null : $ndoc;

  // Validaciones (excepto eliminar)
  if ($accion !== 'eliminar') {
    if ($placa === '' || !preg_match('/^[A-Z0-9]+$/', $placa)) {
      $go('', '❌ Placa inválida (solo A–Z y 0–9, sin espacios).');
    }
    if ($modelo !== '' && !ctype_digit($modelo)) {
      $go('', '❌ Modelo inválido (solo números enteros).');
    }
    if ($cap !== '' && !is_numeric($cap)) {
      $go('', '❌ Capacidad inválida (numérica).');
    }
    if ($tdoc !== '' && !in_array($tdoc, array('CC','CE','PA'), true)) {
      $go('', '❌ Tipo de documento inválido (use CC, CE o PA).');
    }
    if ($ndoc !== '') {
      if ($tdoc === 'PA') {
        if (!preg_match('/^[A-Z0-9]{6,15}$/', $ndoc)) {
          $go('', '❌ Número de pasaporte inválido (6 a 15 caracteres alfanuméricos).');
        }
      } else { // CC/CE
        if (!preg_match('/^[0-9]{6,15}$/', $ndoc)) {
          $go('', '❌ Número de documento inválido (6 a 15 dígitos).');
        }
      }
    }
  } // <-- CIERRE que faltaba

  try {
    if ($accion === 'insertar') {
      $sql = "INSERT INTO vehiculos
              (VEHIPLAC, VEHIMARC, VEHIMODE, VEHICAPA, VEHINOCO, VEHITDOC, VEHIDOCU, VEHICOND)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(array(
        $placa,
        ($marca  !== '' ? $marca  : null),
        ($modelo !== '' ? $modelo : null),
        ($cap    !== '' ? $cap    : null),
        ($condu  !== '' ? $condu  : null),
        ($tdoc   !== '' ? $tdoc   : null),
        ($ndoc   !== '' ? $ndoc   : null),
        ($docCompat !== '' ? $docCompat : null),
      ));
      $go('✅ Vehículo registrado.');

    } elseif ($accion === 'actualizar') {
      // no permitir cambiar placa en edición
      if ($placa !== $placa_o) {
        $go('', '❌ No se permite cambiar la placa del vehículo en edición.');
      }
      $sql = "UPDATE vehiculos SET
                VEHIMARC = ?,
                VEHIMODE = ?,
                VEHICAPA = ?,
                VEHINOCO = ?,
                VEHITDOC = ?,
                VEHIDOCU = ?,
                VEHICOND = ?
              WHERE VEHIPLAC = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(array(
        ($marca  !== '' ? $marca  : null),
        ($modelo !== '' ? $modelo : null),
        ($cap    !== '' ? $cap    : null),
        ($condu  !== '' ? $condu  : null),
        ($tdoc   !== '' ? $tdoc   : null),
        ($ndoc   !== '' ? $ndoc   : null),
        ($docCompat !== '' ? $docCompat : null),
        $placa_o
      ));
      $go('✅ Vehículo actualizado.');

    } elseif ($accion === 'eliminar') {
      if ($placa === '') $go('', '❌ Placa requerida para eliminar.');
      // Pre-chequeo FK ventas
      $st = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE VENTPLAC = ?");
      $st->execute(array($placa));
      $usos = (int)$st->fetchColumn();
      if ($usos > 0) {
        $go('', "❌ No se puede eliminar: el vehículo tiene {$usos} venta(s) asociadas.");
      }
      $pdo->prepare("DELETE FROM vehiculos WHERE VEHIPLAC = ?")->execute(array($placa));
      $go('✅ Vehículo eliminado.');
    }

  } catch (PDOException $e) {
    if ($e->getCode() === '23000') { // integridad referencial
      $st = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE VENTPLAC = ?");
      $st->execute(array($placa));
      $usos = (int)$st->fetchColumn();
      if ($usos > 0) {
        $go('', "❌ No se puede eliminar: el vehículo tiene {$usos} venta(s) asociadas.");
      }
    }
    $go('', '❌ Error: ' . $e->getMessage());
  }
}

/* ================== listado + cargar edición ================== */
$vehiculos = $pdo->query("SELECT * FROM vehiculos ORDER BY VEHIPLAC")->fetchAll(PDO::FETCH_ASSOC);

$vehiculoEditar = null;
if (isset($_GET['editar'])) {
  $st = $pdo->prepare("SELECT * FROM vehiculos WHERE VEHIPLAC = ?");
  $st->execute(array($_GET['editar']));
  $vehiculoEditar = $st->fetch(PDO::FETCH_ASSOC);
}

// Para el form: prioriza VEHIDOCU; si no existe, usar VEHICOND (compat)
$form_docnum = '';
if ($vehiculoEditar) {
  $form_docnum = (isset($vehiculoEditar['VEHIDOCU']) ? $vehiculoEditar['VEHIDOCU'] : '');
  if ($form_docnum === '' || $form_docnum === null) {
    $form_docnum = (isset($vehiculoEditar['VEHICOND']) ? $vehiculoEditar['VEHICOND'] : '');
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Vehículos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{ --azul-oscuro:#1f6691; --azul:#2980b9; --texto:#333; --celeste:#6dd5fa; --blanco:#fff; --danger:#d9534f; --danger-2:#b52b27; }
body{ min-height:100vh; display:flex; flex-direction:column; background:#fff; color:var(--texto); }

/* Topbar con dropdown usuario */
header{
  background:var(--azul-oscuro); color:#fff; padding:12px 24px;
  display:flex; align-items:center; justify-content:space-between; position:relative;
}
header h2{ margin:0; text-align:center; flex-grow:1; }
.user-btn{ cursor:pointer; display:flex; align-items:center; gap:6px; padding:6px 10px; border-radius:10px; }
.user-menu{
  display:none; position:absolute; right:24px; top:58px; background:#fff; color:#0b3850;
  border:1px solid #e5e7eb; border-radius:12px; padding:6px; min-width:190px;
  box-shadow:0 12px 28px rgba(0,0,0,.20); z-index:9999;
}
.user-item{
  display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:8px;
  font-weight:600; text-decoration:none; color:var(--danger);
}
.user-item:hover{ background:#ffe5e5; color:var(--danger-2); }

/* Resto de estilos */
.user-info{ font-weight:600; }
.main-wrap{ flex:1; }
.card-glass{ background:#fff; border:1px solid #e9f1f8; border-radius:14px; box-shadow:0 8px 18px rgba(0,0,0,.06); }
.btn-primary{ background: var(--azul); border-color: var(--azul); }
.btn-primary:hover{ background: var(--celeste); color: var(--texto); border-color: var(--celeste); }
table thead{ background:#e8f2fb; }
footer{ background:#1f6691; color:#fff; text-align:center; padding:12px; margin-top:auto; }
.badge-A{ background:#28a745; }
.badge-I{ background:#ffc107; color:#212529; }

/* Modal logout */
.modal-header.grad{ background:linear-gradient(135deg,#ffefef,#ffe5e5); border-bottom:1px solid #f3c7c7; }
.modal-footer.soft{ background:#fafbfd; border-top:1px solid #eef1f5; }
</style>
</head>
<body>

<header>
  <a href="mantenimiento_tablas_D.php" class="btn btn-light"><i class="bi bi-arrow-left-circle"></i> Volver</a>

  <h2>Gestión de Vehículos</h2>

  <!-- Usuario + Dropdown -->
  <div class="user-btn" id="userBtn">
    <i class="bi bi-person-fill"></i>
    <?php echo htmlspecialchars((isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado')); ?>
    <div class="user-menu" id="userMenu">
      <a href="#" id="openLogout" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
  </div>
</header>

<div class="main-wrap">
  <div class="container my-4">

    <!-- Mensajes ok/err -->
    <?php
      $ok  = (isset($_GET['ok']) ? $_GET['ok'] : '');
      $err = (isset($_GET['err']) ? $_GET['err'] : '');
      if ($ok || $err):
        $isOk = (bool)$ok; $msg = $isOk ? $ok : $err;
    ?>
      <div class="alert <?php echo $isOk ? 'alert-success' : 'alert-danger'; ?> alert-dismissible shadow-sm border-0" role="alert"
           style="border-left:6px solid <?php echo $isOk ? '#28a745' : '#dc3545'; ?>; background:#fff;">
        <div class="d-flex align-items-center">
          <i class="bi <?php echo $isOk ? 'bi-check-circle-fill text-success' : 'bi-exclamation-octagon-fill text-danger'; ?> fs-4 me-2"></i>
          <div class="fw-semibold"><?php echo htmlspecialchars($msg); ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
    <?php endif; ?>

    <!-- Formulario -->
    <div class="card card-glass p-3 mb-4">
      <h4 class="mb-3"><?php echo $vehiculoEditar ? 'Editar Vehículo' : 'Registrar Vehículo'; ?></h4>
      <form method="POST" class="row g-3">
        <input type="hidden" name="accion" value="<?php echo $vehiculoEditar ? 'actualizar' : 'insertar'; ?>">
        <?php if ($vehiculoEditar): ?>
          <input type="hidden" name="VEHIPLAC_ORIG" value="<?php echo htmlspecialchars($vehiculoEditar['VEHIPLAC']); ?>">
        <?php endif; ?>

        <div class="col-md-3">
          <label class="form-label">Placa</label>
          <input type="text" name="VEHIPLAC" class="form-control" required
                 oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'');"
                 value="<?php echo htmlspecialchars((isset($vehiculoEditar['VEHIPLAC']) ? $vehiculoEditar['VEHIPLAC'] : '')); ?>"
                 <?php echo $vehiculoEditar ? 'readonly' : ''; ?>>
        </div>

        <div class="col-md-3">
          <label class="form-label">Marca</label>
          <input type="text" name="VEHIMARC" class="form-control"
                 oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9 ]/g,'');"
                 value="<?php echo htmlspecialchars((isset($vehiculoEditar['VEHIMARC']) ? $vehiculoEditar['VEHIMARC'] : '')); ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Modelo</label>
          <input type="number" name="VEHIMODE" class="form-control"
                 value="<?php echo htmlspecialchars((isset($vehiculoEditar['VEHIMODE']) ? $vehiculoEditar['VEHIMODE'] : '')); ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Capacidad</label>
          <input type="number" step="0.01" name="VEHICAPA" class="form-control"
                 value="<?php echo htmlspecialchars((isset($vehiculoEditar['VEHICAPA']) ? $vehiculoEditar['VEHICAPA'] : '')); ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Conductor</label>
          <input type="text" name="VEHINOCO" class="form-control"
                 oninput="this.value=this.value.toUpperCase().replace(/[^A-ZÑÁÉÍÓÚÜ ]/g,'');"
                 value="<?php echo htmlspecialchars((isset($vehiculoEditar['VEHINOCO']) ? $vehiculoEditar['VEHINOCO'] : '')); ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Tipo de documento</label>
          <select name="VEHITDOC" id="VEHITDOC" class="form-select">
            <?php $curTdoc = (isset($vehiculoEditar['VEHITDOC']) ? $vehiculoEditar['VEHITDOC'] : ''); ?>
            <option value="">-- Selecciona --</option>
            <option value="CC" <?php echo ($curTdoc==='CC'?'selected':''); ?>>CÉDULA DE CIUDADANÍA</option>
            <option value="CE" <?php echo ($curTdoc==='CE'?'selected':''); ?>>CÉDULA DE EXTRANJERÍA</option>
            <option value="PA" <?php echo ($curTdoc==='PA'?'selected':''); ?>>PASAPORTE</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Número de documento</label>
          <input type="text" name="VEHIDOCU" id="VEHIDOCU" class="form-control"
                 placeholder="CC/CE: 6–15 dígitos · PA: 6–15 A–Z/0–9"
                 minlength="6" maxlength="15"
                 value="<?php echo htmlspecialchars($form_docnum); ?>">
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
          <?php if ($vehiculoEditar): ?>
            <a href="vehiculos_D.php" class="btn btn-secondary">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Tabla -->
    <div class="card card-glass p-3">
      <h4 class="mb-3">Lista de Vehículos</h4>
      <div class="table-responsive">
        <table class="table table-bordered table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th>Placa</th>
              <th>Marca</th>
              <th>Modelo</th>
              <th>Capacidad</th>
              <th>Conductor</th>
              <th>Tipo Doc.</th>
              <th>Número Doc.</th>
              <th style="width: 210px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vehiculos as $v):
              $docShown = (isset($v['VEHIDOCU']) ? $v['VEHIDOCU'] : '');
              if ($docShown === '' || $docShown === null) $docShown = (isset($v['VEHICOND']) ? $v['VEHICOND'] : '');
            ?>
              <tr>
                <td><?php echo htmlspecialchars((isset($v['VEHIPLAC']) ? $v['VEHIPLAC'] : '—')); ?></td>
                <td><?php echo htmlspecialchars((isset($v['VEHIMARC']) ? $v['VEHIMARC'] : '—')); ?></td>
                <td><?php echo htmlspecialchars((isset($v['VEHIMODE']) ? $v['VEHIMODE'] : '—')); ?></td>
                <td><?php echo htmlspecialchars((isset($v['VEHICAPA']) ? $v['VEHICAPA'] : '—')); ?></td>
                <td><?php echo htmlspecialchars((isset($v['VEHINOCO']) ? $v['VEHINOCO'] : '—')); ?></td>
                <td><?php echo htmlspecialchars((isset($v['VEHITDOC']) ? $v['VEHITDOC'] : '—')); ?></td>
                <td><?php echo htmlspecialchars($docShown ? $docShown : '—'); ?></td>
                <td>
                  <div class="d-flex flex-wrap gap-2">
                    <a href="vehiculos_D.php?editar=<?php echo urlencode($v['VEHIPLAC']); ?>"
                       class="btn btn-warning btn-sm">
                       <i class="bi bi-pencil-square"></i> Editar
                    </a>

                    <!-- Botón Eliminar abre modal -->
                    <button class="btn btn-danger btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#confirmDeleteModal"
                            data-placa="<?php echo htmlspecialchars($v['VEHIPLAC']); ?>">
                      <i class="bi bi-trash"></i> Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($vehiculos)): ?>
              <tr><td colspan="8" class="text-center text-muted">No hay vehículos registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Modal Confirmar Eliminación (bonito) -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#1f6691,#2980b9); color:#fff;">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirmar eliminación</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">¿Seguro que deseas eliminar este vehículo?</p>
        <p class="fw-bold mb-2">Placa: <span id="delPlaca">—</span></p>
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
          <input type="hidden" name="VEHIPLAC" id="delPlacaInput">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash"></i> Sí, eliminar
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Confirmar Logout -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header grad">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Confirmar cierre de sesión</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        ¿Seguro que deseas cerrar tu sesión?
      </div>
      <div class="modal-footer soft">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Sí, salir</a>
      </div>
    </div>
  </div>
</div>

<footer>
  <small>&copy; <?php echo date('Y'); ?> - Sistema de Facturación</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pasar datos al modal de eliminación
var modal = document.getElementById('confirmDeleteModal');
if (modal) {
  modal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var placa = button.getAttribute('data-placa') || '—';
    modal.querySelector('#delPlaca').textContent   = placa;
    modal.querySelector('#delPlacaInput').value    = placa;
  });
}

// Dropdown usuario + abrir modal logout
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if(btn && menu){
    btn.onclick = function(e){
      e = e || window.event; if(e.stopPropagation) e.stopPropagation();
      menu.style.display = (menu.style.display === 'block' ? 'none' : 'block');
    };
    document.addEventListener('click', function(ev){
      if(!btn.contains(ev.target)) menu.style.display='none';
    }, false);
  }

  var openLogout = document.getElementById('openLogout');
  if(openLogout){
    openLogout.onclick = function(e){
      e.preventDefault();
      var m = new bootstrap.Modal(document.getElementById('logoutModal'));
      m.show();
    };
  }
})();

// Máscara dinámica del número de documento según el tipo (CC/CE/PA)
(function(){
  var sel = document.getElementById('VEHITDOC');
  var inp = document.getElementById('VEHIDOCU');
  function enforceMask(){
    var t = (sel && sel.value) ? sel.value.toUpperCase() : '';
    if (!inp) return;
    inp.value = (inp.value || '');
    if (t === 'PA') {
      // Permitir A-Z y 0-9
      inp.value = inp.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
      inp.placeholder = 'PA: 6–15 A–Z/0–9';
    } else {
      // Solo dígitos
      inp.value = inp.value.replace(/[^0-9]/g,'');
      inp.placeholder = 'CC/CE: 6–15 dígitos';
    }
  }
  if (sel && inp){
    sel.addEventListener('change', enforceMask);
    inp.addEventListener('input', enforceMask);
    enforceMask(); // inicial
  }
})();
</script>
</body>
</html>
