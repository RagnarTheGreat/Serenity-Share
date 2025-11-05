<?php
require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');
require_once('templates/error.php');    

initSecureSession();

// Load language system (after session is initialized)
require_once('includes/language.php');

// Force reload translations to ensure we get the correct language from session
reloadTranslations();

// Only set security headers for GET requests (not POST/uploads)
// POST requests will set their own headers for JSON responses
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['create_share'])) {
    // Set security headers
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data: https://www.google.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' http://ip-api.com; frame-ancestors 'none'; form-action 'self';");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// Function to get readable error message for upload errors
function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}

if (!isset($_GET['id'])) {
    if (!validateSession()) {
        showError(403, 'Access Denied', 'Please log in to access the share management.');
        exit;
    }
}


function getSharedLinks() {
    global $config;
    $shares = [];
    
    if (is_dir($config['share_dir'])) {
        foreach (glob($config['share_dir'] . '*', GLOB_ONLYDIR) as $shareDir) {
            $metadataPath = $shareDir . '/metadata.json';
            if (file_exists($metadataPath)) {
                $metadata = json_decode(file_get_contents($metadataPath), true);
                $shareId = basename($shareDir);
                
                // Skip expired shares, but keep never-expiring shares
                if ($metadata['expires'] !== 4102444800 && time() > $metadata['expires']) {
                    deleteDirectory($shareDir);
                    continue;
                }
                
                $shares[] = [
                    'id' => $shareId,
                    'url' => $config['domain_url'] . 'public_share.php?id=' . $shareId,
                    'created' => $metadata['created'],
                    'expires' => $metadata['expires'],
                    'files' => $metadata['files'],
                    'has_password' => isset($metadata['password']),
                    'has_temp_access' => isset($metadata['temp_access_key'])
                ];
            }
        }
    }
    

    usort($shares, function($a, $b) {
        return $b['created'] - $a['created'];
    });
    
    return $shares;
}


