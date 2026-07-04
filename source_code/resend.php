<?php
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/PHPMailer/Exception.php';
require_once 'includes/PHPMailer/PHPMailer.php';
require_once 'includes/PHPMailer/SMTP.php';

startSession();

if (!isset($_SESSION['verify_email']) || !isPost()) {
    redirect('signup.php', 'Invalid request.', 'error');
}

$email = $_SESSION['verify_email'];

try {
    
    $stmt = $pdo->prepare("SELECT id, username, otp_created_at FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
 
        $time_elapsed = time() - strtotime($user['otp_created_at']);
        if ($time_elapsed < 60) {
            $wait = 60 - $time_elapsed;
            redirect('verify.php', "Please wait $wait seconds before requesting a new code.", 'error');
        }

        $new_otp = sprintf("%06d", mt_rand(1, 999999));
        $hashed_new_otp = password_hash($new_otp, PASSWORD_DEFAULT); 
        $current_time = date('Y-m-d H:i:s');

        $update_stmt = $pdo->prepare("UPDATE users SET otp_code = :otp, otp_created_at = :time WHERE id = :id");
        $update_stmt->execute(['otp' => $hashed_new_otp, 'time' => $current_time, 'id' => $user['id']]);

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'autopentai.support@gmail.com'; 
        $mail->Password   = 'eworgemerocpqzth'; // حط الباسورد هنا
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('autopentai.support@gmail.com', 'AutoPentAI Support');
        $mail->addAddress($email, $user['username']);
        $mail->isHTML(true);
        $mail->Subject = 'New Verification Code - AutoPentAI';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; background-color: #0f0f1a; color: #ffffff; padding: 30px; text-align: center; border-radius: 10px; border: 1px solid #00ffff;'>
                <h2 style='color: #00ffff; margin-bottom: 20px;'>New Code Requested</h2>
                <div style='background-color: rgba(0,255,255,0.1); padding: 15px; border-radius: 8px; display: inline-block; margin: 20px 0;'>
                    <h1 style='color: #ff00ff; letter-spacing: 8px; margin: 0; font-size: 32px;'>$new_otp</h1>
                </div>
                <p style='font-size: 13px; color: #ff4477; margin-top: 15px;'><strong>Note:</strong> This code will expire in 10 minutes.</p>
            </div>
        ";
        $mail->send();

        redirect('verify.php', 'A new code has been sent to your email.', 'success');
    }
} catch (Exception $e) {
    redirect('verify.php', 'Failed to send new code. Please try again.', 'error');
}
?>