@php
    $rawFrom = $email['from'] ?? 'U';
    $cleanName = trim(preg_replace('/<.*?>/', '', $rawFrom));
    if (!$cleanName) $cleanName = 'U';
    $firstLetter = strtoupper(substr($cleanName, 0, 1));
    $colors = ['#1abc9c', '#3498db', '#9b59b6', '#34495e', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6'];
    $bgColor = $colors[ord($firstLetter) % count($colors)];
    $routePrefix = config('gmail-mailbox.route_prefix', 'gmail');
@endphp

<!-- Email Header -->
<div class="border-bottom pb-3 mb-3">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h5 class="text-dark fw-bold mb-0">{{ $email['subject'] ?: '(No Subject)' }}</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1"
                onclick="openMailModal('{{ addslashes($email['from']) }}', '{{ addslashes('Re: ' . preg_replace('/^Re:\s*/i', '', $email['subject'])) }}', '{{ $email['id'] }}', '{{ $email['threadId'] ?? '' }}')">
                <i class="mdi mdi-reply"></i>
                <span>Reply</span>
            </button>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                style="width: 44px; height: 44px; background-color: {{ $bgColor }}; font-size: 18px;">
                {{ $firstLetter }}
            </div>
            <div>
                <div class="fw-bold text-dark fs-6">{{ $email['from'] }}</div>
                <small class="text-muted">To: {{ $email['to'] }}</small>
            </div>
        </div>
        <small class="text-muted fw-medium">
            {{ !empty($email['date']) ? date('M d, Y, g:i A', strtotime($email['date'])) : '' }}
        </small>
    </div>
</div>

<!-- Email Body -->
<div class="scroll-y px-2" style="max-height: 440px;">
    <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #202124; word-break: break-word;">
        @if(!empty($email['body']))
            {!! $email['body'] !!}
        @else
            {!! nl2br(e($email['snippet'] ?? '')) !!}
        @endif
    </div>

    <!-- Attachments Section -->
    @if(!empty($email['attachments']))
        <hr class="my-4">
        <h6 class="fw-bold text-dark mb-3">
            <i class="mdi mdi-paperclip me-1"></i> Attachments ({{ count($email['attachments']) }})
        </h6>
        <div class="d-flex flex-wrap gap-2 pb-3">
            @foreach($email['attachments'] as $att)
                @php
                    $sizeStr = $att['size'] > 1048576
                        ? round($att['size'] / 1048576, 2) . ' MB'
                        : round($att['size'] / 1024, 2) . ' KB';
                    $isImage = str_starts_with($att['mimeType'], 'image/');
                    $icon = $isImage ? 'mdi-image-outline text-success' : 'mdi-file-document-outline text-primary';
                    $downloadUrl = route($routePrefix . '.attachment.download', [
                        'messageId'    => $email['id'],
                        'attachmentId' => $att['attachmentId'],
                        'filename'     => $att['filename'],
                        'mime'         => $att['mimeType'],
                    ]);
                @endphp
                <div class="card shadow-sm border rounded" style="width: 190px;">
                    <div class="bg-light p-3 d-flex align-items-center justify-content-center" style="height: 70px;">
                        <i class="mdi {{ $icon }} fs-1 opacity-75"></i>
                    </div>
                    <div class="p-2 bg-white">
                        <div class="fw-semibold text-truncate text-dark" style="font-size: 12px;" title="{{ $att['filename'] }}">
                            {{ $att['filename'] }}
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-1">
                            <small class="text-muted" style="font-size: 11px;">{{ $sizeStr }}</small>
                            <div class="d-flex gap-1">
                                <a href="{{ $downloadUrl }}&action=view" target="_blank" class="btn btn-xs btn-outline-secondary px-2 py-0" title="View">
                                    <i class="mdi mdi-eye"></i>
                                </a>
                                <a href="{{ $downloadUrl }}&action=download" class="btn btn-xs btn-outline-primary px-2 py-0" title="Download">
                                    <i class="mdi mdi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
