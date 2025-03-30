<?php
function initSecureSession() {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } else if (time() - $_SESSION['last_regeneration'] > 3600) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function validateSession() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // Check for session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > 3600)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

function destroySession() {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}
