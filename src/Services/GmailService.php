<?php

namespace Queen\GmailMailbox\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Queen\GmailMailbox\Models\GoogleToken;
use Illuminate\Support\Facades\Log;

class GmailService
{
    /**
     * @var Client|null
     */
    protected ?Client $client = null;

    /**
     * Get or initialize configured Google Client
     */
    public function getClient(): Client
    {
        if ($this->client) {
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
        ]));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $tokenModel = GoogleToken::first();
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
                }
            }
        }

        $this->client = $client;
        return $this->client;
    }

    /**
     * Check if currently authenticated
     */
    public function isAuthenticated(): bool
    {
        $client = $this->getClient();
        return (bool) $client->getAccessToken() && !$client->isAccessTokenExpired();
    }

    /**
     * Get Google OAuth login URL
     */
    public function getAuthUrl(): string
    {
        return $this->getClient()->createAuthUrl();
    }

    /**
     * Exchange auth code for token
     */
    public function handleCallback(string $code): bool
    {
        $client = $this->getClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (!isset($token['error'])) {
            GoogleToken::truncate();
            GoogleToken::create([
                'token' => $token,
            ]);
            return true;
        }

        Log::error('Gmail OAuth Callback Error: ' . json_encode($token));
        return false;
    }

    /**
     * Disconnect / clear stored tokens
     */
    public function disconnect(): void
    {
        $client = $this->getClient();
        if ($token = $client->getAccessToken()) {
            try {
                $client->revokeToken($token);
            } catch (\Exception $e) {
                // Ignore token revocation failure on Google's side
            }
        }
        GoogleToken::truncate();
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
}
