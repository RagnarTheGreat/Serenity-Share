<?php
require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');

// Initialize secure session instead of session_start()
initSecureSession();

// Load language system (after session is initialized)
require_once('includes/language.php');

// Debug: Log session language
error_log("Logs.php: Session ID: " . session_id());
error_log("Logs.php: Session language: " . (isset($_SESSION['language']) ? $_SESSION['language'] : 'NOT SET'));
error_log("Logs.php: getCurrentLanguage() returns: " . getCurrentLanguage());

// Force reload translations to ensure we get the correct language from session
reloadTranslations();

// Replace the basic session check with secure validation
if (!validateSession()) {
    destroySession();
    header('Location: admin.php');
    exit;
}

// Check IP whitelist
function checkIPWhitelist() {
    global $config;
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (!isset($config['admin_ips']) || !is_array($config['admin_ips'])) {
        error_log("Warning: admin_ips configuration is missing or invalid");
        return;
    }
    
    if (!in_array($ip, $config['admin_ips'])) {
        require_once('templates/error.php');
        showError(403, 'Access Denied', 'Your IP is not whitelisted.');
    }
}

// Check IP whitelist if enabled in config
if ($config['ip_whitelist_enabled']) {
    checkIPWhitelist();
}

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

// Handle log deletion
if (isset($_POST['delete_logs']) && isset($_POST['csrf_token']) && isset($_POST['selected_timestamps'])) {
    error_log("Delete logs request received");
    error_log("Selected timestamps: " . $_POST['selected_timestamps']);
    
    try {
        // Verify CSRF token
        if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token verification failed");
            throw new Exception('Invalid CSRF token');
        }

        $log_file = "logs/ip_logs.txt";
        if (file_exists($log_file)) {
            // Get selected timestamps
            $selected_timestamps = json_decode($_POST['selected_timestamps'], true);
            error_log("Decoded timestamps: " . print_r($selected_timestamps, true));
            
            if (empty($selected_timestamps)) {
                error_log("No logs selected");
                throw new Exception('No logs selected');
            }

            // Read all logs
            $logs = array_filter(file($log_file, FILE_IGNORE_NEW_LINES), function($line) {
                return !empty(trim($line)); // Skip empty lines
            });
            $new_logs = [];
            $deleted_count = 0;

            // Keep only non-selected logs
            foreach ($logs as $log) {
                $keep = true;
                foreach ($selected_timestamps as $timestamp) {
                    if (strpos(trim($log), "[$timestamp]") === 0) {
                        $keep = false;
                        $deleted_count++;
                        break;
                    }
                }
                if ($keep) {
                    $new_logs[] = $log;
                }
            }

            // Write back the remaining logs (with single newline at end)
            file_put_contents($log_file, implode("\n", array_filter($new_logs)) . "\n");
            $_SESSION['message'] = "✅ Successfully deleted $deleted_count log(s)";
        } else {
            $_SESSION['message'] = "ℹ️ No logs to delete";
        }
    } catch (Exception $e) {
        error_log("Error in log deletion: " . $e->getMessage());
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
    }
    
    header('Location: logs.php');
    exit;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get logs
$log_file = "logs/ip_logs.txt";
$logs = file_exists($log_file) ? array_filter(file($log_file), function($line) {
    return !empty(trim($line)); // Skip empty lines
}) : [];
$logs = array_reverse($logs); // Show newest first

// Filtering parameters
$filter_date = $_GET['date'] ?? '';
$filter_ip = $_GET['ip'] ?? '';
$filter_type = $_GET['type'] ?? '';

