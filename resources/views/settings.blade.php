@extends(config('gmail-mailbox.layout', 'layouts/layoutMaster'))

@section('title', 'Gmail API Integration')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header border-bottom py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="mdi mdi-google text-danger fs-3"></i>
                    <h5 class="card-title mb-0 fw-bold">Gmail API Integration</h5>
                </div>
                <a href="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.inbox') }}" class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-arrow-left me-1"></i> Back to Mailbox
                </a>
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

                <div class="p-4 rounded border bg-light mb-4 text-center">
                    @if ($isConnected)
                        <div class="text-success mb-2">
                            <i class="mdi mdi-check-decagram display-4"></i>
                        </div>
                        <h5 class="text-success fw-bold">Google Account Connected</h5>
                        <p class="text-muted small mb-3">Your Gmail API credentials are active and authorized to sync messages.</p>
                        
                        <form action="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.disconnect') }}" method="POST" onsubmit="return confirm('Are you sure you want to disconnect your Google account?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="mdi mdi-link-off me-1"></i> Disconnect Account
                            </button>
                        </form>
                    @else
                        <div class="text-warning mb-2">
                            <i class="mdi mdi-alert-circle-outline display-4"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Google Account Not Connected</h5>
                        <p class="text-muted small mb-3">Authorize your Google Workspace or Gmail account to send and receive emails directly.</p>
                        
                        <a href="{{ route(config('gmail-mailbox.route_prefix', 'gmail') . '.auth') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
                            <i class="mdi mdi-google"></i>
                            <span>Connect with Google</span>
                        </a>
                    @endif
                </div>

                <h6 class="fw-bold text-dark mb-2">Setup Checklist:</h6>
                <ul class="text-muted small ps-3 mb-0">
                    <li>Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> to your <code>.env</code> file.</li>
                    <li>Set <code>GOOGLE_REDIRECT_URI={{ url('/' . config('gmail-mailbox.route_prefix', 'gmail') . '/callback') }}</code> in Google Cloud Console.</li>
                    <li>Ensure Gmail API is enabled in your Google Cloud Project.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
