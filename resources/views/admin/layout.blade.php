<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Comrade AI Admin')</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('ndc-admin-theme');
                document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-app">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="overlay" id="sidebar-overlay"></div>
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Admin">
        <div class="sidebar-brand">
            <div class="sidebar-brand-mark" aria-hidden="true">NDC</div>
            <div>
                <strong>Comrade AI</strong>
                <span>Admin</span>
            </div>
        </div>
        <nav class="sidebar-nav" aria-label="Sections">
            <button type="button" class="nav-item" data-section="overview">Overview</button>
            <button type="button" class="nav-item" data-section="communicators">Communicators</button>
            <button type="button" class="nav-item" data-section="press-prep">Press Prep</button>
            <button type="button" class="nav-item" data-section="notices">Notices</button>
            <button type="button" class="nav-item" data-section="reports">Reports</button>
            <button type="button" class="nav-item" data-section="media">Media</button>
            <button type="button" class="nav-item" data-section="documents">Documents</button>
            <button type="button" class="nav-item" data-section="settings">Settings</button>
        </nav>
        <div class="sidebar-foot">
            <button type="button" class="btn btn-ghost" id="theme-toggle" aria-pressed="false">Dark mode</button>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="btn btn-muted" type="submit">Logout</button>
            </form>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn" id="menu-toggle" aria-label="Open menu">☰</button>
            <div class="topbar-title" id="topbar-title">Overview</div>
        </header>

        <main class="content" id="main-content">
            <div class="content-header">
                <div>
                    <h1 id="section-title">Overview</h1>
                    <p class="muted">Comrade AI · NDC Communicators operations</p>
                </div>
            </div>

            @if (session('status'))
                <div class="flash" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="flash" role="alert" style="border-color:#c53030;background:#fff5f5;color:#9b2c2c;">
                    {{ $errors->first() }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<dialog class="transcript-modal" id="transcript-modal">
    <div class="transcript-modal-inner">
        <div class="transcript-modal-head">
            <div>
                <h2 id="transcript-title">Press Prep transcript</h2>
                <p class="muted" id="transcript-meta"></p>
            </div>
            <button type="button" class="btn btn-muted" id="transcript-close">Close</button>
        </div>
        <div class="transcript-actions row">
            <a class="btn" id="transcript-pdf" href="#" target="_blank" rel="noopener">Download PDF</a>
            <a class="btn btn-muted" id="transcript-txt" href="#" target="_blank" rel="noopener">Download text</a>
        </div>
        <div id="transcript-body" class="transcript-body"></div>
    </div>
</dialog>

<script>
    window.NDC_ADMIN = {
        pressPrepShowUrl: @json(url('/admin/press-prep')),
    };
</script>
<script src="{{ asset('js/admin.js') }}"></script>
@stack('scripts')
</body>
</html>
