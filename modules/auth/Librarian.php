<?php
/**
 * File: Librarian.php
 * Module: Auth
 * Assigned to: Jose Narame
 * Status: DONE
 * Description: Librarian OOP authentication model
 */

// Use require_once __DIR__ . '/../../config/Database.php'; so paths work from any folder.
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/exceptions.php';

class Librarian
{
    /**
     * Check a username and password against the librarians table.
     * Returns the librarian (librarian_id, full_name, username) without the password hash.
     * Throws ValidationException if a field is empty or the login is wrong.
     */
    public static function authenticate($username, $password): array
    {
        $username = trim((string) $username);
        $password = (string) $password;

        if ($username === '' || $password === '') {
            throw new ValidationException('Please enter your username and password.');
        }

        $pdo = Database::getInstance()->getConnection();

        // Prepared statement: the username is sent separately from the SQL, so it cannot inject SQL.
        $stmt = $pdo->prepare('SELECT librarian_id, full_name, username, password_hash FROM librarians WHERE username = ?');
        $stmt->execute([$username]);
        $librarian = $stmt->fetch();

        // password_verify compares the typed password with the stored bcrypt hash.
        // Same message for "no such user" and "wrong password", so attackers cannot guess usernames.
        if (!$librarian || !password_verify($password, $librarian['password_hash'])) {
            throw new ValidationException('Invalid username or password.');
        }

        // If PHP's default hashing settings got stronger, save an upgraded hash.
        if (password_needs_rehash($librarian['password_hash'], PASSWORD_DEFAULT)) {
            $update = $pdo->prepare('UPDATE librarians SET password_hash = ? WHERE librarian_id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $librarian['librarian_id']]);
        }

        // Never hand the hash to the rest of the app.
        unset($librarian['password_hash']);
        return $librarian;
    }
}
