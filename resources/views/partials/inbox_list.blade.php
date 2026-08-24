<style>
    @keyframes inboxBadgePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.15); }
    }
    .received-unread-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        border-radius: 10px;
        background: #ea4335;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 0 6px;
        animation: inboxBadgePulse 1.8s ease-in-out infinite;
    }
    .count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        border-radius: 10px;
        background: #e8eaed;
        color: #5f6368;
        font-size: 11px;
        font-weight: 600;
        padding: 0 6px;
    }
</style>

<!-- Navigation Tabs -->
<ul class="nav nav-pills nav-fill mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link {{ ($tab ?? 'received') === 'received' ? 'active' : '' }} d-flex align-items-center justify-content-between py-2 px-3"
            onclick="switchTabAjax('received')" type="button">
            <span class="d-flex align-items-center gap-1">
                <i class="mdi mdi-inbox-arrow-down fs-5"></i> Inbox
            </span>
            @php
                $unreadCount = collect($inboxEmails ?? [])->where('is_unread', true)->count();
            @endphp
            @if($unreadCount > 0)
                <span class="received-unread-badge">{{ $unreadCount }}</span>
            @else
                <span class="count-badge">{{ count($inboxEmails ?? []) }}</span>
            @endif
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ ($tab ?? '') === 'sent' ? 'active' : '' }} d-flex align-items-center justify-content-between py-2 px-3"
            onclick="switchTabAjax('sent')" type="button">
            <span class="d-flex align-items-center gap-1">
                <i class="mdi mdi-send fs-5"></i> Sent
            </span>
            <span class="count-badge">{{ count($sentEmails ?? []) }}</span>
        </button>
    </li>
</ul>

<!-- Emails List Items -->
<div class="scroll-y" style="max-height: 570px;">
    @php
        $emailsToDisplay = ($tab ?? 'received') === 'sent' ? ($sentEmails ?? []) : ($inboxEmails ?? []);
        $colors = ['#1abc9c', '#3498db', '#9b59b6', '#34495e', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6'];
    @endphp

    @forelse($emailsToDisplay as $email)
        @php
            $rawName = ($tab ?? 'received') === 'sent' ? ($email['to'] ?? 'U') : ($email['from'] ?? 'U');
            $cleanName = trim(preg_replace('/<.*?>/', '', $rawName));
            if (!$cleanName) $cleanName = 'U';
            $firstLetter = strtoupper(substr($cleanName, 0, 1));
            $bgColor = $colors[ord($firstLetter) % count($colors)];

            // Extract email address for favorite matching
            preg_match('/<([^>]+)>/', $rawName, $matches);
            $extractedEmail = strtolower(trim($matches[1] ?? (filter_var($rawName, FILTER_VALIDATE_EMAIL) ? $rawName : '')));
            $isFavorite = !empty($extractedEmail) && in_array($extractedEmail, $favoriteEmails ?? []);
        @endphp

        <div class="p-2 border-bottom mail-row rounded mb-1 {{ $isFavorite ? 'border-start border-3 border-warning' : '' }}" id="mail-row-{{ $email['id'] }}" onclick="showEmail('{{ $email['id'] }}')">
            <div class="d-flex align-items-start gap-2">
                <!-- Star Favorite Button -->
                <button type="button" class="btn btn-link p-0 text-decoration-none border-0 flex-shrink-0 mt-1" 
                        onclick="event.stopPropagation(); toggleFavoriteContact('{{ $extractedEmail }}', '{{ addslashes($cleanName) }}', '{{ $email['id'] }}')" 
                        title="{{ $isFavorite ? 'Remove from favorite notifications' : 'Add to favorite notifications' }}">
                    <i class="mdi {{ $isFavorite ? 'mdi-star text-warning' : 'mdi-star-outline text-muted' }} fs-5" id="fav-star-{{ $email['id'] }}"></i>
                </button>

                <!-- Avatar -->
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm flex-shrink-0"
                    style="width: 34px; height: 34px; background-color: {{ $bgColor }}; font-size: 14px;">
                    {{ $firstLetter }}
                </div>

                <!-- Text info -->
                <div class="flex-grow-1 min-w-0" style="overflow: hidden;">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <span class="text-truncate text-dark {{ !empty($email['is_unread']) ? 'fw-bold' : 'fw-medium' }}" 
                              style="max-width: 155px; font-size: 13px;" id="mail-from-{{ $email['id'] }}">
                            {{ $cleanName }}
                            @if($isFavorite)
                                <i class="mdi mdi-bell-ring-outline text-warning ms-1" style="font-size: 12px;" title="Watched Favorite Contact"></i>
                            @endif
                        </span>
                        <small class="text-muted" style="font-size: 11px;">
                            {{ !empty($email['date']) ? date('M d', strtotime($email['date'])) : '' }}
                        </small>
                    </div>

                    <div class="text-truncate text-dark {{ !empty($email['is_unread']) ? 'fw-bold' : '' }}" 
                         style="font-size: 13px;" id="mail-subject-{{ $email['id'] }}">
                        {{ $email['subject'] ?: '(No Subject)' }}
                    </div>

                    <div class="text-truncate text-muted" style="font-size: 12px;">
                        {{ $email['snippet'] ?? '' }}
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-5">
            <i class="mdi mdi-email-off-outline display-4 opacity-25"></i>
            <p class="mt-2 fs-6">No emails found</p>
        </div>
    @endforelse
</div>
