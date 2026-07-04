<?php
/**
 * ============================================
 * PENTEST SCANNER - User Dashboard
 * ============================================
 * Course Project: Part 1 - Lesson 9
 * * This is a PROTECTED page - only accessible to logged-in users
 * It will be the main hub where users can:
 * - View their account info
 * - Start new scans
 * - View scan history
 * - Access AI recommendations
 */

// ============================================
// INCLUDE REQUIRED FILES
// ============================================
require_once 'includes/functions.php';
require_once 'includes/db.php';

// ============================================
// START SESSION AND CHECK AUTHENTICATION
// ============================================
startSession();

/**
 * requireLogin() function:
 * - Checks if user is logged in
 * - If NOT, redirects to login.php
 * - If YES, continues to the page
 */
requireLogin();

// Get flash messages (e.g., "Welcome back!")
$flash = getFlashMessage();

// ============================================
// Get User Information
// ============================================
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];

/**
 * Get additional user data from database if needed
 * For now, we'll just use session data
 */
try {
    // Get user's scan statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_scans,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_scans,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_scans
        FROM scans 
        WHERE user_id = :user_id
    ");
    $stmt->execute(['user_id' => $user_id]);
    $stats = $stmt->fetch();

    // Set defaults if no scans yet
    $total_scans = $stats['total_scans'] ?? 0;
    $completed_scans = $stats['completed_scans'] ?? 0;
    $pending_scans = $stats['pending_scans'] ?? 0;

    // Get recent scans (last 5)
    $stmt = $pdo->prepare("
        SELECT id, target_url, scan_date, status, scan_duration 
        FROM scans 
        WHERE user_id = :user_id 
        ORDER BY scan_date DESC 
        LIMIT 5
    ");
    $stmt->execute(['user_id' => $user_id]);
    $recent_scans = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $total_scans = 0;
    $completed_scans = 0;
    $pending_scans = 0;
    $recent_scans = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Your PenTest Scanner dashboard - manage scans and view security reports.">
    <title>Dashboard - PenTest Scanner</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        /* Dashboard Layout */
        .dashboard {
            min-height: 100vh;
            padding-top: 80px;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            flex-wrap: wrap;
            gap: var(--spacing-md);
        }

        .dashboard-welcome h1 {
            font-size: var(--text-3xl);
            margin-bottom: var(--spacing-xs);
        }

        .dashboard-welcome p {
            color: var(--text-secondary);
            margin: 0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            text-align: center;
            transition: all var(--transition-normal);
        }

        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .stat-card .stat-icon {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-md);
        }

        .stat-card .stat-value {
            font-size: var(--text-4xl);
            font-weight: 700;
            color: var(--primary);
            margin-bottom: var(--spacing-xs);
        }

        .stat-card .stat-label {
            color: var(--text-secondary);
            font-size: var(--text-sm);
        }

        /* Quick Action Card */
        .scan-card {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(168, 85, 247, 0.1));
            border: 1px solid var(--primary);
            border-radius: var(--radius-xl);
            padding: var(--spacing-2xl);
            margin-bottom: var(--spacing-2xl);
            text-align: center;
        }

        .scan-card h2 {
            margin-bottom: var(--spacing-md);
        }

        .scan-card p {
            margin-bottom: var(--spacing-lg);
            color: var(--text-secondary);
        }

        .scan-form {
            display: flex;
            gap: var(--spacing-md);
            max-width: 600px;
            margin: 0 auto;
        }

        .scan-form input {
            flex: 1;
        }

        /* Recent Scans Table */
        .recent-scans {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .recent-scans-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .recent-scans-header h3 {
            margin: 0;
        }

        .scans-table {
            width: 100%;
            border-collapse: collapse;
        }

        .scans-table th,
        .scans-table td {
            padding: var(--spacing-md) var(--spacing-lg);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .scans-table th {
            background: var(--bg-darker);
            color: var(--text-secondary);
            font-weight: 500;
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .scans-table tr:hover {
            background: var(--bg-card-hover);
        }

        .scans-table .url-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed {
            background: rgba(0, 255, 136, 0.2);
            color: var(--success);
        }

        .status-pending {
            background: rgba(255, 170, 0, 0.2);
            color: var(--warning);
        }

        .status-in_progress {
            background: rgba(0, 212, 255, 0.2);
            color: var(--primary);
        }

        .status-failed {
            background: rgba(255, 71, 87, 0.2);
            color: var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-2xl);
            color: var(--text-muted);
        }

        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
        }

        /* User menu dropdown */
        .user-menu {
            position: relative;
        }

        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-full);
            color: var(--text-primary);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .user-menu-btn:hover {
            border-color: var(--primary);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--info));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--text-sm);
        }
