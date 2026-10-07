<?php
/**
 * File: Loan.php
 * Module: Circulation
 * Assigned to: Shalom K
 * Status: DONE
 * Description: Loan / borrow / return OOP model (owns DB transactions)
 */

// Use require_once __DIR__ . '/../../config/Database.php'; so paths work from any folder.
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../catalog/Book.php';
require_once __DIR__ . '/../members/Member.php';
require_once __DIR__ . '/../fines/Fine.php';

class Loan
{
    // ------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------

    /**
     * Create a new loan transactionally.
     *
     * Locks the book row (FOR UPDATE), runs business-rule checks against the DB state,
     * decrements the available copies count, inserts a new loan row, and commits.
     *
     * @param mixed $memberId     Positive integer member_id
     * @param mixed $bookId       Positive integer book_id
     * @param mixed $librarianId  Positive integer librarian_id issuing the loan
     * @return int                new loan_id (lastInsertId)
     *
     * @throws ValidationException  if any id is not a positive integer
     * @throws NotFoundException    if member or book does not exist
     * @throws BusinessRuleException on any rule violation (inactive member, no copies,
     *                               unpaid fines, max loans reached, duplicate unreturned loan)
     * @throws DatabaseException    on wrapped DB errors (from Database class)
     */
    public static function borrow($memberId, $bookId, $librarianId): int
    {
        // --- Step 1: validate IDs as positive integers ---
        $memberId    = self::validateId($memberId, 'Member ID');
        $bookId      = self::validateId($bookId,   'Book ID');
        $librarianId = self::validateId($librarianId, 'Librarian ID');

        $pdo = Database::getInstance()->getConnection();

        // --- Step 2: start transaction, lock book row FOR UPDATE ---
        $startedTx = false;
        try {
            $pdo->beginTransaction();
            $startedTx = true;

            $stmt = $pdo->prepare('SELECT * FROM books WHERE book_id = ? FOR UPDATE');
            $stmt->execute([$bookId]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$book) {
                throw new NotFoundException('Book not found.');
            }

            // --- Step 3: business rule checks (order matters to give a clear error first) ---

            // 3a. Member exists (Member::find throws NotFoundException if missing) and is active.
            $member = Member::find($memberId);
            if (!Member::isActive($memberId)) {
                throw new BusinessRuleException('This member is inactive and cannot borrow books.');
            }

            // 3b. Book has at least 1 available copy right now (freshly locked row).
            if ((int) $book['available_copies'] < 1) {
                throw new BusinessRuleException('No copies of this book are available right now.');
            }

            // 3c. Member has no unpaid fines.
            if (Fine::hasUnpaid($memberId)) {
                throw new BusinessRuleException("Clear the member's unpaid fines first.");
            }

            // 3d. Member has fewer than MAX_LOANS active (unreturned) loans already.
            if (Member::countActiveLoans($memberId) >= MAX_LOANS) {
                throw new BusinessRuleException(
                    'This member already has ' . MAX_LOANS . ' active loans. Return a book first.'
                );
            }

            // 3e. Member does NOT already hold an unreturned copy of this exact book.
            $dup = $pdo->prepare(
                'SELECT 1 FROM loans
                  WHERE member_id = ? AND book_id = ? AND return_date IS NULL
                  LIMIT 1'
            );
            $dup->execute([$memberId, $bookId]);
            if ($dup->fetchColumn() !== false) {
                throw new BusinessRuleException(
                    'This member already has an unreturned copy of this book.'
                );
            }

            // --- Step 4: decrement available, insert loan row ---
            Book::decrementAvailable($bookId);

            $todayObj = new DateTime('today');
            $dueObj   = clone $todayObj;
            $dueObj->modify('+' . LOAN_DAYS . ' days');

            $insert = $pdo->prepare(
                'INSERT INTO loans (book_id, member_id, librarian_id, issue_date, due_date, return_date, status)
                 VALUES (?, ?, ?, ?, ?, NULL, ?)'
            );
            $insert->execute([
                $bookId,
                $memberId,
                $librarianId,
                $todayObj->format('Y-m-d'),
                $dueObj->format('Y-m-d'),
                'borrowed',
            ]);
            $loanId = (int) $pdo->lastInsertId();

            // --- Step 5: commit and return the new loan id ---
            $pdo->commit();
            return $loanId;

        } catch (Throwable $e) {
            // Roll back if the transaction was ever started and is still open.
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Return a loan transactionally.
     *
     * Locks the loan row, sets return_date + status, increments the book's available copies,
     * calculates the overdue fine, and (if > 0) creates a fine record.
     *
     * @param mixed $loanId  Positive integer loan_id
     * @return array  ['loan_id' => int, 'days_late' => int, 'fine' => float]
     *
     * @throws ValidationException  bad id
     * @throws NotFoundException    loan not found
     * @throws BusinessRuleException loan already returned, or fine > 0 but Fine::create failed
     */
    public static function return($loanId): array
    {
        $loanId = self::validateId($loanId, 'Loan ID');
        $pdo    = Database::getInstance()->getConnection();

        $startedTx = false;
        try {
            $pdo->beginTransaction();
            $startedTx = true;

            // --- Step 1: lock the loan row; throw if missing or already returned ---
            $stmt = $pdo->prepare('SELECT * FROM loans WHERE loan_id = ? FOR UPDATE');
            $stmt->execute([$loanId]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$loan) {
                throw new NotFoundException('Loan not found.');
            }
            if ($loan['return_date'] !== null && $loan['return_date'] !== '') {
                throw new BusinessRuleException('This book was already returned.');
            }

            // --- Step 2: update loan return_date/status and increment the book ---
            $todayObj     = new DateTime('today');
            $todayStr     = $todayObj->format('Y-m-d');
            $dueDateStr   = $loan['due_date'];
            $bookId       = (int) $loan['book_id'];

            $upd = $pdo->prepare(
                'UPDATE loans SET return_date = ?, status = ? WHERE loan_id = ?'
            );
            $upd->execute([$todayStr, 'returned', $loanId]);

            Book::incrementAvailable($bookId);

            // --- Step 3: calculate fine; if > 0 create a fine row ---
            $fineAmount = Fine::calculate($dueDateStr, $todayStr);
            if ($fineAmount > 0) {
                Fine::create($loanId, $fineAmount);
            }

            // --- Step 4: commit ---
            $pdo->commit();

            $daysLate = 0;
            $dueObj   = DateTime::createFromFormat('!Y-m-d', $dueDateStr);
            if ($dueObj instanceof DateTime) {
                $daysLate = max(0, (int) $dueObj->diff($todayObj)->format('%r%a'));
            }
            return [
                'loan_id'   => $loanId,
                'days_late' => $daysLate,
                'fine'      => $fineAmount,
            ];

        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Promote any "borrowed" unreturned loans whose due date has passed to "overdue".
     * Idempotent: safe to call on every page load, in a cron job, or from the list methods.
     *
     * @return int number of rows updated
     */
    public static function markOverdue(): int
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "UPDATE loans
                SET status = 'overdue'
              WHERE return_date IS NULL
                AND due_date < CURDATE()
                AND status = 'borrowed'"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Return all currently unreturned loans (status borrowed OR overdue) enriched with
     * member name, book title, days overdue and estimated fine.
     * Calls markOverdue() first so statuses are always up to date.
     */
    public static function getActiveLoans(): array
    {
        self::markOverdue();
        $pdo = Database::getInstance()->getConnection();

        $sql = "SELECT l.loan_id, l.book_id, l.member_id, l.issue_date, l.due_date, l.status,
                       m.full_name AS member_name,
                       b.title AS book_title,
                       CASE
                         WHEN l.due_date < CURDATE() THEN DATEDIFF(CURDATE(), l.due_date)
                         ELSE 0
                       END AS days_overdue,
                       CASE
                         WHEN l.due_date < CURDATE() THEN DATEDIFF(CURDATE(), l.due_date) * ?
                         ELSE 0
                       END AS estimated_fine
                  FROM loans l
                  JOIN members m ON m.member_id = l.member_id
                  JOIN books   b ON b.book_id   = l.book_id
                 WHERE l.return_date IS NULL
                 ORDER BY l.status DESC, l.due_date ASC, l.loan_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([DAILY_FINE]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return only the overdue loans, most overdue first.
     * Calls markOverdue() first so statuses are always up to date.
     */
    public static function getOverdue(): array
    {
        self::markOverdue();
        $pdo = Database::getInstance()->getConnection();

        $sql = "SELECT l.loan_id, l.book_id, l.member_id, l.issue_date, l.due_date, l.status,
                       m.full_name AS member_name,
                       b.title AS book_title,
                       DATEDIFF(CURDATE(), l.due_date) AS days_overdue,
                       (DATEDIFF(CURDATE(), l.due_date) * ?) AS estimated_fine
                  FROM loans l
                  JOIN members m ON m.member_id = l.member_id
                  JOIN books   b ON b.book_id   = l.book_id
                 WHERE l.return_date IS NULL
                   AND l.due_date < CURDATE()
                 ORDER BY days_overdue DESC, l.loan_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([DAILY_FINE]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------

    /**
     * Validate that a user-supplied id is a positive integer (accepts numeric strings too).
     * Returns the id as int on success; throws ValidationException otherwise.
     */
    private static function validateId($value, string $label): int
    {
        $number = is_scalar($value)
            ? filter_var(trim((string) $value), FILTER_VALIDATE_INT)
            : false;
        if ($number === false || $number < 1) {
            throw new ValidationException($label . ' must be a positive whole number.');
        }
        return (int) $number;
    }
}
