

<?php
// Load Composer (needed for phpdotenv)
require 'vendor/autoload.php';

//load parser
require __DIR__ . '/parser/srsParser.php';

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



// Process file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['srsFile'])) {
    if ($_FILES['srsFile']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = "Upload error code: " . $_FILES['srsFile']['error'];
    } elseif (!is_uploaded_file($_FILES['srsFile']['tmp_name'])) {
        $errorMessage = "File not uploaded properly.";
    } else {
        $file = $_FILES['srsFile']['tmp_name'];
        $fileName = $_FILES['srsFile']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $text = '';

        // Validate file type
        if (!in_array($ext, ['pdf', 'txt'])) {
            $errorMessage = 'Invalid file type. Please upload a PDF or TXT file.';
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
            background: linear-gradient(135deg, #f0e7ff 0%, #e8f4f8 50%, #f0e7ff 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        /* ===== UPLOAD PAGE ===== */
        .upload-container {
            max-width: 700px;
            margin: 60px auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 60px 45px;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(91, 33, 182, 0.15),
                        0 0 1px rgba(0, 0, 0, 0.1);
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .upload-icon {
            font-size: 5rem;
            color: var(--primary-light);
            margin-bottom: 1.5rem;
            animation: float 3s ease-in-out infinite;
            filter: drop-shadow(0 10px 20px rgba(139, 92, 246, 0.2));
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        .upload-container h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 0.8rem 0;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .upload-container > p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin: 0 0 3rem 0;
            font-weight: 500;
            line-height: 1.6;
        }

        .drop-zone {
            border: 2px dashed var(--primary-light);
            padding: 60px 40px;
            border-radius: 20px;
            margin: 30px 0;
            transition: all 0.4s ease;
            cursor: pointer;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.08) 0%, rgba(139, 92, 246, 0.02) 100%);
            position: relative;
            overflow: hidden;
        }

        .drop-zone::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 50%, rgba(139, 92, 246, 0.1), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
            pointer-events: none;
        }

        .drop-zone:hover::before {
            opacity: 1;
        }

        .drop-zone:hover {
            border-color: var(--primary);
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.25),
                        inset 0 0 20px rgba(139, 92, 246, 0.05);
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.12) 0%, rgba(139, 92, 246, 0.05) 100%);
            transform: translateY(-3px);
        }

        .drop-zone.drag-over {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(139, 92, 246, 0.08) 100%);
            box-shadow: 0 20px 50px rgba(139, 92, 246, 0.3);
            transform: scale(1.02);
        }

        .drop-zone p {
            margin: 0;
            font-weight: 600;
            color: var(--text-main);
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .drop-zone-hint {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            position: relative;
            z-index: 1;
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

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(91, 33, 182, 0.4);
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background: linear-gradient(135deg, #c4b5fd 0%, #ddd6fe 100%);
            cursor: not-allowed;
            opacity: 0.6;
            box-shadow: none;
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
                <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <p id="fileName">📄 Drag & drop your file here or click to browse</p>
                    <div class="drop-zone-hint">Supports PDF and TXT files • Max 50MB</div>
                    <input type="file" name="srsFile" id="fileInput" accept=".pdf,.txt" required style="display:none;" onchange="handleFileSelect()">
                </div>
                <button class="btn-primary" type="submit" id="submitBtn" disabled><i class="fas fa-play"></i> Begin Analysis</button>
                <div id="fileInfo" style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);"></div>
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
                    <div id="progressContainer" style="margin-top: 15px; display:none;">
    <div style="background: #e5e7eb; border-radius: 10px; overflow: hidden;">
        <div id="progressBar" style="width: 0%; height: 10px; background: linear-gradient(135deg, #2563eb, #1d4ed8); transition: width 0.3s ease;"></div>
    </div>
    <p id="progressText" style="font-size: 0.9rem; margin-top: 6px; color: var(--text-secondary);">Analyzing...</p>
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
        <div class="card-header">
        <span class="badge badge-fr"><i class="fas fa-code"></i> Functional</span>
        <h3 class="card-title"><?php echo htmlspecialchars($item['key']); ?></h3>
    </div>
    <div class="card-content">
        <?php echo htmlspecialchars($item['text']); ?>
    </div>
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
            <div style="margin-bottom:20px;">
    <button class="btn-primary" id="analyzeAllFR">
        <i class="fas fa-brain"></i> Analyze All Functional Requirements
    </button>
</div>
            <?php if (empty($parsed['FR'])): ?>
                <div class="empty-state">
                    <i class="fas fa-code"></i>
                    <p>No Functional Requirements found</p>
                </div>
            <?php else: ?>
      <?php foreach ($parsed['FR'] as $k => $d): ?>
    <div class="card fr-type">
        <div class="card-header">
            <span class="badge badge-fr"><i class="fas fa-code"></i> FR</span>
            <h3 class="card-title"><?php echo htmlspecialchars($k); ?></h3>
        </div>
        <div class="card-content">
            <?php echo htmlspecialchars($d); ?>
        </div>

        <div class="svo-visual"></div>
<div class="svo-result"></div>

<form method="get" action="checker.php">
    <input type="hidden" name="requirement" value="<?php echo htmlspecialchars($d); ?>">
    <button class="btn-analyze" type="submit">
        <i class="fas fa-check-circle"></i> Open Shall/Should Checker
    </button>
</form>
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
document.addEventListener('DOMContentLoaded', function() {
    // Drag and drop functionality
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const submitBtn = document.getElementById('submitBtn');
    const fileInfo = document.getElementById('fileInfo');

    if (dropZone && fileInput) {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('drag-over');
        }

        function unhighlight(e) {
            dropZone.classList.remove('drag-over');
        }

        // Handle dropped files
        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFileSelect();
        }

        // Handle file selection
        window.handleFileSelect = function() {
            const file = fileInput.files[0];
            const fileName = document.getElementById('fileName');
            const fileInfo = document.getElementById('fileInfo');
            
            if (!file) {
                return;
            }

            const validTypes = ['application/pdf', 'text/plain'];
            const fiveMB = 5 * 1024 * 1024;

            if (!validTypes.includes(file.type) && !file.name.endsWith('.pdf') && !file.name.endsWith('.txt')) {
                fileInfo.textContent = '❌ Invalid file type. Please upload a PDF or TXT file.';
                submitBtn.disabled = true;
                return;
            }

            if (file.size > fiveMB) {
                fileInfo.textContent = '❌ File size exceeds 5MB limit.';
                submitBtn.disabled = true;
                return;
            }

            fileName.textContent = '✓ ' + file.name + ' (' + formatFileSize(file.size) + ')';
            fileInfo.textContent = '✓ File ready for upload';
            submitBtn.disabled = false;
        };

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    }

    // Navigation sidebar functionality
    const navBtns = document.querySelectorAll('.nav-btn');
    const viewSections = document.querySelectorAll('.view-section');

    if (navBtns.length > 0 && viewSections.length > 0) {
        navBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                // Remove active class from all buttons
                navBtns.forEach(b => b.classList.remove('active'));
                
                // Add active class to clicked button
                btn.classList.add('active');
                
                // Hide all view sections
                viewSections.forEach(section => section.style.display = 'none');
                
                // Show selected view
                const viewId = btn.getAttribute('data-view');
                const selectedView = document.getElementById(viewId);
                if (selectedView) {
                    selectedView.style.display = 'block';
                }
            });
        });
    }

    // SVO Analysis functionality
    const analyzeAllFRBtn = document.getElementById('analyzeAllFR');
    if (analyzeAllFRBtn) {
        analyzeAllFRBtn.addEventListener('click', analyzeAllFunctionalRequirements);
    }

    function analyzeAllFunctionalRequirements() {
        const frCards = document.querySelectorAll('#fr .card.fr-type');
        let currentIndex = 0;

        function analyzeNext() {
            if (currentIndex >= frCards.length) {
                alert('Analysis complete!');
                return;
            }

            const card = frCards[currentIndex];
            const requirementText = card.querySelector('.card-content').textContent.trim();
            
            // Show progress
            const progressContainer = document.getElementById('progressContainer');
            if (progressContainer) {
                progressContainer.style.display = 'block';
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                const progress = ((currentIndex + 1) / frCards.length) * 100;
                progressBar.style.width = progress + '%';
                progressText.textContent = `Analyzing requirement ${currentIndex + 1} of ${frCards.length}...`;
            }

            analyzeSVO(requirementText, card, () => {
                currentIndex++;
                setTimeout(analyzeNext, 1000); // Small delay between requests
            });
        }

        analyzeNext();
    }

    window.analyzeSVO = function(text, cardElement, callback) {
        const formData = new FormData();
        formData.append('text', text);

        fetch('analyze.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const svoVisual = cardElement.querySelector('.svo-visual');
                const svoResult = cardElement.querySelector('.svo-result');

                svoVisual.innerHTML = `
                    <div class="svo-box svo-subject">${escapeHtml(data.subject || 'N/A')}</div>
                    <div class="svo-arrow">→</div>
                    <div class="svo-box svo-verb">${escapeHtml(data.verb || 'N/A')}</div>
                    <div class="svo-arrow">→</div>
                    <div class="svo-box svo-object">${escapeHtml(data.object || 'N/A')}</div>
                `;

                svoResult.innerHTML = `
                    <strong>SVO Analysis:</strong>
                    Subject: <strong>${escapeHtml(data.subject || 'N/A')}</strong><br>
                    Verb: <strong>${escapeHtml(data.verb || 'N/A')}</strong><br>
                    Object: <strong>${escapeHtml(data.object || 'N/A')}</strong>
                `;
            } else {
                cardElement.querySelector('.svo-result').innerHTML = `<span style="color: red;">Error: ${escapeHtml(data.error)}</span>`;
            }
            if (callback) callback();
        })
        .catch(error => {
            cardElement.querySelector('.svo-result').innerHTML = `<span style="color: red;">Error: ${error.message}</span>`;
            if (callback) callback();
        });
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
</body>
</html>

