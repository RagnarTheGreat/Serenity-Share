# Serenity Share Installation Guide

This guide will help you install and configure Serenity Share on your web server.

## Prerequisites

- Web server (Apache, Nginx, etc.) with PHP support
- PHP 7.4 or higher
- PHP GD extension enabled (for image manipulation)
- Write permissions for the web server user

## Installation Steps

### 1. Download the Files

Clone the repository or download and extract the ZIP file to your web server's document root.

```bash
git clone https://github.com/yourusername/serenity-share.git
# or download and extract the ZIP file
```

### 2. Configure Web Server

#### For Apache:

Make sure the `.htaccess` file is properly working with `mod_rewrite` enabled.

```apache
<Directory /path/to/serenity-share>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

#### For Nginx:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/serenity-share;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 3. Set Directory Permissions

Make sure the following directories are writable by your web server:

```bash
chmod 755 img/ shares/ thumbnails/ logs/ tmp/ cache/ backups/
```

### 4. Configure the Application

Edit the `config.php` file:

1. Set `domain_url` to your website URL (with trailing slash)
2. Change `secret_key` to a strong random string
3. Update the admin password (default is "password")
4. Add your IP address to `admin_ips` for admin area access
5. Set `debug` to false in production environment

### 5. Verify the Installation

1. Visit your website to make sure the main page loads
2. Try to access `/admin.php` and log in
3. Test file uploads to ensure permissions are set correctly

### 6. ShareX Configuration

1. Login to the admin panel
2. Go to the ShareX configuration section
3. Download the configuration file
4. Import it into ShareX

## Troubleshooting

### Upload Issues
- Check if directory permissions are set correctly
- Verify the PHP upload limits in php.ini

### Admin Access Denied
- Make sure your IP is added to the `admin_ips` array in config.php
- Check if you're using the correct admin credentials

### File Not Found Errors
- Ensure mod_rewrite is enabled (Apache)
- Verify that your web server configuration is correct

## Updating

To update Serenity Share:

1. Backup your current installation
2. Download the new version
3. Replace all files except `config.php`
4. Check if there are any new configuration options to add to your existing config.php
5. Run any database migrations if applicable 