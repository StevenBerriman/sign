<?php
header('Content-Type: application/json');

$tools = [];

// Check for pdftotext
$pdftotext = shell_exec('which pdftotext 2>/dev/null');
$tools['pdftotext'] = !empty($pdftotext) ? trim($pdftotext) : 'Not available';

// Check for tesseract
$tesseract = shell_exec('which tesseract 2>/dev/null');
$tools['tesseract'] = !empty($tesseract) ? trim($tesseract) : 'Not available';

// Check if shell_exec is enabled
$tools['shell_exec'] = function_exists('shell_exec') ? 'Available' : 'Disabled';

// Check file upload settings
$tools['max_file_size'] = ini_get('upload_max_filesize');
$tools['max_post_size'] = ini_get('post_max_size');

// Check upload directory permissions
$uploadDir = '../uploads/quotes/';
$tools['upload_dir_writable'] = is_writable($uploadDir) ? 'Writable' : 'Not writable';

echo json_encode([
    'extraction_tools' => $tools,
    'recommendations' => [
        'pdftotext' => $tools['pdftotext'] === 'Not available' ? 'Install poppler-utils: sudo apt-get install poppler-utils' : 'OK',
        'tesseract' => $tools['tesseract'] === 'Not available' ? 'Install tesseract: sudo apt-get install tesseract-ocr' : 'OK',
        'shell_exec' => $tools['shell_exec'] === 'Disabled' ? 'Enable shell_exec in PHP configuration' : 'OK'
    ]
]);
?>