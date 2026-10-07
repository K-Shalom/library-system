<?php
/**
 * capture_pages.php — CLI script: logs in as librarian id=1 via session
 * and renders the circulation module pages to static HTML files that
 * headless Chrome can screenshot without a real cookie jar.
 *
 * Also performs the borrow and return POST flows to capture the flash
 * messages that result.
 */
define('BASE_URL', '/library-system');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/library-system/';
$_SERVER['HTTP_HOST']      = '127.0.0.1:8765';
$_SERVER['SERVER_NAME']    = '127.0.0.1';
$_SERVER['SERVER_PORT']    = '8765';
$_SERVER['HTTPS']          = '';
$_SERVER['SCRIPT_NAME']    = '/library-system/index.php';
$_SERVER['PHP_SELF']       = '/library-system/index.php';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';

session_save_path('/tmp/circ_screenshots_sess');
@mkdir(session_save_path(), 0777, true);
session_name('PHPSESSIDCIRC');
session_start();
$_SESSION['librarian_id']   = 1;
$_SESSION['librarian_name'] = 'Library Admin';
session_write_close();

define('CIRC_DIR', __DIR__ . '/modules/circulation');
define('CAPTURE_DIR', __DIR__ . '/tests/screenshots/_html');
@mkdir(CAPTURE_DIR, 0777, true);

$loader = static function (string $entryScript, string $outFile, array $env = [], array $post = []) {
    foreach ($env as $k => $v) {
        $_SERVER[$k] = $v;
    }
    $_POST = $post;
    $_REQUEST = array_merge($_REQUEST ?? [], $post);
    session_start();
    ob_start();
    try {
        require $entryScript;
    } catch (Throwable $e) {
        $buf = ob_get_clean();
        $buf .= "\n<!-- ERROR: " . get_class($e) . ': ' . htmlspecialchars($e->getMessage()) . ' -->';
        file_put_contents($outFile, $buf);
        echo "  ! " . get_class($e) . ": {$e->getMessage()}\n";
        return;
    }
    $buf = ob_get_clean();
    // Rewrite relative asset URLs to absolute http:// URLs so the static file
    // loads Bootstrap CSS from the real CDN, not file://.
    $buf = preg_replace('#\s(href|src)=("|\')(?!https?:|data:|#|//)([^\'"]+)\2#', ' $1=$2http://127.0.0.1:8765/library-system/$3$2', $buf);
    file_put_contents($outFile, $buf);
    session_write_close();
    echo "  -> wrote " . strlen($buf) . " bytes\n";
};

echo "--- capture dashboard (index.php) ---\n";
$loader(__DIR__ . '/index.php', CAPTURE_DIR . '/03-dashboard-after-login.html', [
    'REQUEST_URI'  => '/library-system/index.php',
    'SCRIPT_NAME'  => '/library-system/index.php',
    'PHP_SELF'     => '/library-system/index.php',
    'REQUEST_METHOD' => 'GET',
]);

echo "--- capture borrow form (GET) ---\n";
$loader(CIRC_DIR . '/borrow.php', CAPTURE_DIR . '/04-borrow-form.html', [
    'REQUEST_URI'  => '/library-system/modules/circulation/borrow.php',
    'SCRIPT_NAME'  => '/library-system/modules/circulation/borrow.php',
    'PHP_SELF'     => '/library-system/modules/circulation/borrow.php',
    'REQUEST_METHOD' => 'GET',
]);

echo "--- POST a borrow (member=5 Eve, book=2 Pride and Prejudice) via direct require ---\n";
// Start a session, generate a CSRF token, then POST with the same session.
session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
session_write_close();

$loader(CIRC_DIR . '/borrow.php', CAPTURE_DIR . '/05-borrow-success.html', [
    'REQUEST_URI'    => '/library-system/modules/circulation/borrow.php',
    'SCRIPT_NAME'    => '/library-system/modules/circulation/borrow.php',
    'PHP_SELF'       => '/library-system/modules/circulation/borrow.php',
    'REQUEST_METHOD' => 'POST',
    'HTTP_REFERER'   => 'http://127.0.0.1:8765/library-system/modules/circulation/borrow.php',
], [
    'csrf_token'    => $csrf,
    'member_id'     => '5',
    'book_id'       => '2',
]);

echo "--- capture return page (GET, showing red overdue row) ---\n";
$loader(CIRC_DIR . '/return.php', CAPTURE_DIR . '/06-return-page-overdue-red.html', [
    'REQUEST_URI'  => '/library-system/modules/circulation/return.php',
    'SCRIPT_NAME'  => '/library-system/modules/circulation/return.php',
    'PHP_SELF'     => '/library-system/modules/circulation/return.php',
    'REQUEST_METHOD' => 'GET',
]);

echo "--- POST a return of the overdue loan #2 (Bob — The Old Man and the Sea, 16 days late) ---\n";
session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
session_write_close();

$loader(CIRC_DIR . '/return.php', CAPTURE_DIR . '/07-return-late-fine.html', [
    'REQUEST_URI'    => '/library-system/modules/circulation/return.php',
    'SCRIPT_NAME'    => '/library-system/modules/circulation/return.php',
    'PHP_SELF'       => '/library-system/modules/circulation/return.php',
    'REQUEST_METHOD' => 'POST',
    'HTTP_REFERER'   => 'http://127.0.0.1:8765/library-system/modules/circulation/return.php',
], [
    'csrf_token'    => $csrf,
    'loan_id'       => '2',
]);

echo "\nDone. HTML captures:\n";
passthru('ls -1 ' . escapeshellarg(CAPTURE_DIR));
