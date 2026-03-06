<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) – Procurement</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { margin: 0; background: #0d1117; color: #e6edf3; }
        .authenticated-layout { display: flex; min-height: 100vh; background: #0d1117; }
        .app-nav { width: 240px; flex-shrink: 0; display: flex; flex-direction: column; border-right: 1px solid #30363d; background: #161b22; }
        .app-nav-brand { display: flex; align-items: center; justify-content: center; padding: 1.25rem 1rem; border-bottom: 1px solid #30363d; }
        .app-nav-logo-text { font-size: 1.125rem; font-weight: 600; color: #e6edf3; }
        .app-nav-profile { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border-bottom: 1px solid #30363d; background: rgba(255,255,255,0.03); }
        .app-nav-profile-icon { width: 20px; height: 20px; flex-shrink: 0; color: #8b949e; }
        .app-nav-profile-name { font-size: 0.875rem; color: #e6edf3; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .app-nav-links { flex: 1; padding: 0.75rem 0; }
        .app-nav-link { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; margin: 0 0.5rem; color: #8b949e; text-decoration: none; font-size: 0.9375rem; border-radius: 6px; transition: color 0.15s, background 0.15s; }
        .app-nav-link:hover { color: #e6edf3; background: rgba(255,255,255,0.06); }
        .app-nav-link.active { color: #58a6ff; background: rgba(88,166,255,0.12); }
        .app-nav-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .app-nav-footer { padding: 1rem; border-top: 1px solid #30363d; }
        .app-nav-logout { width: 100%; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: transparent; border: 1px solid #30363d; color: #8b949e; border-radius: 6px; cursor: pointer; font-size: 0.875rem; text-align: left; transition: color 0.15s, border-color 0.15s; }
        .app-nav-logout:hover { color: #e6edf3; border-color: #8b949e; }
        .app-main { flex: 1; min-height: 0; overflow: auto; }
        .app-main input[type="text"], .app-main input[type="email"], .app-main input[type="password"], .app-main input[type="number"] { background: #0d1117; border: 1px solid #30363d; color: #e6edf3; border-radius: 6px; }
        .app-main input:focus { outline: none; border-color: #58a6ff; }
        .app-main label { color: #e6edf3; }
        .app-main .text-gray-500, .app-main .text-gray-600 { color: #8b949e !important; }
        .app-main .bg-white { background: #161b22 !important; border: 1px solid #30363d; }
        .app-main .text-gray-900 { color: #e6edf3 !important; }
        .app-main .text-red-600 { color: #f85149 !important; }
        .app-main button[type="submit"].bg-indigo-600 { background: #238636 !important; border-color: #2ea043; color: #fff; }
        .app-main .bg-red-600 { background: #da3633 !important; }
    </style>
    @stack('styles')
</head>
<body>
    <div class="authenticated-layout">
        <aside class="app-nav">
            <div class="app-nav-brand">
                <span class="app-nav-logo-text">Procurement</span>
            </div>
            <div class="app-nav-profile">
                <svg class="app-nav-profile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M6.168 18.849A4 4 0 0 1 10 16h4a4 4 0 0 1 3.834 2.855"/></svg>
                <span class="app-nav-profile-name">{{ Auth::user()->name ?? Auth::user()->email ?? 'Account' }}</span>
            </div>
            <nav class="app-nav-links">
                <a href="{{ route('dashboard') }}" class="app-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Dashboard
                </a>
                <a href="{{ route('data-import.create') }}" class="app-nav-link {{ request()->routeIs('data-import.*') ? 'active' : '' }}">
                    <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                    Data Import
                </a>
                <a href="{{ url('/dashboard/research-queue') }}" class="app-nav-link">
                    <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Research Queue
                </a>
                <a href="{{ url('/dashboard/price-comparison') }}" class="app-nav-link">
                    <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    Price Comparison
                </a>
                <a href="{{ url('/dashboard/vendor-progress') }}" class="app-nav-link">
                    <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Vendor Progress
                </a>
                <a href="{{ url('/dashboard/mapping-review') }}" class="app-nav-link">
                    <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Mapping Review
                </a>
                <a href="{{ url('/dashboard/run-controls') }}" class="app-nav-link">
                    <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Run Controls
                </a>
                <a href="{{ route('profile.edit') }}" class="app-nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M6.168 18.849A4 4 0 0 1 10 16h4a4 4 0 0 1 3.834 2.855"/></svg>
                    Profile
                </a>
            </nav>
            <div class="app-nav-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="app-nav-logout">
                        <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Log out
                    </button>
                </form>
            </div>
        </aside>
        <main class="app-main">
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
