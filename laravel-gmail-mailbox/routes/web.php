<?php

use Illuminate\Support\Facades\Route;
use Queen\GmailMailbox\Http\Controllers\GmailMailboxController;
use Queen\GmailMailbox\Http\Controllers\GmailSettingController;

$prefix = config('gmail-mailbox.route_prefix', 'gmail');
$middleware = config('gmail-mailbox.middleware', ['web', 'auth']);

Route::group(['prefix' => $prefix, 'middleware' => $middleware, 'as' => $prefix . '.'], function () {
    // OAuth and Authentication
    Route::get('/auth', [GmailSettingController::class, 'auth'])->name('auth');
    Route::get('/callback', [GmailSettingController::class, 'callback'])->name('callback');
    Route::get('/settings', [GmailSettingController::class, 'index'])->name('settings');
    Route::post('/disconnect', [GmailSettingController::class, 'disconnect'])->name('disconnect');

    // Mailbox Operations
    Route::get('/inbox', [GmailMailboxController::class, 'inbox'])->name('inbox');
    Route::get('/email/{id}', [GmailMailboxController::class, 'showEmail'])->name('email.show');
    Route::post('/send', [GmailMailboxController::class, 'sendMail'])->name('send');
    Route::post('/reply/{id}', [GmailMailboxController::class, 'reply'])->name('reply');
    Route::post('/read/{id}', [GmailMailboxController::class, 'markAsRead'])->name('read');
    Route::get('/unread-count', [GmailMailboxController::class, 'getUnreadCount'])->name('unread.count');
    Route::get('/attachment/{messageId}/{attachmentId}', [GmailMailboxController::class, 'downloadAttachment'])->name('attachment.download');
});
