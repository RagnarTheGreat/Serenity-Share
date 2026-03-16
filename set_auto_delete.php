<?php
require_once('config.php');
require_once('includes/utilities.php');
require_once('includes/session.php');

header('Content-Type: application/json');

initSecureSession();

if (!validateSession()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$filename = isset($_POST['filename']) ? basename($_POST['filename']) : '';
$duration_seconds = isset($_POST['duration_seconds']) ? (int) $_POST['duration_seconds'] : 0;

if ($filename === '' || $duration_seconds <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid filename or duration']);
    exit;
}

$upload_dir = rtrim($config['upload_dir'], '/');
$filepath = $upload_dir . '/' . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

$schedule_file = isset($config['auto_delete_schedule_file']) ? $config['auto_delete_schedule_file'] : (__DIR__ . '/logs/auto_delete_schedule.json');
$thumbnails_dir = 'thumbnails/';

runAutoDeleteCleanup($config['upload_dir'], $thumbnails_dir, $schedule_file);

$schedule = [];
if (file_exists($schedule_file)) {
    $schedule = @json_decode(file_get_contents($schedule_file), true);
    if (!is_array($schedule)) $schedule = [];
}

$delete_at = time() + $duration_seconds;
$schedule[$filename] = $delete_at;
file_put_contents($schedule_file, json_encode($schedule, JSON_PRETTY_PRINT), LOCK_EX);

echo json_encode([
    'success' => true,
    'filename' => $filename,
    'delete_at' => $delete_at,
    'message' => 'Auto-delete scheduled successfully'
]);
