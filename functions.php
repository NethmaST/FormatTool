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
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? null;

    if (!$apiKey || empty($requirements)) {
        return [];
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $prompt = "Analyze requirements and return JSON with Subject, Verb, Object:\n\n";

    if (is_array($requirements)) {
        foreach ($requirements as $req) {
            $prompt .= "- " . trim($req) . "\n";
        }
    } else {
        $prompt .= "- " . trim($requirements) . "\n";
    }

    $prompt .= "\nReturn JSON:\n{
  \"results\": [
    {\"requirement\": \"...\", \"subject\": \"...\", \"verb\": \"...\", \"object\": \"...\"}
  ]
}";

    $data = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        return [];
    }

    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return json_decode($text, true) ?? [];
}
?>