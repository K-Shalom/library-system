<?php
/**
 * _capture_via_children.php — orchestrator that spawns one CLI subprocess per
 * page to render, sharing a single PHP session id across them all.
 *
 * Then it runs headless Chrome on each saved HTML file to produce PNG screenshots
 * under tests/screenshots/ for the project report.
 */
ini_set('display_errors', '1');

define('CAPTURE_DIR', __DIR__ . '/tests/screenshots/_html');
define('SS_DIR',      sys_get_temp_dir() . '/circ_ss_via_child');
@mkdir(CAPTURE_DIR, 0755, true);
@mkdir(SS_DIR,      0777, true);

$SESS_NAME = 'CIRCLIB';
$SESS_ID   = substr(bin2hex(random_bytes(16)), 0, 32);
$CHILD     = __DIR__ . '/_render_child.php';

// Pre-initialize the shared session file with a CSRF token so the very first
// render (which is a POST) already has the same token as the POST body.
$sessSave = SS_DIR;
$sessFile = $sessSave . '/sess_' . $SESS_ID;
@file_put_contents($sessFile,
    'librarian_id|i:1;'
  . 'librarian_name|s:13:"Library Admin";'
  . 'csrf_token|s:64:"' . str_repeat('a', 64) . '";'
);
@chmod($sessFile, 0666);
$CSRF = str_repeat('a', 64);

$run = static function (string $outBase, string $script, string $method, array $post = [], ?string $uriOverride = null) use ($CHILD, $SESS_NAME, $SESS_ID, $SS_DIR) {
    $uri = $uriOverride ?? ('/library-system/' . basename(dirname($script)) . '/' . basename($script));
    $phpBin = PHP_BINARY;
    $env = [
        'RENDER_SCRIPT'    => $script,
        'RENDER_METHOD'    => $method,
        'RENDER_URI'       => $uri,
        'RENDER_SESS_DIR'  => $SS_DIR,
        'RENDER_SESS_NAME' => $SESS_NAME,
        'RENDER_SESS_ID'   => $SESS_ID,
        'RENDER_POST_JSON' => json_encode($post, JSON_UNESCAPED_UNICODE),
        // PATH etc
        'PATH'             => getenv('PATH') ?: '/usr/bin:/bin',
        'HOME'             => getenv('HOME') ?: '/tmp',
        'TMPDIR'           => sys_get_temp_dir(),
    ];
    $desc = [ 0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w'] ];
    $proc = proc_open([$phpBin, $CHILD], $desc, $pipes, __DIR__, $env);
    if (!is_resource($proc)) {
        echo "  [FAIL proc_open] $outBase\n";
        return '';
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $exit = proc_close($proc);
    // Rewrite relative asset URLs to absolute dev-server URLs so Chrome can
    // load them from our running PHP built-in server at 127.0.0.1:8765.
    $stdout = preg_replace_callback(
        '#\s(href|src|action)=("|\')(?!https?:|data:|#|//|mailto:|tel:)([^\'"]+)\2#',
        static function ($m) {
            $path = ltrim($m[3], '/');
            return " {$m[1]}={$m[2]}http://127.0.0.1:8765/library-system/{$path}{$m[2]}";
        },
        $stdout
    );
    $outHtml = CAPTURE_DIR . "/{$outBase}.html";
    file_put_contents($outHtml, $stdout);
    echo sprintf("  %-40s %6d bytes  (child exit=%d, stderr %d bytes)\n",
        $outBase . '.html', strlen($stdout), $exit, strlen($stderr));
    if (strlen($stderr) > 0) {
        // Write any PHP child warnings visible in the HTML capture for debugging.
        file_put_contents($outHtml, "\n<!-- CHILD STDERR: " . htmlspecialchars($stderr) . " -->\n", FILE_APPEND);
    }
    return $outHtml;
};

echo "=== Step 1/2: render pages via CLI subprocesses ===\n";
echo "  Shared session id: $SESS_ID (CSRF = 64 x 'a')\n";

$run('03-dashboard-after-login',      __DIR__.'/index.php',                        'GET',  [], '/library-system/index.php');
$run('04-borrow-form',                __DIR__.'/modules/circulation/borrow.php',    'GET');
$run('05-borrow-success',             __DIR__.'/modules/circulation/borrow.php',    'POST', ['csrf_token'=>$CSRF,'member_id'=>'5','book_id'=>'2']);
$run('06-return-page-overdue-red',    __DIR__.'/modules/circulation/return.php',    'GET');
$run('07-return-late-fine',           __DIR__.'/modules/circulation/return.php',    'POST', ['csrf_token'=>$CSRF,'loan_id'=>'2']);

echo "\n=== Step 2/2: headless Chrome screenshots of each HTML -> PNG ===\n";
$chrome = 'google-chrome --headless=new --disable-gpu --hide-scrollbars --window-size=1440,1100 --no-sandbox --virtual-time-budget=3000';
$shotDir = __DIR__ . '/tests/screenshots';
@mkdir($shotDir, 0755, true);
foreach (['03-dashboard-after-login','04-borrow-form','05-borrow-success','06-return-page-overdue-red','07-return-late-fine'] as $stem) {
    $htmlFile = CAPTURE_DIR . '/' . $stem . '.html';
    $pngFile  = $shotDir . '/' . $stem . '.png';
    $cmd = "$chrome --screenshot=" . escapeshellarg($pngFile) . " file://" . escapeshellarg($htmlFile) . " 2>&1 | tail -3";
    passthru($cmd, $exitCode);
    $size = is_file($pngFile) ? filesize($pngFile) : 0;
    echo "  $stem.png  =>  $size bytes  (chrome exit=$exitCode)\n";
}

echo "\n=== Final listing in tests/screenshots/ ===\n";
passthru('ls -1 ' . escapeshellarg($shotDir));
