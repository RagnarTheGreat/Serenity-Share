<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serenity Share - Self-Hosted File and Image Sharing Solution</title>
    <meta name="description" content="Free self-hosted file and image sharing solution with ShareX integration - Easy to deploy on any web host">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/images/favicon/site.webmanifest">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
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
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--bg-dark);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.8));
            background-size: cover;
            background-position: center;
            color: var(--white);
            padding: 120px 20px;
            text-align: center;
            position: relative;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-darker);
            opacity: 0.1;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 4em;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -0.025em;
        }

        .hero p {
            font-size: 1.25em;
            max-width: 600px;
            margin: 0 auto 40px;
            opacity: 0.9;
        }

        .features {
            padding: 100px 0;
            background: var(--bg-darker);
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5em;
            color: var(--white);
            margin-bottom: 20px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 40px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .feature-card {
            background: var(--card-bg);
            padding: 40px 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
            border: 1px solid var(--border-color);
            animation: fadeInUp 0.6s ease backwards;
        }

        .feature-card:nth-child(1) { animation-delay: 0.1s; }
        .feature-card:nth-child(2) { animation-delay: 0.2s; }
        .feature-card:nth-child(3) { animation-delay: 0.3s; }
        .feature-card:nth-child(4) { animation-delay: 0.4s; }
        .feature-card:nth-child(5) { animation-delay: 0.5s; }
        .feature-card:nth-child(6) { animation-delay: 0.6s; }
        .feature-card:nth-child(7) { animation-delay: 0.7s; }
        .feature-card:nth-child(8) { animation-delay: 0.8s; }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(145deg, var(--card-bg), var(--secondary-color));
        }

        .feature-card i {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 1.5em;
            margin-bottom: 15px;
            color: var(--white);
        }

        .feature-card p {
            color: var(--text-light);
        }

        .screenshots {
            padding: 80px 0;
            background: var(--bg-darker);
        }

        .screenshot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .screenshot-item {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            cursor: pointer;
            aspect-ratio: 16/9;
            border: 1px solid var(--border-color);
        }

        .screenshot-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .screenshot-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .screenshot-overlay span {
            color: white;
            font-weight: 500;
            font-size: 1.1em;
        }

        .screenshot-item:hover {
            transform: translateY(-5px);
        }

        .screenshot-item:hover .screenshot-overlay {
            opacity: 1;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.98);
            z-index: 1000;
            padding: 20px;
            cursor: pointer;
            backdrop-filter: blur(5px);
        }

        .modal img {
            max-width: 95%;
            max-height: 95vh;
            margin: auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: var(--border-radius);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
        }

        .modal::after {
            content: '×';
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 30px;
            color: white;
            cursor: pointer;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .modal::after:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .pricing {
            padding: 100px 0;
            background: var(--bg-darker);
        }

        .price-card {
            max-width: 600px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .button {
            display: inline-block;
            padding: 15px 30px;
            background: var(--primary-color);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: transform 0.2s, background-color 0.2s;
            border: 1px solid transparent;
        }

        .button:hover {
            transform: translateY(-2px);
            background: var(--primary-dark);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        footer {
            background: var(--secondary-color);
            color: var(--white);
            padding: 60px 0;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--card-bg);
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-links a i {
            font-size: 20px;
        }

        .social-links a:hover {
            transform: translateY(-3px);
            background: var(--primary-color);
        }

        .social-links a.discord:hover {
            background: #5865F2;
        }

        .social-links a.email:hover {
            background: #ea4335;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5em;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }

            .screenshot-grid {
                grid-template-columns: 1fr;
                padding: 0 15px;
            }

            .screenshot-overlay {
                opacity: 1;
            }
        }

        .hosting-options {
            background: var(--bg-darker);
            padding: 40px 0;
        }

        .compatibility-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin: 40px 0;
        }

        .compatibility-item {
            background: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 150px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .compatibility-item i {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .compatibility-item span {
            font-size: 1.2em;
            color: var(--white);
        }

        .compatibility-item.recommended {
            border-color: var(--primary-color);
            background: linear-gradient(145deg, var(--card-bg), rgba(59, 130, 246, 0.1));
        }

        .requirements-list {
            background: var(--card-bg);
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 20px 0;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .requirements-list li {
            margin: 10px 0;
            padding-left: 25px;
            position: relative;
        }

        .requirements-list li:before {
            content: "→";
            position: absolute;
            left: 0;
            color: var(--primary-color);
        }

        .logo {
            max-width: 150px;
            margin-bottom: 20px;
        }

        .feature-card, .compatibility-item, .screenshot-item {
            transition: all 0.3s ease;
        }

        .recommended-badge {
            background: var(--accent-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-bottom: 20px;
            display: inline-block;
        }

        .hosting-advice {
            background: var(--bg-darker);
            padding: 40px 0;
        }

        .hosting-advice p {
            color: var(--white);
            margin-bottom: 20px;
        }

        .hosting-advice .hosting-button {
            display: inline-block;
            padding: 15px 30px;
            background: var(--primary-color);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: transform 0.2s, background-color 0.2s;
            border: 1px solid transparent;
            margin-right: 10px;
        }

        .hosting-advice .hosting-button:hover {
            transform: translateY(-2px);
            background: var(--primary-dark);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        .hosting-contact-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hosting-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .hosting-button i {
            font-size: 1.2em;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hosting-contact-buttons {
                flex-direction: column;
            }
            
            .hosting-button {
                width: 100%;
                justify-content: center;
            }
        }

        /* Optional: Add animation for the recommended badge */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .recommended-badge {
            animation: pulse 2s infinite;
        }

        /* Feature Showcase Styles */
        .feature-showcase {
            padding: 80px 0;
            background: var(--bg-darker);
            overflow: hidden;
        }

        .showcase-item {
            display: flex;
            align-items: center;
            gap: 60px;
            padding: 40px;
            background: linear-gradient(145deg, var(--card-bg), rgba(59, 130, 246, 0.1));
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .feature-tag {
            background: var(--accent-color);
            color: var(--white);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-block;
            margin-bottom: 15px;
        }

        .upload-demo {
            width: 300px;
            height: 60px;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            width: 0;
            background: var(--primary-color);
            animation: upload 2s infinite;
        }

        @keyframes upload {
            0% { width: 0; }
            100% { width: 100%; }
        }

        /* Statistics Styles */
        .statistics {
            padding: 80px 0;
            background: var(--bg-darker);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            text-align: center;
        }

        .stat-item {
            padding: 30px;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3em;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 1.1em;
        }

        /* Add animation for statistics */
        .stat-item {
            animation: fadeInUp 0.6s ease backwards;
        }

        .stat-item:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stat-item:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Floating Features */
        .floating-features {
            margin-top: -60px;
            margin-bottom: 60px;
            position: relative;
            z-index: 10;
        }

        .floating-features .container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            padding: 0 20px;
        }

        .floating-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            text-align: center;
            box-shadow: var(--shadow-lg);
            animation: float 6s ease-in-out infinite;
            transition: all 0.3s ease;
        }

        .floating-card:nth-child(2) {
            animation-delay: -2s;
        }

        .floating-card:nth-child(3) {
            animation-delay: -4s;
        }

        .floating-card i {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .floating-card h3 {
            color: var(--white);
            font-size: 1.5em;
            margin: 15px 0;
        }

        .floating-card p {
            color: var(--text-light);
            font-size: 0.95em;
            line-height: 1.6;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Add glowing effect on hover */
        .floating-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2);
            transform: translateY(-5px);
            background: linear-gradient(145deg, var(--card-bg), rgba(59, 130, 246, 0.1));
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .floating-features {
                margin-top: -30px;
            }
            
            .floating-card {
                padding: 20px;
            }
        }

        /* Add typing effect styles */
        .typing-text {
            border-right: 2px solid var(--primary-color);
            white-space: nowrap;
            overflow: hidden;
            animation: typing 3.5s steps(40, end),
                       blink-caret .75s step-end infinite;
        }

        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }

        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: var(--primary-color) }
        }

        /* Particle background styles */
        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        /* Ensure other sections stay above particles */
        section {
            position: relative;
            z-index: 1;
        }

        /* Common card hover effect for all cards */
        .feature-card, .compatibility-item, .floating-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .feature-card:hover, .compatibility-item:hover, .floating-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2);
            transform: translateY(-5px);
            background: linear-gradient(145deg, var(--card-bg), rgba(59, 130, 246, 0.1));
        }

        /* Update feature cards */
        .feature-card {
            padding: 40px 30px;
            border-radius: var(--border-radius);
            text-align: center;
        }

        /* Update compatibility items */
        .compatibility-item {
            padding: 30px;
            border-radius: var(--border-radius);
            text-align: center;
        }

        /* Update requirements list items */
        .requirements-list ul li {
            transition: all 0.3s ease;
            padding: 15px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
        }

        .requirements-list ul li:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2);
            transform: translateX(5px);
            background: linear-gradient(145deg, var(--card-bg), rgba(59, 130, 246, 0.1));
        }

        /* Price card hover effect */
        .price-card {
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .price-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2);
            transform: translateY(-5px);
            background: linear-gradient(145deg, var(--card-bg), rgba(59, 130, 246, 0.1));
        }

        /* Ensure icons maintain their color */
        .feature-card i, .compatibility-item i, .floating-card i {
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        /* Optional: Add icon pulse on hover */
        .feature-card:hover i, .compatibility-item:hover i, .floating-card:hover i {
            animation: iconPulse 0.5s ease;
        }

        @keyframes iconPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Ensure text remains readable on hover */
        .feature-card:hover h3, 
        .compatibility-item:hover span, 
        .floating-card:hover h3 {
            color: var(--white);
        }

        .feature-card:hover p, 
        .floating-card:hover p {
            color: var(--text-light);
        }

        /* CTA Banner Styles */
        .cta-banner {
            padding: 80px 0;
            background: var(--bg-darker);
        }

        .cta-content.feature-card {
            max-width: 800px;
            margin: 0 auto;
            padding: 60px 40px;
            text-align: center;
        }

        .cta-content h2 {
            font-size: 2.5em;
            margin-bottom: 20px;
            color: var(--white);
        }

        .cta-content p {
            font-size: 1.2em;
            color: var(--text-light);
            margin-bottom: 30px;
        }

        .cta-buttons {
            margin-top: 30px;
        }

        .cta-buttons .button {
            padding: 15px 40px;
            font-size: 1.1em;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .cta-content h2 {
                font-size: 2em;
            }
            
            .cta-content.feature-card {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <section class="hero">
        <div class="hero-content container">
            <img src="assets/images/logo.png" alt="Serenity Share Logo" class="logo">
            <h1>Serenity Share</h1>
            <p>Free web-based file and image hosting solution. Easy to deploy, secure by design.</p>
            <a href="https://github.com/RagnarTheGreat/Serenity-Share" class="button">Get Started</a>
        </div>
    </section>

    <section class="floating-features">
        <div class="container">
            <div class="floating-card">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>ShareX Ready</h3>
                <p>Direct integration with ShareX for instant uploads</p>
            </div>
            <div class="floating-card">
                <i class="fas fa-lock"></i>
                <h3>Secure Access</h3>
                <p>Private uploads with secure sharing options</p>
            </div>
            <div class="floating-card">
                <i class="fas fa-image"></i>
                <h3>Image Gallery</h3>
                <p>Beautiful gallery to showcase your uploads</p>
            </div>
            <div class="floating-card">
                <i class="fab fa-discord"></i>
                <h3>Discord Notifications</h3>
                <p>Real-time notifications in Discord when files are uploaded</p>
            </div>
        </div>
    </section>

    <section class="screenshots">
        <div class="container">
            <div class="section-title">
                <h2>Beautiful Interface</h2>
                <p>Modern, clean, and user-friendly design</p>
            </div>
            <div class="screenshot-grid">
                <div class="screenshot-item" onclick="openModal('assets/images/dash.png')">
                    <div class="screenshot-overlay">
                        <span>Dashboard View</span>
                    </div>
                    <img src="assets/images/dash.png" alt="Dashboard Interface">
                </div>
                <div class="screenshot-item" onclick="openModal('assets/images/share.png')">
                    <div class="screenshot-overlay">
                        <span>File Sharing</span>
                    </div>
                    <img src="assets/images/share.png" alt="File Upload Interface">
                </div>
                <div class="screenshot-item" onclick="openModal('assets/images/gallery.png')">
                    <div class="screenshot-overlay">
                        <span>Gallery View</span>
                    </div>
                    <img src="assets/images/gallery.png" alt="Analytics Dashboard">
                </div>
                <div class="screenshot-item" onclick="openModal('assets/images/Discord_JmpQF3inS7.png')">
                    <div class="screenshot-overlay">
                        <span>Discord Notifications</span>
                    </div>
                    <img src="assets/images/Discord_JmpQF3inS7.png" alt="Discord Notifications">
                </div>
            </div>
        </div>
    </section>

    <section class="hosting-options">
        <div class="container">
            <div class="section-title">
                <h2>Deploy Anywhere</h2>
                <p>Flexible deployment options for any hosting environment</p>
            </div>
            <div class="compatibility-list">
                <div class="compatibility-item recommended">
                    <i class="fas fa-server"></i>
                    <span>DirectAdmin</span>
                    <div class="recommended-badge">Recommended</div>
                </div>
                <div class="compatibility-item">
                    <i class="fas fa-cloud"></i>
                    <span>VPS</span>
                </div>
                <div class="compatibility-item">
                    <i class="fas fa-network-wired"></i>
                    <span>Dedicated Server</span>
                </div>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>Key Features</h2>
                <p>Everything you need for a professional file and image sharing service</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-rocket"></i>
                    <h3>Easy Deployment</h3>
                    <p>Simple installation process on any PHP-enabled web host</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-code"></i>
                    <h3>Open Source</h3>
                    <p>Free and open source software - no license required</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-image"></i>
                    <h3>Image Gallery</h3>
                    <p>Beautiful gallery view for organizing and showcasing uploads</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-clock"></i>
                    <h3>Expiring Links</h3>
                    <p>Set expiration times for sensitive file sharing</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-user-shield"></i>
                    <h3>Password Protection</h3>
                    <p>Secure your uploads with optional password protection</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-file-archive"></i>
                    <h3>ZIP Downloads</h3>
                    <p>Download multiple files as a ZIP archive</p>
                </div>
                <div class="feature-card">
                    <i class="fab fa-discord"></i>
                    <h3>Discord Notifications</h3>
                    <p>Get real-time notifications in Discord with rich embeds and image previews</p>
                </div>
            </div>
        </div>
    </section>

    <section class="requirements">
        <div class="container">
            <div class="section-title">
                <h2>DirectAdmin Server Requirements</h2>
                <p>Minimum server specifications for optimal performance</p>
            </div>
            <div class="requirements-list">
                <ul>
                <li>DirectAdmin 1.60 or higher</li>
                <li>PHP 7.4+ with curl and zip extensions</li>
                <li>MySQL 5.7+ or MariaDB 10.3+</li>
                <li>Minimum 1GB RAM (2GB recommended)</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="pricing">
        <div class="container">
            <div class="section-title">
                <h2>Free File Sharing Solution</h2>
                <p>Get your file and image sharing solution today - 100% Free</p>
            </div>
            <div class="price-card">
                <h3>What's Included</h3>
                <ul style="list-style: none; margin: 20px 0;">
                    <li>✓ Completely free</li>
                    <li>✓ No license required</li>
                    <li>✓ ShareX configuration</li>
                </ul>
            </div>
        </div>
    </section>

    <div id="imageModal" class="modal" onclick="closeModal()">
        <img id="modalImage" src="" alt="Enlarged Screenshot">
    </div>

    <section class="cta-banner">
        <div class="container">
            <div class="cta-content feature-card">
                <h2>Ready to Start Your Own File Hosting Service?</h2>
                <p>Get started today with Serenity Share - 100% Free</p>
                <div class="cta-buttons">
                    <a href="https://github.com/RagnarTheGreat/Serenity-Share" class="button primary">
                        <i class="fab fa-github"></i> Download on GitHub
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>© 2024 Serenity Share - Self-Hosted File and Image Sharing Solution</p>
            <div class="social-links">
                <a href="https://discord.gg/Bp9FqPEcuB" class="discord" title="Join our Discord">
                    <i class="fa-brands fa-discord"></i>
                </a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        function openModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = "block";
            modalImg.src = imageSrc;
        }

        function closeModal() {
            document.getElementById('imageModal').style.display = "none";
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeModal();
            }
        });

        // Counter animation
        const counters = document.querySelectorAll('.counter');
        
        counters.forEach(counter => {
            const target = parseFloat(counter.getAttribute('data-target'));
            const duration = 2000; // 2 seconds
            const step = target / (duration / 16); // 60fps
            
            function updateCount() {
                const current = parseFloat(counter.innerText);
                if (current < target) {
                    counter.innerText = Math.min(current + step, target).toFixed(1);
                    setTimeout(updateCount, 16);
                }
            }
            
            // Start counter when element is in view
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    updateCount();
                    observer.disconnect();
                }
            });
            
            observer.observe(counter);
        });

        particlesJS('particles-js',
            {
                "particles": {
                    "number": {
                        "value": 80,
                        "density": {
                            "enable": true,
                            "value_area": 800
                        }
                    },
                    "color": {
                        "value": "#3b82f6"
                    },
                    "opacity": {
                        "value": 0.2
                    },
                    "size": {
                        "value": 3
                    },
                    "line_linked": {
                        "enable": true,
                        "distance": 150,
                        "color": "#3b82f6",
                        "opacity": 0.1,
                        "width": 1
                    },
                    "move": {
                        "enable": true,
                        "speed": 2
                    }
                }
            }
        );
    </script>
</body>
</html>
