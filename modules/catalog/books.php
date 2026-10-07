<?php
/**
 * File: books.php
 * Module: Catalog
 * Assigned to: Adeline N
 * Status: DONE
 * Description: Book catalog listing and search UI
 */

require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Book.php';

$queryValue = $_GET['q'] ?? '';
$searchTerm = is_scalar($queryValue) ? sanitize($queryValue) : '';

// Delete is POST-only; always redirect afterward to prevent accidental repeats.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	try {
		csrf_verify();
		if (($_POST['action'] ?? '') !== 'delete') {
			throw new ValidationException('Invalid catalog action.');
		}
		Book::delete($_POST['book_id'] ?? null);
		set_flash('success', 'Book deleted successfully.');
	} catch (Throwable $e) {
		flash_exception($e);
	}
	$url = BASE_URL . '/modules/catalog/books.php';
	if ($searchTerm !== '') {
		$url .= '?q=' . rawurlencode($searchTerm);
	}
	redirect($url);
}

$books = [];
try {
	$books = $searchTerm === '' ? Book::all() : Book::search($searchTerm);
} catch (Throwable $e) {
	flash_exception($e);
}

$pageTitle = 'Books';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
	<h1 class="h3 mb-0">Book catalog</h1>
	<a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/catalog/book_form.php">Add book</a>
</div>

<form class="row g-2 mb-3" method="get" action="<?= e(BASE_URL) ?>/modules/catalog/books.php">
	<div class="col-sm-9 col-md-10">
		<label class="visually-hidden" for="book-search">Search books</label>
		<input class="form-control" id="book-search" type="search" name="q"
			   value="<?= e($searchTerm) ?>" placeholder="Search title, ISBN, category, or author">
	</div>
	<div class="col-sm-3 col-md-2 d-grid">
		<button class="btn btn-outline-primary" type="submit">Search</button>
	</div>
</form>

<?php if ($books === []): ?>
	<p class="text-muted">No books found.</p>
<?php else: ?>
	<div class="table-responsive">
		<table class="table table-striped table-hover align-middle">
			<thead>
				<tr>
					<th scope="col">Title</th>
					<th scope="col">ISBN</th>
					<th scope="col">Authors</th>
					<th scope="col">Category</th>
					<th scope="col">Publisher</th>
					<th scope="col">Availability</th>
					<th scope="col"><span class="visually-hidden">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($books as $book): ?>
					<?php $available = (int) $book['available_copies']; ?>
					<tr>
						<td><?= e($book['title']) ?></td>
						<td><?= e($book['isbn']) ?></td>
						<td><?= e(implode(', ', $book['authors'])) ?></td>
						<td><?= e($book['category']) ?></td>
						<td><?= e($book['publisher']) ?></td>
						<td>
							<?php if ($available > 0): ?>
								<span class="text-success">Available (<?= $available ?>/<?= (int) $book['total_copies'] ?>)</span>
							<?php else: ?>
								<span class="text-danger">Not available</span>
							<?php endif; ?>
						</td>
						<td class="text-nowrap">
							<a class="btn btn-sm btn-outline-primary"
							   href="<?= e(BASE_URL) ?>/modules/catalog/book_form.php?id=<?= (int) $book['book_id'] ?>">Edit</a>
							<form class="d-inline" method="post"
								  action="<?= e(BASE_URL) ?>/modules/catalog/books.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>"
								  onsubmit="return confirm('Delete this book?');">
								<?= csrf_field() ?>
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
								<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
