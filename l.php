<?php
require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');
require_once('templates/error.php');

// Initialize session for language support
initSecureSession();

// Load language system
require_once('includes/language.php');
reloadTranslations();

// Set security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data: https://www.google.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' http://ip-api.com; frame-ancestors 'none'; form-action 'self';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Get the short code from query parameter
$code = $_GET['c'] ?? '';

if (empty($code)) {
    showError(400, t('shorten.invalid_link', 'Invalid Link'), t('shorten.no_code', 'No short code provided.'));
    exit;
}

// Load link data
$linkFile = $config['links_dir'] . $code . '.json';

if (!file_exists($linkFile)) {
    showError(404, t('shorten.link_not_found', 'Link Not Found'), t('shorten.link_not_exist', 'This shortened link does not exist or has been deleted.'));
    exit;
}

$linkData = json_decode(file_get_contents($linkFile), true);

if (!$linkData) {
    showError(500, t('shorten.error', 'Error'), t('shorten.load_failed', 'Failed to load link data.'));
    exit;
}

// Check if link has expired
if ($linkData['expires'] !== 4102444800 && time() > $linkData['expires']) {
    @unlink($linkFile);
    showError(410, t('shorten.link_expired', 'Link Expired'), t('shorten.expired_message', 'This shortened link has expired.'));
    exit;
}

// Increment click counter
if (!isset($linkData['clicks'])) {
    $linkData['clicks'] = 0;
}
$linkData['clicks']++;

// Save updated link data with proper JSON encoding
$jsonData = json_encode($linkData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($jsonData === false) {
    error_log("Failed to encode link data for code: " . $code);
} else {
    $result = file_put_contents($linkFile, $jsonData, LOCK_EX);
    if ($result === false) {
        error_log("Failed to save click count for code: " . $code . " - File: " . $linkFile);
    }
}

// Redirect to original URL
header('Location: ' . $linkData['original_url'], true, 302);
exit;

