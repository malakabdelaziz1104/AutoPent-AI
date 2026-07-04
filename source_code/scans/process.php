<?php
/**
 * ============================================
 * PENTEST SCANNER - Process Scan Workflow
 * ============================================
 * Parallel scanning support (Windows/WSL/Linux)
 * Uses temp files + START /B on Windows for true parallel execution
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/nmap_wrapper.php';
require_once '../includes/openvas_wrapper.php';
require_once '../includes/burp_wrapper.php';
require_once '../includes/ai_wrapper.php';

ignore_user_abort(true);
set_time_limit(0);

startSession();
requireLogin();

if (!isPost()) {
    redirect('../dashboard.php', 'Invalid request method.', 'error');
}

$user_id    = $_SESSION['user_id'];
$target_url = isset($_POST['target_url']) ? trim($_POST['target_url']) : '';

// ============================================
// 1. DETERMINE SELECTED ENGINES & OPTIONS
// ============================================
$is_deep_scan = isset($_POST['deep_scan']) && $_POST['deep_scan'] == '1';
$is_web_scan  = isset($_POST['web_scan'])  && $_POST['web_scan']  == '1';

$nmap_aggressive  = isset($_POST['nmap_aggressive'])     && $_POST['nmap_aggressive']     == '1';
$nmap_full        = isset($_POST['nmap_full'])           && $_POST['nmap_full']           == '1';
$nmap_scripts     = isset($_POST['nmap_scripts'])        && $_POST['nmap_scripts']        == '1';
$nmap_firewall    = isset($_POST['firewall_detection'])  && $_POST['firewall_detection']  == '1';
$nmap_credentials = isset($_POST['default_credentials']) && $_POST['default_credentials'] == '1';

$is_nmap_scan = (isset($_POST['nmap_scan']) && $_POST['nmap_scan'] == '1')
    || $nmap_aggressive || $nmap_full || $nmap_scripts || $nmap_firewall || $nmap_credentials;

$nmap_options = [
    'aggressive'          => $nmap_aggressive,
    'full_port'           => $nmap_full,
    'scripts'             => $nmap_scripts,
    'firewall_detection'  => $nmap_firewall,
    'default_credentials' => $nmap_credentials,
];

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// ============================================
// 2. VALIDATION
// ============================================
if (empty($target_url)) {
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>'Please enter a target URL.']); exit; }
    redirect('../dashboard.php', 'Please enter a target URL.', 'error');
}

if (!filter_var((strpos($target_url,'http')===0 ? $target_url : 'http://'.$target_url), FILTER_VALIDATE_URL)) {
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>'Invalid URL format.']); exit; }
    redirect('../dashboard.php', 'Invalid URL format.', 'error');
}

if (!$is_deep_scan && !$is_web_scan && !$is_nmap_scan) {
    $error_message = 'No tool selected! Please select at least one scanning tool.';
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>$error_message]); exit; }
    redirect('../dashboard.php', $error_message, 'error');
}

// ============================================
// 3. TOOL AVAILABILITY VALIDATION
// ============================================
ini_set('display_errors', 0);

function isToolInstalled($tool) {
    $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $cmd = $is_windows ? "where ".escapeshellarg($tool)." 2>nul" : "command -v ".escapeshellarg($tool)." 2>/dev/null";
    return !empty(trim((string)@shell_exec($cmd)));
}

function isServiceRunning($host, $port) {
    $c = @fsockopen($host, $port, $errno, $errstr, 1);
    if (is_resource($c)) { fclose($c); return true; }
    return false;
}

function isWindows() {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

$tool_errors = [];
if ($is_nmap_scan && !isToolInstalled('nmap')) {
    $tool_errors[] = "Nmap is not installed or not in system PATH.";
}
if ($is_deep_scan && !isServiceRunning('127.0.0.1', 9390) && !isToolInstalled('gvm-cli')) {
    $tool_errors[] = "OpenVAS engine is offline. Please run docker.";
}
if ($is_web_scan && !isServiceRunning('127.0.0.1', 8888) && !isServiceRunning('127.0.0.1', 1337)) {
    $tool_errors[] = "Web Scanner API/Proxy is not running. Please check the configured port.";
}

if (!empty($tool_errors)) {
    $error_message = implode(" | ", $tool_errors);
    if ($is_ajax) { if(ob_get_length()) ob_clean(); header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>$error_message]); exit; }
    redirect('../dashboard.php', $error_message, 'error');
}

// ============================================
// HELPERS: parallel execution via temp files
// ============================================

/**
 * Launch a PHP runner script in the background.
 * On Windows: uses "start /B" (true background, no console window)
 * On Linux:   uses "&" operator
 * 
 * The runner writes its JSON output to $out_file
 * and writes "1" to $done_file when finished.
 */
