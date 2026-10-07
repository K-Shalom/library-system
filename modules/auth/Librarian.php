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
    public static function all(): array
    {
        $stmt = Database::getInstance()->getConnection()->query(
            'SELECT librarian_id, full_name, username FROM librarians ORDER BY full_name'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id): array
    {
        $id = self::validateId($id);
        $stmt = Database::getInstance()->getConnection()->prepare(
            'SELECT librarian_id, full_name, username FROM librarians WHERE librarian_id = ?'
        );
        $stmt->execute([$id]);
        $librarian = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$librarian) {
            throw new NotFoundException('Librarian not found.');
        }
        return $librarian;
    }

    public static function create(array $data): int
    {
        $values = self::validateData($data);
        $password = self::validatePassword($data['password'] ?? null, true);
        $pdo = Database::getInstance()->getConnection();
        self::ensureUsernameAvailable($pdo, $values['username']);
        $stmt = $pdo->prepare(
            'INSERT INTO librarians (full_name, username, password_hash) VALUES (?, ?, ?)'
        );
        try {
            $stmt->execute([$values['full_name'], $values['username'], password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ValidationException('That username is already in use.');
            }
            throw $e;
        }
        return (int) $pdo->lastInsertId();
    }

    public static function update($id, array $data): void
    {
        $id = self::validateId($id);
        self::find($id);
        $values = self::validateData($data);
        $password = self::validatePassword($data['password'] ?? '', false);
        $pdo = Database::getInstance()->getConnection();
        self::ensureUsernameAvailable($pdo, $values['username'], $id);
        if ($password === null) {
            $stmt = $pdo->prepare('UPDATE librarians SET full_name = ?, username = ? WHERE librarian_id = ?');
            $stmt->execute([$values['full_name'], $values['username'], $id]);
            return;
        }
        $stmt = $pdo->prepare(
            'UPDATE librarians SET full_name = ?, username = ?, password_hash = ? WHERE librarian_id = ?'
        );
        $stmt->execute([$values['full_name'], $values['username'], password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function delete($id, $currentLibrarianId): void
    {
        $id = self::validateId($id);
        if ($currentLibrarianId !== null && $id === self::validateId($currentLibrarianId)) {
            throw new BusinessRuleException('You cannot delete the account you are currently using.');
        }

        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();
        try {
            $lockedIds = $pdo->query('SELECT librarian_id FROM librarians ORDER BY librarian_id FOR UPDATE')
                ->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array($id, array_map('intval', $lockedIds), true)) {
                throw new NotFoundException('Librarian not found.');
            }
            if (count($lockedIds) <= 1) {
                throw new BusinessRuleException('The last librarian account cannot be deleted.');
            }
            $stmt = $pdo->prepare('DELETE FROM librarians WHERE librarian_id = ?');
            $stmt->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                throw new BusinessRuleException('This librarian has loan history and cannot be deleted.');
            }
            throw $e;
        }
    }

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

    private static function validateData(array $data): array
    {
        $nameValue = $data['full_name'] ?? '';
        $usernameValue = $data['username'] ?? '';
        if (!is_scalar($nameValue) || !is_scalar($usernameValue)) {
            throw new ValidationException('Librarian details must be text.');
        }
        $name = trim((string) $nameValue);
        $username = trim((string) $usernameValue);
        if ($name === '' || strlen($name) > 150) {
            throw new ValidationException('Full name is required and must be 150 characters or fewer.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            throw new ValidationException('Username must be 3-50 characters using letters, numbers, dot, underscore, or hyphen.');
        }
        return ['full_name' => $name, 'username' => $username];
    }

    private static function validatePassword($value, bool $required): ?string
    {
        if ($value === null && !$required) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new ValidationException('Password must be text.');
        }
        $password = (string) $value;
        if ($password === '' && !$required) {
            return null;
        }
        if (strlen($password) < 8 || strlen($password) > 255) {
            throw new ValidationException('Password must be between 8 and 255 characters.');
        }
        return $password;
    }

    private static function ensureUsernameAvailable(PDO $pdo, string $username, ?int $ignoreId = null): void
    {
        $sql = 'SELECT 1 FROM librarians WHERE username = ?';
        $params = [$username];
        if ($ignoreId !== null) {
            $sql .= ' AND librarian_id <> ?';
            $params[] = $ignoreId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            throw new ValidationException('That username is already in use.');
        }
    }

    private static function validateId($value): int
    {
        $id = is_scalar($value) ? filter_var(trim((string) $value), FILTER_VALIDATE_INT) : false;
        if ($id === false || $id < 1) {
            throw new ValidationException('Librarian ID must be a positive whole number.');
        }
        return (int) $id;
    }
}
