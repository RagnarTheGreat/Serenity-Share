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

    // Check if we're downloading a single file
    if (isset($_GET['file'])) {
        // Find the file in metadata
        $fileToDownload = null;
        foreach ($metadata['files'] as $file) {
            if ($file['name'] === $_GET['file']) {
                $fileToDownload = $file;
                break;
            }
        }
        
        if (!$fileToDownload) {
            throw new Exception('File not found in share');
        }
        
        // Get the file path
        $filePath = $sharePath . DIRECTORY_SEPARATOR . $fileToDownload['path'];
        if (!file_exists($filePath)) {
            throw new Exception('File not found on server');
        }
        
        // Get file mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        // Set headers
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($fileToDownload['name']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Send file
        readfile($filePath);
        exit;
    }

    // If we get here, we're downloading all files as a ZIP
    // Create new ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('Cannot create zip file');
    }

    // Add files
    foreach ($metadata['files'] as $file) {
        $filePath = $sharePath . DIRECTORY_SEPARATOR . $file['path'];
        if (file_exists($filePath)) {
            $zip->addFile($filePath, $file['path']);
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
