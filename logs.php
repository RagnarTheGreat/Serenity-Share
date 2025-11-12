<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');
require_once('templates/error.php');

// Initialize secure session
initSecureSession();

// Load language system
require_once('includes/language.php');
reloadTranslations();

// Check if user is logged in
if (!validateSession()) {
    showError(403, 'Access Denied', 'Please log in to access the logs.');
    exit;
}

// Check IP whitelist if enabled in config
if ($config['ip_whitelist_enabled']) {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if (!in_array($ip, $config['admin_ips'])) {
        showError(403, 'Access Denied', 'Your IP is not whitelisted.');
        exit;
    }
}

// Handle log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid security token';
        header('Location: logs.php');
        exit;
    }

    $logFile = __DIR__ . '/logs/image_access.json';
    
    if ($_POST['action'] === 'clear_all') {
        // Clear all logs
        file_put_contents($logFile, json_encode([]));
        $_SESSION['message'] = 'All logs cleared successfully';
        header('Location: logs.php');
        exit;
    } elseif ($_POST['action'] === 'delete_selected' && isset($_POST['selected_logs'])) {
        // Delete selected log entries
        $selectedIndices = json_decode($_POST['selected_logs'] ?? '[]', true);
        
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $logs = json_decode($content, true) ?: [];
            
            // Remove selected entries (indices are in reverse order since we display newest first)
            foreach ($selectedIndices as $index) {
                if (isset($logs[$index])) {
                    unset($logs[$index]);
                }
            }
            
            // Re-index array
            $logs = array_values($logs);
            
            file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
            $_SESSION['message'] = 'Selected logs deleted successfully';
        }
        
        header('Location: logs.php');
        exit;
    }
}

// Load logs
$logFile = __DIR__ . '/logs/image_access.json';
$logs = [];

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if ($content) {
        $logs = json_decode($content, true) ?: [];
    }
}

// Pagination
$itemsPerPage = 50;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$totalLogs = count($logs);
$totalPages = ceil($totalLogs / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;
$paginatedLogs = array_slice($logs, $offset, $itemsPerPage);

// Filtering
$filterIP = $_GET['ip'] ?? '';
$filterFilename = $_GET['filename'] ?? '';

if ($filterIP || $filterFilename) {
    $filteredLogs = [];
    foreach ($logs as $log) {
        $matchIP = empty($filterIP) || stripos($log['ip'], $filterIP) !== false;
        $matchFilename = empty($filterFilename) || stripos($log['filename'], $filterFilename) !== false;
        
        if ($matchIP && $matchFilename) {
            $filteredLogs[] = $log;
        }
    }
    $totalLogs = count($filteredLogs);
    $totalPages = ceil($totalLogs / $itemsPerPage);
    $paginatedLogs = array_slice($filteredLogs, $offset, $itemsPerPage);
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to parse user agent
function parseUserAgent($userAgent) {
    $info = [
        'browser' => 'Unknown',
        'browser_version' => '',
        'os' => 'Unknown',
        'device' => 'Desktop',
        'device_icon' => '💻'
    ];
    
    $ua = strtolower($userAgent);
    
    // Detect browser
    if (strpos($ua, 'chrome') !== false && strpos($ua, 'edg') === false) {
        $info['browser'] = 'Chrome';
        if (preg_match('/chrome\/([\d\.]+)/', $ua, $matches)) {
            $info['browser_version'] = $matches[1];
        }
    } elseif (strpos($ua, 'firefox') !== false) {
        $info['browser'] = 'Firefox';
        if (preg_match('/firefox\/([\d\.]+)/', $ua, $matches)) {
            $info['browser_version'] = $matches[1];
        }
    } elseif (strpos($ua, 'safari') !== false && strpos($ua, 'chrome') === false) {
        $info['browser'] = 'Safari';
        if (preg_match('/version\/([\d\.]+)/', $ua, $matches)) {
            $info['browser_version'] = $matches[1];
        }
    } elseif (strpos($ua, 'edg') !== false) {
        $info['browser'] = 'Edge';
        if (preg_match('/edg\/([\d\.]+)/', $ua, $matches)) {
            $info['browser_version'] = $matches[1];
        }
    } elseif (strpos($ua, 'opera') !== false || strpos($ua, 'opr') !== false) {
        $info['browser'] = 'Opera';
    }
    
    // Detect OS
    if (strpos($ua, 'windows') !== false) {
        $info['os'] = 'Windows';
        if (strpos($ua, 'windows nt 10') !== false) $info['os'] = 'Windows 10/11';
        elseif (strpos($ua, 'windows nt 6.3') !== false) $info['os'] = 'Windows 8.1';
        elseif (strpos($ua, 'windows nt 6.2') !== false) $info['os'] = 'Windows 8';
        elseif (strpos($ua, 'windows nt 6.1') !== false) $info['os'] = 'Windows 7';
    } elseif (strpos($ua, 'mac os x') !== false || strpos($ua, 'macintosh') !== false) {
        $info['os'] = 'macOS';
    } elseif (strpos($ua, 'linux') !== false) {
        $info['os'] = 'Linux';
    } elseif (strpos($ua, 'android') !== false) {
        $info['os'] = 'Android';
    } elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) {
        $info['os'] = 'iOS';
    }
    
    // Detect device type
    if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
        $info['device'] = 'Mobile';
        $info['device_icon'] = '📱';
    } elseif (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
        $info['device'] = 'Tablet';
        $info['device_icon'] = '📱';
    }
    
    return $info;
}

