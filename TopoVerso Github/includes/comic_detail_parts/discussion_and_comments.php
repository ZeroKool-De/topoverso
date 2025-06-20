<?php
// topolinolib/includes/comic_detail_parts/discussion_and_comments.php
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

<div class="comment-form-full-width-wrapper" id="reply-form-wrapper">
    <div class="container">
        <div id="reply-form" class="comment-form-container-box">
            <h3 class="stories-section-title">Lascia un Commento</h3>
             <div class="card-body">
                <?php 
                    $feedback_message_form = $_SESSION['feedback']['message'] ?? null;
                    $feedback_type_form = $_SESSION['feedback']['type'] ?? 'info';
                    if ($feedback_message_form): ?>
                    <div class="message <?php echo $feedback_type_form; ?>"><?php echo htmlspecialchars($feedback_message_form); ?></div>
                <?php 
                    unset($_SESSION['feedback']);
                    endif; 
                ?>

                <form action="<?php echo BASE_URL; ?>actions/comment_actions.php" method="POST">
                    <input type="hidden" name="comic_id" value="<?php echo $comic_id; ?>">
                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                    
                    <div class="form-group">
                        <label for="story_id" class="form-label">Commenta una storia specifica (opzionale):</label>
                        <select name="story_id" id="story_id" class="form-control">
                            <option value="">-- Commento generale sull'albo --</option>
                            <?php
                            if (isset($stories_data) && !empty($stories_data)) {
                                foreach ($stories_data as $story) {
                                    echo '<option value="' . $story['story_id'] . '">' . htmlspecialchars($story['title']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="content" class="form-label">Il tuo commento</label>
                        <textarea name="content" id="content" rows="4" class="form-control" required></textarea>
                    </div>

                    <?php if (!isset($_SESSION['user_id_frontend'])): // Se l'utente non è loggato ?>
                        <div class="form-group">
                            <label for="author_name" class="form-label">Il tuo nome (richiesto)</label>
                            <input type="text" name="author_name" id="author_name" class="form-control" required>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-success">Invia commento</button>
                </form>
            </div>
        </div>
    </div>
</div>