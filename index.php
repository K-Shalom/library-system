<?php
/**
 * File: index.php
 * Module: Core
 * Assigned to: Sabin Levis
 * Status: DONE
 * Description: Main librarian dashboard
 */

// --- Convention 4: bootstrap helpers, enforce login ---
require_once __DIR__ . '/config/functions.php';
require_login();

try {
    $pdo = Database::getInstance()->getConnection();

    // --- 1) Aggregate dashboard counters (single prepared queries per card) ---

    // Total unique book titles = number of rows in books table.
    $stmtTotalTitles = $pdo->query('SELECT COUNT(*) FROM books');
    $totalTitles = (int) $stmtTotalTitles->fetchColumn();

    // Total available copies = sum of available_copies across all books.
    $stmtAvail = $pdo->query('SELECT COALESCE(SUM(available_copies), 0) FROM books');
    $availableCopies = (int) $stmtAvail->fetchColumn();

    // Active members = count(status = 'active').
    $stmtActiveMembers = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'");
    $activeMembers = (int) $stmtActiveMembers->fetchColumn();

    // Active loans = unreturned loans (return_date IS NULL) with status borrowed.
    $stmtActiveLoans = $pdo->query(
        "SELECT COUNT(*) FROM loans WHERE return_date IS NULL AND status = 'borrowed'"
    );
    $activeLoans = (int) $stmtActiveLoans->fetchColumn();

    // Overdue loans = return_date IS NULL AND due_date < CURDATE().
    $stmtOverdue = $pdo->prepare(
        "SELECT COUNT(*)
           FROM loans
          WHERE return_date IS NULL
            AND due_date < CURDATE()"
    );
    $stmtOverdue->execute();
    $overdueLoans = (int) $stmtOverdue->fetchColumn();

    // Total unpaid fines in RWF = SUM(amount) where paid = 0.
    $stmtUnpaid = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM fines WHERE paid = 0');
    $totalUnpaidFines = (float) $stmtUnpaid->fetchColumn();

    // --- 2) 5 most recent loans ---
    $stmtRecent = $pdo->prepare(
        "SELECT l.loan_id, l.issue_date, l.due_date, l.status,
                b.title AS book_title,
                m.full_name AS member_name
           FROM loans l
           JOIN books   b ON b.book_id   = l.book_id
           JOIN members m ON m.member_id = l.member_id
          ORDER BY l.loan_id DESC
          LIMIT 5"
    );
    $stmtRecent->execute();
    $recentLoans = $stmtRecent->fetchAll();
} catch (Throwable $e) {
    flash_exception($e);
    // Provide safe defaults so the UI still renders even when DB is down.
    $totalTitles = $availableCopies = $activeMembers = 0;
    $activeLoans = $overdueLoans = 0;
    $totalUnpaidFines = 0.0;
    $recentLoans = [];
}

// Helper: format RWF money with thousand separators and no decimals (DAILY_FINE is integer).
$formatRwf = function (float $amount): string {
    return 'RWF ' . number_format($amount, 0, '.', ',');
};

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== Print-only report header ===== -->
<div class="print-header">
    <h1>College Library Management System</h1>
    <p>Dashboard Summary — Printed on <?= date('Y-m-d H:i') ?></p>
</div>

<!-- ===== Stat Cards (6) ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card stat-books h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= number_format($totalTitles) ?></div>
                    <div class="stat-label">Book Titles</div>
                </div>
                <div class="stat-icon">📖</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card stat-avail h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= number_format($availableCopies) ?></div>
                    <div class="stat-label">Available Copies</div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card stat-members h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= number_format($activeMembers) ?></div>
                    <div class="stat-label">Active Members</div>
                </div>
                <div class="stat-icon">👥</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card stat-loans h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= number_format($activeLoans) ?></div>
                    <div class="stat-label">Active Loans</div>
                </div>
                <div class="stat-icon">📤</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card stat-overdue h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= number_format($overdueLoans) ?></div>
                    <div class="stat-label">Overdue Loans</div>
                </div>
                <div class="stat-icon">⚠️</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card stat-fines h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value" style="font-size:1.3rem;"><?= $formatRwf($totalUnpaidFines) ?></div>
                    <div class="stat-label">Unpaid Fines</div>
                </div>
                <div class="stat-icon">💸</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Quick Actions ===== -->
<div class="card shadow-sm mb-4 print-hide">
    <div class="card-header">Quick Actions</div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <a href="<?= e(BASE_URL) ?>/modules/circulation/borrow.php" class="btn btn-primary w-100 py-2">
                    📤 Borrow Book
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= e(BASE_URL) ?>/modules/circulation/return.php" class="btn btn-outline-success w-100 py-2">
                    📥 Return Book
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= e(BASE_URL) ?>/modules/catalog/book_form.php" class="btn btn-outline-primary w-100 py-2">
                    ➕ Add Book
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= e(BASE_URL) ?>/modules/members/form.php" class="btn btn-outline-secondary w-100 py-2">
                    👤 Register Member
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ===== Recent Loans ===== -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>5 Most Recent Loans</span>
        <button type="button" class="btn btn-sm btn-outline-secondary print-hide" onclick="window.print()">
            🖨️ Print
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Member</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentLoans) === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No loans recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLoans as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['book_title']) ?></td>
                            <td><?= e($r['member_name']) ?></td>
                            <td><?= e($r['issue_date']) ?></td>
                            <td><?= e($r['due_date']) ?></td>
                            <td class="text-center">
                                <span class="badge status-<?= e($r['status']) ?>">
                                    <?= ucfirst(e($r['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
