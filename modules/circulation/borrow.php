<?php
/**
 * File: borrow.php
 * Module: Circulation
 * Assigned to: Shalom K
 * Status: DONE
 * Description: Issue a new book loan (form + POST handler)
 */

// Use require_once __DIR__ . '/../../config/Database.php'; so paths work from any folder.
require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Loan.php';

// ------------------------------------------------------------
// Page logic
// ------------------------------------------------------------

$pdo = Database::getInstance()->getConnection();
$selectedMember = null;
$selectedBook   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify();

        $memberId = validate_int($_POST['member_id'] ?? null, 'Member', 1);
        $bookId   = validate_int($_POST['book_id']   ?? null, 'Book',   1);
        $libId    = current_librarian_id();
        if ($libId === null) {
            throw new BusinessRuleException('No librarian is logged in.');
        }

        $loanId = Loan::borrow($memberId, $bookId, $libId);

        // Re-read the just-created loan so we can tell the user the exact due date.
        $stmt = $pdo->prepare('SELECT due_date FROM loans WHERE loan_id = ?');
        $stmt->execute([$loanId]);
        $due = $stmt->fetchColumn();

        set_flash(
            'success',
            'Book issued successfully (loan #' . $loanId . '). Due date: ' . $due . '.'
        );
        redirect(BASE_URL . '/modules/circulation/borrow.php');
    } catch (Throwable $e) {
        flash_exception($e);
        // Keep the user's selection so they don't have to re-pick after an error.
        $selectedMember = isset($_POST['member_id']) ? (int) $_POST['member_id'] : null;
        $selectedBook   = isset($_POST['book_id'])   ? (int) $_POST['book_id']   : null;
    }
}

// Load dropdown options (always, for both GET and after-error POST).
$members = $pdo->query(
    "SELECT member_id, full_name, email FROM members
      WHERE status = 'active'
      ORDER BY full_name"
)->fetchAll(PDO::FETCH_ASSOC);

$books = $pdo->query(
    'SELECT b.book_id, b.title, b.isbn, b.available_copies,
            c.name AS category, p.name AS publisher
       FROM books b
       JOIN categories c ON c.category_id = b.category_id
       JOIN publishers p ON p.publisher_id = b.publisher_id
      WHERE b.available_copies > 0
      ORDER BY b.title'
)->fetchAll(PDO::FETCH_ASSOC);

// Compute the due date shown in the form preview (today + LOAN_DAYS).
$todayObj = new DateTime('today');
$dueObj   = clone $todayObj;
$dueObj->modify('+' . LOAN_DAYS . ' days');
$previewDue = $dueObj->format('l, F j, Y');

$pageTitle = 'Borrow Book';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xl-7">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1 class="h5 mb-0">📤 Borrow Book</h1>
                <span class="badge bg-info text-dark">
                    Loan period: <?= (int) LOAN_DAYS ?> days
                </span>
            </div>
            <div class="card-body">

                <!-- Due date preview -->
                <div class="alert alert-info mb-4 d-flex align-items-center">
                    <div class="me-3 fs-4">📅</div>
                    <div>
                        <div class="fw-semibold">Due date preview</div>
                        <div class="small">
                            If you borrow today, the book will be due on:
                            <strong><?= e($previewDue) ?></strong>
                        </div>
                    </div>
                </div>

                <form method="post"
                      action="<?= e(BASE_URL) ?>/modules/circulation/borrow.php"
                      novalidate>
                    <?= csrf_field() ?>

                    <!-- Member dropdown -->
                    <div class="mb-3">
                        <label for="member_id" class="form-label">
                            Member
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg"
                                id="member_id" name="member_id" required>
                            <option value="">-- Select an active member --</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= (int) $m['member_id'] ?>"
                                    <?= $selectedMember === (int) $m['member_id'] ? 'selected' : '' ?>>
                                    <?= e($m['full_name']) ?>
                                    &lt;<?= e($m['email']) ?>&gt;
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (count($members) === 0): ?>
                            <div class="form-text text-danger">
                                No active members exist yet. Register one on the Members page first.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Book dropdown -->
                    <div class="mb-4">
                        <label for="book_id" class="form-label">
                            Book
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg"
                                id="book_id" name="book_id" required>
                            <option value="">-- Select a book with available copies --</option>
                            <?php foreach ($books as $b): ?>
                                <option value="<?= (int) $b['book_id'] ?>"
                                    <?= $selectedBook === (int) $b['book_id'] ? 'selected' : '' ?>>
                                    <?= e($b['title']) ?>
                                    — <?= e($b['isbn']) ?>
                                    — <?= e($b['category']) ?> / <?= e($b['publisher']) ?>
                                    (<?= (int) $b['available_copies'] ?>
                                     <?= (int) $b['available_copies'] === 1 ? 'copy' : 'copies' ?> avail.)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (count($books) === 0): ?>
                            <div class="form-text text-danger">
                                No books are currently available. Add copies in the Books section.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= e(BASE_URL) ?>/modules/circulation/return.php"
                           class="btn btn-outline-secondary">
                            Go to Returns
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            ✅ Issue Loan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
