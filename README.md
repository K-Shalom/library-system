# College Library and Book Circulation Management System

Group 3 project: a database-driven PHP application (PHP 8, PDO, prepared statements, MySQL/MariaDB) for managing books, members, borrowing, returns, overdue tracking and fines.

## Setup (XAMPP)

1. Copy or symlink this folder into the XAMPP web root (on Linux: `/opt/lampp/htdocs/library-system`).
2. Start Apache and MySQL/MariaDB in XAMPP.
3. Import the database: open `http://localhost/phpmyadmin`, then Import, then choose `database.sql`. Or run: `/opt/lampp/bin/mysql -u root < database.sql`
4. Make sure the `logs/` folder is writable by the web server.
5. Open `http://localhost/library-system/`

## Default credentials (development / sample data only)

| Purpose | Username | Password |
|---|---|---|
| Librarian login (application) | `admin` | `admin123` |
| Database (XAMPP default) | `root` | (empty) |

Database name: `library_db`. Host: `localhost`. These are sample credentials for local development only. Change them before any real deployment.

## Database tables

`authors`, `publishers`, `categories`, `books`, `book_authors`, `members`, `librarians`, `loans`, `fines`. The full schema is in `database.sql`.

## Shared settings and conventions

- Constants (in `config/Database.php`): `LOAN_DAYS=14`, `DAILY_FINE=100`, `MAX_LOANS=3`.
- Get the connection with `Database::getInstance()->getConnection()`.
- Custom exceptions (in `config/exceptions.php`): `ValidationException`, `BusinessRuleException`, `NotFoundException`, `DatabaseException`.
- All queries use PDO prepared statements. Technical errors are logged to `logs/error.log` and never shown to users.
- Cross-module methods: `Book::find`, `Book::decrementAvailable`, `Book::incrementAvailable`, `Member::find`, `Member::isActive`, `Fine::calculate`, `Fine::hasUnpaid`, `Fine::create`, `Loan::borrow`, `Loan::return`, `Loan::getOverdue`.

## Team and module ownership

| Member | Module / files | Status |
|---|---|---|
| Shalom K (group admin) | `database.sql`, `config/Database.php`, `config/exceptions.php` | DONE |
| Shalom K | `modules/circulation/` (Loan, borrow, return) | TODO |
| Jose Narame | `modules/auth/`, `config/functions.php`, `error.php` | TODO |
| Sabin Levis | `includes/`, `assets/`, `index.php`, `modules/reports/` | TODO |
| Adeline N | `modules/catalog/` (Book, books, book_form) | TODO |
| Augustin Mugisha | `modules/catalog/` (Author, Publisher, Category, lookups), `modules/fines/` | TODO |
| H Muhamadi | `modules/members/` | TODO |

## Progress log

### Shalom K
- Created the project scaffold and pushed it to GitHub.
- Designed and wrote `database.sql`: 9 tables with primary keys, foreign keys, CHECK constraints, indexes, and sample data (1 librarian, 10 books, 5 authors, 3 publishers, 4 categories, 5 members, 3 sample loans including an overdue loan and a fine).
- Implemented `config/Database.php`: PDO Singleton connection with exception mode, safe error logging, and system constants.
- Implemented `config/exceptions.php`: custom exception classes for validation, business rules, missing records, and database failures.

### Jose Narame
- Finished `config/functions.php` (helpers, flash, CSRF, validation), `modules/auth/Librarian.php`, `modules/auth/login.php`, `modules/auth/logout.php`, and `error.php`.

### Adeline N
- Finished `modules/catalog/Book.php`, `modules/catalog/books.php`, and `modules/catalog/book_form.php` (book model, searchable catalog, and add/edit form).

### Sabin Levis
- Finished `includes/header.php` (Bootstrap 5.3 CDN head + responsive navbar with all module links, Reports dropdown, librarian name, logout; opens container + calls show_flash() with active-page highlighting), `includes/footer.php` (closes container, Group 3 footer text, Bootstrap JS bundle), `assets/style.css` (stat cards, tables, status badges, full @media print styles with .print-hide and .print-header helpers).
- Finished `index.php` dashboard: 6 stat cards (book titles, available copies, active members, active loans, overdue loans, unpaid fines in RWF), 4 quick-action buttons, 5-most-recent-loans table with status badges, direct PDO read-only prepared statements so no dependency on other model classes.
- Finished `modules/reports/overdue.php`: sorted-by-most-overdue table with member name/phone, book title, issue/due dates, days-overdue badge, per-row estimated fine (days × DAILY_FINE RWF), total estimated footer; empty friendly message when no overdue; Print button + print-friendly styles.
- Finished `modules/reports/reports.php`: validated date-range filter (both dates required, real YYYY-MM-DD, from ≤ to), 4 sections — circulation overview (issued / returned / still out), top-5 most borrowed books, availability snapshot (total / available / borrowed / status badge), fines (collected in period vs outstanding current vs total); all with empty-friendly messages and print-friendly layout (page break before availability).

### H Muhamadi
- Finished `modules/members/Member.php`, `modules/members/list.php`, `modules/members/form.php`, and `modules/members/history.php` (member model, searchable directory UI, registration/edit form, and borrowing history view).

(Other members: add your own entry here when you finish your files.)

### Augustin Mugisha
- Finished `modules/fines/Fine.php` (fine calculation, unpaid-fine checks, creation, lookup, listing, payment, and unpaid total).

## Team workflow

1. `git clone https://github.com/K-Shalom/library-system.git`
2. Work only on the files assigned to you.
3. Before pushing: `git pull`, then `git add <your files>`, `git commit -m "Module: what you did"`, `git push`.
4. Commit from your own account. Change `Status: TODO` to `Status: DONE` in a file's header only when it is finished.
5. Report bugs in someone else's file to that person instead of editing it.

## Deliverables

Working PHP application, database script, UML class diagram, ER diagram, source code, test cases and results, technical report, screenshots, individual contribution reports, and the group presentation. Documents go in `docs/` and `tests/`.
