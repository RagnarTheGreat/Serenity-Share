<?php

if (!function_exists('formatSize')) {
    function formatSize($bytes) {
        if ($bytes > 1024*1024*1024) {
            return round($bytes / (1024*1024*1024), 2) . " GB";
        }
        if ($bytes > 1024*1024) {
            return round($bytes / (1024*1024), 2) . " MB";
        }
        if ($bytes > 1024) {
            return round($bytes / 1024, 2) . " KB";
        }
        return $bytes . " B";
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getDiskUsage() {
    global $config;
    $total_space = disk_total_space($config['upload_dir']);
    $free_space = disk_free_space($config['upload_dir']);
    $used_space = $total_space - $free_space;
    
    return [
        'total' => formatSize($total_space),
        'used' => formatSize($used_space),
        'free' => formatSize($free_space),
        'percentage' => round(($used_space / $total_space) * 100, 2)
    ];
}

function cleanupExpiredShares() {
    global $config;
    
    $shares = glob($config['share_dir'] . '*', GLOB_ONLYDIR);
    $cleaned = 0;
    
    foreach ($shares as $sharePath) {
        $metadataPath = $sharePath . '/metadata.json';
        if (!file_exists($metadataPath)) continue;
        
        $metadata = json_decode(file_get_contents($metadataPath), true);
        if (time() > $metadata['expires']) {
            deleteDirectory($sharePath);
            $cleaned++;
        }
    }
    
    return $cleaned;
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    
    return rmdir($dir);
}

function formatDuration($seconds) {
    $days = floor($seconds / (24 * 60 * 60));
    $hours = floor(($seconds % (24 * 60 * 60)) / (60 * 60));
    
    if ($days > 0) {
        return "$days days" . ($hours > 0 ? " $hours hours" : '');
    }
    return "$hours hours";
}

if (!function_exists('validateInput')) {
    function validateInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

function optimizeImage($filepath) {
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    
    // Only process supported image types
    if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
        return false;
    }
    
    // Load image based on type
    switch($extension) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($filepath);
            break;
        case 'png':
            $image = imagecreatefrompng($filepath);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Only optimize if image is larger than 2000px in either dimension
    if ($width > 2000 || $height > 2000) {
        $ratio = $width / $height;
        if ($width > $height) {
            $new_width = 2000;
            $new_height = 2000 / $ratio;
        } else {
            $new_height = 2000;
            $new_width = 2000 * $ratio;
        }
        
        // Create new image with new dimensions
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($extension === 'png') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
        }
        
        // Resize
        imagecopyresampled(
            $new_image, $image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $width, $height
        );
        
        // Save optimized image
        switch($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($new_image, $filepath, 85); // 85% quality
                break;
            case 'png':
                imagepng($new_image, $filepath, 7); // Compression level 7
                break;
        }
        
        imagedestroy($new_image);
    }
    
    imagedestroy($image);
    return true;
}

function getMemoryCache($key) {
    static $cache = [];
    return isset($cache[$key]) ? $cache[$key] : false;
}

function setMemoryCache($key, $value) {
    static $cache = [];
    $cache[$key] = $value;
}

function generateUniqueId($length = 10) {
    $bytes = random_bytes(ceil($length / 2));
    $id = substr(bin2hex($bytes), 0, $length);
    
    // Ensure the ID doesn't already exist
    global $config;
    while (is_dir($config['share_dir'] . $id)) {
        $bytes = random_bytes(ceil($length / 2));
        $id = substr(bin2hex($bytes), 0, $length);
    }
    
    return $id;
}

function createShare($files, $metadata) {
    global $config;
    
    error_log("CreateShare called with metadata: " . print_r($metadata, true));
    
    try {
        $shareId = generateUniqueId();
        $sharePath = $config['share_dir'] . $shareId;
        
        // Handle never expire option
        if (isset($metadata['expires']) && $metadata['expires'] === -1) {
            // Set to a far future date (Year 2100)
            $metadata['expires'] = 4102444800; // January 1, 2100
        }
        
        if (!is_dir($config['share_dir'])) {
            if (!mkdir($config['share_dir'], 0755, true)) {
                throw new Exception("Failed to create shares directory");
            }
        }
        
        if (!mkdir($sharePath, 0755, true)) {
            throw new Exception("Failed to create share directory: $sharePath");
        }
        
        foreach ($files as &$file) {
            $destPath = $sharePath . '/' . basename($file['name']);
            error_log("Moving file from {$file['tmp_name']} to $destPath");
            
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                error_log("Failed to move file: " . error_get_last()['message']);
                deleteDirectory($sharePath);
                throw new Exception("Failed to move uploaded file");
            }
            unset($file['tmp_name']);
        }
        
        $metadataPath = $sharePath . '/metadata.json';
        if (!file_put_contents($metadataPath, json_encode($metadata))) {
            error_log("Failed to write metadata to: $metadataPath");
            deleteDirectory($sharePath);
            throw new Exception("Failed to save share metadata");
        }
        
        return [
            'success' => true,
            'id' => $shareId,
            'url' => $config['domain_url'] . 'public_share.php?id=' . $shareId
        ];
        
    } catch (Exception $e) {
        error_log("CreateShare error: " . $e->getMessage());
        throw $e;
    }
}

function displayError($code, $title = '', $message = '') {
    // Update path to point to templates directory
    require_once(dirname(__DIR__) . '/templates/error.php');
    showError($code, $title, $message);
}

