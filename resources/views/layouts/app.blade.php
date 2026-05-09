<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'KDXJobs' }}</title>
    <style>
        :root {
            --bg: #f8fbff;
            --panel: #ffffff;
            --ink: #101828;
            --muted: #667085;
            --line: #e4e7ec;
            --brand: #175cd3;
            --brand-dark: #1849a9;
            --brand-soft: #eff8ff;
            --accent: #099250;
            --accent-soft: #ecfdf3;
            --warm: #f79009;
            --alert: #f0fdf4;
            --danger: #fff1f3;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 10% 12%, rgba(23, 92, 211, .10), transparent 26%),
                radial-gradient(circle at 90% 18%, rgba(9, 146, 80, .10), transparent 22%),
                linear-gradient(180deg, #f8fbff 0%, #ffffff 48%, #f6fef9 100%);
        }
        a { color: inherit; text-decoration: none; }
        button, input, textarea, select { font: inherit; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
        .header {
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid rgba(228, 231, 236, .88);
            backdrop-filter: blur(18px);
            background: rgba(255, 255, 255, .9);
            box-shadow: 0 10px 30px rgba(16, 24, 40, .05);
        }
        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 0;
        }
        .brand strong { display: block; font-size: 26px; letter-spacing: -.03em; }
        .brand span { color: var(--muted); font-size: 13px; }
        .nav-links, .nav-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .nav-link, .btn {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px 16px;
            background: rgba(255,255,255,.82);
            font-weight: 800;
            transition: background-color .18s ease, border-color .18s ease, color .18s ease, transform .18s ease;
        }
        .nav-link {
            border-radius: 999px;
        }
        .nav-link:hover, .btn:hover {
            transform: translateY(-1px);
        }
        .nav-link:hover {
            border-color: #b2ddff;
            background: #eff8ff;
            color: var(--brand);
        }
        .btn.primary {
            border-color: var(--brand);
            background: var(--brand);
            color: #fff;
            box-shadow: 0 10px 22px rgba(23, 92, 211, .18);
        }
        .btn.primary:hover {
            background: var(--brand-dark);
        }
        .page { padding: 36px 0 70px; }
        .hero { padding: 34px 0 24px; }
        .eyebrow {
            display: inline-flex;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 8px 14px;
            color: var(--brand);
            background: var(--brand-soft);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
        }
        h1, h2, h3 { margin: 0; letter-spacing: -.04em; }
        h1 { font-size: clamp(40px, 6vw, 76px); line-height: .98; margin-top: 18px; }
        h2 { font-size: clamp(30px, 4vw, 42px); }
        .lead { max-width: 700px; margin-top: 16px; color: var(--muted); font-size: 18px; line-height: 1.7; }
        .grid { display: grid; gap: 18px; }
        .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .card {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 16px 40px rgba(16, 24, 40, .07);
            padding: 24px;
        }
        .card:hover {
            border-color: #b2ddff;
        }
        .stat strong { font-size: 34px; color: var(--brand); display: block; }
        .muted { color: var(--muted); }
        .tiny { font-size: 14px; }
        .flash {
            margin-bottom: 18px;
            border-radius: 14px;
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
            border-radius: 12px;
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
        .badge { background: var(--brand-soft); color: var(--brand); border-color: transparent; font-weight: 800; }
        .tag { background: #f2f4f7; color: #475467; font-weight: 700; }
        .section-head { display: flex; justify-content: space-between; align-items: end; gap: 18px; margin-bottom: 22px; }
        .job-card { display: grid; gap: 14px; }
        .meta { display: flex; flex-wrap: wrap; gap: 10px; color: var(--muted); font-size: 14px; }
        .meta span { border: 1px solid var(--line); border-radius: 999px; padding: 7px 11px; background: #fff; }
        .job-card {
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 22px 46px rgba(16, 24, 40, .10);
        }
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
                <span>Modern recruitment platform</span>
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
