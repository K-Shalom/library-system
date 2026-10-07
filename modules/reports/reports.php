<?php
/**
 * File: reports.php
 * Module: Reports
 * Assigned to: Sabin Levis
 * Status: DONE
 * Description: Circulation summary analytics UI
 */

// --- Convention 4: bootstrap helpers, enforce login ---
require_once __DIR__ . '/../../config/functions.php';
require_login();

// Initialize defaults (will be overwritten on valid POST/GET filter submission)
$fromDate = '';
$toDate   = '';
$filterApplied = false;
$errors = [];

// Circulation summary defaults
$issuedCount   = 0;
$returnedCount = 0;
$stillOutCount = 0;
$topBooks = [];
$availability = [];
$finesCollected   = 0.0;
$finesOutstanding = 0.0;
$finesTotal       = 0.0;

try {
    $pdo = Database::getInstance()->getConnection();

    // --- Handle date range filter (GET so results are shareable, validate on submit) ---
    if (isset($_GET['from_date']) || isset($_GET['to_date'])) {
        $filterApplied = true;

        // 1. Validate that both dates are present and real (YYYY-MM-DD).
        $fromRaw = trim((string) ($_GET['from_date'] ?? ''));
        $toRaw   = trim((string) ($_GET['to_date']   ?? ''));
        if ($fromRaw === '' || $toRaw === '') {
            $errors[] = 'Both "From" and "To" dates are required.';
        } else {
            try {
                $fromDate = validate_date($fromRaw, 'From date');
            } catch (ValidationException $e) {
                $errors[] = $e->getMessage();
            }
            try {
                $toDate = validate_date($toRaw, 'To date');
            } catch (ValidationException $e) {
                $errors[] = $e->getMessage();
            }

            if (count($errors) === 0 && $fromDate > $toDate) {
                $errors[] = '"From" date must be on or before "To" date.';
            }
        }
    }

    // ------------------------------------------------------------
    // Run the report only if:
    //   (a) No filter applied yet (show all-time summary), OR
    //   (b) Filter was submitted and passed validation.
    // ------------------------------------------------------------
    if (count($errors) === 0) {

        // WHERE fragments: date ranges use issue_date for issued/returned stats.
        if ($filterApplied) {
            $dateWhere   = 'WHERE l.issue_date BETWEEN :from_date AND :to_date';
            $dateParams  = [':from_date' => $fromDate, ':to_date' => $toDate];
        } else {
            $dateWhere   = ''; // no restriction
            $dateParams  = [];
        }

        // ---- 1) Loans issued in the period -----------------------
        $stmtIssued = $pdo->prepare(
            "SELECT COUNT(*) FROM loans l $dateWhere"
        );
        $stmtIssued->execute($dateParams);
        $issuedCount = (int) $stmtIssued->fetchColumn();

        // ---- 2) Loans returned in the period ---------------------
        // Count by return_date (filter on return_date instead of issue_date).
        if ($filterApplied) {
            $returnedWhere = 'WHERE l.return_date BETWEEN :from_date AND :to_date';
            $returnedParams = [':from_date' => $fromDate, ':to_date' => $toDate];
        } else {
            $returnedWhere = "WHERE l.return_date IS NOT NULL";
            $returnedParams = [];
        }
        $stmtReturned = $pdo->prepare(
            "SELECT COUNT(*) FROM loans l $returnedWhere"
        );
        $stmtReturned->execute($returnedParams);
        $returnedCount = (int) $stmtReturned->fetchColumn();

        // ---- 3) Loans still out (return_date IS NULL as of NOW) --
        // Note: this count is always "current snapshot", independent of date range.
        $stillOutStmt = $pdo->query(
            "SELECT COUNT(*) FROM loans WHERE return_date IS NULL"
        );
        $stillOutCount = (int) $stillOutStmt->fetchColumn();

        // ---- 4) Top 5 most borrowed books ------------------------
        // (Optional: restrict to loans issued in the range when filtering.)
        if ($filterApplied) {
            $topWhere = 'WHERE l.issue_date BETWEEN :from_date AND :to_date';
            $topParams = [':from_date' => $fromDate, ':to_date' => $toDate];
        } else {
            $topWhere = '';
            $topParams = [];
        }
        $stmtTop = $pdo->prepare(
            "SELECT b.book_id, b.title, b.isbn,
                    COUNT(l.loan_id) AS borrow_count
               FROM books b
               LEFT JOIN loans l ON l.book_id = b.book_id $topWhere
              GROUP BY b.book_id, b.title, b.isbn
              HAVING borrow_count > 0
              ORDER BY borrow_count DESC, b.title ASC
              LIMIT 5"
        );
        $stmtTop->execute($topParams);
        $topBooks = $stmtTop->fetchAll();

        // ---- 5) Availability table: every book's stock snapshot ---
        $stmtAvail = $pdo->query(
            "SELECT b.book_id, b.title,
                    b.total_copies,
                    b.available_copies,
                    (b.total_copies - b.available_copies) AS borrowed_copies,
                    CASE WHEN b.available_copies > 0 THEN 'Available'
                         ELSE 'Not available' END AS availability_status
               FROM books b
              ORDER BY b.title ASC"
        );
        $availability = $stmtAvail->fetchAll();

        // ---- 6) Fines: collected vs outstanding vs total ---------
        // Collected fines (paid = 1): if filter applied, count by paid_at in the window.
        if ($filterApplied) {
            $fCollStmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                   FROM fines
                  WHERE paid = 1
                    AND paid_at BETWEEN :from_date AND :to_date"
            );
            $fCollStmt->execute([':from_date' => $fromDate . ' 00:00:00',
                                 ':to_date'   => $toDate   . ' 23:59:59']);
        } else {
            $fCollStmt = $pdo->query(
                "SELECT COALESCE(SUM(amount), 0) FROM fines WHERE paid = 1"
            );
        }
        $finesCollected = (float) $fCollStmt->fetchColumn();

        // Outstanding fines are always as of today (no date window).
        $fOutStmt = $pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM fines WHERE paid = 0"
        );
        $finesOutstanding = (float) $fOutStmt->fetchColumn();
        $finesTotal = $finesCollected + $finesOutstanding;
    }

    // Flash any validation errors so the user sees them.
    if (count($errors) > 0) {
        foreach ($errors as $err) {
            set_flash('danger', $err);
        }
    }
} catch (Throwable $e) {
    flash_exception($e);
}

