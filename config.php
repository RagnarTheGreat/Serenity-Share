<?php
$config = array(
    'domain_url' => 'https://YOURDOMAIN/', // make sure there is a / at the end of the domain
    'upload_dir' => 'img/',
    'share_dir' => 'shares/',
    'secret_key' => 'SHAREX-SECRET-KEY',
    'max_file_size' => 100 * 1024 * 1024,  // 100MB
    'max_share_size' => 1024 * 1024 * 1024, // 1GB
    'allowed_share_types' => array('*'),  // Allow all file types
    'default_expire_time' => 604800,      // 7 days
    'max_expire_time' => 2592000,         // 30 days
    'admin_username' => 'admin',
    'password' => 'GET YOUR PASSWORD HASH FROM /password_hash.php',
    'debug' => true,
    'ip_whitelist_enabled' => true,  // Set to true to enable IP whitelist
    'admin_ips' => array(
        '127.0.0.1',           // localhost
        'YOUR IP'
    ),
    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm'], // DO NOT TOUCH THIS
    'gallery_dir' => __DIR__ . '/img/', // DO NOT TOUCH THIS
    'backup_dir' => __DIR__ . '/backups/', // DO NOT TOUCH THIS
    'discord_webhook_url' => 'https://discord.com/api/webhooks/', // Add your Discord webhook URL here
    'discord_notifications' => true // Set to true to enable Discord notifications
);

// DO NOT TOUCH THIS CODE UNLESS YOU KNOW WHAT YOU'RE DOING 
$required_dirs = ['upload_dir', 'share_dir'];
foreach ($required_dirs as $dir) {
    if (!file_exists($config[$dir])) {
        mkdir($config[$dir], 0755, true);
    }
    if (!is_writable($config[$dir])) {
        die("Error: {$config[$dir]} directory must be writable");
    }
}
?> 
