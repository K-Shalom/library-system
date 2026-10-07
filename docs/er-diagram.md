# Entity-Relationship Diagram

```mermaid
erDiagram
    AUTHORS ||--o{ BOOK_AUTHORS : contributes_to
    BOOKS ||--o{ BOOK_AUTHORS : has_authors
    CATEGORIES ||--o{ BOOKS : classifies
    PUBLISHERS ||--o{ BOOKS : publishes
    BOOKS ||--o{ LOANS : appears_in
    MEMBERS ||--o{ LOANS : borrows
    LIBRARIANS ||--o{ LOANS : processes
    LOANS ||--o| FINES : may_generate

    AUTHORS {
        int author_id PK
        varchar name
    }
    BOOK_AUTHORS {
        int book_id PK,FK
        int author_id PK,FK
    }
    PUBLISHERS {
        int publisher_id PK
        varchar name UK
    }
    CATEGORIES {
        int category_id PK
        varchar name UK
    }
    BOOKS {
        int book_id PK
        varchar title
        varchar isbn UK
        int category_id FK
        int publisher_id FK
        smallint published_year
        int total_copies
        int available_copies
    }
    MEMBERS {
        int member_id PK
        varchar full_name
        varchar email UK
        varchar phone
        enum status
        datetime registered_at
    }
    LIBRARIANS {
        int librarian_id PK
        varchar full_name
        varchar username UK
        varchar password_hash
    }
    LOANS {
        int loan_id PK
        int book_id FK
        int member_id FK
        int librarian_id FK
        date issue_date
        date due_date
        date return_date
        enum status
    }
    FINES {
        int fine_id PK
        int loan_id FK,UK
        decimal amount
        boolean paid
        datetime created_at
        datetime paid_at
    }
```

The `book_authors` composite primary key prevents a book-author relationship from being duplicated. Each fine is associated with at most one loan; `fines.loan_id` is unique.
