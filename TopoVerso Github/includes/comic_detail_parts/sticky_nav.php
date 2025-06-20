<?php
// topolinolib/includes/comic_detail_parts/sticky_nav.php
?>
<?php if ($prev_comic_data || $next_comic_data): ?>
<div class="comic-sticky-navigation">
    <?php if ($prev_comic_data): ?>
        <a href="comic_detail.php?id=<?php echo $prev_comic_data['comic_id']; ?>" class="comic-nav-link prev" title="Albo Precedente: #<?php echo htmlspecialchars($prev_comic_data['issue_number']); ?>">
            <?php if ($prev_comic_data['cover_image']): ?>
                <img src="<?php echo UPLOADS_URL . htmlspecialchars($prev_comic_data['cover_image']); ?>" alt="Cop. #<?php echo htmlspecialchars($prev_comic_data['issue_number']); ?>" class="comic-nav-thumb">
            <?php else: ?>
                <?php echo generate_comic_placeholder_cover(htmlspecialchars($prev_comic_data['issue_number']), 40, 56, 'comic-nav-thumb placeholder-nav-thumb'); ?>
            <?php endif; ?>
            <div class="comic-nav-info">
                <span class="comic-nav-label">&laquo; Precedente</span>
                <span class="comic-nav-issue">#<?php echo htmlspecialchars($prev_comic_data['issue_number']); ?></span>
            </div>
        </a>
    <?php else: ?>
        <div class="comic-nav-link prev" style="visibility: hidden;"></div>
    <?php endif; ?>
    <?php if ($next_comic_data): ?>
        <a href="comic_detail.php?id=<?php echo $next_comic_data['comic_id']; ?>" class="comic-nav-link next" title="Albo Successivo: #<?php echo htmlspecialchars($next_comic_data['issue_number']); ?>">
            <div class="comic-nav-info">
                <span class="comic-nav-label">Successivo &raquo;</span>
                <span class="comic-nav-issue">#<?php echo htmlspecialchars($next_comic_data['issue_number']); ?></span>
            </div>
            <?php if ($next_comic_data['cover_image']): ?>
                <img src="<?php echo UPLOADS_URL . htmlspecialchars($next_comic_data['cover_image']); ?>" alt="Cop. #<?php echo htmlspecialchars($next_comic_data['issue_number']); ?>" class="comic-nav-thumb">
            <?php else: ?>
                <?php echo generate_comic_placeholder_cover(htmlspecialchars($next_comic_data['issue_number']), 40, 56, 'comic-nav-thumb placeholder-nav-thumb'); ?>
            <?php endif; ?>
        </a>
    <?php else: ?>
        <div class="comic-nav-link next" style="visibility: hidden;"></div>
    <?php endif; ?>
</div>
<?php endif; ?>