// Helpers
$formatRwf = function (float $amount): string {
    return 'RWF ' . number_format($amount, 0, '.', ',');
};
$formatInt = function (int $n): string {
    return number_format($n);
};

$pageTitle = 'Circulation Summary Report';
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ===== Print-only report header ===== -->
<div class="print-header">
    <h1>College Library — Circulation Summary Report</h1>
    <p>Generated on <?= date('Y-m-d H:i') ?></p>
    <?php if ($filterApplied && count($errors) === 0): ?>
        <p>Period: <?= e($fromDate) ?> to <?= e($toDate) ?></p>
    <?php else: ?>
        <p>Period: All-time</p>
    <?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 print-hide">
    <h1 class="h3 mb-0">Circulation Summary Report</h1>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        🖨️ Print Report
    </button>
</div>

<?php show_flash(); ?>

<!-- ===== Date Range Filter (screen only) ===== -->
<div class="card shadow-sm mb-4 print-hide">
    <div class="card-header">Filter by Period</div>
    <form method="get" action="<?= e(BASE_URL) ?>/modules/reports/reports.php" class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="from_date" class="form-label">From Date</label>
                <input type="date" class="form-control" id="from_date" name="from_date"
                       value="<?= e($fromDate) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label for="to_date" class="form-label">To Date</label>
                <input type="date" class="form-control" id="to_date" name="to_date"
                       value="<?= e($toDate) ?>" required>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
            <div class="col-6 col-md-2">
                <a href="<?= e(BASE_URL) ?>/modules/reports/reports.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
            <div class="col-12">
                <small class="form-text text-muted">
                    Tip: "Loans issued" and "Top books" use <strong>issue_date</strong>.
                    "Loans returned" and "Fines collected" use the actual return/payment date.
                    "Still out" and "Outstanding fines" are a current snapshot (independent of the filter).
                </small>
            </div>
        </div>
    </form>
