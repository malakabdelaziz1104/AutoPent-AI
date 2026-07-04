<?php
require_once 'includes/functions.php';
startSession();

// توليد الـ CSRF Token لو مش موجود
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - PenTest Scanner</title>
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
            padding: 40px 20px;
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
        .orb-1 { width: 500px; height: 500px; background: var(--neon-purple); top: -150px; right: -150px; animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; background: var(--neon-cyan); bottom: -100px; left: -100px; animation-delay: 12s; }

        @keyframes float {
            0%, 100% { transform: translate(0,0) scale(1); }
            50% { transform: translate(-30px, 20px) scale(1.08); }
        }

        /* Wrapper */
        .auth-wrapper {
            width: 100%;
            max-width: 500px;
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
            padding: 44px;
            box-shadow:
                0 0 60px rgba(157, 0, 255, 0.08),
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
            background: linear-gradient(90deg, transparent, var(--neon-purple), var(--neon-cyan), transparent);
        }

        /* Corner glow */
        .auth-card::after {
            content: '';
            position: absolute;
            bottom: -60px; left: -60px;
            width: 200px; height: 200px;
            background: var(--neon-purple);
            filter: blur(80px);
            opacity: 0.06;
            border-radius: 50%;
        }

        /* Logo */
        .auth-logo {
            text-align: center;
            margin-bottom: 34px;
        }

        .auth-logo .shield-icon {
            font-size: 2.8rem;
            color: var(--neon-cyan);
            filter: drop-shadow(0 0 20px rgba(0, 255, 255, 0.7));
            display: block;
            margin-bottom: 12px;
            animation: iconPulse 3s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { filter: drop-shadow(0 0 15px rgba(0, 255, 255, 0.5)); }
            50%       { filter: drop-shadow(0 0 35px rgba(0, 255, 255, 0.9)); }
        }

        .auth-logo h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.4rem;
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
            font-size: 0.875rem;
            margin-top: 6px;
        }

        /* Two-column row */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* Form */
        .form-label {
            color: var(--text-dim);
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            display: block;
        }

        .form-group { margin-bottom: 18px; }

        .input-wrapper { position: relative; }

        .input-wrapper input {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 13px 16px 13px 46px;
            color: var(--text-white);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .input-wrapper input::placeholder { color: rgba(136, 146, 176, 0.45); }

        .input-wrapper input:focus {
            outline: none;
            border-color: var(--neon-cyan);
            background: rgba(0, 255, 255, 0.04);
            box-shadow: 0 0 0 3px rgba(0, 255, 255, 0.12), 0 0 20px rgba(0, 255, 255, 0.08);
        }

        .input-wrapper input.error {
            border-color: #ff0055;
            box-shadow: 0 0 0 3px rgba(255, 0, 85, 0.12);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 0.9rem;
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
            font-size: 0.9rem;
            transition: color 0.3s;
            padding: 4px;
        }

        .password-toggle:hover { color: var(--neon-cyan); }

        /* Password strength bar */
        .strength-bar {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }

        .strength-segment {
            flex: 1;
            height: 3px;
            border-radius: 4px;
            background: rgba(255,255,255,0.1);
            transition: background 0.4s;
        }

        .strength-segment.weak   { background: #ff0055; }
        .strength-segment.medium { background: #ffff00; }
        .strength-segment.strong { background: var(--neon-green); }

        .strength-label {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 5px;
        }

        /* Hint */
        .hint {
            font-size: 0.75rem;
            color: #4a5568;
            margin-top: 6px;
            display: block;
        }

        /* Terms */
        .terms-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 24px;
        }

        .terms-row input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--neon-cyan);
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .terms-row label {
            color: var(--text-dim);
            font-size: 0.85rem;
            cursor: pointer;
            line-height: 1.5;
        }

        .terms-row label span { color: var(--text-white); font-weight: 600; }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple));
            color: #000;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.9rem;
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

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Footer */
        .auth-footer {
            text-align: center;
            margin-top: 24px;
            color: var(--text-dim);
            font-size: 0.875rem;
        }

        .auth-footer a {
            color: var(--neon-cyan);
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .auth-footer a:hover { text-shadow: 0 0 12px rgba(0, 255, 255, 0.7); }

        /* Alert */
        .alert {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 22px;
            font-size: 0.875rem;
        }

        .alert-error   { background: rgba(255, 0, 85, 0.1); border: 1px solid rgba(255, 0, 85, 0.4); color: #ff4477; }
        .alert-success { background: rgba(0, 255, 136, 0.1); border: 1px solid rgba(0, 255, 136, 0.4); color: var(--neon-green); }

        /* Error text */
        .error-text {
            font-size: 0.78rem;
            color: #ff4477;
            margin-top: 5px;
            display: none;
        }

        .error-text.visible { display: block; }

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
            margin-bottom: 20px;
            transition: color 0.3s;
        }

        .brand-link:hover { color: var(--neon-cyan); }
        .brand-link i { color: var(--neon-cyan); filter: drop-shadow(0 0 8px rgba(0,255,255,0.6)); }

        @media (max-width: 520px) {
            .auth-card { padding: 32px 24px; }
            .form-row { grid-template-columns: 1fr; }
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
            <i class="fas fa-user-shield shield-icon"></i>
            <h1>Join the Network</h1>
            <p>Create your operator account</p>
        </div>

        <!-- Flash message placeholder -->
        <!-- <div class="alert alert-error">Username already taken.</div> -->

        <form action="auth/register.php" method="POST" id="signupForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" id="username" placeholder="cyber_expert" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" id="email" placeholder="operator@command.io" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="strength-bar" id="strengthBar">
                        <div class="strength-segment" id="seg1"></div>
                        <div class="strength-segment" id="seg2"></div>
                        <div class="strength-segment" id="seg3"></div>
                        <div class="strength-segment" id="seg4"></div>
                    </div>
                    <span class="hint" id="strengthLabel">Min 8 chars, numbers & symbols</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required>
                    </div>
                    <span class="error-text" id="matchError">Passwords do not match</span>
                </div>
            </div>

            <div class="terms-row">
                <input type="checkbox" name="terms" id="terms" required>
                <label for="terms">
                    I accept the <span>Terms of Service</span> and <span>Privacy Policy</span>
                </label>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-user-plus" style="margin-right:8px;"></i> Create Identity
            </button>

        </form>

        <div class="auth-footer">
            Already part of the network? <a href="login.php">Sign In</a>
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

    // Password strength meter
    document.getElementById('password').addEventListener('input', function() {
        const val = this.value;
        const segs = [
            document.getElementById('seg1'),
            document.getElementById('seg2'),
            document.getElementById('seg3'),
            document.getElementById('seg4'),
        ];
        const label = document.getElementById('strengthLabel');

        let score = 0;
        if (val.length >= 8)  score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const classes = ['', 'weak', 'medium', 'medium', 'strong'];
        const labels  = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        const colors  = ['', '#ff0055', '#ffaa00', '#ffff00', '#00ff88'];

        segs.forEach((s, i) => {
            s.className = 'strength-segment';
            if (i < score) s.classList.add(score === 1 ? 'weak' : score <= 2 ? 'medium' : 'strong');
        });

        label.textContent = val.length === 0 ? 'Min 8 chars, numbers & symbols' : labels[score] + ' password';
        label.style.color = val.length === 0 ? '#4a5568' : colors[score];
    });

    // Confirm password live check
    document.getElementById('confirm_password').addEventListener('input', function() {
        const pass = document.getElementById('password').value;
        const err  = document.getElementById('matchError');
        if (this.value && this.value !== pass) {
            err.classList.add('visible');
            this.classList.add('error');
        } else {
            err.classList.remove('visible');
            this.classList.remove('error');
        }
    });

    // Form submit
    document.getElementById('signupForm').addEventListener('submit', function(e) {
        const pass    = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;

        if (pass !== confirm) {
            e.preventDefault();
            document.getElementById('matchError').classList.add('visible');
            document.getElementById('confirm_password').classList.add('error');
            return;
        }

        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin" style="margin-right:8px;"></i> Creating Identity...';
        btn.disabled = true;
    });
</script>
</body>
</html>