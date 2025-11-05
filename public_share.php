<?php
require_once('config.php');
require_once('includes/utilities.php');
require_once('templates/error.php');

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', 'logs/share_errors.log');

// Validate share ID format
if (!isset($_GET['id']) || !preg_match('/^[a-f0-9]{32}$/', $_GET['id'])) {
    showError(404, 'Invalid Share', 'The requested share ID is invalid or malformed.');
    exit;
}

$shareId = $_GET['id'];
$sharePath = $config['share_dir'] . $shareId;
$metadataPath = $sharePath . '/metadata.json';

// Add debug logging
error_log("Accessing share: $shareId");
error_log("Share path: $sharePath");
error_log("Metadata path: $metadataPath");

// Check if share exists
if (!file_exists($metadataPath)) {
    error_log("Share not found: $metadataPath");
    showError(404, 'Share Not Found', 'The requested share could not be found.');
    exit;
}

// Load and validate metadata
try {
    $metadata = json_decode(file_get_contents($metadataPath), true);
    if (!$metadata) {
        throw new Exception('Invalid metadata format');
    }
    
    // Validate required metadata fields
    $required_fields = ['created', 'files'];
    foreach ($required_fields as $field) {
        if (!isset($metadata[$field])) {
            throw new Exception("Missing required metadata field: $field");
        }
    }
    
} catch (Exception $e) {
    error_log("Share error: " . $e->getMessage());
    showError(500, 'Share Error', 'An error occurred while loading the share.');
    exit;
}

// Check if share has expired
if (time() > $metadata['expires']) {
    deleteDirectory($sharePath);
    die("This share has expired.");
}

// Check for temporary access key
$hasAccess = isset($_GET['key']) && 
    isset($metadata['temp_access_key']) && 
    $metadata['temp_access_key'] === $_GET['key'];

// Check password if not using temp access key
if (!$hasAccess && isset($metadata['password'])) {
    if (!isset($_POST['password'])) {
        // Show password form
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Protected Share</title>
            <link rel="stylesheet" href="assets/css/style.css">
            <link rel="stylesheet" href="assets/css/public-share.css">
        </head>
        <body>
            <div class="container">
                <div class="share-view">
                    <h2>Protected Share</h2>
                    <form method="POST" class="password-form">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($shareId); ?>">
                        <div class="form-group">
                            <label>This share is password protected:</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit" class="button button-primary">Access Files</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        // Verify password
        $hasAccess = password_verify($_POST['password'], $metadata['password']);
        if (!$hasAccess) {
            die("Incorrect password.");
        }
    }
}

// Define formatFileSize function if not already defined
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
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

// Display the modern UI for public downloads
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Shared Files - Serenity Share</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/public-share.css">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="assets/js/public_share.js"></script>
</head>
<body>
    <script>
    // Disable particles.js temporarily
    document.addEventListener('DOMContentLoaded', function() {
        const particlesEl = document.getElementById('particles-js');
        if (particlesEl) {
            particlesEl.style.display = 'none';
        }
    });
    </script>

    <div id="particles-js" style="pointer-events: none;"></div>
    <div class="container" style="position: relative; z-index: 100;">
        <div class="share-header">
            <h1>Shared Files</h1>
            <p>Access and download your shared files securely</p>
        </div>
        
        <div class="share-info">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">
                        <i class="far fa-calendar-alt"></i>
                        Created
                    </div>
                    <div class="info-value">
                        <?php echo date('F j, Y, g:i a', $metadata['created']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="far fa-clock"></i>
                        Expires
                    </div>
                    <div class="info-value">
                        <?php echo date('F j, Y, g:i a', $metadata['expires']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="far fa-file"></i>
                        Total Files
                    </div>
                    <div class="info-value">
                        <?php echo count($metadata['files']); ?> Files
                    </div>
                </div>
            </div>
        </div>

        <div class="share-actions">
            <a href="download.php?id=<?php echo $shareId; ?>&all=1" class="button">
                <i class="fas fa-download"></i>
                Download All Files
            </a>
        </div>

        <div class="files-container">
            <div class="files-header">
                <h2>Available Files</h2>
            </div>
            
            <div class="file-items">
                <?php foreach ($metadata['files'] as $file): ?>
                    <div class="file-item">
                        <div class="file-info">
                            <i class="far fa-file"></i>
                            <div class="file-details">
                                <span class="file-name"><?php echo htmlspecialchars($file['name']); ?></span>
                                <span class="file-size"><?php echo formatFileSize($file['size']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
