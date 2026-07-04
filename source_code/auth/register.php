<?php
/**
 * ============================================
 * PENTEST SCANNER - Registration Handler 
 * ============================================
 */

require_once '../includes/functions.php';
require_once '../includes/db.php';

startSession();

if (!isPost()) {
    redirect('../signup.php', 'Please use the signup form.', 'error');
}
// ============================================
// CSRF TOKEN VALIDATION
// ============================================
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {

    error_log("CSRF Token validation failed for IP: " . $_SERVER['REMOTE_ADDR']);
    redirect('../signup.php', 'Security Error: Invalid or expired session. Please refresh and try again.', 'error');
}
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
$terms = isset($_POST['terms']) ? true : false;

$errors = [];

// ---------- Validate Username ----------
if (empty($username)) {
    $errors[] = 'Username is required';
} elseif (strlen($username) < 3 || strlen($username) > 50) {
    $errors[] = 'Username must be between 3 and 50 characters';
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = 'Username can only contain letters, numbers, and underscores';
}

// ---------- Validate Email ----------
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!validateEmail($email)) {
    $errors[] = 'Please enter a valid email address';
} elseif (strlen($email) > 100) {
    $errors[] = 'Email cannot exceed 100 characters';
}

// ---------- Validate Password ----------
if (empty($password)) {
    $errors[] = 'Password is required';
} else {
    $passwordCheck = validatePassword($password);
    if (!$passwordCheck['valid']) {
        $errors[] = $passwordCheck['message'];
    }
}

// ---------- Validate Confirm Password ----------
if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

// ---------- Validate Terms ----------
if (!$terms) {
    $errors[] = 'You must accept the Terms of Service and Privacy Policy';
}

if (!empty($errors)) {
    $errorMessage = implode('<br>', $errors);
    redirect('../signup.php', $errorMessage, 'error');
}

// ============================================
// CHECK IF USERNAME OR EMAIL ALREADY EXISTS
// ============================================
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    if ($stmt->rowCount() > 0) {
        redirect('../signup.php', 'This username is already taken. Please choose another.', 'error');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    if ($stmt->rowCount() > 0) {
        redirect('../signup.php', 'This email is already registered. Please use a different email or login.', 'error');
    }
} catch (PDOException $e) {
    error_log("Registration Check Error: " . $e->getMessage());
    redirect('../signup.php', 'A database error occurred. Please try again later.', 'error');
}

// ============================================
// HASH PASSWORD, GENERATE OTP & TIME
// ============================================
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$otp_code = sprintf("%06d", mt_rand(1, 999999));
$hashed_otp = password_hash($otp_code, PASSWORD_DEFAULT); 
$current_time = date('Y-m-d H:i:s');

// ============================================
// INSERT NEW USER INTO DATABASE
// ============================================
try {
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, otp_code, otp_created_at, created_at) 
        VALUES (:username, :email, :password, :otp_code, :otp_created_at, NOW())
    ");

    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => $hashed_password,
        'otp_code' => $hashed_otp,
        'otp_created_at' => $current_time
    ]);

    if ($stmt->rowCount() > 0) {
        $new_user_id = $pdo->lastInsertId();

        // ============================================
        // SEND OTP VIA EMAIL (PHPMailer)
        // ============================================
        require_once '../includes/PHPMailer/Exception.php';
        require_once '../includes/PHPMailer/PHPMailer.php';
        require_once '../includes/PHPMailer/SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'autopentai.support@gmail.com'; 
            $mail->Password   = 'eworgemerocpqzth'; // حط الـ App Password هنا
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('autopentai.support@gmail.com', 'AutoPentAI Support');
            $mail->addAddress($email, $username);

            $mail->isHTML(true);
            $mail->Subject = 'Account Verification Code - AutoPentAI';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; background-color: #0f0f1a; color: #ffffff; padding: 30px; text-align: center; border-radius: 10px; border: 1px solid #00ffff;'>
                    <h2 style='color: #00ffff; margin-bottom: 20px;'>Welcome to AutoPentAI, $username!</h2>
                    <p style='font-size: 16px;'>Your secure verification code is:</p>
                    <div style='background-color: rgba(0,255,255,0.1); padding: 15px; border-radius: 8px; display: inline-block; margin: 20px 0;'>
                        <h1 style='color: #ff00ff; letter-spacing: 8px; margin: 0; font-size: 32px;'>$otp_code</h1>
                    </div>
                    <p style='font-size: 14px; color: #8892b0;'>Please enter this code to activate your operator account.</p>
                    <p style='font-size: 13px; color: #ff4477; margin-top: 15px;'><strong>Note:</strong> This code will expire in 10 minutes.</p>
                </div>
            ";

            $mail->send();

            $_SESSION['verify_email'] = $email;
            redirect('../verify.php', 'Identity created! Please check your email for the verification code.', 'success');

        } catch (Exception $e) {
            error_log("Mail Error: {$mail->ErrorInfo}");
            redirect('../login.php', 'Account created, but failed to send verification email. Please contact support.', 'error');
        }

    } else {
        redirect('../signup.php', 'Registration failed. Please try again.', 'error');
    }

} catch (PDOException $e) {
    error_log("Registration Insert Error: " . $e->getMessage());

    if ($e->getCode() == 23000) {
        redirect('../signup.php', 'This username or email is already registered.', 'error');
    }

    redirect('../signup.php', 'An error occurred during registration. Please try again.', 'error');
}
?>