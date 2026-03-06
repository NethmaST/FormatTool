<?php

function parseTextSRS($text) {
    $lines = preg_split('/\r?\n/', $text);
    $frSections = [];
    $structured = [];

    $currentFR = '';

    foreach ($lines as $line) {
        $clean = trim($line);
        if (empty($clean)) continue;

        $clean = preg_replace('/^[●•\-\*\s]+/', '', $clean);
        $clean = trim($clean);

        if (preg_match('/^(FR-\d+\.\d+)\s*[:\-]\s*(.*)/i', $clean, $m)) {
            $currentFR = $m[1];
            $frSections[$currentFR] = $m[2];

            $structured[] = [
                'type' => 'fr',
                'key' => $currentFR,
                'text' => $m[2]
            ];
            continue;
        }

        if ($currentFR) {
            $frSections[$currentFR] .= ' ' . $clean;
            continue;
        }

        $structured[] = ['type' => 'text', 'text' => $clean];
    }

   return [
    'FR' => $frSections,
    'NFR' => [],
    'STRUCTURED' => $structured
];
}
?>