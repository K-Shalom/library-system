<?php
/**
 * PHP built-in server router. Strips the leading /library-system prefix so the
 * dev server at http://127.0.0.1:8765/library-system/... correctly resolves to
 * files in this folder (the app uses BASE_URL = '/library-system').
 */
$uri = $_SERVER['REQUEST_URI'];
$prefix = '/library-system';

// The request doesn't start with the prefix -> maybe it's a static asset URL
// built without the prefix (e.g. 404 handler or direct reference). Pass through.
if (!str_starts_with($uri, $prefix)) {
    return false;
}

$trimmed = substr($uri, strlen($prefix));
if ($trimmed === '' || $trimmed === '/') {
    $trimmed = '/index.php';
}
$qPos = strpos($trimmed, '?');
$path = $qPos === false ? $trimmed : substr($trimmed, 0, $qPos);
$file = __DIR__ . $path;

// Unknown paths under prefix -> 404 (not a PHP file we know of, doesn't exist)
if (!is_file($file)) {
    return false;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

// Serve static assets from the resolved filesystem path; their URL includes a prefix
// that does not exist as a directory under the built-in server's document root.
$static = ['css','js','png','jpg','jpeg','gif','svg','ico','webp','woff','woff2','ttf','otf','map','txt','html','json'];
if (in_array($ext, $static, true)) {
    $mimeTypes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'map' => 'application/json; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}

// PHP scripts: require them here in-process so sessions/pass-through work for redirects.
if ($ext === 'php') {
    require $file;
    return true;
}

// Unknown extension.
return false;
