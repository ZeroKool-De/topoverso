<?php
// topolinolib/includes/comic_detail_parts/discussion_link.php
?>
<div class="discussion-link-header">
    <?php if ($total_posts_for_comic > 0 && $last_post_for_comic): ?>
        <div class="discussion-preview">
            <strong>Ultimo commento per questo albo:</strong>
            <div class="last-comment-preview">
                <p>"<?php echo htmlspecialchars(substr($last_post_for_comic['content'], 0, 80)); ?><?php if(strlen($last_post_for_comic['content']) > 80) echo '...'; ?>"</p>
                <small>
                    Su: "<?php echo htmlspecialchars($last_post_for_comic['thread_title']); ?>"<br>
                    - <?php echo htmlspecialchars($last_post_for_comic['username'] ?? $last_post_for_comic['author_name']); ?>
                    il <?php echo format_date_italian($last_post_for_comic['created_at'], "d/m/Y"); ?>
                </small>
            </div>
            <a href="thread.php?id=<?php echo $last_post_for_comic['thread_id']; ?>" class="main-thread-link">
                Leggi tutti i <?php echo $total_posts_for_comic; ?> commenti &raquo;
            </a>
        </div>
    <?php else: ?>
        <a href="#reply-form-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="1em" height="1em"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            Nessun commento. Sii il primo a dire la tua!
        </a>
    <?php endif; ?>
</div>