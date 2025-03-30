<?php
/**
 * Serenity Share - Password Hash Generator
 * This utility helps create secure password hashes
 */

// Initialize variables
$password = '';
$hashed_password = '';
$message = '';
$algo_options = [
    'DEFAULT' => 'Default (PASSWORD_DEFAULT)',
    'BCRYPT' => 'BCrypt (PASSWORD_BCRYPT)',
    'ARGON2I' => 'Argon2i (PASSWORD_ARGON2I)',
    'ARGON2ID' => 'Argon2id (PASSWORD_ARGON2ID)'
];
$selected_algo = 'DEFAULT';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && !empty($_POST['password'])) {
        $password = $_POST['password'];
        $selected_algo = $_POST['algorithm'] ?? 'DEFAULT';
        
        // Get the proper algorithm constant
        $algorithm = PASSWORD_DEFAULT;
        switch ($selected_algo) {
            case 'BCRYPT':
                $algorithm = PASSWORD_BCRYPT;
                break;
            case 'ARGON2I':
                $algorithm = defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_DEFAULT;
                break;
            case 'ARGON2ID':
                $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                break;
        }
        
        // Generate hash
        $hashed_password = password_hash($password, $algorithm);
        $message = 'Password hash generated successfully!';
    } else {
        $message = 'Please enter a password.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator - Serenity Share</title>
    <style>
        :root {
            --bg-darker: #0f172a;
            --bg-dark: #1e293b; 
            --text-color: #fff;
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --accent-color: #60a5fa;
            --text-light: #94a3b8;
            --white: #fff;
            --border-color: #334155;
            --border-radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, .1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, .2);
        }
        
        body {
            background: linear-gradient(135deg, var(--bg-darker) 0, var(--bg-dark) 100%);
            color: var(--text-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .container {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown .8s ease;
        }
        
        .page-header h1 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 15px;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .page-header p {
            color: var(--text-light);
            font-size: 1.1em;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input, select, button {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
        
        button {blank
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            color: white;
            font-weight: 600;
            cursor: pointer;
            border: none;
            margin-top: 10px;
        }
        
        button:hover {
            background: linear-gradient(45deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .result {
            margin-top: 30px;
            animation: fadeIn 0.5s ease;
        }
        
        .result-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .result-header h3 {
            margin: 0;
            color: var(--accent-color);
        }
        
        .copy-button {
            background: rgba(96, 165, 250, 0.2);
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            padding: 5px 10px;
            font-size: 0.8em;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        
        .copy-button:hover {
            background: rgba(96, 165, 250, 0.3);
        }
        
        .hash-output {
            padding: 15px;
            background: rgba(15, 23, 42, 0.7);
            border-radius: 6px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .message {
            margin-top: 15px;
            padding: 10px 15px;
            border-radius: 6px;
            animation: fadeIn 0.3s ease;
        }
        
        .success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.5);
            color: #10b981;
        }
        
        .error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #ef4444;
        }
        
        .nav-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }
        
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .info-box h4 {
            margin-top: 0;
            color: var(--accent-color);
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2em;
            }
            
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    
    <div class="container">
        <div class="page-header">
            <h1>Password Hash Generator</h1>
            <p>Generate secure password hashes for your Serenity Share application</p>
        </div>
        
        <div class="card">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">Password to Hash:</label>
                    <input type="text" id="password" name="password" value="<?php echo htmlspecialchars($password); ?>" placeholder="Enter password to hash" autocomplete="off" required>
                </div>
                
                <div class="form-group">
                    <label for="algorithm">Hashing Algorithm:</label>
                    <select id="algorithm" name="algorithm">
                        <?php foreach ($algo_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($selected_algo === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit">Generate Hash</button>
                </div>
            </form>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo (!empty($hashed_password)) ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($hashed_password)): ?>
                <div class="result">
                    <div class="result-header">
                        <h3>Password Hash:</h3>
                        <button class="copy-button" onclick="copyHash()">Copy</button>
                    </div>
                    <div class="hash-output" id="hash-output"><?php echo htmlspecialchars($hashed_password); ?></div>
                </div>
                
                <div class="info-box">
                    <h4>How to use this hash:</h4>
                    <p>
                        1. Copy this hash and use it in your database for password storage.<br>
                        2. Never store raw passwords in your database.<br>
                        3. Use <code>password_verify($password, $hash)</code> to verify passwords during login.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>About Password Hashing</h2>
            <p>Password hashing is a one-way process that converts passwords into a fixed-length string of characters, which appears random. The key benefits are:</p>
            
            <ul>
                <li><strong>Security:</strong> Even if your database is compromised, the actual passwords remain protected.</li>
                <li><strong>Verification:</strong> You can verify a password without storing the actual password.</li>
                <li><strong>Salt:</strong> PHP's password hashing functions automatically include a salt to protect against rainbow table attacks.</li>
            </ul>
            
            <h3>Supported Algorithms</h3>
            <p>PHP supports various hashing algorithms:</p>
            
            <ul>
                <li><strong>PASSWORD_DEFAULT:</strong> Currently uses bcrypt, but may change in future PHP versions to stronger algorithms.</li>
                <li><strong>PASSWORD_BCRYPT:</strong> Implements the Blowfish algorithm, producing a 60-character hash.</li>
                <li><strong>PASSWORD_ARGON2I:</strong> Argon2 algorithm optimized for higher resistance against GPU cracking attacks (PHP 7.2+).</li>
                <li><strong>PASSWORD_ARGON2ID:</strong> Hybrid version of Argon2 providing better resistance against both GPU and side-channel attacks (PHP 7.3+).</li>
            </ul>
        </div>
        
        <a href="index.php" class="nav-link">← Back to Serenity Share</a>
    </div>
    
    <script>
        function copyHash() {
            const hashOutput = document.getElementById('hash-output');
            const textArea = document.createElement('textarea');
            textArea.value = hashOutput.textContent;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            // Show copied message
            const copyBtn = document.querySelector('.copy-button');
            const originalText = copyBtn.textContent;
            copyBtn.textContent = 'Copied!';
            setTimeout(() => {
                copyBtn.textContent = originalText;
            }, 2000);
        }
        
        // Initialize particles.js if available
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof particlesJS !== 'undefined') {
                particlesJS('particles-js', {
                    particles: {
                        number: {
                            value: 80,
                            density: {
                                enable: true,
                                value_area: 800
                            }
                        },
                        color: {
                            value: '#3b82f6'
                        },
                        opacity: {
                            value: 0.5,
                            random: true
                        },
                        size: {
                            value: 3,
                            random: true
                        },
                        line_linked: {
                            enable: true,
                            distance: 150,
                            color: '#3b82f6',
                            opacity: 0.4,
                            width: 1
                        },
                        move: {
                            enable: true,
                            speed: 2,
                            direction: 'none',
                            random: true,
                            straight: false,
                            out_mode: 'out',
                            bounce: false
                        }
                    },
                    interactivity: {
                        detect_on: 'canvas',
                        events: {
                            onhover: {
                                enable: true,
                                mode: 'grab'
                            },
                            onclick: {
                                enable: true,
                                mode: 'push'
                            },
                            resize: true
                        }
                    }
                });
            }
        });
    </script>
</body>
</html> 