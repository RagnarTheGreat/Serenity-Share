<?php
/**
 * Language Translation System
 * Handles loading and retrieving translations for multi-language support
 */

// Don't start session here - let session.php handle it
// Just check if session is active for language preference

// Default language
define('DEFAULT_LANGUAGE', 'en');

// Supported languages
$supported_languages = [
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'it' => 'Italiano',
    'pt' => 'Português',
    'ru' => 'Русский',
    'zh' => '中文',
    'ja' => '日本語',
    'ko' => '한국어'
];

// Get current language from session or default
function getCurrentLanguage() {
    // Check if session is active before accessing $_SESSION
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (isset($_SESSION['language'])) {
            $lang = $_SESSION['language'];
            // Verify language file exists
            $lang_file = __DIR__ . '/../languages/' . $lang . '.json';
            if (file_exists($lang_file)) {
                return $lang;
            } else {
                // Language file doesn't exist, fall back to default
                return DEFAULT_LANGUAGE;
            }
        }
    }
    return DEFAULT_LANGUAGE;
}

// Set language
function setLanguage($lang) {
    global $supported_languages;
    // Session should already be started by initSecureSession() before calling this
    // But we check just in case it's called from a different context
    if (session_status() === PHP_SESSION_NONE) {
        // If session isn't started, we can't set language preference
        // This should rarely happen as initSecureSession() should be called first
        return false;
    }
    if (isset($supported_languages[$lang]) && file_exists(__DIR__ . '/../languages/' . $lang . '.json')) {
        $_SESSION['language'] = $lang;
        // Don't close the session - it will be saved automatically at the end of the request
        return true;
    }
    return false;
}

// Load translations
function loadTranslations($lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    $lang_file = __DIR__ . '/../languages/' . $lang . '.json';
    
    // If language file doesn't exist, fall back to English
    if (!file_exists($lang_file)) {
        $lang_file = __DIR__ . '/../languages/' . DEFAULT_LANGUAGE . '.json';
    }
    
    if (file_exists($lang_file)) {
        $translations = json_decode(file_get_contents($lang_file), true);
        return $translations ?: [];
    }
    
    return [];
}

// Reload translations cache (call this after language change)
function reloadTranslations() {
    $GLOBALS['translations'] = loadTranslations();
    // Update the last language tracker
    $GLOBALS['_last_lang'] = getCurrentLanguage();
}

// Don't load translations at file scope - wait until session is initialized
// This will be loaded by reloadTranslations() or when t() is first called

// Translation function
function t($key, $default = null) {
    // Always check current language - session might have changed between pages
    $current_lang = getCurrentLanguage();
    
    // Get translations from global cache
    // Reload if cache doesn't exist, or if language has changed
    if (!isset($GLOBALS['translations']) || !isset($GLOBALS['_last_lang']) || $GLOBALS['_last_lang'] !== $current_lang) {
        $GLOBALS['translations'] = loadTranslations($current_lang);
        $GLOBALS['_last_lang'] = $current_lang;
    }
    
    $translations = $GLOBALS['translations'];
    
    // Split key by dots (e.g., "admin.dashboard.title")
    $keys = explode('.', $key);
    $value = $translations;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            // Return default or key if not found
            return $default !== null ? $default : $key;
        }
    }
    
    return $value;
}

// Get supported languages
function getSupportedLanguages() {
    global $supported_languages;
    return $supported_languages;
}

// Get current language name
function getCurrentLanguageName() {
    global $supported_languages;
    $lang = getCurrentLanguage();
    return isset($supported_languages[$lang]) ? $supported_languages[$lang] : 'English';
}

