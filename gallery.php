<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');
require_once('templates/error.php');

// Add this to debug
error_log("Gallery.php started");

// Initialize secure session
initSecureSession();

// Check if user is logged in
if (!validateSession()) {
    error_log("Session validation failed");
    showError(403, 'Access Denied', 'Please log in to access the gallery.');
    exit;
}

// Add this to check configuration
error_log("Config upload_dir: " . $config['upload_dir']);
error_log("Config debug: " . ($config['debug'] ? 'true' : 'false'));

// Add these constants
define('THUMBNAIL_SIZE', 200);
define('THUMBNAILS_DIR', 'thumbnails/');
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm']);

// Add basic error handling
if ($config['debug']) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Check if upload directory exists and is writable
if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0755, true);
}

if (!is_writable($config['upload_dir'])) {
    showError(500, 'Configuration Error', 'Upload directory is not writable');
    exit;
}

// Make sure thumbnails directory exists
if (!is_dir(THUMBNAILS_DIR)) {
    mkdir(THUMBNAILS_DIR, 0755, true);
}

if (!is_writable(THUMBNAILS_DIR)) {
    showError(500, 'Configuration Error', 'Thumbnails directory is not writable');
    exit;
}

// Check IP whitelist
checkIPWhitelist();

// IP Whitelist Check
function checkIPWhitelist() {
    global $config;
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (!isset($config['admin_ips']) || !is_array($config['admin_ips'])) {
        error_log("Warning: admin_ips configuration is missing or invalid");
        return;
    }
    
    if (!in_array($ip, $config['admin_ips'])) {
        showError(403, 'Access Denied', 'Your IP is not whitelisted.');
        exit;
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_files'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid security token';
        header('Location: gallery.php');
        exit;
    }

    // Get selected files
    $selected_files = json_decode($_POST['selected_files'] ?? '[]', true);
    if (empty($selected_files)) {
        $_SESSION['error'] = 'No files selected';
        header('Location: gallery.php');
        exit;
    }

    $deleted_count = 0;
    $errors = [];

    foreach ($selected_files as $filename) {
        // Sanitize filename and build full path
        $filename = basename($filename);
        $filepath = $config['upload_dir'] . $filename;
        $thumbnail = THUMBNAILS_DIR . $filename;

        // Check if file exists and is within upload directory
        if (!file_exists($filepath)) {
            $errors[] = "File not found: $filename";
            continue;
        }

        // Try to delete the file
        if (unlink($filepath)) {
            $deleted_count++;
            // Also delete thumbnail if it exists
            if (file_exists($thumbnail)) {
                unlink($thumbnail);
            }
        } else {
            $errors[] = "Failed to delete: $filename";
        }
    }

    if ($deleted_count > 0) {
        $_SESSION['message'] = "Successfully deleted $deleted_count file(s)";
    }
    if (!empty($errors)) {
        $_SESSION['error'] = implode("\n", $errors);
    }

    header('Location: gallery.php');
    exit;
}

// Get gallery items
$gallery_items = [];
$gallery_stats = [
    'file_count' => 0,
    'total_size' => 0,
    'file_types' => []
];

if (is_dir($config['upload_dir'])) {
    foreach (new DirectoryIterator($config['upload_dir']) as $file) {
        if ($file->isFile()) {
            $extension = strtolower($file->getExtension());
            
            if (!in_array($extension, ALLOWED_TYPES)) {
                continue;
            }
            
            // Update stats
            $gallery_stats['file_count']++;
            $gallery_stats['total_size'] += $file->getSize();
            
            if (!isset($gallery_stats['file_types'][$extension])) {
                $gallery_stats['file_types'][$extension] = 0;
            }
            $gallery_stats['file_types'][$extension]++;
            
            $gallery_items[] = [
                'name' => $file->getFilename(),
                'url' => $config['upload_dir'] . $file->getFilename(),
                'thumbnail' => getThumbnailUrl($file->getFilename()),
                'size' => formatSize($file->getSize()),
                'date' => date('Y-m-d H:i:s', $file->getMTime()),
                'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif']),
                'is_video' => in_array($extension, ['mp4', 'webm'])
            ];
        }
    }
}