</div>

<?php if (count($errors) > 0): ?>
    <!-- Validation failed: show only the filter; skip the rest. -->
<?php else: ?>

    <!-- ===== Section 1: Circulation Overview ===== -->
    <section class="mb-4">
        <h2 class="h5 mb-3 border-bottom pb-2">
            1. Circulation Overview
            <?php if ($filterApplied): ?>
                <small class="text-muted fw-normal">(<?= e($fromDate) ?> → <?= e($toDate) ?>)</small>
            <?php else: ?>
                <small class="text-muted fw-normal">(all-time)</small>
            <?php endif; ?>
        </h2>
        <div class="row g-3">
            <div class="col-6 col-md-4">
                <div class="card stat-card stat-loans h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value"><?= $formatInt($issuedCount) ?></div>
                            <div class="stat-label">Loans Issued</div>
                        </div>
                        <div class="stat-icon">📤</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card stat-card stat-avail h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value"><?= $formatInt($returnedCount) ?></div>
                            <div class="stat-label">Loans Returned</div>
                        </div>
                        <div class="stat-icon">✅</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card stat-card stat-books h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value"><?= $formatInt($stillOutCount) ?></div>
                            <div class="stat-label">Still Out (current)</div>
                        </div>
                        <div class="stat-icon">⏳</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Section 2: Top 5 Most Borrowed Books ===== -->
    <section class="mb-4">
        <h2 class="h5 mb-3 border-bottom pb-2">2. Top 5 Most Borrowed Books</h2>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="text-center">Rank</th>
                            <th>Title</th>
                            <th>ISBN</th>
                            <th class="text-center">Times Borrowed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($topBooks) === 0): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No book borrowings in this period.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topBooks as $rank => $b): ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= $rank + 1 ?></span>
                                    </td>
                                    <td class="fw-semibold"><?= e($b['title']) ?></td>
                                    <td class="font-monospace small"><?= e($b['isbn']) ?></td>
                                    <td class="text-center fw-semibold"><?= $formatInt((int) $b['borrow_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ===== Section 3: Book Availability Snapshot ===== -->
    <section class="mb-4 page-break">
        <h2 class="h5 mb-3 border-bottom pb-2">3. Book Availability (current snapshot)</h2>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th class="text-center">Total Copies</th>
                            <th class="text-center">Available</th>
                            <th class="text-center">Borrowed</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($availability) === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No books in the catalog.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($availability as $av):
                                $availCount = (int) $av['available_copies'];
                                $totalCount = (int) $av['total_copies'];
                                $statusClass = $availCount > 0 ? 'bg-success' : 'bg-danger';
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($av['title']) ?></td>
                                    <td class="text-center"><?= $formatInt($totalCount) ?></td>
                                    <td class="text-center"><?= $formatInt($availCount) ?></td>
                                    <td class="text-center"><?= $formatInt((int) $av['borrowed_copies']) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $statusClass ?>">
                                            <?= e($av['availability_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ===== Section 4: Fines Collected vs Outstanding ===== -->
    <section class="mb-4">
        <h2 class="h5 mb-3 border-bottom pb-2">4. Fines Overview</h2>
        <div class="row g-3">
            <div class="col-6 col-md-4">
                <div class="card stat-card stat-avail h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value" style="font-size:1.35rem;"><?= $formatRwf($finesCollected) ?></div>
                            <div class="stat-label">Collected
                                <?php if ($filterApplied): ?>(period)<?php else: ?>(all-time)<?php endif; ?>
                            </div>
                        </div>
                        <div class="stat-icon">✅</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card stat-card stat-overdue h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value" style="font-size:1.35rem;"><?= $formatRwf($finesOutstanding) ?></div>
                            <div class="stat-label">Outstanding (current)</div>
                        </div>
                        <div class="stat-icon">⚠️</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card stat-card stat-fines h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value" style="font-size:1.35rem;"><?= $formatRwf($finesTotal) ?></div>
                            <div class="stat-label">Total Fines (collected + outstanding)</div>
                        </div>
                        <div class="stat-icon">💰</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php endif; /* validation passed */ ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
