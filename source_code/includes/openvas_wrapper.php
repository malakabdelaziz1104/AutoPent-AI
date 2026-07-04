<?php
/**
 * ============================================
 * PENTEST SCANNER - OpenVAS/GVM Wrapper (REAL SCAN)
 * ============================================
 * Architecture:
 * PHP App -> TCP:9390 -> socat bridge -> gvmd Unix socket -> OpenVAS Scanner
 */

class OpenVASScanner {

    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout;
    private $scan_config;
    private $socket = null;
    private $is_connected = false;

    // Built-in OpenVAS scanner UUID (stable across installs).
    const SCANNER_OPENVAS = '08b69003-5fc2-4037-a479-93b440211c73';

    public function __construct() {
        $this->host = defined('OPENVAS_HOST') ? OPENVAS_HOST : '127.0.0.1';
        $this->port = defined('OPENVAS_GMP_PORT') ? OPENVAS_GMP_PORT : 9390;
        $this->username = defined('OPENVAS_USERNAME') ? OPENVAS_USERNAME : 'admin';
        $this->password = defined('OPENVAS_PASSWORD') ? OPENVAS_PASSWORD : 'admin';
        $this->timeout = 10400; // اديناه ساعة ونص كاملة عشان الفحص العميق ياخد راحته
        $this->scan_config = defined('OPENVAS_SCAN_CONFIG') ? OPENVAS_SCAN_CONFIG : 'daba56c8-73ec-11df-a475-002264764cea';
    }

    private function connect() {
        $this->socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            5
        );

        if (!$this->socket) {
            error_log("OpenVAS Connect Failed: ($errno) $errstr on {$this->host}:{$this->port}");
            $this->is_connected = false;
            return false;
        }

