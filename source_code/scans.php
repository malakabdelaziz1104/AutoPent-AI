<?php
/**
 * ============================================
 * PENTEST SCANNER - Operation Logs (Scans)
 * ============================================
 * Course Project: Part 2 - Lesson 15
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $stmt = $pdo->prepare("DELETE FROM scans WHERE id = ? AND user_id = ?");
    $stmt->execute([$delete_id, $user_id]);
    header("Location: scans.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, target_url, scan_date, status, scan_duration, current_engine, progress 
        FROM scans 
        WHERE user_id = :user_id 
        ORDER BY scan_date DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $all_scans = $stmt->fetchAll();

    $stats = [
        'total' => count($all_scans),
        'completed' => 0,
        'failed' => 0,
        'running' => 0
    ];
    foreach ($all_scans as $s) {
        if ($s['status'] == 'completed')
            $stats['completed']++;
        elseif ($s['status'] == 'failed')
            $stats['failed']++;
        else
            $stats['running']++;
    }

} catch (PDOException $e) {
    error_log("Scans Page Error: " . $e->getMessage());
    $all_scans = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations - PenTest Scanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --card-bg: rgba(30, 41, 59, 0.7);
            --item-hover: rgba(51, 65, 85, 0.5);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top right, #0f172a, #020617);
        }

        .scans-container {
            padding-top: 120px;
            padding-bottom: 80px;
            max-width: 1100px;
        }

        /* Stats Cards */
        .stats-banner {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 24px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: rgba(255, 255, 255, 0.05);
        }

        .stat-info h4 {
            margin: 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
        }

        .stat-info .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Activity List */
        .activity-feed {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .feed-header {
            padding: 24px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feed-header h3 {
            margin: 0;
            font-size: 18px;
        }

        .scan-row {
            padding: 20px 30px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            align-items: center;
            gap: 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
        }

        .scan-row:last-child {
            border-bottom: none;
        }

        .scan-row:hover {
            background: var(--item-hover);
        }

        .target-info h5 {
            margin: 0;
            font-size: 16px;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .target-info span {
            font-size: 12px;
            color: var(--text-muted);
        }

        .status-pill {
            padding: 6px 12px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .pill-completed {
            background: rgba(0, 255, 136, 0.1);
            color: var(--success);
        }

        .pill-failed {
            background: rgba(255, 71, 87, 0.1);
            color: var(--danger);
        }

        .pill-running {
            background: rgba(0, 212, 255, 0.1);
            color: var(--info);
        }

        .pulse {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: ripple 1s infinite;
        }

        @keyframes ripple {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 212, 255, 0.4);
            }

            100% {
                box-shadow: 0 0 0 10px rgba(0, 212, 255, 0);
            }
        }

        .timestamp {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .duration {
            font-size: 13px;
            color: var(--text-muted);
            font-family: monospace;
        }

        @media (max-width: 768px) {
            .scan-row {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .scan-row>*:nth-child(4),
            .scan-row>*:nth-child(3) {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- NAVIGATION -->
    <nav class="navbar scrolled">
        <div class="container navbar-container">
            <a href="dashboard.php" class="navbar-brand">
                <span class="logo"><i class="fas fa-shield-halved"></i></span>
                <span>PenTest Scanner</span>
            </a>

            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="scans.php" class="nav-link active">Scans</a></li>
                <li><a href="all_reports.php" class="nav-link">Reports</a></li>
                <!-- <li><a href="settings.php" class="nav-link">Settings</a></li> -->
            </ul>

            <div class="navbar-actions">
                <div class="user-menu" style="margin-right: 15px;">
                    <div class="user-avatar"
                        style="width: 32px; height: 32px; background: var(--primary); color: var(--bg-dark); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <main class="scans-container container">
        <!-- Stats Banner -->
        <div class="stats-banner fade-in">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-info">
                    <h4>Total Audits</h4>
                    <div class="value"><?php echo $stats['total']; ?></div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="color: var(--success);"><i class="fas fa-circle-check"></i></div>
                <div class="stat-info">
                    <h4>Successful</h4>
                    <div class="value"><?php echo $stats['completed']; ?></div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="color: var(--info);"><i class="fas fa-rotate"></i></div>
                <div class="stat-info">
                    <h4>Running</h4>
                    <div class="value"><?php echo $stats['running']; ?></div>
                </div>
            </div>
        </div>

        <!-- Operation Feed -->
        <div class="activity-feed fade-in">
            <div class="feed-header">
                <h3>Operation History</h3>
                <div style="font-size: 12px; color: var(--text-muted);">Real-time monitoring enabled</div>
            </div>

            <?php if (empty($all_scans)): ?>
                <div style="padding: 60px; text-align: center; color: var(--text-muted);">
                    <div style="font-size: 40px; margin-bottom: 20px;"><i class="fas fa-folder-open"></i></div>
                    <p>No operation logs found in the archives.</p>
                </div>
            <?php else: ?>
                <?php foreach ($all_scans as $scan): ?>
                    <div class="scan-row">
                        <div class="target-info">
                            <h5><?php echo htmlspecialchars($scan['target_url']); ?></h5>
                            <span>ID: <?php echo $scan['id']; ?></span>
                        </div>

                        <div>
                            <?php if ($scan['status'] == 'completed'): ?>
                                <span class="status-pill pill-completed">Completed</span>
                            <?php elseif ($scan['status'] == 'failed'): ?>
                                <span class="status-pill pill-failed">Failed</span>
                            <?php else: ?>
                                <span class="status-pill pill-running"><span class="pulse"></span> Active</span>
                            <?php endif; ?>
                        </div>

                        <div class="timestamp">
                            <?php echo date('M j, Y - H:i', strtotime($scan['scan_date'])); ?>
                        </div>

                        <div class="duration">
                            <i class="fas fa-stopwatch"></i>
                            <?php 
                                if ($scan['scan_duration']) {
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
                        </div>
                        <div style="display: flex; flex-direction: row; gap: 8px; align-items:center;">
                            <?php if ($scan['status'] == 'completed'): ?>
                                <a href="report.php?id=<?php echo $scan['id']; ?>" class="btn btn-primary btn-sm">Report</a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>Wait</button>
                            <?php endif; ?>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this scan?');">
                                                <input type="hidden" name="delete_id" value="<?php echo $scan['id']; ?>">
                                                <button type="submit" class="btn btn-sm" style="background: rgba(255, 71, 87, 0.1); color: var(--danger, #ff4757); border: 1px solid var(--danger, #ff4757); cursor: pointer; padding: 6px 12px; border-radius: 6px; transition: all 0.3s ease;" onmouseover="this.style.background='var(--danger, #ff4757)'; this.style.color='#fff';" onmouseout="this.style.background='rgba(255, 71, 87, 0.1)'; this.style.color='var(--danger, #ff4757)';">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                        </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
    </main>

    <script src="assets/js/main.js"></script>
</body>

</html>