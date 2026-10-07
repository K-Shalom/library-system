<?php
/**
 * File: history.php
 * Module: Members
 * Assigned to: H Muhamadi
 * Status: DONE
 * Description: Member borrowing history view
 */

require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Member.php';

$memberId = $_GET['id'] ?? null;
$member = null;
$history = [];
$totalLoans = 0;
$currentlyBorrowed = 0;
$totalUnpaidFines = 0.0;

try {
    $member = Member::find($memberId);
    $history = Member::getHistory($memberId);

    $totalLoans = count($history);
    foreach ($history as $loan) {
        if ($loan['return_date'] === null || $loan['return_date'] === '') {
            $currentlyBorrowed++;
        }
        if ($loan['fine_amount'] !== null && (float)$loan['fine_amount'] > 0 && !(int)$loan['fine_paid']) {
            $totalUnpaidFines += (float)$loan['fine_amount'];
        }
    }
} catch (Throwable $e) {
    flash_exception($e);
    redirect(BASE_URL . '/modules/members/list.php');
}

$pageTitle = 'Member Borrowing History - ' . $member['full_name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h1 class="h3 mb-0">Borrowing History</h1>
        <p class="text-muted mb-0 small">Viewing circulation history and fines for <?= e($member['full_name']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/members/list.php">Back to list</a>
</div>

<!-- Member Details & Stat Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-light">
                <h2 class="h5 mb-0">Member Profile</h2>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th scope="row" class="text-muted" style="width: 130px;">Member ID:</th>
                        <td class="fw-bold">#<?= (int) $member['member_id'] ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">Full Name:</th>
                        <td><?= e($member['full_name']) ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">Email:</th>
                        <td><?= e($member['email']) ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">Phone:</th>
                        <td><?= e($member['phone'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">Status:</th>
                        <td>
                            <?php if (strtolower($member['status']) === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">Registered:</th>
                        <td><?= e($member['registered_at'] ? date('M d, Y', strtotime($member['registered_at'])) : '—') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="row g-3 h-100">
            <div class="col-sm-4">
                <div class="card h-100 shadow-sm border-start border-primary border-4 text-center py-3">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Total Loans</div>
                        <div class="display-6 fw-bold text-primary mt-2"><?= $totalLoans ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card h-100 shadow-sm border-start border-warning border-4 text-center py-3">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Currently Borrowed</div>
                        <div class="display-6 fw-bold text-warning mt-2"><?= $currentlyBorrowed ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card h-100 shadow-sm border-start border-danger border-4 text-center py-3">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Unpaid Fines</div>
                        <div class="fs-4 fw-bold text-danger mt-2"><?= number_format($totalUnpaidFines, 0) ?> RWF</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loan History Table -->
<h2 class="h5 mb-3">Loan Records</h2>
<?php if ($history === []): ?>
    <div class="card card-body text-center py-4 text-muted">
        This member has no loan history yet.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th scope="col">Loan ID</th>
                    <th scope="col">Book Title</th>
                    <th scope="col">Issue Date</th>
                    <th scope="col">Due Date</th>
                    <th scope="col">Return Date</th>
                    <th scope="col">Status</th>
                    <th scope="col">Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $loan): ?>
                    <tr>
                        <td>#<?= (int) $loan['loan_id'] ?></td>
                        <td class="fw-bold"><?= e($loan['book_title']) ?></td>
                        <td><?= e($loan['issue_date']) ?></td>
                        <td><?= e($loan['due_date']) ?></td>
                        <td>
                            <?php if ($loan['return_date']): ?>
                                <?= e($loan['return_date']) ?>
                            <?php else: ?>
                                <span class="text-muted">Not returned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $st = $loan['computed_status'] ?? 'Borrowed';
                            if ($st === 'Returned') {
                                echo '<span class="badge bg-success">Returned</span>';
                            } elseif ($st === 'Overdue') {
                                echo '<span class="badge bg-danger">Overdue</span>';
                            } else {
                                echo '<span class="badge bg-primary">Borrowed</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($loan['fine_amount'] !== null && (float)$loan['fine_amount'] > 0): ?>
                                <?php if ((int)$loan['fine_paid']): ?>
                                    <span class="text-success small fw-semibold"><?= number_format((float)$loan['fine_amount'], 0) ?> RWF (Paid)</span>
                                <?php else: ?>
                                    <span class="text-danger small fw-semibold"><?= number_format((float)$loan['fine_amount'], 0) ?> RWF (Unpaid)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
