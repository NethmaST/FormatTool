<?php

// Load Composer (needed for phpdotenv)
require 'vendor/autoload.php';

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get API key from .env
$apiKey = $_ENV['GEMINI_API_KEY'] ?? null;

if (!$apiKey) {
    die("API key not found");
}

// Error reporting (for development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load functions
require 'functions.php';



// Initialize application state
$parsed = ['FR' => [], 'NFR' => [], 'STRUCTURED' => []];
$showViewer = false;
$errorMessage = '';

/**
 * Extract text content from PDF files
 */
function extractTextFromPDF($filePath) {
    if (!class_exists('\Smalot\PdfParser\Parser')) {
        return "Error: PdfParser library not found. Please install smalot/pdfparser via Composer.";
    }
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    } catch (Exception $e) {
        return "Error parsing PDF: " . $e->getMessage();
    }
}

/**
 * Parse SRS document text into structured requirements
 * Identifies FR (Functional) and NFR (Non-Functional) requirements
 */
function parseTextSRS($text) {
    $lines = preg_split('/\r?\n/', $text);
    $frSections = [];
    $nfrSections = [];
    $structured = [];
    $currentFR = '';
    $currentNFR = '';

    foreach ($lines as $line) {
        $clean = trim($line);
        if (empty($clean)) continue;

        // Match FR-XX or FR-XX.XX pattern
        if (preg_match('/FR[-_\s]?(\d{2}(?:\.\d{2})?)\s*[:\-]?\s*(.*)/i', $clean, $m)) {
            $currentFR = 'FR-' . $m[1];
            $currentNFR = '';
            $frSections[$currentFR] = $m[2];
            $structured[] = ['type' => 'fr', 'key' => $currentFR, 'text' => $m[2]];
        }
        // Match NFR-XX pattern
        elseif (preg_match('/NFR[-_\s]?(\d{2})\s*[:\-]?\s*(.*)/i', $clean, $m)) {
            $currentNFR = 'NFR-' . $m[1];
            $currentFR = '';
            $nfrSections[$currentNFR] = $m[2];
            $structured[] = ['type' => 'nfr', 'key' => $currentNFR, 'text' => $m[2]];
        }
        // Match sub-requirements (bullet points)
        elseif ($currentFR && preg_match('/^\s*[-●•*]\s*(.*)/', $clean, $m)) {
            $frSections[$currentFR] .= ' | ' . $m[1];
            $structured[] = ['type' => 'fr-sub', 'parent' => $currentFR, 'text' => $m[1]];
        }
        // Continuation lines
        elseif (!empty($currentFR) || !empty($currentNFR)) {
            if (!empty($currentFR)) {
                $frSections[$currentFR] .= ' ' . $clean;
                for ($i = count($structured) - 1; $i >= 0; $i--) {
                    if ($structured[$i]['type'] == 'fr' && $structured[$i]['key'] == $currentFR) {
                        $structured[$i]['text'] .= ' ' . $clean;
                        break;
                    }
                }
            } else {
                $nfrSections[$currentNFR] .= ' ' . $clean;
                for ($i = count($structured) - 1; $i >= 0; $i--) {
                    if ($structured[$i]['type'] == 'nfr' && $structured[$i]['key'] == $currentNFR) {
                        $structured[$i]['text'] .= ' ' . $clean;
                        break;
                    }
                }
            }
        } else {
            $structured[] = ['type' => 'text', 'text' => $clean];
        }
    }

    return ['FR' => $frSections, 'NFR' => $nfrSections, 'STRUCTURED' => $structured];
}

