<?php
/**
 * Serenity Share - Command Line Password Hash Generator
 * 
 * Usage: php hash_password_cli.php password
 * Example: php hash_password_cli.php mySecurePassword123
 */

// Check if a password was provided as an argument
if ($argc < 2) {
    echo "Error: No password provided.\n";
    echo "Usage: php hash_password_cli.php <password>\n";
    echo "Example: php hash_password_cli.php mySecurePassword123\n";
    exit(1);
}

// Get the password from the command line argument
$password = $argv[1];

// Display a warning if the password is weak
if (strlen($password) < 8) {
    echo "Warning: Password is less than 8 characters long.\n";
}

if (!preg_match('/[A-Z]/', $password)) {
    echo "Warning: Password doesn't contain uppercase letters.\n";
}

if (!preg_match('/[a-z]/', $password)) {
    echo "Warning: Password doesn't contain lowercase letters.\n";
}

if (!preg_match('/[0-9]/', $password)) {
    echo "Warning: Password doesn't contain numbers.\n";
}

if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    echo "Warning: Password doesn't contain special characters.\n";
}

// Generate hashes with different algorithms
echo "\nPassword Hash Generator Results\n";
echo "==============================\n\n";

echo "Password: $password\n\n";

echo "DEFAULT (current: bcrypt):\n";
echo password_hash($password, PASSWORD_DEFAULT) . "\n\n";

echo "BCRYPT:\n";
echo password_hash($password, PASSWORD_BCRYPT) . "\n\n";

if (defined('PASSWORD_ARGON2I')) {
    echo "ARGON2I:\n";
    echo password_hash($password, PASSWORD_ARGON2I) . "\n\n";
} else {
    echo "ARGON2I: Not available in this PHP version\n\n";
}

if (defined('PASSWORD_ARGON2ID')) {
    echo "ARGON2ID:\n";
    echo password_hash($password, PASSWORD_ARGON2ID) . "\n\n";
} else {
    echo "ARGON2ID: Not available in this PHP version\n\n";
}

echo "Config.php Format:\n";
echo "'password' => '" . password_hash($password, PASSWORD_DEFAULT) . "', // Updated: " . date('Y-m-d') . "\n\n";

echo "Usage Instructions:\n";
echo "1. Copy the line above into your config.php file\n";
echo "2. Replace the existing 'password' line with the new one\n";
echo "3. Use the password you just hashed for logging in\n";
?> 