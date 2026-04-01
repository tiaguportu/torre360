@if(request()->routeIs('filament.admin.auth.login'))
    <style>
        .fi-simple-header-heading {
            display: none !important;
        }
    </style>
    <img src="{{ asset('logo-login.png') }}" alt="Torre360 Login" style="height: 4rem;">
@else
    <img src="{{ asset('logo-dashboard.png') }}" alt="Torre360 Logo" style="height: 3rem; margin10px;">
@endif