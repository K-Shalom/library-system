<?php
/**
 * _render_child.php — runs in a SEPARATE PHP CLI process so header()/exit() inside
 * the target pages (e.g. redirect() calling exit) only terminates this child, not the parent.
 *
 * Arguments via env vars:
 *   RENDER_SCRIPT       absolute path to PHP file to render
 *   RENDER_METHOD       GET / POST
 *   RENDER_URI          REQUEST_URI value (e.g. /library-system/foo.php)
 *   RENDER_SESS_DIR     session save_path directory shared with siblings
 *   RENDER_SESS_NAME    session name (shared)
 *   RENDER_SESS_ID      session id (shared across children to preserve login)
 *   RENDER_POST_JSON    JSON-encoded $_POST data
 */
$script   = getenv('RENDER_SCRIPT');
$method   = getenv('RENDER_METHOD')   ?: 'GET';
$uri      = getenv('RENDER_URI')      ?: '/library-system/index.php';
$sessDir  = getenv('RENDER_SESS_DIR');
$sessName = getenv('RENDER_SESS_NAME') ?: 'CIRCLIB';
$sessId   = getenv('RENDER_SESS_ID')   ?: '';
$postJson = getenv('RENDER_POST_JSON') ?: '[]';
if ($script === false || !is_file($script)) {
    fwrite(STDERR, "render_child: missing/invalid RENDER_SCRIPT\n");
    exit(1);
}
$_ENV = []; // clean

define('BASE_URL', '/library-system');
$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['REQUEST_URI']    = $uri;
$_SERVER['SCRIPT_NAME']    = $uri;
$_SERVER['PHP_SELF']       = $uri;
$_SERVER['HTTP_HOST']      = '127.0.0.1:8765';
$_SERVER['SERVER_NAME']    = '127.0.0.1';
$_SERVER['SERVER_PORT']    = '8765';
$_SERVER['HTTPS']          = '';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTP_REFERER']   = 'http://127.0.0.1:8765' . $uri;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cache_limiter', '');
session_cache_limiter('');
if ($sessDir) { @mkdir($sessDir, 0777, true); session_save_path($sessDir); }
session_name($sessName);
if ($sessId !== '') session_id($sessId);
session_start();
if (!isset($_SESSION['librarian_id'])) {
    $_SESSION['librarian_id']   = 1;
    $_SESSION['librarian_name'] = 'Library Admin';
    $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
}

$post = json_decode($postJson, true) ?: [];
$_POST    = $post;
$_REQUEST = array_merge($_REQUEST ?? [], $post);

ob_start();
require $script;
$buf = ob_get_clean();
echo $buf;
exit(0);
