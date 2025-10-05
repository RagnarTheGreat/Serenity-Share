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
    // Optional: validate secret key for gallery uploads
    if (isset($_POST['secret_key'])) {
        if (!hash_equals($config['secret_key'], (string)$_POST['secret_key'])) {
            throw new Exception('Invalid secret key');
        }
    }

    $uploadedFiles = [];
    $errors = [];

    // Handle gallery uploads: multiple files in files[]
    if (isset($_FILES['files'])) {
        $names = $_FILES['files']['name'];
        $tmps = $_FILES['files']['tmp_name'];
        $errs = $_FILES['files']['error'];
        $count = is_array($names) ? count($names) : 0;

        for ($i = 0; $i < $count; $i++) {
            $error = $errs[$i];
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = "Upload error for file {$names[$i]}";
                continue;
            }

            $originalName = $names[$i];
            $tmpName = $tmps[$i];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($extension, ALLOWED_TYPES)) {
                $errors[] = "Invalid file type: {$originalName}";
                continue;
            }

            $filename = bin2hex(random_bytes(8)) . '.' . $extension;
            $filepath = $config['upload_dir'] . $filename;

            if (!move_uploaded_file($tmpName, $filepath)) {
                $errors[] = "Failed to move uploaded file: {$originalName}";
                continue;
            }

            $uploadedFiles[] = [
                'name' => $filename,
                'url' => $config['domain_url'] . $config['upload_dir'] . $filename
            ];
        }

        if (count($uploadedFiles) === 0) {
            $message = !empty($errors) ? implode('; ', $errors) : 'No valid files uploaded';
            throw new Exception($message);
        }

        echo json_encode([
            'success' => true,
            'files' => $uploadedFiles,
            'error' => null
        ]);
        exit;
    }

    // Handle ShareX uploads: single file in sharex field
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

    $fileUrl = $config['domain_url'] . 'img/' . $filename;
    
    // Send Discord notification
    sendDiscordNotification($filename, $fileUrl);

    echo json_encode([
        'status' => true,
        'url' => $fileUrl,
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
