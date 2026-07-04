<?php
/**
 * ============================================
 * PENTEST SCANNER - All Reports
 * ============================================
 * Course Project: Part 2 - Lesson 15
 * 
 * This page specifically displays COMPLETED reports ready for viewing.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

try {
    // Get all completed scans for the user
    $stmt = $pdo->prepare("
        SELECT id, target_url, scan_date, scan_duration 
        FROM scans 
        WHERE user_id = :user_id AND status = 'completed'
        ORDER BY scan_date DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $reports = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Reports Page Error: " . $e->getMessage());
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Reports - PenTest Scanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reports-container {
            padding-top: 100px;
            padding-bottom: 50px;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-top: var(--spacing-xl);
        }

        .report-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .report-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.1);
        }

        .report-card::before {
            content: '📄';
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 80px;
            opacity: 0.05;
        }

        .report-card h4 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
            word-break: break-all;
        }

        .report-card .meta {
            font-size: var(--text-xs);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
        }
    </style>
</head>

<body>
    <!-- NAVIGATION -->
    <nav class="navbar scrolled" id="navbar">
        <div class="container navbar-container">
            <a href="dashboard.php" class="navbar-brand">
                <span class="logo"><i class="fas fa-shield-halved"></i></span>
                <span>PenTest Scanner</span>
            </a>

            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="scans.php" class="nav-link">Scans</a></li>
                <li><a href="all_reports.php" class="nav-link active">Reports</a></li>
                <!-- <li><a href="settings.php" class="nav-link">Settings</a></li> -->
            </ul>

            <div class="navbar-actions">
                <div class="user-menu">
                    <div class="user-avatar"
                        style="width: 32px; height: 32px; background: var(--primary); color: var(--bg-dark); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <main class="reports-container container">
        <div class="dashboard-header fade-in">
            <div class="dashboard-welcome">
                <h1>Security <span class="text-gradient">Reports</span></h1>
                <p>Access your detailed vulnerability reports and AI remediation guides.</p>
            </div>
        </div>

        <?php if (empty($reports)): ?>
            <div class="empty-state fade-in" style="margin-top: 50px;">
                <div class="icon"><i class="fas fa-folder-open"></i></div>
                <h4>No reports generated yet</h4>
                <p>Complete a scan to see your security reports here.</p>
                <a href="dashboard.php" class="btn btn-primary" style="margin-top: 20px;">Start a Scan</a>
            </div>
        <?php else: ?>
            <div class="report-grid fade-in">
                <?php foreach ($reports as $report): ?>
                    <div class="report-card">
                        <div class="badge badge-success" style="margin-bottom: 10px;">Completed</div>
                        <h4><?php echo htmlspecialchars($report['target_url']); ?></h4>
                        <div class="meta">
                            <div><i class="fas fa-calendar-days"></i>
                                <?php echo date('M j, Y - H:i', strtotime($report['scan_date'])); ?></div>
                            
                            <div><i class="fas fa-stopwatch"></i> Duration: 
                                <?php 
                                    if ($report['scan_duration']) {
                                        $total_sec = $report['scan_duration'];
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
                            </div>
                        </div>
                        <a href="report.php?id=<?php echo $report['id']; ?>" class="btn btn-primary btn-sm btn-block"
                            style="width: 100%;">View Full Report</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="assets/js/main.js"></script>
</body>

</html>