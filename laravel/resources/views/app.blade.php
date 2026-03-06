<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} – Procurement</title>
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    <style>body { margin: 0; background: #0d1117; }</style>
    @php
        $procurementUser = auth()->user()
            ? [
                'id' => auth()->id(),
                'email' => auth()->user()->email,
                'first_name' => auth()->user()->first_name ?? null,
                'last_name' => auth()->user()->last_name ?? null,
              ]
            : null;
    @endphp
    <script>
        window.__PROCUREMENT__ = {
            user: @json($procurementUser),
            logoutUrl: "{{ route('logout') }}",
            csrfToken: "{{ csrf_token() }}",
            loginUrl: "{{ route('login') }}",
            apiBase: "{{ url('/') }}",
        };
    </script>
</head>
<body>
    <div id="app" style="color: #e6edf3; min-height: 100vh;">Loading…</div>
    <form id="procurement-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>
</body>
</html>
