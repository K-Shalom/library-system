<?php
/**
 * File: list.php
 * Module: Members
 * Assigned to: H Muhamadi
 * Status: DONE
 * Description: Member search and directory UI
 */

require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Member.php';

$queryValue = $_GET['search'] ?? ($_GET['q'] ?? '');
$searchTerm = is_scalar($queryValue) ? sanitize($queryValue) : '';

$members = [];
try {
    $members = Member::search($searchTerm);
} catch (Throwable $e) {
    flash_exception($e);
}

$pageTitle = 'Member Directory';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h1 class="h3 mb-0">Member Directory</h1>
        <p class="text-muted mb-0 small">Manage library members, view borrowing history, and register new members.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/members/form.php">Register member</a>
</div>

<form class="row g-2 mb-3" method="get" action="<?= e(BASE_URL) ?>/modules/members/list.php">
    <div class="col-sm-9 col-md-10">
        <label class="visually-hidden" for="member-search">Search members</label>
        <input class="form-control" id="member-search" type="search" name="search"
               value="<?= e($searchTerm) ?>" placeholder="Search members by name, email, or phone number...">
    </div>
    <div class="col-sm-3 col-md-2 d-flex gap-2">
        <button class="btn btn-outline-primary w-100" type="submit">Search</button>
        <?php if ($searchTerm !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/members/list.php">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($members === []): ?>
    <div class="card card-body text-center py-4 text-muted">
        No members found<?= $searchTerm !== '' ? ' matching "' . e($searchTerm) . '"' : '' ?>.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Full Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Active Loans</th>
                    <th scope="col">Status</th>
                    <th scope="col">Registered Date</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td>#<?= (int) $member['member_id'] ?></td>
                        <td class="fw-bold"><?= e($member['full_name']) ?></td>
                        <td><?= e($member['email']) ?></td>
                        <td><?= e($member['phone'] ?: '—') ?></td>
                        <td>
                            <?php $activeCount = (int) ($member['active_loans'] ?? 0); ?>
                            <?php if ($activeCount > 0): ?>
                                <span class="badge bg-primary"><?= $activeCount ?> active</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (strtolower($member['status']) === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($member['registered_at'] ? date('M d, Y', strtotime($member['registered_at'])) : '—') ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary me-1"
                               href="<?= e(BASE_URL) ?>/modules/members/form.php?id=<?= (int) $member['member_id'] ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-info"
                               href="<?= e(BASE_URL) ?>/modules/members/history.php?id=<?= (int) $member['member_id'] ?>">History</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
