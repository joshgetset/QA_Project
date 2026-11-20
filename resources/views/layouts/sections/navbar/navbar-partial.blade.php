<!-- ! Not required for layout-without-menu -->
@if(($isMenu ?? true) && !isset($navbarHideToggle))
<div class="navbar-nav align-items-xl-center me-2 me-sm-4 me-xl-0 {{ isset($contentNavbar) ? ' d-xl-none ' : '' }}">
    <button type="button" class="layout-menu-toggle nav-item nav-link px-0 me-xl-6" aria-label="Expand sidebar" aria-controls="layout-menu" aria-expanded="false">
        <i class="icon-base ri ri-menu-line icon-md text-primary" aria-hidden="true"></i>
    </button>
</div>
@endif

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <ul class="navbar-nav flex-row align-items-center gap-sm-3 ms-auto">
        <li class="nav-item qams-pillar-selector">
            <form method="POST" action="{{ route('pillar.update') }}" class="qams-pillar-form" data-confirm-pillar>
                @csrf
                <div class="qams-pillar-pill" aria-label="Select pillar">
                    <i class="icon-base ri ri-government-line qams-pillar-icon" aria-hidden="true"></i>
                    <label class="qams-pillar-label d-none d-sm-inline" for="qams-pillar-select">Pillar:</label>
                    <select
                        id="qams-pillar-select"
                        class="form-select form-select-sm qams-pillar-select"
                        name="pillar"
                        aria-label="Select pillar"
                        data-current-value="{{ session('pillar', 'National') }}"
                    >
                        @foreach (['National', 'International', 'Institutional'] as $pillar)
                            <option value="{{ $pillar }}" @selected(session('pillar', 'National') === $pillar)>{{ $pillar }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </li>
        <li class="nav-item navbar-dropdown dropdown-notifications dropdown">
            <button type="button" class="btn btn-icon btn-text-primary rounded-pill dropdown-toggle hide-arrow" id="navbar-notifications" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications" title="Notifications">
                <i class="icon-base ri ri-notification-3-line icon-md" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbar-notifications">
                <h6 class="dropdown-header">Notifications</h6>
                <p class="dropdown-item-text text-body-secondary mb-0">No notifications to display.</p>
            </div>
        </li>
        <li class="nav-item d-none" data-navbar-fullscreen>
            <button type="button" class="btn btn-icon btn-text-primary rounded-pill" aria-label="Enter fullscreen" title="Enter fullscreen">
                <i class="icon-base ri ri-fullscreen-line icon-md" aria-hidden="true"></i>
            </button>
        </li>
        <!-- User -->
        <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar avatar-online qams-user-avatar-shell">
                    <span class="avatar-initial rounded-circle qams-user-avatar" aria-label="{{ auth()->user()->name }}">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </span>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="javascript:void(0);">
                        <div class="d-flex">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar avatar-online">
                                    <span class="avatar-initial rounded-circle qams-user-avatar qams-user-avatar-large" aria-hidden="true">
                                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-grow-1 qams-profile-identity">
                                <h6 class="mb-0">{{ auth()->user()->name }}</h6>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                    <a class="dropdown-item" href="javascript:void(0);">
                        <i class="icon-base ri ri-user-3-line icon-md me-3"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="javascript:void(0);">
                        <i class="icon-base ri ri-settings-4-line icon-md me-4"></i><span>Settings</span>
                    </a>
                </li>
                <li>
                    <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                    <div class="d-grid px-4 pt-2 pb-1">
                        <form method="POST" action="{{ route('logout') }}" data-confirm-logout>
                            @csrf
                            <button class="btn btn-danger d-flex w-100" type="submit" data-logout-button>
                                <small class="align-middle">Logout</small>
                                <i class="ri ri-logout-box-r-line ms-2 icon-xs"></i>
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
        </li>
        <!--/ User -->
    </ul>
</div>