        stream_set_timeout($this->socket, 30);
        $this->is_connected = true;
        return true;
    }

    private function sendCommand($xml) {
        if (!$this->socket || !$this->is_connected) {
            throw new \Exception("Not connected to GVM.");
        }

        $written = @fwrite($this->socket, $xml);
        if ($written === false) {
            throw new \Exception("Failed to write to GVM socket.");
        }

        $response = '';
        $maxBytes = 1024 * 1024 * 5;
        $startTime = time();

        while (!feof($this->socket)) {
            $chunk = @fread($this->socket, 8192);
            if ($chunk === false || $chunk === '') {
                $info = stream_get_meta_data($this->socket);
                if ($info['timed_out']) {
                    error_log("OpenVAS: Socket read timed out.");
                    break;
                }
                usleep(50000);
                continue;
            }
            $response .= $chunk;

            if ($this->isCompleteXml($response)) {
                break;
            }

            if (strlen($response) > $maxBytes || (time() - $startTime) > 60) {
                break;
            }
        }

        return $response;
    }

    private function isCompleteXml($xml) {
        $xml = trim($xml);
        if (empty($xml)) return false;

        if (substr($xml, -2) === '/>') {
            return true;
        }

        if (preg_match('/^<([a-zA-Z0-9_-]+)[\s>]/', $xml, $matches)) {
            $rootTag = $matches[1];
            return (strpos($xml, "</{$rootTag}>") !== false);
        }
        return false;
    }

    public function authenticate() {
        $cmd = "<authenticate>"
             . "<credentials>"
             . "<username>{$this->username}</username>"
             . "<password>{$this->password}</password>"
             . "</credentials>"
             . "</authenticate>";

        $response = $this->sendCommand($cmd);
        return strpos($response, 'status="200"') !== false;
    }

    public function getVersion() {
        try {
            if (!$this->connect()) return false;
            if (!$this->authenticate()) return false;

            $response = $this->sendCommand('<get_version/>');
            if (preg_match('/<version>([^<]+)<\/version>/', $response, $matches)) {
                $this->disconnect();
                return $matches[1];
            }
            $this->disconnect();
            return false;
        } catch (\Exception $e) {
            error_log("OpenVAS getVersion error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    private function extractHost($target_url) {
        $t = trim($target_url);
        if (!preg_match('#^https?://#i', $t)) {
            $t = 'http://' . $t;
        }
        $parsed = parse_url($t);
        $host = $parsed['host'] ?? trim($target_url);

        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            $host = 'host.docker.internal';
            error_log("OpenVAS: rewrote loopback target to host.docker.internal so the container can reach the host.");
        }
        return $host;
    }

    // الدالة الحقيقية اللي بتبعت أوامر الفحص للداش بورد
    public function performVulnerabilityScan($target, $scan_id, $pdo, $status_file = null) {
        try {
            if (!$this->connect()) {
                throw new \Exception("Cannot connect to GVM on {$this->host}:{$this->port}");
            }

            if (!$this->authenticate()) {
                throw new \Exception("GVM authentication failed. Check credentials in config.php.");
            }

            $clean_host = $this->extractHost($target);
            error_log("OpenVAS: Authenticated. Starting REAL scan for host: $clean_host");

            if ($status_file) {
                @file_put_contents($status_file, json_encode([
                    'progress' => 40,
                    'status'   => "OpenVAS: preparing scan",
                ]));
            }

            $port_list_response = $this->sendCommand("<get_port_lists/>");
            $port_list_id = $this->pickPortList($port_list_response);

            $config_response = $this->sendCommand("<get_configs/>");
            $config_id = $this->pickScanConfig($config_response);

            // 1. Create Target in OpenVAS
            $target_name = "PenTest_Scan_{$scan_id}_" . time();
            $cmd = "<create_target>"
                 . "<name>{$target_name}</name>"
                 . "<hosts>{$clean_host}</hosts>"
                 . "<port_list id=\"{$port_list_id}\"/>"
                 . "<alive_tests>Consider Alive</alive_tests>"
                 . "</create_target>";

            $response = $this->sendCommand($cmd);
            $target_uuid = $this->extractId($response);

            if (!$target_uuid || strpos($response, 'status="201"') === false) {
                throw new \Exception("Failed to create GVM target. Response: " . substr($response, 0, 200));
            }
            error_log("OpenVAS: Target created: $target_uuid");

            // 2. Create Task in OpenVAS
            $cmd = "<create_task>"
                . "<name>{$target_name}</name>"
                . "<target id=\"{$target_uuid}\"/>"
                . "<config id=\"{$config_id}\"/>"
                . "</create_task>";

            $response = $this->sendCommand($cmd);
            $task_uuid = $this->extractId($response);

            if (!$task_uuid || strpos($response, 'status="201"') === false) {
                throw new \Exception("Failed to create GVM task. Response: " . substr($response, 0, 200));
            }
            error_log("OpenVAS: Task created: $task_uuid");

            // 3. Start Task in OpenVAS (This is what makes it show up in the Dashboard)
            $cmd = "<start_task task_id=\"{$task_uuid}\"/>";
            $response = $this->sendCommand($cmd);

            if (strpos($response, 'status="202"') === false) {
                throw new \Exception("Failed to start GVM task. Response: " . substr($response, 0, 200));
            }
            error_log("OpenVAS: Task started. Polling for completion...");

            // 4. Wait for it to finish and get results
            $results = $this->pollAndWait($task_uuid, $scan_id, $pdo, $status_file);

            $this->disconnect();
            return $results;

        } catch (\Exception $e) {
            error_log("OpenVAS Error: {$e->getMessage()}");
            $this->disconnect();
            return [
                'success'  => false,
                'findings' => [],
                'mode'     => 'error',
                'error'    => "OpenVAS Engine Error: " . $e->getMessage()
            ];
        }
    }

    private function pickPortList($xml_response) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_response);
        libxml_clear_errors();

        $fallback_from_list = null;
        if ($xml !== false && isset($xml->port_list)) {
            foreach ($xml->port_list as $pl) {
                $id = (string)$pl['id'];
                $name = (string)$pl->name;
                if ($fallback_from_list === null) $fallback_from_list = $id;
                if (stripos($name, 'All IANA assigned TCP') !== false) {
                    return $id;
                }
            }
        }

        if ($fallback_from_list !== null) return $fallback_from_list;
        return "33d0cd82-57c6-11e1-8ed1-406186ea4fc5";
    }

    private function pickScanConfig($xml_response) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_response);
        libxml_clear_errors();

        if ($xml !== false && isset($xml->config)) {
            foreach ($xml->config as $cfg) {
                if ((string)$cfg->name === 'Full and fast') {
                    return (string)$cfg['id'];
                }
            }
        }
        return $this->scan_config;
    }

    private function pollAndWait($task_uuid, $scan_id, $pdo, $status_file = null) {
        $startTime = time();
        $finished_naturally = false;
        $report_uuid = null;

        while ((time() - $startTime) < $this->timeout) {
            sleep(5);

            $cmd = "<get_tasks task_id=\"{$task_uuid}\"/>";
            $response = $this->sendCommand($cmd);

            $progress = 0;
            if (preg_match('/<progress>([^<]+)<\/progress>/', $response, $matches)) {
                $progress = (int) $matches[1];
                $progress = max(0, min(100, $progress));
            }

            $status = 'Requested';
            if (preg_match('/<status>([^<]+)<\/status>/', $response, $matches)) {
                $status = $matches[1];
            }

            $mapped_progress = 40 + round(($progress / 100) * 40);

            if ($status_file) {
                @file_put_contents($status_file, json_encode([
                    'progress' => $mapped_progress,
                    'status'   => "OpenVAS Scanning ({$progress}%)",
                ]));
            }

            error_log("OpenVAS Poll: Status=$status, Progress=$progress%");

            if ($status === 'Done' || $status === 'Stopped' || $status === 'Interrupted') {
                $finished_naturally = true;
                
                if (preg_match('/<last_report>\s*<report id="([^"]+)"/i', $response, $matches)) {
                    $report_uuid = $matches[1];
                } elseif (preg_match('/<report id="([^"]+)"/i', $response, $matches)) {
                    $report_uuid = $matches[1];
                }
                break;
            }
        }

        if (!$finished_naturally) {
            error_log("OpenVAS: Timeout ({$this->timeout}s) reached before scan finished.");
            try { $this->sendCommand("<stop_task task_id=\"{$task_uuid}\"/>"); } catch (\Exception $e) {}
        }

        return $this->fetchResults($task_uuid, $report_uuid, $finished_naturally);
    }

    private function fetchResults($task_uuid, $report_uuid, $completed = true) {
    if (!empty($report_uuid)) {
        $cmd = "<get_reports report_id=\"{$report_uuid}\" details=\"1\" filter=\"levels=hmlc rows=1000\"/>";
    } else {
        $cmd = "<get_results filter=\"task_id={$task_uuid} levels=hmlc rows=1000\" details=\"1\"/>";
    }

    $response = $this->sendCommand($cmd);
    error_log("OpenVAS RAW response (report_uuid=" . ($report_uuid ?: 'NONE') . "): " . substr($response, 0, 3000));

    $findings = $this->parseGmpResults($response);

    return [
        'success'  => true,
        'findings' => $findings,
        'mode'     => $completed ? 'real' : 'real_incomplete'
    ];
}

    private function parseGmpResults($xml_string) {
        $findings = [];
        if (empty($xml_string)) return $findings;

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xml_string);
            libxml_clear_errors();

            if ($xml === false) return $findings;

            $results = [];
            if (isset($xml->report->report->results->result)) {
                $results = $xml->report->report->results->result;
            } elseif (isset($xml->report->results->result)) {
                $results = $xml->report->results->result;
            } elseif (isset($xml->result)) {
                $results = $xml->result;
            } elseif (isset($xml->results->result)) {
                $results = $xml->results->result;
            }

            foreach ($results as $result) {
                $threat = isset($result->threat) ? (string)$result->threat : '';
                if ($threat === 'Log' || $threat === 'Debug') continue;

                $cvss = 0.0;
                if (isset($result->severity)) {
                    $cvss = (float)(string)$result->severity;
                } elseif (isset($result->nvt->severities->severity->value)) {
                    $cvss = (float)(string)$result->nvt->severities->severity->value;
                }

                if ($cvss <= 0) continue;

                $name = 'Unknown Vulnerability';
                if (isset($result->nvt->name)) {
                    $name = (string)$result->nvt->name;
                } elseif (isset($result->name)) {
                    $name = (string)$result->name;
                }

                $description = '';
                if (isset($result->description)) {
                    $description = trim((string)$result->description);
                }
                if (empty($description) && isset($result->nvt->tags)) {
                    $tags = (string)$result->nvt->tags;
                    if (preg_match('/summary=([^|]+)/', $tags, $m)) $description = trim($m[1]);
                }

                $recommendation = 'Review and remediate this vulnerability.';
                if (isset($result->nvt->solution)) {
                    $recommendation = trim((string)$result->nvt->solution);
                }
                if ($recommendation === 'Review and remediate this vulnerability.' && isset($result->nvt->tags)) {
                    $tags = (string)$result->nvt->tags;
                    if (preg_match('/solution=([^|]+)/', $tags, $m)) $recommendation = trim($m[1]);
                }

                $host = isset($result->host) ? (string)$result->host : '';
                $port = isset($result->port) ? (string)$result->port : '';
                if ($port && $port !== 'general/tcp') $description = "[Port: $port] " . $description;

                $findings[] = [
                    'type' => $name,
                    'severity' => $this->mapSeverity($cvss),
                    'description' => $description ?: "Vulnerability detected: $name (CVSS: $cvss)",
                    'recommendation' => $recommendation
                ];
            }
        } catch (\Exception $e) {
            error_log("OpenVAS XML Parse Error: " . $e->getMessage());
        }

        return $findings;
    }

    private function extractId($response) {
        if (preg_match('/id="([a-f0-9\-]+)"/', $response, $matches)) return $matches[1];
        return null;
    }

    private function mapSeverity($cvss) {
        if ($cvss >= 9.0) return 'critical';
        if ($cvss >= 7.0) return 'high';
        if ($cvss >= 4.0) return 'medium';
        return 'low';
    }

    private function disconnect() {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
            $this->is_connected = false;
        }
    }
}