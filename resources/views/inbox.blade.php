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
            <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="mdi mdi-email-outline text-primary fs-3"></i>
                    <h5 class="card-title mb-0 fw-bold">Mail Box</h5>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.settings') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                        <i class="mdi mdi-cog-outline"></i>
                        <span>Settings</span>
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

@endsection

@section('page-script')
@parent
<script>
    const routePrefix = "{{ config('gmail-mailbox.route_prefix', 'gmail') }}";

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
</script>
@endsection
