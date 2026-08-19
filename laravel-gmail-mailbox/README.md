# Laravel Gmail Mailbox Package

A reusable, plug-and-play Laravel package to integrate **Google Gmail API** into any Laravel application with a modern, responsive Gmail-like Mailbox UI.

---

## Features

- **Google OAuth 2.0 Integration**: Automatic token exchange & offline refresh token handling.
- **Modern Mailbox UI**: Responsive 2-column layout (Inbox / Sent lists, live search, unread badge pulse).
- **Rich Email Viewer**: Sanitized HTML body display, inline image replacement, and attachment downloading/viewing.
- **Compose & Reply Modal**: Send new emails and reply directly to threads with file attachments and CC/BCC support.
- **Customizable & Extendable**: Publishable configuration, views, layout integration, and migrations.

---

## Requirements

- PHP `^8.1`
- Laravel `10.x` or `11.x`
- `google/apiclient: ^2.15|^2.19`

---

## Installation

### 1. Require the Package via Composer

If hosted on Packagist:
```bash
composer require queentech/laravel-gmail-mailbox
```

Or via local path repository in your application's `composer.json`:
```json
"repositories": [
    {
        "type": "path",
        "url": "packages/laravel-gmail-mailbox"
    }
],
"require": {
    "queentech/laravel-gmail-mailbox": "@dev"
}
```

### 2. Publish Configuration & Migrations (Optional)

```bash
# Publish configuration
php artisan vendor:publish --tag=gmail-mailbox-config

# Publish migrations
php artisan vendor:publish --tag=gmail-mailbox-migrations

# Publish views (if you want to customize the HTML)
php artisan vendor:publish --tag=gmail-mailbox-views
```

### 3. Run Migrations

```bash
php artisan migrate
```

---

## Configuration

Add your Google Cloud credentials to your `.env` file:

```env
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/gmail/callback"

# Optional customizations:
GMAIL_MAILBOX_PREFIX=gmail
GMAIL_MAILBOX_LAYOUT=layouts/layoutMaster
GMAIL_MAILBOX_PER_PAGE=15
```

---

## Routes Provided

| Method | URI | Action | Name |
|---|---|---|---|
| `GET` | `/gmail/inbox` | Main Mailbox interface | `gmail.inbox` |
| `GET` | `/gmail/settings` | Connect / Disconnect page | `gmail.settings` |
| `GET` | `/gmail/auth` | Redirect to Google OAuth | `gmail.auth` |
| `GET` | `/gmail/callback` | OAuth Redirect Callback | `gmail.callback` |
| `GET` | `/gmail/email/{id}` | Email details view/json | `gmail.email.show` |
| `POST`| `/gmail/send` | Send new email | `gmail.send` |
| `POST`| `/gmail/reply/{id}` | Reply to thread | `gmail.reply` |
| `GET` | `/gmail/attachment/{msgId}/{attId}` | Download attachment | `gmail.attachment.download` |
| `GET` | `/gmail/unread-count` | Unread email count | `gmail.unread.count` |

---

## License

The MIT License (MIT).
