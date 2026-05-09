<?php $jobMatchPreview = smart_job_match($job, $user ?? null); ?>
<div class="card job-card card-pad">
    <div class="job-top">
        <span class="icon">💼</span>
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
            <span class="badge"><?= h($job['type']) ?></span>
            <span class="badge"><?= h(ucfirst(job_listing_status($job))) ?></span>
        </div>
    </div>
    <h3><?= h($job['title']) ?></h3>
    <p style="margin:.35rem 0 0;font-weight:800;color:#475569"><?= h($job['company']) ?></p>
    <div class="job-meta">
        <div>📍 <?= h($job['location']) ?></div>
        <div>💰 <?= h($job['salary']) ?></div>
        <div><?= !empty($job['expires_at']) ? 'Deadline ' . h(date('M j, Y', strtotime((string) $job['expires_at']))) : 'No deadline' ?></div>
    </div>
    <?php if ($jobMatchPreview): ?>
        <div class="smart-match-card <?= h($jobMatchPreview['level']) ?>">
            <div class="smart-match-score">
                <strong><?= h((string) $jobMatchPreview['score']) ?>%</strong>
                <span>match</span>
            </div>
            <div class="smart-match-copy">
                <strong><?= h($jobMatchPreview['summary']) ?></strong>
                <span>
                    <?= h((string) count($jobMatchPreview['matched_skills'])) ?>/<?= h((string) max(1, (int) $jobMatchPreview['skill_total'])) ?> skills
                    &middot; <?= $jobMatchPreview['location_match'] ? 'Location fits' : 'Check location' ?>
                </span>
            </div>
        </div>
    <?php elseif (($user['role'] ?? '') === 'jobseeker'): ?>
        <div class="smart-match-card empty">
            <div class="smart-match-score"><strong>--</strong><span>match</span></div>
            <div class="smart-match-copy"><strong>Add profile skills</strong><span>Unlock smart job matching</span></div>
        </div>
    <?php endif; ?>
    <div class="tags">
        <?php foreach (tags($job) as $tag): ?>
            <span class="tag"><?= h($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:10px;margin-top:24px">
        <a class="btn" href="<?= h(job_detail_url($job)) ?>">View Details</a>
        <?php if (($user['role'] ?? '') === 'jobseeker'): ?>
            <?php $isSaved = in_array((int) $job['id'], $savedJobIds ?? [], true); ?>
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="toggle_saved_job">
                <input type="hidden" name="job_id" value="<?= h((string) $job['id']) ?>">
                <input type="hidden" name="save_state" value="<?= $isSaved ? 'saved' : 'new' ?>">
                <input type="hidden" name="redirect_page" value="jobs">
                <button class="btn <?= $isSaved ? 'outline' : 'dark' ?>" title="<?= $isSaved ? 'Remove from saved jobs' : 'Save this job' ?>" aria-label="<?= $isSaved ? 'Remove from saved jobs' : 'Save this job' ?>" style="min-width:54px;padding-left:14px;padding-right:14px"><?= $isSaved ? '✓' : '☆' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