/* Dropdown Menu */
.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;

    min-width: 200px;

    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;

    padding: 8px;

    display: none;

    z-index: 9999;

    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.dropdown-menu.active {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;

    padding: 12px;
    border-radius: 8px;

    color: var(--text-primary);
    text-decoration: none;

    transition: 0.3s;
}

.dropdown-item:hover {
    background: rgba(255,255,255,0.08);
}
        /* Responsive */
        @media (max-width: 768px) {
            .scan-form {
                flex-direction: column;
            }

            .scans-table {
                font-size: var(--text-sm);
            }

            .scans-table th:nth-child(3),
            .scans-table td:nth-child(3),
            .scans-table th:nth-child(4),
            .scans-table td:nth-child(4) {
                display: none;
            }
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

            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="scans.php" class="nav-link">Scans</a></li>
                <li><a href="all_reports.php" class="nav-link">Reports</a></li>
            </ul>

            <div class="navbar-actions">
                <div class="user-menu" id="userMenu">
                    <button class="user-menu-btn" id="userMenuBtn">
                        <span class="user-avatar">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </span>
                        <span><?php echo htmlspecialchars($username); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="settings.php" class="dropdown-item"><i class="fas fa-user-gear"></i> Settings</a>
                        <a href="logout.php" class="dropdown-item"><i class="fas fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>

            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <main class="dashboard">
        <div class="dashboard-container">

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> fade-in">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-header fade-in">
                <div class="dashboard-welcome">
                    <h1>Welcome back, <span class="text-gradient"><?php echo htmlspecialchars($username); ?></span>!
                    </h1>
                    <p>Ready to secure your websites? Start a new scan or view your recent activity.</p>
                </div>
            </div>

            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-icon">🔍</div>
                    <div class="stat-value"><?php echo $total_scans; ?></div>
                    <div class="stat-label">Total Scans</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo $completed_scans; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value"><?php echo $pending_scans; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛡️</div>
                    <div class="stat-value">0</div>
                    <div class="stat-label">Vulnerabilities Found</div>
                </div>
            </div>

            <div class="scan-card fade-in">
                <h2><i class="fas fa-rocket"></i> Start a New Recon Scan</h2>
                <p>Enter a website URL or IP address to detect open ports and running services.</p>
                <form class="scan-form" action="scans/process.php" method="POST" id="scanForm">
                    <div style="flex: 1; display: flex; flex-direction: column; gap: var(--spacing-sm);">
                        <input type="text" name="target_url" class="form-input" placeholder="example.com or 192.168.1.1"
                            required>
                        <div style="text-align: left; display: flex; flex-direction: column; gap: 10px; padding: 5px;">
                            <label class="cyber-checkbox-wrapper" for="deepScan">
                                <input type="checkbox" name="deep_scan" id="deepScan" value="1">
                                <div class="cyber-checkbox"></div>
                                <span class="cyber-label-text">Enable Comprehensive Scan (OpenVAS Engine)</span>
                            </label>

                            <label class="cyber-checkbox-wrapper" for="webScan">
                                <input type="checkbox" name="web_scan" id="webScan" value="1">
                                <div class="cyber-checkbox"></div>
                                <span class="cyber-label-text">Enable Web Vulnerability Scan (Burp Suite)</span>
                            </label>

                            <div style="margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 10px;">
                                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Advanced Nmap Options</p>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <label class="cyber-checkbox-wrapper" for="nmapAggressive">
                                        <input type="checkbox" name="nmap_aggressive" id="nmapAggressive" value="1">
                                        <div class="cyber-checkbox"></div>
                                        <span class="cyber-label-text">Aggressive Scan (-A)</span>
                                    </label>
                                    <label class="cyber-checkbox-wrapper" for="nmapFull">
                                        <input type="checkbox" name="nmap_full" id="nmapFull" value="1">
                                        <div class="cyber-checkbox"></div>
                                        <span class="cyber-label-text">Full Port Scan (-p-)</span>
                                    </label>
                                    <label class="cyber-checkbox-wrapper" for="nmapScripts">
                                        <input type="checkbox" name="nmap_scripts" id="nmapScripts" value="1">
                                        <div class="cyber-checkbox"></div>
                                        <span class="cyber-label-text">Vulnerability Scripts</span>
                                    </label>
                                    <label class="cyber-checkbox-wrapper" for="firewallDetection">
                                        <input type="checkbox" name="firewall_detection" id="firewallDetection" value="1">
                                        <div class="cyber-checkbox"></div>
                                        <span class="cyber-label-text">Firewall Detection</span>
                                    </label>

                                    <label class="cyber-checkbox-wrapper" for="defaultCredentials">
                                        <input type="checkbox" name="default_credentials" id="defaultCredentials" value="1">
                                        <div class="cyber-checkbox"></div>
                                        <span class="cyber-label-text">Default Credentials Check</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="align-self: flex-start;">
                        Launch Audit <i class="fas fa-magnifying-glass"></i>
                    </button>
                </form>
            </div>

            <div id="activeScansContainer" style="display: none; margin-bottom: var(--spacing-xl);">
                <div class="recent-scans">
                    <div class="recent-scans-header">
                        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <h3><i class="fas fa-spinner fa-spin"></i> Active Scan (Live Progress)</h3>
                            <button id="cancelScanBtn" class="btn btn-danger btn-sm" onclick="cancelActiveScan()">
                                <i class="fas fa-xmark"></i> Cancel Scan
                            </button>
                        </div>
                    </div>
                    <div id="activeScansList" style="padding: var(--spacing-lg);">
                        </div>
                </div>
            </div>

            <div class="recent-scans fade-in" id="recentScans">
                <div class="recent-scans-header">
                    <h3><i class="fas fa-clock-rotate-left"></i> Recent Scans</h3>
                    <a href="scans.php" class="btn btn-secondary btn-sm">View All</a>
                </div>

                <?php if (empty($recent_scans)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fas fa-magnifying-glass"></i></div>
                        <h4>No scans yet</h4>
                        <p>Start your first vulnerability scan to see results here.</p>
                    </div>
                <?php else: ?>
                    <table class="scans-table">
                        <thead>
                            <tr>
                                <th>Target URL</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_scans as $scan): ?>
                                <tr>
                                    <td class="url-cell">
                                        <a href="<?php echo htmlspecialchars($scan['target_url']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($scan['target_url']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $scan['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $scan['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($scan['scan_date']); ?></td>
                                    <td>
                                        <?php 
                                            if (!empty($scan['scan_duration'])) {
                                                $total_sec = $scan['scan_duration'];
                                                $hours = floor($total_sec / 3600);
                                                $minutes = floor(($total_sec % 3600) / 60);
                                                $seconds = $total_sec % 60;
                                                
                                                $time_str = "";
                                                if ($hours > 0) {
                                                    $time_str .= $hours . "h ";
                                                }
                                                if ($minutes > 0 || $hours > 0) {
                                                    $time_str .= $minutes . "m ";
                                                }
                                                $time_str .= $seconds . "s";
                                                
                                                echo trim($time_str);
                                            } else {
                                                echo '--';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="report.php?id=<?php echo $scan['id']; ?>"
                                            class="btn btn-secondary btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script src="assets/javascript/main.js"></script>
    <script>
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');
    
    userMenuBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        userDropdown.classList.toggle('active');
    });
    
    document.addEventListener('click', function() {
        userDropdown.classList.remove('active');
    });
        // ============================================
        // AJAX SCAN SUBMISSION + LIVE PROGRESS TRACKER
        // ============================================
        const scanForm = document.getElementById('scanForm');
        const activeContainer = document.getElementById('activeScansContainer');
        const activeScansList = document.getElementById('activeScansList');
        
        // المتغيرات الجديدة الخاصة بالعداد المحلي
        let activeScanId = null;
        let pollInterval = null;
        let scanStartTime = null;
        let localCountdownInterval = null;
        let currentRemainingSeconds = 0;
        let lastProgress = 0;

        // الدالة المسئولة عن العداد المحلي السلس
        function startLocalETA() {
            if (localCountdownInterval) clearInterval(localCountdownInterval);
            
            localCountdownInterval = setInterval(() => {
                if (currentRemainingSeconds > 0) {
                    currentRemainingSeconds--;
                    
                    let h = Math.floor(currentRemainingSeconds / 3600);
                    let m = Math.floor((currentRemainingSeconds % 3600) / 60);
                    let s = currentRemainingSeconds % 60;
                    
                    let etaStr = '';
                    if (h > 0) etaStr += h + 'h ';
                    if (m > 0 || h > 0) etaStr += m + 'm ';
                    etaStr += s + 's';
                    
                    document.getElementById('scanETA').innerHTML = '<i class="fas fa-clock"></i> ~' + etaStr + ' remaining';
                } else if (currentRemainingSeconds === 0 && lastProgress > 0 && lastProgress < 100) {
                    document.getElementById('scanETA').innerHTML = '<i class="fas fa-hourglass-half fa-spin"></i> Finalizing...';
                }
            }, 1000);
        }

        scanForm.addEventListener('submit', function(e) {
            e.preventDefault(); 

            const btn = this.querySelector('button[type="submit"]');
            const formData = new FormData(this);
            const targetUrl = formData.get('target_url');

            if (!targetUrl) return;

            // Reset ETA state
            lastProgress = 0;
            currentRemainingSeconds = 0;
            if (localCountdownInterval) clearInterval(localCountdownInterval);

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initiating...';

            fetch('scans/process.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    activeScanId = data.scan_id;
                    scanStartTime = Date.now();

                    activeContainer.style.display = 'block';
                    activeScansList.innerHTML = buildProgressUI(targetUrl);

                    activeContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    scanForm.reset();
                    btn.disabled = false;
                    btn.innerHTML = 'Launch Audit <i class="fas fa-magnifying-glass"></i>';

                    if (pollInterval) clearInterval(pollInterval);
                    pollInterval = setInterval(pollScanProgress, 2000);
                } else {
                    showAlert(data.error || 'Failed to start scan.', 'error');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    btn.disabled = false;
                    btn.innerHTML = 'Launch Audit <i class="fas fa-magnifying-glass"></i>';
                }
            })
            .catch(err => {
                console.error('Scan submission error:', err);
                showAlert('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Launch Audit <i class="fas fa-magnifying-glass"></i>';
            });
        });

        function buildProgressUI(targetUrl) {
            return `
                <div id="scanProgressCard" style="padding: 5px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div id="scanPulse" style="width: 10px; height: 10px; border-radius: 50%; background: var(--primary); animation: ripple 1.5s infinite;"></div>
                            <span style="font-weight: 600; font-size: 15px;">${targetUrl}</span>
                        </div>
                        <span id="scanETA" style="font-size: 12px; color: var(--text-muted);"><i class="fas fa-clock"></i> Estimating...</span>
                    </div>

                    <div style="width: 100%; height: 14px; background: var(--bg-darker); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); margin-bottom: 12px;">
                        <div id="scanProgressBar" style="width: 5%; height: 100%; background: linear-gradient(90deg, var(--primary), #a855f7); transition: width 0.6s ease; border-radius: 8px;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span id="scanEngine" style="font-size: 13px; color: var(--text-secondary);"><i class="fas fa-gear fa-spin"></i> Initializing scan engines...</span>
                        <span id="scanPercent" style="font-size: 13px; font-weight: 700; color: var(--primary);">5%</span>
                    </div>
                </div>
            `;
        }

        function pollScanProgress() {
            fetch('scans/status.php')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;

                    const scan = data.scans.find(s => s.id == activeScanId);

                    if (scan) {
                        const progress = parseInt(scan.progress) || 0;
                        const engine = scan.current_engine || 'Processing...';

                        document.getElementById('scanProgressBar').style.width = progress + '%';
                        document.getElementById('scanPercent').textContent = progress + '%';
                        document.getElementById('scanEngine').innerHTML = '<i class="fas fa-gear fa-spin"></i> ' + engine;

                        // الحساب الذكي للوقت المتبقي وتشغيل العداد
                        const elapsed = (Date.now() - scanStartTime) / 1000;
                        
                        if (progress > 0 && progress < 100) {
                            if (progress !== lastProgress) {
                                const timePerPercent = elapsed / progress;
                                const percentLeft = 100 - progress;
                                
                                currentRemainingSeconds = Math.round(timePerPercent * percentLeft);
                                lastProgress = progress;
                                
                                if (!localCountdownInterval) startLocalETA();
                            }
                        }
                    } else {
                        // Scan completed or failed
                        clearInterval(pollInterval);
                        pollInterval = null;
                        if (localCountdownInterval) clearInterval(localCountdownInterval);

                        document.getElementById('scanProgressBar').style.width = '100%';
                        document.getElementById('scanProgressBar').style.background = 'linear-gradient(90deg, var(--success), #22c55e)';
                        document.getElementById('scanPercent').textContent = '100%';
                        document.getElementById('scanEngine').innerHTML = '<i class="fas fa-circle-check"></i> Scan Complete!';
                        document.getElementById('scanETA').innerHTML = '<i class="fas fa-arrow-right"></i> Redirecting...';
                        document.getElementById('scanPulse').style.background = 'var(--success)';
                        document.getElementById('scanPulse').style.animation = 'none';

                        setTimeout(() => {
                            window.location.href = 'report.php?id=' + activeScanId;
                        }, 1500);
                    }
                })
                .catch(err => console.error('Polling error:', err));
        }

        function cancelActiveScan() {
            if (!activeScanId) return;

            if (!confirm('Are you sure you want to cancel this scan?')) return;

            const cancelBtn = document.getElementById('cancelScanBtn');
            cancelBtn.disabled = true;
            cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

            fetch('scans/cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ scan_id: activeScanId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    if (localCountdownInterval) clearInterval(localCountdownInterval);
                    
                    activeContainer.style.display = 'none';
                    activeScanId = null;
                    showAlert('Scan cancelled successfully.', 'success');
                } else {
                    showAlert(data.error || 'Failed to cancel scan.', 'error');
                    cancelBtn.disabled = false;
                    cancelBtn.innerHTML = '<i class="fas fa-xmark"></i> Cancel Scan';
                }
            })
            .catch(err => {
                console.error('Cancel error:', err);
                showAlert('Network error. Please try again.', 'error');
                cancelBtn.disabled = false;
                cancelBtn.innerHTML = '<i class="fas fa-xmark"></i> Cancel Scan';
            });
        }

        function checkExistingScans() {
            fetch('scans/status.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.scans.length > 0) {
                        const scan = data.scans[0];
                        activeScanId = scan.id;
                        scanStartTime = Date.now();
                        activeContainer.style.display = 'block';
                        activeScansList.innerHTML = buildProgressUI(scan.target_url);

                        const progress = parseInt(scan.progress) || 0;
                        document.getElementById('scanProgressBar').style.width = progress + '%';
                        document.getElementById('scanPercent').textContent = progress + '%';
                        document.getElementById('scanEngine').innerHTML = '<i class="fas fa-gear fa-spin"></i> ' + (scan.current_engine || 'Processing...');

                        if (!pollInterval) {
                            pollInterval = setInterval(pollScanProgress, 2000);
                        }
                    }
                })
                .catch(err => console.error('Initial poll error:', err));
        }

        checkExistingScans();
    </script>
</body>

</html>

```