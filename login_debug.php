<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');

// Start a secure session
initSecureSession();

// Define a function to check if a password matches the hash
function testPasswordVerification($password, $storedHash) {
    $result = password_verify($password, $storedHash);
    return [
        'password' => $password,
        'storedHash' => $storedHash,
        'passwordMatches' => $result,
        'hashInfo' => password_get_info($storedHash)
    ];
}

// Test the default password against the hash in config
$defaultTest = testPasswordVerification('password', $config['password']);

// Check if the form was submitted
$formSubmitted = false;
$customTest = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_password'])) {
    $formSubmitted = true;
    $customTest = testPasswordVerification($_POST['custom_password'], $config['password']);
}

// Get session information
$sessionInfo = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_name' => session_name(),
    'session_cookie_params' => session_get_cookie_params(),
    'session_variables' => $_SESSION ?? []
];

// Get PHP environment information
$phpInfo = [
    'php_version' => phpversion(),
    'password_hash_options' => [
        'PASSWORD_DEFAULT' => defined('PASSWORD_DEFAULT'),
        'PASSWORD_BCRYPT' => defined('PASSWORD_BCRYPT'),
        'PASSWORD_ARGON2I' => defined('PASSWORD_ARGON2I'),
        'PASSWORD_ARGON2ID' => defined('PASSWORD_ARGON2ID')
    ],
    'password_hash_available' => function_exists('password_hash'),
    'password_verify_available' => function_exists('password_verify')
];

// Check server configuration
$serverInfo = [
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
    'http_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not set'
];

// Validate IP whitelist
$ipWhitelistCheck = false;
$userIP = $_SERVER['REMOTE_ADDR'];
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $userIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
$ipWhitelistCheck = in_array($userIP, $config['admin_ips']);

// Test the hash generator
$testPassword = "test123";
$testHash = password_hash($testPassword, PASSWORD_DEFAULT);
$testVerify = password_verify($testPassword, $testHash);

