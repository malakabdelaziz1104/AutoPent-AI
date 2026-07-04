<?php
require_once 'includes/functions.php';
startSession();

// Generate CSRF Token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Retrieve flash messages for errors and success
$error_raw = getFlashMessage('error');
$error = is_array($error_raw) ? (isset($error_raw['message']) ? $error_raw['message'] : reset($error_raw)) : $error_raw;

$success_raw = getFlashMessage('success');
$success = is_array($success_raw) ? (isset($success_raw['message']) ? $success_raw['message'] : reset($success_raw)) : $success_raw;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PenTest Scanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --neon-cyan: #00ffff;
            --neon-pink: #ff00ff;
            --neon-purple: #9d00ff;
            --neon-green: #00ff88;
            --bg-void: #000000;
            --bg-deep: #0a0a0f;
            --bg-card: #0f0f1a;
            --bg-glass: rgba(15, 15, 26, 0.85);
            --text-white: #ffffff;
            --text-glow: #e0e7ff;
            --text-dim: #8892b0;
            --border-neon: rgba(0, 255, 255, 0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #000000 0%, #0a0514 50%, #000a14 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            color: var(--text-white);
        }

        /* Animated Grid */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-image:
                linear-gradient(rgba(0, 255, 255, 0.025) 2px, transparent 2px),
                linear-gradient(90deg, rgba(0, 255, 255, 0.025) 2px, transparent 2px);
            background-size: 60px 60px;
            animation: gridMove 30s linear infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
        }

        /* Orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.12;
            pointer-events: none;
            z-index: 0;
            animation: float 25s ease-in-out infinite;
        }
        .orb-1 { width: 500px; height: 500px; background: var(--neon-cyan); top: -150px; left: -150px; animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; background: var(--neon-purple); bottom: -100px; right: -100px; animation-delay: 10s; }

        @keyframes float {
            0%, 100% { transform: translate(0,0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.08); }
        }

        /* Card wrapper */
        .auth-wrapper {
            width: 100%;
            max-width: 460px;
            padding: 20px;
            position: relative;
            z-index: 1;
            animation: cardEntry 1s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
        }

        @keyframes cardEntry {
            from { opacity: 0; transform: translateY(40px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .auth-card {
            background: var(--bg-glass);
            backdrop-filter: blur(30px) saturate(180%);
            border: 1px solid var(--border-neon);
            border-radius: 28px;
            padding: 48px 44px;
            box-shadow:
                0 0 60px rgba(0, 255, 255, 0.08),
                0 30px 60px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }

        /* Top accent line */
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--neon-cyan), var(--neon-purple), transparent);
        }

        /* Corner glow */
        .auth-card::after {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 200px; height: 200px;
            background: var(--neon-cyan);
            filter: blur(80px);
            opacity: 0.06;
            border-radius: 50%;
        }

        /* Logo */
        .auth-logo {
            text-align: center;
            margin-bottom: 36px;
        }

        .auth-logo .shield-icon {
            font-size: 3rem;
            color: var(--neon-cyan);
            filter: drop-shadow(0 0 20px rgba(0, 255, 255, 0.7));
            display: block;
            margin-bottom: 14px;
            animation: iconPulse 3s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { filter: drop-shadow(0 0 15px rgba(0, 255, 255, 0.5)); }
            50%       { filter: drop-shadow(0 0 35px rgba(0, 255, 255, 0.9)); }
        }

        .auth-logo h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: linear-gradient(90deg, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-logo p {
            color: var(--text-dim);
            font-size: 0.9rem;
            margin-top: 6px;
        }

        /* Form label */
        .form-label {
            color: var(--text-dim);
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            display: block;
        }

        .form-group { margin-bottom: 22px; }

        /* Input */
        .input-wrapper { position: relative; }

        .input-wrapper input {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 14px 16px 14px 48px;
            color: var(--text-white);
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .input-wrapper input::placeholder { color: rgba(136, 146, 176, 0.5); }

        .input-wrapper input:focus {
            outline: none;
            border-color: var(--neon-cyan);
            background: rgba(0, 255, 255, 0.04);
            box-shadow: 0 0 0 3px rgba(0, 255, 255, 0.12), 0 0 20px rgba(0, 255, 255, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1rem;
            transition: color 0.3s;
        }

        .input-wrapper:focus-within .input-icon { color: var(--neon-cyan); }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.3s;
            padding: 4px;
        }

        .password-toggle:hover { color: var(--neon-cyan); }

        /* Remember me row */
        .remember-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
            color: var(--text-dim);
            font-size: 0.875rem;
        }

        .remember-row input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--neon-cyan);
            cursor: pointer;
        }

        .remember-row label { cursor: pointer; }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple));
            color: #000;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.35);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transition: 0.6s;
        }

        .btn-submit:hover::before { left: 100%; }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 35px rgba(0, 255, 255, 0.6);
        }

        .btn-submit:active { transform: translateY(0); }

        /* Footer */
        .auth-footer {
            text-align: center;
            margin-top: 28px;
            color: var(--text-dim);
            font-size: 0.875rem;
        }

        .auth-footer a {
            color: var(--neon-cyan);
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .auth-footer a:hover {
            text-shadow: 0 0 12px rgba(0, 255, 255, 0.7);
        }

        /* Alert Messages */
        .alert {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            font-size: 0.875rem;
        }

        .alert-error { background: rgba(255, 0, 85, 0.1); border: 1px solid rgba(255, 0, 85, 0.4); color: #ff4477; }
        .alert-success { background: rgba(0, 255, 136, 0.1); border: 1px solid rgba(0, 255, 136, 0.4); color: var(--neon-green); }

        /* Brand link top */
        .brand-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dim);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 24px;
            transition: color 0.3s;
        }

        .brand-link:hover { color: var(--neon-cyan); }

        .brand-link i {
            color: var(--neon-cyan);
            filter: drop-shadow(0 0 8px rgba(0,255,255,0.6));
        }
    </style>
</head>
<body>

<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="auth-wrapper">

    <a href="index.php" class="brand-link">
        <i class="fas fa-shield-halved"></i>
        PenTest Scanner
    </a>

    <div class="auth-card">
        <div class="auth-logo">
    <img src="logo1.jpg" alt="PenTest Logo" class="logo" style="width: 60px; height: auto; margin-right: 8px;">
            <h1>Sign in</h1>
            <p>Sign in to your command center</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="auth/authenticate.php" method="POST" id="loginForm" autocomplete="on">

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" id="email" autocomplete="email" placeholder="operator@command.io" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" autocomplete="current-password" placeholder="••••••••" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="remember-row">
                <input type="checkbox" name="remember_me" id="remember_me">
                <label for="remember_me">Remember Me</label>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-right-to-bracket" style="margin-right:8px;"></i> Sign In
            </button>

        </form>

        <div class="auth-footer">
            New to the platform? <a href="signup.php">Create Account</a>
        </div>
    </div>
</div>

<script>
    // Password visibility toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const input = document.getElementById('password');
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });

    // Form submission loading state
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin" style="margin-right:8px;"></i> Authenticating...';
        btn.disabled = true;
    });
</script>
</body>
</html>