if (isset($_POST['delete_share'])) {
    $shareId = $_POST['share_id'];
    $sharePath = $config['share_dir'] . $shareId;
    
    if (file_exists($sharePath)) {
        deleteDirectory($sharePath);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Share not found']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_share'])) {
    // Clear any existing output buffers first
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffering
    ob_start();
    
    // Enable error logging but don't display errors
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    set_time_limit(0);
    ignore_user_abort(true);
    
    try {
        if (empty($_FILES) || !isset($_FILES['files'])) {
            throw new Exception("No files uploaded or files array not set");
        }

        error_log("Files received: " . print_r($_FILES, true));
        
        $files = [];
        $uploadErrors = [];
        
        $shareId = bin2hex(random_bytes(16));
        $sharePath = $config['share_dir'] . '/' . $shareId;
        
        error_log("Share path: " . $sharePath);
        
        // Ensure share directory exists and is writable
        if (!file_exists($sharePath)) {
            error_log("Creating share directory: " . $sharePath);
            if (!mkdir($sharePath, 0755, true)) {
                $error = error_get_last();
                throw new Exception("Failed to create share directory: " . ($error ? $error['message'] : 'Unknown error'));
            }
        }
        
        if (!is_writable($sharePath)) {
            $permissions = fileperms($sharePath);
            throw new Exception("Share directory is not writable. Permissions: " . decoct($permissions & 0777));
        }
        
        // Log the start of file processing
        error_log("Starting file processing for share: " . $shareId);
        
        // Check if files array is properly structured
        if (!is_array($_FILES['files']['name'])) {
            throw new Exception("Files array improperly formatted");
        }
        
        foreach ($_FILES['files']['error'] as $key => $error) {
            $fileName = isset($_FILES['files']['name'][$key]) ? $_FILES['files']['name'][$key] : 'unknown';
            error_log("Processing file: " . $fileName . " (error code: " . $error . ")");
            
            if ($error === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['files']['tmp_name'][$key];
                
                if (!file_exists($tmpName)) {
                    $uploadErrors[] = "Temporary file does not exist: " . $tmpName;
                    error_log("Error: Temporary file does not exist: " . $tmpName);
                    continue;
                }
                
                if (!is_uploaded_file($tmpName)) {
                    $uploadErrors[] = "Invalid upload attempt for file: " . $fileName;
                    error_log("Error: Invalid upload attempt for file: " . $fileName);
                    continue;
                }
                
                // Handle folder structure
                $relativePath = isset($_POST['relative_path'][$key]) ? $_POST['relative_path'][$key] : '';
                $destination = $sharePath;
                
                if ($relativePath) {
                    error_log("File has relative path: " . $relativePath);
                    // Create folder structure
                    $folders = explode('/', dirname($relativePath));
                    foreach ($folders as $folder) {
                        if ($folder && $folder !== '.') {
                            $destination .= '/' . $folder;
                            if (!file_exists($destination)) {
                                error_log("Creating directory: " . $destination);
                                if (!mkdir($destination, 0755, true)) {
                                    $error = error_get_last();
                                    throw new Exception("Failed to create directory: " . $destination . " - " . ($error ? $error['message'] : 'Unknown error'));
                                }
                            }
                        }
                    }
                }
                
                $destination .= '/' . basename($fileName);
                error_log("Moving file to: " . $destination);
                
                if (!move_uploaded_file($tmpName, $destination)) {
                    $error = error_get_last();
                    $uploadErrors[] = "Failed to move uploaded file: " . $fileName . " - " . ($error ? $error['message'] : 'Unknown error');
                    error_log("Error: Failed to move uploaded file: " . $fileName . " - " . ($error ? $error['message'] : 'Unknown error'));
                    continue;
                }
                
                $files[] = [
                    'name' => $fileName,
                    'type' => $_FILES['files']['type'][$key],
                    'size' => $_FILES['files']['size'][$key],
                    'path' => $relativePath ? $relativePath : $fileName
                ];
                
                error_log("Added file to share: " . ($relativePath ? $relativePath : $fileName));
            } else {
                $errorMessage = get_upload_error_message($error);
                $uploadErrors[] = "Upload error for file {$fileName}: " . $errorMessage;
                error_log("Error: Upload error for file {$fileName}: " . $errorMessage);
            }
        }
        
        if (empty($files)) {
            throw new Exception("No valid files uploaded. Errors: " . implode(", ", $uploadErrors));
        }
        
        error_log("Total files processed: " . count($files));
        
        // Create metadata and finish share creation
        $expiration = isset($_POST['expiration']) ? intval($_POST['expiration']) : 7;
        $expires = $expiration === -1 ? 4102444800 : time() + ($expiration * 24 * 60 * 60);
        
        $metadata = [
            'created' => time(),
            'expires' => $expires,
            'files' => $files
        ];
        
        if (isset($_POST['password']) && !empty($_POST['password'])) {
            $metadata['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        // Save metadata
        $metadataPath = $sharePath . '/metadata.json';
        error_log("Saving metadata to: " . $metadataPath);
        if (!file_put_contents($metadataPath, json_encode($metadata))) {
            $error = error_get_last();
            throw new Exception("Failed to save share metadata: " . ($error ? $error['message'] : 'Unknown error'));
        }
        
        // Clear any previous output and headers BEFORE sending response
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send JSON response first
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        $shareUrl = $config['domain_url'] . 'public_share.php?id=' . $shareId;
        $response = [
            'success' => true,
            'data' => [
                'id' => $shareId,
                'url' => $shareUrl,
                'fileCount' => count($files)
            ]
        ];
        echo json_encode($response);
        
        // Send response immediately and close connection
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // Send Discord webhook notification AFTER response is sent (non-blocking)
        if (!empty($files) && isset($config['discord_notifications']) && $config['discord_notifications']) {
            $firstFile = $files[0];
            $additionalData = [
                'share_id' => $shareId,
                'file_count' => count($files),
                'expires' => $expiration === -1 ? 'Never' : $expiration . ' days'
            ];
            // Send webhook asynchronously (don't wait for it)
            @sendDiscordWebhook($firstFile['name'], $shareUrl, 'share', $additionalData);
        }
        
        exit;
        
    } catch (Exception $e) {
        error_log("Share creation error: " . $e->getMessage());
        
        // Clear any previous output and headers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send error response
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        http_response_code(400);
        
        $response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
        echo json_encode($response);
        exit;
    }
}

// Handle GET requests next
if (isset($_GET['id'])) {
    // Redirect to public share page if not logged in
    if (!isset($_SESSION['logged_in'])) {
        header('Location: public_share.php?id=' . $_GET['id']);
        exit;
    }

    $shareId = $_GET['id'];
    $sharePath = $config['share_dir'] . $shareId;
    $metadataPath = $sharePath . '/metadata.json';
    
    if (!file_exists($metadataPath)) {
        require_once('error.php');
        showError(404, 'Share Not Found', 'The requested share has expired or does not exist.');
    }
    
    $metadata = json_decode(file_get_contents($metadataPath), true);
    
    if (time() > $metadata['expires']) {
        deleteDirectory($sharePath);
        require_once('error.php');
        showError(404, 'Share Expired', 'This share link has expired.');
    }
    
    // Check for temporary access key
    $hasAccess = isset($_GET['key']) && 
        $metadata['temp_access_key'] === $_GET['key'];
    
    // Check password if not using temp access key
    if (!$hasAccess && isset($metadata['password'])) {
        if (!isset($_POST['password'])) {
            // Show password form
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title><?php echo t('share.protected_share', 'Protected Share'); ?></title>
                <link rel="stylesheet" href="assets/css/style.css">
            </head>
            <body>
                <div class="container">
                    <div class="share-view">
                        <h2><?php echo t('share.protected_share', 'Protected Share'); ?></h2>
                        <form method="POST" class="password-form">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($shareId); ?>">
                            <div class="form-group">
                                <label><?php echo t('share.password_protected', 'This share is password protected:'); ?></label>
                                <input type="password" name="password" required>
                            </div>
                            <button type="submit" class="button button-primary"><?php echo t('share.access_files', 'Access Files'); ?></button>
                        </form>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        } else {
            if (!password_verify($_POST['password'], $metadata['password'])) {
                require_once('error.php');
                showError(403, 'Access Denied', 'Incorrect password.');
            }
            $hasAccess = true;
        }
    } else {
        $hasAccess = true;
    }
    
    if (!$hasAccess) {
        require_once('error.php');
        showError(403, 'Access Denied', 'You do not have permission to access this share.');
    }
    
    // Display admin share view
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Share Management</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="assets/css/share.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php echo t('share.title', 'Share Management'); ?></h1>
                <a href="admin.php" class="button">
                    <i class="fas fa-arrow-left"></i> <?php echo t('share.back_to_dashboard', 'Back to Dashboard'); ?>
                </a>
            </div>

            <div class="section">
                <div class="share-info">
                    <h2><?php echo t('share.share_details', 'Share Details'); ?></h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label><?php echo t('share.created', 'Created'); ?>:</label>
                            <span><?php echo date('Y-m-d H:i:s', $metadata['created']); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php echo t('share.expires', 'Expires'); ?>:</label>
                            <span><?php echo date('Y-m-d H:i:s', $metadata['expires']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Public URL:</label>
                            <input type="text" readonly value="<?php echo $config['domain_url']; ?>public_share.php?id=<?php echo $shareId; ?>" class="share-url">
                        </div>
                    </div>
                </div>

                <div class="files-list">
                    <div class="files-header">
                        <h3>Files</h3>
                        <div class="actions">
                            <a href="download.php?id=<?php echo $shareId; ?>" class="button">
                                <i class="fas fa-download"></i> Download All
                            </a>
                            <button class="button button-danger" onclick="deleteShare('<?php echo $shareId; ?>')">
                                <i class="fas fa-trash"></i> Delete Share
                            </button>
                        </div>
                    </div>
                    <div class="file-items">
                        <?php foreach ($metadata['files'] as $file): ?>
                        <div class="file-item">
                            <i class="far fa-file"></i>
                            <span class="file-name"><?php echo htmlspecialchars($file['name']); ?></span>
                            <span class="file-size"><?php echo formatSize($file['size']); ?></span>
                            <div class="file-actions">
                                <a href="download.php?id=<?php echo $shareId; ?>&file=<?php echo urlencode($file['name']); ?>" class="button">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="delete-dialog" class="delete-dialog" style="display: none;">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this share? This action cannot be undone.</p>
            <div class="delete-dialog-buttons">
                <button class="button-cancel" onclick="hideDeleteDialog()">Cancel</button>
                <button class="button-confirm-delete" onclick="proceedWithDelete()">Delete</button>
            </div>
        </div>
        <div id="delete-dialog-overlay" class="delete-dialog-overlay" style="display: none;" onclick="hideDeleteDialog()"></div>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_GET['file'])) {
    // Find the file metadata first to get the correct path
    $fileKey = null;
    $fileData = null;
    
    foreach ($metadata['files'] as $index => $file) {
        if ($file['name'] === $_GET['file']) {
            $fileKey = $index;
            $fileData = $file;
            break;
        }
    }
    
    if (!$fileData) {
        die("File not found in share.");
    }
    
    $filename = $fileData['name'];
    $filepath = $sharePath . '/' . $fileData['path'];
    
    // Validate that the file exists and is within the share directory
    if (!file_exists($filepath) || !is_file($filepath) || 
        strpos(realpath($filepath), realpath($sharePath)) !== 0) {
        die("File not found or access denied.");
    }
    
    // Get file mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    
    // Set headers for download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($filepath);
    exit;
}

if (isset($_GET['download_all'])) {
    $zip = new ZipArchive();
    $zipName = 'share_' . $shareId . '.zip';
    $zipPath = sys_get_temp_dir() . '/' . $zipName;
    
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // Add each file to the zip
        foreach ($metadata['files'] as $file) {
            $filePath = $sharePath . '/' . $file['path'];
            if (file_exists($filePath)) {
                // Add file to zip with its relative path
                $zip->addFile($filePath, $file['path']);
            }
        }
        $zip->close();
        
        // Send zip file to user
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($zipPath);
        unlink($zipPath); // Delete temporary zip file
        exit;
    }
}

// Finally, show the admin interface if no other handlers matched
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('share.title', 'Share Management'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/share.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo t('share.title', 'Share Management'); ?></h1>
            <a href="admin.php" class="button button-primary">
                <i class="fas fa-chevron-left"></i> <?php echo t('share.back_to_dashboard', 'Back to Dashboard'); ?>
            </a>
        </div>

        <div class="section">
            <div class="share-form">
                <h2><?php echo t('share.create_new_share', 'Create New Share'); ?></h2>
                <form id="shareForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="create_share" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="files"><?php echo t('share.select_files', 'Select Files or Folders'); ?>:</label>
                        <div class="upload-zone" id="drop-zone">
                            <div class="upload-message">
                                <span class="icon">📁</span>
                                <p><?php echo t('share.drag_drop', 'Drag and drop files or folders here'); ?></p>
                                <p><?php echo t('share.or', 'or'); ?></p>
                                <div class="upload-buttons">
                                    <button type="button" class="button button-primary" onclick="document.getElementById('file-input').click()"><?php echo t('share.choose_files', 'Choose Files'); ?></button>
                                    <button type="button" class="button button-primary" onclick="document.getElementById('folder-input').click()"><?php echo t('share.choose_folder', 'Choose Folder'); ?></button>
                                </div>
                                <input type="file" id="file-input" name="files[]" multiple style="display: none" onchange="handleFileSelect(this)">
                                <input type="file" id="folder-input" name="files[]" webkitdirectory directory style="display: none" onchange="handleFolderSelect(this)">
                            </div>
                        </div>
                        <div id="selected-files" class="selected-files"></div>
                    </div>

                    <div class="form-group">
                        <label for="expiration"><?php echo t('share.share_expiration', 'Share Expiration'); ?>:</label>
                        <select name="expiration" id="expiration">
                            <option value="1">1 <?php echo t('share.day', 'Day'); ?></option>
                            <option value="7" selected>7 <?php echo t('share.days', 'Days'); ?></option>
                            <option value="30">30 <?php echo t('share.days', 'Days'); ?></option>
                            <option value="-1"><?php echo t('share.never', 'Never'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password"><?php echo t('share.password_protection', 'Password Protection (Optional)'); ?>:</label>
                        <input type="password" name="password" id="password" placeholder="<?php echo t('share.enter_password', 'Enter password to protect share'); ?>">
                    </div>

                    <div id="uploadProgress" class="upload-progress hidden">
                        <div class="progress-info">
                            <span id="currentFile"><?php echo t('share.preparing_upload', 'Preparing upload...'); ?></span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="progress-bar">
                            <div id="progressBar" style="width: 0%"></div>
                        </div>
                    </div>

                    <button type="submit" class="button button-primary"><?php echo t('share.create_share', 'Create Share'); ?></button>
                </form>
            </div>

            <div class="shares-list">
                <h2><?php echo t('share.active_shares', 'Active Shares'); ?></h2>
                <div class="shares-grid">
                    <?php
                    $shares = getSharedLinks();
                    foreach ($shares as $share):
                    ?>
                    <div class="share-card">
                        <div class="share-header">
                            <h3><?php echo t('share.share_id', 'Share #'); ?><?php echo $share['id']; ?></h3>
                        </div>
                        <div class="share-details">
                            <div class="detail-item">
                                <span class="label"><i class="far fa-calendar"></i> <?php echo t('share.created', 'Created'); ?>:</span>
                                <span><?php echo date('Y-m-d H:i', $share['created']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="far fa-clock"></i> <?php echo t('share.expires', 'Expires'); ?>:</span>
                                <span><?php 
                                    if ($share['expires'] === 4102444800) {
                                        echo t('share.never', 'Never');
                                    } else {
                                        echo date('Y-m-d H:i', $share['expires']);
                                    }
                                ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="far fa-file"></i> <?php echo t('share.files', 'Files'); ?>:</span>
                                <span><?php echo count($share['files']); ?></span>
                            </div>
                        </div>
                        <div class="share-url">
                            <input type="text" readonly value="<?php echo $share['url']; ?>">
                            <button class="button" onclick="copyToClipboard(this.previousElementSibling)">
                                <i class="fa-regular fa-copy"></i> Copy
                            </button>
                            <button class="button button-danger" onclick="deleteShare('<?php echo $share['id']; ?>')">
                                <i class="fa-regular fa-trash-can"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="delete-dialog" class="delete-dialog" style="display: none;">
        <h3>Confirm Deletion</h3>
        <p>Are you sure you want to delete this share? This action cannot be undone.</p>
        <div class="delete-dialog-buttons">
            <button class="button-cancel" onclick="hideDeleteDialog()">Cancel</button>
            <button class="button-confirm-delete" onclick="proceedWithDelete()">Delete</button>
        </div>
    </div>
    <div id="delete-dialog-overlay" class="delete-dialog-overlay" style="display: none;" onclick="hideDeleteDialog()"></div>

    <script>
        const secretKey = '<?php echo $config['secret_key']; ?>';
    </script>
    <script src="assets/js/share.js"></script>
</body>
</html>
