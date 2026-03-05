<?php
/* demo_config.php
   Global config for public demo mode.
*/

if (!defined('DEMO_PUBLICO')) define('DEMO_PUBLICO', true);
if (!defined('DEMO_AUTOLOGIN')) define('DEMO_AUTOLOGIN', false);
if (!defined('DEMO_USUARIO_DOC')) define('DEMO_USUARIO_DOC', 'demo');
if (!defined('DEMO_USUARIO_NOMBRE')) define('DEMO_USUARIO_NOMBRE', 'Usuario Demo');
if (!defined('DEMO_USUARIO_ROL')) define('DEMO_USUARIO_ROL', 'admin');

if (!defined('DEMO_MAX_PRODUCTOS')) define('DEMO_MAX_PRODUCTOS', 5);
if (!defined('DEMO_MAX_VEHICULOS')) define('DEMO_MAX_VEHICULOS', 2);
if (!defined('DEMO_MAX_FACTURAS')) define('DEMO_MAX_FACTURAS', 3);
if (!defined('DEMO_MAX_USUARIOS_CREADOS')) define('DEMO_MAX_USUARIOS_CREADOS', 1);

if (!defined('DEMO_RESET_ON_LOGOUT')) define('DEMO_RESET_ON_LOGOUT', true);
if (!defined('DEMO_BLOQUEAR_EXPORTES')) define('DEMO_BLOQUEAR_EXPORTES', true);

if (!function_exists('demo_activo')) {
    function demo_activo() {
        return (defined('DEMO_PUBLICO') && DEMO_PUBLICO);
    }
}

if (!function_exists('demo_banner_html')) {
    function demo_banner_html() {
        if (!demo_activo()) return '';
        return '<div style="background:#fff3cd;border:1px solid #ffe69c;color:#664d03;padding:8px 12px;text-align:center;font-weight:700;">ENTORNO DEMO - Datos temporales de prueba.</div>';
    }
}

if (!function_exists('demo_count_rows')) {
    function demo_count_rows($pdo, $table, $whereSql, $params) {
        $sql = 'SELECT COUNT(*) AS c FROM ' . $table;
        if ($whereSql !== '') $sql .= ' WHERE ' . $whereSql;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }
}

if (!function_exists('demo_limite_alcanzado')) {
    function demo_limite_alcanzado($pdo, $table, $max, $whereSql, $params) {
        return demo_count_rows($pdo, $table, $whereSql, $params) >= (int)$max;
    }
}

if (!function_exists('demo_table_exists')) {
    function demo_table_exists($pdo, $table) {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $st->execute(array($table));
            return ((int)$st->fetchColumn()) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('demo_column_exists')) {
    function demo_column_exists($pdo, $table, $column) {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $st->execute(array($table, $column));
            return ((int)$st->fetchColumn()) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('demo_safe_exec')) {
    function demo_safe_exec($pdo, $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) { }
    }
}

if (!function_exists('demo_reset_data')) {
    function demo_reset_data($pdo) {
        if (!demo_activo() || !defined('DEMO_RESET_ON_LOGOUT') || !DEMO_RESET_ON_LOGOUT) return;

        $inTx = false;
        try {
            $pdo->beginTransaction();
            $inTx = true;
        } catch (Exception $e) {
            $inTx = false;
        }

        demo_safe_exec($pdo, 'SET FOREIGN_KEY_CHECKS=0');

        // Delete in dependency-safe order, only if table exists.
        $tablesDelete = array('pagos', 'facturas', 'ventas_det', 'ventas', 'kardex', 'vehiculos', 'productos', 'clientes');
        foreach ($tablesDelete as $t) {
            if (demo_table_exists($pdo, $t)) {
                demo_safe_exec($pdo, 'DELETE FROM ' . $t);
            }
        }

        // Keep only base users.
        if (demo_table_exists($pdo, 'usuarios')) {
            if (demo_column_exists($pdo, 'usuarios', 'USUADOCU')) {
                try {
                    $st = $pdo->prepare('DELETE FROM usuarios WHERE USUADOCU NOT IN (?, ?)');
                    $st->execute(array('admin', DEMO_USUARIO_DOC));
                } catch (Exception $e) { }
            } elseif (demo_column_exists($pdo, 'usuarios', 'usuario')) {
                try {
                    $st = $pdo->prepare('DELETE FROM usuarios WHERE usuario NOT IN (?, ?)');
                    $st->execute(array('admin', DEMO_USUARIO_DOC));
                } catch (Exception $e) { }
            }
        }

        // Reset autoincrement where available.
        $tablesAI = array('ventas', 'pagos', 'ventas_det', 'productos', 'kardex', 'clientes');
        foreach ($tablesAI as $t) {
            if (demo_table_exists($pdo, $t)) {
                demo_safe_exec($pdo, 'ALTER TABLE ' . $t . ' AUTO_INCREMENT = 1');
            }
        }

        demo_safe_exec($pdo, 'SET FOREIGN_KEY_CHECKS=1');

        if ($inTx) {
            try {
                $pdo->commit();
            } catch (Exception $e) {
                try { $pdo->rollBack(); } catch (Exception $x) { }
            }
        }
    }
}
