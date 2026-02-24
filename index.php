

<?php
require 'functions.php';
?>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if vendor exists (MAMP safety check)
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
}

// 1. INITIALIZE DEFAULTS
$parsed = ['FR' => [], 'NFR' => [], 'STRUCTURED' => []];
$showViewer = false;

// 2. DEFINE FUNCTIONS
function extractTextFromPDF($filePath) {
    if (!class_exists('\Smalot\PdfParser\Parser')) {
        return "Error: PdfParser library not found. Run 'composer require smalot/pdfparser'";
    }
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($filePath);
    return $pdf->getText();
}

function parseTextSRS($text) {
    $lines = preg_split('/\r?\n/', $text);
    $frSections = [];
    $nfrSections = [];
    $structured = [];
    $currentFR = '';
    $currentNFR = '';

    foreach ($lines as $line) {
        $clean = trim($line);
        if ($clean === '') continue;

        // Match FR-XX or FR-XX.XX
        if (preg_match('/FR[-_\s]?(\d{2}(?:\.\d{2})?)\s*[:\-]?\s*(.*)/i', $clean, $m)) {
            $currentFR = 'FR-'.$m[1];
            $frSections[$currentFR] = $m[2];
            $structured[] = ['type'=>'fr','key'=>$currentFR,'text'=>$m[2]];
        }
        // Match NFR-XX
        elseif (preg_match('/NFR[-_\s]?(\d{2})\s*[:\-]?\s*(.*)/i', $clean, $m)) {
            $currentNFR = 'NFR-'.$m[1];
            $nfrSections[$currentNFR] = $m[2];
            $structured[] = ['type'=>'nfr','key'=>$currentNFR,'text'=>$m[2]];
        }
        // Sub-step of FR
        elseif ($currentFR && preg_match('/^\s*[-●•]\s*(.*)/', $clean, $m)) {
            $frSections[$currentFR] .= ' | '.$m[1];
            $structured[] = ['type'=>'fr-sub','parent'=>$currentFR,'text'=>$m[1]];
        }
        // Continuation lines for FR/NFR
        elseif ($currentFR || $currentNFR) {
            if ($currentFR) {
                $frSections[$currentFR] .= ' '.$clean;
                // Update the last structured FR item
                for($i=count($structured)-1;$i>=0;$i--){
                    if($structured[$i]['type']=='fr' && $structured[$i]['key']==$currentFR){
                        $structured[$i]['text'] .= ' ' . $clean;
                        break;
                    }
                }
            } else {
                $nfrSections[$currentNFR] .= ' '.$clean;
                for($i=count($structured)-1;$i>=0;$i--){
                    if($structured[$i]['type']=='nfr' && $structured[$i]['key']==$currentNFR){
                        $structured[$i]['text'] .= ' ' . $clean;
                        break;
                    }
                }
            }
        }
        else {
            $structured[] = ['type'=>'text','text'=>$clean];
        }
    }
    return ['FR'=>$frSections,'NFR'=>$nfrSections,'STRUCTURED'=>$structured];
}

