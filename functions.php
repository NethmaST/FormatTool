<?php
/**
 * SRS Intelligence Portal - Core Functions
 * Handles PDF parsing, requirement analysis, and SVO analysis via Gemini API
 */

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from .env file
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Exception $e) {
    // .env file not found, use system environment
}

/**
 * Analyze requirements using Gemini API to extract Subject-Verb-Object components
 * 
 * @param mixed $requirements Required text or array of requirements
 * @return array Analysis results with SVO components
 */
function analyzeSVO($requirements)
{
    // Try multiple ways to get the API key
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');

    if (!$apiKey) {
        return ['error' => 'Gemini API key not found. Please add GEMINI_API_KEY to .env file.'];
    }
    
    if (empty($requirements)) {
        return ['error' => 'No requirement text provided.'];
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . urlencode($apiKey);

    $prompt = "Analyze the following requirement and extract Subject, Verb, and Object. Return ONLY valid JSON in this exact format:\n\n";
    $prompt .= "Requirement:\n";
    
    if (is_array($requirements)) {
        foreach ($requirements as $req) {
            $prompt .= trim($req) . "\n";
        }
    } else {
        $prompt .= trim($requirements) . "\n";
    }

    $prompt .= "\nRespond with ONLY this JSON structure (no markdown, no extra text):\n";
    $prompt .= '{"results": [{"subject": "...", "verb": "...", "object": "..."}]}';

    $data = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];
    set_time_limit(20);
    
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // ⏳ limit to 15 seconds

$response = curl_exec($ch);

if (curl_errno($ch)) {
    return "Curl Error: " . curl_error($ch);
}

// DO NOT USE curl_close($ch); // not needed in PHP 8+


    // Parse the API response
    $result = json_decode($response, true);
    
    if (!$result) {
        return ['error' => 'Invalid JSON response from API'];
    }

    // Extract the text content from Gemini response
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return ['error' => 'Unexpected API response format: ' . json_encode($result)];
    }

    $text = $result['candidates'][0]['content']['parts'][0]['text'];
    
    // Clean up the response (remove markdown code blocks if present)
    $text = str_replace(['```json', '```', '`'], '', $text);
    $text = trim($text);
    
    // Parse the JSON from the response
    $analysisResult = json_decode($text, true);
    
    if (!$analysisResult) {
        return ['error' => 'Could not parse JSON from Gemini response: ' . $text];
    }

    return $analysisResult;
}
?>