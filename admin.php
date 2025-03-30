<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');

// Initialize secure session
initSecureSession();

// Only enable error reporting if debug mode is true
if ($config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', 'logs/php_errors.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
}

// Store current URL for redirect after login
$_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];

// Simple memory cache for this request
$GLOBALS['_CACHE'] = [];

// Optimized IP Whitelist Check
function checkIPWhitelistOptimized() {
    global $config;
    
    // Get the actual client IP
    $ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // Check if IP is in whitelist
    if (!in_array($ip, $config['admin_ips'])) {
        error_log("Access denied for IP: " . $ip);
        require_once('templates/error.php');
        showError(403, 'Access Denied', 'Your IP (' . htmlspecialchars($ip) . ') is not whitelisted.');
        return false;
    }
    
    return true;
}

// Make sure this is called early in the script
if (!checkIPWhitelistOptimized()) {
    exit;
}

// Optimized rate limiting
function checkRateLimitOptimized() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $timeframe = 300; // 5 minutes
    $max_attempts = 5;
    
    // Use memory cache if available
    $cache_key = 'rate_' . $ip;
    if (isset($GLOBALS['_CACHE'][$cache_key])) {
        $attempts = $GLOBALS['_CACHE'][$cache_key];
    } else {
        // Use a single file per IP to reduce I/O
        $file = 'logs/rate_' . md5($ip) . '.txt';
        
        if (file_exists($file)) {
            $data = file_get_contents($file);
            $attempts = $data ? explode(',', $data) : [];
        } else {
            $attempts = [];
        }
        
        $GLOBALS['_CACHE'][$cache_key] = $attempts;
    }
    
    // Clean old attempts
    $time = time();
    $attempts = array_filter($attempts, function($t) use ($time, $timeframe) {
        return ($time - $t) < $timeframe;
    });
    
    if (count($attempts) >= $max_attempts) {
        $oldest = min($attempts);
        $wait = ($oldest + $timeframe) - $time;
        if ($wait > 0) {
            $minutes = ceil($wait / 60);
            die("Too many login attempts. Please try again in {$minutes} minutes.");
        }
    }
    
    // Add new attempt
    $attempts[] = $time;
    $GLOBALS['_CACHE'][$cache_key] = $attempts;
    
    // Save to file
    $dir = 'logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents('logs/rate_' . md5($ip) . '.txt', implode(',', $attempts));
    
    return true;
}

// Periodic cleanup (1% chance)
if (mt_rand(1, 100) === 1) {
    $files = glob('logs/rate_*.txt');
    $time = time();
    foreach ($files as $file) {
        if ($time - filemtime($file) > 3600) { // Remove files older than 1 hour
            @unlink($file);
        }
    }
}

// Login handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    error_log("Login attempt received");
    
    // Add rate limit check here
    if (!checkRateLimitOptimized()) {
        $error = "Too many login attempts. Please try again later.";
        error_log("Rate limit exceeded");
    } else {
        global $config;
        
        // Get credentials directly from config
        $valid_username = $config['admin_username']; 
        $valid_password = $config['password'];       
        
        $username = validateInput($_POST['username']);
        $password = $_POST['password'];
        
        error_log("Checking credentials for username: " . $username);
        
        if ($username === $valid_username && password_verify($password, $valid_password)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            error_log("Login successful for user: " . $username);
            
            header('Location: admin.php');
            exit;
        } else {
            error_log("Invalid credentials");
            $error = "Invalid credentials";
        }
    }
} else {
    // Only check session for non-login requests
    if (!validateSession() && !isset($_POST['username'])) {
        $login_template = __DIR__ . '/templates/login.php';
        if (file_exists($login_template)) {
            include($login_template);
        } else {
            die('Login template not found. Please ensure templates/login.php exists.');
        }
        exit;
    }
}

// Filtering parameters
$filter_date_start = $_GET['date_start'] ?? '';
$filter_date_end = $_GET['date_end'] ?? '';
$filter_ip = $_GET['ip'] ?? '';
$filter_type = $_GET['type'] ?? '';

// Logout handling
if (isset($_GET['logout'])) {
    destroySession();
    header('Location: admin.php');
    exit;
}

