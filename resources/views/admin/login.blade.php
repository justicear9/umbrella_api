<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NDC Comms Admin - Login</title>
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
<body>
<div class="login-page">
    <form class="login-card" method="POST" action="{{ route('admin.login.submit') }}">
        @csrf
        <h1>NDC Communicators</h1>
        <p class="lede">Admin access for document digests and Press Prep.</p>
        @error('admin_key')
            <div class="field-error">{{ $message }}</div>
        @enderror
        <label for="admin_key">Admin key</label>
        <input id="admin_key" name="admin_key" type="password" required autofocus autocomplete="current-password">
        <button type="submit">Enter</button>
    </form>
</div>
<script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
