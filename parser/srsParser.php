<?php

function parseTextSRS($text) {
    $lines = preg_split('/\r?\n/', $text);
    $frSections = [];
    $structured = [];
    $currentFR = '';
    $currentSection = '';
    $sectionHierarchy = [];

    foreach ($lines as $line) {
        $clean = trim($line);
        if (empty($clean)) continue;

        // Remove bullet points from the beginning
        $cleanWithoutBullet = preg_replace('/^[●•\-\*\s]+/', '', $clean);
        $cleanWithoutBullet = trim($cleanWithoutBullet);

        // Pattern 1: Numbered section headers (e.g., "3. Functional Requirements", "3.1 Phase 1:", "3.1.1 User Management Module")
        if (preg_match('/^(\d+(?:\.\d+)*)\s+([A-Z][^:]*?)(?:\s*[:\-]\s*(.*))?$/i', $cleanWithoutBullet, $m)) {
            $sectionNumber = $m[1];
            $sectionTitle = trim($m[2]);
            $sectionDesc = trim($m[3] ?? '');
            
            // Determine section level based on number of dots
            $level = substr_count($sectionNumber, '.') + 1;
            
            // This is a section header
            $sectionHierarchy[$level] = [
                'number' => $sectionNumber,
                'title' => $sectionTitle,
                'description' => $sectionDesc,
                'level' => $level
            ];
            
            $currentSection = $sectionNumber . ' ' . $sectionTitle;
            $currentFR = '';
            
            // Add to structured output
            $structured[] = [
                'type' => 'section',
                'level' => $level,
                'number' => $sectionNumber,
                'title' => $sectionTitle,
                'description' => $sectionDesc,
                'hierarchy' => array_values(array_filter($sectionHierarchy, function($v, $k) use ($level) {
                    return $k <= $level;
                }, ARRAY_FILTER_USE_BOTH))
            ];
            continue;
        }

        // Pattern 2: FR items (e.g., "FR-01.01: Description")
        if (preg_match('/^(FR-\d+\.\d+)\s*[:\-]\s*(.*)/i', $cleanWithoutBullet, $m)) {
            $currentFR = $m[1];
            $frText = $m[2];
            
            $frSections[$currentFR] = $frText;
            $structured[] = [
                'type' => 'fr',
                'key' => $currentFR,
                'text' => $frText,
                'section' => $currentSection
            ];
            continue;
        }

        // Pattern 3: Continue text for current FR or add to section
        if ($currentFR) {
            $frSections[$currentFR] .= ' ' . $cleanWithoutBullet;
            $structured[] = [
                'type' => 'fr_continuation',
                'key' => $currentFR,
                'text' => $cleanWithoutBullet
            ];
            continue;
        }

        // Pattern 4: Regular text (description, notes, etc.)
        if (!empty($cleanWithoutBullet)) {
            $structured[] = [
                'type' => 'text',
                'text' => $cleanWithoutBullet,
                'section' => $currentSection
            ];
        }
    }

    return [
        'FR' => $frSections,
        'NFR' => [],
        'STRUCTURED' => $structured
    ];
}
?>