// Session timeout (30 minutes)
if (!validateSession()) {
    destroySession();
    header('Location: admin.php');
    exit;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            /* SweetAlert2 Dark Theme */
            .dark-theme-popup {
                background-color: #2d2d2d !important;
                color: #ffffff !important;
            }
            
            .dark-theme-title {
                color: #ffffff !important;
            }
            
            .dark-theme-content {
                color: #b3b3b3 !important;
            }
            
            .dark-theme-button {
                color: #ffffff !important;
            }
            
            /* Override SweetAlert2 icon colors */
            .swal2-icon.swal2-question {
                border-color: #4CAF50 !important;
                color: #4CAF50 !important;
            }
            
            /* Additional SweetAlert2 Dark Theme Styles */
            .dark-theme-input {
                background-color: #333333 !important;
                color: #ffffff !important;
                border: 1px solid #404040 !important;
            }
            
            .dark-theme-input:focus {
                border-color: #4CAF50 !important;
                box-shadow: 0 0 0 1px #4CAF50 !important;
            }
            
            /* Style the number input arrows */
            .dark-theme-input::-webkit-inner-spin-button,
            .dark-theme-input::-webkit-outer-spin-button {
                opacity: 1;
                background: #404040;
            }
            
            /* Override SweetAlert2 input label */
            .swal2-input-label {
                color: #ffffff !important;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="login-container">
                <?php if(isset($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="post" class="login-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="button button-primary">Login</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function showMessage($title, $message) {
    global $config;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f8f9fa;
                margin: 0;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .message-box {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 500px;
                width: 100%;
                text-align: center;
            }
            h1 {
                color: #dc3545;
                margin: 0 0 20px 0;
                font-size: 24px;
                font-weight: 500;
            }
            p {
                color: #6c757d;
                margin: 0;
                line-height: 1.5;
                font-size: 16px;
            }
            .back-link {
                margin-top: 20px;
                display: inline-block;
                color: #007bff;
                text-decoration: none;
            }
            .back-link:hover {
                text-decoration: underline;
            }
            .debug-info {
                margin-top: 20px;
                padding: 10px;
                background: #f8f9fa;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-family: monospace;
                font-size: 12px;
                white-space: pre-wrap;
            }
        </style>
    </head>
    <body>
        <div class="message-box">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="javascript:history.back()" class="back-link">← Go Back</a>
            
            <?php if (isset($config['debug']) && $config['debug']): ?>
            <div class="debug-info">
                <strong>Debug Information:</strong>
                <br>
                Status: Free version
                <br>
                PHP Version: <?php echo phpversion(); ?>
                <br>
                Server IP: <?php echo $_SERVER['SERVER_ADDR']; ?>
                <br>
                Error Log: Check /logs/php_errors.log for details
            </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_SESSION['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Admin Dashboard</h1>
                <div class="header-actions">
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <a href="gallery.php" class="button button-primary">View Gallery</a>
                        <a href="logs.php" class="button button-primary">View Logs</a>
                        <a href="share.php" class="button button-primary">Share Files</a>
                        <a href="?logout=1" class="button button-danger">Logout</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h2>System Information</h2>
                    <div class="info-container">
                        <div class="info-group">
                            <h3>PHP Information</h3>
                            <div class="info-item">
                                <span class="info-label">PHP Version</span>
                                <span class="info-value"><?php echo phpversion(); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">FFmpeg</span>
                                <span class="info-value"><?php 
                                    if (function_exists('exec')) {
                                        @exec('ffmpeg -version', $output, $return_var);
                                        echo ($return_var === 0) ? '✅ Installed' : '❌ Not Found';
                                    } elseif (function_exists('system')) {
                                        ob_start();
                                        @system('ffmpeg -version');
                                        $output = ob_get_clean();
                                        echo !empty($output) ? '✅ Installed' : '❌ Not Found';
                                    } else {
                                        echo '❌ Not Available';
                                    }
                                ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">ZIP</span>
                                <span class="info-value"><?php echo extension_loaded('zip') ? '✅ Enabled' : '❌ Disabled'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">cURL</span>
                                <span class="info-value"><?php echo extension_loaded('curl') ? '✅ Enabled' : '❌ Disabled'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Imagick</span>
                                <span class="info-value"><?php echo extension_loaded('imagick') ? '✅ Enabled' : '❌ Disabled'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">File Info</span>
                                <span class="info-value"><?php echo extension_loaded('fileinfo') ? '✅ Enabled' : '❌ Disabled'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Exif</span>
                                <span class="info-value"><?php echo extension_loaded('exif') ? '✅ Enabled' : '❌ Disabled'; ?></span>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <h3>Directory Information</h3>
                            <div class="info-item">
                                <span class="info-label">Upload Directory</span>
                                <span class="info-value"><?php echo is_writable($config['upload_dir']) ? '✅ Writable' : '❌ Not Writable'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Logs Directory</span>
                                <span class="info-value"><?php echo is_writable('logs') ? '✅ Writable' : '❌ Not Writable'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Temp Directory</span>
                                <span class="info-value"><?php echo is_writable(sys_get_temp_dir()) ? '✅ Writable' : '❌ Not Writable'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <h2><span class="icon">💾</span> Storage Information</h2>
                    <div class="info-container">
                        <?php
                        // Get disk space for the actual hosting directory instead of root
                        $base_path = dirname(__FILE__);
                        $total_space = @disk_total_space($base_path);
                        $free_space = @disk_free_space($base_path);
                        
                        // Check if we got valid values
                        if ($total_space && $free_space) {
                            $used_space = $total_space - $free_space;
                            $used_percentage = round(($used_space / $total_space) * 100, 2);
                        } else {
                            // Fallback values if we can't get disk space
                            $used_space = 0;
                            $free_space = 0;
                            $total_space = 0;
                            $used_percentage = 0;
                        }
                        
                        // Get upload directory size
                        function getDirSize($dir) {
                            $size = 0;
                            if (is_dir($dir)) {
                                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
                                    $size += $file->getSize();
                                }
                            }
                            return $size;
                        }
                        
                        $upload_size = getDirSize($config['upload_dir']);
                        ?>
                        
                        <div class="info-group">
                            <h3>Upload Directory</h3>
                            <div class="storage-meter">
                                <div class="storage-used" style="width: <?php echo $used_percentage; ?>%"></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Size</span>
                                <span class="info-value"><?php echo formatBytes($upload_size); ?></span>
                            </div>
                            <?php
                            // Get file type counts
                            $file_types = [
                                'images' => ['jpg', 'jpeg', 'png', 'webp'],
                                'gifs' => ['gif'],
                                'videos' => ['mp4', 'webm', 'mov'],
                                'other' => []
                            ];
                            
                            $type_counts = array_fill_keys(array_keys($file_types), 0);
                            $total_files = 0;
                            
                            if (is_dir($config['upload_dir'])) {
                                foreach (new DirectoryIterator($config['upload_dir']) as $file) {
                                    if ($file->isFile()) {
                                        $ext = strtolower($file->getExtension());
                                        $counted = false;
                                        
                                        foreach ($file_types as $type => $extensions) {
                                            if (in_array($ext, $extensions)) {
                                                $type_counts[$type]++;
                                                $counted = true;
                                                break;
                                            }
                                        }
                                        
                                        if (!$counted) {
                                            $type_counts['other']++;
                                        }
                                        
                                        $total_files++;
                                    }
                                }
                            }
                            ?>
                            <div class="info-item">
                                <span class="info-label" data-type="total">Total Files</span>
                                <span class="info-value"><?php echo $total_files; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label" data-type="images">Images</span>
                                <span class="info-value"><?php echo $type_counts['images']; ?> files</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label" data-type="gifs">GIFs</span>
                                <span class="info-value"><?php echo $type_counts['gifs']; ?> files</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label" data-type="videos">Videos</span>
                                <span class="info-value"><?php echo $type_counts['videos']; ?> files</span>
                            </div>
                            <?php if ($type_counts['other'] > 0): ?>
                            <div class="info-item">
                                <span class="info-label" data-type="other">Other Files</span>
                                <span class="info-value"><?php echo $type_counts['other']; ?> files</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($total_space && $free_space): ?>
                        <div class="info-group">
                            <h3>Hosting Space</h3>
                            <div class="info-item">
                                <span class="info-label">Used Space</span>
                                <span class="info-value"><?php echo formatBytes($used_space); ?> (<?php echo $used_percentage; ?>%)</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Free Space</span>
                                <span class="info-value"><?php echo formatBytes($free_space); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Space</span>
                                <span class="info-value"><?php echo formatBytes($total_space); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                function getSystemHealth() {
                    return [
                        'cpu_usage' => sys_getloadavg()[0],
                        'php_errors' => error_get_last(),
                        'disk_health' => getDiskUsage()['percentage'] < 90
                    ];
                }

                $health = getSystemHealth();
                ?>

                <div class="dashboard-card">
                    <h2><span class="icon">🏥</span> System Health</h2>
                    <div class="info-container">
                        <div class="info-group">
                            <div class="info-item">
                                <span class="info-label">Disk Space</span>
                                <span class="info-value <?php echo $health['disk_health'] ? 'healthy' : 'warning'; ?>">
                                    <?php echo $health['disk_health'] ? '✅ Healthy' : '⚠️ Low Space'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">CPU Usage</span>
                                <span class="info-value <?php echo $health['cpu_usage'] < 80 ? 'healthy' : 'warning'; ?>">
                                    <?php echo $health['cpu_usage'] < 80 ? '✅ Normal' : '⚠️ High'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">PHP Status</span>
                                <span class="info-value <?php echo !$health['php_errors'] ? 'healthy' : 'warning'; ?>">
                                    <?php echo !$health['php_errors'] ? '✅ No Errors' : '⚠️ Errors Detected'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="toast-container"></div>
        
        <?php if (isset($_SESSION['message'])): ?>
        <script>
            showToast(<?php echo json_encode($_SESSION['message']); ?>, 'success');
        </script>
        <?php unset($_SESSION['message']); endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <script>
            showToast(<?php echo json_encode($_SESSION['error']); ?>, 'error');
        </script>
        <?php unset($_SESSION['error']); endif; ?>
    </body>
    </html>
    <?php
}

