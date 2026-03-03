<?php
$requirement = $_GET['requirement'] ?? '';
$text = strtolower($requirement);

// Check for various requirement keywords
$hasShall = strpos($text, 'shall') !== false;
$hasShould = strpos($text, 'should') !== false;
$hasMust = strpos($text, 'must') !== false;
$hasMay = strpos($text, 'may') !== false;
$hasWill = strpos($text, 'will') !== false;
$isAcceptable = $hasShall || $hasShould;

// Extract keywords
$keywords = [];
if ($hasShall) $keywords[] = 'shall';
if ($hasShould) $keywords[] = 'should';
if ($hasMust) $keywords[] = 'must';
if ($hasMay) $keywords[] = 'may';
if ($hasWill) $keywords[] = 'will';

// Word count
$wordCount = str_word_count($requirement);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shall/Should Checker</title>
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
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-body: #f9fafb;
            --bg-secondary: #f3f4f6;
            --text-main: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100vh;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0e7ff 0%, #e8f4f8 50%, #f0e7ff 100%);
            background-attachment: fixed;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-icon {
            font-size: 4rem;
            color: var(--primary-light);
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
            filter: drop-shadow(0 10px 20px rgba(139, 92, 246, 0.2));
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin: 0;
        }

        /* Status Card */
        .status-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 2px solid var(--border);
            animation: slideUp 0.6s ease-out 0.1s both;
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

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .status-icon.success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: var(--success);
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.2);
        }

        .status-icon.danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: var(--danger);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.2);
        }

        .status-content h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.4rem;
            color: var(--text-main);
        }

        .status-content p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Requirement Display */
        .requirement-box {
            background: var(--bg-secondary);
            border-left: 4px solid var(--primary-light);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            animation: slideUp 0.6s ease-out 0.2s both;
        }

        .requirement-box h3 {
            margin: 0 0 0.75rem 0;
            font-size: 0.9rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 1px;
        }

        .requirement-text {
            margin: 0;
            color: var(--text-main);
            line-height: 1.7;
            font-size: 1rem;
            word-break: break-word;
        }

        /* Keywords Found */
        .keywords-section {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            animation: slideUp 0.6s ease-out 0.3s both;
        }

        .keywords-section h3 {
            margin: 0 0 1rem 0;
            font-size: 1rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 1px;
        }

        .keywords-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .keyword-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }

        .keyword-tag i {
            font-size: 0.9rem;
        }

        .no-keywords {
            color: var(--text-secondary);
            font-style: italic;
            padding: 1rem;
            text-align: center;
            background: var(--bg-secondary);
            border-radius: 10px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
            animation: slideUp 0.6s ease-out 0.4s both;
        }

        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-box {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border-top: 3px solid var(--primary-light);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
        }

        /* Checklist */
        .checklist-section {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            animation: slideUp 0.6s ease-out 0.5s both;
        }

        .checklist-section h3 {
            margin: 0 0 1rem 0;
            font-size: 1rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 1px;
        }

        .checklist-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .checklist-item:last-child {
            border-bottom: none;
        }

        .check-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: bold;
            flex-shrink: 0;
        }

        .check-icon.success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: var(--success);
        }

        .check-icon.danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: var(--danger);
        }

        .check-label {
            flex: 1;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            animation: slideUp 0.6s ease-out 0.6s both;
        }

        @media (max-width: 600px) {
            .button-group {
                flex-direction: column;
            }
        }

        .btn {
            padding: 0.9rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(91, 33, 182, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(91, 33, 182, 0.4);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-main);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--white);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }

            .header-icon {
                font-size: 3rem;
            }

            .status-card {
                padding: 1.5rem;
            }

            .requirement-box {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <div class="header-icon">
            <i class="fas fa-check-double"></i>
        </div>
        <h1>Requirement Validator</h1>
        <p>Shall/Should Analysis & Quality Check</p>
    </div>

    <!-- Status Card -->
    <div class="status-card">
        <div class="status-indicator">
            <div class="status-icon <?php echo $isAcceptable ? 'success' : 'danger'; ?>">
                <?php echo $isAcceptable ? '✓' : '✗'; ?>
            </div>
            <div class="status-content">
                <h2><?php echo $isAcceptable ? 'Valid Requirement' : 'Invalid Requirement'; ?></h2>
                <p>
                    <?php 
                    if ($isAcceptable) {
                        echo $hasShall ? 
                            'Contains "shall" - mandatory requirement ✓' : 
                            'Contains "should" - recommended requirement ✓';
                    } else {
                        echo 'Missing shall/should keywords - not a proper requirement ✗';
                    }
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Requirement Text -->
    <div class="requirement-box">
        <h3><i class="fas fa-file-lines"></i> Requirement Text</h3>
        <p class="requirement-text"><?php echo htmlspecialchars($requirement); ?></p>
    </div>

    <!-- Keywords Found -->
    <div class="keywords-section">
        <h3><i class="fas fa-tag"></i> Keywords Found</h3>
        <div class="keywords-list">
            <?php if (!empty($keywords)): ?>
                <?php foreach ($keywords as $keyword): ?>
                    <span class="keyword-tag">
                        <i class="fas fa-check-circle"></i>
                        <?php echo strtoupper($keyword); ?>
                    </span>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-keywords">No standard requirement keywords found</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-box">
            <p class="stat-label">Word Count</p>
            <div class="stat-value"><?php echo $wordCount; ?></div>
        </div>
        <div class="stat-box">
            <p class="stat-label">Requirement Type</p>
            <div class="stat-value" style="font-size: 1rem; color: var(--text-main);">
                <?php 
                if ($hasShall) echo '🔴 Mandatory';
                elseif ($hasShould) echo '🟡 Recommended';
                else echo '⚪ Unknown';
                ?>
            </div>
        </div>
    </div>

    <!-- Checklist -->
    <div class="checklist-section">
        <h3><i class="fas fa-clipboard-list"></i> Quality Checklist</h3>
        
        <div class="checklist-item">
            <div class="check-icon <?php echo $hasShall || $hasShould ? 'success' : 'danger'; ?>">
                <?php echo $hasShall || $hasShould ? '✓' : '✗'; ?>
            </div>
            <span class="check-label">Contains shall/should keyword</span>
        </div>

        <div class="checklist-item">
            <div class="check-icon <?php echo $wordCount >= 5 ? 'success' : 'danger'; ?>">
                <?php echo $wordCount >= 5 ? '✓' : '✗'; ?>
            </div>
            <span class="check-label">Requirement has sufficient detail (min 5 words)</span>
        </div>

        <div class="checklist-item">
            <div class="check-icon <?php echo !empty($requirement) ? 'success' : 'danger'; ?>">
                <?php echo !empty($requirement) ? '✓' : '✗'; ?>
            </div>
            <span class="check-label">Requirement text is not empty</span>
        </div>

        <div class="checklist-item">
            <div class="check-icon <?php echo $hasShall || $hasShould || $hasMust ? 'success' : 'danger'; ?>">
                <?php echo $hasShall || $hasShould || $hasMust ? '✓' : '✗'; ?>
            </div>
            <span class="check-label">Uses mandatory/recommended language</span>
        </div>
    </div>

    <!-- Buttons -->
    <div class="button-group">
        <a href="index.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Analysis
        </a>
        <button class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print Result
        </button>
    </div>
</div>

</body>
</html>