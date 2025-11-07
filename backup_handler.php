<?php
/**
 * Backup and Restore Handler
 * Handles creating backups and restoring from backups
 * Optimized for large files using streaming
 */

// Disable error display - we'll return JSON errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Start output buffering to catch any unexpected output
ob_start();

require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');

// Initialize session
initSecureSession();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Set execution time limit for large backups
set_time_limit(0);
ini_set('memory_limit', '512M');

/**
 * Get list of available backups
 */
function getBackupList() {
    global $config;
    $backups = [];
    $backupDir = $config['backup_dir'];
    
    // Ensure backup directory exists
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            error_log("Failed to create backup directory: " . $backupDir);
            return $backups;
        }
    }
    
    // Normalize path separator
    $backupDir = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR;
    
    // Check if directory is readable
    if (!is_readable($backupDir)) {
        error_log("Backup directory is not readable: " . $backupDir);
        return $backups;
    }
    
    $pattern = $backupDir . 'backup_*.zip';
    $files = glob($pattern);
    
    if ($files === false) {
        error_log("Failed to glob backup files: " . $pattern);
        return $backups;
    }
    
    foreach ($files as $file) {
        if (is_file($file) && is_readable($file)) {
            try {
                $backups[] = [
                    'filename' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'created' => filemtime($file),
                    'formatted_size' => formatBytes(filesize($file)),
                    'formatted_date' => date('Y-m-d H:i:s', filemtime($file))
                ];
            } catch (Exception $e) {
                error_log("Error processing backup file " . $file . ": " . $e->getMessage());
                continue;
            }
        }
    }
    
    // Sort by creation date (newest first)
    usort($backups, function($a, $b) {
        return $b['created'] - $a['created'];
    });
    
    return $backups;
}

/**
 * Create a backup of images, shares, and links
 */
