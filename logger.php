<?php
require_once('config.php');
require_once('includes/utilities.php');


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


$filename = isset($_GET['f']) ? basename($_GET['f']) : '';
$filepath = $config['upload_dir'] . $filename;

if ($filename && file_exists($filepath)) {
    // Log image access with IP address
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    
    // Only log image types (not videos)
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        // Check if this is a bot/crawler request (like Discord, search engines, etc.)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isBot = false;
        
        // Skip logging if no user agent (likely a bot)
        if (empty($userAgent)) {
            $isBot = true;
        } else {
            // List of specific bot user agents to exclude from logging
            // These are checked in order - more specific patterns first
            $botPatterns = [
                'discordbot',           // Discord bot (most common for this use case)
                'facebookexternalhit',   // Facebook link preview
                'twitterbot',            // Twitter bot
                'linkedinbot',           // LinkedIn bot
                'whatsapp',              // WhatsApp link preview
                'telegrambot',           // Telegram bot
                'slackbot',              // Slack bot
                'googlebot',             // Google crawler
                'bingbot',               // Bing crawler
                'yandexbot',             // Yandex crawler
                'baiduspider',           // Baidu crawler
                'crawler',               // Generic crawler
                'spider',                // Generic spider
                'scraper'                // Generic scraper
            ];
            
            // Check if user agent matches any bot pattern
            $userAgentLower = strtolower($userAgent);
            foreach ($botPatterns as $pattern) {
                if (strpos($userAgentLower, $pattern) !== false) {
                    $isBot = true;
                    break;
                }
            }
        }
        
        // Only log if it's not a bot (real user browser access)
        if (!$isBot) {
            // Log the access - this happens before any output
            try {
                logImageAccess($filename);
                // Debug: log that we attempted to log (only in debug mode)
                if ($config['debug']) {
                    error_log("Image access logged: " . $filename . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                }
            } catch (Exception $e) {
                // Log the error for debugging
                error_log("Failed to log image access for " . $filename . ": " . $e->getMessage());
            }
        } else {
            // Debug: log when bot is detected (only in debug mode)
            if ($config['debug']) {
                error_log("Bot detected, skipping log: " . $filename . " - UA: " . ($userAgent ?: 'empty'));
            }
        }
    }
    
   
    while (ob_get_level()) {
        ob_end_clean();
    }
    

    switch($extension) {
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'gif':
            header('Content-Type: image/gif');
            break;
        case 'mp4':
            header('Content-Type: video/mp4');
            break;
        case 'webm':
            header('Content-Type: video/webm');
            break;
        default:
            header("HTTP/1.0 415 Unsupported Media Type");
            include(__DIR__ . '/templates/error.php');
            showError(404, 'File Not Found', 'This file has more commitment issues than your ex. At least it left a 404 note! 💌');
            exit;
    }
    
 
    header('Content-Length: ' . filesize($filepath));
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT');
    

    readfile($filepath);
    exit;
} else {

    include(__DIR__ . '/templates/error.php');
    showError(404, 'File Not Found', 'This file has more commitment issues than your ex. At least it left a 404 note! 💌');
    exit;
}
