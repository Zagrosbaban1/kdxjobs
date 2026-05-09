<footer class="footer">
    <div class="wrap">
        <div class="footer-top">
            <div class="footer-about">
                <div class="footer-logo">
                    <img src="<?= h(asset_url('assets/kdx-logo.svg')) ?>" alt="">
                    <span>KDXJobs</span>
                </div>
                <p>KDXJobs helps job seekers and employers in Iraq connect through clear listings, company profiles, applications, and recruiter tools built for faster hiring.</p>
                <div class="social-row" aria-label="Social links">
                    <span class="social-dot" title="Facebook" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.4V6.7c0-.8.2-1.3 1.4-1.3H17V2.2c-.8-.1-1.7-.2-2.5-.2-2.6 0-4.4 1.6-4.4 4.5v1.9H7.2V12h2.9v10H14V12h2.8l.4-3.6H14z"/></svg>
                    </span>
                    <span class="social-dot" title="LinkedIn" aria-label="LinkedIn">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.8 7.7A2.3 2.3 0 1 1 4.8 3a2.3 2.3 0 0 1 0 4.7zM3 21h3.6V9H3v12zm6.3-12H13v1.6h.1c.5-.9 1.7-1.9 3.5-1.9 3.8 0 4.5 2.5 4.5 5.7V21h-3.6v-5.8c0-1.4 0-3.2-1.9-3.2s-2.2 1.5-2.2 3.1V21H9.3V9z"/></svg>
                    </span>
                    <span class="social-dot" title="Instagram" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.6 2h8.8A5.6 5.6 0 0 1 22 7.6v8.8a5.6 5.6 0 0 1-5.6 5.6H7.6A5.6 5.6 0 0 1 2 16.4V7.6A5.6 5.6 0 0 1 7.6 2zm0 3A2.6 2.6 0 0 0 5 7.6v8.8A2.6 2.6 0 0 0 7.6 19h8.8a2.6 2.6 0 0 0 2.6-2.6V7.6A2.6 2.6 0 0 0 16.4 5H7.6zM12 7.4a4.6 4.6 0 1 1 0 9.2 4.6 4.6 0 0 1 0-9.2zm0 3a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2zm5.1-3.7a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2z"/></svg>
                    </span>
                    <span class="social-dot" title="Pinterest" aria-label="Pinterest">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.2 2C6.7 2 3 5.8 3 10.4c0 3 1.7 5 4.1 5 .8 0 1.2-2.1 1.2-2.6 0-.7-1.8-1.1-1.8-3.1 0-3 2.3-5.1 5.3-5.1 2.6 0 4.5 1.5 4.5 4.2 0 2-1 5.8-3.5 5.8-1 0-1.8-.7-1.6-1.7.3-1.2.9-2.5.9-3.4 0-2.2-3.1-1.8-3.1 1 0 .9.3 1.5.3 1.5l-1.4 5.9c-.3 1.3 0 3.1.1 4.1h.3c.6-.8 1.7-2.3 2-3.3.2-.5.8-3.1.8-3.1.5.9 1.7 1.6 3 1.6 4 0 6.9-3.7 6.9-8.2C21 5 17.5 2 12.2 2z"/></svg>
                    </span>
                </div>
            </div>
            <div class="footer-links">
                <strong><?= h(tr('footer.explore', 'Platform')) ?></strong>
                <a href="<?= h(app_url('home')) ?>"><?= h(tr('footer.home', 'Home')) ?></a>
                <a href="<?= h(app_url('jobs')) ?>"><?= h(tr('footer.browse_jobs', 'Browse Jobs')) ?></a>
                <a href="<?= h(app_url('companies')) ?>"><?= h(tr('footer.hiring_companies', 'Hiring Companies')) ?></a>
                <a href="<?= h(app_url('blog')) ?>"><?= h(tr('nav.blog', 'Blog')) ?></a>
            </div>
            <div class="footer-links">
                <strong><?= h(tr('footer.account', 'Accounts')) ?></strong>
                <a href="<?= h(app_url('register')) ?>"><?= h(tr('nav.register', 'Create Account')) ?></a>
                <a href="<?= h(app_url('login')) ?>"><?= h(tr('nav.login', 'Login')) ?></a>
                <a href="<?= h(app_url('company')) ?>"><?= h(tr('footer.company_access', 'Company Access')) ?></a>
            </div>
            <div class="footer-links">
                <strong><?= h(tr('footer.support', 'Company')) ?></strong>
                <a href="<?= h(app_url('about')) ?>"><?= h(tr('footer.about', 'About Us')) ?></a>
                <a href="<?= h(app_url('faq')) ?>"><?= h(tr('footer.faq', 'FAQ')) ?></a>
                <a href="<?= h(app_url('policy')) ?>"><?= h(tr('footer.privacy', 'Privacy Policy')) ?></a>
                <a href="<?= h(app_url('terms')) ?>"><?= h(tr('footer.terms', 'Terms')) ?></a>
                <a href="<?= h(app_url('contact')) ?>"><?= h(tr('footer.contact', 'Contact Us')) ?></a>
            </div>
        </div>
        <div class="footer-bottom">
            <span class="tiny muted">&copy;2026. <?= h('Built for clear, modern recruitment.') ?></span>
            <span class="tiny muted">KDXJobs Iraq</span>
        </div>
    </div>
</footer>
<?php $needsClassicEditor = in_array($page, ['admin', 'company'], true); ?>
<?php $needsQuillEditor = $page === 'admin'; ?>
<?php if ($needsQuillEditor): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js" defer></script>
<?php endif; ?>
<?php if ($needsClassicEditor): ?>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js" defer></script>
<?php endif; ?>
<script src="<?= h(asset_url('assets/app.js?v=11')) ?>" defer></script>
