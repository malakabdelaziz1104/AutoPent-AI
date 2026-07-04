<?php
/**
 * ============================================
 * PENTEST SCANNER - Nessus Style Reporting
 * ============================================
 * Course Project: Part 2 - Lesson 15 (Finale)
 * This page aggregates scan data into a professional, 
 * Nessus-inspired exportable HTML/PDF report.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

$user_id = $_SESSION['user_id'];
$scan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$scan_id) {
    redirect('dashboard.php', 'Invalid scan ID.', 'error');
}

try {
    // 1. Get the Scan Details
    $stmt = $pdo->prepare("SELECT * FROM scans WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $scan_id, 'user_id' => $user_id]);
    $scan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scan) {
        redirect('dashboard.php', 'Scan not found or access denied.', 'error');
    }

    // 2. Fetch Findings from Base Tools (Nmap, OpenVAS, Burp) - أعمدة الجدول الأصلية فقط لمنع أي خطأ
    $stmt1 = $pdo->prepare("
    SELECT
        vulnerability_type,
        severity,
        description,
        recommendation
    FROM scan_results
    WHERE scan_id = :id
    ");

    $stmt1->execute(['id' => $scan_id]);
    $base_findings = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    
    $findings = $base_findings;

    // 4. Sort findings by Severity (Critical -> Info)
    $severity_order = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4, 'info' => 5];
    usort($findings, function($a, $b) use ($severity_order) {
        $sevA = strtolower(trim($a['severity']));
        $sevB = strtolower(trim($b['severity']));
        $orderA = $severity_order[$sevA] ?? 99;
        $orderB = $severity_order[$sevB] ?? 99;
        return $orderA <=> $orderB;
    });

    // 5. Calculate Analytics
    $severity_counts = [
        'critical' => 0,
        'high'     => 0,
        'medium'   => 0,
        'low'      => 0,
        'info'     => 0
    ];

    foreach ($findings as $f) {
        $severity = strtolower(trim($f['severity']));
        if (isset($severity_counts[$severity])) {
            $severity_counts[$severity]++;
        } else {
            $severity_counts['info']++;
        }
    }

} catch (PDOException $e) {
    error_log("Report Error: " . $e->getMessage());
    redirect('dashboard.php', 'Error generating report.', 'error');
}

// Nessus Standard Colors
$colors = [
    'critical' => '#d43f3a',
    'high'     => '#ee9336',
    'medium'   => '#fdc431',
    'low'      => '#3ea92e',
    'info'     => '#3aa6d4'
];


$engines_display = "";
if (!empty($scan['current_engine'])) {
    if (preg_match('/\((.+)\)/', $scan['current_engine'], $matches)) {
        $engines_display = $matches[1];
    } else {
        $engines_display = $scan['current_engine'];
    }
   
    $engines_display = preg_replace('/,\s*nuclei/i', '', $engines_display);
    $engines_display = preg_replace('/nuclei,\s*/i', '', $engines_display);
    $engines_display = preg_replace('/nuclei/i', '', $engines_display);
    $engines_display = trim($engines_display, ', ');
}
if (empty($engines_display)) {
    $engines_display = "Nmap, OpenVAS, Burp Suite";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vulnerability Report - <?php echo htmlspecialchars($scan['target_url']); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/style.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* =========================================
           NESSUS STYLE REPORT THEME
           ========================================= */
        body {
            background-color: #f4f6f8;
            color: #333333;
        }

        .report-wrapper {
            background: #ffffff;
            max-width: 1000px;
            margin: 100px auto 50px auto;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 40px;
            font-family: 'Open Sans', Arial, sans-serif;
            color: #222222;
        }

        .report-header {
            border-bottom: 3px solid #333333;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .report-logo h1 {
            margin: 0;
            font-size: 28px;
            color: #111111;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .navbar {
                display: none !important;
            }
            .report-wrapper {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 20px !important;
                max-width: 100% !important;
            }
            .finding-block {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            canvas {
                display: none !important;
            }
            #chartContainer img {
                display: block !important;
                max-width: 350px !important;
            }
        }

        .report-logo .subtitle {
            color: #666666;
            font-size: 14px;
            margin-top: 5px;
        }

        .report-meta-info {
            text-align: right;
            font-size: 13px;
            color: #555555;
            line-height: 1.6;
        }

        .nessus-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .nessus-table th, .nessus-table td {
            border: 1px solid #dddddd;
            padding: 10px 15px;
            text-align: left;
        }

        .nessus-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333333;
            width: 30%;
        }

        .summary-container {
            display: flex;
            gap: 40px;
            margin-bottom: 40px;
            align-items: center;
        }

        .chart-box {
            flex: 1;
            max-width: 350px;
            position: relative;
        }

        .severity-table-box {
            flex: 1;
        }

        .sev-bar {
            height: 20px;
            margin-bottom: 5px;
            border-radius: 2px;
            display: flex;
            align-items: center;
            padding: 0 10px;
            color: white;
            font-weight: bold;
            font-size: 12px;
            justify-content: space-between;
        }

        .section-title {
            font-size: 20px;
            border-bottom: 2px solid #dddddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #111111;
            margin-top: 40px;
            break-after: avoid;
            page-break-after: avoid;
        }

        .finding-block {
            border: 1px solid #dddddd;
            margin-bottom: 25px;
        }

        .finding-header {
            padding: 12px 15px;
            color: #ffffff;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
        }

        .finding-header.sev-critical { background-color: <?php echo $colors['critical']; ?>; }
        .finding-header.sev-high { background-color: <?php echo $colors['high']; ?>; }
        .finding-header.sev-medium { background-color: <?php echo $colors['medium']; ?>; color: #222222; }
        .finding-header.sev-low { background-color: <?php echo $colors['low']; ?>; }
        .finding-header.sev-info { background-color: <?php echo $colors['info']; ?>; }

        .finding-content {
            padding: 20px;
            background-color: #fcfcfc;
        }

        .finding-row {
            margin-bottom: 15px;
        }

        .finding-row h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333333;
            text-transform: uppercase;
        }

        .finding-row p {
            margin: 0;
            font-size: 14px;
            color: #555555;
            line-height: 1.5;
            background: #ffffff;
            padding: 10px;
            border: 1px solid #eeeeee;
            border-left: 3px solid #cccccc;
        }

        .risk-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
    </style>
