<?php
/**
 * File: Book.php
 * Module: Catalog
 * Assigned to: Adeline N
 * Status: DONE
 * Description: OOP Book model class
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/exceptions.php';

class Book
{
	// Return one book with its lookup names and author IDs for the edit form.
	public static function find($id): array
	{
		$pdo = Database::getInstance()->getConnection();
		$id = self::validateId($id, 'Book ID');
		$stmt = $pdo->prepare(
			'SELECT b.*, c.name AS category, p.name AS publisher
			 FROM books b
			 JOIN categories c ON c.category_id = b.category_id
			 JOIN publishers p ON p.publisher_id = b.publisher_id
			 WHERE b.book_id = ?'
		);
		$stmt->execute([$id]);
		$book = $stmt->fetch();
		if (!$book) {
			throw new NotFoundException('Book not found.');
		}

		$authors = self::loadAuthors($pdo, [$id]);
		$book['authors'] = array_column($authors[$id] ?? [], 'name');
		$book['author_ids'] = array_column($authors[$id] ?? [], 'author_id');
		return $book;
	}

	// Return every book with its category, publisher, and authors.
	public static function all(): array
	{
		return self::getBooks(null);
	}

	// Search book details and related category or author names.
	public static function search($term): array
	{
		if (!is_scalar($term)) {
			throw new ValidationException('Search term must be text.');
		}
		$term = trim((string) $term);
		return self::getBooks($term === '' ? null : $term);
	}

	// Validate and save a new book and its author links.
	public static function create(array $data, array $authorIds): int
	{
		$pdo = Database::getInstance()->getConnection();
		$book = self::validateData($pdo, $data);
		$authorIds = self::validateAuthors($pdo, $authorIds);
		$ownsTransaction = !$pdo->inTransaction();

		if ($ownsTransaction) {
			$pdo->beginTransaction();
		}
		try {
			$stmt = $pdo->prepare(
				'INSERT INTO books (title, isbn, category_id, publisher_id, published_year, total_copies, available_copies)
				 VALUES (?, ?, ?, ?, ?, ?, ?)'
			);
			$stmt->execute([
				$book['title'], $book['isbn'], $book['category_id'], $book['publisher_id'],
				$book['published_year'], $book['total_copies'], $book['available_copies'],
			]);
			$id = (int) $pdo->lastInsertId();
			self::saveAuthors($pdo, $id, $authorIds);
			if ($ownsTransaction) {
				$pdo->commit();
			}
			return $id;
		} catch (Throwable $e) {
			if ($ownsTransaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	// Update book details and replace its author links.
	public static function update($id, array $data, array $authorIds): void
	{
		$pdo = Database::getInstance()->getConnection();
		$id = self::validateId($id, 'Book ID');
		self::ensureBookExists($pdo, $id);
		$book = self::validateData($pdo, $data, $id);
		$authorIds = self::validateAuthors($pdo, $authorIds);
		$ownsTransaction = !$pdo->inTransaction();

		if ($ownsTransaction) {
			$pdo->beginTransaction();
		}
		try {
			$stmt = $pdo->prepare(
				'UPDATE books SET title = ?, isbn = ?, category_id = ?, publisher_id = ?,
				 published_year = ?, total_copies = ?, available_copies = ? WHERE book_id = ?'
			);
			$stmt->execute([
				$book['title'], $book['isbn'], $book['category_id'], $book['publisher_id'],
				$book['published_year'], $book['total_copies'], $book['available_copies'], $id,
			]);
			self::saveAuthors($pdo, $id, $authorIds);
			if ($ownsTransaction) {
				$pdo->commit();
			}
		} catch (Throwable $e) {
			if ($ownsTransaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	// Refuse deletion if any loan references the book.
	public static function delete($id): void
	{
		$pdo = Database::getInstance()->getConnection();
		$id = self::validateId($id, 'Book ID');
		self::ensureBookExists($pdo, $id);
		if (self::hasActiveLoans($id)) {
			throw new BusinessRuleException('This book has active loans and cannot be deleted.');
		}

		$stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ?');
		$stmt->execute([$id]);
		if ((int) $stmt->fetchColumn() > 0) {
			throw new BusinessRuleException('This book has loan history and cannot be deleted.');
		}

		$stmt = $pdo->prepare('DELETE FROM books WHERE book_id = ?');
		$stmt->execute([$id]);
	}

	// Check for loans that have not yet been returned.
	public static function hasActiveLoans($id): bool
	{
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ? AND return_date IS NULL');
		$stmt->execute([self::validateId($id, 'Book ID')]);
		return (int) $stmt->fetchColumn() > 0;
	}

	// Reduce availability only when a copy is currently available.
	public static function decrementAvailable($id): void
	{
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->prepare(
			'UPDATE books SET available_copies = available_copies - 1
			 WHERE book_id = ? AND available_copies > 0'
		);
		$stmt->execute([self::validateId($id, 'Book ID')]);
		if ($stmt->rowCount() === 0) {
			throw new BusinessRuleException('No copies available.');
		}
	}

	// Increase availability without exceeding the number of owned copies.
	public static function incrementAvailable($id): void
	{
		$pdo = Database::getInstance()->getConnection();
		$id = self::validateId($id, 'Book ID');
		$stmt = $pdo->prepare(
			'UPDATE books SET available_copies = available_copies + 1
			 WHERE book_id = ? AND available_copies < total_copies'
		);
		$stmt->execute([$id]);
		if ($stmt->rowCount() === 0) {
			self::ensureBookExists($pdo, $id);
			throw new BusinessRuleException('All copies are already available.');
		}
	}

	// Build a prepared list query and attach each book's author names.
	private static function getBooks(?string $term): array
	{
		$pdo = Database::getInstance()->getConnection();
		$sql = 'SELECT b.book_id, b.title, b.isbn, c.name AS category, p.name AS publisher,
					   b.published_year, b.total_copies, b.available_copies
				FROM books b
				JOIN categories c ON c.category_id = b.category_id
				JOIN publishers p ON p.publisher_id = b.publisher_id';
		if ($term !== null) {
			$sql .= ' WHERE b.title LIKE ? OR b.isbn LIKE ? OR c.name LIKE ?
					  OR EXISTS (SELECT 1 FROM book_authors ba JOIN authors a ON a.author_id = ba.author_id
								 WHERE ba.book_id = b.book_id AND a.name LIKE ?)';
		}
		$sql .= ' ORDER BY b.title';
		$stmt = $pdo->prepare($sql);
		if ($term !== null) {
			$like = '%' . $term . '%';
			$stmt->execute([$like, $like, $like, $like]);
		} else {
			$stmt->execute();
		}
		$books = $stmt->fetchAll();
		if ($books === []) {
			return [];
		}

		$authors = self::loadAuthors($pdo, array_column($books, 'book_id'));
		foreach ($books as &$book) {
			$book['authors'] = array_column($authors[(int) $book['book_id']] ?? [], 'name');
		}
		unset($book);
		return $books;
	}

	// Fetch author IDs and names in one query for the selected books.
	private static function loadAuthors(PDO $pdo, array $bookIds): array
	{
		$placeholders = implode(',', array_fill(0, count($bookIds), '?'));
		$stmt = $pdo->prepare(
			'SELECT ba.book_id, a.author_id, a.name
			 FROM book_authors ba JOIN authors a ON a.author_id = ba.author_id
			 WHERE ba.book_id IN (' . $placeholders . ') ORDER BY a.name'
		);
		$stmt->execute(array_values($bookIds));
		$authors = [];
		foreach ($stmt->fetchAll() as $author) {
			$authors[(int) $author['book_id']][] = [
				'author_id' => (int) $author['author_id'],
				'name' => $author['name'],
			];
		}
		return $authors;
	}

	// Validate fields and confirm related records exist before writing.
	private static function validateData(PDO $pdo, array $data, ?int $ignoreId = null): array
	{
		$titleValue = $data['title'] ?? '';
		$isbnValue = $data['isbn'] ?? '';
		if (!is_scalar($titleValue) || !is_scalar($isbnValue)) {
			throw new ValidationException('Title and ISBN must be text.');
		}
		$title = trim((string) $titleValue);
		$isbn = trim((string) $isbnValue);
		if ($title === '') {
			throw new ValidationException('Title is required.');
		}
		if (strlen($title) > 255) {
			throw new ValidationException('Title must be 255 characters or fewer.');
		}
		if ($isbn === '') {
			throw new ValidationException('ISBN is required.');
		}
		if (strlen($isbn) > 20) {
			throw new ValidationException('ISBN must be 20 characters or fewer.');
		}

		$duplicateSql = 'SELECT book_id FROM books WHERE isbn = ?';
		$duplicateValues = [$isbn];
		if ($ignoreId !== null) {
			$duplicateSql .= ' AND book_id <> ?';
			$duplicateValues[] = $ignoreId;
		}
		$stmt = $pdo->prepare($duplicateSql);
		$stmt->execute($duplicateValues);
		if ($stmt->fetchColumn() !== false) {
			throw new ValidationException('A book with this ISBN already exists.');
		}

		$categoryId = self::validateId($data['category_id'] ?? null, 'Category');
		$publisherId = self::validateId($data['publisher_id'] ?? null, 'Publisher');
		self::ensureLookupExists($pdo, 'categories', 'category_id', $categoryId, 'Category');
		self::ensureLookupExists($pdo, 'publishers', 'publisher_id', $publisherId, 'Publisher');

		$totalCopies = self::validateInteger($data['total_copies'] ?? null, 'Total copies', 1);
		$availableCopies = self::validateInteger(
			$data['available_copies'] ?? $totalCopies,
			'Available copies',
			0
		);
		if ($availableCopies > $totalCopies) {
			throw new ValidationException('Available copies cannot exceed total copies.');
		}

		if (!is_scalar($data['published_year'] ?? '')) {
			throw new ValidationException('Published year must be a whole number.');
		}
		$yearValue = trim((string) ($data['published_year'] ?? ''));
		$year = null;
		if ($yearValue !== '') {
			$year = self::validateInteger($yearValue, 'Published year', 1000, (int) date('Y'));
		}

		return [
			'title' => $title,
			'isbn' => $isbn,
			'category_id' => $categoryId,
			'publisher_id' => $publisherId,
			'published_year' => $year,
			'total_copies' => $totalCopies,
			'available_copies' => $availableCopies,
		];
	}

	// Reject invalid or missing author IDs before replacing links.
	private static function validateAuthors(PDO $pdo, array $authorIds): array
	{
		$ids = [];
		foreach ($authorIds as $authorId) {
			$ids[] = self::validateId($authorId, 'Author');
		}
		$ids = array_values(array_unique($ids));
		if ($ids === []) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$stmt = $pdo->prepare('SELECT author_id FROM authors WHERE author_id IN (' . $placeholders . ')');
		$stmt->execute($ids);
		if (count($stmt->fetchAll(PDO::FETCH_COLUMN)) !== count($ids)) {
			throw new ValidationException('Please select valid authors.');
		}
		return $ids;
	}

	// Replace all author links using prepared statements.
	private static function saveAuthors(PDO $pdo, int $bookId, array $authorIds): void
	{
		$stmt = $pdo->prepare('DELETE FROM book_authors WHERE book_id = ?');
		$stmt->execute([$bookId]);
		$stmt = $pdo->prepare('INSERT INTO book_authors (book_id, author_id) VALUES (?, ?)');
		foreach ($authorIds as $authorId) {
			$stmt->execute([$bookId, $authorId]);
		}
	}

	// Validate positive database IDs and numeric book fields.
	private static function validateId($value, string $label): int
	{
		return self::validateInteger($value, $label, 1);
	}

	private static function validateInteger($value, string $label, int $min, ?int $max = null): int
	{
		$number = is_scalar($value)
			? filter_var(trim((string) $value), FILTER_VALIDATE_INT)
			: false;
		if ($number === false || $number < $min || ($max !== null && $number > $max)) {
			$range = $max === null ? 'at least ' . $min : 'between ' . $min . ' and ' . $max;
			throw new ValidationException($label . ' must be a whole number ' . $range . '.');
		}
		return $number;
	}

	private static function ensureBookExists(PDO $pdo, int $id): void
	{
		$stmt = $pdo->prepare('SELECT 1 FROM books WHERE book_id = ?');
		$stmt->execute([$id]);
		if ($stmt->fetchColumn() === false) {
			throw new NotFoundException('Book not found.');
		}
	}

	// The table and key names here are fixed internal values, not user input.
	private static function ensureLookupExists(PDO $pdo, string $table, string $key, int $id, string $label): void
	{
		$stmt = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE ' . $key . ' = ?');
		$stmt->execute([$id]);
		if ($stmt->fetchColumn() === false) {
			throw new ValidationException('Please select a valid ' . strtolower($label) . '.');
		}
	}

}