// 3. PROCESS FILE UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_FILES['srsFile']) 
    && !isset($_POST['doAnalyze'])) {

    $file = $_FILES['srsFile']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['srsFile']['name'], PATHINFO_EXTENSION));
    $text = '';

    if ($ext === 'pdf') {
        $text = extractTextFromPDF($file);
    } elseif ($ext === 'txt') {
        $text = file_get_contents($file);
    }

    if ($text) {
        $parsed = parseTextSRS($text);
        $showViewer = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRS Intelligence Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-body: #f8fafc;
            --sidebar: #1e293b;
            --text-main: #334155;
            --white: #ffffff;
            --fr-accent: #3b82f6;
            --nfr-accent: #10b981;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg-body); 
            color: var(--text-main);
            margin: 0; 
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            background: var(--sidebar);
            color: white;
            padding: 2rem 1rem;
            flex-shrink: 0;
            display: <?php echo $showViewer ? 'flex' : 'none'; ?>;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            position: relative;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar h2 { font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.9; display: flex; align-items: center; gap: 10px; }
        
        .nav-btn {
            background: transparent; border: none; color: #94a3b8; padding: 12px 15px;
            text-align: left; width: 100%; border-radius: 8px; cursor: pointer;
            margin-bottom: 0.5rem; transition: 0.3s; font-weight: 500;
        }

        .nav-btn:hover, .nav-btn.active { background: #334155; color: white; }
        .nav-btn i { width: 20px; }

        .main-content { flex-grow: 1; padding: 2rem; overflow-y: auto; }

        .upload-container {
            max-width: 600px; margin: 100px auto; background: white;
            padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);
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
            border: 2px dashed #cbd5e1; padding: 40px 20px; border-radius: 12px;
            margin: 20px 0; transition: 0.3s; cursor: pointer;
        }

        .drop-zone:hover { border-color: var(--primary); background: #f5f3ff; }

        .card {
            background: white; border-radius: 12px; padding: 1.5rem;
            margin-bottom: 1rem; border-left: 5px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: 0.2s;
        }

        .card.fr-type { border-left-color: var(--fr-accent); }
        .card.nfr-type { border-left-color: var(--nfr-accent); }

        .badge {
            font-size: 0.75rem; text-transform: uppercase; font-weight: 700;
            padding: 2px 8px; border-radius: 4px; margin-right: 10px;
        }
        .badge-fr { background: #dbeafe; color: #1e40af; }
        .badge-nfr { background: #dcfce7; color: #166534; }

        .sub-requirement {
            margin-left: 2rem; margin-top: 0.5rem; padding: 8px;
            border-left: 2px solid #e2e8f0; font-size: 0.95rem; color: #64748b;
        }

        /* ===== BUTTONS ===== */
        .btn-primary {
            background: var(--primary); color: white; padding: 12px 24px;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
        }
    </style>
</head>
<body>

<?php if($showViewer): ?>
    <div class="sidebar">
        <h2><i class="fas fa-file-contract"></i> SRS Analyzer</h2>
        <button class="nav-btn active" data-view="full"><i class="fas fa-layer-group"></i> Full Document</button>
        <button class="nav-btn" data-view="fr"><i class="fas fa-code"></i> Functional (FR)</button>
        <button class="nav-btn" data-view="nfr"><i class="fas fa-shield-halved"></i> Non-Functional (NFR)</button>
        <button class="nav-btn" data-view="clean">
    <i class="fas fa-list"></i> Clean Requirements
</button>
        <div style="margin-top: auto;">
            <a href="?" style="color: #94a3b8; text-decoration: none; font-size: 0.9rem;"><i class="fas fa-arrow-left"></i> Upload New File</a>
        </div>
    </div>
<?php endif; ?>

<div class="main-content">
    <?php if(!$showViewer): ?>
        <div class="upload-container">
            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary);"></i>
            <h1>Upload SRS</h1>
            <p style="color: #64748b;">Analyze your System Requirements Specification (PDF or TXT)</p>
            
            <form method="post" enctype="multipart/form-data">
                <div class="drop-zone" onclick="document.getElementById('fileInput').click()">
                    <p id="fileName">Drag & drop or click to browse</p>
                    <input type="file" name="srsFile" id="fileInput" accept=".pdf,.txt" required style="display:none;" onchange="updateFileName()">
                </div>
                <button class="btn-primary" type="submit">Begin Analysis</button>
            </form>
        </div>
    <?php else: ?>
        <header style="margin-bottom: 2rem;">
            <h1>Analysis Overview</h1>
            <p>We detected <strong><?php echo count($parsed['FR']); ?></strong> Functional and <strong><?php echo count($parsed['NFR']); ?></strong> Non-Functional requirements.</p>
        </header>

    <div id="full" class="view-section">
    <?php foreach($parsed['STRUCTURED'] as $item): ?>
        <?php if($item['type']=='fr'): ?>
            <div class="card fr-type">
                <span class="badge badge-fr">Functional</span>
                <strong><?php echo htmlspecialchars($item['key']); ?></strong>

                <p><?php echo htmlspecialchars($item['text']); ?></p>

    
            </div>   

                <?php elseif($item['type']=='fr-sub'): ?>
                    <div class="sub-requirement"><i class="fas fa-arrow-right-long"></i> <?php echo htmlspecialchars($item['text']); ?></div>
                <?php elseif($item['type']=='nfr'): ?>
                    <div class="card nfr-type">
                        <span class="badge badge-nfr">Non-Functional</span> <strong><?php echo htmlspecialchars($item['key']); ?></strong>
                        <p><?php echo htmlspecialchars($item['text']); ?></p>
                    </div>
                <?php else: ?>
                    <p style="color: #94a3b8; margin: 1.5rem 0;"><?php echo htmlspecialchars($item['text']); ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div id="fr" class="view-section" style="display:none;">
            <?php if(empty($parsed['FR'])): echo "<p>No FRs found.</p>"; endif; ?>
            <?php foreach($parsed['FR'] as $k=>$d): 
                $parts = explode('|',$d); ?>
                <div class="card fr-type">
                    <strong><?php echo htmlspecialchars($k); ?></strong>
                 <p><?php echo htmlspecialchars($d); ?></p>

<!-- Analyze Button -->
<button class="btn-primary analyze-btn" data-text="<?php echo htmlspecialchars($item['text']); ?>" style="margin-top:10px;">
    Analyze SVO
</button>
<div class="svo-result"></div>

<?php
if(isset($_POST['doAnalyze']) && $_POST['analyze_text'] === $item['text']) {
    $analysis = analyzeSVO($item['text']);
    echo "<div style='margin-top:10px; padding:10px; background:#f1f5f9; border-radius:8px;'>
            <strong>SVO Analysis:</strong><br>" . htmlspecialchars($analysis) . "
          </div>";
}
?>
                    <?php foreach($parts as $sub): ?>
                        <div class="sub-requirement"><?php echo htmlspecialchars($sub); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="nfr" class="view-section" style="display:none;">
            <?php if(empty($parsed['NFR'])): echo "<p>No NFRs found.</p>"; endif; ?>
            <?php foreach($parsed['NFR'] as $k=>$d): ?>
                <div class="card nfr-type">
                    <strong><?php echo htmlspecialchars($k); ?></strong>
                    <p><?php echo htmlspecialchars($d); ?></p>
                </div>
            <?php endforeach; ?> 
        </div>

       <div id="clean" class="view-section" style="display:none;">
    <h2>Clean Requirements (Point-wise)</h2>

    <?php
    $allRequirements = [];


    foreach($parsed['FR'] as $req){
        $parts = explode('|',$req);
        foreach($parts as $p){
            $allRequirements[] = trim($p);
        }
    }

    foreach($parsed['NFR'] as $req){
        $allRequirements[] = trim($req);
    }
    ?>
    <?php
if(empty($allRequirements)){
    echo "<p>No requirements found to analyze.</p>";
} else {
    echo "<p>Extracted <strong>" . count($allRequirements) . "</strong> requirements for analysis.</p>";
}
?>

<?php
$analysisMap = [];

if (!empty($allRequirements)) {
    foreach ($allRequirements as $req) {
        $analysisMap[$req] = analyzeSVO($req);
    }
}
?>

    <?php if (!empty($analysisData['results'])): ?>
<h2>Clean Requirements (Point-wise)</h2>

<ul style="line-height:1.8;">
<?php foreach ($analysisMap as $requirement => $analysis): ?>
    <li>
        <strong>Requirement:</strong> <?php echo htmlspecialchars($requirement); ?><br>
        <strong>SVO:</strong><br>
        <?php echo $analysis; ?>
        <hr>
    </li>
<?php endforeach; ?>
</ul>

<?php else: ?>
<p>No analysis data returned.</p>
<?php endif; ?>

        <!-- Download PDF Button -->
        <form method="post" action="download.php">
            <input type="hidden" name="requirements" value="<?php echo htmlspecialchars(json_encode($allRequirements)); ?>">
            <button type="submit" class="btn-primary" style="margin-top: 20px;">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </form>
    <?php endif; ?>
</div>

  
    ?>

<script>
function updateFileName() {
    const input = document.getElementById('fileInput');
    if(input.files[0]) {
        document.getElementById('fileName').innerText = input.files[0].name;
    }
}

// NAVIGATION VIEW SWITCH
document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const view = this.dataset.view;
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
        dropZone.style.background = 'linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(139, 92, 246, 0.08) 100%)';
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = 'var(--primary-light)';
        dropZone.style.background = 'linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(139, 92, 246, 0) 100%)';
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

// SVO ANALYSIS AJAX
document.querySelectorAll('.analyze-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const text = this.getAttribute('data-text');
        const resultBox = this.nextElementSibling;

        resultBox.innerHTML = "Analyzing...";

        fetch('analyze.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'text=' + encodeURIComponent(text)
        })
        .then(response => response.text())
        .then(data => {
            resultBox.innerHTML = "<strong>SVO:</strong><br>" + data;
        })
        .catch(() => {
            resultBox.innerHTML = "Error analyzing.";
        });
    });
});
</script>
</body>
</html>