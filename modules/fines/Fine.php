<?php
/**
 * File: Fine.php
 * Module: Fines
 * Assigned to: Augustin Mugisha
 * Status: DONE
 * Description: Overdue fine calculation OOP model
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/exceptions.php';

class Fine
{
	// Calculate the charge using whole calendar days past the due date.
	public static function calculate($dueDate, $returnDate): float
	{
		$dates = [];
		foreach ([$dueDate, $returnDate] as $value) {
			if (!is_string($value)) {
				throw new ValidationException('Due date and return date must be valid dates.');
			}

			$date = DateTime::createFromFormat('!Y-m-d', trim($value));
			$errors = DateTime::getLastErrors();
			if ($date === false
				|| $date->format('Y-m-d') !== trim($value)
				|| ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
				throw new ValidationException('Due date and return date must be valid dates.');
			}
			$dates[] = $date;
		}

		$daysLate = (int) $dates[0]->diff($dates[1])->format('%r%a');
		return $daysLate > 0 ? (float) ($daysLate * DAILY_FINE) : 0.0;
	}

	// Check for any unpaid fine attached to one of the member's loans.
	public static function hasUnpaid($memberId): bool
	{
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->prepare(
			'SELECT 1 FROM fines f
			 INNER JOIN loans l ON l.loan_id = f.loan_id
			 WHERE l.member_id = :member_id AND f.paid = 0
			 LIMIT 1'
		);
		$stmt->execute(['member_id' => $memberId]);
		return $stmt->fetchColumn() !== false;
	}

	// Add one fine for a loan after rejecting invalid amounts and duplicates.
	public static function create($loanId, $amount): int
	{
		if (!is_numeric($amount) || !is_finite((float) $amount)) {
			throw new ValidationException('Fine amount must be a valid number.');
		}
		if ((float) $amount <= 0) {
			throw new BusinessRuleException('Fine amount must be greater than zero.');
		}

		$pdo = Database::getInstance()->getConnection();
		$check = $pdo->prepare('SELECT fine_id FROM fines WHERE loan_id = :loan_id');
		$check->execute(['loan_id' => $loanId]);
		if ($check->fetchColumn() !== false) {
			throw new BusinessRuleException('A fine already exists for this loan.');
		}

		$stmt = $pdo->prepare('INSERT INTO fines (loan_id, amount) VALUES (:loan_id, :amount)');
		$stmt->execute(['loan_id' => $loanId, 'amount' => $amount]);
		return (int) $pdo->lastInsertId();
	}

	// Return the fine attached to a loan, or report that it does not exist.
	public static function findByLoan($loanId): array
	{
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->prepare('SELECT * FROM fines WHERE loan_id = :loan_id');
		$stmt->execute(['loan_id' => $loanId]);
		$fine = $stmt->fetch();
		if (!$fine) {
			throw new NotFoundException('Fine not found.');
		}
		return $fine;
	}

	// List fines with the member, book, and loan dates needed by the page.
	public static function listAll(): array
	{
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->query(
			'SELECT f.fine_id, f.loan_id, m.full_name AS member_name,
					b.title AS book_title, l.due_date, l.return_date,
					f.amount, f.paid, f.created_at, f.paid_at
			 FROM fines f
			 INNER JOIN loans l ON l.loan_id = f.loan_id
			 INNER JOIN members m ON m.member_id = l.member_id
			 INNER JOIN books b ON b.book_id = l.book_id
			 ORDER BY f.created_at DESC, f.fine_id DESC'
		);
		return $stmt->fetchAll();
	}

	// Record payment once and reject missing or already-paid fines.
	public static function markPaid($fineId): void
	{
		$fineId = self::validateId($fineId);
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->prepare('UPDATE fines SET paid = 1, paid_at = NOW() WHERE fine_id = ? AND paid = 0');
		$stmt->execute([$fineId]);
		if ($stmt->rowCount() === 1) {
			return;
		}

		$check = $pdo->prepare('SELECT paid FROM fines WHERE fine_id = ?');
		$check->execute([$fineId]);
		$paid = $check->fetchColumn();
		if ($paid === false) {
			throw new NotFoundException('Fine not found.');
		}
		throw new BusinessRuleException('This fine has already been paid.');
	}

	// Return the sum of all fines that have not been paid yet.
	public static function totalUnpaid(): float
	{
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM fines WHERE paid = 0');
		return (float) $stmt->fetchColumn();
	}

	private static function validateId($value): int
	{
		$id = is_scalar($value) ? filter_var(trim((string) $value), FILTER_VALIDATE_INT) : false;
		if ($id === false || $id < 1) {
			throw new ValidationException('Fine ID must be a positive whole number.');
		}
		return (int) $id;
	}

}
