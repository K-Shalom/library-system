<?php
/**
 * File: Database.php
 * Module: Core
 * Assigned to: Shalom K
 * Status: DONE
 * Description: PDO Singleton connection class
 */

// Load custom exception classes so we can wrap PDO errors safely
require_once __DIR__ . '/exceptions.php';

// ------------------------------------------------------------
// Configuration constants (guarded so they are only defined once)
// ------------------------------------------------------------
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    'library_db');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Business logic constants shared across the application
if (!defined('LOAN_DAYS'))  define('LOAN_DAYS',  14);     // Default loan period in days
if (!defined('DAILY_FINE')) define('DAILY_FINE', 100);    // Daily overdue fine in RWF
if (!defined('MAX_LOANS'))  define('MAX_LOANS',  3);      // Maximum active loans per member

// Path to the error log file (inside logs/ folder)
if (!defined('LOG_FILE'))   define('LOG_FILE',   __DIR__ . '/../logs/error.log');

// Set default timezone for all date functions
date_default_timezone_set('Africa/Kigali');

class Database
{
    // Singleton instance storage (only one Database object ever exists)
    private static $instance = null;

    // The live PDO connection
    private $pdo = null;

    // ------------------------------------------------------------
    // Singleton access methods
    // ------------------------------------------------------------

    // Prevent direct instantiation with "new Database()" from outside
    private function __construct()
    {
        try {
            // Build a DSN string that PDO understands. charset=utf8mb4 ensures full Unicode support.
            // When DB_HOST is "localhost", PHP tries to use a Unix socket. XAMPP stores its socket in a
            // non-standard path (/opt/lampp/var/mysql/mysql.sock on Linux), so auto-detect common sockets
            // and attach the path explicitly. Fall back to 127.0.0.1 (TCP) if no socket is found.
            $dsnHost = DB_HOST;
            $socketPart = '';
            if (DB_HOST === 'localhost') {
                $candidateSockets = [
                    '/opt/lampp/var/mysql/mysql.sock',   // XAMPP on Linux
                    '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock', // XAMPP on macOS
                    ini_get('pdo_mysql.default_socket'),
                    ini_get('mysqli.default_socket'),
                ];
                foreach ($candidateSockets as $sock) {
                    if ($sock && file_exists($sock)) {
                        $socketPart = ';unix_socket=' . $sock;
                        break;
                    }
                }
                if ($socketPart === '') {
                    // No usable unix socket found -> force TCP via 127.0.0.1.
                    $dsnHost = '127.0.0.1';
                }
            }
            $dsn = 'mysql:host=' . $dsnHost . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET . $socketPart;

            // PDO options tuned for safe, predictable behaviour:
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // Return rows as associative arrays
                PDO::ATTR_EMULATE_PREPARES   => false,                     // Use real database prepared statements
            ];

            // Open the connection using the root credentials defined above
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // NEVER leak the real error message to the end user (it may contain hostnames, paths, credentials).
            // Instead log the real message for the admin and throw a generic safe message.
            Database::logError('Database connection failed: ' . $e->getMessage());
            throw new DatabaseException('The system is temporarily unavailable. Please try again later.');
        }
    }

    // Prevent cloning of the singleton instance
    private function __clone()
    {
    }

    // Return the single shared instance, creating it on first call.
    // Return type-hint "Database" ensures callers get the right type.
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------

    // Return the raw PDO connection so models can run queries.
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    // Append a timestamped error line to LOG_FILE. Silently fails if the log cannot be written.
    public static function logError(string $message): void
    {
        // Suppress errors with @ so a broken log file never breaks the whole app.
        $logDir = dirname(LOG_FILE);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents(LOG_FILE, $line, FILE_APPEND);
    }
}
