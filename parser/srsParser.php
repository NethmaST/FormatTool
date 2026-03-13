<?php

function parseTextSRS($text) {

    $lines = preg_split('/\r?\n/', $text);

    $frSections = [];
    $nfrSections = [];
    $structured = [];

   $currentFR = '';
$currentNFR = '';
$currentSection = '';

    foreach ($lines as $line) {

        $line = trim($line);

// remove bullet symbols
$line = preg_replace('/^[\•\●\-\*]+\s*/u', '', $line);
        if ($clean === '') continue;

        // remove bullets
        $clean = preg_replace('/^[●•\-\*\s]+/', '', $clean);
        $clean = trim($clean);

        /*
        ============================
        1. SECTION HEADERS
        Example:
        3 Functional Requirements
        3.1 Phase 1
        3.1.1 User Management Module
        ============================
        */

        if (preg_match('/^(\d+(\.\d+)*)\s+(.*)$/', $clean, $m)) {

            $sectionNumber = $m[1];
            $sectionTitle  = trim($m[3]);

            $currentSection = $sectionNumber . " " . $sectionTitle;

            $structured[] = [
                'type' => 'section',
                'number' => $sectionNumber,
                'title' => $sectionTitle,
                'text' => $sectionTitle
            ];

            continue;
        }

       /*
============================
2. FUNCTIONAL REQUIREMENTS
Supports:
FR-01
FR-01:
FR-01.01
FR-01.01:
FR inside brackets (FR-01)
============================
*/

if (preg_match('/\(?(FR-\d+(?:\.\d+)*)\)?\s*[:\-]?\s*(.*)/i', $clean, $m)) {

    $key = strtoupper($m[1]);
    $textFR = trim($m[2]);

    // If text is empty, use next lines
    if ($textFR == '') {
        $textFR = $clean;
    }

    $currentFR = $key;

    if (!isset($frSections[$key])) {
        $frSections[$key] = $textFR;
    } else {
        $frSections[$key] .= " " . $textFR;
    }

    $structured[] = [
        'type' => 'fr',
        'key' => $key,
        'text' => $textFR,
        'section' => $currentSection
    ];

    continue;
}

/*
============================
3. NON-FUNCTIONAL REQUIREMENTS
Supports:
NFR-01
NFR-01 (Security):
============================
*/

if (preg_match('/^(NFR-\d+)\s*(\([^)]+\))?\s*:\s*(.+)$/i', $clean, $m)) {

    $key = strtoupper($m[1]);
    $title = isset($m[2]) ? trim($m[2]) : '';
    $text = trim($m[3]);

    if ($title) {
        $text = $title . " " . $text;
    }

    $currentNFR = $key;
    $currentFR = ''; // stop FR continuation

    $nfrSections[$key] = $text;

    $structured[] = [
        'type' => 'nfr',
        'key' => $key,
        'text' => $text,
        'section' => $currentSection
    ];

    continue;
} 

/*
============================
NFR CONTINUATION
============================
*/

if ($currentNFR != '' && !preg_match('/^(NFR-\d+)/i', $clean)) {

    $nfrSections[$currentNFR] .= " " . $clean;

    $structured[] = [
        'type' => 'nfr_continuation',
        'key' => $currentNFR,
        'text' => $clean
    ];

    continue;
}





        /*
        ============================
        4. CONTINUATION OF FR
        ============================
        */

    if ($currentFR != '' && !preg_match('/^(FR-\d+)/i', $clean)) {

    $frSections[$currentFR] .= " " . $clean;

    $structured[] = [
        'type' => 'fr_continuation',
        'key' => $currentFR,
        'text' => $clean
    ];

    continue;
}

if (!empty($nfrSections)) {
    $lastNFR = array_key_last($nfrSections);

    if ($lastNFR && !preg_match('/^(NFR-\d+)/i', $clean)) {

        $nfrSections[$lastNFR] .= " " . $clean;

        $structured[] = [
            'type' => 'nfr_continuation',
            'key' => $lastNFR,
            'text' => $clean
        ];

        continue;
    }
}


        /*
        ============================
        5. NORMAL TEXT
        ============================
        */

        $structured[] = [
            'type' => 'text',
            'text' => $clean,
            'section' => $currentSection
        ];
    }

    return [
        'FR' => $frSections,
        'NFR' => $nfrSections,
        'STRUCTURED' => $structured
    ];
}