// Process file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['srsFile'])) {
    $file = $_FILES['srsFile']['tmp_name'];
    $fileName = $_FILES['srsFile']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $text = '';

    // Validate file type
    if (!in_array($ext, ['pdf', 'txt'])) {
        $errorMessage = 'Invalid file type. Please upload a PDF or TXT file.';
    } elseif (!is_uploaded_file($file)) {
        $errorMessage = 'File upload failed. Please try again.';
    } else {
        if ($ext === 'pdf') {
            $text = extractTextFromPDF($file);
        } else {
            $text = file_get_contents($file);
        }

        if (!$text || strpos($text, 'Error') === 0) {
            $errorMessage = $text ?: 'Failed to read file content.';
        } else {
            $parsed = parseTextSRS($text);
            $showViewer = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SRS Intelligence Portal - Analyze System Requirements Specifications">
    <title>SRS Intelligence Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Source+Code+Pro:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --primary: #5b21b6;
            --primary-light: #8b5cf6;
            --primary-dark: #6d28d9;
            --fr-accent: #2563eb;
            --fr-light: #eff6ff;
            --nfr-accent: #059669;
            --nfr-light: #ecfdf5;
            --bg-body: #f9fafb;
            --bg-secondary: #f3f4f6;
            --sidebar: #0f172a;
            --text-main: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--sidebar) 0%, #1a2947 100%);
            color: white;
            padding: 2.5rem 1.5rem;
            flex-shrink: 0;
            display: <?php echo $showViewer ? 'flex' : 'none'; ?>;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            position: relative;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 3rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header i {
            font-size: 1.8rem;
            color: var(--primary-light);
            background: rgba(139, 92, 246, 0.1);
            padding: 0.6rem;
            border-radius: 10px;
        }

        .sidebar-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .sidebar h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
            margin: 1.5rem 0 0.75rem 0;
            letter-spacing: 1px;
        }

        .nav-btn {
            background: transparent;
            border: none;
            color: #cbd5e1;
            padding: 0.9rem 1rem;
            text-align: left;
            width: 100%;
            border-radius: 10px;
            cursor: pointer;
            margin-bottom: 0.4rem;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Poppins', sans-serif;
        }

        .nav-btn i {
            width: 18px;
            font-size: 1rem;
        }

        .nav-btn:hover {
            background: rgba(139, 92, 246, 0.15);
            color: white;
            transform: translateX(4px);
        }

        .nav-btn.active {
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.75rem;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar-footer a:hover {
            color: white;
            background: rgba(139, 92, 246, 0.1);
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex-grow: 1;
            padding: 3rem;
            overflow-y: auto;
            background: var(--bg-body);
        }

        /* ===== UPLOAD PAGE ===== */
        .upload-container {
            max-width: 700px;
            margin: 80px auto;
            background: var(--white);
            padding: 60px 45px;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }

        .upload-icon {
            font-size: 4rem;
            color: var(--primary-light);
            margin-bottom: 1.5rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .upload-container h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0 0 0.5rem 0;
            letter-spacing: -1px;
        }

        .upload-container > p {
            color: var(--text-secondary);
            font-size: 1.05rem;
            margin: 0 0 2.5rem 0;
        }

        .drop-zone {
            border: 2px dashed var(--primary-light);
            padding: 50px 30px;
            border-radius: 16px;
            margin: 30px 0;
            transition: all 0.3s ease;
            cursor: pointer;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(139, 92, 246, 0) 100%);
        }

        .drop-zone:hover {
            border-color: var(--primary);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.2);
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
        }

        .drop-zone p {
            margin: 0;
            font-weight: 500;
            color: var(--text-main);
        }

        /* ===== ERROR MESSAGE ===== */
        .error-alert {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .error-alert i {
            font-size: 1.2rem;
        }

        /* ===== ANALYSIS HEADER ===== */
        .analysis-header {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid var(--border);
        }

        .analysis-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0 0 1rem 0;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.fr {
            background: var(--fr-light);
            color: var(--fr-accent);
        }

        .stat-icon.nfr {
            background: var(--nfr-light);
            color: var(--nfr-accent);
        }

        .stat-content h3 {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0 0 0.25rem 0;
            text-transform: uppercase;
            font-weight: 600;
        }

        .stat-content p {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        /* ===== CARDS ===== */
        .card {
            background: var(--white);
            border-radius: 14px;
            padding: 1.8rem;
            margin-bottom: 1.2rem;
            box-shadow: var(--shadow-sm);
            border-top: 4px solid var(--border);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .card.fr-type {
            border-top-color: var(--fr-accent);
            background: linear-gradient(to right, var(--fr-light), var(--white));
        }

        .card.nfr-type {
            border-top-color: var(--nfr-accent);
            background: linear-gradient(to right, var(--nfr-light), var(--white));
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
        }

        .badge {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 700;
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            display: inline-block;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .badge-fr {
            background: var(--fr-light);
            color: var(--fr-accent);
        }

        .badge-nfr {
            background: var(--nfr-light);
            color: var(--nfr-accent);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            font-family: 'Source Code Pro', monospace;
        }

        .card-content {
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 1rem 0 0 0;
            font-size: 0.95rem;
        }

        .sub-requirement {
            margin-left: 1rem;
            margin-top: 0.8rem;
            padding: 0.75rem 1rem;
            background: var(--bg-secondary);
            border-left: 3px solid var(--primary-light);
            font-size: 0.9rem;
            color: var(--text-secondary);
            border-radius: 6px;
            transition: all 0.2s;
        }

        .sub-requirement:hover {
            background: var(--bg-body);
            padding-left: 1.25rem;
        }

        /* ===== BUTTONS ===== */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 0.9rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(91, 33, 182, 0.3);
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(91, 33, 182, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-analyze {
            background: linear-gradient(135deg, var(--fr-accent) 0%, #1d4ed8 100%);
            color: white;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 1rem;
        }

        .btn-analyze:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.5);
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }

        .btn-analyze:active {
            transform: translateY(0);
        }

        /* ===== SVO ANALYSIS ===== */
        .svo-result {
            margin-top: 1rem;
            padding: 1.2rem;
            background: var(--bg-secondary);
            border-radius: 10px;
            border-left: 4px solid var(--primary-light);
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .svo-result strong {
            color: var(--text-main);
            display: block;
            margin-bottom: 0.5rem;
        }

        /* ===== VIEW SECTIONS ===== */
        .view-section {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--border);
            margin-bottom: 1rem;
        }

        .empty-state p {
            margin: 1rem 0;
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.5);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .sidebar {
                width: 240px;
                padding: 1.5rem 1rem;
            }

            .main-content {
                padding: 1.5rem;
            }

            .upload-container {
                margin: 40px 20px;
                padding: 40px 25px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .analysis-header h1 {
                font-size: 1.5rem;
            }
        }
   .svo-visual {
    margin-top: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.svo-box {
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 90px;
    text-align: center;
}

.svo-subject { background: #e0f2ff; color: #0369a1; }
.svo-verb { background: #ede9fe; color: #5b21b6; }
.svo-object { background: #ecfdf5; color: #047857; }

.svo-arrow {
    font-size: 1.2rem;
    color: #6b7280;
}

    </style>
</head>
<body>

<?php if ($showViewer): ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-file-contract"></i>
            <div>
                <h2>SRS Analyzer</h2>
            </div>
        </div>

        <h3>Navigation</h3>
        <button class="nav-btn active" data-view="full"><i class="fas fa-layer-group"></i> Full Document</button>
        <button class="nav-btn" data-view="fr"><i class="fas fa-code"></i> Functional (FR)</button>
        <button class="nav-btn" data-view="nfr"><i class="fas fa-shield-halved"></i> Non-Functional (NFR)</button>
        <button class="nav-btn" data-view="clean"><i class="fas fa-list"></i> Clean Requirements</button>

        <div class="sidebar-footer">
            <a href="?"><i class="fas fa-arrow-left"></i> Upload New File</a>
        </div>
    </div>
<?php endif; ?>

<div class="main-content">
    <?php if (!$showViewer): ?>
        <div class="upload-container">
            <div class="upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h1>Upload SRS Document</h1>
            <p>Analyze your System Requirements Specification for functional and non-functional requirements</p>
            
            <?php if ($errorMessage): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($errorMessage); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="drop-zone" onclick="document.getElementById('fileInput').click()">
                    <p id="fileName">📄 Drag & drop your file here or click to browse</p>
                    <input type="file" name="srsFile" id="fileInput" accept=".pdf,.txt" required style="display:none;" onchange="updateFileName()">
                </div>
                <button class="btn-primary" type="submit"><i class="fas fa-play"></i> Begin Analysis</button>
            </form>
        </div>
    <?php else: ?>
        <div class="analysis-header">
            <h1><i class="fas fa-chart-bar"></i> Analysis Results</h1>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon fr"><i class="fas fa-code"></i></div>
                    <div class="stat-content">
                        <h3>Functional Requirements</h3>
                        <p><?php echo count($parsed['FR']); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon nfr"><i class="fas fa-shield-halved"></i></div>
                    <div class="stat-content">
                        <h3>Non-Functional Requirements</h3>
                        <p><?php echo count($parsed['NFR']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div id="full" class="view-section">
            <?php if (empty($parsed['STRUCTURED'])): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No requirements found in the document</p>
                </div>
            <?php else: ?>
                <?php foreach ($parsed['STRUCTURED'] as $item): ?>
    <?php if ($item['type'] == 'fr'): ?>
        <div class="card fr-type">
            ...
        </div>

    <?php elseif ($item['type'] == 'fr-sub'): ?>
        <?php continue; // skip sub-requirements in full view ?>

    <?php elseif ($item['type'] == 'nfr'): ?>
                        <div class="card nfr-type">
                            <div class="card-header">
                                <span class="badge badge-nfr"><i class="fas fa-shield-halved"></i> Non-Functional</span>
                                <h3 class="card-title"><?php echo htmlspecialchars($item['key']); ?></h3>
                            </div>
                            <div class="card-content">
                                <?php echo htmlspecialchars($item['text']); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); margin: 2rem 0; padding: 1rem; background: var(--bg-secondary); border-radius: 8px;">
                            <?php echo htmlspecialchars($item['text']); ?>
                        </p>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="fr" class="view-section" style="display:none;">
            <?php if (empty($parsed['FR'])): ?>
                <div class="empty-state">
                    <i class="fas fa-code"></i>
                    <p>No Functional Requirements found</p>
                </div>
            <?php else: ?>
                <?php foreach ($parsed['FR'] as $k => $d):
                    $parts = explode('|', $d); ?>
                    <div class="card fr-type">
                        <div class="card-header">
                            <span class="badge badge-fr"><i class="fas fa-code"></i> FR</span>
                            <h3 class="card-title"><?php echo htmlspecialchars($k); ?></h3>
                        </div>
                        <div class="card-content">
                            <?php echo htmlspecialchars($d); ?>
                        </div>
                        <?php foreach ($parts as $sub): ?>
                            <div class="sub-requirement">
                                <i class="fas fa-check"></i> <?php echo htmlspecialchars(trim($sub)); ?>
                            </div>
                        <?php endforeach; ?>
                        <button class="btn-analyze" data-text="<?php echo htmlspecialchars($d); ?>">
                            <i class="fas fa-brain"></i> Analyze SVO
                        </button>
                        <div class="svo-visual"></div>
                        <div class="svo-result"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="nfr" class="view-section" style="display:none;">
            <?php if (empty($parsed['NFR'])): ?>
                <div class="empty-state">
                    <i class="fas fa-shield-halved"></i>
                    <p>No Non-Functional Requirements found</p>
                </div>
            <?php else: ?>
                <?php foreach ($parsed['NFR'] as $k => $d): ?>
                    <div class="card nfr-type">
                        <div class="card-header">
                            <span class="badge badge-nfr"><i class="fas fa-shield-halved"></i> NFR</span>
                            <h3 class="card-title"><?php echo htmlspecialchars($k); ?></h3>
                        </div>
                        <div class="card-content">
                            <?php echo htmlspecialchars($d); ?>
                        </div>
                        <button class="btn-analyze" data-text="<?php echo htmlspecialchars($d); ?>">
                            <i class="fas fa-brain"></i> Analyze SVO
                        </button>
                        <div class="svo-visual"></div>
                        <div class="svo-result"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="clean" class="view-section" style="display:none;">
            <?php
            $allRequirements = [];
            foreach ($parsed['FR'] as $req) {
                $parts = explode('|', $req);
                foreach ($parts as $p) {
                    $allRequirements[] = trim($p);
                }
            }
            foreach ($parsed['NFR'] as $req) {
                $allRequirements[] = trim($req);
            }
            ?>

            <?php if (!empty($allRequirements)): ?>
                <h2>Clean Requirements (Point-wise)</h2>
                <ul style="line-height:1.8;">
                <?php foreach ($allRequirements as $requirement): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($requirement); ?></strong>
                    </li>
                <?php endforeach; ?>
                </ul>

                <!-- Download PDF Button -->
                <form method="post" action="download.php">
                    <input type="hidden" name="requirements" value="<?php echo htmlspecialchars(json_encode($allRequirements)); ?>">
                    <button type="submit" class="btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-list"></i>
                    <p>No requirements found</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {

    // Update file input display name
    function updateFileName() {
        const input = document.getElementById('fileInput');
        if(input && input.files[0]) {
            document.getElementById('fileName').innerText = input.files[0].name;
        }
    }
    window.updateFileName = updateFileName;

    // NAVIGATION VIEW SWITCH (FIXED)
    const navButtons = document.querySelectorAll('.nav-btn');
    navButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            navButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const view = this.getAttribute('data-view');

            document.querySelectorAll('.view-section').forEach(section => {
                section.style.display = (section.id === view) ? 'block' : 'none';
            });
        });
    });

    // DRAG AND DROP
    const dropZone = document.querySelector('.drop-zone');
    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = 'var(--primary)';
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.style.borderColor = 'var(--primary-light)';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            const input = document.getElementById('fileInput');
            if (e.dataTransfer.files.length > 0) {
                input.files = e.dataTransfer.files;
                updateFileName();
            }
        });
    }

    // SVO ANALYSIS AJAX (UNCHANGED LOGIC)
    document.querySelectorAll('.btn-analyze').forEach(btn => {
        btn.addEventListener('click', function () {
            const text = this.getAttribute('data-text');
            const resultBox = this.parentElement.querySelector('.svo-result');
            const visual = this.parentElement.querySelector('.svo-visual');

            resultBox.innerHTML = "🔄 Analyzing...";

            fetch('analyze.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'text=' + encodeURIComponent(text)
            })
            .then(response => response.json())
            .then(data => {

                if (!data.success) {
                    resultBox.innerHTML = "❌ " + data.error;
                    return;
                }

                resultBox.innerHTML =
    "<strong>SVO:</strong> " +
    data.subject + " → " +
    data.verb + " → " +
    data.object;

                
                if (data.subject && data.verb && data.object) {
                    visual.innerHTML = `
                        <div class="svo-box svo-subject">${data.subject}</div>
                        <div class="svo-arrow"><i class="fas fa-long-arrow-alt-right"></i></div>
                        <div class="svo-box svo-verb">${data.verb}</div>
                        <div class="svo-arrow"><i class="fas fa-long-arrow-alt-right"></i></div>
                        <div class="svo-box svo-object">${data.object}</div>
                    `;
                }
            })
            .catch(() => {
                resultBox.innerHTML = "❌ Error analyzing requirement.";
            });
        });
    });

});
</script>
</body>
</html>