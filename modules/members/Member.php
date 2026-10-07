<?php
/**
 * File: Member.php
 * Module: Members
 * Assigned to: H Muhamadi
 * Status: DONE
 * Description: OOP Member model class
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/exceptions.php';

class Member
{
    /**
     * Find a single member by ID.
     * Throws NotFoundException if record does not exist.
     */
    public static function find($id): array
    {
        $id = self::validateId($id);
        $pdo = Database::getInstance()->getConnection();
        
        $stmt = $pdo->prepare('SELECT * FROM members WHERE member_id = ?');
        $stmt->execute([$id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            throw new NotFoundException('Member not found.');
        }

        return $member;
    }

    /**
     * Get all members with their active loan count.
     */
    public static function all(): array
    {
        $pdo = Database::getInstance()->getConnection();
        $sql = 'SELECT m.*, 
                       (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.member_id AND l.return_date IS NULL) AS active_loans
                FROM members m
                ORDER BY m.full_name ASC';
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a member exists and is active.
     */
    public static function isActive($id): bool
    {
        $member = self::find($id);
        return strtolower($member['status']) === 'active';
    }

    /**
     * Count active (unreturned) loans for a given member.
     */
    public static function countActiveLoans($memberId): int
    {
        $memberId = self::validateId($memberId);
        $pdo = Database::getInstance()->getConnection();
        
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE member_id = ? AND return_date IS NULL');
        $stmt->execute([$memberId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Search members by name, email, or phone.
     * Returns members with active loan count and status.
     */
    public static function search($term): array
    {
        if (!is_scalar($term)) {
            $term = '';
        }
        $term = trim((string) $term);
        if ($term === '') {
            return self::all();
        }

        $pdo = Database::getInstance()->getConnection();
        $sql = 'SELECT m.*, 
                       (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.member_id AND l.return_date IS NULL) AS active_loans
                FROM members m
                WHERE m.full_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?
                ORDER BY m.full_name ASC';
        
        $stmt = $pdo->prepare($sql);
        $like = '%' . $term . '%';
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new member after validation.
     * Returns the new member_id.
     */
    public static function create(array $data): int
    {
        $pdo = Database::getInstance()->getConnection();
        $validated = self::validateMemberData($pdo, $data, null);

        $stmt = $pdo->prepare(
            'INSERT INTO members (full_name, email, phone, status, registered_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $validated['full_name'],
            $validated['email'],
            $validated['phone'],
            $validated['status'],
            date('Y-m-d H:i:s')
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update an existing member after validation.
     */
    public static function update($id, array $data): bool
    {
        $id = self::validateId($id);
        self::find($id); // ensures member exists

        $pdo = Database::getInstance()->getConnection();
        $validated = self::validateMemberData($pdo, $data, $id);

        $stmt = $pdo->prepare(
            'UPDATE members 
             SET full_name = ?, email = ?, phone = ?, status = ?
             WHERE member_id = ?'
        );
        return $stmt->execute([
            $validated['full_name'],
            $validated['email'],
            $validated['phone'],
            $validated['status'],
            $id
        ]);
    }

    /**
     * Get borrowing history for a member.
     * Includes book title, issue_date, due_date, return_date, computed status, fine amount and paid status.
     */
    public static function getHistory($memberId): array
    {
        $memberId = self::validateId($memberId);
        self::find($memberId); // ensures member exists

        $pdo = Database::getInstance()->getConnection();
        $sql = 'SELECT l.loan_id, l.book_id, l.member_id, l.issue_date, l.due_date, l.return_date, l.status AS raw_status,
                       b.title AS book_title,
                       f.fine_id, f.amount AS fine_amount, f.paid AS fine_paid
                FROM loans l
                JOIN books b ON b.book_id = l.book_id
                LEFT JOIN fines f ON f.loan_id = l.loan_id
                WHERE l.member_id = ?
                ORDER BY l.issue_date DESC, l.loan_id DESC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$memberId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        foreach ($rows as &$row) {
            if ($row['return_date'] !== null && $row['return_date'] !== '') {
                $row['computed_status'] = 'Returned';
            } elseif ($row['due_date'] < $today) {
                $row['computed_status'] = 'Overdue';
            } else {
                $row['computed_status'] = 'Borrowed';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Validate positive integer ID.
     */
    private static function validateId($id): int
    {
        $num = filter_var($id, FILTER_VALIDATE_INT);
        if ($num === false || $num < 1) {
            throw new ValidationException('Invalid member ID.');
        }
        return $num;
    }

    /**
     * Validate member fields for create and update.
     */
    private static function validateMemberData(PDO $pdo, array $data, ?int $ignoreId): array
    {
        $errors = [];

        // Full Name validation
        $fullName = trim((string) ($data['full_name'] ?? ''));
        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        } elseif (strlen($fullName) > 150) {
            $errors[] = 'Full name cannot exceed 150 characters.';
        }

        // Email validation
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $errors[] = 'Email address is required.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($email) > 150) {
            $errors[] = 'Email address cannot exceed 150 characters.';
        } else {
            // Uniqueness check
            $checkSql = 'SELECT member_id FROM members WHERE email = ?';
            $params = [$email];
            if ($ignoreId !== null) {
                $checkSql .= ' AND member_id <> ?';
                $params[] = $ignoreId;
            }
            $stmt = $pdo->prepare($checkSql);
            $stmt->execute($params);
            if ($stmt->fetchColumn() !== false) {
                $errors[] = 'An account with this email address already exists.';
            }
        }

        // Phone validation (optional, 7 to 15 digits, allowing + and spaces)
        $phoneRaw = trim((string) ($data['phone'] ?? ''));
        $phone = null;
        if ($phoneRaw !== '') {
            $digitCount = strlen(preg_replace('/[^\d]/', '', $phoneRaw));
            if (!preg_match('/^\+?[0-9\s]+$/', $phoneRaw) || $digitCount < 7 || $digitCount > 15) {
                $errors[] = 'Phone number must contain between 7 and 15 digits (plus sign and spaces allowed).';
            } else {
                $phone = $phoneRaw;
            }
        }

        // Status validation
        $status = strtolower(trim((string) ($data['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Status must be either active or inactive.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors[0], 0, null, $errors);
        }

        return [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'status' => $status
        ];
    }
}
