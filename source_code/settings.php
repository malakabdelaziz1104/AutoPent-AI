<?php
/**
 * ============================================
 * PENTEST SCANNER - System Settings & Diagnostics
 * ============================================
 * Course Project: Part 2 - Lesson 15 (Final)
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];

$flash = getFlashMessage();

// --- TOOL DIAGNOSTICS ---
function checkTool($command) {
    $output = [];
    $return_var = -1;
    $cmd = "where " . escapeshellarg($command);
    exec($cmd, $output, $return_var);
    
    if ($return_var === 0 && !empty($output)) {
        return ['status' => 'installed', 'path' => $output[0], 'icon' => 'fa-circle-check', 'color' => 'var(--success)'];
    } else {
        return ['status' => 'not found', 'path' => 'Path not found in environment', 'icon' => 'fa-circle-xmark', 'color' => 'var(--danger)'];
    }
}

function checkOllama() {
    $url = "http://127.0.0.1:11434/api/tags";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        $models = isset($data['models']) ? array_column($data['models'], 'name') : [];
        return ['status' => 'active', 'path' => 'http://127.0.0.1:11434', 'icon' => 'fa-microchip', 'color' => 'var(--info)', 'models' => $models];
    } else {
        return ['status' => 'offline', 'path' => 'Ensure Ollama is running on port 11434', 'icon' => 'fa-triangle-exclamation', 'color' => 'var(--warning)', 'models' => []];
    }
}

// Check OpenVAS via real GMP connection
function checkOpenVAS() {
    require_once 'includes/openvas_wrapper.php';
    $scanner = new OpenVASScanner();
    $version = $scanner->getVersion();
    
    if ($version) {
        return [
            'status' => "connected (v$version)",
            'path' => OPENVAS_HOST . ':' . OPENVAS_GMP_PORT . ' (GMP over TCP)',
            'icon' => 'fa-server',
            'color' => 'var(--success)'
        ];
    } else {
        return [
            'status' => 'offline',
            'path' => 'Run: docker-compose up -d',
            'icon' => 'fa-server',
            'color' => 'var(--danger)'
        ];
    }
}

$ollama_data = checkOllama();
$diagnostics = [
    'Nmap' => checkTool('nmap'),
    'Ollama' => $ollama_data,
    'OpenVAS (GVM)' => checkOpenVAS()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PenTest Scanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: radial-gradient(circle at top right, #0f172a, #020617); }
        .settings-container { padding-top: 120px; padding-bottom: 80px; max-width: 1000px; }
        .settings-grid { display: grid; grid-template-columns: 250px 1fr; gap: 30px; }
        .settings-nav { background: rgba(30, 41, 59, 0.7); border: 1px solid var(--border-color); border-radius: 20px; padding: 20px; height: fit-content; position: sticky; top: 100px; }
        .settings-nav-item { padding: 12px 15px; border-radius: 12px; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px; margin-bottom: 8px; border: 1px solid transparent; }
        .settings-nav-item.active { background: var(--primary); color: var(--bg-dark); font-weight: 600; }
        .settings-nav-item:hover:not(.active) { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
        .settings-content { background: rgba(30, 41, 59, 0.7); border: 1px solid var(--border-color); border-radius: 24px; padding: 40px; backdrop-filter: blur(10px); min-height: 500px; }
        .diagnostic-card { background: rgba(15, 23, 42, 0.5); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; margin-bottom: 15px; display: flex; align-items: center; gap: 20px; }
        .diag-icon { font-size: 20px; width: 45px; height: 45px; background: rgba(255,255,255,0.05); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .form-section { display: none; animation: fadeIn 0.3s ease; }
        .form-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .danger-zone { border: 1px solid rgba(255, 71, 87, 0.3); border-radius: 16px; padding: 24px; background: rgba(255, 71, 87, 0.03); }
        .model-pill { display: inline-block; padding: 4px 12px; background: rgba(0, 212, 255, 0.1); border: 1px solid var(--primary); border-radius: 8px; font-size: 12px; color: var(--primary); margin-right: 8px; margin-top: 8px; }
    </style>
</head>
<body>
    <nav class="navbar scrolled">
        <div class="container navbar-container">
            <a href="dashboard.php" class="navbar-brand">
                <span class="logo"><i class="fas fa-shield-halved"></i></span>
                <span>PenTest Scanner</span>
            </a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="scans.php" class="nav-link">Scans</a></li>
                <li><a href="all_reports.php" class="nav-link">Reports</a></li>
                <li><a href="settings.php" class="nav-link active">Settings</a></li>
            </ul>
        </div>
    </nav>

    <main class="settings-container container">
        <div class="dashboard-header fade-in">
            <h1>Identity & <span class="text-gradient">Control</span></h1>
            <p>Configure your environment and manage your security profile.</p>
        </div>

        <div class="settings-grid fade-in">
            <div class="settings-nav">
                <div class="settings-nav-item active" onclick="showTab('health')"><i class="fas fa-microchip"></i> System Health</div>
                <div class="settings-nav-item" onclick="showTab('profile')"><i class="fas fa-user-gear"></i> Profile Settings</div>
                <div class="settings-nav-item" onclick="showTab('ai')"><i class="fas fa-brain"></i> AI Configuration</div>
                <div class="settings-nav-item" onclick="showTab('security')"><i class="fas fa-shield-halved"></i> Security</div>
            </div>

            <div class="settings-content">
                <!-- Health Tab -->
                <div id="health" class="form-section active">
                    <h3 style="margin-bottom: 20px;"><i class="fas fa-stethoscope"></i> Tool Diagnostics</h3>
                    <?php foreach($diagnostics as $name => $data): ?>
                    <div class="diagnostic-card">
                        <div class="diag-icon" style="color: <?php echo $data['color']; ?>">
                            <i class="fas <?php echo $data['icon']; ?>"></i>
                        </div>
                        <div class="diag-info">
                            <h4 style="margin:0;"><?php echo $name; ?></h4>
                            <p style="margin:5px 0 0 0; font-size:11px; color:var(--text-muted); font-family:monospace;"><?php echo $data['path']; ?></p>
                        </div>
                        <div style="margin-left:auto; font-size:10px; font-weight:700; text-transform:uppercase; color:<?php echo $data['color']; ?>;"><?php echo $data['status']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Profile Tab -->
                <div id="profile" class="form-section">
                    <h3 style="margin-bottom: 20px;"><i class="fas fa-user-circle"></i> Profile Management</h3>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($username); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($email); ?>" readonly>
                    </div>
                    
                    <div class="danger-zone" style="margin-top: 40px;">
                        <h4 style="color: var(--danger); margin-top: 0;"><i class="fas fa-triangle-exclamation"></i> Danger Zone</h4>
                        <p style="font-size: 13px; color: var(--text-secondary);">Resetting your profile will permanently delete all scan records and reports.</p>
                        <button class="btn btn-secondary btn-sm" style="background: rgba(255, 71, 87, 0.1); border-color: var(--danger); color: var(--danger);" onclick="confirmReset()">Reset Scan History</button>
                    </div>
                </div>

                <!-- AI Tab -->
                <div id="ai" class="form-section">
                    <h3 style="margin-bottom: 20px;"><i class="fas fa-robot"></i> AI Configuration</h3>
                    <div class="diagnostic-card">
                        <div class="diag-icon" style="color: var(--info);"><i class="fas fa-microchip"></i></div>
                        <div class="diag-info">
                            <h4>Local Engine (Ollama)</h4>
                            <p>Status: <?php echo $ollama_data['status']; ?></p>
                        </div>
                    </div>
                    
                    <h4 style="font-size: 14px; margin-top: 30px;">Available Models</h4>
                    <div class="model-list">
                        <?php if (empty($ollama_data['models'])): ?>
                            <p style="font-size: 13px; color: var(--text-muted);">No models found. Run <code>ollama pull llama2</code> to get started.</p>
                        <?php else: ?>
                            <?php foreach($ollama_data['models'] as $model): ?>
                                <span class="model-pill"><?php echo $model; ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Security Tab -->
                <div id="security" class="form-section">
                    <h3 style="margin-bottom: 20px;"><i class="fas fa-lock"></i> Security Settings</h3>
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-input" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-input" placeholder="••••••••">
                    </div>
                    <button class="btn btn-primary btn-sm">Update Password</button>
                    
                    <hr style="border: none; border-top: 1px solid var(--border-color); margin: 30px 0;">
                    <h4 style="font-size: 15px;">Session Activity</h4>
                    <p style="font-size: 13px; color: var(--text-secondary);">Last Login: <?php echo date('Y-m-d H:i'); ?> (Current Session)</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.form-section').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-nav-item').forEach(n => n.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function confirmReset() {
            if (confirm("WARNING: This will permanently delete your entire scan history. This cannot be undone. Proceed?")) {
                alert("This function would now execute a TRUNCATE query on the scans table for your user ID.");
            }
        }
    </script>
</body>
</html>
