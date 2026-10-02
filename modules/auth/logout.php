<?php
/**
 * File: logout.php
 * Module: Auth
 * Assigned to: Jose Narame
 * Status: DONE
 * Description: Logout handler
 */

// Use require_once __DIR__ . '/../../config/Database.php'; so paths work from any folder.
require_once __DIR__ . '/../../config/functions.php';

// 1. Empty all session data (librarian_id, name, CSRF token, ...).
$_SESSION = [];

// 2. Delete the old session on the server and give the browser a brand-new, empty session ID.
//    The old ID becomes useless, even if someone had copied it.
session_regenerate_id(true);

// 3. Show a message on the login page (stored in the new, empty session).
set_flash('success', 'You have been logged out.');
redirect(BASE_URL . '/modules/auth/login.php');
