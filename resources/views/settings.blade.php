@extends(config('gmail-mailbox.layout', 'layouts/layoutMaster'))

@section('title', 'Gmail API Integration')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card shadow-sm border-0">
            <div class="card-header border-bottom py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="mdi mdi-google text-danger fs-3"></i>
                    <div>
                        <h5 class="card-title mb-0 fw-bold">Gmail API Integration</h5>
                        <small class="text-muted">Manage connected Google accounts and mailboxes</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.auth', ['add' => 1]) }}" class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                        <i class="mdi mdi-account-plus-outline"></i>
                        <span>Add Google Account</span>
                    </a>
                    <a href="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.inbox') }}" class="btn btn-sm btn-outline-primary">
                        <i class="mdi mdi-arrow-left me-1"></i> Back to Mailbox
                    </a>
                </div>
            </div>

            <div class="card-body p-4">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle me-1"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle me-1"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <!-- Connected Accounts List -->
                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <i class="mdi mdi-account-multiple-outline text-primary"></i>
                    <span>Connected Accounts ({{ count($accounts ?? []) }})</span>
                </h6>

                @if(!empty($accounts) && count($accounts) > 0)
                    <div class="list-group mb-4">
                        @foreach($accounts as $acc)
                            @php
                                $isActive = ($activeAccount && $activeAccount->id === $acc->id);
                            @endphp
                            <div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 {{ $isActive ? 'border-primary bg-light' : '' }}">
                                <div class="d-flex align-items-center gap-3">
                                    @if(!empty($acc->avatar))
                                        <img src="{{ $acc->avatar }}" alt="{{ $acc->display_name }}" class="rounded-circle shadow-sm" style="width: 44px; height: 44px; object-fit: cover;" referrerpolicy="no-referrer">
                                    @else
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                                            style="width: 44px; height: 44px; font-size: 18px; background-color: {{ $acc->avatar_color }};">
                                            {{ $acc->initial }}
                                        </div>
                                    @endif

                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold text-dark fs-6">{{ $acc->display_name }}</span>
                                            @if($isActive)
                                                <span class="badge bg-success py-1 px-2" style="font-size: 11px;">Active</span>
                                            @endif
                                        </div>
                                        <div class="text-muted small">{{ $acc->email }}</div>
                                        <div class="text-muted" style="font-size: 11px;">Connected {{ $acc->created_at ? $acc->created_at->diffForHumans() : 'recently' }}</div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    @if(!$isActive)
                                        <a href="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.switch', $acc->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="mdi mdi-swap-horizontal me-1"></i> Switch
                                        </a>
                                    @endif

                                    <form action="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.disconnect', $acc->id) }}" method="POST" onsubmit="return confirm('Disconnect {{ $acc->email }}?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Disconnect account">
                                            <i class="mdi mdi-link-off"></i>
                                            <span class="d-none d-md-inline ms-1">Disconnect</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-4 rounded border bg-light mb-4 text-center">
                        <div class="text-warning mb-2">
                            <i class="mdi mdi-alert-circle-outline display-4"></i>
                        </div>
                        <h5 class="text-dark fw-bold">No Google Accounts Connected</h5>
                        <p class="text-muted small mb-3">Connect your first Gmail or Google Workspace account to get started.</p>
                        
                        <a href="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.auth') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
                            <i class="mdi mdi-google"></i>
                            <span>Connect Google Account</span>
                        </a>
                    </div>
                @endif

                <hr class="my-4">

                <h6 class="fw-bold text-dark mb-2">Setup Checklist:</h6>
                <ul class="text-muted small ps-3 mb-0">
                    <li>Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> to your <code>.env</code> file.</li>
                    <li>Set <code>GOOGLE_REDIRECT_URI={{ url('/' . config('gmail-mailbox.route_prefix', 'gmail') . '/callback') }}</code> in Google Cloud Console.</li>
                    <li>Ensure Gmail API and Google Identity / OAuth2 services are enabled in your Google Cloud Project.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
