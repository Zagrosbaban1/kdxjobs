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
    <div class="tags">
        <?php foreach (tags($job) as $tag): ?>
            <span class="tag"><?= h($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:10px;margin-top:24px">
        <a class="btn" href="<?= h(app_url('jobs', ['job' => $job['id']])) ?>">View Details</a>
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
