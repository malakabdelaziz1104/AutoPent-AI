
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Professional website vulnerability scanner. Detect security issues and get AI-powered recommendations to protect your web applications." />
    <meta name="keywords" content="penetration testing, vulnerability scanner, website security, cybersecurity, security audit" />
    <meta name="author" content="PenTest Scanner" />
    <title>PenTest Scanner - Professional Website Vulnerability Scanner</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        /* ============================================================
           CYBERPUNK THEME - PENTEST SCANNER (SOLID BLACK BG)
        ============================================================ */

        :root {
            --neon-cyan: #00ffff;
            --neon-pink: #ff00ff;
            --neon-blue: #00d4ff;
            --neon-purple: #9d00ff;
            --neon-green: #00ff88;
            --neon-orange: #ff6b00;
            --bg-void: #000000;
            --bg-deep: #0a0a0f;
            --bg-card: #0f0f1a;
            --bg-glass: rgba(15, 15, 26, 0.8);
            --text-white: #ffffff;
            --text-glow: #e0e7ff;
            --text-dim: #8892b0;
            --border-neon: rgba(0, 255, 255, 0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            background: #000000; /* خلفية سوداء سادة */
            color: var(--text-white);
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            position: relative;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
            position: relative;
            z-index: 1;
        }

        /* ============================================================
           SCROLL ANIMATIONS
        ============================================================ */
        .reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1),
                        transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .reveal.reveal-left {
            transform: translateX(-60px);
        }

        .reveal.reveal-right {
            transform: translateX(60px);
        }

        .reveal.reveal-scale {
            transform: scale(0.85);
            opacity: 0;
        }

        .reveal.visible {
            opacity: 1;
            transform: translate(0, 0) scale(1);
        }

        /* Stagger children */
        .stagger-children .reveal:nth-child(1) { transition-delay: 0s; }
        .stagger-children .reveal:nth-child(2) { transition-delay: 0.12s; }
        .stagger-children .reveal:nth-child(3) { transition-delay: 0.24s; }
        .stagger-children .reveal:nth-child(4) { transition-delay: 0.36s; }
        .stagger-children .reveal:nth-child(5) { transition-delay: 0.48s; }
        .stagger-children .reveal:nth-child(6) { transition-delay: 0.60s; }

        /* ============================================================
           NAVBAR
        ============================================================ */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 80px;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(25px) saturate(180%);
            border-bottom: 1px solid var(--border-neon);
            z-index: 1000;
            transition: all 0.3s;
        }

        .navbar.scrolled {
            height: 70px;
            box-shadow: 0 10px 50px rgba(0, 255, 255, 0.1);
        }

        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            font-family: 'Orbitron', sans-serif;
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--text-white);
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: 0.3s;
        }

        .navbar-brand:hover { transform: scale(1.05); }

        .navbar-brand .logo {
            font-size: 1.8rem;
            color: var(--neon-cyan);
            filter: drop-shadow(0 0 12px rgba(0,255,255,0.7));
        }

        .navbar-nav {
            display: flex;
            list-style: none;
            gap: 40px;
        }

        .nav-link {
            color: var(--text-glow);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            transition: 0.3s;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px; left: 0;
            width: 0; height: 2px;
            background: linear-gradient(90deg, var(--neon-cyan), var(--neon-purple));
            transition: 0.3s;
        }

        .nav-link:hover { color: var(--neon-cyan); text-shadow: 0 0 15px rgba(0, 255, 255, 0.6); }
        .nav-link:hover::after { width: 100%; }

        .navbar-actions { display: flex; gap: 15px; }

        .btn {
            padding: 12px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.6s;
        }

        .btn:hover::before { left: 100%; }

        .btn-primary {
            background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple));
            color: #000;
            border-color: var(--neon-cyan);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.4);
            font-family: 'Orbitron', sans-serif;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 40px rgba(0, 255, 255, 0.7);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-white);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--neon-cyan);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.3);
        }

        .btn-lg { padding: 18px 40px; font-size: 1.1rem; }
        .btn-sm { padding: 8px 20px; font-size: 0.85rem; }

        .mobile-menu-toggle { display: none; }

        /* ============================================================
           HERO SECTION
        ============================================================ */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 150px 0 100px;
            position: relative;
            overflow: hidden;
            background: #000000;
        }

        .hero-content {
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            opacity: 0;
            animation: heroEntry 1.2s cubic-bezier(0.16, 1, 0.3, 1) 0.3s forwards;
        }

        @keyframes heroEntry {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 4.5rem;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 30px;
            text-shadow: 0 0 40px rgba(0, 255, 255, 0.4);
            animation: titleGlow 3s ease-in-out infinite alternate;
        }

        @keyframes titleGlow {
            from { text-shadow: 0 0 20px rgba(0, 255, 255, 0.3); }
            to { text-shadow: 0 0 60px rgba(0, 255, 255, 0.8); }
        }

        .text-gradient {
            background: linear-gradient(90deg, var(--neon-cyan), var(--neon-purple), var(--neon-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradientShift 5s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { filter: hue-rotate(0deg); }
            50% { filter: hue-rotate(45deg); }
        }

        .hero-subtitle {
            font-size: 1.4rem;
            color: var(--text-dim);
            margin-bottom: 50px;
            line-height: 1.8;
            font-weight: 300;
        }

        .hero-actions { display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-top: 80px;
            flex-wrap: wrap;
        }

        .stat-item { text-align: center; }

        .stat-number {
            display: block;
            font-family: 'Orbitron', sans-serif;
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 10px;
            text-shadow: 0 0 30px rgba(0, 255, 255, 0.6);
        }

        .stat-label {
            color: var(--text-dim);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        /* ============================================================
           SECTIONS
        ============================================================ */
        .section { padding: 120px 0; position: relative; background: #000000; }

        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 80px;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 20px;
            text-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
        }

        .section-subtitle { font-size: 1.2rem; color: var(--text-dim); line-height: 1.6; }

        /* ============================================================
           GRID SYSTEM
        ============================================================ */
        .grid { display: grid; gap: 30px; }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        .gap-lg { gap: 40px; }
        .gap-md { gap: 25px; }

        /* ============================================================
           CARDS
        ============================================================ */
        .card {
            background: var(--bg-card);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 40px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(135deg, transparent, rgba(0, 255, 255, 0.05));
            opacity: 0;
            transition: 0.4s;
        }

        .card:hover::before { opacity: 1; }

        .card:hover {
            transform: translateY(-10px);
            border-color: var(--neon-cyan);
            box-shadow: 0 20px 60px rgba(0, 255, 255, 0.2);
        }

        .feature-card .icon {
            font-size: 3.5rem;
            margin-bottom: 25px;
            display: block;
            filter: drop-shadow(0 0 15px rgba(0, 255, 255, 0.5));
            color: var(--neon-cyan);
        }

        .feature-card h4 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--neon-cyan);
        }

        .feature-card p { color: var(--text-dim); line-height: 1.7; }

        /* ============================================================
           STEPS
        ============================================================ */
        .step-number {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
            margin: 0 auto 25px;
            box-shadow: 0 10px 40px rgba(0, 255, 255, 0.4);
            color: #000;
        }

        .text-center { text-align: center; }
        .text-center h4 { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; margin-bottom: 15px; color: var(--text-glow); }
        .text-center p { color: var(--text-dim); line-height: 1.7; }

        /* ============================================================
           VULNERABILITY ITEMS
        ============================================================ */
        .vuln-item {
            background: var(--bg-card);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px 25px;
            text-align: center;
            transition: 0.3s;
        }

        .vuln-item:hover {
            transform: translateY(-5px);
            border-color: var(--neon-cyan);
            box-shadow: 0 15px 40px rgba(0, 255, 255, 0.1);
        }

        .badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }

        .badge-critical { background: rgba(255, 0, 85, 0.2); color: #ff0055; border: 1px solid #ff0055; }
        .badge-high { background: rgba(255, 107, 0, 0.2); color: var(--neon-orange); border: 1px solid var(--neon-orange); }
        .badge-medium { background: rgba(255, 255, 0, 0.2); color: #ffff00; border: 1px solid #ffff00; }
        .badge-low { background: rgba(0, 255, 136, 0.2); color: var(--neon-green); border: 1px solid var(--neon-green); }

        .vuln-item h5 { font-family: 'Orbitron', sans-serif; font-size: 1rem; color: var(--text-glow); }

        /* ============================================================
           CTA SECTION
        ============================================================ */
        .cta-section {
            background: #000000;
            border-top: 2px solid var(--border-neon);
            border-bottom: 2px solid var(--border-neon);
        }

        .cta-content h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 25px;
            text-shadow: 0 0 40px rgba(0, 255, 255, 0.4);
        }

        /* ============================================================
           FOOTER
        ============================================================ */
        .footer {
            background: var(--bg-card);
            padding: 80px 0 30px;
            border-top: 2px solid var(--border-neon);
        }

        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 50px;
        }

        .footer-brand p { color: var(--text-dim); line-height: 1.8; margin-top: 20px; }

        .footer-links h4 { font-family: 'Orbitron', sans-serif; color: var(--neon-cyan); margin-bottom: 20px; font-size: 1.1rem; }
        .footer-links ul { list-style: none; }
        .footer-links li { margin-bottom: 12px; }
        .footer-links a { color: var(--text-dim); text-decoration: none; transition: 0.3s; }
        .footer-links a:hover { color: var(--neon-cyan); text-shadow: 0 0 10px rgba(0, 255, 255, 0.5); }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-dim);
            font-size: 0.9rem;
        }

        /* ============================================================
           UTILITIES
        ============================================================ */
        .mt-xl { margin-top: 80px; }

        /* ============================================================
           SCROLLBAR
        ============================================================ */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: var(--bg-deep); }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, var(--neon-cyan), var(--neon-purple)); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--neon-cyan); }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1200px) {
            .grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
            .grid-cols-4 { grid-template-columns: repeat(3, 1fr); }
            .footer-content { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 768px) {
            .navbar-nav { display: none; }
            .navbar-nav.open { display: flex; flex-direction: column; position: absolute; top: 80px; left: 0; right: 0; background: var(--bg-card); border-bottom: 1px solid var(--border-neon); padding: 20px 40px; gap: 20px; }

            .mobile-menu-toggle {
                display: flex;
                flex-direction: column;
                gap: 5px;
                background: transparent;
                border: none;
                cursor: pointer;
            }

            .mobile-menu-toggle span {
                width: 25px; height: 3px;
                background: var(--neon-cyan);
                border-radius: 3px;
                transition: 0.3s;
            }

            .hero-title { font-size: 2.5rem; }
            .section-title { font-size: 2rem; }
            .cta-content h2 { font-size: 2.2rem; }
            .grid-cols-3, .grid-cols-4 { grid-template-columns: 1fr; }
            .footer-content { grid-template-columns: 1fr; gap: 40px; }
            .hero-actions { flex-direction: column; align-items: center; }
            .hero-stats { gap: 40px; }
            .container { padding: 0 20px; }
        }
    </style>
</head>
<body>

<nav class="navbar" id="navbar">
    <div class="container navbar-container">
        <a href="index.php" class="navbar-brand">
    <img src="logo1.jpg" alt="PenTest Logo" class="logo" style="width: 40px; height: auto; margin-right: 8px;">
            <span>PenTest Scanner</span>
        </a>

        <ul class="navbar-nav" id="navMenu">
            <li><a href="#features" class="nav-link">Features</a></li>
            <li><a href="#how-it-works" class="nav-link">How It Works</a></li>
            <li><a href="#pricing" class="nav-link">Pricing</a></li>
            <li><a href="#faq" class="nav-link">FAQ</a></li>
        </ul>

        <div class="navbar-actions">
            <a href="login.php" class="btn btn-secondary btn-sm">Login</a>
            <a href="signup.php" class="btn btn-primary btn-sm">Get Started</a>
        </div>

        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<section class="hero" id="hero">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">
                Secure Your Website with
                <span class="text-gradient">AI-Powered</span>
                Vulnerability Scanning
            </h1>

            <p class="hero-subtitle">
                Detect security vulnerabilities, get detailed reports, and receive
                intelligent recommendations to protect your web applications from
                cyber threats.
            </p>

            <div class="hero-actions">
                <a href="signup.php" class="btn btn-primary btn-lg">
                    Start Free Scan <span>→</span>
                </a>
                <a href="#how-it-works" class="btn btn-secondary btn-lg">
                    Learn More
                </a>
            </div>

            <div class="hero-stats mt-xl">
                <div class="stat-item">
                    <span class="stat-number text-gradient">10,000+</span>
                    <span class="stat-label">Scans Completed</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number text-gradient">500+</span>
                    <span class="stat-label">Vulnerabilities Found</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number text-gradient">99.9%</span>
                    <span class="stat-label">Accuracy Rate</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="features">
    <div class="container">
        <div class="section-header reveal">
            <h2 class="section-title">
                Powerful <span class="text-gradient">Features</span>
            </h2>
            <p class="section-subtitle">
                Everything you need to identify and fix security vulnerabilities in
                your web applications
            </p>
        </div>

        <div class="grid grid-cols-3 gap-lg stagger-children">
            <div class="card feature-card reveal reveal-left">
                <div class="icon"><i class="fas fa-magnifying-glass"></i></div>
                <h4>Vulnerability Detection</h4>
                <p>Scan for SQL injection, XSS, CSRF, and 50+ other common vulnerabilities that hackers exploit daily.</p>
            </div>
            <div class="card feature-card reveal">
                <div class="icon"><i class="fas fa-globe"></i></div>
                <h4>Port Scanning</h4>
                <p>Discover open ports and services running on your server using professional-grade Nmap integration.</p>
            </div>
            <div class="card feature-card reveal reveal-right">
                <div class="icon"><i class="fas fa-robot"></i></div>
                <h4>AI Recommendations</h4>
                <p>Get intelligent, actionable recommendations powered by AI to fix identified security issues.</p>
            </div>
            <div class="card feature-card reveal reveal-left">
                <div class="icon"><i class="fas fa-file-shield"></i></div>
                <h4>Detailed Reports</h4>
                <p>Receive comprehensive PDF reports with risk ratings, executive summaries, and technical remediation guides.</p>
            </div>
            <div class="card feature-card reveal">
                <div class="icon"><i class="fas fa-clock-rotate-left"></i></div>
                <h4>Scan History</h4>
                <p>Complete audit trail of all security scans with trend analysis and comparative metrics over time.</p>
            </div>
            <div class="card feature-card reveal reveal-right">
                <div class="icon"><i class="fas fa-bell"></i></div>
                <h4>Real-time Alerts</h4>
                <p>Instant notifications for critical vulnerabilities with priority scoring and automated response workflows.</p>
            </div>
        </div>
    </div>
</section>

<section class="section" id="how-it-works">
    <div class="container">
        <div class="section-header reveal">
            <h2 class="section-title">
                How It <span class="text-gradient">Works</span>
            </h2>
            <p class="section-subtitle">
                Start securing your website in just three simple steps
            </p>
        </div>

        <div class="grid grid-cols-3 stagger-children">
            <div class="card text-center reveal reveal-scale">
                <div class="step-number">1</div>
                <h4>Enter Your URL</h4>
                <p>Simply paste your website URL into our scanner. We support any publicly accessible website.</p>
            </div>
            <div class="card text-center reveal reveal-scale">
                <div class="step-number">2</div>
                <h4>Run the Scan</h4>
                <p>Our advanced scanner analyzes your website for vulnerabilities, open ports, and security misconfigurations.</p>
            </div>
            <div class="card text-center reveal reveal-scale">
                <div class="step-number">3</div>
                <h4>Get Results</h4>
                <p>Receive a detailed report with findings and AI-powered recommendations to fix any issues found.</p>
            </div>
        </div>

        <div class="text-center mt-xl reveal">
            <a href="signup.php" class="btn btn-primary btn-lg">
                Start Your Free Scan Now
            </a>
        </div>
    </div>
</section>

<section class="section" id="vulnerabilities">
    <div class="container">
        <div class="section-header reveal">
            <h2 class="section-title">
                What We <span class="text-gradient">Detect</span>
            </h2>
            <p class="section-subtitle">
                Our scanner checks for the most critical web application vulnerabilities
            </p>
        </div>

        <div class="grid grid-cols-4 gap-md stagger-children">
            <div class="vuln-item reveal reveal-scale">
                <span class="badge badge-critical">Critical</span>
                <h5>SQL Injection</h5>
            </div>
            <div class="vuln-item reveal reveal-scale">
                <span class="badge badge-critical">Critical</span>
                <h5>Remote Code Execution</h5>
            </div>
            <div class="vuln-item reveal reveal-scale">
                <span class="badge badge-high">High</span>
                <h5>Cross-Site Scripting (XSS)</h5>
            </div>
            <div class="vuln-item reveal reveal-scale">
                <span class="badge badge-high">High</span>
                <h5>Authentication Bypass</h5>
            </div>
            <div class="vuln-item reveal reveal-scale">
                <span class="badge badge-medium">Medium</span>
                <h5>CSRF Vulnerabilities</h5>
            </div>
            <div class="vuln-item reveal reveal-scale">
                <span class="badge badge-medium">Medium</span>
                <h5>Insecure Cookies</h5>
            </div>
            <div class="vuln-item reveal reveal-scale">
                <span class="badge badge-low">Low</span>
                <h5>Information Disclosure</h5>
            </div>
            <div class="vuln-item reveal reveal-scale">
                <span class="badge badge-low">Low</span>
                <h5>Missing Headers</h5>
            </div>
        </div>
    </div>
</section>

<section class="section cta-section" id="cta">
    <div class="container">
        <div class="cta-content text-center reveal">
            <h2>Ready to Secure Your Website?</h2>
            <p class="section-subtitle">
                Join thousands of developers and security professionals who trust PenTest Scanner
            </p>
            <div class="hero-actions" style="margin-top: 40px;">
                <a href="signup.php" class="btn btn-primary btn-lg">
                    Create Free Account
                </a>
                <a href="login.php" class="btn btn-secondary btn-lg">
                    Login to Dashboard
                </a>
            </div>
        </div>
    </div>
</section>

<footer class="footer" id="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-brand reveal reveal-left">
                <a href="index.php" class="navbar-brand">
                    <span class="logo"><i class="fas fa-shield-halved"></i></span>
                    <span>PenTest Scanner</span>
                </a>
                <p>Professional website vulnerability scanning and security analysis powered by advanced AI technology.</p>
            </div>

            <div class="footer-links reveal">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>
            </div>

            <div class="footer-links reveal">
                <h4>Resources</h4>
                <ul>
                    <li><a href="#">Documentation</a></li>
                    <li><a href="#">API Reference</a></li>
                    <li><a href="#">Security Blog</a></li>
                    <li><a href="#">Tutorials</a></li>
                </ul>
            </div>

            <div class="footer-links reveal reveal-right">
                <h4>Legal</h4>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                    <li><a href="#">Disclaimer</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2024 PenTest Scanner. All rights reserved. Built for educational purposes.</p>
        </div>
    </div>
</footer>

<script>
    // ============================================================
    // NAVBAR SCROLL EFFECT
    // ============================================================
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
    });

    // ============================================================
    // SMOOTH SCROLL
    // ============================================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // ============================================================
    // MOBILE MENU
    // ============================================================
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const navMenu = document.getElementById('navMenu');

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            navMenu.classList.toggle('open');
        });
    }

    // ============================================================
    // SCROLL REVEAL ANIMATION
    // ============================================================
    const revealElements = document.querySelectorAll('.reveal');

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                // Keep observing for reverse animation on scroll up
            } else {
                // Remove 'visible' when element goes out of viewport (scroll up)
                entry.target.classList.remove('visible');
            }
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -60px 0px'
    });

    revealElements.forEach(el => revealObserver.observe(el));
</script>
</body>
</html>

