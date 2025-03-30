<?php
require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');
require_once('templates/error.php');    

initSecureSession();

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

    set_time_limit(0);
    ignore_user_abort(true);
    
    try {
        if (!isset($_FILES['files'])) {
            throw new Exception("No files uploaded");
        }

        $files = [];
        $uploadErrors = [];
        

        $shareId = bin2hex(random_bytes(16));
        $sharePath = $config['share_dir'] . '/' . $shareId;
        if (!file_exists($sharePath)) {
            mkdir($sharePath, 0755, true);
        }
        

        foreach ($_FILES['files']['error'] as $key => $error) {
            if ($error === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['files']['tmp_name'][$key];
                $fileName = $_FILES['files']['name'][$key];
                
                if (!is_uploaded_file($tmpName)) {
                    $uploadErrors[] = "Invalid upload attempt for file: " . $fileName;
                    continue;
                }
                

                $destination = $sharePath . '/' . $fileName;
                if (!move_uploaded_file($tmpName, $destination)) {
                    $uploadErrors[] = "Failed to move uploaded file: " . $fileName;
                    continue;
                }
                

                $files[] = [
                    'name' => $fileName,
                    'type' => $_FILES['files']['type'][$key],
                    'size' => $_FILES['files']['size'][$key],
                    'path' => $fileName
                ];
                
                error_log("Added file to share: " . $fileName); // Debug logging
            } else {
                $uploadErrors[] = "Upload error for file {$_FILES['files']['name'][$key]}: " . get_upload_error_message($error);
            }
        }
        
        if (empty($files)) {
            throw new Exception("No valid files uploaded. Errors: " . implode(", ", $uploadErrors));
        }

        error_log("Total files processed: " . count($files)); // Debug logging
        
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
        if (!file_put_contents($metadataPath, json_encode($metadata))) {
            throw new Exception("Failed to save share metadata");
        }
        
        error_log("Share created successfully with " . count($files) . " files"); // Debug logging
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $shareId,
                'url' => $config['domain_url'] . 'public_share.php?id=' . $shareId,
                'fileCount' => count($files) // Add file count to response
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Share creation error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
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
                <title>Protected Share</title>
                <link rel="stylesheet" href="assets/css/style.css">
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
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Shares</h1>
                <a href="admin.php" class="button">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <div class="section">
                <div class="share-info">
                    <h2>Share Details</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Created:</label>
                            <span><?php echo date('Y-m-d H:i:s', $metadata['created']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Expires:</label>
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
    $filename = basename($_GET['file']);
    $filepath = $sharePath . '/' . $_GET['file'];
    
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
    <title>Share Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/share.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Share Management</h1>
            <a href="admin.php" class="button button-primary">
                <i class="fas fa-chevron-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="section">
            <!-- Replace the dropzone with a simple upload button -->
            <div class="upload-section">
                <div class="upload-button-container">
                    <input type="file" id="fileInput" multiple style="display: none;">
                    <button class="button button-primary" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-upload"></i> Upload Files
                    </button>
                </div>
            </div>

            <!-- Keep the upload options -->
            <div class="upload-options">
                <div class="form-group">
                    <label>Expiration</label>
                    <select id="expiration" name="expiration">
                        <option value="-1" selected>Never expire</option>
                        <option value="1">1 day</option>
                        <option value="7">7 days</option>
                        <option value="30">30 days</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password Protection</label>
                    <input type="password" id="sharePassword" placeholder="Optional">
                </div>
                <button class="button button-primary" onclick="uploadFiles()">
                    <i class="fas fa-share"></i> Create Share
                </button>
            </div>

            <div id="uploadProgress" class="upload-progress hidden">
                <div class="progress-info">
                    <span id="currentFile">Preparing upload...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="progress-bar">
                    <div id="progressBar" style="width: 0%"></div>
                </div>
            </div>

            <div class="shares-list">
                <h2>Active Shares</h2>
                <div class="shares-grid">
                    <?php
                    $shares = getSharedLinks();
                    foreach ($shares as $share):
                    ?>
                    <div class="share-card">
                        <div class="share-header">
                            <h3>Share #<?php echo $share['id']; ?></h3>
                        </div>
                        <div class="share-details">
                            <div class="detail-item">
                                <span class="label"><i class="far fa-calendar"></i> Created:</span>
                                <span><?php echo date('Y-m-d H:i', $share['created']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="far fa-clock"></i> Expires:</span>
                                <span><?php 
                                    if ($share['expires'] === 4102444800) {
                                        echo 'Never';
                                    } else {
                                        echo date('Y-m-d H:i', $share['expires']);
                                    }
                                ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="far fa-file"></i> Files:</span>
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
