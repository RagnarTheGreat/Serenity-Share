# Images Directory

This directory stores uploaded images and files that are shared through the Serenity Share application.

## Purpose

- **Image Gallery**: Images uploaded through the admin panel or ShareX are stored here
- **File Sharing**: All uploaded files (images, videos, etc.) are stored in this directory
- **Public Access**: Files in this directory are accessible via the gallery and public share features

## File Naming

Files are stored with unique hash-based filenames to prevent conflicts and ensure security.

## Important Notes

- All uploaded files will be stored in this directory
- Files in this directory are excluded from the Git repository via `.gitignore`
- Make sure this directory has write permissions (755 or 775) for your web server user
- Files are accessed through `logger.php` for tracking and security purposes
- Images larger than 2000px are automatically optimized on upload

## Supported File Types

- Images: JPG, JPEG, PNG, GIF
- Videos: MP4, WebM

## Access Control

- Files are routed through `logger.php` for access logging and security
- Direct access to files is controlled via `.htaccess` rewrite rules
- Gallery view is available through `gallery.php`

## Storage Management

- Consider implementing periodic cleanup of old files
- Monitor disk space usage regularly

