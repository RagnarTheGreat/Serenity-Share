<?php
require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');
require_once('templates/error.php');    

initSecureSession();

// Load language system (after session is initialized)
require_once('includes/language.php');
reloadTranslations();

// Set security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data: https://www.google.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' http://ip-api.com; frame-ancestors 'none'; form-action 'self';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

if (!validateSession()) {
    showError(403, 'Access Denied', 'Please log in to access the link shortener.');
    exit;
}

// Function to generate a unique short code
function generateShortCode($length = 6) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

// Function to get all shortened links
function getShortenedLinks() {
    global $config;
    $links = [];
    
    if (is_dir($config['links_dir'])) {
        foreach (glob($config['links_dir'] . '*.json') as $linkFile) {
            $data = json_decode(file_get_contents($linkFile), true);
            $linkId = basename($linkFile, '.json');
            
            // Skip expired links, but keep never-expiring links
            if ($data['expires'] !== 4102444800 && time() > $data['expires']) {
                @unlink($linkFile);
                continue;
            }
            
            $links[] = [
                'id' => $linkId,
                'code' => $data['code'],
                'url' => $config['domain_url'] . 'l.php?c=' . $data['code'],
                'original_url' => $data['original_url'],
                'created' => $data['created'],
                'expires' => $data['expires'],
                'clicks' => isset($data['clicks']) ? $data['clicks'] : 0
            ];
        }
    }
    
    usort($links, function($a, $b) {
        return $b['created'] - $a['created'];
    });
    
    return $links;
}

// Handle link creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_link'])) {
    header('Content-Type: application/json');
    
    try {
        $originalUrl = trim($_POST['original_url'] ?? '');
        
        if (empty($originalUrl)) {
            throw new Exception('Please enter a URL to shorten');
        }
        
        // Validate URL
        if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('Please enter a valid URL');
        }
        
        // Generate unique short code
        $code = generateShortCode();
        $linkFile = $config['links_dir'] . $code . '.json';
        
        // Ensure code is unique
        while (file_exists($linkFile)) {
            $code = generateShortCode();
            $linkFile = $config['links_dir'] . $code . '.json';
        }
        
        // Calculate expiration
        $expiration = isset($_POST['expiration']) ? intval($_POST['expiration']) : 7;
        $expires = $expiration === -1 ? 4102444800 : time() + ($expiration * 24 * 60 * 60);
        
        // Create link data
        $linkData = [
            'code' => $code,
            'original_url' => $originalUrl,
            'created' => time(),
            'expires' => $expires,
            'clicks' => 0
        ];
        
        // Save link data
        if (!file_put_contents($linkFile, json_encode($linkData))) {
            throw new Exception('Failed to save shortened link');
        }
        
        $shortUrl = $config['domain_url'] . 'l.php?c=' . $code;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'code' => $code,
                'short_url' => $shortUrl,
                'original_url' => $originalUrl
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Handle link deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_link'])) {
    header('Content-Type: application/json');
    
    $code = $_POST['code'] ?? '';
    $linkFile = $config['links_dir'] . $code . '.json';
    
    if (file_exists($linkFile)) {
        @unlink($linkFile);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Link not found']);
    }
    exit;
}

