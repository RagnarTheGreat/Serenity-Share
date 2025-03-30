<?php

require_once('config.php');
require_once('includes/utilities.php');


define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm']);


date_default_timezone_set('America/New_York');
ini_set('log_errors', 1);
ini_set('error_log', 'logs/php_errors.log');
error_reporting(E_ALL);


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

try {

    if (!isset($_FILES['sharex']) || $_FILES['sharex']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No file uploaded or upload error occurred");
    }

    $file = $_FILES['sharex'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));


    if (!in_array($extension, ALLOWED_TYPES)) {
        throw new Exception("Invalid file type");
    }


    $filename = bin2hex(random_bytes(8)) . '.' . $extension;
    $filepath = $config['upload_dir'] . $filename;


    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception("Failed to move uploaded file");
    }


    echo json_encode([
        'status' => true,
        'url' => $config['domain_url'] . 'img/' . $filename,
        'deletion_url' => null,
        'error' => null
    ]);
    
} catch (Exception $e) {
    error_log('Upload Error: ' . $e->getMessage());
    echo json_encode([
        'status' => false,
        'url' => null,
        'deletion_url' => null,
        'error' => $e->getMessage()
    ]);
}