// Sort files by date (newest first)
usort($gallery_items, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/gallery.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Image Gallery</h1>
            <a href="admin.php" class="button button-primary">Back to Dashboard</a>
        </div>

        <div class="gallery-controls">
            <div class="view-controls">
                <button onclick="setView('grid')" class="button view-button" id="grid-view">
                    <span class="icon">📱</span> Grid
                </button>
                <button onclick="setView('list')" class="button view-button" id="list-view">
                    <span class="icon">📄</span> List
                </button>
            </div>
            <div class="control-buttons">
                <button type="button" class="button button-primary" onclick="showUploadDialog()">
                    📤 Upload Files
                </button>
                <button type="button" class="button button-primary" onclick="toggleAll(this)" id="select-all-button">
                    ✓ Select All
                </button>
                <form method="post" id="delete-form" style="display: inline;">
                    <input type="hidden" name="delete_files" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="selected_files" id="selected-files">
                    <button type="button" class="button button-danger" disabled id="delete-button" onclick="confirmDelete()">
                        🗑️ Delete Selected
                    </button>
                </form>
            </div>
        </div>

        <div class="gallery-stats">
            <div class="stat-card">
                <div class="stat-title">📁 Total Files</div>
                <div class="stat-value"><?php echo $gallery_stats['file_count']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">💾 Storage Used</div>
                <div class="stat-value"><?php echo formatSize($gallery_stats['total_size']); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">📊 File Types</div>
                <div class="stat-value">
                    <?php foreach($gallery_stats['file_types'] as $type => $count): ?>
                        <span class="file-type-badge">
                            .<?php echo $type; ?> (<?php echo $count; ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="gallery-grid">
            <?php foreach ($gallery_items as $image): ?>
                <div class="gallery-item" data-name="<?php echo htmlspecialchars($image['name']); ?>" data-date="<?php echo $image['date']; ?>">
                    <input type="checkbox" class="file-checkbox" value="<?php echo htmlspecialchars($image['name']); ?>" onchange="updateDeleteButton()">
                    <div class="gallery-media">
                        <?php if ($image['is_video']): ?>
                            <a href="<?php echo htmlspecialchars($image['url']); ?>" target="_blank">
                                <video src="<?php echo htmlspecialchars($image['url']); ?>" 
                                       class="gallery-video" 
                                       preload="metadata"></video>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($image['url']); ?>" target="_blank">
                                <img src="<?php echo htmlspecialchars($image['thumbnail']); ?>" 
                                     loading="lazy" 
                                     class="gallery-img" 
                                     alt="<?php echo htmlspecialchars($image['name']); ?>">
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="gallery-info">
                        <div class="filename"><?php echo htmlspecialchars($image['name']); ?></div>
                        <div class="filesize">Size: <?php echo $image['size']; ?></div>
                        <div class="filedate">Date: <?php 
                            $date = new DateTime($image['date']);
                            echo $date->format('Y-m-d H:i:s'); 
                        ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="upload-dialog" class="upload-dialog" style="display: none;">
            <div class="upload-content">
                <div class="upload-header">
                    <h2>Upload Files</h2>
                    <button onclick="hideUploadDialog()" class="close-button">×</button>
                </div>
                <div class="upload-zone" id="drop-zone">
                    <div class="upload-message">
                        <span class="icon">📁</span>
                        <p>Drag and drop files here</p>
                        <p>or</p>
                        <button class="button button-primary">Choose Files</button>
                        <p class="upload-info">Supported formats: JPG, PNG, GIF, MP4, WEBM</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="delete-dialog" class="delete-dialog" style="display: none;">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete the selected files? This action cannot be undone.</p>
            <div class="delete-dialog-buttons">
                <button class="button-cancel" onclick="hideDeleteDialog()">Cancel</button>
                <button class="button-confirm-delete" onclick="proceedWithDelete()">Delete</button>
            </div>
        </div>
        <div id="delete-dialog-overlay" class="delete-dialog-overlay" style="display: none;" onclick="hideDeleteDialog()"></div>
    </div>

    <div id="toast-container"></div>
    
    <meta name="secret-key" content="<?php echo $config['secret_key']; ?>">
    
    <script src="assets/js/gallery.js"></script>
    
    <?php if (isset($_SESSION['message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showToast(<?php echo json_encode($_SESSION['message']); ?>, 'success');
        });
    </script>
    <?php unset($_SESSION['message']); endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showToast(<?php echo json_encode($_SESSION['error']); ?>, 'error');
        });
    </script>
    <?php unset($_SESSION['error']); endif; ?>
</body>
</html>

