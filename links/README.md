# Links Directory

This directory stores JSON files containing metadata for shortened links created through the link shortener feature.

## File Structure

Each shortened link is stored as a JSON file named `{code}.json` where `{code}` is the unique short code (e.g., `abc123.json`).

## JSON File Format

```json
{
    "code": "abc123",
    "original_url": "https://example.com/long-url",
    "created": 1234567890,
    "expires": 1234567890,
    "clicks": 0
}
```

## Fields

- `code`: The unique short code for this link
- `original_url`: The original URL that this link redirects to
- `created`: Unix timestamp when the link was created
- `expires`: Unix timestamp when the link expires (4102444800 = never expires)
- `clicks`: Number of times the link has been clicked

## Notes

- Expired links are automatically cleaned up when accessed
- This directory is created automatically by the application if it doesn't exist
- Files in this directory are managed by the link shortener system

