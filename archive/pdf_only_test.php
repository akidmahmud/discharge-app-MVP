<?php
// Pure mPDF test - no ProcessWire bootstrap conflict
$rootPath = 'C:/laragon/www/discharge-app';
require_once($rootPath . '/vendor/autoload.php');

echo "Mpdf class exists: " . (class_exists('\Mpdf\Mpdf') ? "YES" : "NO") . "\n";

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'   => 'utf-8',
        'format' => 'A4',
        'margin_top' => 16,
        'margin_bottom' => 16,
        'margin_left' => 16,
        'margin_right' => 16,
    ]);
    
    $html = '<h1 style="color:#2563EB;">CLINICAL DISCHARGE SUMMARY</h1>';
    $html .= '<p><b>Patient:</b> Baby, Yasmika | <b>IP:</b> IP-20260420-007</p>';
    $html .= '<p><b>Diagnosis:</b> Right Supracondylar Fracture Humerus</p>';
    $html .= '<p><b>Procedure:</b> Closed Reduction & K-wire Fixation</p>';
    $html .= '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
    
    $mpdf->WriteHTML($html);
    $pdfBytes = $mpdf->Output('', 'S'); // return as string
    
    $header4 = substr($pdfBytes, 0, 4);
    $sizeKB = round(strlen($pdfBytes) / 1024, 1);
    
    echo "PDF generated: " . ($header4 === '%PDF' ? "PASS" : "FAIL") . "\n";
    echo "PDF header: '$header4'\n";
    echo "PDF size: {$sizeKB} KB\n";
    
    // Save to verify it's a real PDF
    file_put_contents($rootPath . '/pdf_test_output.pdf', $pdfBytes);
    echo "PDF saved to pdf_test_output.pdf\n";
    
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
