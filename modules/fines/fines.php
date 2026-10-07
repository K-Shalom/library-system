<?php
/**
 * File: fines.php
 * Module: Fines
 * Description: Fine tracking and payment interface
 */

require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Fine.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	try {
		csrf_verify();
		if (($_POST['action'] ?? '') !== 'pay') {
			throw new ValidationException('Invalid fine action.');
		}
		Fine::markPaid($_POST['fine_id'] ?? null);
		set_flash('success', 'Fine payment recorded.');
	} catch (Throwable $e) {
		flash_exception($e);
	}
	redirect(BASE_URL . '/modules/fines/fines.php');
}

$fines = [];
$unpaidTotal = 0.0;
$paidTotal = 0.0;
try {
	$fines = Fine::listAll();
	$unpaidTotal = Fine::totalUnpaid();
	foreach ($fines as $fine) {
		if ((int) $fine['paid'] === 1) {
			$paidTotal += (float) $fine['amount'];
		}
	}
} catch (Throwable $e) {
	flash_exception($e);
}

$formatRwf = static fn(float $amount): string => 'RWF ' . number_format($amount, 0, '.', ',');
$pageTitle = 'Fines';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
	<h1 class="h3 mb-0">Fines</h1>
	<a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/reports/overdue.php">Overdue report</a>
</div>

<div class="row g-3 mb-4">
	<div class="col-sm-6">
		<div class="card stat-card stat-fines h-100"><div class="card-body">
			<div class="stat-value"><?= e($formatRwf($unpaidTotal)) ?></div>
			<div class="stat-label">Outstanding</div>
		</div></div>
	</div>
	<div class="col-sm-6">
		<div class="card stat-card stat-avail h-100"><div class="card-body">
			<div class="stat-value"><?= e($formatRwf($paidTotal)) ?></div>
			<div class="stat-label">Collected</div>
		</div></div>
	</div>
</div>

<div class="table-responsive">
	<table class="table table-striped table-hover align-middle">
		<thead><tr><th>Member</th><th>Book</th><th>Due</th><th>Returned</th><th>Created</th><th class="text-end">Amount</th><th>Status</th><th class="text-end">Action</th></tr></thead>
		<tbody>
		<?php if ($fines === []): ?>
			<tr><td colspan="8" class="text-center text-muted py-4">No fines have been recorded.</td></tr>
		<?php else: ?>
			<?php foreach ($fines as $fine): ?>
				<tr>
					<td><?= e($fine['member_name']) ?></td>
					<td><?= e($fine['book_title']) ?></td>
					<td><?= e($fine['due_date']) ?></td>
					<td><?= e($fine['return_date'] ?? '—') ?></td>
					<td><?= e($fine['created_at']) ?></td>
					<td class="text-end"><?= e($formatRwf((float) $fine['amount'])) ?></td>
					<td>
						<?php if ((int) $fine['paid'] === 1): ?>
							<span class="badge bg-success">Paid<?= $fine['paid_at'] ? ' · ' . e($fine['paid_at']) : '' ?></span>
						<?php else: ?>
							<span class="badge bg-danger">Outstanding</span>
						<?php endif; ?>
					</td>
					<td class="text-end">
						<?php if ((int) $fine['paid'] === 0): ?>
							<form method="post" action="<?= e(BASE_URL) ?>/modules/fines/fines.php" onsubmit="return confirm('Record payment for this fine?');">
								<?= csrf_field() ?>
								<input type="hidden" name="action" value="pay">
								<input type="hidden" name="fine_id" value="<?= (int) $fine['fine_id'] ?>">
								<button class="btn btn-sm btn-outline-success" type="submit">Record payment</button>
							</form>
						<?php else: ?>
							<span class="text-muted">Complete</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