function createFullBackup() {
    global $config;
    
    // Check if ZIP extension is available
    if (!extension_loaded('zip')) {
        return ['success' => false, 'error' => 'ZIP extension is not available'];
    }
    
    $backupDir = $config['backup_dir'];
    
    // Normalize path separator
    $backupDir = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR;
    
    // Ensure backup directory exists
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            error_log("Failed to create backup directory: " . $backupDir);
            return ['success' => false, 'error' => 'Failed to create backup directory: ' . $backupDir];
        }
    }
    
    // Check if directory is writable
    if (!is_writable($backupDir)) {
        error_log("Backup directory is not writable: " . $backupDir);
        return ['success' => false, 'error' => 'Backup directory is not writable: ' . $backupDir];
    }
    
    // Generate backup filename with timestamp
    $timestamp = date('Y-m-d_His');
    $backupFile = $backupDir . 'backup_' . $timestamp . '.zip';
    
    // Create ZIP archive
    $zip = new ZipArchive();
    $result = $zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== TRUE) {
        return ['success' => false, 'error' => 'Failed to create backup file: ' . $result];
    }
    
    $stats = [
        'images' => 0,
        'shares' => 0,
        'links' => 0,
        'total_files' => 0,
        'total_size' => 0
    ];
    
    try {
        // Backup images directory
        $uploadDir = $config['upload_dir'];
        // Convert relative path to absolute if needed
        if (!is_dir($uploadDir)) {
            $uploadDir = __DIR__ . '/' . ltrim($uploadDir, '/');
        }
        
        if (is_dir($uploadDir)) {
            $uploadDirNormalized = realpath($uploadDir);
            if ($uploadDirNormalized === false) {
                error_log("Failed to resolve upload directory path: " . $uploadDir);
                $uploadDirNormalized = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;
            } else {
                $uploadDirNormalized = rtrim($uploadDirNormalized, '/\\') . DIRECTORY_SEPARATOR;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    try {
                        $filePath = $file->getRealPath();
                        $relativePath = 'img/' . str_replace($uploadDirNormalized, '', str_replace('\\', '/', $filePath));
                        $zip->addFile($filePath, $relativePath);
                        $stats['images']++;
                        $stats['total_files']++;
                        $stats['total_size'] += $file->getSize();
                    } catch (Exception $e) {
                        error_log("Error adding file to backup: " . $filePath . " - " . $e->getMessage());
                        continue;
                    }
                }
            }
        }
        
        // Backup shares directory
        $shareDir = $config['share_dir'];
        // Convert relative path to absolute if needed
        if (!is_dir($shareDir)) {
            $shareDir = __DIR__ . '/' . ltrim($shareDir, '/');
        }
        
        if (is_dir($shareDir)) {
            $shareDirNormalized = realpath($shareDir);
            if ($shareDirNormalized === false) {
                error_log("Failed to resolve share directory path: " . $shareDir);
                $shareDirNormalized = rtrim($shareDir, '/\\') . DIRECTORY_SEPARATOR;
            } else {
                $shareDirNormalized = rtrim($shareDirNormalized, '/\\') . DIRECTORY_SEPARATOR;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($shareDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    try {
                        $filePath = $file->getRealPath();
                        $relativePath = 'shares/' . str_replace($shareDirNormalized, '', str_replace('\\', '/', $filePath));
                        $zip->addFile($filePath, $relativePath);
                        $stats['shares']++;
                        $stats['total_files']++;
                        $stats['total_size'] += $file->getSize();
                    } catch (Exception $e) {
                        error_log("Error adding file to backup: " . $filePath . " - " . $e->getMessage());
                        continue;
                    }
                }
            }
        }
        
        // Backup links directory
        $linksDir = $config['links_dir'];
        // Convert relative path to absolute if needed
        if (!is_dir($linksDir)) {
            $linksDir = __DIR__ . '/' . ltrim($linksDir, '/');
        }
        
        if (is_dir($linksDir)) {
            $linksDirNormalized = realpath($linksDir);
            if ($linksDirNormalized === false) {
                error_log("Failed to resolve links directory path: " . $linksDir);
                $linksDirNormalized = rtrim($linksDir, '/\\') . DIRECTORY_SEPARATOR;
            } else {
                $linksDirNormalized = rtrim($linksDirNormalized, '/\\') . DIRECTORY_SEPARATOR;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($linksDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    try {
                        $filePath = $file->getRealPath();
                        $relativePath = 'links/' . str_replace($linksDirNormalized, '', str_replace('\\', '/', $filePath));
                        $zip->addFile($filePath, $relativePath);
                        $stats['links']++;
                        $stats['total_files']++;
                        $stats['total_size'] += $file->getSize();
                    } catch (Exception $e) {
                        error_log("Error adding file to backup: " . $filePath . " - " . $e->getMessage());
                        continue;
                    }
                }
            }
        }
        
        // Add metadata file
        $metadata = [
            'version' => '1.0',
            'created' => time(),
            'created_date' => date('Y-m-d H:i:s'),
            'stats' => $stats
        ];
        
        $zip->addFromString('backup_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        
        // Close ZIP archive
        $zip->close();
        
        return [
            'success' => true,
            'filename' => basename($backupFile),
            'size' => filesize($backupFile),
            'formatted_size' => formatBytes(filesize($backupFile)),
            'stats' => $stats
        ];
        
    } catch (Exception $e) {
        // Clean up on error
        if (isset($backupFile) && file_exists($backupFile)) {
            @unlink($backupFile);
        }
        error_log("Backup creation failed: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['success' => false, 'error' => 'Backup failed: ' . $e->getMessage()];
    } catch (Error $e) {
        // Clean up on error
        if (isset($backupFile) && file_exists($backupFile)) {
            @unlink($backupFile);
        }
        error_log("Backup creation error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['success' => false, 'error' => 'Backup failed: ' . $e->getMessage()];
    }
}

/**
 * Upload and validate a backup file
 */
function uploadBackupFile() {
    global $config;
    
    // Check if file was uploaded
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No backup file uploaded or upload error occurred'];
    }
    
    $uploadedFile = $_FILES['backup_file'];
    
    // Validate file type
    $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
    if ($extension !== 'zip') {
        return ['success' => false, 'error' => 'Invalid file type. Only ZIP files are allowed.'];
    }
    
    // Validate file size (max 10GB)
    $maxSize = 10 * 1024 * 1024 * 1024; // 10GB
    if ($uploadedFile['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 10GB.'];
    }
    
    // Validate it's a valid ZIP file
    $zip = new ZipArchive();
    $result = $zip->open($uploadedFile['tmp_name']);
    
    if ($result !== TRUE) {
        return ['success' => false, 'error' => 'Invalid ZIP file'];
    }
    
    // Check if it contains backup_metadata.json
    $hasMetadata = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if ($filename === 'backup_metadata.json') {
            $hasMetadata = true;
            break;
        }
    }
    
    $zip->close();
    
    if (!$hasMetadata) {
        return ['success' => false, 'error' => 'Invalid backup file. Missing metadata.'];
    }
    
    // Move uploaded file to backup directory
    $backupDir = $config['backup_dir'];
    $backupDir = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR;
    
    // Ensure backup directory exists
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create backup directory'];
        }
    }
    
    // Generate safe filename
    $originalName = basename($uploadedFile['name']);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
    $timestamp = date('Y-m-d_His');
    $backupFile = $backupDir . 'uploaded_backup_' . $timestamp . '_' . $safeName;
    
    if (!move_uploaded_file($uploadedFile['tmp_name'], $backupFile)) {
        return ['success' => false, 'error' => 'Failed to save uploaded backup file'];
    }
    
    return [
        'success' => true,
        'filename' => basename($backupFile),
        'size' => filesize($backupFile),
        'formatted_size' => formatBytes(filesize($backupFile))
    ];
}

/**
 * Restore from a backup file
 */