// Create a test hash for troubleshooting
$adminPassword = "password"; // Default from config
$newHash = password_hash($adminPassword, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Debugging</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }
        h1, h2, h3 {
            color: #2563eb;
        }
        section {
            background-color: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .code {
            background-color: #f0f0f0;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f0f7ff;
        }
        .success {
            color: #10803d;
            font-weight: bold;
        }
        .error {
            color: #e11d48;
            font-weight: bold;
        }
        .warning {
            color: #f59e0b;
            font-weight: bold;
        }
        form {
            margin: 20px 0;
            padding: 20px;
            background-color: #f0f7ff;
            border-radius: 8px;
        }
        input, button {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #2563eb;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <h1>Login Debugging</h1>
    <p>This page helps diagnose login issues with Serenity Share.</p>
    
    <section>
        <h2>Default Password Test</h2>
        <p>Testing if the default password ("password") matches the hash in config.php:</p>
        <div class="code">
Password: <?php echo htmlspecialchars($defaultTest['password']); ?>
Stored Hash: <?php echo htmlspecialchars($defaultTest['storedHash']); ?>
Password Matches: <?php echo $defaultTest['passwordMatches'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?>
Hash Info: <?php echo print_r($defaultTest['hashInfo'], true); ?>
        </div>
        
        <?php if (!$defaultTest['passwordMatches']): ?>
        <div class="warning">
            <p><strong>Warning:</strong> The default password ("password") does not match the hash in config.php.</p>
            <p>If you've changed your password, this is expected. Otherwise, it could indicate a problem with your configuration.</p>
        </div>
        <?php endif; ?>
    </section>
    
    <section>
        <h2>Custom Password Test</h2>
        <p>Test a custom password against the hash in config.php:</p>
        <form method="post">
            <input type="text" name="custom_password" placeholder="Enter password to test" required>
            <button type="submit">Test Password</button>
        </form>
        
        <?php if ($formSubmitted): ?>
        <div class="code">
Password: <?php echo htmlspecialchars($customTest['password']); ?>
Stored Hash: <?php echo htmlspecialchars($customTest['storedHash']); ?>
Password Matches: <?php echo $customTest['passwordMatches'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?>
Hash Info: <?php echo print_r($customTest['hashInfo'], true); ?>
        </div>
        <?php endif; ?>
    </section>
    
    <section>
        <h2>New Password Hash Generator</h2>
        <p>If you need to update your password, here's a new hash for the default password ("password"):</p>
        <div class="code"><?php echo htmlspecialchars($newHash); ?></div>
        <p>Copy this hash and replace the value of 'password' in your config.php file.</p>
    </section>
    
    <section>
        <h2>Session Information</h2>
        <table>
            <tr><th>Parameter</th><th>Value</th></tr>
            <tr><td>Session Status</td><td><?php 
                switch($sessionInfo['session_status']) {
                    case PHP_SESSION_DISABLED: echo '<span class="error">Sessions are disabled</span>'; break;
                    case PHP_SESSION_NONE: echo '<span class="warning">Session has not started</span>'; break;
                    case PHP_SESSION_ACTIVE: echo '<span class="success">Session is active</span>'; break;
                    default: echo 'Unknown';
                }
            ?></td></tr>
            <tr><td>Session ID</td><td><?php echo htmlspecialchars($sessionInfo['session_id']); ?></td></tr>
            <tr><td>Session Name</td><td><?php echo htmlspecialchars($sessionInfo['session_name']); ?></td></tr>
            <tr><td>Session Cookie Parameters</td><td><pre><?php print_r($sessionInfo['session_cookie_params']); ?></pre></td></tr>
        </table>
        
        <h3>Session Variables</h3>
        <div class="code"><?php print_r($sessionInfo['session_variables']); ?></div>
    </section>
    
    <section>
        <h2>Server Information</h2>
        <table>
            <tr><th>Parameter</th><th>Value</th></tr>
            <?php foreach($serverInfo as $key => $value): ?>
            <tr><td><?php echo htmlspecialchars($key); ?></td><td><?php echo htmlspecialchars($value); ?></td></tr>
            <?php endforeach; ?>
        </table>
    </section>
    
    <section>
        <h2>IP Whitelist Check</h2>
        <p>Your IP (<?php echo htmlspecialchars($userIP); ?>) is <?php echo $ipWhitelistCheck ? '<span class="success">whitelisted</span>' : '<span class="error">not whitelisted</span>'; ?> in config.php.</p>
        <?php if (!$ipWhitelistCheck): ?>
        <div class="warning">
            <p>Add your IP to the 'admin_ips' array in config.php:</p>
            <div class="code">'admin_ips' => array(
    '127.0.0.1',
    '<?php echo htmlspecialchars($userIP); ?>'
    // Add more IPs as needed
),</div>
        </div>
        <?php endif; ?>
    </section>
    
    <section>
        <h2>PHP Environment</h2>
        <table>
            <tr><th>Parameter</th><th>Value</th></tr>
            <tr><td>PHP Version</td><td><?php echo htmlspecialchars($phpInfo['php_version']); ?></td></tr>
            <tr><td>password_hash() Available</td><td><?php echo $phpInfo['password_hash_available'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td></tr>
            <tr><td>password_verify() Available</td><td><?php echo $phpInfo['password_verify_available'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td></tr>
        </table>
        
        <h3>Available Password Algorithms</h3>
        <table>
            <tr><th>Algorithm</th><th>Available</th></tr>
            <?php foreach($phpInfo['password_hash_options'] as $algo => $available): ?>
            <tr><td><?php echo htmlspecialchars($algo); ?></td><td><?php echo $available ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td></tr>
            <?php endforeach; ?>
        </table>
    </section>
    
    <section>
        <h2>Password Hashing Test</h2>
        <p>Testing if password hashing and verification works correctly:</p>
        <div class="code">
Test Password: <?php echo htmlspecialchars($testPassword); ?>
Generated Hash: <?php echo htmlspecialchars($testHash); ?>
Verification: <?php echo $testVerify ? '<span class="success">Success</span>' : '<span class="error">Failed</span>'; ?>
        </div>
        <?php if (!$testVerify): ?>
        <div class="error">
            <p><strong>Error:</strong> Password hashing and verification is not working correctly. This could indicate an issue with your PHP configuration.</p>
        </div>
        <?php endif; ?>
    </section>
    
    <section>
        <h2>Next Steps</h2>
        <ol>
            <li>Check if your IP is properly whitelisted in config.php</li>
            <li>Verify that the password hash in config.php is correct</li>
            <li>If needed, update the password hash using the generated one above</li>
            <li>Ensure sessions are properly configured and working</li>
            <li>Check for any PHP errors or warnings in your server logs</li>
        </ol>
        
        <p><a href="admin.php">Return to Admin Login</a></p>
    </section>
</body>
</html> 