// Display the link shortener interface
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('shorten.title', 'Link Shortener'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/share.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        .link-form {
            background: var(--card-bg, #1e1e1e);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color, #2e2e2e);
        }
        
        .links-list {
            background: var(--card-bg, #1e1e1e);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--border-color, #2e2e2e);
        }
        
        .link-card {
            background: var(--bg-darker, #0a0a0a);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color, #2e2e2e);
        }
        
        .link-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .link-card h3 {
            margin: 0;
            color: var(--text-color, #e2e8f0);
        }
        
        .link-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-item .label {
            font-size: 0.9em;
            color: var(--text-light, #94a3b8);
            margin-bottom: 5px;
        }
        
        .detail-item span {
            color: var(--text-color, #e2e8f0);
            word-break: break-all;
        }
        
        .link-url {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .link-url input {
            flex: 1;
            padding: 10px;
            background: var(--bg-dark, #111111);
            border: 1px solid var(--border-color, #2e2e2e);
            border-radius: 6px;
            color: var(--text-color, #e2e8f0);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        
        .badge-clicks {
            background: #3b82f6;
            color: white;
        }
        
        /* SweetAlert2 Dark Theme */
        .swal2-popup {
            background: var(--card-bg, #1e1e1e) !important;
            color: var(--text-color, #e2e8f0) !important;
            border: 1px solid var(--border-color, #2e2e2e) !important;
        }
        
        .swal2-title {
            color: var(--text-color, #e2e8f0) !important;
        }
        
        .swal2-content {
            color: var(--text-light, #94a3b8) !important;
        }
        
        .swal2-html-container {
            color: var(--text-color, #e2e8f0) !important;
        }
        
        .swal2-html-container p {
            color: var(--text-color, #e2e8f0) !important;
        }
        
        .swal2-html-container strong {
            color: var(--text-color, #e2e8f0) !important;
        }
        
        .swal2-confirm {
            background: var(--primary-color, #3b82f6) !important;
            color: white !important;
            border: none !important;
        }
        
        .swal2-confirm:hover {
            background: var(--primary-dark, #2563eb) !important;
        }
        
        .swal2-cancel {
            background: var(--bg-dark, #111111) !important;
            color: var(--text-color, #e2e8f0) !important;
            border: 1px solid var(--border-color, #2e2e2e) !important;
        }
        
        .swal2-cancel:hover {
            background: var(--card-bg, #1e1e1e) !important;
        }
        
        .swal2-success {
            border-color: #4CAF50 !important;
        }
        
        .swal2-success [class^=swal2-success-line] {
            background-color: #4CAF50 !important;
        }
        
        .swal2-success .swal2-success-ring {
            border-color: rgba(76, 175, 80, 0.3) !important;
        }
        
        .swal2-error {
            border-color: #f43f5e !important;
        }
        
        .swal2-error [class^=swal2-x-mark-line] {
            background-color: #f43f5e !important;
        }
        
        .swal2-backdrop-show {
            background: rgba(0, 0, 0, 0.8) !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo t('shorten.title', 'Link Shortener'); ?></h1>
            <a href="admin.php" class="button button-primary">
                <i class="fas fa-chevron-left"></i> <?php echo t('shorten.back_to_dashboard', 'Back to Dashboard'); ?>
            </a>
        </div>

        <div class="section">
            <div class="link-form">
                <h2><?php echo t('shorten.create_new_link', 'Create New Short Link'); ?></h2>
                <form id="linkForm" method="post" autocomplete="off">
                    <input type="hidden" name="create_link" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="original_url"><?php echo t('shorten.original_url', 'Original URL'); ?>:</label>
                        <input type="url" name="original_url" id="original_url" required 
                               placeholder="https://example.com" class="form-control" 
                               autocomplete="url" data-lpignore="true">
                    </div>

                    <div class="form-group">
                        <label for="expiration"><?php echo t('shorten.expiration', 'Expiration'); ?>:</label>
                        <select name="expiration" id="expiration" autocomplete="off">
                            <option value="1">1 <?php echo t('shorten.day', 'Day'); ?></option>
                            <option value="7" selected>7 <?php echo t('shorten.days', 'Days'); ?></option>
                            <option value="30">30 <?php echo t('shorten.days', 'Days'); ?></option>
                            <option value="-1"><?php echo t('shorten.never', 'Never'); ?></option>
                        </select>
                    </div>

                    <button type="submit" class="button button-primary">
                        <i class="fas fa-link"></i> <?php echo t('shorten.create_link', 'Create Short Link'); ?>
                    </button>
                </form>
            </div>

            <div class="links-list">
                <h2><?php echo t('shorten.active_links', 'Active Short Links'); ?></h2>
                <div id="links-container">
                    <?php
                    $links = getShortenedLinks();
                    if (empty($links)):
                    ?>
                        <p style="text-align: center; color: var(--text-light, #94a3b8); padding: 40px;">
                            <?php echo t('shorten.no_links', 'No shortened links yet. Create your first one above!'); ?>
                        </p>
                    <?php else: ?>
                        <?php foreach ($links as $link): ?>
                        <div class="link-card">
                            <div class="link-card-header">
                                <h3>
                                    <?php echo t('shorten.link', 'Link'); ?>: <?php echo htmlspecialchars($link['code']); ?>
                                    <span class="badge badge-clicks">
                                        <i class="fas fa-mouse-pointer"></i> <?php echo $link['clicks']; ?> <?php echo t('shorten.clicks', 'clicks'); ?>
                                    </span>
                                </h3>
                            </div>
                            <div class="link-details">
                                <div class="detail-item">
                                    <span class="label"><i class="far fa-calendar"></i> <?php echo t('shorten.created', 'Created'); ?>:</span>
                                    <span><?php echo date('Y-m-d H:i', $link['created']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="label"><i class="far fa-clock"></i> <?php echo t('shorten.expires', 'Expires'); ?>:</span>
                                    <span><?php 
                                        if ($link['expires'] === 4102444800) {
                                            echo t('shorten.never', 'Never');
                                        } else {
                                            echo date('Y-m-d H:i', $link['expires']);
                                        }
                                    ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-link"></i> <?php echo t('shorten.original_url', 'Original URL'); ?>:</span>
                                    <span><?php echo htmlspecialchars($link['original_url']); ?></span>
                                </div>
                            </div>
                            <div class="link-url">
                                <input type="text" readonly value="<?php echo htmlspecialchars($link['url']); ?>">
                                <button class="button" onclick="copyToClipboard(this.previousElementSibling)">
                                    <i class="fa-regular fa-copy"></i> <?php echo t('shorten.copy', 'Copy'); ?>
                                </button>
                                <button class="button button-danger" onclick="deleteLink('<?php echo htmlspecialchars($link['code']); ?>')">
                                    <i class="fa-regular fa-trash-can"></i> <?php echo t('shorten.delete', 'Delete'); ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(element) {
            element.select();
            document.execCommand('copy');
            
            Toastify({
                text: "<?php echo t('shorten.copied', 'Copied to clipboard!'); ?>",
                duration: 2000,
                gravity: "top",
                position: "right",
                backgroundColor: "#4CAF50",
            }).showToast();
        }

        function deleteLink(code) {
            Swal.fire({
                title: "<?php echo t('shorten.confirm_delete', 'Confirm Deletion'); ?>",
                text: "<?php echo t('shorten.delete_warning', 'Are you sure you want to delete this link? This action cannot be undone.'); ?>",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: "<?php echo t('shorten.delete', 'Delete'); ?>",
                cancelButtonText: "<?php echo t('shorten.cancel', 'Cancel'); ?>"
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('delete_link', '1');
                    formData.append('code', code);
                    
                    fetch('shorten.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: "<?php echo t('shorten.deleted', 'Deleted!'); ?>",
                                text: "<?php echo t('shorten.link_deleted', 'The link has been deleted.'); ?>",
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: "<?php echo t('shorten.error', 'Error'); ?>",
                                text: data.error || "<?php echo t('shorten.delete_failed', 'Failed to delete link.'); ?>",
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: "<?php echo t('shorten.error', 'Error'); ?>",
                            text: "<?php echo t('shorten.delete_failed', 'Failed to delete link.'); ?>",
                            icon: 'error'
                        });
                    });
                }
            });
        }

        document.getElementById('linkForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('shorten.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: "<?php echo t('shorten.success', 'Success!'); ?>",
                        html: `<p><strong><?php echo t('shorten.short_url', 'Short URL'); ?>:</strong><br><input type="text" value="${data.data.short_url}" readonly style="width: 100%; padding: 10px; margin-top: 10px; background: #1e1e1e; border: 1px solid #2e2e2e; border-radius: 6px; color: #e2e8f0;" onclick="this.select(); copyToClipboard(this);"></p>`,
                        icon: 'success',
                        confirmButtonText: "<?php echo t('shorten.ok', 'OK'); ?>"
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: "<?php echo t('shorten.error', 'Error'); ?>",
                        text: data.error || "<?php echo t('shorten.create_failed', 'Failed to create short link.'); ?>",
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: "<?php echo t('shorten.error', 'Error'); ?>",
                    text: "<?php echo t('shorten.create_failed', 'Failed to create short link.'); ?>",
                    icon: 'error'
                });
            });
        });
    </script>
</body>
</html>

