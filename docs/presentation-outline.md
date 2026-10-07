# Group Presentation and Demonstration Outline

## Suggested Slides

1. **Scenario and problem**: manual register limitations and intended outcomes.
2. **Requirements and users**: librarian workflows and business rules.
3. **System design**: ER diagram, class diagram, and PDO-based architecture.
4. **Database and security**: keys, relationships, prepared statements, validation, CSRF, password hashing, and safe error handling.
5. **Implemented workflows**: catalog/member management, borrowing, returns, fines, overdue tracking, and reports.
6. **Testing**: integration results, manual UI cases, known limitations, and evidence.
7. **Conclusion**: benefits, remaining deployment considerations, and questions.

## Practical Demonstration Sequence

1. Log in as a librarian.
2. Search the catalog and register or edit a book and member.
3. Issue a book and show its availability decrement.
4. Return a loan and show availability restoration.
5. Demonstrate an overdue return, the generated fine, and payment recording.
6. Open the overdue and circulation reports.
7. Show a rejected operation, such as borrowing with an unpaid fine or reducing total copies below active loans.

Use a seeded development database. Do not import `database.sql` over a database containing data that must be retained; the script drops and recreates `library_db`.
