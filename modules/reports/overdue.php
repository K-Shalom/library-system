<?php
/**
 * File: overdue.php
 * Module: Reports
 * Assigned to: Sabin Levis
 * Status: DONE
 * Description: Overdue books report page
 */

// --- Convention 4: bootstrap helpers, enforce login ---
require_once __DIR__ . '/../../config/functions.php';
require_login();

try {
    $pdo = Database::getInstance()->getConnection();

    // Overdue loans = return_date IS NULL AND due_date < CURDATE()
    // Sorted by most-overdue first (largest days-overdue first).
    $stmt = $pdo->prepare(
        "SELECT l.loan_id, l.due_date, l.issue_date,
                DATEDIFF(CURDATE(), l.due_date) AS days_overdue,
                b.title AS book_title,
                b.isbn  AS book_isbn,
                m.member_id,
                m.full_name AS member_name,
                m.email AS member_email,
                m.phone AS member_phone
           FROM loans l
           JOIN books   b ON b.book_id   = l.book_id
           JOIN members m ON m.member_id = l.member_id
          WHERE l.return_date IS NULL
            AND l.due_date < CURDATE()
          ORDER BY days_overdue DESC, l.due_date ASC"
    );
    $stmt->execute();
    $overdueLoans = $stmt->fetchAll();

    // Totals for the summary strip (also shown in printed output).
    $countOverdue = count($overdueLoans);
    $totalEstimatedFine = 0.0;
    foreach ($overdueLoans as $loan) {
        $totalEstimatedFine += (int) $loan['days_overdue'] * DAILY_FINE;
    }
} catch (Throwable $e) {
    flash_exception($e);
    $overdueLoans = [];
    $countOverdue = 0;
    $totalEstimatedFine = 0.0;
}

$formatRwf = function (float $amount): string {
    return 'RWF ' . number_format($amount, 0, '.', ',');
};

$pageTitle = 'Overdue Loans Report';
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ===== Print-only report header ===== -->
<div class="print-header">
    <h1>College Library — Overdue Loans Report</h1>
    <p>Generated on <?= date('Y-m-d H:i') ?> | <?= $countOverdue ?> overdue loan(s) |
       Estimated total: <?= $formatRwf($totalEstimatedFine) ?></p>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 print-hide">
    <h1 class="h3 mb-0">Overdue Loans Report</h1>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        🖨️ Print Report
    </button>
</div>

<!-- Summary strip (screen + print) -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card stat-card stat-overdue">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?= number_format($countOverdue) ?></div>
                    <div class="stat-label">Overdue Loans</div>
                </div>
                <div class="stat-icon">⚠️</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card stat-card stat-fines">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value" style="font-size:1.35rem;"><?= $formatRwf($totalEstimatedFine) ?></div>
                    <div class="stat-label">Estimated Total Fine (<?= $formatRwf(DAILY_FINE) ?> / day)</div>
                </div>
                <div class="stat-icon">💸</div>
            </div>
        </div>
    </div>
</div>

<?php if ($countOverdue === 0): ?>
    <div class="alert alert-info">
        🎉 No overdue loans at the moment. All borrowed books are still within their due date.
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th>Phone</th>
                        <th>Book</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th class="text-center">Days Overdue</th>
                        <th class="text-end">Estimated Fine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdueLoans as $i => $row):
                        $days = (int) $row['days_overdue'];
                        $fine = $days * DAILY_FINE;
                    ?>
                        <tr>
                            <td class="text-muted small"><?= $i + 1 ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($row['member_name']) ?></div>
                                <div class="small text-muted"><?= e($row['member_email']) ?></div>
                            </td>
                            <td><?= e($row['member_phone'] ?? '—') ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($row['book_title']) ?></div>
                                <div class="small text-muted font-monospace"><?= e($row['book_isbn']) ?></div>
                            </td>
                            <td><?= e($row['issue_date']) ?></td>
                            <td class="text-danger"><?= e($row['due_date']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-danger"><?= $days ?> day<?= $days !== 1 ? 's' : '' ?></span>
                            </td>
                            <td class="text-end fw-semibold"><?= $formatRwf($fine) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="7" class="text-end">TOTAL ESTIMATED</td>
                        <td class="text-end"><?= $formatRwf($totalEstimatedFine) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