function restoreBackup($backupFilename) {
    global $config;
    
    // Check if ZIP extension is available
    if (!extension_loaded('zip')) {
        return ['success' => false, 'error' => 'ZIP extension is not available'];
    }
    
    $backupDir = $config['backup_dir'];
    $backupFile = $backupDir . $backupFilename;
    
    // Validate backup file
    if (!file_exists($backupFile)) {
        return ['success' => false, 'error' => 'Backup file not found'];
    }
    
    // Validate it's a ZIP file
    $zip = new ZipArchive();
    $result = $zip->open($backupFile);
    
    if ($result !== TRUE) {
        return ['success' => false, 'error' => 'Invalid backup file'];
    }
    
    try {
        // Extract to temporary directory first
        $tempDir = sys_get_temp_dir() . '/restore_' . uniqid() . '/';
        if (!mkdir($tempDir, 0755, true)) {
            $zip->close();
            return ['success' => false, 'error' => 'Failed to create temporary directory'];
        }
        
        // Extract all files
        $zip->extractTo($tempDir);
        $zip->close();
        
        $restored = [
            'images' => 0,
            'shares' => 0,
            'links' => 0,
            'total_files' => 0
        ];
        
        // Restore images
        $imgSource = $tempDir . 'img/';
        if (is_dir($imgSource)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($imgSource, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($imgSource, '', $file->getRealPath());
                    $destPath = $config['upload_dir'] . $relativePath;
                    $destDir = dirname($destPath);
                    
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    
                    copy($file->getRealPath(), $destPath);
                    $restored['images']++;
                    $restored['total_files']++;
                }
            }
        }
        
        // Restore shares
        $sharesSource = $tempDir . 'shares/';
        if (is_dir($sharesSource)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sharesSource, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($sharesSource, '', $file->getRealPath());
                    $destPath = $config['share_dir'] . $relativePath;
                    $destDir = dirname($destPath);
                    
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    
                    copy($file->getRealPath(), $destPath);
                    $restored['shares']++;
                    $restored['total_files']++;
                }
            }
        }
        
        // Restore links
        $linksSource = $tempDir . 'links/';
        if (is_dir($linksSource)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($linksSource, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($linksSource, '', $file->getRealPath());
                    $destPath = $config['links_dir'] . $relativePath;
                    $destDir = dirname($destPath);
                    
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    
                    copy($file->getRealPath(), $destPath);
                    $restored['links']++;
                    $restored['total_files']++;
                }
            }
        }
        
        // Clean up temporary directory
        deleteDirectory($tempDir);
        
        return [
            'success' => true,
            'restored' => $restored
        ];
        
    } catch (Exception $e) {
        // Clean up on error
        if (is_dir($tempDir)) {
            deleteDirectory($tempDir);
        }
        return ['success' => false, 'error' => 'Restore failed: ' . $e->getMessage()];
    }
}

/**
 * Delete a backup file
 */
function deleteBackup($backupFilename) {
    global $config;
    
    $backupDir = $config['backup_dir'];
    $backupFile = $backupDir . $backupFilename;
    
    if (!file_exists($backupFile)) {
        return ['success' => false, 'error' => 'Backup file not found'];
    }
    
    // Security check: ensure it's in the backup directory
    $realBackupFile = realpath($backupFile);
    $realBackupDir = realpath($backupDir);
    
    if (strpos($realBackupFile, $realBackupDir) !== 0) {
        return ['success' => false, 'error' => 'Invalid backup file path'];
    }
    
    if (@unlink($backupFile)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to delete backup file'];
    }
}

// Handle requests
// Clear any output that might have been sent
ob_end_clean();
header('Content-Type: application/json');

// Enable error logging for debugging
if (isset($config['debug']) && $config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'list') {
        try {
            $backups = getBackupList();
            echo json_encode(['success' => true, 'backups' => $backups], JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            error_log("Error getting backup list: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to load backups: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
        } catch (Error $e) {
            error_log("Error getting backup list: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to load backups: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log("Error getting backup list: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to load backups: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle file upload separately
    if (isset($_FILES['backup_file']) && $action === 'upload') {
        try {
            $result = uploadBackupFile();
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log("Error uploading backup: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
        }
        exit;
    }
    
    switch ($action) {
        case 'create':
            try {
                $result = createFullBackup();
                echo json_encode($result, JSON_UNESCAPED_SLASHES);
            } catch (Exception $e) {
                error_log("Error creating backup: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Backup creation failed: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
            } catch (Error $e) {
                error_log("Error creating backup: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Backup creation failed: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
            } catch (Throwable $e) {
                error_log("Error creating backup: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Backup creation failed: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
            }
            break;
            
        case 'restore':
            if (!isset($_POST['filename'])) {
                echo json_encode(['success' => false, 'error' => 'Backup filename not provided'], JSON_UNESCAPED_SLASHES);
                break;
            }
            try {
                $result = restoreBackup($_POST['filename']);
                echo json_encode($result, JSON_UNESCAPED_SLASHES);
            } catch (Throwable $e) {
                error_log("Error restoring backup: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Restore failed: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
            }
            break;
            
        case 'delete':
            if (!isset($_POST['filename'])) {
                echo json_encode(['success' => false, 'error' => 'Backup filename not provided'], JSON_UNESCAPED_SLASHES);
                break;
            }
            try {
                $result = deleteBackup($_POST['filename']);
                echo json_encode($result, JSON_UNESCAPED_SLASHES);
            } catch (Throwable $e) {
                error_log("Error deleting backup: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action'], JSON_UNESCAPED_SLASHES);
            break;
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request'], JSON_UNESCAPED_SLASHES);

