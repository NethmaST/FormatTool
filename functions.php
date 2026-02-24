<?php
require_once 'vendor/autoload.php';

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
    curl_close($ch);

    if (!$response) {
        return [];
    }

    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return json_decode($text, true) ?? [];
}
?>