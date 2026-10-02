<?php
/**
 * File: error.php
 * Module: Core
 * Assigned to: Jose Narame
 * Status: DONE
 * Description: Friendly error handling page
 */

// This page is fully standalone: it does not load functions.php, the database or the session,
// so it still works when those are the reason something failed.

// Same value as in functions.php; defined here so this page has no dependencies.
if (!defined('BASE_URL')) define('BASE_URL', '/library-system');

// Tell the browser (and search engines) that this is a server error.
http_response_code(500);

// Small local escape helper (e() lives in functions.php, which we deliberately do not load).
function error_page_escape(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong | Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm text-center">
                <div class="card-body p-4">
                    <h1 class="h3 mb-3">Something went wrong</h1>
                    <p class="text-muted mb-4">
                        An unexpected error occurred. Please try again.
                        If the problem continues, contact the system administrator.
                    </p>
                    <a href="<?= error_page_escape(BASE_URL) ?>/index.php" class="btn btn-primary me-2">Back to dashboard</a>
                    <a href="<?= error_page_escape(BASE_URL) ?>/modules/auth/login.php" class="btn btn-outline-secondary">Go to login</a>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
