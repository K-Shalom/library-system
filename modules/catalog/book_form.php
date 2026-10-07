<?php
/**
 * File: book_form.php
 * Module: Catalog
 * Assigned to: Adeline N
 * Status: DONE
 * Description: Add and edit book details form
 */

require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Book.php';
require_once __DIR__ . '/Author.php';
require_once __DIR__ . '/Publisher.php';
require_once __DIR__ . '/Category.php';

$isEdit = isset($_GET['id']);
$bookId = $_GET['id'] ?? null;
$values = [
	'title' => '',
	'isbn' => '',
	'category_id' => '',
	'publisher_id' => '',
	'published_year' => '',
	'total_copies' => '1',
	'available_copies' => '1',
];
$selectedAuthors = [];
$authors = [];
$categories = [];
$publishers = [];

try {
	// These shared lookup models provide the options for the form.
	$authors = Author::all();
	$categories = Category::all();
	$publishers = Publisher::all();

	if ($isEdit) {
		$book = Book::find($bookId);
		foreach (array_keys($values) as $field) {
			$values[$field] = (string) ($book[$field] ?? '');
		}
		$selectedAuthors = $book['author_ids'];
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		foreach (array_keys($values) as $field) {
			$postedValue = $_POST[$field] ?? '';
			if (!is_scalar($postedValue)) {
				throw new ValidationException('Book fields must contain text or numbers.');
			}
			$values[$field] = trim((string) $postedValue);
		}
		$selectedAuthors = $_POST['author_ids'] ?? [];
		if (!is_array($selectedAuthors)) {
			throw new ValidationException('Please choose valid authors.');
		}
		csrf_verify();

		if ($isEdit) {
			Book::update($bookId, $values, $selectedAuthors);
			set_flash('success', 'Book updated successfully.');
		} else {
			Book::create($values, $selectedAuthors);
			set_flash('success', 'Book added successfully.');
		}
		redirect(BASE_URL . '/modules/catalog/books.php');
	}
} catch (Throwable $e) {
	flash_exception($e);
	if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
		redirect(BASE_URL . '/modules/catalog/books.php');
	}
}

$pageTitle = $isEdit ? 'Edit book' : 'Add book';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
	<h1 class="h3 mb-0"><?= $isEdit ? 'Edit book' : 'Add book' ?></h1>
	<a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/catalog/books.php">Back to books</a>
</div>

<form method="post" action="<?= e(BASE_URL) ?>/modules/catalog/book_form.php<?= $isEdit ? '?id=' . rawurlencode((string) $bookId) : '' ?>">
	<?= csrf_field() ?>
	<div class="row g-3">
		<div class="col-md-8">
			<label class="form-label" for="title">Title</label>
			<input class="form-control" id="title" name="title" maxlength="255" required value="<?= e($values['title']) ?>">
		</div>
		<div class="col-md-4">
			<label class="form-label" for="isbn">ISBN</label>
			<input class="form-control" id="isbn" name="isbn" maxlength="20" required value="<?= e($values['isbn']) ?>">
		</div>
		<div class="col-md-6">
			<label class="form-label" for="category_id">Category</label>
			<select class="form-select" id="category_id" name="category_id" required>
				<option value="">Choose a category</option>
				<?php foreach ($categories as $category): ?>
					<option value="<?= (int) $category['category_id'] ?>" <?= (string) $category['category_id'] === $values['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-6">
			<label class="form-label" for="publisher_id">Publisher</label>
			<select class="form-select" id="publisher_id" name="publisher_id" required>
				<option value="">Choose a publisher</option>
				<?php foreach ($publishers as $publisher): ?>
					<option value="<?= (int) $publisher['publisher_id'] ?>" <?= (string) $publisher['publisher_id'] === $values['publisher_id'] ? 'selected' : '' ?>><?= e($publisher['name']) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-4">
			<label class="form-label" for="published_year">Published year</label>
			<input class="form-control" type="number" id="published_year" name="published_year"
				   min="1000" max="<?= (int) date('Y') ?>" value="<?= e($values['published_year']) ?>">
		</div>
		<div class="col-md-4">
			<label class="form-label" for="total_copies">Total copies</label>
			<input class="form-control" type="number" id="total_copies" name="total_copies" min="1" required value="<?= e($values['total_copies']) ?>">
		</div>
		<div class="col-md-4">
			<label class="form-label">Availability</label>
			<div class="form-control bg-light">Managed automatically</div>
			<div class="form-text">Calculated from total copies and books currently on loan.</div>
		</div>
		<div class="col-12">
			<label class="form-label" for="author_ids">Authors</label>
			<select class="form-select" id="author_ids" name="author_ids[]" multiple size="5">
				<?php foreach ($authors as $author): ?>
					<option value="<?= (int) $author['author_id'] ?>" <?= in_array((int) $author['author_id'], array_map(static fn($authorId) => is_scalar($authorId) ? (int) $authorId : 0, $selectedAuthors), true) ? 'selected' : '' ?>><?= e($author['name']) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-12">
			<button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Add book' ?></button>
			<a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/catalog/books.php">Cancel</a>
		</div>
	</div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
