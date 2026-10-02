<?php
/**
 * File: functions.php
 * Module: Core
 * Assigned to: Jose Narame
 * Status: DONE
 * Description: Helper functions for input sanitization and flash messages
 */

// Load the database class (and its constants) plus our custom exception classes.
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/exceptions.php';

// ------------------------------------------------------------
// Error settings: never show PHP errors to users, write them to the log file instead.
// ------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_FILE);

// Base path of the application inside the web root (http://localhost/library-system/).
if (!defined('BASE_URL')) define('BASE_URL', '/library-system');

// ------------------------------------------------------------
// Session: start it once, with safer cookie settings.
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,   // JavaScript cannot read the session cookie
        'samesite' => 'Lax',  // Browser does not send the cookie on most cross-site requests
    ]);
    session_start();
}

// Last line of defence: if an exception is not caught by a page, log it and show the friendly error page.
set_exception_handler(function (Throwable $e) {
    Database::logError('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        header('Location: ' . BASE_URL . '/error.php');
    } else {
        // Page output already started, so we cannot redirect: print a short safe message instead.
        echo '<p>Something went wrong. Please try again.</p>';
    }
    exit;
});

// Warnings and notices (e.g. "undefined variable") are written to the log and never shown on the page.
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Respect error_reporting() so errors silenced with @ are ignored.
    if (!(error_reporting() & $errno)) {
        return false;
    }
    Database::logError('PHP error [' . $errno . ']: ' . $errstr . ' in ' . $errfile . ':' . $errline);
    return true; // true = PHP should not run its own error output
});

// ------------------------------------------------------------
// Output and input helpers
// ------------------------------------------------------------

// Escape a value before printing it in HTML, so user data can never inject HTML or JavaScript.
function e($s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

// Clean a text input: remove HTML tags and surrounding spaces. (Do not use this on passwords.)
function sanitize($s): string
{
    return trim(strip_tags((string) ($s ?? '')));
}

// Send the browser to another URL and stop the script immediately.
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ------------------------------------------------------------
// Flash messages: one-time messages stored in the session and shown on the next page.
// ------------------------------------------------------------

// Save a message. $type is a Bootstrap alert colour: success, danger, warning or info.
function set_flash(string $type, string $msg): void
{
    $allowed = ['success', 'danger', 'warning', 'info'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

// Print all saved messages as Bootstrap alerts, then delete them so they show only once.
function show_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $flash) {
        echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show" role="alert">'
            . e($flash['msg'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
    }
    unset($_SESSION['flash']);
}

// ------------------------------------------------------------
// CSRF protection: every POST form carries a secret token that only our pages know.
// ------------------------------------------------------------

// Return the session's CSRF token, creating a random one the first time.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Return the hidden form field that holds the token. Print it inside every POST form with: echo csrf_field();
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Check the token sent with a POST form. Throws ValidationException if it is missing or wrong.
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        throw new ValidationException('Your form has expired. Please reload the page and try again.');
    }
}

// ------------------------------------------------------------
// Login helpers
// ------------------------------------------------------------

// Send visitors who are not logged in ($_SESSION['librarian_id'] missing) to the login page.
function require_login(): void
{
    if (current_librarian_id() === null) {
        set_flash('info', 'Please log in to continue.');
        redirect(BASE_URL . '/modules/auth/login.php');
    }
}

// ID of the logged-in librarian, or null if nobody is logged in.
function current_librarian_id(): ?int
{
    return isset($_SESSION['librarian_id']) ? (int) $_SESSION['librarian_id'] : null;
}

// Full name of the logged-in librarian, or an empty string.
function current_librarian_name(): string
{
    return (string) ($_SESSION['librarian_name'] ?? '');
}

// ------------------------------------------------------------
// Validation helpers: each returns the cleaned value or throws ValidationException.
// ------------------------------------------------------------

// Small helper so every validator throws the same way (message + errors list).
function validation_fail(string $msg): void
{
    throw new ValidationException($msg, 0, null, [$msg]);
}

// The value must not be empty. Returns the trimmed text.
function validate_required($v, string $label): string
{
    $v = trim((string) ($v ?? ''));
    if ($v === '') {
        validation_fail($label . ' is required.');
    }
    return $v;
}

// The value must be a valid email address. Returns the trimmed email.
function validate_email($v): string
{
    $v = trim((string) ($v ?? ''));
    if (filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
        validation_fail('Please enter a valid email address.');
    }
    return $v;
}

// The value must be a whole number, optionally between $min and $max. Returns it as an int.
function validate_int($v, string $label, ?int $min = null, ?int $max = null): int
{
    $n = filter_var(trim((string) ($v ?? '')), FILTER_VALIDATE_INT);
    if ($n === false) {
        validation_fail($label . ' must be a whole number.');
    }
    if ($min !== null && $n < $min) {
        validation_fail($label . ' must be at least ' . $min . '.');
    }
    if ($max !== null && $n > $max) {
        validation_fail($label . ' must be at most ' . $max . '.');
    }
    return $n;
}

// The value must be a real date in YYYY-MM-DD format. Returns the date string.
function validate_date($v, string $label): string
{
    $v = trim((string) ($v ?? ''));
    $date = DateTime::createFromFormat('Y-m-d', $v);
    // format() check rejects impossible dates such as 2025-02-30.
    if ($date === false || $date->format('Y-m-d') !== $v) {
        validation_fail($label . ' must be a valid date (YYYY-MM-DD).');
    }
    return $v;
}

// ------------------------------------------------------------
// Turn any exception into a friendly flash message.
// ------------------------------------------------------------

// Our own exceptions already carry a user-friendly message, so show it.
// Anything else (PDOException, bugs, ...) is logged and replaced with a generic message.
function flash_exception(Throwable $e): void
{
    if ($e instanceof ValidationException || $e instanceof BusinessRuleException) {
        set_flash('danger', $e->getMessage());
    } elseif ($e instanceof NotFoundException) {
        set_flash('warning', $e->getMessage());
    } else {
        Database::logError(get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        set_flash('danger', 'Something went wrong. Please try again.');
    }
}
