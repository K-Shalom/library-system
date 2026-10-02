<?php
/**
 * File: login.php
 * Module: Auth
 * Assigned to: Jose Narame
 * Status: DONE
 * Description: Staff login interface
 */

// Use require_once __DIR__ . '/../../config/Database.php'; so paths work from any folder.
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/Librarian.php';

// Already logged in? Go straight to the dashboard.
if (current_librarian_id() !== null) {
    redirect(BASE_URL . '/index.php');
}

// Keep the typed username so the user does not retype it after a failed attempt.
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify();

        // Both fields are required. The password is checked but never sanitized or changed.
        $username = validate_required(sanitize($_POST['username'] ?? ''), 'Username');
        $password = (string) ($_POST['password'] ?? '');
        validate_required($password, 'Password');

        $librarian = Librarian::authenticate($username, $password);

        // New session ID after login stops "session fixation" attacks.
        session_regenerate_id(true);
        $_SESSION['librarian_id']   = (int) $librarian['librarian_id'];
        $_SESSION['librarian_name'] = $librarian['full_name'];

        set_flash('success', 'Welcome, ' . $librarian['full_name'] . '!');
        redirect(BASE_URL . '/index.php'); // Post/Redirect/Get
    } catch (Throwable $e) {
        flash_exception($e);
    }
}

$pageTitle = 'Librarian Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(BASE_URL) ?>/assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-8 col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 text-center mb-1">College Library</h1>
                    <p class="text-center text-muted mb-4">Librarian login</p>

                    <?php show_flash(); ?>

                    <form method="post" action="<?= e(BASE_URL) ?>/modules/auth/login.php" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= e($username) ?>" required autofocus autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password"
                                   required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
