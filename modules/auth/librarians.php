<?php
/**
 * File: librarians.php
 * Module: Auth
 * Description: Manage librarian accounts
 */

require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Librarian.php';

$editId = $_GET['id'] ?? null;
$values = ['full_name' => '', 'username' => ''];
$passwordValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	try {
		csrf_verify();
		$action = $_POST['action'] ?? '';
		if ($action === 'delete') {
			Librarian::delete($_POST['librarian_id'] ?? null, current_librarian_id());
			set_flash('success', 'Librarian account deleted.');
		} elseif ($action === 'save') {
			foreach (['full_name', 'username', 'password'] as $field) {
				$value = $_POST[$field] ?? '';
				if (!is_scalar($value)) {
					throw new ValidationException('Form fields must contain text.');
				}
				if ($field === 'password') {
					$passwordValue = (string) $value;
				} else {
					$values[$field] = trim((string) $value);
				}
			}
			if ($editId === null || $editId === '') {
				Librarian::create($values + ['password' => $passwordValue]);
				set_flash('success', 'Librarian account created.');
			} else {
				Librarian::update($editId, $values + ['password' => $passwordValue]);
				if ((int) $editId === current_librarian_id()) {
					$_SESSION['librarian_name'] = $values['full_name'];
				}
				set_flash('success', 'Librarian account updated.');
			}
		} else {
			throw new ValidationException('Invalid librarian action.');
		}
		redirect(BASE_URL . '/modules/auth/librarians.php');
	} catch (Throwable $e) {
		flash_exception($e);
		$editId = $_POST['librarian_id'] ?? $_POST['id'] ?? $editId;
	}
}

if ($editId !== null && $editId !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
	try {
		$record = Librarian::find($editId);
		$values = ['full_name' => $record['full_name'], 'username' => $record['username']];
	} catch (Throwable $e) {
		flash_exception($e);
		$editId = null;
	}
}

$librarians = [];
try {
	$librarians = Librarian::all();
} catch (Throwable $e) {
	flash_exception($e);
}

$pageTitle = 'Librarians';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
	<h1 class="h3 mb-0">Librarian Accounts</h1>
</div>

<div class="row g-4">
	<div class="col-lg-5">
		<form class="card" method="post" action="<?= e(BASE_URL) ?>/modules/auth/librarians.php<?= $editId !== null && $editId !== '' ? '?id=' . rawurlencode((string) $editId) : '' ?>">
			<div class="card-header"><h2 class="h5 mb-0"><?= $editId !== null && $editId !== '' ? 'Edit librarian' : 'Add librarian' ?></h2></div>
			<div class="card-body">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="save">
				<div class="mb-3">
					<label class="form-label" for="full_name">Full name</label>
					<input class="form-control" id="full_name" name="full_name" maxlength="150" required value="<?= e($values['full_name']) ?>">
				</div>
				<div class="mb-3">
					<label class="form-label" for="username">Username</label>
					<input class="form-control" id="username" name="username" minlength="3" maxlength="50" required value="<?= e($values['username']) ?>" autocomplete="username">
				</div>
				<div class="mb-3">
					<label class="form-label" for="password"><?= $editId !== null && $editId !== '' ? 'New password' : 'Password' ?></label>
					<input class="form-control" id="password" name="password" type="password" minlength="8" maxlength="255" <?= $editId !== null && $editId !== '' ? '' : 'required' ?> autocomplete="new-password">
					<div class="form-text"><?= $editId !== null && $editId !== '' ? 'Leave blank to keep the current password.' : 'Use at least 8 characters.' ?></div>
				</div>
				<button class="btn btn-primary" type="submit"><?= $editId !== null && $editId !== '' ? 'Save changes' : 'Create account' ?></button>
				<?php if ($editId !== null && $editId !== ''): ?>
					<a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/auth/librarians.php">Cancel</a>
				<?php endif; ?>
			</div>
		</form>
	</div>
	<div class="col-lg-7">
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead><tr><th>Name</th><th>Username</th><th class="text-end">Actions</th></tr></thead>
				<tbody>
				<?php foreach ($librarians as $librarian): ?>
					<tr>
						<td><?= e($librarian['full_name']) ?><?= (int) $librarian['librarian_id'] === current_librarian_id() ? ' (you)' : '' ?></td>
						<td><?= e($librarian['username']) ?></td>
						<td class="text-end text-nowrap">
							<a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/auth/librarians.php?id=<?= (int) $librarian['librarian_id'] ?>">Edit</a>
							<?php if ((int) $librarian['librarian_id'] !== current_librarian_id()): ?>
								<form class="d-inline" method="post" action="<?= e(BASE_URL) ?>/modules/auth/librarians.php" onsubmit="return confirm('Delete this librarian account?');">
									<?= csrf_field() ?>
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="librarian_id" value="<?= (int) $librarian['librarian_id'] ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($librarians === []): ?><tr><td colspan="3" class="text-muted">No librarian accounts found.</td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>