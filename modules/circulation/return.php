<?php
/**
 * File: return.php
 * Module: Circulation
 * Assigned to: Shalom K
 * Status: DONE
 * Description: Return-books dashboard with active-loans table and per-row Return action
 */

// Use require_once __DIR__ . '/../../config/Database.php'; so paths work from any folder.
require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Loan.php';

// ------------------------------------------------------------
// POST: handle a "Return" action for one loan id.
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify();
        $loanId = validate_int($_POST['loan_id'] ?? null, 'Loan', 1);
        $summary = Loan::return($loanId);

        $msg = 'Loan #' . (int) $summary['loan_id'] . ' returned successfully.';
        if ((int) $summary['days_late'] > 0) {
            $msg .= ' Returned ' . (int) $summary['days_late']
                  . ' day' . ((int) $summary['days_late'] === 1 ? '' : 's')
                  . ' late.';
        } else {
            $msg .= ' Returned on time.';
        }
        if ((float) $summary['fine'] > 0) {
            $msg .= ' Fine: RWF ' . number_format((float) $summary['fine'], 0, '.', ',') . '.';
            set_flash('warning', $msg);
            $_SESSION['return_fine_link'] = true;
        } else {
            set_flash('success', $msg);
        }

        redirect(BASE_URL . '/modules/circulation/return.php');
    } catch (Throwable $e) {
        flash_exception($e);
    }
}

// ------------------------------------------------------------
// GET / post-error: load the active-loans table (mark overdue first).
// ------------------------------------------------------------
$activeLoans = Loan::getActiveLoans();

$formatRwf = function (float $amount): string {
    return 'RWF ' . number_format($amount, 0, '.', ',');
};

$pageTitle = 'Return Books';
require_once __DIR__ . '/../../includes/header.php';

// After the header renders show_flash() with the warning, if a fine was just created,
// display a clickable link to the Fines page right below the alerts.
if (!empty($_SESSION['return_fine_link'])):
    unset($_SESSION['return_fine_link']);
?>
<div class="alert alert-warning d-flex align-items-center justify-content-between">
    <span>💡 A new overdue fine has been recorded.</span>
    <a class="btn btn-sm btn-warning fw-semibold"
       href="<?= e(BASE_URL) ?>/modules/fines/fines.php">
        View fines →
    </a>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="h5 mb-0">📥 Return Books</h1>
        <div class="d-flex gap-2">
            <span class="badge bg-secondary">
                <?= count($activeLoans) ?> active loan<?= count($activeLoans) === 1 ? '' : 's' ?>
            </span>
            <a href="<?= e(BASE_URL) ?>/modules/circulation/borrow.php" class="btn btn-sm btn-outline-primary">
                Issue new loan
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th>Book</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th class="text-end">Est. Fine</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activeLoans) === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                🎉 No active loans. Every book has been returned.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeLoans as $idx => $l):
                            $loanId = (int) $l['loan_id'];
                            $isOverdue = (int) $l['days_overdue'] > 0;
                            $rowClass = $isOverdue ? 'table-danger' : '';
                            $statusClass = $l['status'] === 'overdue' ? 'bg-danger' : 'bg-success';
                            $statusLabel = ucfirst($l['status']);
                            if ($isOverdue) {
                                $statusLabel .= ' — ' . (int) $l['days_overdue'] . 'd late';
                            }
                        ?>
                            <tr class="<?= e($rowClass) ?>">
                                <td class="fw-semibold"><?= (int) $loanId ?></td>
                                <td><?= e($l['member_name']) ?></td>
                                <td class="fw-medium"><?= e($l['book_title']) ?></td>
                                <td><?= e($l['issue_date']) ?></td>
                                <td><?= e($l['due_date']) ?></td>
                                <td>
                                    <span class="badge <?= e($statusClass) ?>">
                                        <?= e($statusLabel) ?>
                                    </span>
                                </td>
                                <td class="text-end <?= $isOverdue ? 'fw-semibold text-danger' : '' ?>">
                                    <?= $isOverdue ? $formatRwf((float) $l['estimated_fine']) : '—' ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $confirmMsg = 'Return "' . $l['book_title'] . '" from ' . $l['member_name'] . '?';
                                    $confirmAttr = e('confirm(' . json_encode($confirmMsg) . ');');
                                    ?>
                                    <form method="post"
                                          action="<?= e(BASE_URL) ?>/modules/circulation/return.php"
                                          onsubmit="return <?= $confirmAttr ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden"
                                               name="loan_id"
                                               value="<?= (int) $loanId ?>">
                                        <button type="submit"
                                                class="btn btn-sm btn-outline-success">
                                            ✅ Return
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (count($activeLoans) > 0): ?>
        <div class="card-footer bg-white text-muted small">
            🔴 Red rows = overdue. Estimated fine (RWF) = days past due &times; <?= (int) DAILY_FINE ?>.
            The actual fine is charged when you click <em>Return</em>.
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
