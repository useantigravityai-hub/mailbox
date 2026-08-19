<?php

namespace Queen\GmailMailbox\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Queen\GmailMailbox\Services\GmailService;
use Queen\GmailMailbox\Models\GoogleToken;

class GmailSettingController extends Controller
{
    protected GmailService $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Show Gmail API settings page
     */
    public function index()
    {
        $isConnected = $this->gmailService->isAuthenticated();
        $token = GoogleToken::first();

        return view('gmail-mailbox::settings', [
            'isConnected' => $isConnected,
            'token'       => $token,
        ]);
    }

    /**
     * Redirect to Google OAuth Consent Page
     */
    public function auth()
    {
        return redirect($this->gmailService->getAuthUrl());
    }

    /**
     * OAuth Redirect Callback Handler
     */
    public function callback(Request $request)
    {
        if ($code = $request->query('code')) {
            $success = $this->gmailService->handleCallback($code);
            if ($success) {
                return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.inbox')
                    ->with('success', 'Google account connected successfully!');
            }
        }

        return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.settings')
            ->with('error', 'Failed to connect Google account.');
    }

    /**
     * Disconnect Google account and remove tokens
     */
    public function disconnect()
    {
        $this->gmailService->disconnect();

        return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.settings')
            ->with('success', 'Google account disconnected successfully.');
    }
}
