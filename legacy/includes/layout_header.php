<header class="header">
    <div class="wrap nav">
        <a class="brand" href="<?= h(app_url('home')) ?>">
            <span class="brand-icon"><img src="<?= h(asset_url('assets/kdx-logo.svg')) ?>" alt=""></span>
            <span><span class="brand-title">KDXJobs</span></span>
        </a>
        <button class="btn outline nav-menu-toggle" type="button" data-nav-toggle aria-label="<?= h(tr('nav.menu', 'Open navigation menu')) ?>" aria-controls="primary-navigation" aria-expanded="false">&#9776;</button>
        <nav class="nav-links" id="primary-navigation">
            <?php
            $navItems = [['home', tr('nav.home', 'Home')], ['jobs', tr('nav.jobs', 'Jobs')], ['companies', tr('nav.companies', 'Companies')], ['learn', tr('nav.learn', 'Learn')], ['blog', tr('nav.blog', 'Blog')]];
            if (is_admin_role($user['role'] ?? '')) {
                $navItems[] = ['admin', tr('nav.admin', 'Admin Panel')];
            } elseif (($user['role'] ?? '') === 'company') {
                $navItems[] = ['company', tr('nav.company', 'Company Dashboard')];
            } elseif ($user) {
                $navItems[] = ['user', tr('nav.user', 'Job Seeker Dashboard')];
            }
            foreach ($navItems as [$id,$label]):
                $isActiveNav = $page === $id;
            ?>
                <a class="nav-link <?= $isActiveNav ? 'active' : '' ?>" href="<?= h(app_url($id)) ?>" <?= $isActiveNav ? 'aria-current="page"' : '' ?>><?= h($label) ?></a>
            <?php endforeach; ?>
            <div class="nav-mobile-actions">
                <?php if ($user): ?>
                    <span class="tiny muted"><?= h($user['full_name'] ?: ($user['company_name'] ?: $user['email'])) ?></span>
                    <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="logout"><button class="nav-link nav-action-button" type="submit"><?= h(tr('nav.logout', 'Logout')) ?></button></form>
                <?php else: ?>
                    <a class="nav-link <?= $page === 'login' ? 'active' : '' ?>" href="<?= h(app_url('login')) ?>" <?= $page === 'login' ? 'aria-current="page"' : '' ?>><?= h(tr('nav.login', 'Login')) ?></a>
                    <a class="nav-link <?= $page === 'register' ? 'active' : '' ?>" href="<?= h(app_url('register')) ?>" <?= $page === 'register' ? 'aria-current="page"' : '' ?>><?= h(tr('nav.register', 'Register')) ?></a>
                <?php endif; ?>
            </div>
        </nav>
        <div class="nav-actions">
            <button class="btn outline theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch theme"><span data-theme-icon aria-hidden="true">&#9790;</span><span class="sr-only" data-theme-label>Dark mode</span></button>
            <?php if ($user): ?>
                <span class="tiny muted"><?= h($user['full_name'] ?: ($user['company_name'] ?: $user['email'])) ?></span>
                <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="logout"><button class="btn outline"><?= h(tr('nav.logout', 'Logout')) ?></button></form>
            <?php else: ?>
                <a class="btn outline <?= $page === 'login' ? 'active' : '' ?>" href="<?= h(app_url('login')) ?>" <?= $page === 'login' ? 'aria-current="page"' : '' ?>><?= h(tr('nav.login', 'Login')) ?></a>
                <a class="btn <?= $page === 'register' ? 'active' : '' ?>" href="<?= h(app_url('register')) ?>" <?= $page === 'register' ? 'aria-current="page"' : '' ?>><?= h(tr('nav.register', 'Register')) ?></a>
            <?php endif; ?>
        </div>
        <button class="btn outline theme-toggle theme-mobile" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch theme"><span data-theme-icon aria-hidden="true">&#9790;</span><span class="sr-only" data-theme-label>Dark mode</span></button>
        <a class="btn outline mobile-menu" href="<?= h(app_url('jobs')) ?>"><?= h(tr('nav.menu', 'Menu')) ?></a>
    </div>
</header>
