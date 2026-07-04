<?php
require_once 'includes/functions.php';
require_once 'includes/db.php';

startSession();

if (!isset($_SESSION['verify_email'])) {
    redirect('signup.php', 'Please sign up first.', 'error');
}

$email = $_SESSION['verify_email'];
$error = '';
$success_raw = getFlashMessage('success');
$success = is_array($success_raw) ? (isset($success_raw['message']) ? $success_raw['message'] : reset($success_raw)) : $success_raw;
$time_left_to_resend = 0;

try {
   
    $stmt = $pdo->prepare("SELECT id, username, otp_code, otp_created_at FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && $user['otp_created_at']) {
        $elapsed = time() - strtotime($user['otp_created_at']);
        $time_left_to_resend = max(0, 60 - $elapsed); 
    }

    
    if (isPost() && isset($_POST['otp_code'])) {
        $entered_otp = trim($_POST['otp_code']);

        if (empty($entered_otp)) {
            $error = 'Please enter the verification code.';
        } elseif ($user && password_verify($entered_otp, $user['otp_code'])) {
            
            $elapsed_since_creation = time() - strtotime($user['otp_created_at']);
            
            if ($elapsed_since_creation > 600) {
                $error = 'Code expired! Please click "Resend Code" to get a new one.';
            } else {

                $update_stmt = $pdo->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_created_at = NULL WHERE id = :id");
                $update_stmt->execute(['id' => $user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['logged_in'] = true;
                unset($_SESSION['verify_email']);

                redirect('dashboard.php', 'Account verified successfully! Welcome to AutoPentAI.', 'success');
            }
        } else {
            $error = 'Invalid verification code. Please try again.';
        }
    }
} catch (PDOException $e) {
    $error = 'A database error occurred.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account - AutoPentAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --neon-cyan: #00ffff;
            --neon-purple: #9d00ff;
            --bg-glass: rgba(15, 15, 26, 0.85);
            --text-white: #ffffff;
            --text-dim: #8892b0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #000000 0%, #0a0514 50%, #000a14 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-white);
            margin: 0;
        }
        .auth-card {
            background: var(--bg-glass);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 28px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 0 60px rgba(157, 0, 255, 0.08);
        }
        .icon { font-size: 3rem; color: var(--neon-cyan); margin-bottom: 20px; filter: drop-shadow(0 0 15px rgba(0, 255, 255, 0.5)); }
        h1 { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; margin-bottom: 10px; }
        p { color: var(--text-dim); font-size: 0.9rem; margin-bottom: 20px; line-height: 1.5; }
        
        .form-group { margin-bottom: 20px; text-align: center; }
        input {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 15px 0; 
            color: white;
            font-size: 1.8rem; 
            text-align: center;
            letter-spacing: 15px; 
            text-indent: 15px; 
            font-family: 'Orbitron', sans-serif;
            margin: 0 auto;
        }
        input::placeholder {
            color: rgba(255, 255, 255, 0.2);
            font-size: 1.2rem;
            letter-spacing: 10px;
            text-indent: 10px;
        }
        input:focus { outline: none; border-color: var(--neon-cyan); box-shadow: 0 0 15px rgba(0, 255, 255, 0.2); }
        
        .btn {
            width: 100%; padding: 15px; background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple));
            border: none; border-radius: 14px; color: #000; font-family: 'Orbitron', sans-serif; font-weight: bold;
            font-size: 1rem; cursor: pointer; text-transform: uppercase; margin-bottom: 15px;
        }
        
        .resend-form { margin-top: 15px; }
        .btn-resend {
            background: none; border: none; color: var(--neon-cyan); font-family: 'Inter', sans-serif;
            font-size: 0.85rem; cursor: pointer; text-decoration: underline; transition: 0.3s;
        }
        .btn-resend:disabled { color: #555; cursor: not-allowed; text-decoration: none; }
        .btn-resend:not(:disabled):hover { color: var(--neon-purple); }

        .alert-error { color: #ff0055; font-size: 0.85rem; margin-bottom: 15px; }
        .alert-success { color: #00ff88; font-size: 0.85rem; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="auth-card">
    <i class="fas fa-envelope-open-text icon"></i>
    <h1>Verify Identity</h1>
    <p>We've sent a 6-digit transmission to<br><strong style="color: #fff;"><?php echo htmlspecialchars($email); ?></strong></p>
    
    <p style="color: #ffaa00; font-size: 0.8rem; margin-top: -15px; margin-bottom: 25px;">
        <i class="fas fa-exclamation-circle"></i> Please check your Spam or Junk folder if you don't see it.
    </p>

    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>

    <form method="POST" action="verify.php">
        <div class="form-group">
            <input type="text" name="otp_code" placeholder="000000" maxlength="6" required autocomplete="off">
        </div>
        <button type="submit" class="btn">Authenticate</button>
    </form>

    <form method="POST" action="resend.php" class="resend-form">
        <button type="submit" id="resendBtn" class="btn-resend" disabled>
            Resend Code <span id="timerText">(Wait 60s)</span>
        </button>
    </form>
</div>

<script>
    
    let timeLeft = <?php echo $time_left_to_resend; ?>;
    const resendBtn = document.getElementById('resendBtn');
    const timerText = document.getElementById('timerText');

    if (timeLeft > 0) {
        const timerInterval = setInterval(() => {
            timeLeft--;
            timerText.textContent = `(Wait ${timeLeft}s)`;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                resendBtn.disabled = false;
                timerText.textContent = '';
            }
        }, 1000);
    } else {
        resendBtn.disabled = false;
        timerText.textContent = '';
    }
</script>

</body>
</html>