// Helper function to get relative time
function getRelativeTime($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hour' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' week' . (floor($diff / 604800) > 1 ? 's' : '') . ' ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' month' . (floor($diff / 2592000) > 1 ? 's' : '') . ' ago';
    return floor($diff / 31536000) . ' year' . (floor($diff / 31536000) > 1 ? 's' : '') . ' ago';
}

// Calculate statistics
$uniqueIPs = [];
$uniqueFiles = [];
$todayCount = 0;
$todayStart = strtotime('today');

foreach ($logs as $log) {
    if (!in_array($log['ip'], $uniqueIPs)) {
        $uniqueIPs[] = $log['ip'];
    }
    if (!in_array($log['filename'], $uniqueFiles)) {
        $uniqueFiles[] = $log['filename'];
    }
    if (isset($log['timestamp']) && $log['timestamp'] >= $todayStart) {
        $todayCount++;
    }
}

$stats = [
    'total_views' => $totalLogs,
    'unique_ips' => count($uniqueIPs),
    'unique_files' => count($uniqueFiles),
    'today_views' => $todayCount
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('logs.title', 'View Logs'); ?> - <?php echo t('admin.dashboard.title', 'Admin Dashboard'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <style>
        .logs-container {
            background: var(--card-bg, #1e1e1e);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border-color, #2e2e2e);
            transition: all 0.3s ease;
        }
        
        .logs-container:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2), 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color, #2e2e2e);
        }
        
        .logs-header h2 {
            margin: 0;
            color: var(--text-color, #e2e8f0);
            font-size: 1.8em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logs-header h2::before {
            content: "📊";
            font-size: 1.2em;
        }
        
        .logs-stats {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--bg-darker, #0a0a0a);
            padding: 12px 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color, #2e2e2e);
        }
        
        .logs-stats span {
            color: var(--text-color, #e2e8f0);
            font-weight: 500;
            font-size: 0.95em;
        }
        
        .logs-stats .stat-number {
            color: var(--primary-color, #4f46e5);
            font-weight: 700;
            font-size: 1.1em;
        }
        
        .logs-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--bg-darker, #0a0a0a);
            border-radius: 10px;
            border: 1px solid var(--border-color, #2e2e2e);
        }
        
        .logs-filters input {
            flex: 1;
            min-width: 200px;
            padding: 12px 16px;
            background: var(--bg-color, #111111);
            border: 1px solid var(--border-color, #2e2e2e);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-color, #e2e8f0);
            transition: all 0.3s ease;
        }
        
        .logs-filters input:focus {
            outline: none;
            border-color: var(--primary-color, #4f46e5);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .logs-filters input::placeholder {
            color: var(--text-light, #94a3b8);
        }
        
        .logs-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .logs-table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid var(--border-color, #2e2e2e);
            background: var(--bg-darker, #0a0a0a);
            overflow-y: hidden;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }
        
        .logs-table th,
        .logs-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color, #2e2e2e);
        }
        
        .logs-table thead {
            background: var(--bg-darker, #0a0a0a);
        }
        
        .logs-table th {
            background: var(--bg-darker, #0a0a0a);
            font-weight: 600;
            color: var(--text-color, #e2e8f0);
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--primary-color, #4f46e5);
        }
        
        .logs-table tbody tr {
            background: var(--card-bg, #1e1e1e);
            transition: all 0.2s ease;
            position: relative;
        }
        
        .logs-table tbody tr:nth-child(even) {
            background: var(--bg-darker, #0a0a0a);
        }
        
        .logs-table tbody tr:hover {
            background: var(--primary-color, #4f46e5);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.2);
        }
        
        .logs-table tbody tr:hover td {
            color: #fff;
        }
        
        .logs-table td {
            color: var(--text-color, #e2e8f0);
            font-size: 0.9em;
        }
        
        .log-ip {
            font-family: 'Courier New', monospace;
            color: var(--primary-color, #4f46e5);
            font-weight: 600;
            background: rgba(79, 70, 229, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .logs-table tbody tr:hover .log-ip {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        
        .log-filename {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 500;
            color: var(--text-color, #e2e8f0);
        }
        
        .log-user-agent {
            max-width: 350px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.85em;
            color: var(--text-light, #94a3b8);
        }
        
        .log-referer {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.85em;
            color: var(--text-light, #94a3b8);
        }
        
        .log-datetime {
            font-family: 'Courier New', monospace;
            color: var(--text-color, #e2e8f0);
            font-size: 0.9em;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 16px;
            border: 1px solid var(--border-color, #2e2e2e);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-color, #e2e8f0);
            background: var(--card-bg, #1e1e1e);
            transition: all 0.3s ease;
            font-weight: 500;
            min-width: 44px;
            text-align: center;
        }
        
        .pagination a:hover {
            background: var(--primary-color, #4f46e5);
            color: #fff;
            border-color: var(--primary-color, #4f46e5);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }
        
        .pagination .current {
            background: var(--primary-color, #4f46e5);
            color: white;
            border-color: var(--primary-color, #4f46e5);
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }
        
        .checkbox-column {
            width: 40px;
            text-align: center;
        }
        
        .checkbox-column input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color, #4f46e5);
        }
        
        .no-logs {
            text-align: center;
            padding: 60px 40px;
            color: var(--text-light, #94a3b8);
            background: var(--bg-darker, #0a0a0a);
            border-radius: 10px;
            border: 1px solid var(--border-color, #2e2e2e);
        }
        
        .no-logs::before {
            content: "📭";
            font-size: 3em;
            display: block;
            margin-bottom: 15px;
        }
        
        .no-logs p {
            margin: 0;
            font-size: 1.1em;
        }
        
        .stats-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: var(--bg-darker, #0a0a0a);
            border: 1px solid var(--border-color, #2e2e2e);
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--primary-color, #4f46e5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        .stat-icon {
            font-size: 2em;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--primary-color, #4f46e5);
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.85em;
            color: var(--text-light, #94a3b8);
            margin-top: 5px;
        }
        
        .log-relative-time {
            font-size: 0.75em;
            color: var(--text-light, #94a3b8);
            margin-top: 4px;
        }
        
        .copy-btn, .action-btn {
            background: transparent;
            border: 1px solid var(--border-color, #2e2e2e);
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 0.9em;
            color: var(--text-color, #e2e8f0);
            transition: all 0.2s ease;
            margin-left: 8px;
            display: inline-block;
        }
        
        .copy-btn:hover, .action-btn:hover {
            background: var(--primary-color, #4f46e5);
            border-color: var(--primary-color, #4f46e5);
            transform: scale(1.1);
        }
        
        .filename-link {
            color: var(--primary-color, #4f46e5);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .filename-link:hover {
            color: var(--primary-dark, #4338ca);
            text-decoration: underline;
        }
        
        .log-browser-info {
            font-size: 0.85em;
        }
        
        .browser-name {
            color: var(--text-color, #e2e8f0);
            font-weight: 500;
        }
        
        .os-name {
            color: var(--text-light, #94a3b8);
            font-size: 0.9em;
            margin-top: 2px;
        }
        
        .device-badge {
            background: rgba(79, 70, 229, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            color: var(--text-color, #e2e8f0);
            display: inline-block;
        }
        
        .direct-badge {
            background: rgba(107, 114, 128, 0.2);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            color: var(--text-light, #94a3b8);
            display: inline-block;
        }
        
        .referer-link {
            color: var(--primary-color, #4f46e5);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .referer-link:hover {
            color: var(--primary-dark, #4338ca);
            text-decoration: underline;
        }
        
        .log-actions {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .action-btn {
            margin: 0;
            text-decoration: none;
        }
        
        .button {
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 500;
            padding: 12px 24px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .button-danger {
            background: #dc3545;
        }
        
        .button-danger:hover {
            background: #c82333;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }
        
        @media (max-width: 768px) {
            .logs-container {
                padding: 20px;
            }
            
            .logs-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .logs-filters {
                flex-direction: column;
            }
            
            .logs-filters input {
                width: 100%;
                min-width: unset;
            }
            
            .logs-table-wrapper {
                overflow-x: scroll;
            }
            
            .logs-table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo t('logs.title', 'View Logs'); ?></h1>
            <div class="header-actions">
                <a href="admin.php" class="button button-primary"><?php echo t('admin.dashboard.title', 'Admin Dashboard'); ?></a>
                <a href="?logout=1" class="button button-danger"><?php echo t('admin.buttons.logout', 'Logout'); ?></a>
            </div>
        </div>

        <div class="logs-container">
            <div class="logs-header">
                <h2><?php echo t('logs.image_access_logs', 'Image Access Logs'); ?></h2>
                <div class="logs-stats">
                    <span><?php echo t('logs.total_entries', 'Total'); ?>: <span class="stat-number"><?php echo number_format($totalLogs); ?></span> entries</span>
                </div>
            </div>

            <div class="stats-panel">
                <div class="stat-card">
                    <div class="stat-icon">👁️</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_views']); ?></div>
                        <div class="stat-label">Total Views</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🌐</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['unique_ips']); ?></div>
                        <div class="stat-label">Unique IPs</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🖼️</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['unique_files']); ?></div>
                        <div class="stat-label">Unique Images</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['today_views']); ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                </div>
            </div>

            <div class="logs-filters">
                <input type="text" id="filter-ip" placeholder="<?php echo t('logs.filter_by_ip', 'Filter by IP address'); ?>" value="<?php echo htmlspecialchars($filterIP); ?>">
                <input type="text" id="filter-filename" placeholder="<?php echo t('logs.filter_by_filename', 'Filter by filename'); ?>" value="<?php echo htmlspecialchars($filterFilename); ?>">
                <button class="button button-primary" onclick="applyFilters()"><?php echo t('logs.apply_filters', 'Apply Filters'); ?></button>
                <?php if ($filterIP || $filterFilename): ?>
                    <a href="logs.php" class="button"><?php echo t('logs.clear_filters', 'Clear Filters'); ?></a>
                <?php endif; ?>
            </div>

            <div class="logs-actions">
                <button class="button button-primary" onclick="exportLogs('csv')">📥 Export CSV</button>
                <button class="button button-primary" onclick="exportLogs('json')">📥 Export JSON</button>
                <button class="button button-danger" onclick="clearAllLogs()"><?php echo t('logs.clear_all_logs', 'Clear All Logs'); ?></button>
                <button class="button button-danger" id="delete-selected-btn" onclick="deleteSelected()" disabled><?php echo t('logs.delete_selected', 'Delete Selected'); ?></button>
            </div>

            <?php if (empty($paginatedLogs)): ?>
                <div class="no-logs">
                    <p><?php echo t('logs.no_logs', 'No logs found.'); ?></p>
                </div>
            <?php else: ?>
                <form id="delete-form" method="POST" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="delete_selected">
                    <input type="hidden" name="selected_logs" id="selected-logs-input">
                </form>

                <div class="logs-table-wrapper">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <input type="checkbox" id="select-all" onchange="toggleAll(this)">
                                </th>
                                <th><?php echo t('logs.date_time', 'Date & Time'); ?></th>
                                <th><?php echo t('logs.ip_address', 'IP Address'); ?></th>
                                <th><?php echo t('logs.filename', 'Filename'); ?></th>
                                <th>Browser / OS</th>
                                <th>Device</th>
                                <th><?php echo t('logs.referer', 'Referer'); ?></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginatedLogs as $index => $log): 
                                $uaInfo = parseUserAgent($log['user_agent'] ?? '');
                                $timestamp = $log['timestamp'] ?? strtotime($log['datetime'] ?? 'now');
                                $relativeTime = getRelativeTime($timestamp);
                                $imageUrl = $config['domain_url'] . 'img/' . $log['filename'];
                            ?>
                                <tr>
                                    <td class="checkbox-column">
                                        <input type="checkbox" class="log-checkbox" value="<?php echo $offset + $index; ?>" onchange="updateDeleteButton()">
                                    </td>
                                    <td class="log-datetime">
                                        <div><?php echo htmlspecialchars($log['datetime'] ?? date('Y-m-d H:i:s', $timestamp)); ?></div>
                                        <div class="log-relative-time"><?php echo $relativeTime; ?></div>
                                    </td>
                                    <td>
                                        <span class="log-ip"><?php echo htmlspecialchars($log['ip']); ?></span>
                                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($log['ip']); ?>', this)" title="Copy IP">📋</button>
                                    </td>
                                    <td class="log-filename" title="<?php echo htmlspecialchars($log['filename']); ?>">
                                        <a href="<?php echo htmlspecialchars($imageUrl); ?>" target="_blank" class="filename-link">
                                            <?php echo htmlspecialchars($log['filename']); ?>
                                        </a>
                                    </td>
                                    <td class="log-browser-info">
                                        <div class="browser-name"><?php echo htmlspecialchars($uaInfo['browser']); ?><?php echo $uaInfo['browser_version'] ? ' ' . htmlspecialchars($uaInfo['browser_version']) : ''; ?></div>
                                        <div class="os-name"><?php echo htmlspecialchars($uaInfo['os']); ?></div>
                                    </td>
                                    <td class="log-device">
                                        <span class="device-badge">
                                            <?php echo $uaInfo['device_icon']; ?> <?php echo htmlspecialchars($uaInfo['device']); ?>
                                        </span>
                                    </td>
                                    <td class="log-referer" title="<?php echo htmlspecialchars($log['referer']); ?>">
                                        <?php if ($log['referer'] !== 'Direct'): ?>
                                            <a href="<?php echo htmlspecialchars($log['referer']); ?>" target="_blank" class="referer-link" title="<?php echo htmlspecialchars($log['referer']); ?>">
                                                <?php echo htmlspecialchars(strlen($log['referer']) > 30 ? substr($log['referer'], 0, 30) . '...' : $log['referer']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="direct-badge">Direct</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="log-actions">
                                        <button class="action-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($imageUrl); ?>', this)" title="Copy Image Link">🔗</button>
                                        <a href="<?php echo htmlspecialchars($imageUrl); ?>" target="_blank" class="action-btn" title="View Image">👁️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?><?php echo $filterIP ? '&ip=' . urlencode($filterIP) : ''; ?><?php echo $filterFilename ? '&filename=' . urlencode($filterFilename) : ''; ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $filterIP ? '&ip=' . urlencode($filterIP) : ''; ?><?php echo $filterFilename ? '&filename=' . urlencode($filterFilename) : ''; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?><?php echo $filterIP ? '&ip=' . urlencode($filterIP) : ''; ?><?php echo $filterFilename ? '&filename=' . urlencode($filterFilename) : ''; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
        function showToast(message, type = "info", duration = 3000) {
            const container = document.getElementById("toast-container");
            if (!container) return;
            
            const toast = document.createElement("div");
            toast.className = `toast ${type}`;
            toast.style.animation = "slideIn 0.3s ease-out";
            
            let icon = "🔔";
            switch (type) {
                case "success": icon = "✅"; break;
                case "error": icon = "❌"; break;
                case "warning": icon = "⚠️"; break;
            }
            
            toast.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-message">${message}</span>`;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = "slideOut 0.3s ease-out forwards";
                setTimeout(() => {
                    if (container.contains(toast)) {
                        container.removeChild(toast);
                    }
                }, 300);
            }, duration);
        }

        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.log-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateDeleteButton();
        }

        function updateDeleteButton() {
            const checked = document.querySelectorAll('.log-checkbox:checked');
            const btn = document.getElementById('delete-selected-btn');
            if (btn) {
                btn.disabled = checked.length === 0;
            }
        }

        function deleteSelected() {
            const checked = document.querySelectorAll('.log-checkbox:checked');
            if (checked.length === 0) return;
            
            if (!confirm(`Are you sure you want to delete ${checked.length} log entry/entries?`)) {
                return;
            }
            
            const indices = Array.from(checked).map(cb => cb.value);
            document.getElementById('selected-logs-input').value = JSON.stringify(indices);
            document.getElementById('delete-form').submit();
        }

        function clearAllLogs() {
            if (!confirm('<?php echo t('logs.clear_confirm', 'Are you sure you want to clear all logs? This action cannot be undone.'); ?>')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="clear_all">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function applyFilters() {
            const ip = document.getElementById('filter-ip').value;
            const filename = document.getElementById('filter-filename').value;
            const params = new URLSearchParams();
            if (ip) params.append('ip', ip);
            if (filename) params.append('filename', filename);
            window.location.href = 'logs.php?' + params.toString();
        }

        // Allow Enter key to apply filters
        document.getElementById('filter-ip').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
        document.getElementById('filter-filename').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });

        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(function() {
                const originalText = button.innerHTML;
                button.innerHTML = '✅';
                button.style.background = '#28a745';
                showToast('Copied to clipboard!', 'success', 2000);
                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.style.background = '';
                }, 2000);
            }).catch(function(err) {
                showToast('Failed to copy', 'error');
            });
        }

        function exportLogs(format) {
            const logs = <?php echo json_encode($logs); ?>;
            
            if (logs.length === 0) {
                showToast('No logs to export', 'warning');
                return;
            }
            
            let content, filename, mimeType;
            
            if (format === 'csv') {
                // CSV header
                let csv = 'Date & Time,IP Address,Filename,Browser,OS,Device,Referer,User Agent\n';
                
                logs.forEach(log => {
                    const uaInfo = parseUserAgentJS(log.user_agent || '');
                    const row = [
                        log.datetime || new Date(log.timestamp * 1000).toISOString(),
                        log.ip,
                        log.filename,
                        uaInfo.browser + (uaInfo.browser_version ? ' ' + uaInfo.browser_version : ''),
                        uaInfo.os,
                        uaInfo.device,
                        log.referer,
                        log.user_agent
                    ].map(field => '"' + String(field).replace(/"/g, '""') + '"').join(',');
                    csv += row + '\n';
                });
                
                content = csv;
                filename = 'image_access_logs_' + new Date().toISOString().split('T')[0] + '.csv';
                mimeType = 'text/csv';
            } else {
                content = JSON.stringify(logs, null, 2);
                filename = 'image_access_logs_' + new Date().toISOString().split('T')[0] + '.json';
                mimeType = 'application/json';
            }
            
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
            URL.revokeObjectURL(url);
            
            showToast('Export started', 'success');
        }

        function parseUserAgentJS(userAgent) {
            const info = {
                browser: 'Unknown',
                browser_version: '',
                os: 'Unknown',
                device: 'Desktop'
            };
            
            const ua = userAgent.toLowerCase();
            
            if (ua.indexOf('chrome') !== -1 && ua.indexOf('edg') === -1) {
                info.browser = 'Chrome';
                const match = ua.match(/chrome\/([\d\.]+)/);
                if (match) info.browser_version = match[1];
            } else if (ua.indexOf('firefox') !== -1) {
                info.browser = 'Firefox';
                const match = ua.match(/firefox\/([\d\.]+)/);
                if (match) info.browser_version = match[1];
            } else if (ua.indexOf('safari') !== -1 && ua.indexOf('chrome') === -1) {
                info.browser = 'Safari';
            } else if (ua.indexOf('edg') !== -1) {
                info.browser = 'Edge';
            }
            
            if (ua.indexOf('windows') !== -1) {
                info.os = 'Windows';
            } else if (ua.indexOf('mac') !== -1) {
                info.os = 'macOS';
            } else if (ua.indexOf('linux') !== -1) {
                info.os = 'Linux';
            } else if (ua.indexOf('android') !== -1) {
                info.os = 'Android';
            } else if (ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1) {
                info.os = 'iOS';
            }
            
            if (ua.indexOf('mobile') !== -1 || ua.indexOf('android') !== -1 || ua.indexOf('iphone') !== -1) {
                info.device = 'Mobile';
            } else if (ua.indexOf('tablet') !== -1 || ua.indexOf('ipad') !== -1) {
                info.device = 'Tablet';
            }
            
            return info;
        }
    </script>
</body>
</html>

