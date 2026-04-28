<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'KDXJobs' }}</title>
    <style>
        :root {
            --bg: #f6f4ed;
            --panel: #fffdf8;
            --ink: #142016;
            --muted: #5d6e61;
            --line: #d8dece;
            --brand: #17633c;
            --brand-soft: #e3f2d8;
            --accent: #f0ba56;
            --alert: #f6fef8;
            --danger: #fff2f0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Segoe UI", serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(240, 186, 86, .22), transparent 24%),
                linear-gradient(180deg, #f5f7ef 0%, #f6f4ed 50%, #edf3ea 100%);
        }
        a { color: inherit; text-decoration: none; }
        button, input, textarea, select { font: inherit; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
        .header {
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid rgba(216, 222, 206, .8);
            backdrop-filter: blur(18px);
            background: rgba(246, 244, 237, .88);
        }
        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 0;
        }
        .brand strong { display: block; font-size: 26px; letter-spacing: -.04em; }
        .brand span { color: var(--muted); font-size: 13px; }
        .nav-links, .nav-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .nav-link, .btn {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 16px;
            background: rgba(255,255,255,.7);
        }
        .btn.primary {
            border-color: var(--brand);
            background: var(--brand);
            color: #fff;
        }
        .page { padding: 36px 0 70px; }
        .hero { padding: 34px 0 24px; }
        .eyebrow {
            display: inline-flex;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 8px 14px;
            color: var(--brand);
            background: rgba(255,255,255,.7);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
        }
        h1, h2, h3 { margin: 0; letter-spacing: -.04em; }
        h1 { font-size: clamp(40px, 6vw, 76px); line-height: .96; margin-top: 18px; }
        h2 { font-size: clamp(30px, 4vw, 42px); }
        .lead { max-width: 700px; margin-top: 16px; color: var(--muted); font-size: 18px; line-height: 1.7; }
        .grid { display: grid; gap: 18px; }
        .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .card {
            border: 1px solid var(--line);
            border-radius: 26px;
            background: rgba(255, 253, 248, .92);
            box-shadow: 0 16px 40px rgba(20, 32, 22, .05);
            padding: 24px;
        }
        .stat strong { font-size: 34px; color: var(--brand); display: block; }
        .muted { color: var(--muted); }
        .tiny { font-size: 14px; }
        .flash {
            margin-bottom: 18px;
            border-radius: 18px;
            padding: 14px 18px;
            border: 1px solid var(--line);
        }
        .flash.ok { background: var(--alert); color: #1d5f3a; }
        .flash.bad { background: var(--danger); color: #9c3426; }
        .form { display: grid; gap: 14px; }
        .label { display: grid; gap: 8px; font-weight: 700; }
        .input, .textarea, .select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            padding: 13px 14px;
            color: var(--ink);
        }
        .tags { display: flex; flex-wrap: wrap; gap: 10px; }
        .tag, .badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 13px;
            background: #fff;
            border: 1px solid var(--line);
        }
        .badge { background: var(--brand-soft); color: var(--brand); border-color: transparent; }
        .section-head { display: flex; justify-content: space-between; align-items: end; gap: 18px; margin-bottom: 22px; }
        .job-card { display: grid; gap: 14px; }
        .meta { display: flex; flex-wrap: wrap; gap: 10px; color: var(--muted); font-size: 14px; }
        .meta span { border: 1px solid var(--line); border-radius: 999px; padding: 7px 11px; background: #fff; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-size: 13px; text-transform: uppercase; }
        @media (max-width: 900px) {
            .nav { align-items: start; flex-direction: column; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .section-head { align-items: start; flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="wrap nav">
            <a class="brand" href="{{ route('home') }}">
                <strong>KDXJobs</strong>
                <span>Laravel migration in progress</span>
            </a>

            <nav class="nav-links">
                <a class="nav-link" href="{{ route('home') }}">Home</a>
                <a class="nav-link" href="{{ route('jobs.index') }}">Jobs</a>
                <a class="nav-link" href="{{ route('companies.index') }}">Companies</a>
                <a class="nav-link" href="{{ route('blog.index') }}">Blog</a>
            </nav>

            <div class="nav-actions">
                @auth
                    @if (auth()->user()->role === 'jobseeker')
                        <a class="nav-link" href="{{ route('user.dashboard') }}">Dashboard</a>
                    @elseif (auth()->user()->role === 'company')
                        <a class="nav-link" href="{{ route('company.dashboard') }}">Company</a>
                    @else
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">Admin</a>
                    @endif
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn" type="submit">Logout</button>
                    </form>
                @else
                    <a class="nav-link" href="{{ route('login') }}">Login</a>
                    <a class="btn primary" href="{{ route('register') }}">Register</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="page">
        <div class="wrap">
            @if (session('message'))
                <div class="flash ok">{{ session('message') }}</div>
            @endif
            @if (session('error'))
                <div class="flash bad">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="flash bad">{{ $errors->first() }}</div>
            @endif

            @yield('content')
        </div>
    </main>
</body>
</html>
