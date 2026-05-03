<?php if ($page === 'home'): ?>
<section class="hero">
    <div class="orb"></div>
    <div class="wrap hero-grid">
        <div>
            <span class="pill">🛡️ <?= h(tr('hero.pill', 'Smart Recruitment Platform')) ?></span>
            <h1><?= h(tr('hero.title', 'Find the right job. Hire the right talent.')) ?></h1>
            <p class="lead"><?= h(tr('hero.lead', 'A clean recruitment website for job seekers, companies, and admins, built with modern profiles, dashboards, applications, and job management.')) ?></p>
            <form class="search" method="get">
	            <input type="hidden" name="page" value="jobs">
                <div class="search-inner"><span>🔍</span><input name="q" value="<?= h($search) ?>" placeholder="<?= h(tr('hero.search_placeholder', 'Search job title, company, or skill')) ?>"></div>
                <button class="btn"><?= h(tr('hero.find_jobs', 'Find Jobs')) ?></button>
            </form>
            <div class="hero-actions">
                <a class="btn dark" href="<?= h(app_url('jobs')) ?>"><?= h(tr('hero.find_jobs', 'Find Jobs')) ?></a>
                <a class="btn outline" href="<?= h(app_url('company')) ?>"><?= h(tr('hero.post_job', 'Post a Job')) ?></a>
            </div>
        </div>
        <div class="card hero-panel">
            <div class="panel-inner">
                <div class="grid">
                    <div class="card stat"><span class="icon">💼</span><div><div class="tiny muted"><?= h(tr('stats.open_jobs', 'Open Jobs')) ?></div><div class="stat-value"><?= h($stats['openJobs']) ?></div></div></div>
                    <div class="card stat"><span class="icon">🏢</span><div><div class="tiny muted"><?= h(tr('stats.companies', 'Companies')) ?></div><div class="stat-value"><?= h($stats['companies']) ?></div></div></div>
                    <div class="card stat"><span class="icon">👥</span><div><div class="tiny muted"><?= h(tr('stats.job_seekers', 'Job Seekers')) ?></div><div class="stat-value"><?= h($stats['jobSeekers']) ?></div></div></div>
                </div>
                <div class="card card-pad" style="margin-top:24px">
                    <h3 style="margin-bottom:16px"><?= h(tr('home.latest_applicants', 'Latest Applicants')) ?></h3>
                    <?php foreach (array_slice($applicants, 0, 3) as $a): ?>
                        <div class="applicant">
                            <div><strong><?= h($a['applicant_name']) ?></strong><br><span class="tiny muted"><?= h($a['role']) ?></span></div>
                            <span class="badge"><?= h($a['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="wrap hero-pipeline-row">
        <div class="recruit-animation" aria-label="Animated recruitment pipeline">
            <div class="recruit-animation-head">
                <strong><?= h(tr('pipeline.live', 'Hiring pipeline live')) ?></strong>
                <span><?= h(tr('pipeline.offer_ready', 'Offer ready')) ?></span>
            </div>
            <div class="pipeline-track" aria-hidden="true">
                <div class="pipeline-line"></div>
                <div class="pipeline-stage"><div class="pipeline-node">CV</div><small><?= h(tr('pipeline.profile', 'Profile')) ?></small></div>
                <div class="pipeline-stage"><div class="pipeline-node">✓</div><small><?= h(tr('pipeline.review', 'Review')) ?></small></div>
                <div class="pipeline-stage"><div class="pipeline-node">★</div><small><?= h(tr('pipeline.shortlist', 'Shortlist')) ?></small></div>
                <div class="pipeline-stage"><div class="pipeline-node">Q</div><small><?= h(tr('pipeline.interview', 'Interview')) ?></small></div>
                <div class="pipeline-stage"><div class="pipeline-node">↗</div><small><?= h(tr('pipeline.offer', 'Offer')) ?></small></div>
                <span class="candidate-dot"></span>
                <span class="candidate-dot two"></span>
                <span class="candidate-dot three"></span>
                <div class="offer-card"><i>✓</i> <?= h(tr('pipeline.matched', 'Candidate matched')) ?></div>
            </div>
        </div>
    </div>
</section>
<section class="section career-potential">
    <div class="wrap">
        <div class="section-title"><p class="eyebrow"><?= h(tr('home.why', 'Why KDXJOBS')) ?></p><h2><?= h(tr('home.momentum_title', 'A career platform built for local momentum')) ?></h2><p><?= h(tr('home.momentum_text', 'Not just listings. KDXJOBS helps people understand where they fit, what to improve, and how to move forward.')) ?></p></div>
        <div class="idea-grid">
            <article class="idea-card yellow">
                <div class="idea-art" aria-hidden="true">
                    <svg viewBox="0 0 200 200">
                        <path d="M58 110l36-58 48 96"></path><path d="M85 88h36"></path><path d="M56 126c20 24 70 24 90 0"></path><path d="M42 56l-16-10"></path><path d="M158 56l16-10"></path><path d="M40 88H20"></path><path d="M160 88h20"></path><circle cx="58" cy="150" r="8"></circle><circle cx="142" cy="150" r="8"></circle>
                    </svg>
                </div>
                <div><h3><?= h(tr('home.profiles_title', 'Launch-ready profiles')) ?></h3><p><?= h(tr('home.profiles_text', 'Help candidates turn skills, CVs, and project experience into profiles recruiters can read quickly.')) ?></p></div>
            </article>
            <article class="idea-card pink">
                <div class="idea-art" aria-hidden="true">
                    <svg viewBox="0 0 200 200">
                        <circle cx="72" cy="64" r="42"></circle><path d="M58 62c12 14 28 14 42 0"></path><path d="M52 92c20 20 44 20 64 0"></path><path d="M126 44l34-24 16 18-34 24"></path><path d="M122 80l42 44"></path><circle cx="134" cy="136" r="28"></circle><path d="M124 136c8 8 18 8 26 0"></path>
                    </svg>
                </div>
                <div><h3><?= h(tr('home.matches_title', 'Better matches')) ?></h3><p><?= h(tr('home.matches_text', 'Surface roles by skills, salary, location, and work style so applicants waste less time guessing.')) ?></p></div>
            </article>
            <article class="idea-card violet">
                <div class="idea-art" aria-hidden="true">
                    <svg viewBox="0 0 200 200">
                        <path d="M36 118l82-82 34 34-82 82z"></path><circle cx="130" cy="58" r="36"></circle><path d="M120 58c8 10 22 10 30 0"></path><path d="M34 152h112"></path><path d="M146 152l-22 18"></path><path d="M146 152l-22-18"></path><path d="M166 58h18"></path>
                    </svg>
                </div>
                <div><h3><?= h(tr('home.status_title', 'Status clarity')) ?></h3><p><?= h(tr('home.status_text', 'Keep applications visible from submitted to reviewed, shortlisted, accepted, or rejected.')) ?></p></div>
            </article>
        </div>
    </div>
</section>
<section class="section">
    <div class="wrap">
        <div class="section-title"><p class="eyebrow"><?= h(tr('home.how', 'How It Works')) ?></p><h2><?= h(tr('home.how_title', 'From first search to final update')) ?></h2><p><?= h(tr('home.how_text', 'A calmer hiring flow for job seekers, companies, and recruiter admins.')) ?></p></div>
        <div class="journey-grid">
            <article class="journey-card"><span class="journey-icon">01</span><h3>Map your direction</h3><p>Choose the roles, cities, industries, and work styles that match where you want to grow next.</p></article>
            <article class="journey-card"><span class="journey-icon">02</span><h3>Show real strengths</h3><p>Highlight practical skills, languages, projects, and CV details recruiters can understand quickly.</p></article>
            <article class="journey-card"><span class="journey-icon">03</span><h3>Move faster on good roles</h3><p>Save time with ready profile details, clean job filters, and direct applications from one place.</p></article>
            <article class="journey-card"><span class="journey-icon">04</span><h3>Stay informed after applying</h3><p>Follow each application status and know when a recruiter has reviewed or shortlisted you.</p></article>
        </div>
    </div>
</section>
<section class="section" id="application-tracking">
    <div class="wrap">
        <div class="tracking-story">
            <div class="tracking-story-grid">
                <div class="tracking-story-copy">
                    <span class="tracking-ribbon"><?= h(tr('home.track_ribbon', 'Application Tracking')) ?></span>
                    <h2><?= h(tr('home.track_title', 'We do more than collect applications. We stay with people through every detail.')) ?></h2>
                    <p><?= h(tr('home.track_text', 'Most recruiting services stop after the application is sent. KDXJobs is built to keep candidates informed, supported, and moving with confidence. We show real progress, highlight what happens next, and make every update feel human instead of silent.')) ?></p>
                    <div class="tracking-badges">
                        <span class="tracking-badge"><?= h('Live status updates') ?></span>
                        <span class="tracking-badge"><?= h('Interview reminders') ?></span>
                        <span class="tracking-badge"><?= h('Human support at every stage') ?></span>
                    </div>
                </div>
                <div class="tracking-visual" aria-label="Animated application tracking story">
                    <div class="tracking-glow"></div>
                    <div class="tracking-line"></div>
                    <div class="tracking-pulse"></div>
                    <div class="tracking-step">
                        <div class="tracking-node">✉</div>
                        <div class="tracking-card">
                            <strong>Applied with clarity</strong>
                            <span>Your application is received and instantly visible inside your journey.</span>
                        </div>
                    </div>
                    <div class="tracking-step">
                        <div class="tracking-node">👀</div>
                        <div class="tracking-card">
                            <strong>Reviewed with transparency</strong>
                            <span>You can see when your application is reviewed instead of wondering in silence.</span>
                        </div>
                    </div>
                    <div class="tracking-step">
                        <div class="tracking-node">💬</div>
                        <div class="tracking-card support">
                            <strong>Guided with real support</strong>
                            <span>Interview notes, follow-ups, and service chat keep you informed in every detail.</span>
                        </div>
                    </div>
                    <div class="tracking-step" style="margin-bottom:0">
                        <div class="tracking-node">♥</div>
                        <div class="tracking-card care">
                            <strong>Different because we care</strong>
                            <span>We are not just a recruiting board. We stay beside you until the next step becomes clear.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="section innovation-band">
    <div class="wrap innovation-grid">
        <div class="innovation-lead">
            <p class="eyebrow">New Ideas</p>
            <h2>Next features worth building</h2>
            <p class="lead">A few product ideas that would make KDXJOBS more useful than a standard job board.</p>
        </div>
        <article class="innovation-card"><span class="tiny">Candidate</span><h3>CV health check</h3><p class="muted">Flag missing phone numbers, weak summaries, or skills that match common job filters.</p></article>
        <article class="innovation-card"><span class="tiny">Company</span><h3>Hiring pipeline board</h3><p class="muted">Give employers a simple view of new, reviewed, shortlisted, and final-stage applicants.</p></article>
        <article class="innovation-card"><span class="tiny">Admin</span><h3>Market insights</h3><p class="muted">Show which skills, cities, and salaries are trending across posted jobs.</p></article>
    </div>
</section>
<section class="section">
    <div class="wrap">
        <div class="section-title"><p class="eyebrow"><?= h(tr('home.featured_jobs', 'Featured Jobs')) ?></p><h2><?= h(tr('home.featured_title', 'Fresh opportunities for talented people')) ?></h2><p><?= h(tr('home.featured_text', 'Simple job cards with clear information and fast application flow.')) ?></p></div>
        <div class="grid grid3">
            <?php foreach (array_slice($jobs, 0, 3) as $job): ?>
                <?php include dirname(__DIR__) . '/partials_job_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
