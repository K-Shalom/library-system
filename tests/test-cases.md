# Test Cases and Results

## Environment

- PHP CLI and PDO MySQL
- MySQL/MariaDB database configured by `config/Database.php`
- Development database containing the schema from `database.sql`
- Automated command: `php tests/run-integration.php`

The integration script creates uniquely named fixtures and removes only those fixtures afterward. Run it against a development database, not a production database.

## Automated Integration Results

Recorded on 2026-10-07. Result: **6 integration checks passed.**

| ID | Test | Expected result | Result |
|---|---|---|---|
| I-01 | Create, read, and update an author lookup | Updated lookup value is returned | PASS |
| I-02 | Create librarian and authenticate with its password | Password verifies; deleting the active account is rejected | PASS |
| I-03 | Register a new book with inconsistent posted availability | All new copies start available | PASS |
| I-04 | Borrow two copies, then reduce total stock below active loans | Borrowing decrements availability; invalid stock reduction is rejected; valid stock increase preserves the loan count | PASS |
| I-05 | Return late, try borrowing with unpaid fine, pay fine, and attempt duplicate payment | Fine is recorded; unpaid-fine borrowing is blocked; first payment succeeds; repeat payment is rejected | PASS |
| I-06 | Return on time and remove a lookup used by a book | No on-time fine; availability is restored; referenced lookup deletion is rejected | PASS |

## Manual UI Cases

These browser-level cases remain for the group to execute and record during practical testing. Model-level integration results do not replace UI verification.

| ID | Test steps | Expected result | Result |
|---|---|---|---|
| M-01 | Log in with valid and invalid credentials | Valid login opens dashboard; invalid login shows a safe message | Pending practical run |
| M-02 | Add, search, edit, and delete a book without loan history | Catalog reflects changes; invalid fields show a friendly validation message | Pending practical run |
| M-03 | Add and edit a member; inspect their borrowing history | Member details persist and history is displayed | Pending practical run |
| M-04 | Add, edit, and delete an author, publisher, and category | Lookup changes appear in book forms; referenced lookup cannot be deleted | Pending practical run |
| M-05 | Issue a book, then attempt invalid borrowing operations | Valid issue updates availability; invalid operation shows a business-rule message | Pending practical run |
| M-06 | Return an on-time and overdue loan | Return is recorded once; overdue return creates a fine and restores availability | Pending practical run |
| M-07 | Record a fine payment and retry the same payment | First payment is recorded; duplicate payment is rejected | Pending practical run |
| M-08 | Open overdue and circulation summary reports with valid and invalid date ranges | Reports show expected records; invalid dates show a friendly message | Pending practical run |
| M-09 | Open protected pages while logged out and submit a form without a valid CSRF token | Login is required; invalid token is rejected without exposing technical details | Pending practical run |

## How to Re-run

```sh
php tests/run-integration.php
```
