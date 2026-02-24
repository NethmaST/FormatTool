<?php
require 'functions.php';

$text = $_POST['text'] ?? '';

if (!$text) {
    echo "No text provided";
    exit;
}

$result = analyzeSVO($text);

if (isset($result['results'][0])) {
    $row = $result['results'][0];
    echo "Subject: {$row['subject']}<br>";
    echo "Verb: {$row['verb']}<br>";
    echo "Object: {$row['object']}";
} else {
    echo "No analysis";
}
?>