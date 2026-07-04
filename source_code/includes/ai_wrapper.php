<?php
/**
 * ============================================
 * PENTEST SCANNER - AI Classification & RAG Engine
 * ============================================
 */
set_time_limit(0);

class AIEngine {
    
    // Default Ollama API endpoint
    private $ollama_url = 'http://127.0.0.1:11434/api/generate';
    
    // Primary model: deepseek-r1:7b for deep, accurate security analysis
    private $primary_model = 'deepseek-r1:7b';

    // Fallback model: mistral:latest — used if primary times out or fails
    private $fallback_model = 'mistral:latest';

    // Timeout per model (seconds) — DeepSeek needs more time to think
    private $primary_timeout = 300; // 5 minutes
    private $fallback_timeout = 120; // 2 minutes

    /**
     * Calls Ollama with a given model and prompt.
     * Returns the raw response string, or false on failure.
     */
    private function callOllama($model, $prompt, $timeout = 60) {
        $data = [
            'model'  => $model,
            'prompt' => $prompt,
            'stream' => false
            // تم حذف 'format' => 'json' لمنع التعارض مع موديلات التفكير مثل DeepSeek
        ];

        $ch = curl_init($this->ollama_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("Ollama [{$model}] Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $response;
    }

    /**
     * Helper function to extract and parse JSON from the LLM response.
     * Cleans up markdown code blocks and DeepSeek <think> tags.
     */
    private function parseLLMResponse($response) {
        if ($response === false) {
            return null;
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['response'])) {
            return null;
        }

        $text = trim($result['response']);
        
        // 1. مسح تاج التفكير الخاص بـ DeepSeek وأي محتوى بداخله
        $text = preg_replace('/<think>.*?<\/think>/is', '', $text);
        
        // 2. مسح علامات الـ Markdown لو الموديل كتبها
        $text = preg_replace('/```json|```/', '', $text);
        
        // 3. استخراج الـ JSON فقط (من أول القوس { لحد القوس })
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $text = $matches[0];
        }
        
        // محاولة تحويل النص النهائي إلى مصفوفة
        $json = json_decode(trim($text), true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }
        
        return null; // فشل في تحليل الـ JSON
    }

    public function enrichFindings($findings) {
        $enriched = [];
        
        foreach ($findings as $finding) {
            
            // Normalize vulnerability names to smoothly handle output from Nmap or Burp Suite
            $vulnName = $finding['vulnerability_type'] ?? $finding['vulnerability_name'] ?? 'Unknown Vulnerability';
            $severity = $finding['severity'] ?? 'info';
            $description = $finding['description'] ?? 'No detailed description provided.';
            $recommendation = $finding['recommendation'] ?? 'No raw recommendation available. Please generate a detailed remediation guide from scratch.';

            $prompt = "You are a senior cybersecurity expert. Analyze the following vulnerability finding from an automated scanner.\n\n";
            $prompt .= "Vulnerability: " . $vulnName . "\n";
            $prompt .= "Severity: " . $severity . "\n";
            $prompt .= "Raw Description: " . $description . "\n";
            $prompt .= "Raw Recommendation: " . $recommendation . "\n\n";
            $prompt .= "Task 1 (Classification): Classify this vulnerability according to the OWASP Top 10 or CWE, and rewrite the description to be professional, clear, and concise (maximum 3 sentences).\n";
            $prompt .= "Task 2 (Remediation): Provide a practical, actionable remediation guide. Restrict your answer to exactly 3 to 4 short, clear bullet points. Do NOT write a long essay.\n\n";
            $prompt .= "Return the result EXACTLY in the following JSON format. Do NOT wrap it in markdown code blocks, just return the raw JSON object:\n";
            $prompt .= "{\n  \"description\": \"<new classified description>\",\n  \"recommendation\": [\n    \"<bullet point 1>\",\n    \"<bullet point 2>\",\n    \"<bullet point 3>\"\n  ]\n}";

            $llm_output = null;

            // --- المحاولة الأولى: Primary Model (DeepSeek) ---
            $response = $this->callOllama($this->primary_model, $prompt, $this->primary_timeout);
            $llm_output = $this->parseLLMResponse($response);

            // --- المحاولة الثانية: Fallback Model (Mistral) ---
            if ($llm_output === null) {
                // Primary model failed or returned bad JSON — switch to Mistral
                error_log("Primary model ({$this->primary_model}) failed or returned invalid JSON for vulnerability: {$vulnName}. Switching to fallback ({$this->fallback_model}).");
                
                $response = $this->callOllama($this->fallback_model, $prompt, $this->fallback_timeout);
                $llm_output = $this->parseLLMResponse($response);
            }

            // --- اعتماد النتيجة أو الحفاظ على الثغرة الأصلية ---
            if ($llm_output !== null && isset($llm_output['description']) && isset($llm_output['recommendation'])) {
                
                // 1. معالجة الوصف (Description) والتأكد إنه مش Array
                $finding['description'] = is_array($llm_output['description']) ? implode(" ", $llm_output['description']) : $llm_output['description'];
                
                // 2. معالجة الحل (Recommendation) وتحويله لـ Bullet Points حقيقية
                if (is_array($llm_output['recommendation'])) {
                    $formattedBullets = "";
                    foreach ($llm_output['recommendation'] as $point) {
                        // تنظيف أي شرطة أو مسافة الموديل يكون كتبها بالغلط عشان ميحصلش تكرار
                        $cleanPoint = ltrim($point, "-*• \t\n\r");
                        // إضافة رمز الدائرة وسطر جديد
                        $formattedBullets .= "• " . $cleanPoint . "\n";
                    }
                    $finding['recommendation'] = trim($formattedBullets);
                } else {
                    // لو الموديل خالف الأوامر ورجعها نص عادي
                    $finding['recommendation'] = "• " . ltrim($llm_output['recommendation'], "-*• \t\n\r");
                }
                
            } else {
                // Both models failed — keep original finding untouched
                error_log("Both models failed to return valid JSON for vulnerability: {$vulnName}. Keeping original finding.");
            }
            
            $enriched[] = $finding;
        }
        
        return $enriched;
    }
}
?>