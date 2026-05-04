<?php if ($page === 'blog'): ?>
<?php
$publishedPosts = array_values(array_filter($blogPosts, static fn(array $post): bool => ($post['status'] ?? 'published') === 'published'));
$selectedPostId = (int) ($_GET['post'] ?? 0);
$selectedPost = null;
foreach ($publishedPosts as $post) {
    if ((int) $post['id'] === $selectedPostId) {
        $selectedPost = $post;
        break;
    }
}
?>
<section class="section blog-page-section">
    <div class="wrap">
        <?php if ($selectedPost): ?>
            <div class="blog-detail-wrap">
                <main class="card job-detail-main blog-detail-main">
                    <div class="blog-detail-top">
                        <a class="btn outline blog-back-btn" href="<?= h(app_url('blog')) ?>">Back to Blog</a>
                    </div>
                    <?php if (!empty($selectedPost['cover_image'])): ?><img class="blog-cover detail" src="<?= h(download_url((string) $selectedPost['cover_image'])) ?>" alt="<?= h($selectedPost['title']) ?>"><?php endif; ?>
                    <p class="eyebrow" style="margin-bottom:8px"><?= h($selectedPost['category'] ?: 'Career Advice') ?></p>
                    <h2><?= h($selectedPost['title']) ?></h2>
                    <?php if (!empty($selectedPost['excerpt'])): ?><p class="lead" style="margin-top:16px"><?= h($selectedPost['excerpt']) ?></p><?php endif; ?>
                    <div class="job-rich-text" style="margin-top:28px"><?= rich_text_html($selectedPost['content'] ?? '') ?></div>
                </main>
            </div>
        <?php else: ?>
            <div class="section-title"><p class="eyebrow">Blog</p><h2>Recent Blog Posts</h2><p>Practical advice for job seekers, employers, and recruiters building stronger hiring habits.</p></div>
            <div class="grid grid3 blog-list-grid">
                <?php if (!$publishedPosts): ?>
                    <div class="card empty-state" style="grid-column:1/-1"><h3>No blog posts yet</h3><p class="muted">Admins can publish the first post from the admin dashboard.</p></div>
                <?php endif; ?>
                <?php foreach ($publishedPosts as $post): ?>
                    <article class="card blog-list-card">
                        <?php if (!empty($post['cover_image'])): ?>
                            <div class="blog-list-media">
                                <img src="<?= h(download_url((string) $post['cover_image'])) ?>" alt="<?= h($post['title']) ?>">
                            </div>
                        <?php else: ?>
                            <div class="blog-list-media blog-list-placeholder" aria-hidden="true">
                                <span>KDX</span>
                                <strong><?= h($post['category'] ?: 'Career Advice') ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="blog-list-body">
                            <div class="blog-card-badges">
                                <?php if (!empty($post['is_featured'])): ?><span class="badge featured">Featured</span><?php endif; ?>
                                <span class="badge"><?= h($post['category'] ?: 'Career Advice') ?></span>
                            </div>
                            <h3><?= h($post['title']) ?></h3>
                            <p class="muted"><?= h($post['excerpt'] ?: substr(strip_tags((string) $post['content']), 0, 150) . '...') ?></p>
                            <p class="tiny muted">By <?= h($post['author_name'] ?: 'KDXJOBS Team') ?> - <?= h(date('M j, Y', strtotime((string) $post['created_at']))) ?></p>
                            <a class="btn outline" href="<?= h(app_url('blog', ['post' => $post['id']])) ?>">Read Article</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
