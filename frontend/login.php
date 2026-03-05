<?php
session_start();
require_once 'conexion.php';
require_once __DIR__ . '/demo_config.php';

if (!ini_get('date.timezone')) { date_default_timezone_set('America/Bogota'); }

$mensaje = '';

if (defined('DEMO_PUBLICO') && DEMO_PUBLICO && (!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '')) {
    $_SESSION['usuario'] = (defined('DEMO_USUARIO_DOC') ? DEMO_USUARIO_DOC : 'demo');
    $_SESSION['nombre']  = (defined('DEMO_USUARIO_NOMBRE') ? DEMO_USUARIO_NOMBRE : 'Usuario Demo');
    $_SESSION['rol']     = strtolower((defined('DEMO_USUARIO_ROL') ? DEMO_USUARIO_ROL : 'admin'));
    header('Location: menu_principal.php');
    exit();
}

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string) || !is_string($user_string)) return false;
        $klen = strlen($known_string);
        if ($klen !== strlen($user_string)) return false;
        $res = 0;
        for ($i = 0; $i < $klen; $i++) {
            $res |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        return $res === 0;
    }
}

function verify_password_53($plain, $stored) {
    $plain  = (string)$plain;
    $stored = trim((string)$stored);

    if (strpos($stored, 'sha1$') === 0) $stored = 'sha1:' . substr($stored, 5);
    if (strpos($stored, 'md5$')  === 0) $stored = 'md5:'  . substr($stored, 4);

    if (strpos($stored, 'sha1:') === 0) return sha1($plain) === substr($stored, 5);
    if (strpos($stored, 'md5:')  === 0) return md5($plain)  === substr($stored, 4);

    if (strncmp($stored, '$2', 2) === 0) {
        $calc = crypt($plain, $stored);
        return is_string($calc) && hash_equals($stored, $calc);
    }

    if (preg_match('/^[a-f0-9]{40}$/i', $stored)) return hash_equals(strtolower($stored), sha1($plain));
    if (preg_match('/^[a-f0-9]{32}$/i', $stored)) return hash_equals(strtolower($stored), md5($plain));

    return hash_equals($stored, $plain);
}

function get_table_columns($pdo, $table) {
    $cols = array();
    $q = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    if ($q) {
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (isset($r['Field'])) $cols[] = $r['Field'];
        }
    }
    return $cols;
}

function pick_column($cols, $candidates) {
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

function is_active_value($v) {
    $s = strtoupper(trim((string)$v));
    return ($s === '' || $s === 'A' || $s === '1' || $s === 'ACTIVO' || $s === 'ACTIVE' || $s === 'TRUE');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim(isset($_POST['usuario']) ? $_POST['usuario'] : '');
    $clave   = isset($_POST['clave']) ? $_POST['clave'] : '';

    if ($usuario === '' || $clave === '') {
        $mensaje = 'Usuario y contrasena son obligatorios.';
    } else {
        try {
            $cols = get_table_columns($pdo, 'usuarios');

            $colUser   = pick_column($cols, array('USUADOCU', 'usuario', 'username', 'user', 'login'));
            $colPass   = pick_column($cols, array('USUACLAV', 'clave', 'password', 'pass', 'contrasena', 'contrasenia'));
            $colRole   = pick_column($cols, array('USUAROLE', 'rol', 'role'));
            $colEstado = pick_column($cols, array('USUAESTA', 'estado', 'status', 'activo'));

            if (!$colUser || !$colPass) {
                $mensaje = 'La tabla usuarios no tiene columnas compatibles para login.';
            } else {
                $sql = 'SELECT `' . $colUser . '` AS usr, `' . $colPass . '` AS pwd';
                if ($colRole) {
                    $sql .= ', `' . $colRole . '` AS rol';
                } else {
                    $sql .= ", 'admin' AS rol";
                }
                if ($colEstado) {
                    $sql .= ', `' . $colEstado . '` AS est';
                } else {
                    $sql .= ", '' AS est";
                }
                $sql .= ' FROM usuarios WHERE `' . $colUser . '` = ? LIMIT 1';

                $stmt = $pdo->prepare($sql);
                $stmt->execute(array($usuario));
                $fila = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($fila) {
                    if (!is_active_value(isset($fila['est']) ? $fila['est'] : '')) {
                        $mensaje = 'Usuario inactivo.';
                    } elseif (verify_password_53($clave, isset($fila['pwd']) ? $fila['pwd'] : '')) {
                        $_SESSION['usuario'] = isset($fila['usr']) ? $fila['usr'] : $usuario;
                        $_SESSION['rol']     = strtolower(trim((string)(isset($fila['rol']) ? $fila['rol'] : 'admin')));
                        header('Location: menu_principal.php');
                        exit();
                    } else {
                        $mensaje = 'Contrasena incorrecta.';
                    }
                } else {
                    $mensaje = 'Usuario no encontrado.';
                }
            }
        } catch (Exception $e) {
            $mensaje = 'Error de autenticacion: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  html,body{margin:0 !important;padding:0 !important;} 
  header,.topbar{margin-top:0 !important;}
  body{font-family:Segoe UI,Arial;margin:0;background:linear-gradient(135deg,#2980b9,#6dd5fa,#fff);height:100vh;display:flex;justify-content:center;align-items:center}
  .login-box{width:90%;max-width:350px;background:#fff;padding:28px 22px;border-radius:14px;box-shadow:0 10px 25px rgba(0,0,0,.15);text-align:center}
  label{display:block;text-align:left;margin:10px 2px 6px;font-weight:700}
  input{width:100%;box-sizing:border-box;padding:11px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px;background:#fbfdff}
  .btn{width:100%;margin-top:14px;padding:12px;border:none;border-radius:10px;background:#2980b9;color:#fff;font-weight:800;font-size:16px;cursor:pointer}
  .mensaje{margin-top:14px;color:#d92c2c;font-weight:700}
</style>
</head>
<body>
  <div class="login-box">
    <h2>Inicio de Sesion</h2>
    <form method="POST" novalidate>
      <label for="usuario">Usuario:</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" required>
      <label for="clave">Contrasena:</label>
      <input type="password" id="clave" name="clave" autocomplete="current-password" required>
      <button class="btn" type="submit">Ingresar</button>
    </form>
    <?php if (!empty($mensaje)): ?>
      <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>
  </div>
</body>
</html>

