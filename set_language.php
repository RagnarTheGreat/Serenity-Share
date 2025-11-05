<?php
/**
 * Language Switcher Handler
 * Handles language switching requests
 */

require_once('includes/session.php');
require_once('includes/language.php');

// Initialize session
initSecureSession();

// Get language from request
$lang = isset($_GET['lang']) ? $_GET['lang'] : null;

// Validate and set language
if ($lang && setLanguage($lang)) {
    // Debug: Verify language was set
    error_log("set_language.php: Language set to: " . $lang);
    error_log("set_language.php: Session ID: " . session_id());
    error_log("set_language.php: Session language is: " . (isset($_SESSION['language']) ? $_SESSION['language'] : 'NOT SET'));
    error_log("set_language.php: All session keys: " . implode(', ', array_keys($_SESSION)));
    
    // Reload translations after language change
    reloadTranslations();
    
    // Clear the translation cache to force reload on next page load
    if (isset($GLOBALS['translations'])) {
        unset($GLOBALS['translations']);
    }
    if (isset($GLOBALS['_last_lang'])) {
        unset($GLOBALS['_last_lang']);
    }
    
    // Redirect back to the page that requested the language change
    $redirect = isset($_GET['redirect']) ? basename($_GET['redirect']) : 'admin.php';
    // Sanitize redirect to prevent open redirects
    if (!preg_match('/^[a-zA-Z0-9_-]+\.php$/', $redirect)) {
        $redirect = 'admin.php';
    }
    
    // Session will be saved automatically when script ends
    // Don't close it manually - let PHP save it naturally
    error_log("set_language.php: Before redirect, session language is: " . (isset($_SESSION['language']) ? $_SESSION['language'] : 'NOT SET'));
    
    header('Location: ' . $redirect);
    exit;
} else {
    // Invalid language, redirect to admin
    error_log("set_language.php: Failed to set language: " . ($lang ? $lang : 'NULL'));
    header('Location: admin.php');
    exit;
}

