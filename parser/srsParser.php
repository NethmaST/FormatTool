<?php

function parseTextSRS($text) {

    $lines = preg_split('/\r?\n/', $text);

    $frSections = [];
    $nfrSections = [];
    $structured = [];

    $currentFR = '';
    $currentSection = '';

    foreach ($lines as $line) {

        $clean = trim($line);
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
        3. NFR DETECTION (simple)
        ============================
        */

        if (stripos($clean, 'shall') !== false &&
            (stripos($clean, 'performance') !== false ||
             stripos($clean, 'security') !== false ||
             stripos($clean, 'availability') !== false ||
             stripos($clean, 'usability') !== false)) {

            $nfrKey = "NFR-" . (count($nfrSections) + 1);

            $nfrSections[$nfrKey] = $clean;

            $structured[] = [
                'type' => 'nfr',
                'key' => $nfrKey,
                'text' => $clean,
                'section' => $currentSection
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