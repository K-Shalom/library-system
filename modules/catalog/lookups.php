<?php
/**
 * File: lookups.php
 * Module: Catalog
 * Description: Manage authors, publishers, and categories
 */

require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Author.php';
require_once __DIR__ . '/Publisher.php';
require_once __DIR__ . '/Category.php';

$types = [
	'authors' => ['class' => Author::class, 'label' => 'Author', 'plural' => 'Authors', 'id' => 'author_id'],
	'publishers' => ['class' => Publisher::class, 'label' => 'Publisher', 'plural' => 'Publishers', 'id' => 'publisher_id'],
	'categories' => ['class' => Category::class, 'label' => 'Category', 'plural' => 'Categories', 'id' => 'category_id'],
];
$editType = is_string($_GET['type'] ?? null) && isset($types[$_GET['type']]) ? $_GET['type'] : '';
$editId = $_GET['id'] ?? null;
$editRecord = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	try {
		csrf_verify();
		$type = $_POST['type'] ?? '';
		if (!is_string($type) || !isset($types[$type])) {
			throw new ValidationException('Invalid lookup type.');
		}
		$model = $types[$type]['class'];
		$action = $_POST['action'] ?? '';
		if ($action === 'delete') {
			$model::delete($_POST['id'] ?? null);
			set_flash('success', $types[$type]['label'] . ' deleted.');
		} elseif ($action === 'save') {
			$id = $_POST['id'] ?? '';
			if ($id === '') {
				$model::create($_POST['name'] ?? null);
				set_flash('success', $types[$type]['label'] . ' created.');
			} else {
				$model::update($id, $_POST['name'] ?? null);
				set_flash('success', $types[$type]['label'] . ' updated.');
			}
		} else {
			throw new ValidationException('Invalid lookup action.');
		}
		redirect(BASE_URL . '/modules/catalog/lookups.php');
	} catch (Throwable $e) {
		flash_exception($e);
		$editType = is_string($_POST['type'] ?? null) && isset($types[$_POST['type']]) ? $_POST['type'] : '';
		$editId = $_POST['id'] ?? null;
	}
}

$records = [];
foreach ($types as $type => $config) {
	try {
		$records[$type] = $config['class']::all();
	} catch (Throwable $e) {
		flash_exception($e);
		$records[$type] = [];
	}
}

$editName = '';
if ($editType !== '' && $editId !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
	try {
		$editRecord = $types[$editType]['class']::find($editId);
		$editName = $editRecord['name'];
	} catch (Throwable $e) {
		flash_exception($e);
	}
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editType !== '' && ($_POST['action'] ?? '') === 'save') {
	$editName = is_scalar($_POST['name'] ?? '') ? trim((string) $_POST['name']) : '';
}

$pageTitle = 'Catalog Lookups';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
	<h1 class="h3 mb-0">Authors, Publishers &amp; Categories</h1>
	<a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/catalog/books.php">Back to books</a>
</div>

<?php foreach ($types as $type => $config): ?>
	<section class="mb-4" aria-labelledby="lookup-<?= e($type) ?>">
		<h2 class="h5" id="lookup-<?= e($type) ?>"><?= e($config['plural']) ?></h2>
		<form class="row g-2 mb-3" method="post" action="<?= e(BASE_URL) ?>/modules/catalog/lookups.php">
			<?= csrf_field() ?>
			<input type="hidden" name="type" value="<?= e($type) ?>">
			<input type="hidden" name="action" value="save">
			<?php $isEditing = $editType === $type && $editId !== null; ?>
			<input type="hidden" name="id" value="<?= $isEditing ? (int) $editId : '' ?>">
			<div class="col">
				<label class="visually-hidden" for="name-<?= e($type) ?>"><?= e($config['label']) ?> name</label>
				<input class="form-control" id="name-<?= e($type) ?>" name="name" maxlength="150" required
					value="<?= $isEditing ? e($editName) : '' ?>"
					placeholder="<?= $isEditing ? 'Edit ' . e(strtolower($config['label'])) : 'Add ' . e(strtolower($config['label'])) ?>">
			</div>
			<div class="col-auto">
				<button class="btn btn-primary" type="submit"><?= $isEditing ? 'Save' : 'Add' ?></button>
				<?php if ($isEditing): ?>
					<a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/catalog/lookups.php">Cancel</a>
				<?php endif; ?>
			</div>
		</form>
		<div class="table-responsive">
			<table class="table table-sm table-striped align-middle">
				<thead><tr><th><?= e($config['label']) ?></th><th class="text-end">Actions</th></tr></thead>
				<tbody>
				<?php if ($records[$type] === []): ?>
					<tr><td colspan="2" class="text-muted">No <?= e(strtolower($config['label'])) ?> records.</td></tr>
				<?php else: ?>
					<?php foreach ($records[$type] as $record): ?>
						<tr>
							<td><?= e($record['name']) ?></td>
							<td class="text-end text-nowrap">
								<a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/catalog/lookups.php?type=<?= e($type) ?>&amp;id=<?= (int) $record[$config['id']] ?>">Edit</a>
								<form class="d-inline" method="post" action="<?= e(BASE_URL) ?>/modules/catalog/lookups.php" onsubmit="return confirm('Delete this record?');">
									<?= csrf_field() ?>
									<input type="hidden" name="type" value="<?= e($type) ?>">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="id" value="<?= (int) $record[$config['id']] ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
