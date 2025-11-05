<?php
// Keep only essential code
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/maintenance_errors.log');

require_once('includes/session.php');
initSecureSession();

try {
    require_once('config.php');
    require_once('includes/utilities.php');

    // Verify session
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        throw new Exception('Not authorized');
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    error_log("Maintenance error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}