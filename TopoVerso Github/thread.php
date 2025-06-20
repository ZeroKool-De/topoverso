<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$thread_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$thread_id) {
    header('Location: forum.php');
    exit;
}

$thread_stmt = $mysqli->prepare("
    SELECT 
        t.id, t.title, t.comic_id, t.story_id,
        s.name as section_name, s.id as section_id,
        c.slug as comic_slug
    FROM forum_threads t
    JOIN forum_sections s ON t.section_id = s.id
    LEFT JOIN comics c ON t.comic_id = c.comic_id
    WHERE t.id = ?
");
$thread_stmt->bind_param('i', $thread_id);
$thread_stmt->execute();
$thread = $thread_stmt->get_result()->fetch_assoc();
$thread_stmt->close();

if (!$thread) {
    header('Location: forum.php');
    exit;
}

$page_title = $thread['title'];

$items_per_page = 20;
$total_posts_stmt = $mysqli->prepare("SELECT COUNT(id) as total FROM forum_posts WHERE thread_id = ? AND status = 'approved'");
$total_posts_stmt->bind_param('i', $thread_id);
$total_posts_stmt->execute();
$total_posts = $total_posts_stmt->get_result()->fetch_assoc()['total'];
$total_posts_stmt->close();

$total_pages = ceil($total_posts / $items_per_page);
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
if ($current_page > $total_pages && $total_posts > 0) { $current_page = $total_pages; }
$offset = ($current_page - 1) * $items_per_page;

$posts_stmt = $mysqli->prepare("
    SELECT p.id, p.content, p.created_at, p.edited_at, p.edited_by_admin_id, p.user_id, p.author_name, 
           u.username, u.avatar_image_path, u.user_role 
    FROM forum_posts p
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE p.thread_id = ? AND p.status = 'approved'
    ORDER BY p.created_at ASC
    LIMIT ? OFFSET ?
");
$posts_stmt->bind_param('iii', $thread_id, $items_per_page, $offset);
$posts_stmt->execute();
$posts = $posts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$posts_stmt->close();

require_once 'includes/header.php';
?>
<div class="container page-content-container">
    <nav class="breadcrumb-nav">
        <a href="forum.php">Forum</a> &raquo; 
        <a href="forum.php?section_id=<?php echo $thread['section_id'] ?? 1; ?>"><?php echo htmlspecialchars($thread['section_name']); ?></a> &raquo; 
        <span><?php echo htmlspecialchars($thread['title']); ?></span>
    </nav>
    <h1><?php echo htmlspecialchars($thread['title']); ?></h1>

    <?php if (!empty($thread['comic_slug'])): 
        $link_url = BASE_URL . 'comic_detail.php?slug=' . htmlspecialchars($thread['comic_slug']);
        $link_text = "&laquo; Torna alla scheda dell'albo";

        if (!empty($thread['story_id'])) {
            $link_url .= '#story-item-' . $thread['story_id'];
            $link_text = "&laquo; Torna alla storia originale nell'albo";
        }
    ?>
    <div class="thread-context-link">
        <a href="<?php echo $link_url; ?>"><?php echo $link_text; ?></a>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['feedback'])): ?>
        <div class="message <?php echo $_SESSION['feedback']['type']; ?>"><?php echo $_SESSION['feedback']['message']; ?></div>
        <?php unset($_SESSION['feedback']); ?>
    <?php endif; ?>

    <div class="forum-posts-container">
        <?php foreach ($posts as $post): 
            $post_id = $post['id'];
            
            // --- INIZIO BLOCCO LOGICA AUTORE CORRETTO ---
            $is_registered_user_post = !empty($post['user_id']);

            if ($is_registered_user_post) {
                // È un utente registrato (user, contributor, o admin)
                $display_name = $post['username'];
                $display_role = $post['user_role'];
                $display_avatar = $post['avatar_image_path'];
            } else {
                // È un visitatore
                $display_name = $post['author_name'] ?? 'Visitatore';
                $display_role = 'visitor';
                $display_avatar = null;
            }
            
            $is_owner = isset($_SESSION['user_id_frontend']) && $is_registered_user_post && $_SESSION['user_id_frontend'] == $post['user_id'];
            $is_admin = (isset($_SESSION['user_role_frontend']) && $_SESSION['user_role_frontend'] === 'admin');
            $can_edit = $is_owner || $is_admin;
            // --- FINE BLOCCO LOGICA AUTORE ---
        ?>
            <div class="forum-post" id="post-<?php echo $post_id; ?>">
                <div class="post-author-info">
                    <a href="#">
                    <?php if ($display_avatar): ?>
                        <img src="<?php echo UPLOADS_URL . htmlspecialchars($display_avatar); ?>" alt="Avatar" class="post-avatar">
                    <?php else: ?>
                        <div class="post-avatar placeholder"><?php echo strtoupper(substr($display_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <strong><?php echo htmlspecialchars($display_name); ?></strong>
                    </a>
                    <span class="user-role-badge role-<?php echo htmlspecialchars($display_role); ?>"><?php echo ucfirst(htmlspecialchars($display_role)); ?></span>
                </div>
                <div class="post-content-container">
                    <div class="post-meta">
                        <span><?php echo format_date_italian($post['created_at'], "d F Y, H:i"); ?></span>
                        <a href="#post-<?php echo $post_id; ?>" class="post-permalink">#<?php echo $post_id; ?></a>
                    </div>
                    
                    <div class="post-content" id="post-content-<?php echo $post_id; ?>">
                        <?php echo format_post_content($post['content']); ?>
                    </div>

                    <div class="edit-post-form" id="edit-form-<?php echo $post_id; ?>" style="display:none;">
                        <form action="actions/comment_actions.php" method="POST">
                            <input type="hidden" name="action" value="edit_post">
                            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <textarea name="content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                            <div class="edit-form-actions">
                                <button type="submit" class="btn btn-primary btn-sm">Salva Modifiche</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEdit(<?php echo $post_id; ?>)">Annulla</button>
                            </div>
                        </form>
                    </div>

                    <div class="post-footer">
                        <div class="post-signature">
                            <?php if ($post['edited_at']): ?>
                                <?php if ($post['edited_by_admin_id']): ?>
                                    <em class="edited-timestamp">Modificato da un Amministratore il <?php echo format_date_italian($post['edited_at'], "d F Y, H:i"); ?></em>
                                <?php else: ?>
                                    <em class="edited-timestamp">Modificato il <?php echo format_date_italian($post['edited_at'], "d F Y, H:i"); ?></em>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="post-actions">
                             <button class="btn btn-secondary btn-sm" onclick="quotePost(<?php echo $post_id; ?>, '<?php echo htmlspecialchars(addslashes($display_name)); ?>')">Cita</button>
                            <?php if ($can_edit): ?>
                                <button class="btn btn-primary btn-sm" onclick="toggleEdit(<?php echo $post_id; ?>)">Modifica</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="pagination-controls">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="thread.php?id=<?php echo $thread_id; ?>&page=<?php echo $i; ?>" class="<?php echo ($i == $current_page) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>

    <div class="reply-form-container">
        <h3>Rispondi alla discussione</h3>
        <form action="actions/comment_actions.php" method="POST">
            <input type="hidden" name="action" value="add_comment">
            <input type="hidden" name="thread_id" value="<?php echo $thread_id; ?>">
            <input type="hidden" name="redirect_url" value="thread.php?id=<?php echo $thread_id; ?>">
            <?php if (isset($_SESSION['user_id_frontend'])): ?>
                <div class="form-group">
                    <textarea name="content" id="reply_content" rows="6" required placeholder="Scrivi la tua risposta..."></textarea>
                </div>
            <?php else: ?>
                <div class="visitor-prompt-box">
                    <h4>Ehi, appassionato di Topolino!</h4>
                    <p>Siamo felicissimi di vederti qui! Per mantenere le nostre discussioni sempre ordinate e costruttive, i commenti dei visitatori vengono controllati da un amministratore prima di essere pubblicati.</p>
                    <p><strong>Vuoi entrare subito nella discussione e sbloccare tutte le funzioni di TopoVerso?</strong><br> La soluzione è semplice e gratuita: registrati!</p>
                    <ul>
                        <li>Pubblicherai commenti in tempo reale.</li>
                        <li>Potrai creare e gestire la tua collezione personale.</li>
                        <li>Riceverai notifiche e potrai modificare i tuoi messaggi.</li>
                    </ul>
                    <p style="text-align:center; margin-top:15px;">
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-success">Clicca qui per unirti alla nostra Community!</a>
                    </p>
                    <hr>
                    <p class="small-text">Ti ricordiamo che TopoVerso è un progetto amatoriale senza scopo di lucro, nato dalla pura passione. Tutto quello che vedi è, e sarà sempre, <strong>completamente gratuito</strong>.</p>
                </div>

                <div class="form-group">
                    <label for="author_name">Il tuo nome:</label>
                    <input type="text" name="author_name" id="author_name" required>
                </div>
                 <div class="form-group">
                    <label for="reply_content">Il tuo commento:</label>
                    <textarea name="content" id="reply_content" rows="6" required></textarea>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Invia Risposta</button>
        </form>
    </div>
</div>

<script>
function toggleEdit(postId) {
    const contentDiv = document.getElementById('post-content-' + postId);
    const formDiv = document.getElementById('edit-form-' + postId);

    if (formDiv.style.display === 'none') {
        contentDiv.style.display = 'none';
        formDiv.style.display = 'block';
    } else {
        contentDiv.style.display = 'block';
        formDiv.style.display = 'none';
    }
}

function quotePost(postId, author) {
    const postContentElement = document.getElementById('post-content-' + postId);
    if (!postContentElement) return;

    let rawText = postContentElement.innerHTML;
    rawText = rawText.replace(/<br\s*[\/]?>/gi, "\n"); 
    rawText = rawText.replace(/<[^>]*>?/gm, '').trim();

    const quoteText = `[quote=${author}]${rawText}[/quote]\n\n`;

    const replyTextarea = document.getElementById('reply_content');
    replyTextarea.value += quoteText;
    replyTextarea.focus();
    replyTextarea.scrollTop = replyTextarea.scrollHeight;
}
</script>

<?php
require_once 'includes/footer.php';
$mysqli->close();
?>