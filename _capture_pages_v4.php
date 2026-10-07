<?php
/**
 * _capture_pages_v4.php
 * Session strategy: disable cookies & auto-cache limiter, start ONE shared session
 * for ALL renders. No session headers will be sent in CLI mode so no "headers already sent".
 */
define('BASE_URL', '/library-system');
$_SERVER['HTTP_HOST']      = '127.0.0.1:8765';
$_SERVER['SERVER_NAME']    = '127.0.0.1';
$_SERVER['SERVER_PORT']    = '8765';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTPS']          = '';
$_SERVER['REQUEST_URI']    = '/library-system/';
$_SERVER['SCRIPT_NAME']    = '/library-system/index.php';
$_SERVER['PHP_SELF']       = '/library-system/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

ini_set('session.use_cookies',       '0');
ini_set('session.use_only_cookies',  '0');
ini_set('session.use_trans_sid',     '0');
ini_set('session.cache_limiter',     '');
ini_set('session.use_strict_mode',   '0');
session_cache_limiter('');

$ssDir = sys_get_temp_dir() . '/circ_ss_sess_v4';
@mkdir($ssDir, 0777, true);
session_save_path($ssDir);
session_name('CIRCSSV4');
$sid = substr(bin2hex(random_bytes(16)), 0, 32);
session_id($sid);
session_start();
$_SESSION['librarian_id']   = 1;
$_SESSION['librarian_name'] = 'Library Admin';
$_SESSION['csrf_token']     = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
session_write_close();

define('CAPTURE_DIR', __DIR__ . '/tests/screenshots/_html');
@mkdir(CAPTURE_DIR, 0755, true);

$results = [];

$run = static function (string $title, string $script, string $outBase, string $method, array $post = [], ?string $uriOverride = null) use (&$results, $ssDir, $csrf) {
    $uri = $uriOverride ?? ('/library-system/' . basename(dirname($script)) . '/' . basename($script));
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI']    = $uri;
    $_SERVER['SCRIPT_NAME']    = $uri;
    $_SERVER['PHP_SELF']       = $uri;
    $_SERVER['HTTP_REFERER']   = 'http://127.0.0.1:8765' . $uri;

    // Reopen the SAME session (functions.php will also session_start() on the same id).
    session_save_path($ssDir);
    session_name('CIRCSSV4');
    session_start();
    $_SESSION['csrf_token'] = $csrf;
    $_SESSION['librarian_id']   = 1;
    $_SESSION['librarian_name'] = 'Library Admin';
    session_write_close();
    session_start();

    $_POST    = $post;
    $_REQUEST = array_merge($_REQUEST ?? [], $post);

    ob_start();
    try {
        require $script;
    } catch (\Throwable $e) {
        $buf = ob_get_clean();
        $buf .= "\n<!-- [CAPTURE EXCEPTION] " . get_class($e) . ": " . htmlspecialchars($e->getMessage()) . " -->\n";
    }
    if (!isset($buf)) $buf = ob_get_clean();
    $buf = preg_replace_callback(
        '#\s(href|src|action)=("|\')(?!https?:|data:|#|//|mailto:|tel:)([^\'"]+)\2#',
        static function ($m) {
            $path = ltrim($m[3], '/');
            return " {$m[1]}={$m[2]}http://127.0.0.1:8765/library-system/{$path}{$m[2]}";
        },
        $buf
    );
    $outHtml = CAPTURE_DIR . "/{$outBase}.html";
    file_put_contents($outHtml, $buf);
    $results[] = ['t' => $title, 'n' => $outBase, 'b' => strlen($buf)];
    if (function_exists('header_remove')) @header_remove();
};

echo "=== Rendering circulation flows (session cookie OFF, CLI mode) ===\n";
$run('03 Dashboard after login',  __DIR__ . '/index.php',                        '03-dashboard-after-login',      'GET',  [],                                                         '/library-system/index.php');
$run('04 Borrow form (GET)',      __DIR__ . '/modules/circulation/borrow.php',    '04-borrow-form',                'GET');
$run('05 Borrow success (POST)',  __DIR__ . '/modules/circulation/borrow.php',    '05-borrow-success',             'POST', ['csrf_token'=>$csrf,'member_id'=>'5','book_id'=>'2']);
$run('06 Return page overdue',    __DIR__ . '/modules/circulation/return.php',    '06-return-page-overdue-red',    'GET');
$run('07 Return late -> fine',    __DIR__ . '/modules/circulation/return.php',    '07-return-late-fine',           'POST', ['csrf_token'=>$csrf,'loan_id'=>'2']);

foreach ($results as $r) {
    echo sprintf("  %-40s -> %s.html  %6d bytes\n", $r['t'], $r['n'], $r['b']);
}
echo "\n--- ", CAPTURE_DIR, " ---\n";
passthru('ls -1 ' . escapeshellarg(CAPTURE_DIR));
