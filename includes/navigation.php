<?php
/**
 * Navigation Component
 * Reusable navigation bar for all admin pages
 */
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    return; // Don't show navigation if not logged in
}

// Get current page to highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="main-navigation">
    <div class="nav-container">
        <div class="nav-brand">
            <h1><?php echo t('admin.dashboard.title', 'Admin Dashboard'); ?></h1>
        </div>
        <div class="nav-links">
            <a href="admin.php" class="nav-link <?php echo $current_page === 'admin.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> <span><?php echo t('admin.buttons.home', 'Home'); ?></span>
            </a>
            <a href="gallery.php" class="nav-link <?php echo $current_page === 'gallery.php' ? 'active' : ''; ?>">
                <i class="fas fa-images"></i> <span><?php echo t('admin.buttons.view_gallery', 'View Gallery'); ?></span>
            </a>
            <a href="logs.php" class="nav-link <?php echo $current_page === 'logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-list-alt"></i> <span><?php echo t('admin.buttons.view_logs', 'View Logs'); ?></span>
            </a>
            <a href="share.php" class="nav-link <?php echo $current_page === 'share.php' ? 'active' : ''; ?>">
                <i class="fas fa-share-alt"></i> <span><?php echo t('admin.buttons.share_files', 'Share Files'); ?></span>
            </a>
            <a href="shorten.php" class="nav-link <?php echo $current_page === 'shorten.php' ? 'active' : ''; ?>">
                <i class="fas fa-link"></i> <span><?php echo t('admin.buttons.shorten_links', 'Shorten Links'); ?></span>
            </a>
        </div>
        <div class="nav-actions">
            <div class="language-switcher">
                <select id="language-select" onchange="changeLanguage(this.value)">
                    <?php 
                    $current_lang = getCurrentLanguage();
                    $languages = getSupportedLanguages();
                    foreach ($languages as $code => $name): 
                    ?>
                        <option value="<?php echo $code; ?>" <?php echo $current_lang === $code ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="?logout=1" class="nav-link nav-link-danger">
                <i class="fas fa-sign-out-alt"></i> <span><?php echo t('admin.buttons.logout', 'Logout'); ?></span>
            </a>
        </div>
    </div>
</nav>

<style>
.main-navigation {
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.95) 0%, rgba(26, 26, 46, 0.95) 100%);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(79, 70, 229, 0.2);
    padding: 0;
    margin-bottom: 30px;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
}

.nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

.nav-brand {
    display: flex;
    align-items: center;
}

.nav-brand h1 {
    font-size: 1.4rem;
    font-weight: 700;
    background: linear-gradient(135deg, #ffffff 0%, #a5b4fc 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
    padding: 0;
    letter-spacing: -0.5px;
    text-shadow: 0 0 30px rgba(165, 180, 252, 0.3);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    background: rgba(0, 0, 0, 0.2);
    padding: 6px;
    border-radius: 12px;
    backdrop-filter: blur(5px);
}

.nav-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.nav-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.85);
    background: transparent;
    border: 1px solid transparent;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
    font-size: 0.95rem;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
}

.nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%);
    opacity: 0;
    transition: opacity 0.25s ease;
    z-index: -1;
}

.nav-link i {
    font-size: 0.9rem;
    transition: transform 0.25s ease;
}

.nav-link:hover {
    color: #fff;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(99, 102, 241, 0.15) 100%);
    border-color: rgba(79, 70, 229, 0.3);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
}

.nav-link:hover i {
    transform: scale(1.1);
}

.nav-link.active {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: #fff;
    border-color: rgba(79, 70, 229, 0.5);
    box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1) inset;
    font-weight: 600;
}

.nav-link.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60%;
    height: 2px;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 2px;
}

.nav-link-danger {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    color: #fff;
    border-color: rgba(220, 38, 38, 0.5);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.nav-link-danger:hover {
    background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
}

.nav-link-danger i {
    transform: rotate(-90deg);
}

.language-switcher {
    display: inline-block;
    position: relative;
}

.language-switcher::before {
    content: '🌐';
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    font-size: 1rem;
    pointer-events: none;
}

.language-switcher select {
    padding: 10px 20px 10px 40px;
    border: 1px solid rgba(79, 70, 229, 0.3);
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.2) 0%, rgba(99, 102, 241, 0.2) 100%);
    color: #fff;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 1em;
    padding-right: 35px;
    backdrop-filter: blur(5px);
}

.language-switcher select:hover {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.3) 0%, rgba(99, 102, 241, 0.3) 100%);
    border-color: rgba(79, 70, 229, 0.5);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    transform: translateY(-1px);
}

.language-switcher select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3), 0 4px 12px rgba(79, 70, 229, 0.2);
    border-color: rgba(79, 70, 229, 0.6);
}

.language-switcher select option {
    background: #1e1e1e;
    color: #fff;
    padding: 10px;
}

@media (max-width: 1024px) {
    .nav-container {
        padding: 12px 20px;
    }
    
    .nav-links {
        gap: 6px;
        padding: 4px;
    }
    
    .nav-link {
        padding: 8px 14px;
        font-size: 0.9rem;
    }
}

@media (max-width: 768px) {
    .main-navigation {
        margin-bottom: 20px;
    }
    
    .nav-container {
        flex-direction: column;
        align-items: stretch;
        padding: 16px 20px;
        gap: 16px;
    }
    
    .nav-brand {
        text-align: center;
        justify-content: center;
    }
    
    .nav-brand h1 {
        font-size: 1.3rem;
    }
    
    .nav-links {
        justify-content: center;
        width: 100%;
        gap: 4px;
        padding: 4px;
    }
    
    .nav-actions {
        justify-content: center;
        width: 100%;
        gap: 10px;
    }
    
    .nav-link {
        flex: 1;
        justify-content: center;
        min-width: 0;
        padding: 10px 12px;
        font-size: 0.85rem;
    }
    
    .language-switcher {
        flex: 1;
        min-width: 150px;
    }
    
    .language-switcher select {
        width: 100%;
    }
    
    .nav-link-danger {
        flex: 0 0 auto;
        min-width: auto;
    }
}

@media (max-width: 480px) {
    .nav-container {
        padding: 12px 16px;
    }
    
    .nav-brand h1 {
        font-size: 1.2rem;
    }
    
    .nav-links {
        gap: 3px;
        padding: 3px;
    }
    
    .nav-link {
        padding: 8px 10px;
        font-size: 0.8rem;
        gap: 6px;
    }
    
    .nav-link i {
        font-size: 0.85rem;
    }
    
    .nav-link span {
        display: none;
    }
    
    .nav-link-danger span {
        display: inline;
    }
}

/* Page header styling for pages using navigation */
.page-header {
    margin-bottom: 30px;
    padding: 20px;
    background: var(--element-bg, #1e1e1e);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.page-header h1 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-color, #fff);
    margin: 0;
}
</style>

<script>
function changeLanguage(lang) {
    const currentPage = window.location.pathname.split('/').pop() || 'admin.php';
    window.location.href = 'set_language.php?lang=' + lang + '&redirect=' + encodeURIComponent(currentPage);
}
</script>

