<?php
/**
 * QR Code Setup Checker
 * 
 * This script helps verify that the QR code feature is properly set up.
 * Access this file in your browser to check if everything is configured correctly.
 */

// Check if vendor directory exists
$vendorPath = __DIR__ . '/vendor/autoload.php';
$vendorExists = file_exists($vendorPath);

// Check PHP version
$phpVersion = phpversion();
$phpVersionOk = version_compare($phpVersion, '8.1.0', '>=');

// Check GD extension
$gdEnabled = extension_loaded('gd');

// Check if composer.json exists
$composerJsonExists = file_exists(__DIR__ . '/composer.json');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Setup Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .check-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .check-ok {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .check-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .status {
            font-weight: bold;
            font-size: 18px;
        }
        .status-ok {
            color: #28a745;
        }
        .status-error {
            color: #dc3545;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #2196F3;
        }
        .info h3 {
            margin-top: 0;
            color: #1976D2;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 QR Code Setup Check</h1>
        
        <div class="check-item <?php echo $vendorExists ? 'check-ok' : 'check-error'; ?>">
            <div>
                <strong>Vendor Directory</strong><br>
                <small>Required for QR code generation</small>
            </div>
            <span class="status <?php echo $vendorExists ? 'status-ok' : 'status-error'; ?>">
                <?php echo $vendorExists ? '✅ OK' : '❌ MISSING'; ?>
            </span>
        </div>
        
        <div class="check-item <?php echo $phpVersionOk ? 'check-ok' : 'check-error'; ?>">
            <div>
                <strong>PHP Version</strong><br>
                <small>Current: <?php echo $phpVersion; ?> (Requires: 8.1+)</small>
            </div>
            <span class="status <?php echo $phpVersionOk ? 'status-ok' : 'status-error'; ?>">
                <?php echo $phpVersionOk ? '✅ OK' : '❌ TOO OLD'; ?>
            </span>
        </div>
        
        <div class="check-item <?php echo $gdEnabled ? 'check-ok' : 'check-error'; ?>">
            <div>
                <strong>GD Extension</strong><br>
                <small>Required for image generation</small>
            </div>
            <span class="status <?php echo $gdEnabled ? 'status-ok' : 'status-error'; ?>">
                <?php echo $gdEnabled ? '✅ ENABLED' : '❌ MISSING'; ?>
            </span>
        </div>
        
        <div class="check-item <?php echo $composerJsonExists ? 'check-ok' : 'check-error'; ?>">
            <div>
                <strong>Composer Configuration</strong><br>
                <small>composer.json file</small>
            </div>
            <span class="status <?php echo $composerJsonExists ? 'status-ok' : 'status-error'; ?>">
                <?php echo $composerJsonExists ? '✅ EXISTS' : '❌ MISSING'; ?>
            </span>
        </div>
        
        <?php if (!$vendorExists): ?>
        <div class="info">
            <h3>⚠️ Vendor Directory Missing</h3>
            <p><strong>Solution:</strong></p>
            <ol>
                <li><strong>If you downloaded from GitHub:</strong> Make sure you downloaded the entire repository including the <code>vendor</code> folder. The vendor folder should be in the same directory as this file.</li>
                <li><strong>If vendor folder is missing:</strong> You need to install dependencies using Composer:
                    <pre style="background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto;">composer install</pre>
                </li>
                <li><strong>For Ubuntu/Debian:</strong> If Composer is not installed:
                    <pre style="background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto;">curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer install</pre>
                </li>
            </ol>
            <p><strong>Note:</strong> For drag-and-drop installation to work, the <code>vendor</code> directory must be included in the GitHub repository. Make sure it's committed and pushed.</p>
        </div>
        <?php elseif (!$phpVersionOk): ?>
        <div class="info">
            <h3>⚠️ PHP Version Too Old</h3>
            <p>You need PHP 8.1 or higher for QR code generation. Current version: <?php echo $phpVersion; ?></p>
            <p><strong>Solution:</strong> Upgrade PHP to version 8.1 or higher.</p>
        </div>
        <?php elseif (!$gdEnabled): ?>
        <div class="info">
            <h3>⚠️ GD Extension Missing</h3>
            <p>The GD extension is required for generating QR code images.</p>
            <p><strong>Solution for Ubuntu/Debian:</strong></p>
            <pre style="background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto;">sudo apt-get install php-gd
sudo systemctl restart apache2  # or: sudo systemctl restart php8.1-fpm</pre>
        </div>
        <?php else: ?>
        <div class="info" style="background: #d4edda; border-left-color: #28a745;">
            <h3 style="color: #155724;">✅ Everything Looks Good!</h3>
            <p>Your QR code setup is complete. QR codes should work properly.</p>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
            <p><strong>File Location:</strong> <?php echo __FILE__; ?></p>
            <p><strong>Vendor Path Checked:</strong> <?php echo $vendorPath; ?></p>
        </div>
    </div>
</body>
</html>

