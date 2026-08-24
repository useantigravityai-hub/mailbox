@extends(config('gmail-mailbox.layout', 'layouts/layoutMaster'))

@section('title', 'Mail Box')

@section('vendor-style')
@parent
<style>
    .spinner {
        width: 38px;
        height: 38px;
        border: 4px solid rgba(0,0,0,0.1);
        border-top: 4px solid #0076b6;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }

    .mail-row {
        transition: background-color 0.15s ease-in-out;
        cursor: pointer;
    }
    .mail-row:hover {
        background-color: #f4f6f9 !important;
    }
    .mail-row.active-mail {
        background-color: #e8f0fe !important;
        border-left: 3px solid #1a73e8;
    }

    .gmail-search-box {
        background-color: #f1f3f4;
        border-radius: 24px;
        padding: 4px 12px;
        display: flex;
        align-items: center;
        border: 1px solid transparent;
        transition: all 0.15s ease-in-out;
    }
    .gmail-search-box:focus-within {
        background-color: #fff;
        border: 1px solid #dcdcdc;
        box-shadow: 0 1px 3px rgba(60, 64, 67, 0.2);
    }
    .gmail-search-box input {
        border: none;
        background: transparent;
        box-shadow: none !important;
        outline: none !important;
        padding-left: 8px;
        width: 100%;
        font-size: 14px;
    }
    .scroll-y {
        overflow-y: auto;
    }
</style>
@endsection

@section('content')
<div class="row g-3">
    <!-- Header -->
    <div class="col-lg-12">
        <div class="card card-action shadow-sm border-0">
            <div class="card-header border-bottom py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="mdi mdi-email-outline text-primary fs-3"></i>
                    <h5 class="card-title mb-0 fw-bold">Mail Box</h5>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <!-- Google Account Switcher -->
                    @include('gmail-mailbox::partials.account_switcher')

                    <!-- Favorite Notifications Trigger -->
                    <button type="button" class="btn btn-sm btn-outline-warning d-flex align-items-center gap-1" onclick="openFavoriteModal()" title="Manage Favorite Notifications">
                        <i class="mdi mdi-star"></i>
                        <span class="d-none d-md-inline">Favorites</span>
                        <span class="badge bg-warning text-dark rounded-pill px-1" id="headerFavBadge" style="font-size: 10px;">{{ count($favoritesList ?? []) }}</span>
                    </button>

                    <a href="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.settings') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" title="Settings">
                        <i class="mdi mdi-cog-outline"></i>
                        <span class="d-none d-md-inline">Settings</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-primary fw-bold d-flex align-items-center gap-1" onclick="openMailModal()">
                        <i class="mdi mdi-plus"></i>
                        <span>Compose</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Left List Card -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0" style="min-height: 720px; max-height: 720px; display: flex; flex-direction: column;">
            <div class="card-body p-3 d-flex flex-column h-100">
                <!-- Search Box -->
                <div class="pb-3">
                    <form method="GET" action="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.inbox') }}" id="gmailSearchForm" onsubmit="event.preventDefault(); fetchSearchResults();">
                        <input type="hidden" name="tab" id="current_tab" value="{{ $tab ?? 'received' }}">
                        <div class="gmail-search-box">
                            <i class="mdi mdi-magnify fs-4 text-muted"></i>
                            <input type="text" name="search" id="gmailSearchInput" placeholder="Search mail..." value="{{ $search ?? '' }}" autocomplete="off">
                            <a href="javascript:;" onclick="clearSearch()" id="clearSearchBtn" class="text-muted text-decoration-none {{ !empty($search) ? '' : 'd-none' }}">
                                <i class="mdi mdi-close fs-5"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Inbox List Partial -->
                <div id="inbox-list-wrapper" class="flex-grow-1 position-relative">
                    @include('gmail-mailbox::partials.inbox_list')
                </div>
            </div>
        </div>
    </div>

    <!-- Right Detail Card -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0" style="min-height: 720px; max-height: 720px;" id="emailDetailPanel">
            <div class="card-body p-4 d-flex align-items-center justify-content-center h-100" id="emailDetailContent">
                <div class="text-center text-muted">
                    <i class="mdi mdi-email-open-outline display-3 opacity-25"></i>
                    <p class="mt-2 fs-6">Select an email to view details</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Modal -->
@include('gmail-mailbox::partials.compose_modal')

<!-- Favorite Notification Modal -->
@include('gmail-mailbox::partials.favorite_modal')

<!-- Live Toast Notifications Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="favoriteToastContainer" style="z-index: 1090;"></div>

@endsection

@section('page-script')
@parent
<script>
    const routePrefix = "{{ config('gmail-mailbox.route_prefix', 'gmail') }}";
    let favoriteEmailsList = @json($favoriteEmails ?? []);

    function showEmail(id) {
        const detailPanel = $('#emailDetailContent');
        
        // Highlight active row
        $('.mail-row').removeClass('active-mail');
        $('#mail-row-' + id).addClass('active-mail');

        // Loading spinner
        detailPanel.html(`
            <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5">
                <div class="spinner mb-3"></div>
                <span class="text-muted fs-6">Loading message...</span>
            </div>
        `);

        // Fetch detail
        $.ajax({
            url: `/${routePrefix}/email/${id}`,
            type: 'GET',
            success: function(response) {
                detailPanel.html(response);
                // Mark unread badge on UI
                $(`#mail-from-${id}`).css('font-weight', '500');
                $(`#mail-subject-${id}`).css('font-weight', '500');
                $(`#mail-badge-${id}`).remove();
            },
            error: function(xhr) {
                detailPanel.html(`
                    <div class="text-center text-danger py-5">
                        <i class="mdi mdi-alert-circle-outline display-4"></i>
                        <p class="mt-2">Failed to load message. Please try again.</p>
                    </div>
                `);
            }
        });
    }

    function switchTabAjax(tab) {
        $('#current_tab').val(tab);
        fetchSearchResults();
    }

    function fetchSearchResults() {
        const tab = $('#current_tab').val();
        const search = $('#gmailSearchInput').val();
        const wrapper = $('#inbox-list-wrapper');

        $('#clearSearchBtn').toggleClass('d-none', !search);

        wrapper.html(`
            <div class="d-flex justify-content-center align-items-center py-5">
                <div class="spinner"></div>
            </div>
        `);

        $.ajax({
            url: `/${routePrefix}/inbox`,
            type: 'GET',
            data: { tab: tab, search: search },
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(html) {
                wrapper.html(html);
            },
            error: function() {
                wrapper.html('<div class="text-center text-danger py-4">Failed to load emails.</div>');
            }
        });
    }

    function clearSearch() {
        $('#gmailSearchInput').val('');
        fetchSearchResults();
    }

    // ==========================================
    // ⭐ FAVORITE MAIL NOTIFICATION SYSTEM
    // ==========================================

    function toggleFavoriteContact(email, name, mailId) {
        if (!email) {
            alert('Could not determine contact email address.');
            return;
        }

        $.ajax({
            url: `/${routePrefix}/favorites/toggle`,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                email: email,
                name: name,
                notify_incoming: true,
                notify_outgoing: true
            },
            success: function(res) {
                if (res.status) {
                    const isFav = res.is_favorite;
                    
                    // Update cache array
                    if (isFav) {
                        if (!favoriteEmailsList.includes(email.toLowerCase())) {
                            favoriteEmailsList.push(email.toLowerCase());
                        }
                    } else {
                        favoriteEmailsList = favoriteEmailsList.filter(e => e !== email.toLowerCase());
                    }

                    // Update Star icon in row
                    if (mailId) {
                        const starIcon = $(`#fav-star-${mailId}`);
                        const row = $(`#mail-row-${mailId}`);
                        if (isFav) {
                            starIcon.removeClass('mdi-star-outline text-muted').addClass('mdi-star text-warning');
                            row.addClass('border-start border-3 border-warning');
                        } else {
                            starIcon.removeClass('mdi-star text-warning').addClass('mdi-star-outline text-muted');
                            row.removeClass('border-start border-3 border-warning');
                        }
                    }

                    // Request desktop notifications if granted
                    if (isFav && 'Notification' in window && Notification.permission === 'default') {
                        Notification.requestPermission();
                    }

                    // Update header badge
                    $('#headerFavBadge').text(favoriteEmailsList.length);
                }
            },
            error: function() {
                alert('Failed to update favorite status.');
            }
        });
    }

    function openFavoriteModal() {
        $('#favoriteNotificationModal').modal('show');
        checkNotificationPermissionStatus();
        fetchFavoritesListModal();
    }

    function checkNotificationPermissionStatus() {
        if ('Notification' in window) {
            if (Notification.permission === 'default') {
                $('#browserPermBanner').removeClass('d-none');
            } else {
                $('#browserPermBanner').addClass('d-none');
            }
        }
    }

    function requestDesktopPermission() {
        if ('Notification' in window) {
            Notification.requestPermission().then(function(perm) {
                checkNotificationPermissionStatus();
                if (perm === 'granted') {
                    playNotificationChime();
                    new Notification('Favorite Mail Notifications Enabled', {
                        body: 'You will receive desktop alerts when your watched contacts send or receive emails.',
                        icon: 'https://cdn-icons-png.flaticon.com/512/281/281769.png'
                    });
                }
            });
        }
    }

    function fetchFavoritesListModal() {
        const container = $('#favoritesListContainer');
        $.ajax({
            url: `/${routePrefix}/favorites`,
            type: 'GET',
            success: function(res) {
                if (res.status) {
                    const list = res.favorites || [];
                    $('#favCountBadge').text(`${list.length} contact${list.length === 1 ? '' : 's'}`);
                    $('#headerFavBadge').text(list.length);

                    if (list.length === 0) {
                        container.html(`
                            <div class="text-center text-muted py-4">
                                <i class="mdi mdi-star-off-outline display-4 opacity-25"></i>
                                <p class="small mt-2 mb-0">No favorite contacts watched yet.<br>Add an email above or star messages in your inbox.</p>
                            </div>
                        `);
                        return;
                    }

                    let html = '<div class="list-group list-group-flush">';
                    list.forEach(item => {
                        html += `
                            <div class="list-group-item d-flex align-items-center justify-content-between px-2 py-2">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <i class="mdi mdi-star text-warning fs-5"></i>
                                    <div class="text-truncate">
                                        <div class="fw-semibold text-dark small text-truncate">${item.name || item.email}</div>
                                        <div class="text-muted" style="font-size: 11px;">${item.email}</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-label-info" style="font-size: 10px;">
                                        ${item.notify_incoming ? 'In ' : ''}${item.notify_outgoing ? 'Out' : ''}
                                    </span>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="removeFavoriteContact(${item.id})" title="Remove">
                                        <i class="mdi mdi-trash-can-outline fs-5"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.html(html);
                }
            }
        });
    }

    function submitAddFavorite() {
        const email = $('#new_fav_email').val();
        const name = $('#new_fav_name').val();
        const notifyIn = $('#fav_notify_in').is(':checked');
        const notifyOut = $('#fav_notify_out').is(':checked');

        if (!email) return;

        $.ajax({
            url: `/${routePrefix}/favorites/toggle`,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                email: email,
                name: name,
                notify_incoming: notifyIn,
                notify_outgoing: notifyOut
            },
            success: function(res) {
                $('#new_fav_email').val('');
                $('#new_fav_name').val('');
                fetchFavoritesListModal();
                fetchSearchResults();
            }
        });
    }

    function removeFavoriteContact(id) {
        $.ajax({
            url: `/${routePrefix}/favorites/${id}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function() {
                fetchFavoritesListModal();
                fetchSearchResults();
            }
        });
    }

    // Sound chime generator using HTML5 Web Audio API
    function playNotificationChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();

            // Tone 1
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            gain1.gain.setValueAtTime(0.15, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start();
            osc1.stop(ctx.currentTime + 0.35);

            // Tone 2 (Harmonic high chime)
            setTimeout(() => {
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880.00, ctx.currentTime); // A5
                gain2.gain.setValueAtTime(0.2, ctx.currentTime);
                gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start();
                osc2.stop(ctx.currentTime + 0.5);
            }, 120);
        } catch (e) {
            // Audio context failed or blocked by browser policy
        }
    }

    // In-App Toast + Desktop Notification Trigger
    function triggerNotification(notif) {
        // 1. Play melodic chime
        playNotificationChime();

        // 2. Browser Desktop Notification
        if ('Notification' in window && Notification.permission === 'granted') {
            const title = notif.type === 'incoming' 
                ? `⭐ Favorite Mail from ${notif.contact || notif.email}`
                : `📤 Email Sent to ${notif.contact || notif.email}`;

            const desktopNotif = new Notification(title, {
                body: `${notif.subject}\n${notif.snippet || ''}`,
                icon: 'https://cdn-icons-png.flaticon.com/512/281/281769.png'
            });

            desktopNotif.onclick = function() {
                window.focus();
                showEmail(notif.id);
            };
        }

        // 3. In-App Floating Toast Alert
        const toastId = 'toast-' + notif.id + '-' + Date.now();
        const typeBadge = notif.type === 'incoming' 
            ? '<span class="badge bg-warning text-dark">⭐ Priority Incoming</span>'
            : '<span class="badge bg-info">📤 Sent Out</span>';

        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-dark bg-white border-warning shadow-lg show mb-2" role="alert" aria-live="assertive" aria-atomic="true" style="border-left: 5px solid #ffab00; border-radius: 12px;">
                <div class="toast-header bg-warning bg-opacity-10 text-dark border-bottom-0 py-2">
                    <i class="mdi mdi-star text-warning me-2 fs-5"></i>
                    <strong class="me-auto text-truncate" style="max-width: 170px;">${notif.contact || notif.email}</strong>
                    ${typeBadge}
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body py-2">
                    <div class="fw-bold text-truncate small mb-1">${notif.subject}</div>
                    <div class="text-muted small text-truncate mb-2" style="font-size: 11px;">${notif.snippet || ''}</div>
                    <div class="d-flex justify-content-end gap-1">
                        <button type="button" class="btn btn-xs btn-primary px-3" onclick="showEmail('${notif.id}'); $('#${toastId}').remove();">
                            <i class="mdi mdi-email-open-outline me-1"></i> View Mail
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('#favoriteToastContainer').append(toastHtml);

        // Auto remove toast after 10s
        setTimeout(() => {
            $(`#${toastId}`).fadeOut(300, function() { $(this).remove(); });
        }, 10000);
    }

    // Polling background worker (every 25 seconds)
    function startFavoriteNotificationPolling() {
        setInterval(function() {
            $.ajax({
                url: `/${routePrefix}/notifications/check`,
                type: 'GET',
                success: function(res) {
                    if (res.status && res.notifications && res.notifications.length > 0) {
                        res.notifications.forEach(notif => {
                            triggerNotification(notif);
                        });
                        // Refresh inbox list seamlessly
                        fetchSearchResults();
                    }
                }
            });
        }, 25000);
    }

    $(document).ready(function() {
        startFavoriteNotificationPolling();
    });
</script>
@endsection
