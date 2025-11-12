<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');

// Initialize secure session first
initSecureSession();

// Then load language system (after session is active)
require_once('includes/language.php');

// Reload translations now that session is active
reloadTranslations();

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

// Check IP whitelist if enabled in config
if ($config['ip_whitelist_enabled'] && !checkIPWhitelistOptimized()) {
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
        $valid_username = $config['admin_username'];  // This matches your config.php
        $valid_password = $config['password'];        // This matches your config.php
        
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
        <title><?php echo t('admin.dashboard.title', 'Admin Dashboard'); ?></title>
        <link rel="stylesheet" href="assets/css/style.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
        <style>
            .language-switcher {
                display: inline-block;
                margin-right: 10px;
            }
            
            .language-switcher select {
                padding: 10px 20px;
                border: 0;
                border-radius: 8px;
                background: var(--primary-color, #4f46e5);
                color: #fff;
                font-size: 1rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right 8px center;
                background-size: 1em;
                padding-right: 35px;
            }
            
            .language-switcher select:hover {
                background-color: var(--primary-dark, #4338ca);
            }
            
            .language-switcher select:focus {
                outline: none;
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3);
            }
            
            .language-switcher select option {
                background: var(--bg-color, #fff);
                color: var(--text-color, #000);
                padding: 8px;
            }
            
            @media (max-width: 768px) {
                .language-switcher {
                    width: 100%;
                    margin-bottom: 10px;
                    margin-right: 0;
                }
                
                .language-switcher select {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php echo t('admin.dashboard.title', 'Admin Dashboard'); ?></h1>
                <div class="header-actions">
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <div class="language-switcher">
                            <select id="language-select" onchange="changeLanguage(this.value)">
                                <?php 
                                $current_lang = getCurrentLanguage();
                                $languages = getSupportedLanguages();
                                foreach ($languages as $code => $name): 
                                ?>
                                    <option value="<?php echo $code; ?>" <?php echo $current_lang === $code ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <a href="gallery.php" class="button button-primary"><?php echo t('admin.buttons.view_gallery', 'View Gallery'); ?></a>
                        <a href="logs.php" class="button button-primary"><?php echo t('admin.buttons.view_logs', 'View Logs'); ?></a>
                        <a href="share.php" class="button button-primary"><?php echo t('admin.buttons.share_files', 'Share Files'); ?></a>
                        <a href="shorten.php" class="button button-primary"><?php echo t('admin.buttons.shorten_links', 'Shorten Links'); ?></a>
                        <a href="?logout=1" class="button button-danger"><?php echo t('admin.buttons.logout', 'Logout'); ?></a>
                    <?php endif; ?>
                </div>
            </div>


            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h2><?php echo t('admin.dashboard.system_info', 'System Information'); ?></h2>
                    <div class="info-container">
                        <div class="info-group">
                            <h3><?php echo t('admin.php_info.title', 'PHP Information'); ?></h3>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.php_info.php_version', 'PHP Version'); ?></span>
                                <span class="info-value"><?php echo phpversion(); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.php_info.ffmpeg', 'FFmpeg'); ?></span>
                                <span class="info-value"><?php 
                                    if (function_exists('exec')) {
                                        @exec('ffmpeg -version', $output, $return_var);
                                        echo ($return_var === 0) ? '✅ ' . t('admin.php_info.installed', 'Installed') : '❌ ' . t('admin.php_info.not_found', 'Not Found');
                                    } elseif (function_exists('system')) {
                                        ob_start();
                                        @system('ffmpeg -version');
                                        $output = ob_get_clean();
                                        echo !empty($output) ? '✅ ' . t('admin.php_info.installed', 'Installed') : '❌ ' . t('admin.php_info.not_found', 'Not Found');
                                    } else {
                                        echo '❌ ' . t('admin.php_info.not_available', 'Not Available');
                                    }
                                ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.php_info.zip', 'ZIP'); ?></span>
                                <span class="info-value"><?php echo extension_loaded('zip') ? '✅ ' . t('admin.php_info.enabled', 'Enabled') : '❌ ' . t('admin.php_info.disabled', 'Disabled'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.php_info.curl', 'cURL'); ?></span>
                                <span class="info-value"><?php echo extension_loaded('curl') ? '✅ ' . t('admin.php_info.enabled', 'Enabled') : '❌ ' . t('admin.php_info.disabled', 'Disabled'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.php_info.imagick', 'Imagick'); ?></span>
                                <span class="info-value"><?php echo extension_loaded('imagick') ? '✅ ' . t('admin.php_info.enabled', 'Enabled') : '❌ ' . t('admin.php_info.disabled', 'Disabled'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.php_info.file_info', 'File Info'); ?></span>
                                <span class="info-value"><?php echo extension_loaded('fileinfo') ? '✅ ' . t('admin.php_info.enabled', 'Enabled') : '❌ ' . t('admin.php_info.disabled', 'Disabled'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.php_info.exif', 'Exif'); ?></span>
                                <span class="info-value"><?php echo extension_loaded('exif') ? '✅ ' . t('admin.php_info.enabled', 'Enabled') : '❌ ' . t('admin.php_info.disabled', 'Disabled'); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <h3><?php echo t('admin.directory_info.title', 'Directory Information'); ?></h3>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.directory_info.upload_directory', 'Upload Directory'); ?></span>
                                <span class="info-value"><?php echo is_writable($config['upload_dir']) ? '✅ ' . t('admin.directory_info.writable', 'Writable') : '❌ ' . t('admin.directory_info.not_writable', 'Not Writable'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.directory_info.logs_directory', 'Logs Directory'); ?></span>
                                <span class="info-value"><?php echo is_writable('logs') ? '✅ ' . t('admin.directory_info.writable', 'Writable') : '❌ ' . t('admin.directory_info.not_writable', 'Not Writable'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.directory_info.temp_directory', 'Temp Directory'); ?></span>
                                <span class="info-value"><?php echo is_writable(sys_get_temp_dir()) ? '✅ ' . t('admin.directory_info.writable', 'Writable') : '❌ ' . t('admin.directory_info.not_writable', 'Not Writable'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <h2><span class="icon">💾</span> <?php echo t('admin.dashboard.storage_info', 'Storage Information'); ?></h2>
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
                            <h3><?php echo t('admin.storage.upload_directory', 'Upload Directory'); ?></h3>
                            <div class="storage-meter">
                                <div class="storage-used" style="width: <?php echo $used_percentage; ?>%"></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.storage.total_size', 'Total Size'); ?></span>
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
                                <span class="info-label" data-type="total"><?php echo t('admin.storage.total_files', 'Total Files'); ?></span>
                                <span class="info-value"><?php echo $total_files; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label" data-type="images"><?php echo t('admin.storage.images', 'Images'); ?></span>
                                <span class="info-value"><?php echo $type_counts['images']; ?> <?php echo t('admin.storage.files', 'files'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label" data-type="gifs"><?php echo t('admin.storage.gifs', 'GIFs'); ?></span>
                                <span class="info-value"><?php echo $type_counts['gifs']; ?> <?php echo t('admin.storage.files', 'files'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label" data-type="videos"><?php echo t('admin.storage.videos', 'Videos'); ?></span>
                                <span class="info-value"><?php echo $type_counts['videos']; ?> <?php echo t('admin.storage.files', 'files'); ?></span>
                            </div>
                            <?php if ($type_counts['other'] > 0): ?>
                            <div class="info-item">
                                <span class="info-label" data-type="other"><?php echo t('admin.storage.other_files', 'Other Files'); ?></span>
                                <span class="info-value"><?php echo $type_counts['other']; ?> <?php echo t('admin.storage.files', 'files'); ?></span>
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
                    <h2><span class="icon">🏥</span> <?php echo t('admin.dashboard.system_health', 'System Health'); ?></h2>
                    <div class="info-container">
                        <div class="info-group">
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.system_health.disk_space', 'Disk Space'); ?></span>
                                <span class="info-value <?php echo $health['disk_health'] ? 'healthy' : 'warning'; ?>">
                                    <?php echo $health['disk_health'] ? '✅ ' . t('admin.system_health.healthy', 'Healthy') : '⚠️ ' . t('admin.system_health.low_space', 'Low Space'); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.system_health.cpu_usage', 'CPU Usage'); ?></span>
                                <span class="info-value <?php echo $health['cpu_usage'] < 80 ? 'healthy' : 'warning'; ?>">
                                    <?php echo $health['cpu_usage'] < 80 ? '✅ ' . t('admin.system_health.normal', 'Normal') : '⚠️ ' . t('admin.system_health.high', 'High'); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php echo t('admin.system_health.php_status', 'PHP Status'); ?></span>
                                <span class="info-value <?php echo !$health['php_errors'] ? 'healthy' : 'warning'; ?>">
                                    <?php echo !$health['php_errors'] ? '✅ ' . t('admin.system_health.no_errors', 'No Errors') : '⚠️ ' . t('admin.system_health.errors_detected', 'Errors Detected'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <h2><span class="icon">💾</span> <?php echo t('admin.backup.title', 'Backup & Restore'); ?></h2>
                    <div class="info-container">
                        <div class="info-group">
                            <p style="margin-bottom: 20px; color: #666;">
                                <?php echo t('admin.backup.description', 'Create backups of all images, shares, and links. Restore from previous backups when needed.'); ?>
                            </p>
                            
                            <div style="margin-bottom: 20px;">
                                <button id="create-backup-btn" class="button button-primary" style="margin-right: 10px;">
                                    <?php echo t('admin.backup.create_backup', 'Create Backup'); ?>
                                </button>
                                <label for="upload-backup-input" class="button button-primary" style="margin-right: 10px; cursor: pointer; display: inline-block;">
                                    <?php echo t('admin.backup.upload_backup', 'Upload Backup'); ?>
                                    <input type="file" id="upload-backup-input" accept=".zip" style="display: none;">
                                </label>
                                <span id="backup-status" style="display: none; margin-left: 10px; color: #666;"></span>
                            </div>
                            
                            <div id="backup-list-container">
                                <h3 style="margin-top: 20px; margin-bottom: 10px;"><?php echo t('admin.backup.existing_backups', 'Existing Backups'); ?></h3>
                                <div id="backup-list" style="max-height: 400px; overflow-y: auto;">
                                    <p style="color: #999; text-align: center; padding: 20px;"><?php echo t('admin.backup.loading', 'Loading backups...'); ?></p>
                                </div>
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
        
        <script>
            /**
             * Shows a toast notification
             * @param {string} message - The message to display
             * @param {string} type - The type of toast (info, success, error, warning)
             * @param {number} duration - How long to display the toast in ms
             */
            function showToast(message, type = "info", duration = 3000) {
                const container = document.getElementById("toast-container");
                if (!container) {
                    console.error("Toast container not found");
                    return;
                }
                
                const toast = document.createElement("div");
                toast.className = `toast ${type}`;
                toast.style.animation = "slideIn 0.3s ease-out";
                
                // Select icon based on type
                let icon = "🔔";
                switch (type) {
                    case "success":
                        icon = "✅";
                        break;
                    case "error":
                        icon = "❌";
                        break;
                    case "warning":
                        icon = "⚠";
                        break;
                }
                
                toast.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-message">${message}</span>`;
                container.appendChild(toast);
                
                // Remove the toast after duration
                setTimeout(() => {
                    toast.style.animation = "slideOut 0.3s ease-out forwards";
                    setTimeout(() => {
                        if (container.contains(toast)) {
                            container.removeChild(toast);
                        }
                    }, 300);
                }, duration);
            }

            function changeLanguage(lang) {
                // Redirect to set_language.php with the selected language
                // Use just the page name to avoid URL encoding issues
                const currentPage = window.location.pathname.split('/').pop() || 'admin.php';
                window.location.href = 'set_language.php?lang=' + lang + '&redirect=' + encodeURIComponent(currentPage);
            }

            // Backup and Restore functionality
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            
            // Load backup list on page load
            function loadBackupList() {
                fetch('backup_handler.php?action=list')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP error! status: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            displayBackupList(data.backups);
                        } else {
                            document.getElementById('backup-list').innerHTML = 
                                '<p style="color: #dc3545; text-align: center; padding: 20px;">' + 
                                escapeHtml(data.error || '<?php echo t('admin.backup.load_failed', 'Failed to load backups'); ?>') + '</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading backups:', error);
                        document.getElementById('backup-list').innerHTML = 
                            '<p style="color: #dc3545; text-align: center; padding: 20px;"><?php echo t('admin.backup.load_error', 'Error loading backups'); ?>: ' + escapeHtml(error.message) + '</p>';
                    });
            }

            function displayBackupList(backups) {
                const container = document.getElementById('backup-list');
                
                if (backups.length === 0) {
                    container.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;"><?php echo t('admin.backup.no_backups', 'No backups found'); ?></p>';
                    return;
                }
                
                let html = '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr style="border-bottom: 2px solid #ddd;">';
                html += '<th style="padding: 10px; text-align: left;"><?php echo t('admin.backup.filename', 'Filename'); ?></th>';
                html += '<th style="padding: 10px; text-align: left;"><?php echo t('admin.backup.size', 'Size'); ?></th>';
                html += '<th style="padding: 10px; text-align: left;"><?php echo t('admin.backup.created', 'Created'); ?></th>';
                html += '<th style="padding: 10px; text-align: right;"><?php echo t('admin.backup.actions', 'Actions'); ?></th>';
                html += '</tr></thead><tbody>';
                
                backups.forEach(backup => {
                    html += '<tr style="border-bottom: 1px solid #eee;">';
                    html += '<td style="padding: 10px;">' + escapeHtml(backup.filename) + '</td>';
                    html += '<td style="padding: 10px;">' + backup.formatted_size + '</td>';
                    html += '<td style="padding: 10px;">' + backup.formatted_date + '</td>';
                    html += '<td style="padding: 10px; text-align: right;">';
                    html += '<button class="button button-primary" style="margin-right: 5px; padding: 5px 10px; font-size: 0.9em;" onclick="restoreBackup(\'' + escapeHtml(backup.filename) + '\')"><?php echo t('admin.backup.restore', 'Restore'); ?></button>';
                    html += '<button class="button button-danger" style="padding: 5px 10px; font-size: 0.9em;" onclick="deleteBackup(\'' + escapeHtml(backup.filename) + '\')"><?php echo t('admin.backup.delete', 'Delete'); ?></button>';
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                container.innerHTML = html;
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Create backup
            document.getElementById('create-backup-btn').addEventListener('click', function() {
                const btn = this;
                const status = document.getElementById('backup-status');
                
                btn.disabled = true;
                btn.textContent = '<?php echo t('admin.backup.creating', 'Creating...'); ?>';
                status.style.display = 'inline';
                status.textContent = '';
                
                const formData = new FormData();
                formData.append('action', 'create');
                formData.append('csrf_token', csrfToken);
                
                fetch('backup_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        status.textContent = '<?php echo t('admin.backup.created_success', 'Backup created successfully!'); ?>';
                        status.style.color = '#28a745';
                        showToast('<?php echo t('admin.backup.backup_created', 'Backup created successfully'); ?>', 'success');
                        loadBackupList();
                    } else {
                        const errorMsg = data.error || '<?php echo t('admin.backup.create_failed', 'Failed to create backup'); ?>';
                        status.textContent = errorMsg;
                        status.style.color = '#dc3545';
                        showToast(errorMsg, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error creating backup:', error);
                    const errorMsg = '<?php echo t('admin.backup.create_failed', 'Failed to create backup'); ?>: ' + error.message;
                    status.textContent = errorMsg;
                    status.style.color = '#dc3545';
                    showToast(errorMsg, 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = '<?php echo t('admin.backup.create_backup', 'Create Backup'); ?>';
                    setTimeout(() => {
                        status.style.display = 'none';
                    }, 5000);
                });
            });

            // Restore backup
            function restoreBackup(filename) {
                if (!confirm('<?php echo t('admin.backup.restore_confirm', 'Are you sure you want to restore from this backup? This will overwrite existing data.'); ?>')) {
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'restore');
                formData.append('filename', filename);
                formData.append('csrf_token', csrfToken);
                
                fetch('backup_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('<?php echo t('admin.backup.restored_success', 'Backup restored successfully'); ?>', 'success');
                        loadBackupList();
                    } else {
                        showToast(data.error || '<?php echo t('admin.backup.restore_failed', 'Failed to restore backup'); ?>', 'error');
                    }
                })
                .catch(error => {
                    showToast('<?php echo t('admin.backup.restore_failed', 'Failed to restore backup'); ?>', 'error');
                });
            }

            // Delete backup
            function deleteBackup(filename) {
                if (!confirm('<?php echo t('admin.backup.delete_confirm', 'Are you sure you want to delete this backup? This action cannot be undone.'); ?>')) {
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('filename', filename);
                formData.append('csrf_token', csrfToken);
                
                fetch('backup_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('<?php echo t('admin.backup.deleted_success', 'Backup deleted successfully'); ?>', 'success');
                        loadBackupList();
                    } else {
                        showToast(data.error || '<?php echo t('admin.backup.delete_failed', 'Failed to delete backup'); ?>', 'error');
                    }
                })
                .catch(error => {
                    showToast('<?php echo t('admin.backup.delete_failed', 'Failed to delete backup'); ?>', 'error');
                });
            }

            // Upload backup file
            document.getElementById('upload-backup-input').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                if (!file.name.endsWith('.zip')) {
                    showToast('<?php echo t('admin.backup.invalid_file_type', 'Please select a ZIP file'); ?>', 'error');
                    e.target.value = '';
                    return;
                }
                
                if (!confirm('<?php echo t('admin.backup.upload_confirm', 'Upload and restore from this backup file? This will overwrite existing data.'); ?>')) {
                    e.target.value = '';
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('backup_file', file);
                formData.append('csrf_token', csrfToken);
                
                const status = document.getElementById('backup-status');
                status.style.display = 'inline';
                status.textContent = '<?php echo t('admin.backup.uploading', 'Uploading...'); ?>';
                status.style.color = '#666';
                
                fetch('backup_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        status.textContent = '<?php echo t('admin.backup.uploaded_success', 'Backup uploaded successfully!'); ?>';
                        status.style.color = '#28a745';
                        showToast('<?php echo t('admin.backup.backup_uploaded', 'Backup uploaded successfully'); ?>', 'success');
                        
                        // Ask if user wants to restore immediately
                        if (confirm('<?php echo t('admin.backup.restore_now', 'Do you want to restore from this backup now? This will overwrite existing data.'); ?>')) {
                            restoreBackup(data.filename);
                        } else {
                            loadBackupList();
                        }
                    } else {
                        const errorMsg = data.error || '<?php echo t('admin.backup.upload_failed', 'Failed to upload backup'); ?>';
                        status.textContent = errorMsg;
                        status.style.color = '#dc3545';
                        showToast(errorMsg, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error uploading backup:', error);
                    const errorMsg = '<?php echo t('admin.backup.upload_failed', 'Failed to upload backup'); ?>: ' + error.message;
                    status.textContent = errorMsg;
                    status.style.color = '#dc3545';
                    showToast(errorMsg, 'error');
                })
                .finally(() => {
                    e.target.value = '';
                    setTimeout(() => {
                        status.style.display = 'none';
                    }, 5000);
                });
            });

            // Load backup list on page load
            loadBackupList();
        </script>
    </body>
    </html>
    <?php
}

