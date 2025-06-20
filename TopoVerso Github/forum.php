<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$page_title = "Forum della Community";
require_once 'includes/header.php';

// Controllo per vedere se l'utente loggato è un admin
$is_admin = isset($_SESSION['admin_user_id']);

// 1. Recupera tutte le sezioni del forum per mostrarle sempre
$sections = [];
$sections_result = $mysqli->query("SELECT id, name, description FROM forum_sections ORDER BY id ASC");
if ($sections_result) {
    while ($section = $sections_result->fetch_assoc()) {
        $sections[$section['id']] = $section;
    }
}

// 2. Query aggiornata per recuperare anche l'autore del primo post
$threads_sql = "
    WITH LastPosts AS (
        SELECT 
            p.thread_id, p.user_id, p.author_name, p.created_at,
            ROW_NUMBER() OVER(PARTITION BY p.thread_id ORDER BY p.created_at DESC) as rn_desc
        FROM forum_posts p WHERE p.status = 'approved'
    ),
    FirstPosts AS (
        SELECT
            p.thread_id, p.user_id, p.author_name,
            ROW_NUMBER() OVER(PARTITION BY p.thread_id ORDER BY p.created_at ASC) as rn_asc
        FROM forum_posts p WHERE p.status = 'approved'
    )
    SELECT
        t.id AS thread_id, t.title AS thread_title, t.last_post_at, s.id as section_id,
        (SELECT COUNT(*) FROM forum_posts fp WHERE fp.thread_id = t.id AND fp.status = 'approved') AS post_count,
        starter.username AS starter_username, 
        starter.avatar_image_path AS starter_avatar,
        fp.author_name AS first_post_author_name,
        last_poster_user.username AS last_poster_username, 
        lp.author_name AS last_poster_visitor_name, 
        last_poster_user.avatar_image_path AS last_poster_avatar,
        lp.created_at AS last_post_date
    FROM forum_threads t
    JOIN forum_sections s ON t.section_id = s.id
    LEFT JOIN users starter ON t.user_id = starter.user_id
    LEFT JOIN LastPosts lp ON t.id = lp.thread_id AND lp.rn_desc = 1
    LEFT JOIN users last_poster_user ON lp.user_id = last_poster_user.user_id
    LEFT JOIN FirstPosts fp ON t.id = fp.thread_id AND fp.rn_asc = 1
    ORDER BY s.id ASC, t.last_post_at DESC";

$threads_result = $mysqli->query($threads_sql);
if (!$threads_result) { die("Errore nella query del forum: " . $mysqli->error); }

$threads_by_section = [];
while ($thread = $threads_result->fetch_assoc()) {
    $threads_by_section[$thread['section_id']][] = $thread;
}
?>
<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    
    <?php if ($is_admin): ?>
        <div class="new-thread-controls">
            <a href="new_thread.php" class="btn btn-primary">Crea Nuova Discussione</a>
        </div>
    <?php endif; ?>

    <p class="page-description">Leggi e partecipa alle discussioni della community di TopoVerso.</p>
    
    <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $section_id => $section_details): ?>
            <div class="forum-section-container">
                <h2><?php echo htmlspecialchars($section_details['name']); ?></h2>
                <?php if (!empty($section_details['description'])): ?>
                    <p class="section-description"><?php echo htmlspecialchars($section_details['description']); ?></p>
                <?php endif; ?>

                <div class="forum-header">
                    <div style="flex-grow: 1;">Discussione</div>
                    <div class="thread-stats-header">Messaggi</div>
                    <div class="thread-last-post-header">Ultimo Messaggio</div>
                    <?php if ($is_admin): ?>
                        <div class="thread-actions-header">Azioni</div>
                    <?php endif; ?>
                </div>

                <ul class="thread-list">
                    <?php if (isset($threads_by_section[$section_id]) && !empty($threads_by_section[$section_id])): ?>
                        <?php foreach ($threads_by_section[$section_id] as $thread): ?>
                            <li class="thread-item">
                                <div class="thread-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                </div>
                                <div class="thread-details">
                                    <h3 class="thread-title"><a href="thread.php?id=<?php echo $thread['thread_id']; ?>"><?php echo htmlspecialchars($thread['thread_title']); ?></a></h3>
                                    <div class="thread-meta">
                                        Iniziata da 
                                        <?php
                                            // --- LOGICA DI VISUALIZZAZIONE CORRETTA ---
                                            $starter_display_name = 'Visitatore';
                                            $starter_display_avatar = null;

                                            if (!empty($thread['starter_username'])) {
                                                // Caso 1: Thread iniziato da un utente registrato
                                                $starter_display_name = $thread['starter_username'];
                                                $starter_display_avatar = $thread['starter_avatar'];
                                            } elseif (is_null($thread['starter_username']) && !empty($thread['first_post_author_name'])) {
                                                // Caso 2: Thread iniziato da un Admin (user_id nullo, ma il primo post ha un nome autore)
                                                $starter_display_name = $thread['first_post_author_name'];
                                            }
                                        ?>
                                        <a href="#" class="user-avatar-link" title="Autore: <?php echo htmlspecialchars($starter_display_name); ?>">
                                            <?php if ($starter_display_avatar): ?>
                                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($starter_display_avatar); ?>" alt="Avatar" class="user-avatar">
                                            <?php else: ?>
                                                <?php echo generate_image_placeholder(htmlspecialchars($starter_display_name), 20, 20, 'user-avatar'); ?>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($starter_display_name); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="thread-stats">
                                    <span class="stat-value"><?php echo max(0, $thread['post_count']); ?></span>
                                    <span class="stat-label">Messaggi</span>
                                </div>
                                <div class="thread-last-post">
                                    <?php $last_poster_name = $thread['last_poster_username'] ?? $thread['last_poster_visitor_name']; if ($last_poster_name): ?>
                                        <a href="thread.php?id=<?php echo $thread['thread_id']; ?>&page=<?php echo ceil($thread['post_count'] / 10); ?>#reply-form" class="user-avatar-link" title="Ultimo da: <?php echo htmlspecialchars($last_poster_name); ?>">
                                            <?php if ($thread['last_poster_avatar']): ?>
                                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($thread['last_poster_avatar']); ?>" alt="Avatar" class="user-avatar">
                                            <?php else: ?>
                                                <?php echo generate_image_placeholder(htmlspecialchars($last_poster_name), 20, 20, 'user-avatar'); ?>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($last_poster_name); ?>
                                        </a>
                                        <div><?php echo format_date_italian($thread['last_post_at'], "d F Y, H:i"); ?></div>
                                    <?php else: ?>
                                        <span>Nessun messaggio</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_admin): ?>
                                    <div class="thread-actions">
                                        <form action="<?php echo BASE_URL; ?>admin/actions/forum_actions.php" method="POST" onsubmit="return confirm('Sei sicuro di voler eliminare questa intera discussione e tutti i suoi commenti? L\'azione è irreversibile.');">
                                            <input type="hidden" name="action" value="delete_thread">
                                            <input type="hidden" name="thread_id" value="<?php echo $thread['thread_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Elimina Discussione">Elimina</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <li class="thread-item" style="justify-content: center; color: #777; font-style: italic;">Nessuna discussione in questa sezione.</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="padding: 20px; text-align: center;">Nessuna sezione del forum è stata ancora creata.</p>
    <?php endif; ?>
</div>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>