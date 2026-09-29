-- File: database.sql
-- Module: Core
-- Assigned to: Shalom K
-- Status: DONE
-- Description: MySQL schema import script

-- Create and use database
DROP DATABASE IF EXISTS library_db;
CREATE DATABASE library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

-- Authors table (standalone, no FK dependencies)
CREATE TABLE authors (
    author_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publishers table (standalone, no FK dependencies)
CREATE TABLE publishers (
    publisher_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table (standalone, no FK dependencies)
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Books table (depends on categories, publishers)
CREATE TABLE books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) NOT NULL UNIQUE,
    category_id INT NOT NULL,
    publisher_id INT NOT NULL,
    published_year SMALLINT NULL,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    INDEX idx_title (title),
    CONSTRAINT fk_books_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_books_publisher
        FOREIGN KEY (publisher_id) REFERENCES publishers(publisher_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_total_copies CHECK (total_copies >= 0),
    CONSTRAINT chk_available_copies CHECK (available_copies >= 0),
    CONSTRAINT chk_available_lte_total CHECK (available_copies <= total_copies)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Book authors many-to-many link (depends on books, authors)
CREATE TABLE book_authors (
    book_id INT NOT NULL,
    author_id INT NOT NULL,
    PRIMARY KEY (book_id, author_id),
    CONSTRAINT fk_ba_book
        FOREIGN KEY (book_id) REFERENCES books(book_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ba_author
        FOREIGN KEY (author_id) REFERENCES authors(author_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Members table (standalone)
CREATE TABLE members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Librarians table (standalone)
CREATE TABLE librarians (
    librarian_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loans table (depends on books, members, librarians)
CREATE TABLE loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    member_id INT NOT NULL,
    librarian_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('borrowed', 'returned', 'overdue') NOT NULL DEFAULT 'borrowed',
    INDEX idx_loans_member (member_id),
    INDEX idx_loans_book (book_id),
    INDEX idx_loans_status (status),
    INDEX idx_loans_due (due_date),
    CONSTRAINT fk_loans_book
        FOREIGN KEY (book_id) REFERENCES books(book_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_loans_member
        FOREIGN KEY (member_id) REFERENCES members(member_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_loans_librarian
        FOREIGN KEY (librarian_id) REFERENCES librarians(librarian_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_due_ge_issue CHECK (due_date >= issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fines table (depends on loans)
CREATE TABLE fines (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    paid TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    CONSTRAINT fk_fines_loan
        FOREIGN KEY (loan_id) REFERENCES loans(loan_id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_amount_positive CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- 1 Librarian (password = admin123, bcrypt hash generated via PHP password_hash())
INSERT INTO librarians (full_name, username, password_hash) VALUES
('Library Admin', 'admin', '$2y$12$qwISPD.nJ4/WhADM/9xadueuW0KCsA8qPhYBm4o7pP3NzkVSlpXSe');

-- 5 Authors
INSERT INTO authors (name) VALUES
('J.K. Rowling'),
('George Orwell'),
('Jane Austen'),
('Charles Dickens'),
('Harper Lee');

-- 3 Publishers
INSERT INTO publishers (name) VALUES
('Penguin Books'),
('Oxford University Press'),
('Bloomsbury Publishing');

-- 4 Categories
INSERT INTO categories (name) VALUES
('Fiction'),
('Science Fiction'),
('Classic Literature'),
('Mystery');

-- 10 Books
-- Note: book 1 (Harry Potter) has 3 copies, book 2 (1984) has 2 copies.
-- We will check out 2 copies total, so available_copies must be reduced accordingly.
INSERT INTO books (title, isbn, category_id, publisher_id, published_year, total_copies, available_copies) VALUES
('Harry Potter and the Philosophers Stone', '9780747532699', 1, 3, 1997, 3, 2),
('1984', '9780451524935', 2, 1, 1949, 2, 1),
('Pride and Prejudice', '9780141439518', 3, 1, 1813, 2, 2),
('To Kill a Mockingbird', '9780061120084', 3, 2, 1960, 2, 2),
('Oliver Twist', '9780141439747', 3, 1, 1838, 2, 2),
('Harry Potter and the Chamber of Secrets', '9780747538486', 1, 3, 1998, 2, 2),
('Animal Farm', '9780451526342', 2, 1, 1945, 2, 2),
('Sense and Sensibility', '9780141439662', 3, 1, 1811, 1, 1),
('Great Expectations', '9780141439563', 3, 1, 1861, 2, 2),
('Harry Potter and the Prisoner of Azkaban', '9780747542156', 1, 3, 1999, 2, 2);

-- Link books to authors (book_authors)
INSERT INTO book_authors (book_id, author_id) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 5),
(5, 4),
(6, 1),
(7, 2),
(8, 3),
(9, 4),
(10, 1);

-- 5 Members (1 inactive)
INSERT INTO members (full_name, email, phone, status, registered_at) VALUES
('Alice Kamikazi', 'alice.kamikazi@example.rw', '+250780000001', 'active', DATE_SUB(CURDATE(), INTERVAL 60 DAY)),
('Bob Niyonteze', 'bob.niyonteze@example.rw', '+250780000002', 'active', DATE_SUB(CURDATE(), INTERVAL 45 DAY)),
('Carol Uwase', 'carol.uwase@example.rw', '+250780000003', 'inactive', DATE_SUB(CURDATE(), INTERVAL 120 DAY)),
('David Musoni', 'david.musoni@example.rw', '+250780000004', 'active', DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
('Eve Ingabire', 'eve.ingabire@example.rw', '+250780000005', 'active', DATE_SUB(CURDATE(), INTERVAL 15 DAY));

-- 3 Sample loans:
--   Loan 1: Active (borrowed) loan - 14 days loan period
--   Loan 2: Overdue loan (issued 30 days ago, due 16 days ago, still not returned)
--   Loan 3: Returned-late loan (returned 5 days after due, fine created unpaid)

INSERT INTO loans (book_id, member_id, librarian_id, issue_date, due_date, return_date, status) VALUES
-- Loan 1: Active borrowed (book 2: 1984 - reduces available from 2 to 1 above)
(2, 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), NULL, 'borrowed'),

-- Loan 2: Overdue (book 1: Harry Potter 1 - reduces available from 3 to 2 above, due date in past)
(1, 2, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 16 DAY), NULL, 'overdue'),

-- Loan 3: Returned late (book 3: Pride and Prejudice - returned 5 days after due, so available was already restored to 2 when returned)
(3, 4, 1, DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_SUB(CURDATE(), INTERVAL 11 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'returned');

-- Fine for loan 3 (returned 5 days late, DAILY_FINE = 100 RWF => 5 * 100 = 500)
INSERT INTO fines (loan_id, amount, paid, created_at, paid_at) VALUES
(3, 500.00, 0, DATE_SUB(CURDATE(), INTERVAL 6 DAY), NULL);
