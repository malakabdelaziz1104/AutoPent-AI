<?php
/**
 * ============================================
 * PENTEST SCANNER - Nmap Wrapper
 * ============================================
 * Course Project: Part 2 - Lesson 11
 * * This class handles the execution of Nmap commands.
 * It's designed to be safe for local XAMPP environments:
 * If Nmap is not installed, it returns mock (fake) data 
 * so the application doesn't break during testing.
 */

class NmapScanner {
    
    // Path to the Nmap executable. 
    // On Windows, it might be 'C:\Program Files (x86)\Nmap\nmap.exe' or just 'nmap' if it's in the PATH.
    // On Linux/Mac, it's usually just 'nmap'.
    private $nmap_path = 'nmap';
    private $is_nmap_installed = false;

    public function __function() {
        // We can check if Nmap is installed when the class is created
        $this->checkNmapInstallation();
    }

    public function __construct() {
        $this->checkNmapInstallation();
    }

    /**
     * Checks if the nmap command is available on the system.
     */
    private function checkNmapInstallation() {
        // 1. First, check if 'nmap' is in the system PATH
        $check_cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'where nmap' : 'which nmap';
        
        exec($check_cmd . ' 2>&1', $output, $return_var);
        
        if ($return_var === 0) {
            $this->is_nmap_installed = true;
            // nmap_path remains 'nmap' because it's in the PATH
            return;
        }

        // 2. If not in PATH and on Windows, check common installation directories
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $common_paths = [
                'C:\\Program Files (x86)\\Nmap\\nmap.exe',
                'C:\\Program Files\\Nmap\\nmap.exe'
            ];

            foreach ($common_paths as $path) {
                if (file_exists($path)) {
                    $this->nmap_path = $path;
                    $this->is_nmap_installed = true;
                    return;
                }
            }
        }