function getShareUrl($shareId) {
    global $config;
    return $config['domain_url'] . 'public_share.php?id=' . $shareId;
}

function createBackup() {
    global $config;
    
    try {
        $backupDir = $config['backup_dir'] ?? 'backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . '/backup_' . $timestamp . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Cannot create zip file");
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($config['gallery_dir']),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($config['gallery_dir']));
                
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
        
        return [
            'success' => true,
            'message' => 'Backup created successfully: ' . basename($backupFile)
        ];
        
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Backup failed: " . $e->getMessage()
        ];
    }
}

function getThumbnailUrl($filename) {
    global $config;
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // If it's a video, return a default video thumbnail
    if (in_array($extension, ['mp4', 'webm'])) {
        return $config['domain_url'] . 'assets/images/video-thumbnail.png';
    }
    
    // For images, check if thumbnail exists
    $thumbnailPath = THUMBNAILS_DIR . $filename;
    
    // If thumbnail doesn't exist, create it
    if (!file_exists($thumbnailPath)) {
        createThumbnail($config['upload_dir'] . $filename, $thumbnailPath);
    }
    
    return $config['domain_url'] . 'thumbnails/' . $filename;
}

function createThumbnail($sourcePath, $thumbnailPath) {
    // Get image type
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }

    // Create source image based on file type
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$source) {
        return false;
    }

    // Get original dimensions
    $width = imagesx($source);
    $height = imagesy($source);

    // Calculate new dimensions
    $ratio = $width / $height;
    if ($width > $height) {
        $new_width = THUMBNAIL_SIZE;
        $new_height = (int)(THUMBNAIL_SIZE / $ratio);
    } else {
        $new_height = THUMBNAIL_SIZE;
        $new_width = (int)(THUMBNAIL_SIZE * $ratio);
    }

    // Create new image
    $thumbnail = imagecreatetruecolor((int)$new_width, (int)$new_height);

    // Handle transparency for PNG images
    if ($imageInfo[2] === IMAGETYPE_PNG) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, (int)$new_width, (int)$new_height, $transparent);
    }

    // Resize image
    imagecopyresampled(
        $thumbnail, $source,
        0, 0, 0, 0,
        (int)$new_width, (int)$new_height,
        $width, $height
    );

    // Create thumbnail directory if it doesn't exist
    $thumbnailDir = dirname($thumbnailPath);
    if (!is_dir($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }

    // Save thumbnail based on original image type
    $success = false;
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($thumbnail, $thumbnailPath, 85);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($thumbnail, $thumbnailPath, 6);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($thumbnail, $thumbnailPath);
            break;
    }

    // Clean up
    imagedestroy($source);
    imagedestroy($thumbnail);

    return $success;
}

// Enhanced Discord webhook function with rich embeds
function sendDiscordNotification($filename, $fileUrl) {
    global $config;
    
    // Check if Discord notifications are enabled
    if (!isset($config['discord_notifications']) || !$config['discord_notifications']) {
        return;
    }
    
    // Check if webhook URL is set
    if (empty($config['discord_webhook_url'])) {
        return;
    }
    
    // Get domain from config
    $domain = parse_url($config['domain_url'], PHP_URL_HOST);
    
    // Get file info
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $filePath = $config['upload_dir'] . $filename;
    $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
    
    // Determine file type and emoji
    $fileEmoji = '📁';
    $fileDescription = 'File';
    $color = 0x5865F2; // Discord blue
    
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $fileEmoji = '🖼️';
        $fileDescription = 'Image';
        $color = 0x00ff00; // Green for images
    } elseif (in_array($extension, ['mp4', 'webm'])) {
        $fileEmoji = '🎥';
        $fileDescription = 'Video';
        $color = 0xff6b6b; // Red for videos
    }
    
    // Get visitor info
    $visitorIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $isMobile = preg_match('/(android|iphone|ipad|mobile)/i', strtolower($userAgent));
    $deviceType = $isMobile ? '📱 Mobile' : '💻 Desktop';
    
    // Create rich embed
    $embed = [
        'title' => '📤 New File Uploaded',
        'color' => $color,
        'fields' => [
            [
                'name' => '🌐 Domain',
                'value' => $domain,
                'inline' => true
            ],
            [
                'name' => '📄 Filename',
                'value' => "`{$filename}`",
                'inline' => true
            ],
            [
                'name' => '📊 File Type',
                'value' => "{$fileEmoji} {$fileDescription} ({$extension})",
                'inline' => true
            ],
            [
                'name' => '📏 File Size',
                'value' => formatSize($fileSize),
                'inline' => true
            ],
            [
                'name' => '🖥️ Device',
                'value' => $deviceType,
                'inline' => true
            ],
            [
                'name' => '🔗 Direct Link',
                'value' => "[Click to view]({$fileUrl})",
                'inline' => true
            ]
        ],
        'timestamp' => date('c'),
        'footer' => [
            'text' => 'Serenity Share • Upload Bot',
            'icon_url' => 'https://cdn.discordapp.com/emojis/1234567890123456789.png'
        ]
    ];
    
    // Add image preview for image files
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $embed['image'] = [
            'url' => $fileUrl
        ];
    }
    
    // Send to Discord with rich embed
    $data = [
        'username' => 'Upload Bot',
        'avatar_url' => 'https://cdn.discordapp.com/emojis/1234567890123456789.png',
        'embeds' => [$embed]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['discord_webhook_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: Serenity-Share/1.0'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>

