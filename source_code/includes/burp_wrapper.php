<?php
/**
 * ============================================
 * PENTEST SCANNER - Burp Suite Wrapper
 * ============================================
 * Handles integration with Burp Suite Professional API
 * for Web Vulnerability Scanning.
 */

class BurpSuiteScanner {
    
    private $host = '127.0.0.1';
    private $port = 1337;
    private $api_key = 'E9h61krVyvcJlCcub4n7uY91RAeqPOzU'; 
    private $api_version = 'v0.1';
    
    // Send HTTP requests to Burp Suite REST API
    private function sendApiRequest($method, $endpoint, $payload = null) {
        $url = "http://{$this->host}:{$this->port}/{$this->api_key}/{$this->api_version}/{$endpoint}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 0); 
        curl_setopt($ch, CURLOPT_HEADER, true); 
        
        $headers = [
            "Content-Type: application/json"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($http_code >= 400) {
            throw new Exception("API Request failed with HTTP Code: {$http_code}");
        }

        $header_text = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $result = json_decode($body, true) ?? [];

        // Extract Task ID from the Location header if starting a new scan
        if (preg_match('/Location:\s*([^\r\n]+)/i', $header_text, $matches)) {
            $location_url = trim($matches[1]);
            $url_parts = explode('/', rtrim($location_url, '/'));
            $result['task_id'] = end($url_parts);
        }

        return $result;
    }

    // Check if Burp Suite API is reachable and authenticated
    private function authenticate() {
        try {
            $this->sendApiRequest('GET', 'knowledge_base/issue_definitions');
            return true;
        } catch (Exception $e) {
            error_log("Burp Connection Error: " . $e->getMessage());
            return false;
        }
    }

    // Main execution function for the scanner
    // $status_file: optional path. If provided, progress/status is written
    // here as JSON instead of writing directly to the `scans` DB row.
    // This avoids two processes (this wrapper + the main process.php
    // orchestrator) racing to write the same `progress` column at once,
    // which is what caused the progress bar to jump up and down.
    public function performWebScan($target, $scan_id, $pdo, $status_file = null) {
        set_time_limit(0);
        ignore_user_abort(true);
        
        // Close session to prevent blocking other requests while scanning
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            if (!$this->authenticate()) {
                throw new Exception("Burp Suite Authentication failed. Check API key or connection.");
            }

            $startPayload = [
                "urls" => [$target]
            ];
            
            $startResponse = $this->sendApiRequest('POST', 'scan', $startPayload);
            
            $taskId = $startResponse['task_id'] ?? null;
            
            if (!$taskId) {
                throw new Exception("Failed to get Task ID from Burp Suite.");
            }

            // Monitor the scan until it finishes
            $this->pollStatus($taskId, $scan_id, $status_file);

            // Fetch and parse the discovered vulnerabilities
            $issues = $this->fetchFindings($taskId);

            return ['success' => true, 'findings' => $issues];

        } catch (Exception $e) {
            error_log("Burp Suite Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Dynamic polling mechanism to track progress.
    // Writes progress to a local JSON file instead of updating the DB
    // directly — the main process.php orchestrator is the single owner
    // of the `scans.progress` column and reads this file to merge status.
    // (Writing to the DB from here caused two processes to race on the
    // same row, which is why the progress bar used to jump up and down.)
    private function pollStatus($taskId, $scan_id, $status_file = null) {
        $completed = false;
        $current_progress = 65.0; // Burp phase starts at 65%
        
        while (!$completed) {
            sleep(4); // 4 seconds delay for smoother progress bar updates
            
            try {
                $statusResponse = $this->sendApiRequest('GET', "scan/{$taskId}");
                
                $status = strtolower($statusResponse['scan_status'] ?? $statusResponse['status'] ?? 'running');
                $is_done = in_array($status, ['succeeded', 'completed', 'finished']);

                $display_status = "Burp: " . ucfirst($status);

                if (isset($statusResponse['metrics'])) {
                    $metrics = $statusResponse['metrics'];
                    
                    $audit_active = $metrics['audit_queue_items_active'] ?? $metrics['audit_items_in_progress'] ?? 0;
                    $audit_waiting = $metrics['audit_queue_items_waiting'] ?? $metrics['audit_items_pending'] ?? 0;
                    $audit_completed = $metrics['audit_queue_items_completed'] ?? $metrics['audit_items_completed'] ?? 0;
                    $crawl_reqs = $metrics['crawl_requests_made'] ?? 0;
                    
                    $total_audit = $audit_active + $audit_waiting + $audit_completed;
                    
                    // Update status text and calculate progress based on the current phase
                    if ($total_audit > 0) {
                        $display_status = "Burp Auditing: {$audit_completed} / {$total_audit} items";
                        
                        // Auditing phase: from 75% to 85%
                        $audit_ratio = ($audit_completed / $total_audit);
                        $target_progress = 75 + ($audit_ratio * 10); 
                        if ($current_progress < $target_progress) {
                            $current_progress = $target_progress;
                        }
                    } 
                    elseif ($crawl_reqs > 0) {
                         $display_status = "Burp Crawling: {$crawl_reqs} requests sent";
                         
                         // Crawling phase: from 65% to 75% (estimated based on average size)
                         $crawl_ratio = min(1.0, $crawl_reqs / 300); 
                         $target_progress = 65 + ($crawl_ratio * 10); 
                         if ($current_progress < $target_progress) {
                             $current_progress = $target_progress;
                         }
                    }
                }

                // Smooth progression trick: continuously add small increments
                $current_progress += 0.5; 
                
                // Cap at 84% to avoid showing completion before it actually finishes
                if ($current_progress > 84) {
                    $current_progress = 84;
                }

                if ($is_done) {
                    $completed = true;
                    $current_progress = 85;
                    $display_status = "Burp: Finished";
                } elseif (in_array($status, ['failed', 'error', 'aborted'])) {
                    $completed = true;
                }

                // Write status to file instead of the DB. Atomic write
                // (tmp file + rename) so the reader never sees a half-written file.
                if ($status_file) {
                    $payload = json_encode([
                        'progress' => round($current_progress),
                        'status'   => $display_status,
                        'done'     => $completed,
                        'ts'       => time(),
                    ]);
                    $tmp = $status_file . '.tmp';
                    file_put_contents($tmp, $payload);
                    rename($tmp, $status_file);
                }
                
            } catch (Exception $e) {
                error_log("Polling API warning: " . $e->getMessage());
            }
        }
    }

    // Retrieve and format the discovered vulnerabilities from the API
    private function fetchFindings($taskId) {
        $findings = [];
        $unique_issues = []; 
        
        // Fetch full scan details including vulnerabilities
        $issuesResponse = $this->sendApiRequest('GET', "scan/{$taskId}");
        
        // Vulnerabilities in Burp Pro are stored inside the 'issue_events' array
        if (isset($issuesResponse['issue_events']) && is_array($issuesResponse['issue_events'])) {
            foreach ($issuesResponse['issue_events'] as $event) {
                
                // Ensure issue data exists
                if (!isset($event['issue'])) continue;
                
                $issue = $event['issue'];
                $issue_name = htmlspecialchars($issue['name'] ?? 'Unknown Vulnerability');
                
                // Prevent duplicate vulnerabilities to keep the report clean
                if (isset($unique_issues[$issue_name])) {
                    continue;
                }
                $unique_issues[$issue_name] = true;

                // Normalize severity strings
                $raw_severity = strtolower($issue['severity'] ?? 'low');
                if ($raw_severity === 'information') {
                    $raw_severity = 'low'; // Treat informational as low risk for dashboard consistency
                }

                // Note: Burp sometimes uses snake_case or camelCase depending on the version
                $description = $issue['issue_background'] ?? $issue['issueBackground'] ?? 'No description provided.';
                $remediation = $issue['remediation_background'] ?? $issue['remediationBackground'] ?? 'No remediation provided.';

                $findings[] = [
                    'type' => $issue_name,
                    'severity' => $raw_severity, 
                    'description' => strip_tags($description),
                    'recommendation' => strip_tags($remediation)
                ];
            }
        }
        
        return $findings;
    }
}
?>