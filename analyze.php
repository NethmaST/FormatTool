<?php
header('Content-Type: application/json; charset=utf-8');
require 'functions.php';

$text = $_POST['text'] ?? '';

if (!$text) {
    echo json_encode([
        'success' => false,
        'error' => 'No text provided'
    ]);
    exit;
}

$result = analyzeSVO($text);

if (isset($result['error'])) {
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
    exit;
}

if (isset($result['results'][0])) {
    $row = $result['results'][0];

    echo json_encode([
        'success' => true,
        'subject' => $row['subject'] ?? '',
        'verb' => $row['verb'] ?? '',
        'object' => $row['object'] ?? ''
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Could not parse analysis'
    ]);
}
?>