function launchParallel($runner_script, $out_file, $done_file) {
    // Hardcode full path because PHP is not in Windows PATH
    $php = file_exists("C:\\xampp\\php\\php.exe") ? "C:\\xampp\\php\\php.exe" : PHP_BINARY;

    if (isWindows()) {
        // start /B launches detached from current process — true parallel on Windows
        $cmd = 'start /B "" '
             . escapeshellarg($php) . ' '
             . escapeshellarg($runner_script)
             . ' > NUL 2>&1';
        pclose(popen($cmd, 'r'));
    } else {
        $cmd = escapeshellarg($php) . ' '
             . escapeshellarg($runner_script)
             . ' > /dev/null 2>&1 &';
        shell_exec($cmd);
    }
}

/** Check if a background job has finished */
function jobDone($done_file) {
    clearstatcache(true, $done_file);
    return file_exists($done_file);
}

/** Read output file and delete it */
function readOutput($out_file) {
    if (!file_exists($out_file)) return null;
    $data = file_get_contents($out_file);
    @unlink($out_file);
    return $data;
}

try {
    // ============================================
    // 4. CREATE SCAN RECORD IN DATABASE
    // ============================================
    $stmt = $pdo->prepare("
        INSERT INTO scans (user_id, target_url, status, current_engine, progress, pid)
        VALUES (:user_id, :target_url, 'in_progress', 'Initializing...', 0, :pid)
    ");
    $stmt->execute(['user_id' => $user_id, 'target_url' => $target_url, 'pid' => getmypid()]);
    $scan_id = $pdo->lastInsertId();

    // Return scan_id to browser immediately (AJAX)
    if ($is_ajax) {
        session_write_close();
        $json_response = json_encode(['success'=>true,'scan_id'=>$scan_id,'message'=>'Scan initiated.']);
        header('Content-Type: application/json');
        header('Connection: close');
        header('Content-Length: ' . strlen($json_response));
        if (ob_get_level() > 0) ob_end_clean();
        ob_start();
        echo $json_response;
        ob_end_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        ini_set('display_errors', 0);
        ob_start();
    }

    $start_time   = microtime(true);
    $engines_used = [];
    $update = $pdo->prepare("UPDATE scans SET current_engine = :engine, progress = :p WHERE id = :id");

    // ============================================
    // 5. BUILD RUNNER SCRIPTS IN TEMP DIR
    // Each runner: bootstraps DB, runs scan, writes
    // JSON to $out_file, writes "1" to $done_file
    // ============================================
    $tmp         = sys_get_temp_dir();
    $runner_dir  = $tmp . DIRECTORY_SEPARATOR . 'pentest_' . $scan_id;
    @mkdir($runner_dir, 0755, true);

    $inc         = realpath(__DIR__ . '/../includes');
    $config_path = $inc . DIRECTORY_SEPARATOR . 'config.php';

    // DB credentials to pass into runners
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $db_json = json_encode(['dsn' => $dsn, 'user' => DB_USER, 'pass' => DB_PASS]);

    // Nmap options
    $nmap_json = json_encode($nmap_options);

    // ---- File paths ----
    $nmap_runner    = $runner_dir . DIRECTORY_SEPARATOR . 'run_nmap.php';
    $nmap_out       = $runner_dir . DIRECTORY_SEPARATOR . 'nmap_out.json';
    $nmap_done      = $runner_dir . DIRECTORY_SEPARATOR . 'nmap.done';

    $openvas_runner = $runner_dir . DIRECTORY_SEPARATOR . 'run_openvas.php';
    $openvas_out    = $runner_dir . DIRECTORY_SEPARATOR . 'openvas_out.json';
    $openvas_done   = $runner_dir . DIRECTORY_SEPARATOR . 'openvas.done';
    $openvas_status = $runner_dir . DIRECTORY_SEPARATOR . 'openvas_status.json';   // <-- جديد

    $burp_runner    = $runner_dir . DIRECTORY_SEPARATOR . 'run_burp.php';
    $burp_out       = $runner_dir . DIRECTORY_SEPARATOR . 'burp_out.json';
    $burp_done      = $runner_dir . DIRECTORY_SEPARATOR . 'burp.done';
    // Burp writes its live progress/status here instead of touching the
    // `scans` DB row directly, so it never races with the main process's
    // own progress writes below (that race was the cause of the progress
    // bar jumping up and down).
    $burp_status    = $runner_dir . DIRECTORY_SEPARATOR . 'burp_status.json';

    // ---- Write Nmap runner ----
    $nmap_runner_code = '<?php' . "\n"
        . 'require_once ' . var_export($config_path, true) . ";\n"
        . 'require_once ' . var_export($inc . DIRECTORY_SEPARATOR . 'nmap_wrapper.php', true) . ";\n"
        . '$db   = json_decode(' . var_export($db_json, true) . ', true);' . "\n"
        . '$pdo  = new PDO($db["dsn"], $db["user"], $db["pass"], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);' . "\n"
        . '$opts = json_decode(' . var_export($nmap_json, true) . ', true);' . "\n"
        . '$t    = ' . var_export($target_url, true) . ";\n"
        . '$out  = ' . var_export($nmap_out, true) . ";\n"
        . '$done = ' . var_export($nmap_done, true) . ";\n"
        . '$scanner = new NmapScanner();' . "\n"
        . '$result  = $scanner->scanTarget($t, $opts);' . "\n"
        . 'file_put_contents($out, json_encode($result));' . "\n"
        . 'file_put_contents($done, "1");' . "\n";
    file_put_contents($nmap_runner, $nmap_runner_code);

    // ---- Write OpenVAS runner ----
    $openvas_runner_code = '<?php' . "\n"
        . 'require_once ' . var_export($config_path, true) . ";\n"
        . 'require_once ' . var_export($inc . DIRECTORY_SEPARATOR . 'openvas_wrapper.php', true) . ";\n"
        . '$db   = json_decode(' . var_export($db_json, true) . ', true);' . "\n"
        . '$pdo  = new PDO($db["dsn"], $db["user"], $db["pass"], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);' . "\n"
        . '$t    = ' . var_export($target_url, true) . ";\n"
        . '$sid  = ' . (int)$scan_id . ";\n"
        . '$out  = ' . var_export($openvas_out, true) . ";\n"
        . '$done = ' . var_export($openvas_done, true) . ";\n"
        . '$status_file = ' . var_export($openvas_status, true) . ";\n"
        . '$scanner = new OpenVASScanner();' . "\n"
        // FIX: $status_file is now actually passed through to the wrapper
        // so OpenVAS can report fine-grained progress (e.g. "OpenVAS Scanning (X%)")
        // the same way Burp already does via $burp_status.
        . '$result  = $scanner->performVulnerabilityScan($t, $sid, $pdo, $status_file);' . "\n"
        . 'file_put_contents($out, json_encode($result));' . "\n"
        . 'file_put_contents($done, "1");' . "\n";
    file_put_contents($openvas_runner, $openvas_runner_code);

    // ---- Write Burp runner ----
    $burp_runner_code = '<?php' . "\n"
        . 'require_once ' . var_export($config_path, true) . ";\n"
        . 'require_once ' . var_export($inc . DIRECTORY_SEPARATOR . 'burp_wrapper.php', true) . ";\n"
        . '$db   = json_decode(' . var_export($db_json, true) . ', true);' . "\n"
        . '$pdo  = new PDO($db["dsn"], $db["user"], $db["pass"], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);' . "\n"
        . '$t    = ' . var_export($target_url, true) . ";\n"
        . '$sid  = ' . (int)$scan_id . ";\n"
        . '$out  = ' . var_export($burp_out, true) . ";\n"
        . '$done = ' . var_export($burp_done, true) . ";\n"
        . '$status_file = ' . var_export($burp_status, true) . ";\n"
        . '$scanner = new BurpSuiteScanner();' . "\n"
        . '$result  = $scanner->performWebScan($t, $sid, $pdo, $status_file);' . "\n"
        . 'file_put_contents($out, json_encode($result));' . "\n"
        . 'file_put_contents($done, "1");' . "\n";
    file_put_contents($burp_runner, $burp_runner_code);

    // ============================================
    // 6. LAUNCH SCANNERS IN PARALLEL
    // ============================================
    $update->execute(['engine' => 'Starting parallel scanners...', 'p' => 5, 'id' => $scan_id]);

    $jobs = []; // ['name' => [...paths]]

    if ($is_nmap_scan) {
        launchParallel($nmap_runner, $nmap_out, $nmap_done);
        $jobs['nmap'] = ['out' => $nmap_out, 'done' => $nmap_done, 'runner' => $nmap_runner];
    }
    if ($is_deep_scan) {
        launchParallel($openvas_runner, $openvas_out, $openvas_done);
        $jobs['openvas'] = ['out' => $openvas_out, 'done' => $openvas_done, 'runner' => $openvas_runner];
    }
    if ($is_web_scan) {
        launchParallel($burp_runner, $burp_out, $burp_done);
        $jobs['burp'] = ['out' => $burp_out, 'done' => $burp_done, 'runner' => $burp_runner];
    }

    // ============================================
    // 7. POLL UNTIL ALL JOBS FINISH
    // ============================================
    $total     = count($jobs);
    $finished  = [];
    $progress  = 10;
    $max_wait  = 7200; // 2 hours max
    $waited    = 0;

    $update->execute([
        'engine' => 'Running: ' . implode(' + ', array_keys($jobs)) . '...',
        'p'      => $progress,
        'id'     => $scan_id,
    ]);

    while (count($finished) < $total && $waited < $max_wait) {
        foreach ($jobs as $name => $paths) {
            if (!isset($finished[$name]) && jobDone($paths['done'])) {
                $finished[$name] = true;
            }
        }

        $done_count   = count($finished);
        // Base progress: how many of the parallel jobs have fully finished.
        // This alone only moves in big steps (e.g. 10 -> 36 -> 62 -> ...),
        // which is why we layer Burp's fine-grained status on top below.
        $new_progress = 10 + (int)(($done_count / max($total, 1)) * 65);

        // Read Burp's own progress file, if this scan includes a web scan
        // and Burp hasn't finished yet. Burp reports its own 65-85% range
        // (crawling/auditing), which is finer-grained than "job done or not".
        // We only ever take the MAX of the two so progress never goes
        // backwards, no matter what either side computes.
        $burp_status_text = null;
        if (isset($jobs['burp']) && !isset($finished['burp'])) {
            $bs = @file_get_contents($burp_status);
            if ($bs !== false) {
                $bs_data = json_decode($bs, true);
                if (is_array($bs_data) && isset($bs_data['progress'])) {
                    $new_progress = max($new_progress, (int)$bs_data['progress']);
                    $burp_status_text = $bs_data['status'] ?? null;
                }
            }
        }
        $openvas_status_text = null;
        if (isset($jobs['openvas']) && !isset($finished['openvas'])) {
            $os = @file_get_contents($openvas_status);
            if ($os !== false) {
                $os_data = json_decode($os, true);
                if (is_array($os_data) && isset($os_data['progress'])) {
                    $new_progress = max($new_progress, (int)$os_data['progress']);
                    $openvas_status_text = $os_data['status'] ?? null;
                }
            }
        }
        if ($new_progress > $progress) $progress = $new_progress;

        $still = array_diff(array_keys($jobs), array_keys($finished));

        $detail_msgs = [];
        if ($burp_status_text && in_array('burp', $still, true)) {
            $detail_msgs[] = $burp_status_text;
        }
        if ($openvas_status_text && in_array('openvas', $still, true)) {
            $detail_msgs[] = $openvas_status_text;
        }

        $plain_still = array_filter($still, function($j) use ($burp_status_text, $openvas_status_text) {
            if ($j === 'burp' && $burp_status_text) return false;
            if ($j === 'openvas' && $openvas_status_text) return false;
            return true;
        });

        if (!empty($detail_msgs)) {
            $engine_msg = !empty($plain_still)
                ? 'Running: ' . implode(', ', $plain_still) . ' | ' . implode(' | ', $detail_msgs)
                : implode(' | ', $detail_msgs);
        } else {
            $engine_msg = empty($still)
                ? 'All scanners done, processing...'
                : 'Running: ' . implode(', ', $still);
        }

        $update->execute(['engine' => $engine_msg, 'p' => $progress, 'id' => $scan_id]);

        if (count($finished) < $total) {
            sleep(3);
            $waited += 3;
        }
    }

    // ============================================
    // 8. READ RESULTS & INSERT INTO DB
    // ============================================
    $update->execute(['engine' => 'Processing results...', 'p' => 78, 'id' => $scan_id]);

    $insertFinding = $pdo->prepare("
        INSERT INTO scan_results (scan_id, vulnerability_type, severity, description, recommendation)
        VALUES (:scan_id, :type, :severity, :description, :recommendation)
    ");

    // --- Nmap ---
    if (isset($jobs['nmap'])) {
        $raw = readOutput($jobs['nmap']['out']);
        @unlink($jobs['nmap']['done']);
        @unlink($jobs['nmap']['runner']);

        $nmap_results = $raw ? (json_decode($raw, true) ?? []) : [];

        if (!empty($nmap_results['findings'])) {
            $engines_used[] = 'Nmap';
            foreach ($nmap_results['findings'] as $f) {
                if ($f['type'] === 'Firewall Detected') {
                    $rec = "A firewall is filtering Port {$f['raw_port']}. Ensure firewall rules are properly configured.";
                } elseif ($f['type'] === 'Default Credentials Found') {
                    $rec = "CRITICAL: Default credentials found on Port {$f['raw_port']} ({$f['service']}). Change immediately.";
                } elseif ($f['type'] === 'Anonymous Access Allowed') {
                    $rec = "Anonymous access enabled on Port {$f['raw_port']} ({$f['service']}). Disable immediately.";
                } else {
                    $rec = "Review open port {$f['raw_port']}. Ensure service ({$f['service']}) is secure. Close via firewall if not needed.";
                }
                $insertFinding->execute([
                    'scan_id'        => $scan_id,
                    'type'           => $f['type'],
                    'severity'       => $f['severity'],
                    'description'    => $f['description'],
                    'recommendation' => $rec,
                ]);
            }
        }
    }

    // --- OpenVAS ---
    if (isset($jobs['openvas'])) {
        $raw = readOutput($jobs['openvas']['out']);
        @unlink($jobs['openvas']['done']);
        @unlink($jobs['openvas']['runner']);

        $vas_results = $raw ? (json_decode($raw, true) ?? []) : [];

        if (!empty($vas_results['findings'])) {
    $engines_used[] = 'OpenVAS';
    foreach ($vas_results['findings'] as $f) {
        $insertFinding->execute([
            'scan_id'        => $scan_id,
            'type'           => $f['type'],
            'severity'       => $f['severity'],
            'description'    => $f['description'],
            'recommendation' => $f['recommendation'],
        ]);
    }
} elseif (!empty($vas_results['error'])) {
    $engines_used[] = 'OpenVAS (FAILED: ' . $vas_results['error'] . ')';
    error_log("OpenVAS scan #$scan_id failed: " . $vas_results['error']);
} elseif (isset($vas_results['mode']) && $vas_results['mode'] === 'real_incomplete') {
    $engines_used[] = 'OpenVAS (INCOMPLETE - timed out)';
} else {
    $engines_used[] = 'OpenVAS';
}
    }

    // --- Burp ---
    if (isset($jobs['burp'])) {
        $raw = readOutput($jobs['burp']['out']);
        @unlink($jobs['burp']['done']);
        @unlink($jobs['burp']['runner']);

        $burp_results = $raw ? (json_decode($raw, true) ?? []) : [];

        if (!empty($burp_results['findings'])) {
            $engines_used[] = 'Web Scanner';
            foreach ($burp_results['findings'] as $f) {
                $insertFinding->execute([
                    'scan_id'        => $scan_id,
                    'type'           => $f['type'],
                    'severity'       => $f['severity'],
                    'description'    => $f['description'],
                    'recommendation' => $f['recommendation'],
                ]);
            }
        }
    }

    // Cleanup runner dir
    @rmdir($runner_dir);

    // ============================================
    // 9. AI CLASSIFICATION & RAG
    // ============================================
    if (defined('ENABLE_AI_RECOMMENDATIONS') && ENABLE_AI_RECOMMENDATIONS) {
        $update->execute(['engine' => 'AI Classification (Ollama)', 'p' => 85, 'id' => $scan_id]);

        $stmt = $pdo->prepare("SELECT * FROM scan_results WHERE scan_id = :id");
        $stmt->execute(['id' => $scan_id]);
        $all_findings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($all_findings)) {
            $ai = new AIEngine();
            $enhanced = $ai->enrichFindings($all_findings);
            $upd = $pdo->prepare("UPDATE scan_results SET recommendation = :rec, description = :desc WHERE id = :id");
            foreach ($enhanced as $f) {
                $upd->execute(['rec' => $f['recommendation'], 'desc' => $f['description'], 'id' => $f['id']]);
            }
        }
        $engines_used[] = 'AI Classification & RAG';
    }

    // ============================================
    // 10. FINALIZE SCAN
    // ============================================
    $duration = round(microtime(true) - $start_time);
    $modes    = implode(' + ', $engines_used);
    if (empty($modes)) $modes = 'Completed with no engine output';

    $pdo->prepare("
        UPDATE scans
        SET status = 'completed', progress = 100, current_engine = :modes, scan_duration = :dur
        WHERE id = :id
    ")->execute(['modes' => "Completed ($modes)", 'dur' => $duration, 'id' => $scan_id]);

    if (!$is_ajax) {
        redirect("../dashboard.php", "Scan completed! Engines: $modes.", 'success');
    }

} catch (PDOException $e) {
    error_log("DB Error in process.php: " . $e->getMessage());
    if (!empty($scan_id)) {
        try {
            $pdo->prepare("UPDATE scans SET status='failed', progress=100, current_engine='Failed' WHERE id=:id")
                ->execute(['id' => $scan_id]);
        } catch (Exception $ignored) {}
    }
    if (!$is_ajax) {
        redirect('../dashboard.php', 'A database error occurred during the scan.', 'error');
    }
}
?>
