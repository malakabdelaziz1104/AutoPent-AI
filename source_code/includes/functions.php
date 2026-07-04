<?php
/**
 * Helper Functions File
 * =====================
 * This file contains reusable utility functions used throughout the application
 * Include this file in any PHP page where you need these functions
 */

// Prevent direct access to this file
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Sanitize user input to prevent XSS attacks
 * 
 * @param string $data The input data to sanitize
 * @return string Sanitized data safe for output
 */
function sanitize($data)
{
    // Remove whitespace from beginning and end
    $data = trim($data);

    // Remove backslashes
    $data = stripslashes($data);

    // Convert special characters to HTML entities (prevents XSS attacks)
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    return $data;
}

/**
 * Validate email address format
 * 
 * @param string $email Email address to validate
 * @return bool True if valid email, false otherwise
 */
function validateEmail($email)
{
    // Use PHP's built-in filter for email validation
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL format
 * 
 * @param string $url URL to validate
 * @return bool True if valid URL, false otherwise
 */
function validateURL($url)
{
    // Use PHP's built-in filter for URL validation
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate password strength
 * 
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePassword($password)
{
    $result = ['valid' => true, 'message' => 'Password meets requirements'];

    // Check minimum length
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $result['valid'] = false;
        $result['message'] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long";
        return $result;
    }

    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $result['valid'] = false;
        $result['message'] = "Password must contain at least one uppercase letter";
        return $result;
    }

    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $result['valid'] = false;
        $result['message'] = "Password must contain at least one lowercase letter";
        return $result;
    }

    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $result['valid'] = false;
        $result['message'] = "Password must contain at least one number";
        return $result;
    }

    return $result;
}

// ============================================
// SESSION FUNCTIONS
// ============================================

/**
 * Start a secure session
 * Call this at the beginning of pages that need session support
 */
function startSession()
{
    // Check if session is not already started
    if (session_status() === PHP_SESSION_NONE) {
        // Start the session
        session_start();

        // Regenerate session ID to prevent session fixation attacks
        // Only regenerate if this is a new session
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['last_activity'] = time();
        }

        // Check for session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            // Session has expired
            session_unset();
            session_destroy();
            return false;
        }

        // Update last activity time
        $_SESSION['last_activity'] = time();
    }

    return true;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in (redirect to login if not)
 * 
 * @param string $redirect_to Optional page to redirect to after login
 */
function requireLogin($redirect_to = '')
{
    if (!isLoggedIn()) {
        // Save the page they were trying to access
        if (!empty($redirect_to)) {
            $_SESSION['redirect_after_login'] = $redirect_to;
        }

        // Redirect to login page
        redirect('login.php');
    }
}

/**
 * Log out the current user
 */
function logout()
{
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Unset all session variables
    $_SESSION = [];

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy the session
    session_destroy();
}

// ============================================
// REDIRECT FUNCTIONS
// ============================================

/**
 * Redirect to another page
 * 
 * @param string $page Page to redirect to
 * @param string $message Optional message to display
 * @param string $type Optional message type (success, error, info)
 */
function redirect($page, $message = '', $type = 'info')
{
    // Start session to store message
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Store message in session if provided
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }

    // Perform redirect
    header("Location: " . $page);
    exit();
}

/**
 * Get and clear flash message from session
 * 
 * @return array ['message' => string, 'type' => string] or null
 */
function getFlashMessage()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];

        // Clear the message
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        return $flash;
    }

    return null;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Format timestamp to readable date
 * 
 * @param string $timestamp Database timestamp
 * @param string $format Date format (default: 'F j, Y g:i A')
 * @return string Formatted date
 */
function formatDate($timestamp, $format = 'F j, Y g:i A')
{
    return date($format, strtotime($timestamp));
}

/**
 * Generate a random token (useful for CSRF protection, password reset, etc.)
 * 
 * @param int $length Token length (default: 32)
 * @return string Random token
 */
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Display error message in HTML
 * 
 * @param string $message Error message
 * @return string HTML formatted error
 */
function displayError($message)
{
    return '<div class="alert alert-error">' . sanitize($message) . '</div>';
}

/**
 * Display success message in HTML
 * 
 * @param string $message Success message
 * @return string HTML formatted success message
 */
function displaySuccess($message)
{
    return '<div class="alert alert-success">' . sanitize($message) . '</div>';
}

/**
 * Get user's IP address
 * 
 * @return string IP address
 */
function getUserIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Check if request is POST
 * 
 * @return bool True if POST request
 */
function isPost()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 * 
 * @return bool True if GET request
 */
function isGet()
{
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}
