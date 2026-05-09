<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'KDXJobs' }}</title>
    <style>
        :root{--bg:#f6fbff;--panel:#fff;--ink:#111827;--muted:#516176;--line:#dbeafe;--brand:#0ea5e9;--brand-deep:#071bff;--soft:#eef8ff}
        *{box-sizing:border-box}body{margin:0;font-family:Inter,"Segoe UI",Arial,sans-serif;background:linear-gradient(180deg,#f8fcff,#fff 48%,#f5f9fc);color:var(--ink)}a{text-decoration:none;color:inherit}button,input{font:inherit}.wrap{max-width:1460px;margin:0 auto;padding:0 28px}.header{border-bottom:1px solid var(--line);background:rgba(255,255,255,.94);backdrop-filter:blur(18px)}.nav{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:24px;min-height:102px}.brand{display:flex;align-items:center;gap:16px;min-width:240px;font-weight:950}.brand-icon{display:grid;place-items:center;width:68px;height:68px;border:1px solid var(--line);border-radius:22px;background:#fff}.brand-icon img{width:52px;height:52px}.brand-title{font-size:34px;letter-spacing:-.03em}.nav-links{justify-self:center;display:flex;gap:8px;border:1px solid var(--line);border-radius:999px;background:rgba(255,255,255,.78);padding:6px}.nav-link{display:inline-flex;align-items:center;justify-content:center;min-height:56px;border-radius:999px;padding:0 26px;color:#334155;font-size:18px;font-weight:900}.nav-link:hover,.nav-link.active{background:linear-gradient(135deg,var(--brand-deep),var(--brand));color:#fff}.nav-actions{display:flex;align-items:center;gap:12px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:56px;border:1px solid var(--line);border-radius:999px;background:#fff;padding:0 28px;color:#075985;font-size:18px;font-weight:950;cursor:pointer}.btn.primary{border-color:var(--brand);background:var(--brand);color:#fff}.theme-toggle{width:56px;min-width:56px;padding:0}.page{min-height:calc(100vh - 102px);padding:70px 0 120px;background:radial-gradient(circle at 8% 20%,rgba(14,165,233,.10),transparent 24%),radial-gradient(circle at 85% 25%,rgba(7,27,255,.06),transparent 22%)}.auth-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(420px,620px);gap:70px;align-items:center}.eyebrow{display:inline-flex;border:1px solid var(--line);border-radius:999px;background:#fff;padding:10px 18px;color:#0284c7;font-size:16px;font-weight:950;letter-spacing:.22em;text-transform:uppercase}.hero-title{margin:28px 0 0;max-width:720px;font-size:clamp(48px,6vw,86px);line-height:.98;font-weight:950;letter-spacing:-.045em}.lead{max-width:730px;margin:28px 0 0;color:var(--muted);font-size:24px;line-height:1.65}.card{border:1px solid var(--line);border-radius:22px;background:rgba(255,255,255,.92);box-shadow:0 24px 70px rgba(15,23,42,.08);padding:30px}.form{display:grid;gap:20px}.label{display:grid;gap:10px;color:#334155;font-size:20px;font-weight:950}.input{width:100%;height:62px;border:1px solid var(--line);border-radius:16px;background:#eaf3ff;padding:0 20px;color:#0f172a;font-size:19px;font-weight:850;outline:none}.input:focus{border-color:#38bdf8;box-shadow:0 0 0 4px #e0f2fe;background:#fff}.form-actions{display:grid;gap:14px;margin-top:6px}.form-actions .btn{width:100%;border-radius:16px}.flash{margin-bottom:22px;border-radius:16px;padding:15px 18px;font-weight:850}.flash.ok{border:1px solid #bbf7d0;background:#f0fdf4;color:#166534}.flash.bad{border:1px solid #fecaca;background:#fef2f2;color:#991b1b}.footer{border-top:1px solid var(--line);background:#f8fbff;padding:38px 0;color:#475569}.footer strong{color:#071bff;font-size:28px}
        body.theme-dark{--bg:#0b1220;--panel:#111827;--ink:#f8fafc;--muted:#cbd5e1;--line:#1e3a5f;--soft:#10233d;background:#0b1220;color:var(--ink)}body.theme-dark .header{background:rgba(15,23,42,.96);border-color:var(--line)}body.theme-dark .brand-icon,body.theme-dark .nav-links,body.theme-dark .btn,body.theme-dark .card,body.theme-dark .eyebrow{border-color:var(--line);background:#111827;color:#e5e7eb}body.theme-dark .nav-link{color:#cbd5e1}body.theme-dark .page{background:radial-gradient(circle at 8% 20%,rgba(14,165,233,.14),transparent 24%),#0b1220}body.theme-dark .input{border-color:#1e3a5f;background:#10233d;color:#f8fafc}body.theme-dark .label{color:#e5e7eb}body.theme-dark .footer{border-color:var(--line);background:#0b1220;color:#cbd5e1}
        @media(max-width:980px){.nav{grid-template-columns:minmax(0,1fr) auto}.brand{min-width:0}.brand-title{font-size:24px}.nav-links{grid-column:1/-1;justify-self:stretch;overflow:auto}.nav-link{min-height:48px;padding:0 16px;font-size:15px}.nav-actions .btn:not(.theme-toggle){display:none}.auth-grid{grid-template-columns:1fr;gap:38px}.page{padding:44px 0 80px}.hero-title{font-size:46px}.lead{font-size:18px}.card{padding:22px}.wrap{padding:0 18px}}
    </style>
</head>
<body>
    <header class="header">
        <div class="wrap nav">
            <a class="brand" href="{{ url('/index.php') }}?page=home">
                <span class="brand-icon"><img src="{{ asset('assets/kdx-logo.svg') }}" alt=""></span>
                <span class="brand-title">KDXJobs</span>
            </a>
            <nav class="nav-links" aria-label="Primary navigation">
                <a class="nav-link" href="{{ url('/index.php') }}?page=home">Home</a>
                <a class="nav-link" href="{{ url('/index.php') }}?page=jobs">Jobs</a>
                <a class="nav-link" href="{{ url('/index.php') }}?page=companies">Companies</a>
                <a class="nav-link" href="{{ url('/index.php') }}?page=learn">Learn</a>
                <a class="nav-link" href="{{ url('/index.php') }}?page=blog">Blog</a>
            </nav>
            <div class="nav-actions">
                <button class="btn theme-toggle" type="button" data-theme-toggle aria-label="Switch theme">☾</button>
                <a class="btn" href="{{ url('/index.php') }}?page=login">Login</a>
                <a class="btn primary" href="{{ url('/index.php') }}?page=register">Register</a>
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

    <footer class="footer">
        <div class="wrap"><strong>KDXJobs</strong></div>
    </footer>

    <script>
        (() => {
            const key = 'kdx-theme';
            const apply = value => document.body.classList.toggle('theme-dark', value === 'dark');
            apply(localStorage.getItem(key));
            document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
                const next = document.body.classList.contains('theme-dark') ? 'light' : 'dark';
                localStorage.setItem(key, next);
                apply(next);
            });
        })();
    </script>
</body>
</html>