        // Nmap not found. We will use Mock mode.
        $this->is_nmap_installed = false;
    }

    /**
     * Performs a fast port scan on the target.
     * * @param string $target  The URL or IP to scan
     * @return array          Array of findings (ports, services)
     */
    public function scanTarget($target, $options = []) {
        // Security check: validate the target format before passing to command line
        $clean_target = $this->sanitizeTarget($target);
        
        if (!$clean_target) {
            return [
                'success' => false,
                'error' => 'Invalid target format. Please provide a valid URL or IP address.'
            ];
        }

        // If Nmap isn't installed locally, return fake demo data so the course can continue
        if (!$this->is_nmap_installed) {
            return $this->getMockData($clean_target);
        }

        /**
         * ⚠️ REAL NMAP EXECUTION ⚠️
         */
        $executable = (strpos($this->nmap_path, ' ') !== false) ? '"' . $this->nmap_path . '"' : $this->nmap_path;
        
        // Base command
        $flags = [];
        
        // Port selection
        if (isset($options['full_port']) && $options['full_port']) {
            $flags[] = "-p-"; // All 65535 ports
        } else {
            $flags[] = "-F"; // Fast scan (top 100 ports)
        }

        // Service Detection
        $flags[] = "-sV";

        // Aggressive options (-A: OS detection, version detection, script scanning, and traceroute)
        if (isset($options['aggressive']) && $options['aggressive']) {
            $flags[] = "-A";
        }

        // 🆕 FIREWALL DETECTION
        // بيكتشف لو في Firewall قدام السيرفر
        // ============================================
        if (isset($options['firewall_detection']) && $options['firewall_detection']) {
            $flags[] = "--script firewall-bypass";
        }

        // ============================================
        // 🆕 DEFAULT CREDENTIALS CHECK
        // بيجرب الـ passwords الافتراضية على كل service
        // ============================================
        if (isset($options['default_credentials']) && $options['default_credentials']) {
            $flags[] = "--script auth";         // بيجرب default usernames/passwords
            $flags[] = "--script default";      // بيشغل الـ default scripts المفيدة
        }

        // Script scanning (Vulnerability scripts)
        if (isset($options['scripts']) && $options['scripts']) {
            $flags[] = "--script vuln";
        }

        // Output format (XML to stdout)
        $flags[] = "-oX -";
        
        $flags_str = implode(' ', $flags);
        $command = "{$executable} {$flags_str} " . escapeshellarg($clean_target) . " 2>&1";
        
        // Execute command and capture output
        exec($command, $output, $return_var);
        
        if ($return_var !== 0 || empty($output)) {
            // Log the error for debugging
            error_log("Nmap failed with return code $return_var. Command: $command");
            error_log("Nmap output: " . implode("\n", $output));

            return [
                'success' => false,
                'error' => 'Nmap execution failed. Check server permissions or command syntax.'
            ];
        }

        // Parse the raw XML output into a PHP array
        $xml_string = implode("\n", $output);
        return $this->parseNmapXml($xml_string);
    }

    /**
     * Parses Nmap's XML output into a structured array
     */
    private function parseNmapXml($xml_string) {
        $findings = [];
        
        try {
            // Suppress warnings in case of malformed XML
            $xml = @simplexml_load_string($xml_string);
            
            if ($xml === false) {
                throw new Exception("Failed to parse Nmap XML output.");
            }

            // Loop through all 'host' elements (usually just 1)
            foreach ($xml->host as $host) {
                // Determine if host is up
                $status = (string)$host->status['state'];
                if ($status !== 'up') {
                    continue; 
                }

                // Loop through all 'port' elements
                if (isset($host->ports->port)) {
                    foreach ($host->ports->port as $port) {
                        $port_state = (string)$port->state['state'];
                        
                        // ============================================
                        // 🆕 FIREWALL DETECTION - لو الـ Port filtered
                        // ============================================
                        if ($port_state === 'filtered') {
                            $port_id     = (string)$port['portid'];
                            $protocol    = (string)$port['protocol'];
                            $port_reason = (string)$port->state['reason'];
                
                            $findings[] = [
                                'type'        => 'Firewall Detected',
                                'severity'    => 'low',
                                'description' => "Firewall detected on Port $port_id/$protocol. The port is filtered. Reason: $port_reason",
                                'raw_port'    => $port_id,
                                'service'     => 'firewall'
                            ];
                            continue;
                        }

                        // We only care about open ports
                        if ($port_state === 'open') {
                            $port_id = (string)$port['portid'];
                            $protocol = (string)$port['protocol'];
                            
                            // Get service details if available
                            $service_name = 'unknown';
                            $product = '';
                            $version = '';
                            
                            if (isset($port->service)) {
                                $service_name = (string)$port->service['name'];
                                $product = isset($port->service['product']) ? (string)$port->service['product'] : '';
                                $version = isset($port->service['version']) ? (string)$port->service['version'] : '';
                            }
                            
                            // Build the finding description
                            $desc = "Open Port: $port_id/$protocol";
                            if ($service_name !== 'unknown') {
                                $desc .= " (Service: $service_name";
                                if ($product) { $desc .= " - $product $version"; }
                                $desc .= ")";
                            }

                            // Classify severity based on service type
                            // Dangerous or sensitive services get higher severity
                            $dangerous_services = ['mysql', 'postgresql', 'mssql', 'oracle', 'redis', 'mongodb', 'memcached', 'elasticsearch'];
                            $high_risk_services = ['ftp', 'telnet', 'smb', 'rdp', 'vnc', 'rpc', 'netbios-ssn', 'microsoft-ds'];
                            $medium_services = ['smtp', 'pop3', 'imap', 'snmp', 'ntp', 'upnp', 'sip'];
                            
                            $port_severity = 'low'; // Default for common web ports
                            if (in_array($service_name, $dangerous_services)) {
                                $port_severity = 'critical';
                            } elseif (in_array($service_name, $high_risk_services)) {
                                $port_severity = 'high';
                            } elseif (in_array($service_name, $medium_services)) {
                                $port_severity = 'medium';
                            }

                            // Add to our findings array
                            $findings[] = [
                                'type' => 'Open Port',
                                'severity' => $port_severity,
                                'description' => $desc,
                                'raw_port' => $port_id,
                                'service' => $service_name
                            ];
                            
                            // ============================================
                            // 🆕 DEFAULT CREDENTIALS & VULNERABILITIES - تحليل النتايج
                            // ============================================
                            if (isset($port->script)) {
                                foreach ($port->script as $script) {
                                    $script_id     = (string)$script['id'];
                                    $script_output = (string)$script['output'];

                                    // لو لقى default credentials
                                    if (stripos($script_output, 'valid credentials') !== false ||
                                        stripos($script_output, 'login correct')     !== false ||
                                        stripos($script_output, 'success')           !== false) {
                    
                                        $findings[] = [
                                            'type'        => 'Default Credentials Found',
                                            'severity'    => 'critical',
                                            'description' => "Default credentials detected on Port $port_id ($service_name). Script: $script_id. Output: $script_output",
                                            'raw_port'    => $port_id,
                                            'service'     => $service_name
                                        ];
                                    }

                                    // لو لقى anonymous access
                                    if (stripos($script_output, 'anonymous') !== false) {
                                        $findings[] = [
                                            'type'        => 'Anonymous Access Allowed',
                                            'severity'    => 'high',
                                            'description' => "Anonymous access detected on Port $port_id ($service_name). Script: $script_id.",
                                            'raw_port'    => $port_id,
                                            'service'     => $service_name
                                        ];
                                    }

                                    // ============================================
                                    // 🆕 VULNERABILITY SCRIPTS - التقاط ثغرات الويب (نسخة منظمة ومختصرة)
                                    // ============================================
                                    if (stripos($script_id, 'vuln') !== false || 
                                        stripos($script_id, 'http-') !== false || 
                                        stripos($script_output, 'VULNERABLE') !== false) {
                                        
                                        // 1. استبعاد الرسايل اللي بتأكد عدم وجود ثغرات (False Positives)
                                        $is_false_positive = (stripos($script_output, "Couldn't find") !== false) || 
                                                             (stripos($script_output, "No vulnerabilities") !== false);
                                                             
                                        // 2. استبعاد سكريبتات كلمات المرور
                                        $is_auth_check = (stripos($script_output, 'valid credentials') !== false) || 
                                                         (stripos($script_output, 'anonymous') !== false);

                                        if (!$is_false_positive && !$is_auth_check) {
                                            
                                            $dynamic_severity = 'medium';
                                            $final_output = trim($script_output);
                                            
                                            // 3. فلترة وتلخيص مخرجات سكريبت Vulners عشان التقرير ميكبرش
                                            if (stripos($script_id, 'vulners') !== false) {
                                                $dynamic_severity = 'high';
                                                
                                                // تقسيم المخرجات لسطور
                                                $lines = explode("\n", $final_output);
                                                $total_lines = count($lines);
                                                
                                                // لو المخرجات أكتر من 6 سطور (سطر للتعريف و 5 ثغرات)
                                                if ($total_lines > 6) {
                                                    $top_lines = array_slice($lines, 0, 6);
                                                    $hidden_count = $total_lines - 6;
                                                    
                                                    // دمج السطور وإضافة رسالة التلخيص
                                                    $final_output = implode("\n", $top_lines) . "\n\n... [ +" . $hidden_count . " more vulnerabilities found. Service update is highly recommended! ]";
                                                }
                                            }
                                            // 4. تقييم باقي السكريبتات
                                            elseif (stripos($script_output, 'VULNERABLE') !== false) {
                                                $dynamic_severity = 'high';
                                            } elseif (stripos($script_id, 'csrf') !== false || stripos($script_id, 'sql') !== false) {
                                                $dynamic_severity = 'high';
                                            } elseif (stripos($script_id, 'title') !== false || stripos($script_id, 'server-header') !== false || stripos($script_id, 'methods') !== false) {
                                                $dynamic_severity = 'low'; 
                                            }

                                            $findings[] = [
                                                'type'        => 'Vulnerability Detected (' . $script_id . ')',
                                                'severity'    => $dynamic_severity,
                                                'description' => "Nmap Script detected a vulnerability on Port $port_id ($service_name).\nDetails:\n$final_output",
                                                'raw_port'    => $port_id,
                                                'service'     => $service_name
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return [
                'success' => true,
                'findings' => $findings,
                'mode' => 'real' // Indicates actual Nmap was used
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error parsing Nmap results: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extracts hostname or IP from a URL string to prevent command injection
     */
    private function sanitizeTarget($target) {
        // ضفنا http:// مؤقتاً لو مش موجودة عشان دالة parse_url تفهم اللينك
        if (strpos($target, 'http://') === false && strpos($target, 'https://') === false) {
            $target = 'http://' . $target;
        }
        
        $parsed = parse_url($target);
        $host = isset($parsed['host']) ? $parsed['host'] : '';

        // مسح البورت لو مكتوب في اللينك
        $host = explode(':', $host)[0];

        // التحقق الذكي: دلوقتي هيقبل localhost أو أي IP أو أي دومين
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
            return $host;
        }
        
        return false;
    }

    /**
     * Returns fake data for users testing the app locally without Nmap installed.
     * This ensures the application workflow doesn't break during the course.
     */
    private function getMockData($target) {
        // Simulate a 2-second scan delay
        sleep(2);
        
        return [
            'success' => true,
            'mode' => 'mock (Nmap not installed natively)',
            'target' => $target,
            'findings' => [
                [
                    'type' => 'Open Port',
                    'severity' => 'low',
                    'description' => 'Open Port: 80/tcp (Service: http - Apache httpd 2.4.41)',
                    'raw_port' => '80',
                    'service' => 'http'
                ],
                [
                    'type' => 'Open Port',
                    'severity' => 'low',
                    'description' => 'Open Port: 443/tcp (Service: https - Apache httpd 2.4.41)',
                    'raw_port' => '443',
                    'service' => 'https'
                ],
                [
                    'type' => 'Open Port',
                    'severity' => 'medium',
                    'description' => 'Open Port: 3306/tcp (Service: mysql - MySQL 8.0.21). Exposing databases to the public internet is risky.',
                    'raw_port' => '3306',
                    'service' => 'mysql'
                ]
            ]
        ];
    }
}
?>