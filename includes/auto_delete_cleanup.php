<?php
/**
 * Run scheduled auto-deletions (gallery files that were set to delete after a delay).
 * Safe to include on every page load. Runs only if config has auto_delete_schedule_file.
 * No cron needed: any visit to the site will process expired files.
 */
if (!function_exists('runAutoDeleteCleanup')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/utilities.php';
}
if (!empty($config['auto_delete_schedule_file'])) {
    runAutoDeleteCleanup($config['upload_dir'], 'thumbnails/', $config['auto_delete_schedule_file']);
}
