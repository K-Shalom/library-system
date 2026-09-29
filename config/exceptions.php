<?php
/**
 * File: exceptions.php
 * Module: Core
 * Assigned to: Shalom K
 * Status: DONE
 * Description: Custom business and database exception classes
 */

// Use when user input fails validation (e.g. empty required fields, invalid email format)
class ValidationException extends Exception
{
    private $errors = [];

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, array $errors = [])
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

// Use when a business rule is violated (e.g. no copies available, member has unpaid fines, max loans reached)
class BusinessRuleException extends Exception
{

}

// Use when a requested record does not exist in the database (e.g. unknown book_id, unknown member_id)
class NotFoundException extends Exception
{

}

// Use to wrap PDO/database failures so a user-safe message is shown instead of raw DB errors
class DatabaseException extends Exception
{

}