// Add the same functions as above
function showMessage($title, $message) {
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
        </style>
    </head>
    <body>
        <div class="message-box">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="javascript:history.back()" class="back-link">← Go Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('logs.title', 'View Logs'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/logs.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo t('logs.title', 'View Logs'); ?></h1>
            <div class="header-buttons">
                <button onclick="refreshLogs()" class="button button-primary" id="refresh-button">
                    🔄 Refresh
                </button>
                <label class="auto-refresh-label">
                    <input type="checkbox" id="auto-refresh" onchange="toggleAutoRefresh()">
                    Auto-refresh
                </label>
                <a href="admin.php" class="button button-primary"><?php echo t('share.back_to_dashboard', 'Back to Dashboard'); ?></a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="get" style="display: flex; gap: 15px; width: 100%;">
                <div class="filter-group">
                    <label for="date">Date:</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="filter-group">
                    <label for="ip">IP Address:</label>
                    <input type="text" id="ip" name="ip" value="<?php echo htmlspecialchars($filter_ip); ?>" placeholder="Filter by IP">
                </div>
                <div class="filter-group">
                    <label for="type">Type:</label>
                    <select id="type" name="type">
                        <option value="">All Types</option>
                        <option value="image" <?php echo $filter_type === 'image' ? 'selected' : ''; ?>>Images</option>
                        <option value="video" <?php echo $filter_type === 'video' ? 'selected' : ''; ?>>Videos</option>
                    </select>
                </div>
                <div class="filter-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="button button-primary">Apply Filters</button>
                </div>
            </form>
        </div>
        
        <div class="select-controls">
            <div class="control-buttons">
                <button type="button" class="button button-primary" onclick="toggleAll(this)" id="select-all-button">
                    ✓ Select All
                </button>
                <form method="post" id="delete-form" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="delete_logs" value="1">
                    <input type="hidden" name="selected_timestamps" id="selected-timestamps">
                    <button type="submit" class="button button-danger" disabled id="delete-button">
                        🗑️ Delete Selected
                    </button>
                </form>
            </div>
        </div>
        
        <div class="logs-container">
            <?php 
            foreach($logs as $index => $log): 
                $log = trim($log); // Ensure no extra whitespace
                if (empty($log)) continue; // Skip empty lines
                
                // Apply filters
                if ($filter_date && !strpos($log, $filter_date)) continue;
                if ($filter_ip && !strpos($log, $filter_ip)) continue;
                if ($filter_type) {
                    $is_video = strpos($log, '.mp4') !== false || strpos($log, '.webm') !== false;
                    if ($filter_type === 'video' && !$is_video) continue;
                    if ($filter_type === 'image' && $is_video) continue;
                }
                
                // Extract timestamp for identification
                preg_match('/\[(.*?)\]/', $log, $matches);
                $timestamp = $matches[1] ?? '';
                if (empty($timestamp)) continue; // Skip entries without timestamp
            ?>
                <div class="log-entry">
                    <input type="checkbox" 
                           class="log-checkbox" 
                           name="selected_logs[]" 
                           value="<?php echo htmlspecialchars($timestamp); ?>" 
                           onchange="updateDeleteButton()">
                    <div class="log-timestamp"><?php echo htmlspecialchars($timestamp); ?></div>
                    <div class="log-details"><?php echo htmlspecialchars(trim(str_replace("[$timestamp]", '', $log))); ?></div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($logs)): ?>
                <p>No logs found.</p>
            <?php endif; ?>
        </div>
        <div class="export-controls">
            <button onclick="exportLogs('csv')" class="button">
                <span class="icon">📊</span> Export CSV
            </button>
            <button onclick="exportLogs('json')" class="button">
                <span class="icon">📋</span> Export JSON
            </button>
        </div>

        <!-- Add this new delete dialog -->
        <div id="delete-dialog" class="delete-dialog" style="display: none;">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete the selected logs? This action cannot be undone.</p>
            <div class="delete-dialog-buttons">
                <button class="button-cancel" onclick="hideDeleteDialog()">Cancel</button>
                <button class="button-confirm-delete" onclick="proceedWithDelete()">Delete</button>
            </div>
        </div>
        <div id="delete-dialog-overlay" class="delete-dialog-overlay" style="display: none;" onclick="hideDeleteDialog()"></div>
    </div>
    <div id="toast-container"></div>
    <script>
    function showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.style.animation = 'slideIn 0.3s ease-out';
        
        let icon = '🔔';
        switch(type) {
            case 'success':
                icon = '✅';
                break;
            case 'error':
                icon = '❌';
                break;
            case 'warning':
                icon = '⚠️';
                break;
        }
        
        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => {
                container.removeChild(toast);
            }, 300);
        }, duration);
    }

    function toggleAll(button) {
        const checkboxes = document.getElementsByClassName('log-checkbox');
        const isSelecting = button.textContent.includes('Select All');
        
        for (let checkbox of checkboxes) {
            checkbox.checked = isSelecting;
        }
        
        button.innerHTML = isSelecting ? '✗ Deselect All' : '✓ Select All';
        updateDeleteButton();
    }

    function updateDeleteButton() {
        const checkboxes = document.getElementsByClassName('log-checkbox');
        const deleteButton = document.getElementById('delete-button');
        const selectAllButton = document.getElementById('select-all-button');
        let checkedCount = 0;
        let totalCount = checkboxes.length;
        
        for (let checkbox of checkboxes) {
            if (checkbox.checked) checkedCount++;
        }
        
        deleteButton.disabled = checkedCount === 0;
        
        // Update select all button text based on selection state
        if (checkedCount === 0) {
            selectAllButton.innerHTML = '✓ Select All';
        } else if (checkedCount === totalCount) {
            selectAllButton.innerHTML = '✗ Deselect All';
        }
    }

    function confirmDelete() {
        const checkboxes = document.getElementsByClassName('log-checkbox');
        const selectedTimestamps = [];
        
        for (let checkbox of checkboxes) {
            if (checkbox.checked) {
                selectedTimestamps.push(checkbox.value);
            }
        }
        
        if (selectedTimestamps.length === 0) {
            showToast('No logs selected', 'error');
            return false;
        }
        
        // Set the selected timestamps
        document.getElementById('selected-timestamps').value = JSON.stringify(selectedTimestamps);
        
        // Show the delete dialog
        document.getElementById('delete-dialog').style.display = 'block';
        document.getElementById('delete-dialog-overlay').style.display = 'block';
        
        return false;
    }

    function hideDeleteDialog() {
        document.getElementById('delete-dialog').style.display = 'none';
        document.getElementById('delete-dialog-overlay').style.display = 'none';
    }

    function proceedWithDelete() {
        const form = document.getElementById('delete-form');
        form.submit();
        hideDeleteDialog();
    }

    let autoRefreshInterval;
    const REFRESH_INTERVAL = 10000; // 10 seconds

    function refreshLogs() {
        const button = document.getElementById('refresh-button');
        const icon = button.firstChild;
        
        // Add spin animation
        button.classList.add('refresh-spin');
        
        // Get current filter values
        const date = document.getElementById('date')?.value || '';
        const ip = document.getElementById('ip')?.value || '';
        const type = document.getElementById('type')?.value || '';
        
        // Construct URL with current filters
        const url = new URL(window.location.href);
        url.searchParams.set('date', date);
        url.searchParams.set('ip', ip);
        url.searchParams.set('type', type);
        url.searchParams.set('_', Date.now()); // Prevent caching
        
        // Fetch new logs
        fetch(url)
            .then(response => response.text())
            .then(html => {
                // Create a temporary container
                const temp = document.createElement('div');
                temp.innerHTML = html;
                
                // Replace logs container content
                const newLogs = temp.querySelector('.logs-container').innerHTML;
                document.querySelector('.logs-container').innerHTML = newLogs;
                
                // Update checkboxes and buttons
                updateDeleteButton();
            })
            .catch(error => console.error('Error refreshing logs:', error))
            .finally(() => {
                // Remove spin animation
                setTimeout(() => {
                    button.classList.remove('refresh-spin');
                }, 1000);
            });
    }

    function toggleAutoRefresh() {
        const autoRefreshCheckbox = document.getElementById('auto-refresh');
        
        if (autoRefreshCheckbox.checked) {
            // Start auto-refresh
            autoRefreshInterval = setInterval(refreshLogs, REFRESH_INTERVAL);
            localStorage.setItem('autoRefreshEnabled', 'true');
        } else {
            // Stop auto-refresh
            clearInterval(autoRefreshInterval);
            localStorage.setItem('autoRefreshEnabled', 'false');
        }
    }

    // Initialize auto-refresh state from localStorage
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOMContentLoaded event fired');
        const autoRefreshCheckbox = document.getElementById('auto-refresh');
        const savedState = localStorage.getItem('autoRefreshEnabled');
        
        if (savedState === 'true') {
            autoRefreshCheckbox.checked = true;
            toggleAutoRefresh();
        }

        // Add this form handler
        const deleteForm = document.getElementById('delete-form');
        if (deleteForm) {
            console.log('Delete form found, adding submit handler');
            deleteForm.onsubmit = function(e) {
                console.log('Form submit triggered');
                e.preventDefault();
                confirmDelete();
            };
        } else {
            console.log('Delete form not found');
        }

        // Check for a session message and display it
        <?php if (isset($_SESSION['message'])): ?>
            showToast("<?php echo addslashes($_SESSION['message']); ?>", 'success');
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
    });

    // Cleanup interval when leaving page
    window.addEventListener('beforeunload', () => {
        clearInterval(autoRefreshInterval);
    });

    function exportLogs(format) {
        const logs = Array.from(document.querySelectorAll('.log-entry')).map(entry => ({
            timestamp: entry.querySelector('.log-timestamp').textContent,
            details: entry.querySelector('.log-details').textContent
        }));
        
        let content, filename, type;
        
        if (format === 'csv') {
            content = 'Timestamp,Details\n' + 
                      logs.map(log => `"${log.timestamp}","${log.details}"`).join('\n');
            filename = 'logs.csv';
            type = 'text/csv';
        } else {
            content = JSON.stringify(logs, null, 2);
            filename = 'logs.json';
            type = 'application/json';
        }
        
        const blob = new Blob([content], { type });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html> 
