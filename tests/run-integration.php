<?php

require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/catalog/Author.php';
require_once __DIR__ . '/../modules/catalog/Publisher.php';
require_once __DIR__ . '/../modules/catalog/Category.php';
require_once __DIR__ . '/../modules/catalog/Book.php';
require_once __DIR__ . '/../modules/members/Member.php';
require_once __DIR__ . '/../modules/auth/Librarian.php';
require_once __DIR__ . '/../modules/fines/Fine.php';
require_once __DIR__ . '/../modules/circulation/Loan.php';

function integration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function integration_expect(string $exceptionClass, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $e) {
        if ($e instanceof $exceptionClass) {
            return;
        }
        throw $e;
    }
    throw new RuntimeException($message);
}

$pdo = Database::getInstance()->getConnection();
$token = bin2hex(random_bytes(5));
$authorId = null;
$publisherId = null;
$categoryId = null;
$bookId = null;
$memberId = null;
$secondMemberId = null;
$librarianId = null;
$loanIds = [];
$passed = [];

try {
    $authorId = Author::create('Integration Author ' . $token);
    $publisherId = Publisher::create('Integration Publisher ' . $token);
    $categoryId = Category::create('Integration Category ' . $token);
    Author::update($authorId, 'Integration Author Updated ' . $token);
    integration_assert(Author::find($authorId)['name'] === 'Integration Author Updated ' . $token, 'Author update did not persist.');
    $passed[] = 'Lookup create, read, and update';

    $librarianId = Librarian::create([
        'full_name' => 'Integration Librarian ' . $token,
        'username' => 'test_' . $token,
        'password' => 'test-pass-123',
    ]);
    $authenticated = Librarian::authenticate('test_' . $token, 'test-pass-123');
    integration_assert((int) $authenticated['librarian_id'] === $librarianId, 'Librarian authentication failed.');
    integration_expect(
        BusinessRuleException::class,
        static fn() => Librarian::delete($librarianId, $librarianId),
        'Deleting the currently used librarian account should be blocked.'
    );
    $passed[] = 'Librarian account creation, password verification, and self-delete protection';

    $bookId = Book::create([
        'title' => 'Integration Test Book ' . $token,
        'isbn' => 'T' . $token,
        'category_id' => $categoryId,
        'publisher_id' => $publisherId,
        'published_year' => (string) date('Y'),
        'total_copies' => '2',
        'available_copies' => '0',
    ], [$authorId]);
    integration_assert((int) Book::find($bookId)['available_copies'] === 2, 'New books should start with every copy available.');

    $memberId = Member::create([
        'full_name' => 'Integration Member ' . $token,
        'email' => 'integration-' . $token . '@example.invalid',
        'phone' => '',
        'status' => 'active',
    ]);
    $secondMemberId = Member::create([
        'full_name' => 'Integration Member Two ' . $token,
        'email' => 'integration-two-' . $token . '@example.invalid',
        'phone' => '',
        'status' => 'active',
    ]);
    $loanIds[] = Loan::borrow($memberId, $bookId, $librarianId);
    $loanIds[] = Loan::borrow($secondMemberId, $bookId, $librarianId);
    integration_assert((int) Book::find($bookId)['available_copies'] === 0, 'Borrowing both copies should reduce availability to zero.');

    integration_expect(
        BusinessRuleException::class,
        static function () use ($bookId, $categoryId, $publisherId, $token): void {
            Book::update($bookId, [
                'title' => 'Integration Test Book ' . $token,
                'isbn' => 'T' . $token,
                'category_id' => $categoryId,
                'publisher_id' => $publisherId,
                'published_year' => (string) date('Y'),
                'total_copies' => '1',
            ], []);
        },
        'Total copies below the number on loan should be rejected.'
    );
    Book::update($bookId, [
        'title' => 'Integration Test Book ' . $token,
        'isbn' => 'T' . $token,
        'category_id' => $categoryId,
        'publisher_id' => $publisherId,
        'published_year' => (string) date('Y'),
        'total_copies' => '3',
        'available_copies' => '0',
    ], [$authorId]);
    integration_assert((int) Book::find($bookId)['available_copies'] === 1, 'Updating total copies should preserve the active-loan count.');
    $passed[] = 'Book availability transactions and outstanding-copy validation';

    $pdo->prepare('UPDATE loans SET issue_date = DATE_SUB(CURDATE(), INTERVAL 10 DAY), due_date = DATE_SUB(CURDATE(), INTERVAL 3 DAY) WHERE loan_id = ?')->execute([$loanIds[0]]);
    $returnSummary = Loan::return($loanIds[0]);
    integration_assert((float) $returnSummary['fine'] > 0, 'A late return should calculate a positive fine.');
    integration_assert(Fine::hasUnpaid($memberId), 'A late return should create an unpaid fine.');
    integration_expect(
        BusinessRuleException::class,
        static fn() => Loan::borrow($memberId, $bookId, $librarianId),
        'Borrowing with an unpaid fine should be blocked.'
    );
    $fine = Fine::findByLoan($loanIds[0]);
    Fine::markPaid($fine['fine_id']);
    integration_assert(!Fine::hasUnpaid($memberId), 'Recording payment should clear the unpaid fine.');
    integration_expect(
        BusinessRuleException::class,
        static fn() => Fine::markPaid($fine['fine_id']),
        'A fine must not be paid twice.'
    );
    $passed[] = 'Late-return fine creation, unpaid-fine borrowing block, and one-time payment';

    $loanIds[] = Loan::borrow($memberId, $bookId, $librarianId);
    $onTimeReturn = Loan::return($loanIds[2]);
    integration_assert((float) $onTimeReturn['fine'] === 0.0, 'An on-time return should not create a fine.');
    integration_assert((int) Book::find($bookId)['available_copies'] === 2, 'Returning a book should restore availability.');
    Loan::return($loanIds[1]);
    integration_assert((int) Book::find($bookId)['available_copies'] === 3, 'Returning all copies should restore full availability.');
    $passed[] = 'On-time return and inventory restoration';

    integration_expect(
        BusinessRuleException::class,
        static fn() => Category::delete($categoryId),
        'A category used by a book should not be deleted.'
    );
    $passed[] = 'Lookup deletion protected by book relationships';
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    foreach ($loanIds as $loanId) {
        $pdo->prepare('DELETE FROM fines WHERE loan_id = ?')->execute([$loanId]);
        $pdo->prepare('DELETE FROM loans WHERE loan_id = ?')->execute([$loanId]);
    }
    if ($bookId !== null) {
        $pdo->prepare('DELETE FROM books WHERE book_id = ?')->execute([$bookId]);
    }
    if ($memberId !== null) {
        $pdo->prepare('DELETE FROM members WHERE member_id = ?')->execute([$memberId]);
    }
    if ($secondMemberId !== null) {
        $pdo->prepare('DELETE FROM members WHERE member_id = ?')->execute([$secondMemberId]);
    }
    if ($librarianId !== null) {
        $pdo->prepare('DELETE FROM librarians WHERE librarian_id = ?')->execute([$librarianId]);
    }
    if ($authorId !== null) {
        $pdo->prepare('DELETE FROM authors WHERE author_id = ?')->execute([$authorId]);
    }
    if ($publisherId !== null) {
        $pdo->prepare('DELETE FROM publishers WHERE publisher_id = ?')->execute([$publisherId]);
    }
    if ($categoryId !== null) {
        $pdo->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$categoryId]);
    }
}

if (isset($exitCode)) {
    exit($exitCode);
}
foreach ($passed as $test) {
    echo 'PASS: ' . $test . PHP_EOL;
}
echo count($passed) . ' integration checks passed.' . PHP_EOL;
