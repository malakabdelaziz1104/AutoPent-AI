<?php
/**
 * Database Connection File
 * ========================
 * This file establishes a connection to the MySQL database using PDO
 * PDO (PHP Data Objects) is more secure than mysqli because it supports prepared statements
 */

// Include configuration file to access database constants
require_once 'config.php';

// ============================================
// PDO DATABASE CONNECTION
// ============================================

try {
    // Create DSN (Data Source Name) string
    // Format: "mysql:host=localhost;dbname=database_name;charset=utf8mb4"
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    // PDO options for better security and error handling
    $options = [
        // Set error mode to exceptions (easier to catch and handle errors)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        // Return rows as associative arrays (easier to work with)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Disable emulated prepared statements (use real prepared statements for security)
        PDO::ATTR_EMULATE_PREPARES => false,

        // Set persistent connection for better performance
        // Note: Can be set to false if you experience connection issues
        PDO::ATTR_PERSISTENT => false
    ];

    // Create PDO instance (establish connection)
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // If you reach this line, connection was successful!
    // You can uncomment the line below for testing, but remove it in production
    // echo "Database connection successful!";

} catch (PDOException $e) {
    // ============================================
    // ERROR HANDLING
    // ============================================

    // If connection fails, catch the exception and display a user-friendly message
    // IMPORTANT: Never show detailed error messages to users in production!

    // For development: Show detailed error
    die("Database Connection Failed: " . $e->getMessage());

    // For production, use this instead:
    // die("We're experiencing technical difficulties. Please try again later.");

    // Optionally, log the error to a file for debugging
    // error_log("DB Connection Error: " . $e->getMessage(), 3, "../logs/db_errors.log");
}

/**
 * USAGE EXAMPLE:
 * ==============
 * In any PHP file where you need database access, simply include this file:
 * 
 * require_once 'includes/db.php';
 * 
 * Then use $pdo to execute queries:
 * 
 * // Example: Select all users
 * $stmt = $pdo->query("SELECT * FROM users");
 * $users = $stmt->fetchAll();
 * 
 * // Example: Prepared statement (ALWAYS use for user input!)
 * $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
 * $stmt->execute(['email' => $email]);
 * $user = $stmt->fetch();
 */

// ============================================
// HELPER FUNCTION: Test Database Connection
// ============================================

/**
 * Tests if the database connection is working and tables exist
 * 
 * @return array Status information about database and tables
 */
function testDatabaseConnection()
{
    global $pdo;

    $status = [
        'connection' => false,
        'tables' => [],
        'errors' => []
    ];

    try {
        // Test connection
        $pdo->query("SELECT 1");
        $status['connection'] = true;

        // Check if tables exist
        $tables = ['users', 'scans', 'scan_results'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $status['tables'][$table] = true;
            } else {
                $status['tables'][$table] = false;
                $status['errors'][] = "Table '$table' does not exist";
            }
        }

    } catch (PDOException $e) {
        $status['errors'][] = $e->getMessage();
    }

    return $status;
}
