# Discord Webhook Setup Guide

This guide explains how to set up Discord webhook notifications for your Serenity-Share server.

## What it does

When enabled, the Discord webhook will send notifications to your Discord channel whenever:
- Someone uploads a screenshot via ShareX
- Someone uploads files to the gallery
- Someone creates a file share

## Setup Instructions

### 1. Create a Discord Webhook

1. Go to your Discord server
2. Right-click on the channel where you want notifications
3. Select "Edit Channel"
4. Go to "Integrations" → "Webhooks"
5. Click "Create Webhook"
6. Copy the webhook URL

### 2. Configure the Webhook

Edit your `config.php` file and update the Discord webhook settings:

```php
'discord_webhook' => array(
    'enabled' => true, // Set to true to enable Discord notifications
    'url' => 'https://discord.com/api/webhooks/YOUR_ACTUAL_WEBHOOK_URL_HERE',
)
```

### 3. Configuration Options

- **enabled**: Set to `true` to enable Discord notifications, `false` to disable
- **url**: Your Discord webhook URL (replace the placeholder with your actual URL)
- **username**: The name that will appear as the bot in Discord
- **avatar_url**: Optional URL to a custom avatar image for the bot

## Notification Details

Each notification includes:
- **Domain**: Your server's domain name
- **Filename**: The uploaded file name
- **File Type**: Image, Video, or File with appropriate emoji
- **File Size**: Human-readable file size
- **Upload Type**: Screenshot, Gallery, or Share
- **Location**: Geographic location of the uploader (if available)
- **Share ID**: For file shares, includes the share ID
- **File Count**: For file shares, shows how many files were shared
- **Expiration**: For file shares, shows when the share expires

## Image Previews

For image files (JPG, PNG, GIF), the Discord message will include a preview of the image.

## Troubleshooting

### Webhook not working?

1. Check that `enabled` is set to `true` in your config
2. Verify your webhook URL is correct
3. Check your server's error logs for any webhook-related errors
4. Make sure your server can make outbound HTTPS requests

### Missing location information?

Location information is fetched from the IP address using a free API. If location shows as "Unknown Location", the IP geolocation service might be temporarily unavailable.

## Security Notes

- Keep your webhook URL private - anyone with the URL can send messages to your Discord channel
- The webhook only sends notifications for successful uploads
- No sensitive file content is sent to Discord, only metadata and image previews
