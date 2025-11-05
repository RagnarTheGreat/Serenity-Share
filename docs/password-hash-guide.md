# Password Hash Generator Guide

## Introduction

The Password Hash Generator is a utility tool included with Serenity Share that allows administrators to create secure password hashes. These hashes can be used for:

- Creating admin account passwords
- Setting up secure share passwords
- Testing password security implementations

This guide explains how to use the Password Hash Generator and best practices for password security.

## Accessing the Password Hash Generator

You can access the Password Hash Generator in two ways:

1. From the admin dashboard, click the "Hash Password" button in the navigation menu
2. Directly navigate to `hash_password.php` in your browser

## Using the Password Hash Generator

### Step 1: Enter a Password

Enter the password you want to hash in the "Password to Hash" field. Use a strong password that includes:

- A minimum of 8 characters
- A mix of uppercase and lowercase letters
- Numbers
- Special characters

### Step 2: Select a Hashing Algorithm

Choose the hashing algorithm you want to use:

- **DEFAULT**: Uses PHP's current default algorithm (currently BCrypt). This is recommended for most users as it will automatically upgrade to newer algorithms as PHP evolves.
- **BCRYPT**: The Blowfish hashing algorithm that produces a 60-character hash.
- **ARGON2I**: Available in PHP 7.2+, designed to be resistant to GPU cracking attacks.
- **ARGON2ID**: Available in PHP 7.3+, a hybrid version of Argon2 with better resistance against both GPU and side-channel attacks.

### Step 3: Generate the Hash

Click the "Generate Hash" button to create your secure password hash. The resulting hash will appear in the result area.

### Step 4: Copy the Hash

Click the "Copy" button to copy the generated hash to your clipboard. This hash can then be used in your database to store passwords securely.

## Implementation Examples

### Storing a Password

```php
// This is done automatically by the Password Hash Generator
$password = "user_input_password";
$hash = password_hash($password, PASSWORD_DEFAULT);

// Store $hash in your database
$sql = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$username, $hash]);
```

### Verifying a Password

```php
// When a user tries to log in
$input_password = $_POST['password']; // User-provided password
$stored_hash = $row['password_hash']; // Hash from the database

if (password_verify($input_password, $stored_hash)) {
    // Password is correct, log the user in
    $_SESSION['logged_in'] = true;
} else {
    // Password is incorrect
    echo "Invalid username or password";
}
```

## Security Best Practices

1. **Never store raw passwords** in your database. Always use the hash.
2. **Use `password_hash()` and `password_verify()`** for password management. Don't create your own hashing functions.
3. **Default Algorithm**: Use PASSWORD_DEFAULT unless you have specific requirements.
4. **Future-proof**: PHP's `password_verify()` will work even if the default algorithm changes in future PHP versions.
5. **Rehashing**: Consider rehashing passwords on login if they were created with an older algorithm:

```php
if (password_verify($password, $hash)) {
    // Check if hash needs to be updated
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        // Update the hash in the database
    }
    // Log the user in
}
```

## Troubleshooting

### Algorithm Not Available

If you select an algorithm that isn't available in your version of PHP, the system will automatically fall back to the default algorithm.

### Long Processing Time

Argon2 algorithms may take longer to generate hashes than BCrypt. This is normal and is part of their security features.

### Hash Length

Different algorithms produce different hash lengths:
- BCrypt: 60 characters
- Argon2i/Argon2id: Typically longer than BCrypt

Ensure your database fields are large enough (VARCHAR(255) is recommended).

## Conclusion

The Password Hash Generator is a valuable tool for creating secure password hashes for your Serenity Share installation. By following these guidelines and using this tool, you can significantly improve the security of your application's password management system. 