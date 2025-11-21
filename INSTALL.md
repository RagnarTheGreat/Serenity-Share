# Serenity Share Installation Guide

This guide will help you install and configure Serenity Share on your web server.

## Prerequisites

- Web server (Apache, Nginx, etc.) with PHP support
- PHP 8.1 or higher (required for QR code generation)
- PHP GD extension enabled (for image manipulation and QR code generation)
- Composer (for installing QR code dependencies)
- Write permissions for the web server user

## Installation Steps

### 1. Download the Files

Clone the repository or download and extract the ZIP file to your web server's document root.

```bash
git clone https://github.com/RagnarTheGreat/Serenity-Share
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

### 3. Install Composer Dependencies (Required for QR Code Feature)

The QR code feature requires Composer dependencies. Install them by running:

```bash
composer install
```

**Note:** If you don't have Composer installed, you can install it by following the instructions at https://getcomposer.org/download/

**For Ubuntu/Debian:**
```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Then install dependencies
composer install
```

If you see a "QR Code library not found" error, make sure you've run `composer install` in the project root directory.

### 4. Set Directory Permissions

Make sure the following directories are writable by your web server:

```bash
chmod 755 img/ shares/ thumbnails/ logs/ tmp/ cache/ backups/
```

### 5. Configure the Application

Edit the `config.php` file:

1. Set `domain_url` to your website URL (with trailing slash)
2. Change `secret_key` to a strong random string
3. Update the admin password (default is "password")
4. Add your IP address to `admin_ips` for admin area access
5. Set `debug` to false in production environment

### 6. Verify the Installation

1. Visit your website to make sure the main page loads
2. Try to access `/admin.php` and log in
3. Test file uploads to ensure permissions are set correctly

### 7. ShareX Configuration

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
4. **Run `composer install` to update dependencies (especially important if QR code feature was added/updated)**
5. Check if there are any new configuration options to add to your existing config.php

### QR Code Feature Not Working?

If you're getting a "500 Internal Server Error" when trying to generate QR codes:

1. **Make sure Composer dependencies are installed:**
   ```bash
   composer install
   ```

2. **Verify the `vendor` directory exists:**
   ```bash
   ls -la vendor/autoload.php
   ```
   This file should exist. If it doesn't, run `composer install`.

3. **Check PHP version:**
   ```bash
   php -v
   ```
   You need PHP 8.1 or higher for QR code generation.

4. **Verify GD extension is enabled:**
   ```bash
   php -m | grep -i gd
   ```
   If GD is not listed, install it:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install php-gd
   sudo systemctl restart apache2  # or nginx, php-fpm, etc.
   ``` 
