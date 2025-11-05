<?php
require_once('config.php');
require_once('includes/utilities.php');


if ($config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', 'logs/php_errors.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
}


$filename = isset($_GET['f']) ? basename($_GET['f']) : '';
$filepath = $config['upload_dir'] . $filename;

if ($filename && file_exists($filepath)) {

    logVisitor($filename);
    

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    
   
    while (ob_get_level()) {
        ob_end_clean();
    }
    

    switch($extension) {
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'gif':
            header('Content-Type: image/gif');
            break;
        case 'mp4':
            header('Content-Type: video/mp4');
            break;
        case 'webm':
            header('Content-Type: video/webm');
            break;
        default:
            header("HTTP/1.0 415 Unsupported Media Type");
            include(__DIR__ . '/templates/error.php');
            showError(404, 'File Not Found', 'This file has more commitment issues than your ex. At least it left a 404 note! 💌');
            exit;
    }
    
 
    header('Content-Length: ' . filesize($filepath));
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT');
    

    readfile($filepath);
    exit;
} else {

    include(__DIR__ . '/templates/error.php');
    showError(404, 'File Not Found', 'This file has more commitment issues than your ex. At least it left a 404 note! 💌');
    exit;
}
