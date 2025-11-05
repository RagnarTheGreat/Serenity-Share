<?php
function showError($code, $title = '', $message = '') {
    switch($code) {
        case 403:
            $title = $title ?: 'Access Denied';
            $message = $message ?: 'You do not have permission to access this resource.';
            break;
        case 404:
            $title = $title ?: 'Page Not Found';
            $message = $message ?: 'The requested page could not be found.';
            break;
        default:
            $title = $title ?: 'Error';
            $message = $message ?: 'An unexpected error occurred.';
    }
    
    http_response_code($code);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <meta name="description" content="<?php echo htmlspecialchars($title); ?>">
        <link rel="stylesheet" href="/assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary-color: #3b82f6;
                --primary-dark: #2563eb;
                --secondary-color: #1e1e1e;
                --accent-color: #f43f5e;
                --text-color: #e2e8f0;
                --text-light: #94a3b8;
                --bg-dark: #111111;
                --bg-darker: #0a0a0a;
                --card-bg: #1e1e1e;
                --border-color: #2e2e2e;
                --white: #ffffff;
                --border-radius: 12px;
                --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', sans-serif;
                line-height: 1.5;
                color: var(--text-color);
                background-color: var(--bg-dark);
                min-height: 100vh;
                margin: 0;
                padding: 0;
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 20px;
            }

            #particles-js {
                position: fixed;
                width: 100%;
                height: 100%;
                top: 0;
                left: 0;
                z-index: 1;
            }

            body {
                min-height: 100vh;
                margin: 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                justify-content: center;
                overflow: hidden;
            }

            .hero {
                position: relative;
                z-index: 2;
                padding: 20px;
                height: 45vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .features {
                position: relative;
                z-index: 2;
                padding: 20px 0;
                height: 45vh;
                display: flex;
                align-items: center;
            }

            .hero-content {
                background: rgba(30, 30, 30, 0.8);
                padding: 30px;
                border-radius: var(--border-radius);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                max-width: 800px;
                margin: 0 auto;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }

            .hero-content h1 {
                font-size: 2.8em;
                margin-bottom: 15px;
                color: var(--accent-color);
                text-shadow: 0 0 10px rgba(244, 63, 94, 0.5);
            }

            .hero-content p {
                font-size: 1.2em;
                margin-bottom: 20px;
            }

            .features-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin: 0 auto;
                max-width: 1200px;
                padding: 0 20px;
            }

            .feature-card {
                background: rgba(30, 30, 30, 0.8);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                padding: 25px;
                height: 200px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                animation: float 4s ease-in-out infinite;
            }

            .feature-card:nth-child(2) {
                animation-delay: 0.5s;
            }

            .feature-card:nth-child(3) {
                animation-delay: 1s;
            }

            @keyframes float {
                0% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
                100% { transform: translateY(0px); }
            }

            .denied-messages {
                text-align: center;
                margin-top: 30px;
                font-size: 1.2em;
                color: var(--text-light);
                font-style: italic;
            }

            .button {
                background: linear-gradient(45deg, var(--accent-color), #ff6b6b);
                border: none;
                padding: 15px 30px;
                font-size: 1.1em;
                transform-origin: center;
                transition: all 0.3s ease;
            }

            .button:hover {
                transform: scale(1.05);
                box-shadow: 0 0 20px rgba(244, 63, 94, 0.4);
            }

            /* Responsive design */
            @media (max-height: 800px) {
                .hero-content h1 {
                    font-size: 2.5em;
                }
                .hero-content p {
                    font-size: 1.1em;
                }
                .feature-card {
                    padding: 20px;
                    height: 180px;
                }
            }

            @media (max-width: 768px) {
                body {
                    overflow-y: auto;
                }
                .features-grid {
                    grid-template-columns: 1fr;
                }
                .hero, .features {
                    height: auto;
                }
            }
        </style>
    </head>
    <body>
        <div id="particles-js"></div>
        
        <section class="hero">
            <div class="hero-content">
                <h1><?php echo htmlspecialchars($code . ' - ' . $title); ?></h1>
                <p><?php echo htmlspecialchars($message); ?></p>
                <div class="denied-messages">
                    <?php if ($code === 403): ?>
                        <p>"Even Gandalf couldn't pass this way! 🧙‍♂️"</p>
                        <p>"Houston, we have a permission problem! 🚀"</p>
                        <p>"This area is more exclusive than a cat's attention span! 🐱"</p>
                    <?php elseif ($code === 404): ?>
                        <p>This file has more commitment issues than your ex. At least it left a 404 note! 💌</p>
                    <?php endif; ?>
                </div>
                <a href="/index.php" class="button">
                    <i class="fas fa-home"></i> Return to Safety
                </a>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="<?php echo $code === 404 ? 'fas fa-exclamation-triangle' : 'fas fa-ban'; ?>"></i>
                        <h3>Current Status</h3>
                        <p><?php echo $code; ?> - <?php echo htmlspecialchars($title); ?></p>
                    </div>
                    <div class="feature-card">
                        <?php if ($code === 404): ?>
                            <i class="fas fa-file"></i>
                            <h3>Requested File</h3>
                            <p><?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?></p>
                        <?php else: ?>
                            <i class="fas fa-network-wired"></i>
                            <h3>Your IP Address</h3>
                            <p><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></p>
                            <p style="font-size: 0.9em; color: var(--text-light);">(We see you! 👀)</p>
                        <?php endif; ?>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-clock"></i>
                        <h3>Time of Incident</h3>
                        <p><?php echo date('H:i:s - Y-m-d'); ?></p>
                        <p style="font-size: 0.9em; color: var(--text-light);">(Noted for posterity! 📝)</p>
                    </div>
                </div>
            </div>
        </section>

        <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
        <script>
            particlesJS('particles-js',
                {
                    "particles": {
                        "number": {
                            "value": 100,
                            "density": {
                                "enable": true,
                                "value_area": 1000
                            }
                        },
                        "color": {
                            "value": "<?php echo $code === 404 ? '#3b82f6' : '#f43f5e'; ?>"
                        },
                        "opacity": {
                            "value": 0.3,
                            "random": true,
                            "anim": {
                                "enable": true,
                                "speed": 1,
                                "opacity_min": 0.1,
                                "sync": false
                            }
                        },
                        "size": {
                            "value": 4,
                            "random": true
                        },
                        "line_linked": {
                            "enable": true,
                            "distance": 150,
                            "color": "<?php echo $code === 404 ? '#3b82f6' : '#f43f5e'; ?>",
                            "opacity": 0.2,
                            "width": 1
                        },
                        "move": {
                            "enable": true,
                            "speed": 3,
                            "direction": "none",
                            "random": true,
                            "straight": false,
                            "out_mode": "out",
                            "bounce": false
                        }
                    },
                    "interactivity": {
                        "detect_on": "canvas",
                        "events": {
                            "onhover": {
                                "enable": true,
                                "mode": "grab"
                            },
                            "resize": true
                        },
                        "modes": {
                            "grab": {
                                "distance": 140,
                                "line_linked": {
                                    "opacity": 0.5
                                }
                            }
                        }
                    }
                }
            );
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Handle direct access to this file
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $code = isset($_GET['code']) ? intval($_GET['code']) : 404;
    showError($code);
}
?> 
