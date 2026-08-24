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
        $accounts = $this->gmailService->getAllAccounts();
        $activeAccount = $this->gmailService->getActiveAccount();

        return view('gmail-mailbox::settings', [
            'isConnected'   => $isConnected,
            'accounts'      => $accounts,
            'activeAccount' => $activeAccount,
        ]);
    }

    /**
     * Redirect to Google OAuth Consent Page (supports adding accounts)
     */
    public function auth(Request $request)
    {
        $forceSelect = $request->query('add', true);
        return redirect($this->gmailService->getAuthUrl((bool) $forceSelect));
    }

    /**
     * OAuth Redirect Callback Handler
     */
    public function callback(Request $request)
    {
        if ($code = $request->query('code')) {
            $tokenModel = $this->gmailService->handleCallback($code);
            if ($tokenModel) {
                $email = $tokenModel->email ?: 'Google';
                return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.inbox')
                    ->with('success', "Account {$email} connected successfully!");
            }
        }

        return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.settings')
            ->with('error', 'Failed to connect Google account.');
    }

    /**
     * Disconnect Google account and remove tokens (specific or active)
     */
    public function disconnect(Request $request, ?int $id = null)
    {
        $this->gmailService->disconnect($id);

        return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.settings')
            ->with('success', 'Google account disconnected successfully.');
    }

    /**
     * Switch active email account
     */
    public function switchAccount(Request $request, $id)
    {
        $switched = $this->gmailService->switchAccount((int) $id);

        if ($switched) {
            return redirect()->back()->with('success', 'Switched account successfully.');
        }

        return redirect()->back()->with('error', 'Account not found.');
    }
}
