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

// Debug: Log session info before loading language
error_log("Gallery.php: Session ID: " . session_id());
error_log("Gallery.php: Session status: " . session_status());
error_log("Gallery.php: Session keys before language load: " . implode(', ', array_keys($_SESSION)));

// Load language system (after session is initialized)
require_once('includes/language.php');

// Debug: Log session language
error_log("Gallery.php: Session language after language.php: " . (isset($_SESSION['language']) ? $_SESSION['language'] : 'NOT SET'));
error_log("Gallery.php: getCurrentLanguage() returns: " . getCurrentLanguage());

// Force reload translations to ensure we get the correct language from session
reloadTranslations();
error_log("Gallery.php: After reloadTranslations(), getCurrentLanguage() returns: " . getCurrentLanguage());

// Logout handling - must be before validateSession check
if (isset($_GET['logout'])) {
    destroySession();
    header('Location: admin.php');
    exit;
}

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

// Pagination constants
define('ITEMS_PER_PAGE', 24); // Number of items per page
define('MAX_PAGINATION_LINKS', 10); // Maximum number of pagination links to show

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

// Check IP whitelist if enabled in config
if ($config['ip_whitelist_enabled']) {
    checkIPWhitelist();
}

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

    // Preserve current page after deletion
    $return_page = isset($_POST['return_page']) ? intval($_POST['return_page']) : 1;
    $redirect_url = 'gallery.php';
    if ($return_page > 1) {
        $redirect_url .= '?page=' . $return_page;
    }
    
    header('Location: ' . $redirect_url);
    exit;
}

// Get pagination parameters
$gallery_current_page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = ITEMS_PER_PAGE;

// Get all gallery items for counting and sorting
$all_gallery_items = [];
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
            
            $all_gallery_items[] = [
                'name' => $file->getFilename(),
                'url' => $config['domain_url'] . $config['upload_dir'] . $file->getFilename(),
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
usort($all_gallery_items, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculate pagination
$total_items = count($all_gallery_items);
$total_pages = max(1, ceil($total_items / $items_per_page));
$gallery_current_page = min($gallery_current_page, $total_pages); // Ensure current page doesn't exceed total pages

// Get items for current page
$offset = ($gallery_current_page - 1) * $items_per_page;
$gallery_items = array_slice($all_gallery_items, $offset, $items_per_page);

// Calculate pagination links
$start_page = max(1, $gallery_current_page - floor(MAX_PAGINATION_LINKS / 2));
$end_page = min($total_pages, $start_page + MAX_PAGINATION_LINKS - 1);

// Adjust start_page if we're near the end
if ($end_page - $start_page + 1 < MAX_PAGINATION_LINKS) {
    $start_page = max(1, $end_page - MAX_PAGINATION_LINKS + 1);
}

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('gallery.title', 'Image Gallery'); ?> - Page <?php echo $gallery_current_page; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/gallery.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="assets/js/gallery.js?v=<?php echo filemtime('assets/js/gallery.js'); ?>" defer></script>
</head>
<body>
    <!-- Modals and Dialogs - Must be before container to ensure they're always rendered -->
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

    <!-- QR Code Modal -->
    <div id="qr-modal" class="qr-modal" style="display: none;">
        <div class="qr-modal-content">
            <div class="qr-modal-header">
                <h3 id="qr-modal-title">QR Code</h3>
                <button class="qr-modal-close" onclick="hideQRCode()">&times;</button>
            </div>
            <div class="qr-modal-body">
                <div class="qr-code-container">
                    <img id="qr-code-image" src="" alt="QR Code" />
                </div>
                <div class="qr-url-info">
                    <p><strong>URL:</strong></p>
                    <input type="text" id="qr-url-display" readonly class="qr-url-input" />
                    <button class="button-copy" onclick="copyQRUrl()">Copy URL</button>
                </div>
            </div>
            <div class="qr-modal-footer">
                <a id="qr-download-link" download="qrcode.png" class="button button-primary">Download QR Code</a>
                <button class="button" onclick="hideQRCode()">Close</button>
            </div>
        </div>
    </div>
    <div id="qr-modal-overlay" class="qr-modal-overlay" style="display: none;" onclick="hideQRCode()"></div>

    <?php require_once('includes/navigation.php'); ?>
    <div class="container">
        <div class="page-header">
            <h1><?php echo t('gallery.title', 'Image Gallery'); ?></h1>
            <div class="header-info">
                <span class="page-info">
                    Page <?php echo $gallery_current_page; ?> of <?php echo $total_pages; ?> 
                    (<?php echo number_format($total_items); ?> <?php echo t('gallery.total_files', 'total files'); ?>)
                </span>
            </div>
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
                <div class="stat-title">📁 <?php echo t('gallery.total_files', 'Total Files'); ?></div>
                <div class="stat-value"><?php echo number_format($gallery_stats['file_count']); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">💾 <?php echo t('gallery.storage_used', 'Storage Used'); ?></div>
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
            <div class="stat-card">
                <div class="stat-title">📄 Current Page</div>
                <div class="stat-value">
                    Page <?php echo $gallery_current_page; ?> (<?php echo count($gallery_items); ?> items)
                </div>
            </div>
        </div>



        <div class="gallery-grid">
            <?php if (empty($gallery_items)): ?>
                <div class="no-items">
                    <div class="no-items-content">
                        <span class="no-items-icon">📁</span>
                        <h3>No files found</h3>
                        <p>There are no files to display on this page.</p>
                        <?php if ($gallery_current_page > 1): ?>
                            <a href="?page=1" class="button button-primary">Go to First Page</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
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
                            <div class="gallery-actions">
                                <button class="button-qr" onclick="showQRCode('<?php echo htmlspecialchars($image['url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($image['name'], ENT_QUOTES); ?>')" title="Generate QR Code">
                                    <span class="qr-icon">📱</span> QR Code
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-bottom">
            <div class="pagination">
                <?php if ($gallery_current_page > 1): ?>
                    <a href="?page=1" class="pagination-link">« First</a>
                    <a href="?page=<?php echo $gallery_current_page - 1; ?>" class="pagination-link">‹ Previous</a>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $gallery_current_page): ?>
                        <span class="pagination-link pagination-current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>" class="pagination-link"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($gallery_current_page < $total_pages): ?>
                    <a href="?page=<?php echo $gallery_current_page + 1; ?>" class="pagination-link">Next ›</a>
                    <a href="?page=<?php echo $total_pages; ?>" class="pagination-link">Last »</a>
                <?php endif; ?>
            </div>
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo number_format($total_items); ?> files
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div id="toast-container"></div>
    
    <meta name="secret-key" content="<?php echo $config['secret_key']; ?>">
    
    <script>
        // Ensure DOM is ready before accessing elements
        document.addEventListener('DOMContentLoaded', function() {
            // All functions should now be available
            console.log('DOM ready - Functions available:', {
                toggleAll: typeof toggleAll !== 'undefined',
                setView: typeof setView !== 'undefined',
                showUploadDialog: typeof showUploadDialog !== 'undefined',
                showQRCode: typeof showQRCode !== 'undefined',
                confirmDelete: typeof confirmDelete !== 'undefined'
            });
            console.log('Elements available:', {
                uploadDialog: !!document.getElementById("upload-dialog"),
                deleteDialog: !!document.getElementById("delete-dialog"),
                qrModal: !!document.getElementById("qr-modal")
            });
        });
    </script>
    
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

