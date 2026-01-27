<?php
/*
===========================================================
 File: admin/print_qr_pdf.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-18
 Modified: 2026-01-19
 Revision: 1.2

 Description:
   Generates a printable PDF of QR code labels for all boxes
   owned by the current user. Supports multiple label sizes
   selectable via query string.

 Size Modes:
   small  = 4 x 4 (16 per page)
   medium = 2 x 3 (6 per page)
   large  = 2 x 2 (4 per page)

 Changes:
   Rev 1.0 - Initial printable QR label PDF
   Rev 1.1 - Added size modes + fixed column-aware layout
   Rev 1.2 - add authorization
===========================================================
*/

// start auth
require_once __DIR__ . '/../lib/auth.php';
requireLogin();
$currentUser = $_SESSION['user'];
// end auth

date_default_timezone_set('America/New_York');

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/fpdf.php';

// TEMP user (auth later)
$currentUser = 'jason';

$data  = loadBoxData();
$boxes = $data['boxes'] ?? [];

/*
-----------------------------------------------------------
 Label size configuration
-----------------------------------------------------------
*/
$sizeMode = $_GET['size'] ?? 'small';

switch ($sizeMode) {
    case 'large':
        // 2 x 2 = 4 per page
        $qrSize   = 80;
        $paddingX = 30;
        $paddingY = 30;
        $maxCols  = 2;
        break;

    case 'medium':
        // 2 x 3 = 6 per page
        $qrSize   = 65;
        $paddingX = 5;
        $paddingY = 5;
        $maxCols  = 2;
        break;

    case 'small':
    default:
        // 4 x 4 = 16 per page
        $qrSize   = 40;
        $paddingX = 10;
        $paddingY = 10;
        $maxCols  = 4;
        break;
}

/*
-----------------------------------------------------------
 PDF setup
-----------------------------------------------------------
*/
$pdf = new FPDF('P', 'mm', 'Letter');
$pdf->SetMargins(10, 10);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$pdf->SetFont('Arial', '', 9);

// Starting coordinates
$x   = 10;
$y   = 10;
$col = 0;

/*
-----------------------------------------------------------
 Render labels
-----------------------------------------------------------
*/
foreach ($boxes as $code => $box) {

    if (($box['owner'] ?? '') !== $currentUser) {
        continue;
    }

    $qrFile = __DIR__ . '/../qrcodes/' . $code . '.png';

    if (!file_exists($qrFile)) {
        continue;
    }

    // Page break if needed
    if ($y + $qrSize + 20 > 260) {
        $pdf->AddPage();
        $x   = 10;
        $y   = 10;
        $col = 0;
    }

    // Draw QR
    $pdf->Image($qrFile, $x, $y, $qrSize, $qrSize);

    // Label text (centered)
    $pdf->SetXY($x, $y + $qrSize + 2);
    $pdf->MultiCell(
        $qrSize,
        5,
        ($box['name'] ?? 'Unnamed Box') . "\n" . $code,
        0,
        'C'
    );

    // Advance column
    $col++;

    if ($col >= $maxCols) {
        // New row
        $col = 0;
        $x   = 10;
        $y  += $qrSize + $paddingY + 10;
    } else {
        // Next column
        $x  += $qrSize + $paddingX;
    }
}

/*
-----------------------------------------------------------
 Output PDF
-----------------------------------------------------------
*/
$pdf->Output('I', 'storage-box-qr-labels-' . $sizeMode . '.pdf');
