<!-- ! Not required for layout-without-menu -->
@if(!isset($navbarHideToggle))
<div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 {{ isset($contentNavbar) ? ' d-xl-none ' : '' }}">
    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
        <i class="icon-base ri ri-menu-line icon-md"></i>
    </a>
</div>
@endif

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <!-- Search -->
    <div class="navbar-nav align-items-center qams-navbar-search">
        <div class="nav-item d-flex align-items-center">
            <i class="icon-base ri ri-search-line icon-lg lh-0"></i>
            <input type="search" class="form-control border-0 shadow-none qams-navbar-search-input" placeholder="Search..." aria-label="Search...">
        </div>
    </div>
    <!-- /Search -->
    <ul class="navbar-nav flex-row align-items-center ms-auto">
        <li class="nav-item dropdown me-3 qams-pillar-dropdown">
            <a
                class="nav-link dropdown-toggle"
                href="javascript:void(0);"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false"
                aria-label="Select pillar"
            >
                <i class="icon-base ri ri-focus-3-line me-1"></i>
                <span class="qams-pillar-label">Pillar:</span>
                <span class="fw-semibold">{{ session('pillar', 'National') }}</span>
            </a>
            <div class="dropdown-menu dropdown-menu-end p-3">
                <form method="POST" action="{{ route('pillar.update') }}">
                    @csrf
                    <fieldset>
                        <legend class="form-label fw-semibold mb-2">Select pillar</legend>
                        @foreach (['National', 'International', 'Institutional'] as $pillar)
                            <div class="form-check mb-2">
                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="pillar"
                                    id="pillar-{{ strtolower($pillar) }}"
                                    value="{{ $pillar }}"
                                    @checked(session('pillar', 'National') === $pillar)
                                >
                                <label class="form-check-label" for="pillar-{{ strtolower($pillar) }}">{{ $pillar }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                    <button class="btn btn-primary btn-sm w-100 mt-2" type="submit">Apply pillar</button>
                </form>
            </div>
        </li>
        <!-- User -->
        <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar avatar-online">
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
                            <div class="flex-grow-1">
                                <h6 class="mb-0">{{ auth()->user()->name }}</h6>
                                <small class="text-body-secondary">{{ auth()->user()->email }}</small>
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
                    <a class="dropdown-item" href="javascript:void(0);">
                        <span class="d-flex align-items-center align-middle">
                            <i class="flex-shrink-0 icon-base ri ri-bank-card-line icon-md me-3"></i>
                            <span class="flex-grow-1 align-middle ms-1">Billing Plan</span>
                            <span class="flex-shrink-0 badge rounded-pill bg-danger">4</span>
                        </span>
                    </a>
                </li>
                <li>
                    <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                    <div class="d-grid px-4 pt-2 pb-1">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-danger d-flex w-100" type="submit">
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