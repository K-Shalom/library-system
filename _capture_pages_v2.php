<?php
/**
 * _capture_pages_v2.php
 * CLI-only helper: renders 5 logged-in circulation flows to static HTML files
 * so headless Chrome can screenshot them without cookie-jar fiddling.
 *
 * Session strategy: use a dedicated non-default session name so functions.php's
 * session_start() reuses our fake logged-in session (librarian_id=1, valid CSRF).
 */
define('BASE_URL', '/library-system');
$_SERVER['HTTP_HOST']    = '127.0.0.1:8765';
$_SERVER['SERVER_NAME']  = '127.0.0.1';
$_SERVER['SERVER_PORT']  = '8765';
$_SERVER['REMOTE_ADDR']  = '127.0.0.1';
$_SERVER['HTTPS']        = '';

$ssDir = sys_get_temp_dir() . '/circ_ss_sess_v2';
@mkdir($ssDir, 0777, true);
session_save_path($ssDir);
session_name('CIRCSSV2');
session_start();
$_SESSION['librarian_id']   = 1;
$_SESSION['librarian_name'] = 'Library Admin';
$_SESSION['csrf_token']     = bin2hex(random_bytes(32));
$GLOBALS['_circ_ss_csrf'] = $_SESSION['csrf_token'];
session_write_close();

define('CAPTURE_DIR', __DIR__ . '/tests/screenshots/_html');
@mkdir(CAPTURE_DIR, 0755, true);

$run = static function (string $title, string $script, string $outBase, string $method, array $post = [], string $uriOverride = '') {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI']    = $uriOverride ?: ('/library-system/' . basename(dirname($script)) . '/' . basename($script));
    $_SERVER['SCRIPT_NAME']    = $_SERVER['REQUEST_URI'];
    $_SERVER['PHP_SELF']       = $_SERVER['REQUEST_URI'];
    if ($method === 'POST') {
        $_SERVER['HTTP_REFERER'] = 'http://127.0.0.1:8765' . $_SERVER['REQUEST_URI'];
    }
    // Reload our session cookie into the one functions.php session_start() will pick up.
    session_name('CIRCSSV2');
    session_save_path($GLOBALS['_circ_ss_dir'] ?? $GLOBALS['_circ_ss_dir'] = sys_get_temp_dir() . '/circ_ss_sess_v2');
    @session_start();
    // Re-apply our CSRF token (required because functions.php may regenerate id in future).
    $_SESSION['csrf_token'] = $GLOBALS['_circ_ss_csrf'];
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
    // Rewrite any relative asset URLs in href/src to absolute server URLs (except data:, https:, #, mailto:).
    $buf = preg_replace_callback(
        '#\s(href|src|action)=("|\')(?!https?:|data:|#|//|mailto:|tel:)([^\'"]+)\2#',
        static function ($m) {
            $path = ltrim($m[3], '/');
            return " {$m[1]}={$m[2]}http://127.0.0.1:8765/library-system/{$path}{$m[2]}";
        },
        $buf
    );
    // Also rewrite inline url(...) paths in <style> tags — not needed because we use CDN Bootstrap.
    $outHtml = CAPTURE_DIR . "/{$outBase}.html";
    file_put_contents($outHtml, $buf);
    echo sprintf("  %-40s -> %6d bytes  (HTTP status via headers sent: %s)\n",
        $title, strlen($buf), http_response_code() ?: '200');
    // Clear headers for next iteration.
    if (function_exists('header_remove')) {
        header_remove();
    }
};

echo "=== Rendering flows to static HTML ===\n";

// 03 — dashboard
$run('Dashboard after login', __DIR__ . '/index.php', '03-dashboard-after-login', 'GET', [], '/library-system/index.php');

// 04 — borrow form GET
$run('Borrow form (dropdowns + due preview)', __DIR__ . '/modules/circulation/borrow.php', '04-borrow-form', 'GET', []);

// 05 — borrow POST (Eve, book_id=2 — Pride and Prejudice, avail=2 copies)
$run('Borrow success flash (POST)', __DIR__ . '/modules/circulation/borrow.php', '05-borrow-success', 'POST', [
    'csrf_token' => $GLOBALS['_circ_ss_csrf'],
    'member_id'  => '5',
    'book_id'    => '2',
]);

// 06 — return page GET (contains red overdue row for Bob's loan #2 — which is 16 days late in sample data)
$run('Return page (overdue row RED)', __DIR__ . '/modules/circulation/return.php', '06-return-page-overdue-red', 'GET', []);

// 07 — return POST on the overdue loan (loan_id=2 in sample data for Bob — Old Man and Sea)
$run('Return late -> fine flash', __DIR__ . '/modules/circulation/return.php', '07-return-late-fine', 'POST', [
    'csrf_token' => $GLOBALS['_circ_ss_csrf'],
    'loan_id'    => '2',
]);

echo "\n=== Captures in ", CAPTURE_DIR, " ===\n";
passthru('ls -1 ' . escapeshellarg(CAPTURE_DIR));
