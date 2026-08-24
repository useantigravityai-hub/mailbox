@php
    $routePrefix = config('gmail-mailbox.route_prefix', 'gmail');
@endphp

<!-- Favorite Notifications Management Modal -->
<div class="modal fade" id="favoriteNotificationModal" tabindex="-1" aria-labelledby="favModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle p-2 bg-label-warning text-warning d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="mdi mdi-star fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="favModalLabel">Favorite Mail Notifications</h5>
                        <small class="text-muted">Get instant notifications when priority contacts send or receive emails</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Browser Permission Banner -->
                <div id="browserPermBanner" class="alert alert-warning d-flex align-items-center justify-content-between py-2 px-3 mb-3 d-none">
                    <div class="d-flex align-items-center gap-2 small">
                        <i class="mdi mdi-bell-ring-outline fs-5"></i>
                        <span>Enable desktop notifications to receive alerts even when this tab is in background.</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-warning text-dark fw-bold px-3" onclick="requestDesktopPermission()">
                        Enable
                    </button>
                </div>

                <!-- Add New Favorite Contact Form -->
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-dark mb-2 small text-uppercase" style="letter-spacing: 0.5px;">Add Contact to Watch</h6>
                        <form id="addFavoriteForm" onsubmit="event.preventDefault(); submitAddFavorite();">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <input type="email" class="form-control form-control-sm" id="new_fav_email" placeholder="Email address (e.g. boss@company.com)" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control form-control-sm" id="new_fav_name" placeholder="Contact name (optional)">
                                </div>
                                <div class="col-md-4 d-flex align-items-center gap-2">
                                    <div class="form-check form-check-inline mb-0" title="Notify on incoming">
                                        <input class="form-check-input" type="checkbox" id="fav_notify_in" checked>
                                        <label class="form-check-label small" for="fav_notify_in">In</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0" title="Notify on outgoing">
                                        <input class="form-check-input" type="checkbox" id="fav_notify_out" checked>
                                        <label class="form-check-label small" for="fav_notify_out">Out</label>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 fw-bold d-flex align-items-center justify-content-center gap-1">
                                        <i class="mdi mdi-plus"></i> Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Watched Contacts List -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0">Watched Favorites List</h6>
                    <span class="badge bg-label-primary rounded-pill px-2 py-1 small" id="favCountBadge">0 contacts</span>
                </div>

                <div class="border rounded p-2 bg-white" style="max-height: 280px; overflow-y: auto;" id="favoritesListContainer">
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <p class="small mb-0">Loading favorites...</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top py-2 px-4 d-flex justify-content-between">
                <small class="text-muted">
                    <i class="mdi mdi-information-outline me-1"></i> Star any email in the inbox list to quickly add its sender here.
                </small>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
