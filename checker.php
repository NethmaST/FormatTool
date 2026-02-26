<?php
$requirement = $_GET['requirement'] ?? '';
$text = strtolower($requirement);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shall / Should Checker</title>
    <style>
        body { font-family: Arial; padding: 40px; }
        .ok { color: green; font-weight: bold; }
        .bad { color: red; font-weight: bold; }
    </style>
</head>
<body>

<h2>Requirement Checker</h2>

<p><strong>Requirement:</strong> <?php echo htmlspecialchars($requirement); ?></p>

<?php if (strpos($text, 'shall') !== false || strpos($text, 'should') !== false): ?>
    <p class="ok">✅ Requirement is acceptable (contains shall/should)</p>
<?php else: ?>
    <p class="bad">❌ Requirement not acceptable (missing shall/should)</p>
<?php endif; ?>

<a href="index.php">← Back</a>

</body>
</html>