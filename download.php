<?php
require_once('config.php');
require_once('includes/utilities.php');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('No share ID provided');
    }

    $shareId = $_GET['id'];
    $sharePath = $config['share_dir'] . $shareId;
    $metadataPath = $sharePath . '/metadata.json';

    if (!file_exists($metadataPath)) {
        throw new Exception('Share not found');
    }

    $metadata = json_decode(file_get_contents($metadataPath), true);
    if (!$metadata || $metadata['expires'] < time()) {
        throw new Exception('Share has expired');
    }

    // Set the zip name and path
    $zipName = 'share_' . $shareId . '.zip';
    $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

    // Create new ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('Cannot create zip file');
    }

    // Add files
    foreach ($metadata['files'] as $file) {
        $filePath = $sharePath . DIRECTORY_SEPARATOR . $file['name'];
        if (file_exists($filePath)) {
            $zip->addFile($filePath, basename($file['name']));
        }
    }

    // Close ZIP
    $zip->close();

    // Send headers
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Send file
    readfile($zipPath);
    
    // Delete ZIP file
    unlink($zipPath);
    exit;

} catch (Exception $e) {
    error_log('Download error: ' . $e->getMessage());
    echo 'Error: ' . $e->getMessage();
}
