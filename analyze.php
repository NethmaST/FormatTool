<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function analyzeSVO($text) {
    $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';

    if (empty($apiKey)) {
        return "API key not found";
    }

    $client = OpenAI::client($apiKey);

    $response = $client->chat()->create([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'Extract Subject, Verb, Object (SVO).'],
            ['role' => 'user', 'content' => $text]
        ],
    ]);

    return $response['choices'][0]['message']['content'] ?? 'No analysis';
}

if (isset($_POST['text'])) {
    echo analyzeSVO($_POST['text']);
}