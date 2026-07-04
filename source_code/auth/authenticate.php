
<?php
/**
 * ============================================
 * PENTEST SCANNER - Authentication Handler
 * ============================================
 * * This script processes login form submissions:
 * 1. Validates CSRF token to prevent forgery
 * 2. Validates user input
 * 3. Checks credentials against the database
 * 4. Verifies the password hash
 * 5. Checks if the account is verified via OTP
 * 6. Creates a secure session for logged-in users
 * 7. Redirects appropriately
 */

// ============================================
// INCLUDE REQUIRED FILES
// ============================================
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Start session for login state management
startSession();

// ============================================
// CHECK REQUEST METHOD
// ============================================
if (!isPost()) {
    redirect('../login.php', 'Please use the login form.', 'error');
}

// ============================================
// CSRF TOKEN VALIDATION (NEW LAYER)
// ============================================
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    // Log the potential attack attempt
    error_log("CSRF Token validation failed during login for IP: " . $_SERVER['REMOTE_ADDR']);
    redirect('../login.php', 'Security Error: Invalid or expired session. Please refresh and try again.', 'error');
}

// ============================================
// GET AND SANITIZE FORM DATA
// ============================================
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$remember = isset($_POST['remember_me']) ? true : false; // Updated to match the form's name attribute

// ============================================
// VALIDATION
// ============================================
$errors = [];

// Validate email
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!validateEmail($email)) {
    $errors[] = 'Please enter a valid email address';
}

// Validate password
if (empty($password)) {
    $errors[] = 'Password is required';
}

// Check for validation errors
if (!empty($errors)) {
    redirect('../login.php', implode('<br>', $errors), 'error');
}

// ============================================
// AUTHENTICATE USER
// ============================================
try {
    /**
     * Fetch user by email
     * Added 'is_verified' to check OTP activation status
     */
    $stmt = $pdo->prepare("
        SELECT id, username, email, password, is_verified, created_at 
        FROM users 
        WHERE email = :email 
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);

    // Fetch the user record
    $user = $stmt->fetch();

    // ============================================
    // VERIFY CREDENTIALS
    // ============================================

    if (!$user) {
        /**
         * User not found
         * Use generic message to prevent user enumeration
         */
        redirect('../login.php', 'Invalid email or password. Please try again.', 'error');
    }

    /**
     * Verify password using password_verify()
     */
    if (!password_verify($password, $user['password'])) {
        /**
         * Wrong password
         * Use same generic message as "user not found"
         */
        redirect('../login.php', 'Invalid email or password. Please try again.', 'error');
    }

    // ============================================
    // CHECK ACCOUNT VERIFICATION (NEW LAYER)
    // ============================================
    
    // If the operator hasn't entered their OTP yet
    if ($user['is_verified'] == 0) {
        // Store email in session so verify.php knows who to activate
        $_SESSION['verify_email'] = $email;
        error_log("Unverified login attempt: ID={$user['id']}, IP=" . $_SERVER['REMOTE_ADDR']);
        redirect('../verify.php', 'Access Denied: Please verify your account with the OTP sent to your email before logging in.', 'error');
    }

    // ============================================
    // LOGIN SUCCESSFUL - CREATE SESSION
    // ============================================

    /**
     * Regenerate session ID to prevent session fixation attacks
     * This creates a new session ID while preserving session data
     */
    session_regenerate_id(true);

    /**
     * Store user data in session
     */
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['logged_in'] = true; // Added to match verify.php logic
    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_activity'] = time();

    /**
     * Handle "Remember Me" functionality
     */
    if ($remember) {
        // Set cookie to last 30 days
        $cookie_lifetime = 60 * 60 * 24 * 30; // 30 days in seconds

        // Update session cookie parameters
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            time() + $cookie_lifetime,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // ============================================
    // CHECK FOR REDIRECT AFTER LOGIN
    // ============================================
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect_to = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        redirect($redirect_to);
    }

    // ============================================
    // REDIRECT TO DASHBOARD
    // ============================================
    // Log successful login for auditing (Crucial for platforms like AutoPentAI)
    error_log("Operator logged in successfully: ID={$user['id']}, Username={$user['username']}, IP=" . $_SERVER['REMOTE_ADDR']);

    // Redirect to dashboard with success message
    redirect('../dashboard.php', 'Authentication successful. Welcome to the command center.', 'success');

} catch (PDOException $e) {
    // ============================================
    // DATABASE ERROR
    // ============================================
    error_log("Login Error: " . $e->getMessage());
    redirect('../login.php', 'A database error occurred. Please try again later.', 'error');
}
?>

```