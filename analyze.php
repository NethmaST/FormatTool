<?php
header('Content-Type: text/html; charset=utf-8');
require 'functions.php';

$text = $_POST['text'] ?? '';

if (!$text) {
    echo '<span style="color: #dc2626;">❌ No text provided</span>';
    exit;
}

$result = analyzeSVO($text);

// Check for error response
if (isset($result['error'])) {
    echo '<span style="color: #dc2626;">❌ Error: ' . htmlspecialchars($result['error']) . '</span>';
    exit;
}

// Check for successful analysis
if (isset($result['results'][0])) {
    $row = $result['results'][0];
    echo '<span style="color: #059669;">✓</span> ';
    echo '<strong>Subject:</strong> ' . htmlspecialchars($row['subject'] ?? 'N/A') . '<br>';
    echo '<strong>Verb:</strong> ' . htmlspecialchars($row['verb'] ?? 'N/A') . '<br>';
    echo '<strong>Object:</strong> ' . htmlspecialchars($row['object'] ?? 'N/A');
} else {
    echo '<span style="color: #dc2626;">❌ Could not parse analysis. Response: ' . htmlspecialchars(json_encode($result)) . '</span>';
}
?>