</head>
<body>

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
            </ul>
            
            <div class="navbar-actions">
                <button id="downloadPdfBtn" class="btn btn-primary btn-sm">
                    <span><i class="fas fa-file-pdf"></i> Download PDF Report</span>
                </button>
            </div>
        </div>
    </nav>

    <div id="reportContent" class="report-wrapper">
        
        <div class="report-header">
            <div class="report-logo">
                <h1>Vulnerability Report</h1>
                <div class="subtitle">Generated by PenTest Scanner</div>
            </div>
            <div class="report-meta-info">
                <strong>Report Date:</strong> <?php echo date('F j, Y, g:i a'); ?><br>
                <strong>Scan ID:</strong> V-<?php echo str_pad($scan['id'], 5, '0', STR_PAD_LEFT); ?>
            </div>
        </div>

        <table class="nessus-table">
            <tbody>
                <tr>
                    <th>Target Information</th>
                    <td><strong><?php echo htmlspecialchars($scan['target_url']); ?></strong></td>
                </tr>
                <tr>
                    <th>Scan Start Date</th>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($scan['scan_date'])); ?></td>
                </tr>
                <tr>
                    <th>Scan Duration</th>
                    <td><?php 
                        if ($scan['scan_duration']) {
                            $secs = (int)$scan['scan_duration'];
                            $h = floor($secs / 3600);
                            $m = floor(($secs % 3600) / 60);
                            $s = $secs % 60;
                            $parts = [];
                            if ($h > 0) $parts[] = $h . 'h';
                            if ($m > 0) $parts[] = $m . 'm';
                            $parts[] = $s . 's';
                            echo implode(' ', $parts);
                        } else {
                            echo 'N/A';
                        }
                    ?></td>
                </tr>
                <tr>
                    <th>Scanner Engines</th>
                    <td><?php echo htmlspecialchars($engines_display); ?></td>
                </tr>
            </tbody>
        </table>
        

        <div class="section-title">Vulnerabilities by Severity</div>

        <div class="summary-container">
            <div class="chart-box" id="chartContainer">
                <?php if (count($findings) > 0): ?>
                    <canvas id="severityChart"></canvas>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: <?php echo $colors['low']; ?>; border: 2px dashed <?php echo $colors['low']; ?>;">
                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 10px;"></i>
                        <h3>No Vulnerabilities Found</h3>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="severity-table-box">
                <table class="nessus-table" style="margin-bottom: 0;">
                    <tbody>
                        <tr>
                            <td style="width: 70%; padding: 0; border: none;">
                                <div class="sev-bar" style="background-color: <?php echo $colors['critical']; ?>; width: 100%;">
                                    <span>Critical</span>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: bold; border: none; font-size: 16px; width: 30%;">
                                <?php echo $severity_counts['critical']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0; border: none;">
                                <div class="sev-bar" style="background-color: <?php echo $colors['high']; ?>; width: 100%;">
                                    <span>High</span>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: bold; border: none; font-size: 16px;">
                                <?php echo $severity_counts['high']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0; border: none;">
                                <div class="sev-bar" style="background-color: <?php echo $colors['medium']; ?>; color: #222; width: 100%;">
                                    <span>Medium</span>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: bold; border: none; font-size: 16px;">
                                <?php echo $severity_counts['medium']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0; border: none;">
                                <div class="sev-bar" style="background-color: <?php echo $colors['low']; ?>; width: 100%;">
                                    <span>Low</span>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: bold; border: none; font-size: 16px;">
                                <?php echo $severity_counts['low']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0; border: none;">
                                <div class="sev-bar" style="background-color: <?php echo $colors['info']; ?>; width: 100%;">
                                    <span>Info</span>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: bold; border: none; font-size: 16px;">
                                <?php echo $severity_counts['info']; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section-title" style="margin-top: 60px;">Vulnerability Details</div>

        <?php if (empty($findings)): ?>
            <p style="color: #555;">The target appears to be secure against the tested vectors. No specific remediation actions are required at this time.</p>
        <?php else: ?>
            <?php foreach ($findings as $index => $finding): 
                $sev = strtolower(trim($finding['severity']));
                if (!array_key_exists($sev, $colors)) $sev = 'info';
                $sevClass = 'sev-' . $sev;
                $sevColor = $colors[$sev];
            ?>
                <div class="finding-block">
                    <div class="finding-header <?php echo $sevClass; ?>">
                        <span><?php echo htmlspecialchars($finding['vulnerability_type']); ?></span>
                        <span style="text-transform: uppercase; font-size: 12px; opacity: 0.9;">
                            ID: <?php echo $index + 1; ?>
                        </span>
                    </div>
                    <div class="finding-content">
                        
                        <div class="finding-row">
                            <h4>Risk Factor</h4>
                            <span class="risk-badge" style="background-color: <?php echo $sevColor; ?>; <?php echo $sev == 'medium' ? 'color: #000;' : ''; ?>">
                                <?php echo ucfirst($sev); ?>
                            </span>
                        </div>

                        <div class="finding-row">
                            <h4>Description</h4>
                            <p><?php echo nl2br(htmlspecialchars($finding['description'] ?? 'No description provided.')); ?></p>
                        </div>

                        <div class="finding-row">
                            <h4>Remediation / Solution</h4>
                            <p><?php echo nl2br(htmlspecialchars($finding['recommendation'] ?? 'No remediation steps generated.')); ?></p>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartCanvas = document.getElementById('severityChart');
            if(chartCanvas) {
                const ctx = chartCanvas.getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Critical', 'High', 'Medium', 'Low', 'Info'],
                        datasets: [{
                            data: [
                                <?php echo $severity_counts['critical']; ?>,
                                <?php echo $severity_counts['high']; ?>,
                                <?php echo $severity_counts['medium']; ?>,
                                <?php echo $severity_counts['low']; ?>,
                                <?php echo $severity_counts['info']; ?>
                            ],
                            backgroundColor: [
                                '<?php echo $colors['critical']; ?>',
                                '<?php echo $colors['high']; ?>',
                                '<?php echo $colors['medium']; ?>',
                                '<?php echo $colors['low']; ?>',
                                '<?php echo $colors['info']; ?>'
                            ],
                            borderWidth: 1,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    font: { family: "'Open Sans', Arial, sans-serif", size: 12 },
                                    color: '#333333'
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });
            }

            

            // PDF Generation
            document.getElementById('downloadPdfBtn').addEventListener('click', function() {
                const element = document.getElementById('reportContent');
                const chartContainer = document.getElementById('chartContainer');
                const originalText = this.innerHTML;
                
                this.innerHTML = '<span><i class="fas fa-spinner fa-spin"></i> Preparing PDF...</span>';
                this.disabled = true;

                // Convert chart canvas to image before printing
                if(chartCanvas) {
                    const imgEl = new Image();
                    imgEl.src = chartCanvas.toDataURL("image/png", 1.0);
                    imgEl.style.width = '100%';
                    imgEl.style.maxWidth = '350px';
                    imgEl.id = 'chartImage';
                    chartCanvas.style.display = 'none';
                    chartContainer.appendChild(imgEl);
                }

                setTimeout(() => {
                    window.print();

                    // Restore after print dialog closes
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        if(chartCanvas) {
                            const imgEl = document.getElementById('chartImage');
                            if(imgEl) imgEl.remove();
                            chartCanvas.style.display = 'block';
                        }
                    }, 1000);
                }, 300); 
            });
        });
    </script>
</body>
</html>

