<?php
session_start();
require_once 'conexion.php'; // Debe definir $pdo (PDO)

if (!ini_get('date.timezone')) { date_default_timezone_set('America/Bogota'); }

$mensaje = "";

/* ====== Polyfill para hash_equals en PHP < 5.6 (tiempo constante) ====== */
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

/* ====== Helper seguro para verificar contraseñas en PHP 5.3 ====== */
function verify_password_53($plain, $stored) {
    $plain  = (string)$plain;
    $stored = trim((string)$stored);

    // Normaliza prefijos heredados tipo "sha1$" o "md5$"
    if (strpos($stored, 'sha1$') === 0) $stored = 'sha1:' . substr($stored, 5);
    if (strpos($stored, 'md5$')  === 0) $stored = 'md5:'  . substr($stored, 4);

    // Formatos explícitos "sha1:hash" y "md5:hash"
    if (strpos($stored, 'sha1:') === 0) return sha1($plain) === substr($stored, 5);
    if (strpos($stored, 'md5:')  === 0) return md5($plain)  === substr($stored, 4);

    // Bcrypt ($2a$, $2y$, etc.) mediante crypt()
    if (strncmp($stored, '$2', 2) === 0) {
        $calc = crypt($plain, $stored);
        return is_string($calc) && hash_equals($stored, $calc);
    }

    // Hashes "sueltos" (sin prefijo)
    if (preg_match('/^[a-f0-9]{40}$/i', $stored)) {
        return hash_equals(strtolower($stored), sha1($plain));
    }
    if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
        return hash_equals(strtolower($stored), md5($plain));
    }

    // Plano (no recomendado, legado)
    return hash_equals($stored, $plain);
}

/* ====== Resolver logo ====== */
$scriptBase = rtrim(dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''), '/');
$diskBase   = __DIR__;

$logoCandidates = array(
  'img/LogoEMDT.png','img/LogoEMD.png','img/logoemdt.png','img/logoemd.png',
  'img/LogoEMDT.jpg','img/LogoEMD.jpg','img/logoemdt.jpg','img/logoemd.jpg',
  'img/LogoEMDT.jpeg','img/LogoEMD.jpeg','img/logoemdt.jpeg','img/logoemd.jpeg'
);

$logoWeb = null;
foreach ($logoCandidates as $rel) {
    if (file_exists($diskBase . '/' . $rel)) {
        $logoWeb = $scriptBase . '/' . $rel;
        break;
    }
}
if ($logoWeb === null) {
    $logoWeb = 'data:image/svg+xml;utf8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="260" height="140"><rect width="100%" height="100%" fill="#e6f3fb"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" fill="#2980b9" font-family="Arial" font-size="22">EMDUPAR</text></svg>'
    );
}

/* ====== POST: login ====== */
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // (compat 5.3) sin null-coalesce
    $usuario = trim(isset($_POST['usuario']) ? $_POST['usuario'] : '');
    $clave   = isset($_POST['clave']) ? $_POST['clave'] : '';

    if ($usuario === '' || $clave === '') {
        $mensaje = "❌ Usuario y contraseña son obligatorios.";
    } else {
        $sql = "SELECT USUADOCU, USUACLAV, USUAROLE
                FROM usuarios
                WHERE USUADOCU = ? AND USUAESTA = 'A'
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($usuario));

        if ($stmt->rowCount() > 0) {
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if (verify_password_53($clave, $fila['USUACLAV'])) {
                $_SESSION['usuario'] = $fila['USUADOCU'];
                $_SESSION['rol']     = strtolower($fila['USUAROLE']);
                header("Location: menu_principal.php");
                exit();
            } else {
                $mensaje = "❌ Contraseña incorrecta.";
            }
        } else {
            $mensaje = "❌ Usuario no encontrado o inactivo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login Operador - EMDUPAR</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  :root{ --azul:#2980b9; --azul-osc:#1f6691; --celeste:#6dd5fa; }

  body{
    font-family:"Segoe UI", Arial;
    margin:0; padding:0;
    background: linear-gradient(135deg, var(--azul), var(--celeste), #ffffff);
    height:100vh; display:flex; justify-content:center; align-items:center;
  }

  .login-box{
    width: 90%;
    max-width:350px; /* ✅ CUADRO MÁS PEQUEÑO */
    background:#fff; padding:28px 22px;
    border-radius:14px; box-shadow:0 10px 25px rgba(0,0,0,.15);
    text-align:center;
  }

  .logo{
    width:160px; /* ✅ LOGO MÁS PEQUEÑO */
    margin:6px auto 14px auto; display:block;
  }

  label{
    display:block; text-align:left; margin:10px 2px 6px; font-weight:700;
  }

  input{
    width:100%;
    box-sizing:border-box; /* ✅ EVITA QUE SE SALGA */
    padding:11px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    font-size:15px; background:#fbfdff;
  }

  input:focus{ border-color: var(--azul); box-shadow:0 0 0 3px rgba(41,128,185,.15); }

  .btn{
    width:100%; margin-top:14px; padding:12px; border:none; border-radius:10px;
    background: var(--azul); color:#fff; font-weight:800; font-size:16px; cursor:pointer;
  }
  .btn:hover{ background: var(--azul-osc); }

  .mensaje{ margin-top:14px; color:#d92c2c; font-weight:700; }
</style>
</head>
<body>
  <div class="login-box">
    <img class="logo" src="<?php echo htmlspecialchars($logoWeb); ?>?v=2" alt="Logo EMDUPAR">
    <h2>Inicio de Sesión</h2>

    <form method="POST" novalidate>
      <label for="usuario">Usuario:</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" required>

      <label for="clave">Contraseña:</label>
      <input type="password" id="clave" name="clave" autocomplete="current-password" required>

      <button class="btn" type="submit">Ingresar</button>
    </form>

    <?php if (!empty($mensaje)): ?>
      <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
