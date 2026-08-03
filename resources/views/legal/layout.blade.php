<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') · Comrade AI</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f6f8f6;
            color: #1a2e24;
            line-height: 1.55;
        }
        .wrap { max-width: 720px; margin: 0 auto; padding: 40px 20px 64px; }
        h1 { font-size: 1.75rem; margin: 0 0 8px; color: #016438; }
        h2 { font-size: 1.15rem; margin: 28px 0 8px; color: #016438; }
        .lede { color: #4a6356; margin: 0 0 24px; }
        p, li { color: #24362d; }
        a { color: #016438; }
        nav { margin-bottom: 28px; font-size: 0.95rem; }
        nav a { margin-right: 14px; text-decoration: none; font-weight: 600; }
        nav a:hover { text-decoration: underline; }
        .card {
            background: #fff;
            border: 1px solid #d7e3db;
            border-radius: 14px;
            padding: 22px 22px 8px;
        }
        footer { margin-top: 28px; font-size: 0.85rem; color: #6a7f73; }
    </style>
</head>
<body>
<div class="wrap">
    <nav>
        <a href="{{ url('/support') }}">Support</a>
        <a href="{{ url('/terms') }}">Terms</a>
        <a href="{{ url('/privacy') }}">Privacy</a>
    </nav>
    @yield('content')
    <footer>
        Comrade AI is a private communication-coaching app for authorised political party communicators.
        It is not a Government of Ghana app and does not represent any government entity.
    </footer>
</div>
</body>
</html>
