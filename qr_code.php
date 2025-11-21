<?php
// Suppress all output except the image
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors, just log them
ini_set('log_errors', 1);

require_once('config.php');

// Try to find vendor/autoload.php in multiple possible locations
$vendorPath = null;
$possiblePaths = [
    __DIR__ . '/vendor/autoload.php',  // Same directory as qr_code.php
    dirname(__DIR__) . '/vendor/autoload.php',  // Parent directory
    'vendor/autoload.php',  // Relative path
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $vendorPath = $path;
        break;
    }
}

// Check if vendor/autoload.php exists
if (!$vendorPath) {
    http_response_code(500);
    header('Content-Type: text/plain');
    
    // Provide helpful error message with installation instructions
    $errorMsg = "QR Code library not found.\n\n";
    $errorMsg .= "The vendor directory is missing. This usually happens when:\n";
    $errorMsg .= "1. Files were uploaded without the vendor directory\n";
    $errorMsg .= "2. The vendor directory wasn't included in the download\n\n";
    $errorMsg .= "SOLUTION:\n";
    $errorMsg .= "Make sure you upload ALL files including the 'vendor' folder.\n";
    $errorMsg .= "The vendor folder should be in the same directory as qr_code.php\n";
    $errorMsg .= "If vendor folder is missing, re-download from GitHub (vendor folder should be included)\n";
    
    error_log('QR Code Error: vendor/autoload.php not found. Checked paths: ' . implode(', ', $possiblePaths));
    die($errorMsg);
}

require_once($vendorPath);

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;

// Get the URL to encode from query parameter
$url = isset($_GET['url']) ? urldecode($_GET['url']) : '';
$size = isset($_GET['size']) ? intval($_GET['size']) : 400;

// Validate URL
if (empty($url)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    die('URL parameter is required');
}

// Validate URL format
if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('#^https?://#', $url)) {
    // If not a full URL, prepend domain
    global $config;
    if (strpos($url, '/') === 0) {
        $url = $config['domain_url'] . ltrim($url, '/');
    } else {
        $url = $config['domain_url'] . $url;
    }
}

// Create QR code
try {
    $qrCode = QrCode::create($url)
        ->setEncoding(new Encoding('UTF-8'))
        ->setSize($size)
        ->setMargin(10)
        ->setForegroundColor(new Color(0, 0, 0))
        ->setBackgroundColor(new Color(255, 255, 255));

    $writer = new PngWriter();
    $result = $writer->write($qrCode);

    // Clear any output that might have been generated
    ob_clean();
    
    // Output QR code as PNG image
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    echo $result->getString();
    
    // End output buffering
    ob_end_flush();
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: text/plain');
    error_log('QR Code Error: ' . $e->getMessage());
    ob_end_flush();
    die('Error generating QR code: ' . $e->getMessage());
}
?>

