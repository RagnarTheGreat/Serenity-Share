<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Serenity Share</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .error-message {
            background-color: #fef2f2;
            border-left: 4px solid #e11d48;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 4px 4px 0;
            color: #e11d48;
        }
        .error-message h3 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
        }
        .error-message ul {
            margin: 0;
            padding-left: 20px;
        }
        .error-message li {
            margin: 5px 0;
        }
        .help-text {
            font-size: 0.9em;
            color: #6b7280;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h1>Login</h1>
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <h3>Login Failed</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                    <?php if (strpos($error, 'Too many login attempts') !== false): ?>
                        <ul>
                            <li>Please wait a few minutes before trying again</li>
                            <li>If this persists, contact your administrator</li>
                        </ul>
                    <?php elseif (strpos($error, 'Invalid credentials') !== false): ?>
                        <ul>
                            <li>Check your username and password</li>
                            <li>Make sure caps lock is not enabled</li>
                            <li>Try using the password reset option if available</li>
                        </ul>
                    <?php elseif (strpos($error, 'IP not whitelisted') !== false): ?>
                        <ul>
                            <li>Your IP address is not authorized to access the admin area</li>
                            <li>Contact your administrator to add your IP to the whitelist</li>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="admin.php" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                    <div class="help-text">Enter your admin username</div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="help-text">Enter your admin password</div>
                </div>
                
                <button type="submit" class="button button-primary">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
