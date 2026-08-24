<?php

namespace Queen\GmailMailbox\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Queen\GmailMailbox\Services\GmailService;
use Illuminate\Support\Facades\Log;

class GmailMailboxController extends Controller
{
    protected GmailService $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Display the Inbox view
     */
    public function inbox(Request $request)
    {
        if (!$this->gmailService->isAuthenticated()) {
            return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.auth');
        }

        $search = $request->query('search');
        $tab = $request->query('tab', 'received');
        $inboxToken = $request->query('pageToken_inbox');
        $sentToken = $request->query('pageToken_sent');

        try {
            $inboxData = $this->gmailService->getInboxMessages($search, $inboxToken);
            $sentData = $this->gmailService->getSentMessages($search, $sentToken);

            $viewData = [
                'inboxEmails'        => $inboxData['emails'],
                'sentEmails'         => $sentData['emails'],
                'inboxNextPageToken' => $inboxData['nextPageToken'],
                'sentNextPageToken'  => $sentData['nextPageToken'],
                'inboxPageToken'     => $inboxToken,
                'sentPageToken'      => $sentToken,
                'tab'                => $tab,
                'search'             => $search,
                'activeAccount'      => $this->gmailService->getActiveAccount(),
                'connectedAccounts'  => $this->gmailService->getAllAccounts(),
                'favoriteEmails'     => $this->gmailService->getFavoriteEmails(),
                'favoritesList'      => $this->gmailService->getFavoritesList(),
            ];

            if ($request->ajax()) {
                return view('gmail-mailbox::partials.inbox_list', $viewData);
            }

            return view('gmail-mailbox::inbox', $viewData);
        } catch (\Exception $e) {
            Log::error('Gmail Mailbox Load Error: ' . $e->getMessage());
            return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.auth')
                ->with('error', 'Session expired or error loading account. Please reconnect.');
        }
    }

    /**
     * Switch active email account
     */
    public function switchAccount(Request $request, $id)
    {
        $switched = $this->gmailService->switchAccount((int) $id);

        if ($switched) {
            return redirect()->route(config('gmail-mailbox.route_prefix', 'gmail') . '.inbox')
                ->with('success', 'Switched account successfully.');
        }

        return redirect()->back()->with('error', 'Account not found.');
    }

    /**
     * Fetch single email detail (for AJAX pane or standalone view)
     */
    public function showEmail(string $id, Request $request)
    {
        try {
            $email = $this->gmailService->getMessage($id);

            // Mark as read in background
            if ($email['is_unread']) {
                try {
                    $this->gmailService->markAsRead($id);
                } catch (\Exception $e) {
                    // Ignore mark as read fail
                }
            }

            if ($request->ajax()) {
                return view('gmail-mailbox::partials.email_detail', ['email' => $email]);
            }

            return response()->json(['status' => true, 'email' => $email]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Send new email
     */
    public function sendMail(Request $request)
    {
        $request->validate([
            'to'      => 'required|string',
            'subject' => 'required|string',
            'body'    => 'required|string',
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = [
                    'path' => $file->getRealPath(),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
        }

        try {
            $this->gmailService->sendEmail(
                $request->input('to'),
                $request->input('subject'),
                $request->input('body'),
                $attachments,
                $request->input('cc'),
                $request->input('bcc')
            );

            return response()->json(['status' => true, 'message' => 'Email sent successfully!']);
        } catch (\Exception $e) {
            Log::error('Gmail Send Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reply to an email thread
     */
    public function reply(string $id, Request $request)
    {
        $request->validate([
            'to'       => 'required|string',
            'subject'  => 'required|string',
            'body'     => 'required|string',
            'threadId' => 'required|string',
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = [
                    'path' => $file->getRealPath(),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
        }

        try {
            $this->gmailService->replyEmail(
                $request->input('to'),
                $request->input('subject'),
                $request->input('body'),
                $request->input('threadId'),
                $id,
                $attachments
            );

            return response()->json(['status' => true, 'message' => 'Reply sent successfully!']);
        } catch (\Exception $e) {
            Log::error('Gmail Reply Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Download or view attachment
     */
    public function downloadAttachment(string $messageId, string $attachmentId, Request $request)
    {
        try {
            $data = $this->gmailService->getAttachment($messageId, $attachmentId);
            $disposition = $request->query('action') === 'view' ? 'inline' : 'attachment';
            $filename = $request->query('filename', 'attachment');
            $mime = $request->query('mime', 'application/octet-stream');

            return response($data)
                ->header('Content-Type', $mime)
                ->header('Content-Disposition', "{$disposition}; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            return response('Attachment not found', 404);
        }
    }

    /**
     * Mark single email as read
     */
    public function markAsRead(string $id)
    {
        try {
            $this->gmailService->markAsRead($id);
            return response()->json(['status' => true]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle favorite notification for an email address
     */
    public function toggleFavorite(Request $request)
    {
        $email = $request->input('email');
        $name = $request->input('name');
        $notifyIncoming = $request->boolean('notify_incoming', true);
        $notifyOutgoing = $request->boolean('notify_outgoing', true);

        if (!$email) {
            return response()->json(['status' => false, 'message' => 'Email address is required.'], 422);
        }

        $result = $this->gmailService->toggleFavorite($email, $name, $notifyIncoming, $notifyOutgoing);
        return response()->json($result);
    }

    /**
     * Get list of all favorite notification contacts
     */
    public function getFavoritesList()
    {
        try {
            $favorites = $this->gmailService->getFavoritesList();
            return response()->json(['status' => true, 'favorites' => $favorites]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'favorites' => []], 500);
        }
    }

    /**
     * Remove a favorite contact by ID
     */
    public function removeFavorite($id)
    {
        $deleted = $this->gmailService->removeFavorite((int) $id);
        return response()->json(['status' => $deleted, 'message' => $deleted ? 'Removed from favorites' : 'Not found']);
    }

    /**
     * AJAX polling endpoint to check for favorite contact email activity
     */
    public function checkNotifications()
    {
        try {
            $notifications = $this->gmailService->checkFavoriteNotifications();
            return response()->json([
                'status'        => true,
                'count'         => count($notifications),
                'notifications' => $notifications,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'count' => 0, 'notifications' => []]);
        }
    }
}
