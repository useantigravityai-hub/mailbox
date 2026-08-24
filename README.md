# Laravel Gmail Mailbox Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eibs/laravel-gmail-mailbox.svg?style=flat-square)](https://packagist.org/packages/eibs/laravel-gmail-mailbox)
[![Total Downloads](https://img.shields.io/packagist/dt/eibs/laravel-gmail-mailbox.svg?style=flat-square)](https://packagist.org/packages/eibs/laravel-gmail-mailbox)
[![License](https://img.shields.io/packagist/l/eibs/laravel-gmail-mailbox.svg?style=flat-square)](https://packagist.org/packages/eibs/laravel-gmail-mailbox)

A complete, plug-and-play Laravel package to integrate the **Google Gmail API** into any Laravel application with a responsive, modern Gmail-like Mailbox UI, OAuth 2.0 authentication, email viewer, composer, and thread reply support.

---

## ✨ Features

- **Multi-Account Mail Switcher**: Connect multiple Gmail and Google Workspace accounts with a native Gmail-style profile dropdown switcher. Seamlessly toggle active mailboxes in real-time.
- **Favorite / VIP Mail Notifications**: Mark specific emails, contacts, or senders as Favorites (⭐). Get instant browser desktop notifications, sound chimes, and rich in-app toast alerts when priority emails arrive or are sent.
- **Google OAuth 2.0 Authentication**: Seamless token authorization, profile metadata syncing (name, email, avatar), automatic token expiration detection, and offline refresh token renewal.
- **Modern Mailbox UI**: 2-column layout (Inbox, Sent) with instant live search, pagination, and real-time unread badges.
- **Rich Email Viewer**: Sanitized HTML body display, inline base64/remote image rendering, and file attachments preview/downloading.
- **Compose & Reply**: Send emails and reply to threads with attachments (support for CC/BCC).
- **Zero-Config or Fully Customizable**: Works out of the box with zero setup, but allows full customization of layouts, blade views, middleware, route prefixes, and database migrations.
- **Works With or Without Auth**: Runs smoothly in projects without authentication, with standard Laravel `auth`, or with custom guards/roles.

---

## 📋 Requirements

- **PHP**: `^8.1`
- **Laravel**: `10.x`, `11.x`, or `12.x`
- **google/apiclient**: `^2.15`

---

## 🚀 Installation

### 1. Install via Composer

```bash
composer require eibs/laravel-gmail-mailbox
```

*(Optional: For local development repository, add this to your Laravel application's `composer.json`:)*
```json
"repositories": [
    {
        "type": "path",
        "url": "packages/laravel-gmail-mailbox"
    }
],
"require": {
    "eibs/laravel-gmail-mailbox": "@dev"
}
```

---

### 2. Run Database Migrations

The package automatically loads its migrations to create the `google_tokens` table:

```bash
php artisan migrate
```

*(Optional: Publish migrations if you wish to customize database columns)*
```bash
php artisan vendor:publish --tag=gmail-mailbox-migrations
```

---

### 3. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=gmail-mailbox-config
```
This creates `config/gmail-mailbox.php` where you can customize middleware, route prefix, master layout, and pagination.

---

## 🔑 Google Cloud Console Setup

1. Visit the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project (or select an existing one).
3. Navigate to **APIs & Services > Library**, search for **Gmail API**, and click **Enable**.
4. Go to **APIs & Services > OAuth consent screen**:
   - Select **External** (or Internal for Workspace organizations).
   - Fill in App Name, User Support Email, and Developer Contact Email.
   - Under **Scopes**, add the Gmail API scopes (`gmail.readonly`, `gmail.send`, `gmail.modify`).
   - Under **Test Users**, add the Gmail address you will use to log in (if the app status is in *Testing* mode).
5. Go to **APIs & Services > Credentials**:
   - Click **Create Credentials > OAuth client ID**.
   - Application type: **Web application**.
   - **Authorized redirect URIs**:
     ```
     http://localhost:8000/gmail/callback
     ```
     *(Replace `http://localhost:8000` with your production `APP_URL` on live servers).*
6. Copy your **Client ID** and **Client Secret** and paste them into your `.env` file.

---

## ⚙️ Configuration & Environment Variables

Add the following variables to your `.env` file:

```env
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/gmail/callback"

# Optional UI & Routing Customizations:
GMAIL_MAILBOX_PREFIX=gmail
GMAIL_MAILBOX_LAYOUT=layouts.app
GMAIL_MAILBOX_PER_PAGE=15
```

---

## 🔒 Authentication & Middleware Guide

The package is designed to work seamlessly in both **unauthenticated** (standalone/public) and **authenticated** applications.

### Case A: Projects WITHOUT Authentication (Default)
By default, the package uses `['web']` middleware. You do not need Laravel Breeze, Jetstream, or any login system. Users can access the mailbox routes directly:
- `http://localhost:8000/gmail/settings`
- `http://localhost:8000/gmail/inbox`

### Case B: Projects WITH Authentication (Laravel Breeze / Jetstream / Custom Auth)
If your app has user authentication and you want to restrict the mailbox to logged-in users:

1. Publish the config file:
   ```bash
   php artisan vendor:publish --tag=gmail-mailbox-config
   ```
2. Open `config/gmail-mailbox.php` and add `'auth'` to the middleware array:
   ```php
   'middleware' => ['web', 'auth'],
   ```

### Case C: Custom Roles or Guards (e.g., Admin only)
You can use any custom middleware or role guard:
```php
'middleware' => ['web', 'auth:admin', 'role:super-admin'],
```

---

## 🎨 Customizing Layout & Blade Views

### 1. Integrating into Your App's Master Layout
The mailbox views extend the layout defined in `config/gmail-mailbox.php` or `GMAIL_MAILBOX_LAYOUT` in `.env`:

```env
# In your .env
GMAIL_MAILBOX_LAYOUT=layouts.app
```
Or in `config/gmail-mailbox.php`:
```php
'layout' => env('GMAIL_MAILBOX_LAYOUT', 'layouts.app'),
```
*Your master layout should contain `@yield('content')` or standard Laravel content sections.*

### 2. Customizing Blade Templates & UI
To customize the HTML, CSS, or structure of the inbox, email reader, and settings page, publish the views:

```bash
php artisan vendor:publish --tag=gmail-mailbox-views
```
The views will be copied to `resources/views/vendor/gmail-mailbox/`:
- `inbox.blade.php` — Main 2-column email list, search, compose modal, and reader.
- `settings.blade.php` — Connect / Disconnect Google account page.

---

## 🚦 Available Routes

| Method | URI | Route Name | Description |
|---|---|---|---|
| `GET` | `/gmail/inbox` | `gmail.inbox` | Main Gmail Mailbox interface |
| `GET` | `/gmail/settings` | `gmail.settings` | Account management & status screen |
| `GET` | `/gmail/auth` | `gmail.auth` | Redirects to Google OAuth consent page (with account chooser) |
| `GET` | `/gmail/callback` | `gmail.callback` | Google OAuth redirect callback |
| `GET` | `/gmail/switch/{id}` | `gmail.switch` | Switch active Gmail mailbox account |
| `POST`| `/gmail/disconnect/{id?}` | `gmail.disconnect` | Disconnect specific or active Google account |
| `GET` | `/gmail/email/{id}` | `gmail.email.show` | Fetch email details (JSON / View) |
| `POST`| `/gmail/send` | `gmail.send` | Send a new email with attachments |
| `POST`| `/gmail/reply/{id}` | `gmail.reply` | Reply to an existing email thread |
| `POST`| `/gmail/read/{id}` | `gmail.read` | Mark an email as read |
| `GET` | `/gmail/unread-count` | `gmail.unread.count` | Returns JSON with unread email count |
| `GET` | `/gmail/attachment/{msgId}/{attId}` | `gmail.attachment.download` | Download attachment file |
| `POST`| `/gmail/favorites/toggle` | `gmail.favorites.toggle` | Toggle favorite / watched contact for notifications |
| `GET` | `/gmail/favorites` | `gmail.favorites.list` | List all active favorite notification contacts |
| `DELETE`| `/gmail/favorites/{id}` | `gmail.favorites.remove` | Remove a watched favorite contact |
| `GET` | `/gmail/notifications/check` | `gmail.notifications.check` | Polling endpoint for favorite mail activity |

*(Note: If you change `GMAIL_MAILBOX_PREFIX=mail` in `.env`, the routes will be `/mail/inbox`, `/mail/settings`, etc.)*

---

## 💻 Programmatic Usage (`GmailService`)

You can inject or resolve `Queen\GmailMailbox\Services\GmailService` to perform email and multi-account operations anywhere in your application:

```php
use Queen\GmailMailbox\Services\GmailService;

class MailboxController extends Controller
{
    public function index(GmailService $gmailService)
    {
        // 1. Check if Gmail is connected
        if (!$gmailService->isAuthenticated()) {
            return redirect()->route('gmail.auth');
        }

        // 2. Get active and connected accounts
        $activeAccount = $gmailService->getActiveAccount(); // GoogleToken model
        $allAccounts   = $gmailService->getAllAccounts();

        // 3. Switch active account programmatically
        $gmailService->switchAccount($accountId);

        // 4. Send an email from active account
        $result = $gmailService->sendEmail(
            to: 'client@example.com',
            subject: 'Project Update Notification',
            body: '<h2>Hello!</h2><p>Your project update is ready for review.</p>',
            attachments: [
                ['path' => storage_path('app/invoice.pdf'), 'name' => 'Invoice.pdf']
            ]
        );

        return response()->json(['success' => true, 'message' => 'Email sent!']);
    }
}
```

---

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).
