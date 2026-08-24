@php
    $routePrefix = config('gmail-mailbox.route_prefix', 'gmail');
    $accounts = $connectedAccounts ?? collect([]);
    $current = $activeAccount ?? null;
@endphp

<style>
    .google-account-btn {
        background-color: #f8f9fa;
        border: 1px solid #dadce0;
        border-radius: 20px;
        padding: 4px 10px 4px 6px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: background-color 0.15s ease, box-shadow 0.15s ease;
    }
    .google-account-btn:hover, .google-account-btn:focus {
        background-color: #f1f3f4;
        box-shadow: 0 1px 3px rgba(60,64,67,0.15);
    }
    .google-account-dropdown {
        width: 320px;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 0;
        overflow: hidden;
    }
    .google-account-card {
        background: #f8fafd;
        border-radius: 12px;
        padding: 16px;
        margin: 12px;
        text-align: center;
    }
    .account-item-row {
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: #202124;
        transition: background-color 0.15s ease;
    }
    .account-item-row:hover {
        background-color: #f1f3f4;
        color: #1a73e8;
    }
    .account-avatar-img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }
    .account-avatar-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-weight: 600;
        font-size: 13px;
    }
</style>

<div class="dropdown">
    @if($current)
        <button class="google-account-btn text-dark text-decoration-none dropdown-toggle dropdown-toggle-hide-arrow" 
                type="button" id="googleAccountSwitcher" data-bs-toggle="dropdown" aria-expanded="false" title="Switch Gmail Account">
            @if(!empty($current->avatar))
                <img src="{{ $current->avatar }}" alt="{{ $current->display_name }}" class="account-avatar-img" referrerpolicy="no-referrer" style="width: 26px; height: 26px;">
            @else
                <div class="account-avatar-circle" style="width: 26px; height: 26px; font-size: 11px; background-color: {{ $current->avatar_color }};">
                    {{ $current->initial }}
                </div>
            @endif

            <span class="d-none d-sm-inline-block text-truncate fw-medium" style="max-width: 140px; font-size: 13px;">
                {{ $current->email }}
            </span>
            <i class="mdi mdi-chevron-down fs-5 text-muted"></i>
        </button>

        <ul class="dropdown-menu dropdown-menu-end google-account-dropdown shadow-lg" aria-labelledby="googleAccountSwitcher">
            <!-- Active Account Details -->
            <div class="google-account-card">
                <div class="mb-2 position-relative d-inline-block">
                    @if(!empty($current->avatar))
                        <img src="{{ $current->avatar }}" alt="{{ $current->display_name }}" class="rounded-circle shadow-sm" style="width: 60px; height: 60px; object-fit: cover;" referrerpolicy="no-referrer">
                    @else
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                            style="width: 60px; height: 60px; font-size: 24px; background-color: {{ $current->avatar_color }};">
                            {{ $current->initial }}
                        </div>
                    @endif
                    <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-light rounded-circle" title="Active">
                        <span class="visually-hidden">Active</span>
                    </span>
                </div>
                <div class="fw-bold text-dark fs-6 text-truncate mb-0">{{ $current->display_name }}</div>
                <div class="text-muted small text-truncate mb-2">{{ $current->email }}</div>
                <span class="badge bg-label-primary px-3 py-1 rounded-pill" style="font-size: 11px;">Active Account</span>
            </div>

            <!-- Other Accounts List -->
            @php
                $otherAccounts = $accounts->where('id', '!=', $current->id);
            @endphp

            @if($otherAccounts->count() > 0)
                <div class="px-3 pt-2 pb-1 text-uppercase text-muted fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">
                    Switch Account ({{ $otherAccounts->count() }})
                </div>
                <div class="scroll-y" style="max-height: 180px;">
                    @foreach($otherAccounts as $other)
                        <a href="{{ route($routePrefix . '.switch', $other->id) }}" class="account-item-row">
                            @if(!empty($other->avatar))
                                <img src="{{ $other->avatar }}" alt="{{ $other->display_name }}" class="account-avatar-img" referrerpolicy="no-referrer">
                            @else
                                <div class="account-avatar-circle" style="background-color: {{ $other->avatar_color }};">
                                    {{ $other->initial }}
                                </div>
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-medium text-dark text-truncate" style="font-size: 13px;">{{ $other->display_name }}</div>
                                <div class="text-muted text-truncate" style="font-size: 11px;">{{ $other->email }}</div>
                            </div>
                            <i class="mdi mdi-arrow-right-thin fs-4 text-muted"></i>
                        </a>
                    @endforeach
                </div>
                <div class="dropdown-divider my-1"></div>
            @endif

            <!-- Add Another Account -->
            <a href="{{ route($routePrefix . '.auth', ['add' => 1]) }}" class="account-item-row py-2 text-primary fw-medium" style="font-size: 13px;">
                <div class="account-avatar-circle bg-light border text-primary">
                    <i class="mdi mdi-account-plus-outline fs-5"></i>
                </div>
                <span>Add another account</span>
            </a>

            <div class="dropdown-divider my-1"></div>

            <!-- Manage / Settings Footer -->
            <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-light">
                <a href="{{ route($routePrefix . '.settings') }}" class="text-muted text-decoration-none small d-flex align-items-center gap-1">
                    <i class="mdi mdi-cog-outline"></i>
                    <span>Manage Accounts</span>
                </a>
                <form action="{{ route($routePrefix . '.disconnect', $current->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Disconnect this Google account?');">
                    @csrf
                    <button type="submit" class="btn btn-link text-danger text-decoration-none p-0 small d-flex align-items-center gap-1">
                        <i class="mdi mdi-logout"></i>
                        <span>Disconnect</span>
                    </button>
                </form>
            </div>
        </ul>
    @else
        <a href="{{ route($routePrefix . '.auth') }}" class="btn btn-sm btn-primary d-flex align-items-center gap-1">
            <i class="mdi mdi-google"></i>
            <span>Connect Gmail</span>
        </a>
    @endif
</div>
