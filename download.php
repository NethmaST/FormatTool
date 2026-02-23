<?php
require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['requirements'])) {
    die("No data available for download.");
}

$requirements = json_decode($_POST['requirements'], true);

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Clean Requirements Report', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 12);

foreach ($requirements as $i => $req) {
    $pdf->MultiCell(0, 10, ($i + 1) . ". " . $req);
}

$pdf->Output('D', 'Requirements.pdf');
exit;
?>