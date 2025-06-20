<?php
// topolinolib/includes/comic_detail_parts/comment_form.php
?>
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
                    <?php if (!isset($_SESSION['user_id_frontend'])): ?>
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