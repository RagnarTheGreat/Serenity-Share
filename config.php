<?php

$config = array(
    'domain_url' => 'https://YOUR.DOMAIN.com',
    'upload_dir' => __DIR__ . '/img/',
    'share_dir' => __DIR__ . '/shares/',
'secret_key' => 'RandomSecretKey-MAKE_SURE_IT_MATCHES_SHARX_CONFIG', // make sure this matches the key in sharex config
    'max_file_size' => 100 * 1024 * 1024,  // 100MB
    'max_share_size' => 1024 * 1024 * 1024, // 1GB
    'allowed_share_types' => array('*'),  // Allow all file types
    'default_expire_time' => 604800,      // 7 days 
    'max_expire_time' => 2592000,         // 30 days 
    'admin_username' => 'admin',
    // Updated password hash for "password" - generated using PHP's password_hash() function
    'password' => '$2y$10$0DVCI187Z9tRXwCgvJ/6z.jL8jk.t12tWnMXj7QH8FM2rRVR8mOYC', // Default: "password" to change go to gomain/hash_password.php
    'debug' => false, // Set to true for debugging, then set back to false for production
    'admin_ips' => array(
        '127.0.0.1',     // localhost
       '1.1.1.1' // Add your IP address here to access the admin panel
    ),
    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm'], // DO NOT TOUCH THIS
    'gallery_dir' => __DIR__ . '/img/', // DO NOT TOUCH THIS
    'backup_dir' => __DIR__ . '/backups/' // DO NOT TOUCH THIS
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