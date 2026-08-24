<?php

namespace Queen\GmailMailbox\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Queen\GmailMailbox\Models\GoogleToken;
use Queen\GmailMailbox\Models\GmailFavorite;
use Illuminate\Support\Facades\Log;

class GmailService
{
    /**
     * @var Client|null
     */
    protected ?Client $client = null;

    /**
     * @var GoogleToken|null
     */
    protected ?GoogleToken $activeAccount = null;

    /**
     * Get or initialize configured Google Client for the active or given account
     */
    public function getClient(?int $accountId = null): Client
    {
        if ($this->client && !$accountId) {
            return $this->client;
        }

        $client = new Client();
        $client->setClientId(config('gmail-mailbox.client_id'));
        $client->setClientSecret(config('gmail-mailbox.client_secret'));
        $client->setRedirectUri(config('gmail-mailbox.redirect_uri'));
        $client->setScopes(config('gmail-mailbox.scopes', [
            Gmail::GMAIL_READONLY,
            Gmail::GMAIL_SEND,
            Gmail::GMAIL_MODIFY,
            \Google\Service\Oauth2::USERINFO_EMAIL,
            \Google\Service\Oauth2::USERINFO_PROFILE,
        ]));
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $tokenModel = $accountId 
            ? GoogleToken::forCurrentUser()->find($accountId)
            : $this->getActiveAccount();

        $token = $tokenModel ? $tokenModel->token : null;

        if ($token) {
            $client->setAccessToken($token);

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    if (!isset($newToken['refresh_token']) && isset($token['refresh_token'])) {
                        $newToken['refresh_token'] = $token['refresh_token'];
                    }
                    $tokenModel->update(['token' => $newToken]);
                    $client->setAccessToken($newToken);
                } else {
                    $tokenModel->delete();
                    if (session('gmail_active_account_id') == $tokenModel->id) {
                        session()->forget('gmail_active_account_id');
                    }
                }
            }
        }

        if (!$accountId) {
            $this->client = $client;
        }

        return $client;
    }

    /**
     * Get the active connected account model
     */
    public function getActiveAccount(): ?GoogleToken
    {
        if ($this->activeAccount) {
            return $this->activeAccount;
        }

        $activeId = session('gmail_active_account_id');

        if ($activeId) {
            $this->activeAccount = GoogleToken::forCurrentUser()->find($activeId);
        }

        if (!$this->activeAccount) {
            $this->activeAccount = GoogleToken::forCurrentUser()->first();
            if ($this->activeAccount) {
                session(['gmail_active_account_id' => $this->activeAccount->id]);
            }
        }

        return $this->activeAccount;
    }

    /**
     * Get all connected Google accounts for current context
     */
    public function getAllAccounts()
    {
        return GoogleToken::forCurrentUser()->orderBy('id', 'asc')->get();
    }

    /**
     * Switch the active account
     */
    public function switchAccount(int $id): bool
    {
        $account = GoogleToken::forCurrentUser()->find($id);
        if ($account) {
            session(['gmail_active_account_id' => $account->id]);
            $this->activeAccount = $account;
            $this->client = null;
            return true;
        }
        return false;
    }

    /**
     * Check if currently authenticated
     */
    public function isAuthenticated(): bool
    {
        $account = $this->getActiveAccount();
        if (!$account) {
            return false;
        }
        $client = $this->getClient();
        return (bool) $client->getAccessToken() && !$client->isAccessTokenExpired();
    }

    /**
     * Get Google OAuth login URL
     */
    public function getAuthUrl(bool $forceSelectAccount = true): string
    {
        $client = new Client();
        $client->setClientId(config('gmail-mailbox.client_id'));
        $client->setClientSecret(config('gmail-mailbox.client_secret'));
        $client->setRedirectUri(config('gmail-mailbox.redirect_uri'));
        $client->setScopes(config('gmail-mailbox.scopes', [
            Gmail::GMAIL_READONLY,
            Gmail::GMAIL_SEND,
            Gmail::GMAIL_MODIFY,
            \Google\Service\Oauth2::USERINFO_EMAIL,
            \Google\Service\Oauth2::USERINFO_PROFILE,
        ]));
        $client->setAccessType('offline');
        $client->setPrompt($forceSelectAccount ? 'select_account consent' : 'consent');

        return $client->createAuthUrl();
    }

    /**
     * Exchange auth code for token and save user profile info
     */
    public function handleCallback(string $code): ?GoogleToken
    {
        $client = $this->getClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            Log::error('Gmail OAuth Callback Error: ' . json_encode($token));
            return null;
        }

        $client->setAccessToken($token);

        // Fetch user profile info
        $email = null;
        $name = null;
        $avatar = null;

        try {
            $oauth2 = new \Google\Service\Oauth2($client);
            $userInfo = $oauth2->userinfo->get();
            $email = $userInfo->getEmail();
            $name = $userInfo->getName();
            $avatar = $userInfo->getPicture();
        } catch (\Exception $e) {
            Log::warning('Could not fetch userinfo from Oauth2: ' . $e->getMessage());
        }

        if (!$email) {
            try {
                $gmail = new Gmail($client);
                $profile = $gmail->users->getProfile('me');
                $email = $profile->getEmailAddress();
            } catch (\Exception $e) {
                Log::warning('Could not fetch email from Gmail profile: ' . $e->getMessage());
            }
        }

        $userId = auth()->check() ? auth()->id() : null;

        $tokenModel = GoogleToken::updateOrCreate(
            [
                'user_id' => $userId,
                'email'   => $email,
            ],
            [
                'name'   => $name,
                'avatar' => $avatar,
                'token'  => $token,
            ]
        );

        // Set as active account in session
        session(['gmail_active_account_id' => $tokenModel->id]);
        $this->activeAccount = $tokenModel;
        $this->client = null;

        return $tokenModel;
    }

    /**
     * Disconnect / clear stored tokens for specific account or active account
     */
    public function disconnect(?int $accountId = null): void
    {
        $account = $accountId 
            ? GoogleToken::forCurrentUser()->find($accountId)
            : $this->getActiveAccount();

        if ($account) {
            try {
                $client = $this->getClient($account->id);
                if ($token = $client->getAccessToken()) {
                    $client->revokeToken($token);
                }
            } catch (\Exception $e) {
                // Ignore token revocation failure
            }

            $deletedId = $account->id;
            $account->delete();

            if (session('gmail_active_account_id') == $deletedId) {
                session()->forget('gmail_active_account_id');
                $nextAccount = GoogleToken::forCurrentUser()->first();
                if ($nextAccount) {
                    session(['gmail_active_account_id' => $nextAccount->id]);
                }
            }
        }

        $this->activeAccount = null;
        $this->client = null;
    }

    /**
     * List Inbox Messages
     */
    public function getInboxMessages(?string $search = null, ?string $pageToken = null, ?int $perPage = null): array
    {
        $service = new Gmail($this->getClient());
        $perPage = $perPage ?: config('gmail-mailbox.per_page', 15);

        $params = [
            'labelIds'   => ['INBOX'],
            'maxResults' => $perPage,
        ];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }
        if ($search) {
            $params['q'] = $search;
        }

        $messagesResponse = $service->users_messages->listUsersMessages('me', $params);
        $emails = [];

        foreach ($messagesResponse->getMessages() ?? [] as $msg) {
            try {
                $full = $service->users_messages->get('me', $msg->getId());
                $emails[] = $this->parseMessage($full);
            } catch (\Exception $e) {
                Log::warning("Failed to fetch message {$msg->getId()}: " . $e->getMessage());
            }
        }

        return [
            'emails'        => $emails,
            'nextPageToken' => $messagesResponse->getNextPageToken(),
        ];
    }

    /**
     * List Sent Messages
     */
    public function getSentMessages(?string $search = null, ?string $pageToken = null, ?int $perPage = null): array
    {
        $service = new Gmail($this->getClient());
        $perPage = $perPage ?: config('gmail-mailbox.per_page', 15);

        $params = [
            'labelIds'   => ['SENT'],
            'maxResults' => $perPage,
        ];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }
        if ($search) {
            $params['q'] = $search;
        }

        $messagesResponse = $service->users_messages->listUsersMessages('me', $params);
        $emails = [];

        foreach ($messagesResponse->getMessages() ?? [] as $msg) {
            try {
                $full = $service->users_messages->get('me', $msg->getId());
                $emails[] = $this->parseMessage($full);
            } catch (\Exception $e) {
                Log::warning("Failed to fetch sent message {$msg->getId()}: " . $e->getMessage());
            }
        }

        return [
            'emails'        => $emails,
            'nextPageToken' => $messagesResponse->getNextPageToken(),
        ];
    }

    /**
     * Get single message details
     */
    public function getMessage(string $id): array
    {
        $service = new Gmail($this->getClient());
        $msg = $service->users_messages->get('me', $id);
        return $this->parseMessage($msg);
    }

    /**
     * Download attachment
     */
    public function getAttachment(string $messageId, string $attachmentId): string
    {
        $service = new Gmail($this->getClient());
        $attachment = $service->users_messages_attachments->get('me', $messageId, $attachmentId);
        $data = $attachment->getData();
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $id): void
    {
        $service = new Gmail($this->getClient());
        $mods = new \Google\Service\Gmail\ModifyMessageRequest();
        $mods->setRemoveLabelIds(['UNREAD']);
        $service->users_messages->modify('me', $id, $mods);
    }

    /**
     * Get count of unread messages in inbox
     */
    public function getUnreadCount(): int
    {
        $service = new Gmail($this->getClient());
        $res = $service->users_messages->listUsersMessages('me', [
            'labelIds'   => ['INBOX', 'UNREAD'],
            'maxResults' => 50,
        ]);
        return count($res->getMessages() ?? []);
    }

    /**
     * Send new email
     */
    public function sendEmail(string $to, string $subject, string $body, array $attachments = [], ?string $cc = null, ?string $bcc = null): Message
    {
        $rawMessage = $this->buildRawMimeMessage($to, $subject, $body, $attachments, $cc, $bcc);
        $message = new Message();
        $message->setRaw($rawMessage);

        $service = new Gmail($this->getClient());
        return $service->users_messages->send('me', $message);
    }

    /**
     * Reply to an email
     */
    public function replyEmail(string $to, string $subject, string $body, string $threadId, string $messageId, array $attachments = []): Message
    {
        $service = new Gmail($this->getClient());
        $parent = $service->users_messages->get('me', $messageId);
        $parentHeaders = $parent->getPayload()->getHeaders();

        $origMessageId = collect($parentHeaders)->firstWhere('name', 'Message-ID')['value'] ?? '';
        $references = collect($parentHeaders)->firstWhere('name', 'References')['value'] ?? '';
        $allReferences = trim($references . ' ' . $origMessageId);

        $extraHeaders = [
            'In-Reply-To' => $origMessageId,
            'References'  => $allReferences,
        ];

        $rawMessage = $this->buildRawMimeMessage($to, $subject, $body, $attachments, null, null, $extraHeaders);
        $message = new Message();
        $message->setRaw($rawMessage);
        $message->setThreadId($threadId);

        return $service->users_messages->send('me', $message);
    }

    /**
     * Parse full Gmail message object into standard array
     */
    public function parseMessage($msg): array
    {
        $headers = $msg->getPayload()->getHeaders();
        $attachments = $this->getAttachments($msg->getPayload());
        $body = $this->getBody($msg->getPayload());

        $body = $this->replaceInlineImages($body, $attachments, $msg->getId());

        return [
            'id'          => $msg->getId(),
            'threadId'    => $msg->getThreadId(),
            'from'        => $this->sanitizeUtf8(collect($headers)->firstWhere('name', 'From')['value'] ?? ''),
            'to'          => $this->sanitizeUtf8(collect($headers)->firstWhere('name', 'To')['value'] ?? ''),
            'subject'     => $this->sanitizeUtf8(collect($headers)->firstWhere('name', 'Subject')['value'] ?? ''),
            'date'        => collect($headers)->firstWhere('name', 'Date')['value'] ?? '',
            'snippet'     => $this->sanitizeUtf8($msg->getSnippet()),
            'body'        => $this->sanitizeUtf8($body),
            'attachments' => $attachments,
            'is_unread'   => in_array('UNREAD', $msg->getLabelIds() ?? []),
        ];
    }

    /**
     * Helper: Extract message body
     */
    protected function getBody($payload): string
    {
        $body = '';

        if ($payload->getParts()) {
            foreach ($payload->getParts() as $part) {
                $mime = $part->getMimeType();

                if (!str_starts_with($mime, 'text/')) {
                    if ($part->getParts()) {
                        $nestedBody = $this->getBody($part);
                        if ($nestedBody) {
                            return $nestedBody;
                        }
                    }
                    continue;
                }

                $data = $part->getBody()->getData();

                if ($mime === 'text/html') {
                    return $this->decodeAndClean($data);
                }

                if ($mime === 'text/plain' && !$body) {
                    $body = $this->decodeAndClean($data);
                }

                if ($part->getParts()) {
                    $nestedBody = $this->getBody($part);
                    if ($nestedBody) {
                        return $nestedBody;
                    }
                }
            }
        } else {
            $data = $payload->getBody()->getData();
            if ($data) {
                return $this->decodeAndClean($data);
            }
        }

        return $body;
    }

    /**
     * Helper: Extract attachments metadata
     */
    protected function getAttachments($payload): array
    {
        $attachments = [];

        if ($payload->getParts()) {
            foreach ($payload->getParts() as $part) {
                if ($part->getFilename() && $part->getBody()->getAttachmentId()) {
                    $cid = null;
                    if ($part->getHeaders()) {
                        foreach ($part->getHeaders() as $h) {
                            if (strtolower($h->getName()) === 'content-id') {
                                $cid = trim($h->getValue(), '<>');
                            }
                        }
                    }

                    $attachments[] = [
                        'filename'     => $part->getFilename(),
                        'mimeType'     => $part->getMimeType(),
                        'size'         => $part->getBody()->getSize(),
                        'attachmentId' => $part->getBody()->getAttachmentId(),
                        'cid'          => $cid,
                    ];
                }

                if ($part->getParts()) {
                    $attachments = array_merge($attachments, $this->getAttachments($part));
                }
            }
        }

        return $attachments;
    }

    /**
     * Helper: Replace CID images with direct download route URLs
     */
    protected function replaceInlineImages(string $body, array $attachments, string $messageId): string
    {
        $routePrefix = config('gmail-mailbox.route_prefix', 'gmail');

        foreach ($attachments as $att) {
            if (!empty($att['cid'])) {
                $url = route($routePrefix . '.attachment.download', [
                    'messageId'    => $messageId,
                    'attachmentId' => $att['attachmentId'],
                    'filename'     => $att['filename'],
                    'mime'         => $att['mimeType'],
                    'action'       => 'view',
                ]);
                $body = str_replace('cid:' . $att['cid'], $url, $body);
            }
        }

        return $body;
    }

    /**
     * Helper: Decode base64url and clean UTF-8
     */
    protected function decodeAndClean(?string $data): string
    {
        if (!$data) return '';
        $data = strtr($data, '-_', '+/');
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($data);
        return $this->sanitizeUtf8($decoded);
    }

    /**
     * Helper: Ensure string or array has clean UTF-8 encoding
     */
    protected function sanitizeUtf8($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->sanitizeUtf8($v);
            }
            return $data;
        }
        if (!is_string($data)) return $data;
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }

    /**
     * Helper: Build RFC 2822 MIME raw message
     */
    protected function buildRawMimeMessage(string $to, string $subject, string $body, array $attachments = [], ?string $cc = null, ?string $bcc = null, array $extraHeaders = []): string
    {
        $boundary = '=====' . md5(time()) . '=====';
        $subBoundary = 'sub_' . md5(time());

        $headers = [];
        $headers[] = "To: {$to}";
        if ($cc)  $headers[] = "Cc: {$cc}";
        if ($bcc) $headers[] = "Bcc: {$bcc}";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";

        foreach ($extraHeaders as $name => $val) {
            $headers[] = "{$name}: {$val}";
        }

        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

        $mime = implode("\r\n", $headers) . "\r\n\r\n";
        $mime .= "--{$boundary}\r\n";
        $mime .= "Content-Type: multipart/alternative; boundary=\"{$subBoundary}\"\r\n\r\n";

        // Plain text fallback
        $plainText = strip_tags($body);
        $mime .= "--{$subBoundary}\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($plainText)) . "\r\n";

        // HTML part
        $mime .= "--{$subBoundary}\r\n";
        $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($body)) . "\r\n";
        $mime .= "--{$subBoundary}--\r\n";

        // Attachments
        foreach ($attachments as $file) {
            if (!file_exists($file['path'])) continue;

            $fileData = file_get_contents($file['path']);
            $fileName = $file['name'] ?? basename($file['path']);
            $mimeType = $file['mime'] ?? mime_content_type($file['path']) ?: 'application/octet-stream';

            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $mime .= chunk_split(base64_encode($fileData)) . "\r\n";
        }

        $mime .= "--{$boundary}--";

        return rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
    }

    /**
     * Get list of favorited email addresses for active account
     */
    public function getFavoriteEmails(): array
    {
        $account = $this->getActiveAccount();
        if (!$account) {
            return [];
        }

        return GmailFavorite::forCurrentUser()
            ->forAccount($account->id)
            ->pluck('email')
            ->map(fn($e) => strtolower(trim($e)))
            ->toArray();
    }

    /**
     * Get list of all favorite models for active account
     */
    public function getFavoritesList()
    {
        $account = $this->getActiveAccount();
        if (!$account) {
            return collect([]);
        }

        return GmailFavorite::forCurrentUser()
            ->forAccount($account->id)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Toggle favorite notification for an email address
     */
    public function toggleFavorite(string $email, ?string $name = null, bool $notifyIncoming = true, bool $notifyOutgoing = true): array
    {
        $account = $this->getActiveAccount();
        if (!$account) {
            return ['status' => false, 'message' => 'No active Google account.'];
        }

        $email = strtolower(trim($email));
        $userId = auth()->check() ? auth()->id() : null;

        $existing = GmailFavorite::forCurrentUser()
            ->forAccount($account->id)
            ->where('email', $email)
            ->first();

        if ($existing) {
            $existing->delete();
            return [
                'status'    => true,
                'action'    => 'removed',
                'is_favorite' => false,
                'email'     => $email,
                'message'   => "{$email} removed from favorite notifications.",
            ];
        }

        $favorite = GmailFavorite::create([
            'user_id'          => $userId,
            'account_id'       => $account->id,
            'email'            => $email,
            'name'             => $name ?: explode('@', $email)[0],
            'notify_incoming'  => $notifyIncoming,
            'notify_outgoing'  => $notifyOutgoing,
            'last_notified_at' => now(),
        ]);

        return [
            'status'      => true,
            'action'      => 'added',
            'is_favorite' => true,
            'favorite'    => $favorite,
            'email'       => $email,
            'message'     => "{$email} added to favorite notifications! You will be notified on incoming & outgoing emails.",
        ];
    }

    /**
     * Remove favorite by ID
     */
    public function removeFavorite(int $id): bool
    {
        $account = $this->getActiveAccount();
        if (!$account) {
            return false;
        }

        $favorite = GmailFavorite::forCurrentUser()
            ->forAccount($account->id)
            ->find($id);

        if ($favorite) {
            return (bool) $favorite->delete();
        }

        return false;
    }

    /**
     * Check for new incoming or outgoing emails for favorite contacts
     */
    public function checkFavoriteNotifications(): array
    {
        if (!$this->isAuthenticated()) {
            return [];
        }

        $account = $this->getActiveAccount();
        if (!$account) {
            return [];
        }

        $favorites = GmailFavorite::forCurrentUser()
            ->forAccount($account->id)
            ->get();

        if ($favorites->isEmpty()) {
            return [];
        }

        $service = new Gmail($this->getClient());
        $notifications = [];

        foreach ($favorites as $favorite) {
            $email = $favorite->email;
            $queryParts = [];

            if ($favorite->notify_incoming) {
                $queryParts[] = "from:{$email}";
            }
            if ($favorite->notify_outgoing) {
                $queryParts[] = "to:{$email}";
            }

            if (empty($queryParts)) {
                continue;
            }

            $query = '(' . implode(' OR ', $queryParts) . ') newer_than:2d';

            try {
                $response = $service->users_messages->listUsersMessages('me', [
                    'q'          => $query,
                    'maxResults' => 3,
                ]);

                $messages = $response->getMessages() ?? [];

                foreach ($messages as $msg) {
                    $msgId = $msg->getId();

                    // If this message was already the last notified message, stop
                    if ($favorite->last_message_id === $msgId) {
                        break;
                    }

                    $full = $service->users_messages->get('me', $msgId, ['format' => 'metadata', 'metadataHeaders' => ['From', 'To', 'Subject', 'Date']]);
                    $labels = $full->getLabelIds() ?? [];
                    $headers = $full->getPayload()->getHeaders();

                    $from = collect($headers)->firstWhere('name', 'From')['value'] ?? '';
                    $to = collect($headers)->firstWhere('name', 'To')['value'] ?? '';
                    $subject = collect($headers)->firstWhere('name', 'Subject')['value'] ?? '(No Subject)';
                    $date = collect($headers)->firstWhere('name', 'Date')['value'] ?? '';

                    $isIncoming = !in_array('SENT', $labels);
                    $isUnread = in_array('UNREAD', $labels);

                    // If incoming and unread or outgoing
                    $shouldNotify = ($isIncoming && $favorite->notify_incoming) || (!$isIncoming && $favorite->notify_outgoing);

                    if ($shouldNotify) {
                        $notifications[] = [
                            'id'          => $msgId,
                            'type'        => $isIncoming ? 'incoming' : 'outgoing',
                            'favorite_id' => $favorite->id,
                            'contact'     => $favorite->display_name,
                            'email'       => $email,
                            'from'        => $from,
                            'to'          => $to,
                            'subject'     => $subject,
                            'snippet'     => $full->getSnippet(),
                            'date'        => $date,
                            'is_unread'   => $isUnread,
                        ];
                    }

                    // Update last notified message ID on favorite
                    $favorite->update([
                        'last_message_id'  => $msgId,
                        'last_notified_at' => now(),
                    ]);

                    break; // Notify only the latest new message per favorite per polling cycle
                }
            } catch (\Exception $e) {
                Log::warning("Error checking favorite notification for {$email}: " . $e->getMessage());
            }
        }

        return $notifications;
    }
}

