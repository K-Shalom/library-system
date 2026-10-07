<?php
/**
 * File: header.php
 * Module: Core
 * Assigned to: Sabin Levis
 * Status: DONE
 * Description: Global navigation bar and Bootstrap setup
 */

// ------------------------------------------------------------
// Determine which navbar link should be highlighted ("active").
// We compare the current request URI against each known module folder.
// ------------------------------------------------------------
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
// Strip query string (everything after "?") for cleaner matching.
if (($qPos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $qPos);
}

// Helper: returns "active" if $needle (a sub-path) appears inside the current URI path.
function nav_active(string $needle): string
{
    global $requestUri;
    // Normalise both to forward slashes so Windows paths still match.
    return strpos(str_replace('\\', '/', $requestUri), $needle) !== false ? 'active' : '';
}

// Dashboard link points to /index.php specifically so it never collides with "/modules" matches.
$dashPath = rtrim($requestUri, '/');
$basePath = rtrim(BASE_URL, '/');
$dashboardActive = (str_ends_with($dashPath, '/index.php') || $dashPath === $basePath) ? 'active' : '';
$reportsActive = nav_active('/modules/reports/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Library System') ?> | Library System</title>
    <!-- Bootstrap 5.3 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom project styles -->
    <link href="<?= e(BASE_URL) ?>/assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- ===== Responsive Navigation Bar ===== -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= e(BASE_URL) ?>/index.php">
                📚 College Library
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#mainNav" aria-controls="mainNav"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $dashboardActive ?>" aria-current="page"
                           href="<?= e(BASE_URL) ?>/index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('/modules/catalog/books.php') ?>"
                           href="<?= e(BASE_URL) ?>/modules/catalog/books.php">Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('/modules/catalog/lookups.php') ?>"
                           href="<?= e(BASE_URL) ?>/modules/catalog/lookups.php">Lookups</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('/modules/members/') ?>"
                           href="<?= e(BASE_URL) ?>/modules/members/list.php">Members</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('/modules/circulation/borrow.php') ?>"
                           href="<?= e(BASE_URL) ?>/modules/circulation/borrow.php">Borrow</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('/modules/circulation/return.php') ?>"
                           href="<?= e(BASE_URL) ?>/modules/circulation/return.php">Return</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('/modules/fines/') ?>"
                           href="<?= e(BASE_URL) ?>/modules/fines/fines.php">Fines</a>
                    </li>
                    <!-- Reports dropdown -->
                    <li class="nav-item dropdown <?= $reportsActive ?>">
                        <a class="nav-link dropdown-toggle <?= $reportsActive ?>" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Reports
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item <?= nav_active('/modules/reports/reports.php') ?>"
                                   href="<?= e(BASE_URL) ?>/modules/reports/reports.php">Circulation Summary</a>
                            </li>
                            <li>
                                <a class="dropdown-item <?= nav_active('/modules/reports/overdue.php') ?>"
                                   href="<?= e(BASE_URL) ?>/modules/reports/overdue.php">Overdue Loans</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <!-- Right side: librarian name + logout -->
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                    <li class="nav-item me-lg-3 mb-2 mb-lg-0">
                        <span class="navbar-text text-light">
                            👤 <?= e(current_librarian_name()) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-outline-light"
                           href="<?= e(BASE_URL) ?>/modules/auth/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ===== Main content container opened here (closed in footer.php) ===== -->
    <main class="container pb-5">
        <?php show_flash(); ?>
