<?php
// Suppress all output except the image
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors, just log them
ini_set('log_errors', 1);

require_once('config.php');

// Check if vendor/autoload.php exists
if (!file_exists('vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/plain');
    die('QR Code library not found. Please run: composer install');
}

require_once('vendor/autoload.php');

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

