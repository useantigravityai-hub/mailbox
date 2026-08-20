<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google API Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are used to authenticate with Google APIs for Gmail.
    | You can obtain them from the Google Cloud Console.
    |
    */
    'client_id'     => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri'  => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/gmail/callback'),

    /*
    |--------------------------------------------------------------------------
    | Routing & Middleware
    |--------------------------------------------------------------------------
    |
    | Define the base URL prefix and middleware stack for all package routes.
    | To protect routes with authentication, add 'auth' to the array (e.g. ['web', 'auth']).
    |
    */
    'route_prefix' => env('GMAIL_MAILBOX_PREFIX', 'gmail'),
    'middleware'   => ['web'],

    /*
    |--------------------------------------------------------------------------
    | View & Layout Configuration
    |--------------------------------------------------------------------------
    |
    | The master blade layout that the inbox and setting views should extend.
    | Common values: 'layouts.layoutMaster', 'layouts.app', 'layouts.admin'
    |
    */
    'layout' => env('GMAIL_MAILBOX_LAYOUT', 'layouts/layoutMaster'),

    /*
    |--------------------------------------------------------------------------
    | Pagination & List Settings
    |--------------------------------------------------------------------------
    |
    | Number of emails to retrieve and display per page.
    |
    */
    'per_page' => env('GMAIL_MAILBOX_PER_PAGE', 15),

    /*
    |--------------------------------------------------------------------------
    | Google API Scopes
    |--------------------------------------------------------------------------
    |
    | Scopes required for the Gmail Mailbox operations.
    |
    */
    'scopes' => [
        \Google\Service\Gmail::GMAIL_READONLY,
        \Google\Service\Gmail::GMAIL_SEND,
        \Google\Service\Gmail::GMAIL_MODIFY,
    ],
];
