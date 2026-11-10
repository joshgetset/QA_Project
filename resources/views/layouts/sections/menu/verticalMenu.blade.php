@php
use Illuminate\Support\Facades\Route;
@endphp
<aside id="layout-menu" class="layout-menu menu-vertical menu">

    <!-- ! Hide app brand if navbar-full -->
    <div class="app-brand demo">
        <a href="{{ route('dashboard-analytics') }}" class="app-brand-link qams-brand">
            <img class="qams-brand-logo" src="{{ asset('assets/img/custom/Southern_Leyte_State_University logo.png') }}" alt="Southern Leyte State University" />
            <span class="app-brand-text demo menu-text fw-semibold ms-2">QAMS</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="menu-toggle-icon d-xl-inline-block align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        @foreach ($menuData[0]->menu as $menu)

        {{-- adding active and open class if child is active --}}

        {{-- menu headers --}}
        @if (isset($menu->menuHeader))
        <li class="menu-header mt-7">
            <span class="menu-header-text">{{ __($menu->menuHeader) }}</span>
        </li>
        @else

        {{-- active menu method --}}
        @php
        $activeClass = null;
        $currentRouteName = Route::currentRouteName();
        $isExecutiveDashboardMenu = $menu->slug === 'dashboard-analytics';
        $isDashboardMenuToggle = $isExecutiveDashboardMenu && in_array($currentRouteName, ['dashboard-analytics', 'qams.system'], true);

        if ($currentRouteName === $menu->slug) {
        $activeClass = isset($menu->submenu) && ! $isExecutiveDashboardMenu ? 'active open' : 'active';
        }
        elseif (isset($menu->submenu)) {
        if ($currentRouteName === 'qams.system' && collect($menu->submenu)->contains(fn ($item) => isset($item->systemKey) && $item->systemKey === request()->route('system'))) {
        $activeClass = 'active open';
        }
        if (gettype($menu->slug) === 'array') {
        foreach($menu->slug as $slug){
        if (str_contains($currentRouteName,$slug) and strpos($currentRouteName,$slug) === 0) {
        $activeClass = 'active open';
        }
        }
        }
        else{
        if (str_contains($currentRouteName,$menu->slug) and strpos($currentRouteName,$menu->slug) === 0) {
        $activeClass = 'active open';
        }
        }
        }
        @endphp

        {{-- main menu --}}
        <li class="menu-item {{$activeClass}}">
            <a href="{{ $isDashboardMenuToggle ? 'javascript:void(0);' : (isset($menu->url) ? url($menu->url) : 'javascript:void(0);') }}" class="{{ isset($menu->submenu) && (! $isExecutiveDashboardMenu || $isDashboardMenuToggle) ? 'menu-link menu-toggle' : 'menu-link' }} {{ $currentRouteName === $menu->slug ? 'menu-active-route' : '' }}" @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif>
                @isset($menu->icon)
                <i class="{{ $menu->icon }}"></i>
                @endisset
                <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>
                @isset($menu->badge)
                <div class="badge rounded-pill bg-{{ $menu->badge[0] }} rounded-pill ms-auto">{{ $menu->badge[1] }}</div>
                @endisset
            </a>

            {{-- submenu --}}
            @isset($menu->submenu)
            @include('layouts.sections.menu.submenu',['menu' => $menu->submenu])
            @endisset
        </li>
        @endif
        @endforeach
    </ul>

</aside>
