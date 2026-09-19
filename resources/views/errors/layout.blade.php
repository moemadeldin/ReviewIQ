<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Error') - {{ config('app.name', 'ReviewIQ') }}</title>

    <script>
        (function() {
            let stored = null;
            try { stored = localStorage.getItem('appearance'); } catch (e) {}

            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored ? stored === 'dark' : prefersDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style>
        :root {
            --bg: #fbfbfe;
            --fg: #1b1a28;
            --muted: #6f6c86;
            --card: #ffffff;
            --border: #e4e3f0;
            --accent: #6b5ae6;
            --btn-bg: #1b1a28;
            --btn-fg: #ffffff;
        }

        .dark {
            --bg: #17161d;
            --fg: #f5f5fa;
            --muted: #a8a6b8;
            --card: #23212b;
            --border: #3a3747;
            --accent: #8f7ef0;
            --btn-bg: #e6e5f1;
            --btn-fg: #16151f;
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--fg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100dvh;
            margin: 0;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(1200px 500px at 50% -10%, oklch(0.5 0.16 285.5 / 0.15), transparent 65%);
        }

        .card {
            position: relative;
            width: 100%;
            max-width: 420px;
            text-align: center;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 40px 32px;
            box-shadow: 0 20px 45px -20px oklch(0.2 0.05 285 / 0.3);
        }

        .logo-box { display: flex; justify-content: center; margin-bottom: 24px; }

        .logo { height: 32px; width: auto; border-radius: 8px; }

        .logo-light { display: block; }
        .logo-dark { display: none; }
        .dark .logo-light { display: none; }
        .dark .logo-dark { display: block; }

        .code { font-size: 64px; font-weight: 700; letter-spacing: -0.03em; line-height: 1; color: var(--accent); }

        .title { margin-top: 12px; font-size: 20px; font-weight: 600; letter-spacing: -0.01em; }

        .message { margin-top: 10px; font-size: 14px; line-height: 1.6; color: var(--muted); }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 28px;
            padding: 11px 20px;
            border-radius: 10px;
            font: 500 14px inherit;
            font-family: inherit;
            text-decoration: none;
            background: var(--btn-bg);
            color: var(--btn-fg);
            transition: opacity 0.15s ease;
        }

        .btn:hover { opacity: 0.85; }
    </style>
</head>
<body>
    @php
        $homeUrl = auth()->check() ? '/dashboard' : '/';
        $homeLabel = auth()->check() ? 'Dashboard' : 'Home';
    @endphp

    <div class="card">
        <div class="logo-box">
            <img class="logo logo-dark" src="/logo.png" alt="{{ config('app.name', 'ReviewIQ') }}">
            <img class="logo logo-light" src="/logo-light.png" alt="{{ config('app.name', 'ReviewIQ') }}">
        </div>

        <div class="code">@yield('code')</div>
        <div class="title">@yield('title')</div>
        <div class="message">@yield('message')</div>

        <a class="btn" href="{{ $homeUrl }}">&larr; Back to {{ $homeLabel }}</a>
    </div>
</body>
</html>