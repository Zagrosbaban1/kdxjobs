<?php if ($page === 'learn'): ?>
<section class="section learning-page no-lazy-section">
    <div class="wrap">
        <div class="learning-quiz-shell">
            <div class="learning-quiz-intro">
                <p class="eyebrow">Learn</p>
                <h2>You do not come here only to find a job. You come here to learn.</h2>
                <p class="lead">Practice small career quizzes, understand what recruiters expect, and improve before you apply.</p>
                <div class="learning-quiz-actions">
                    <a class="btn" href="<?= h(app_url('jobs')) ?>">Find Jobs</a>
                    <a class="btn outline" href="<?= h(app_url('blog')) ?>">Career Articles</a>
                </div>
            </div>
            <div class="quiz-model-card real-quiz-card" data-career-quiz>
                <div class="quiz-model-head">
                    <span class="badge">Career Quiz</span>
                    <strong>Job Readiness Test</strong>
                    <span class="tiny muted" data-quiz-progress>Question 1 of 10</span>
                </div>
                <div class="quiz-progress-track" aria-hidden="true"><span data-quiz-progress-bar></span></div>
                <div class="quiz-model-question">
                    <h3 data-quiz-question>Loading question...</h3>
                    <p class="muted" data-quiz-help>Choose one answer, then continue.</p>
                </div>
                <div class="quiz-answer-list" data-quiz-answers>
                </div>
                <div class="quiz-feedback" data-quiz-feedback hidden>
                    <strong>Recommended next action</strong>
                    <p data-quiz-feedback-text></p>
                </div>
                <div class="quiz-result" data-quiz-result hidden>
                    <span class="badge">Final Result</span>
                    <h3 data-quiz-score></h3>
                    <p class="muted" data-quiz-summary></p>
                    <div class="quiz-breakdown" data-quiz-breakdown></div>
                    <div class="quiz-review" data-quiz-review></div>
                    <div class="quiz-result-actions">
                        <button class="btn" type="button" data-quiz-restart>Try Again</button>
                        <button class="btn outline" type="button" data-quiz-copy>Copy Result</button>
                        <a class="btn outline" href="<?= h(app_url('user', ['tab' => 'profile'])) ?>">Improve Profile</a>
                        <span class="tiny muted quiz-copy-status" data-quiz-copy-status></span>
                    </div>
                </div>
                <div class="quiz-controls">
                    <button class="btn outline" type="button" data-quiz-prev>Back</button>
                    <button class="btn" type="button" data-quiz-next>Next</button>
                </div>
            </div>
        </div>

        <div class="learning-topic-grid">
            <article class="quiz-card">
                <span class="quiz-step">01</span>
                <h3>Interview Practice</h3>
                <p>Learn how to answer common interview questions with clear examples.</p>
            </article>
            <article class="quiz-card">
                <span class="quiz-step">02</span>
                <h3>CV Basics</h3>
                <p>Check if your CV has the details recruiters need: contact, skills, experience, and education.</p>
            </article>
            <article class="quiz-card">
                <span class="quiz-step">03</span>
                <h3>Workplace Skills</h3>
                <p>Improve communication, teamwork, and problem-solving before your next role.</p>
            </article>
        </div>
    </div>
</section>
<?php endif; ?>
