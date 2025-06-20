<?php
require_once 'config/config.php';
$user_id_frontend = $_SESSION['user_id_frontend'] ?? null;

// --- LOGICA PER TESTO BENVENUTO DINAMICO ---
$default_welcome_text = "Benvenuto in TopoVerso..."; 
$homepage_welcome_text_to_display = $default_welcome_text;
if (isset($mysqli) && $mysqli instanceof mysqli && function_exists('get_site_setting')) {
    $db_welcome_text = get_site_setting('homepage_welcome_text', $mysqli);
    if ($db_welcome_text !== null && trim($db_welcome_text) !== '') {
        $homepage_welcome_text_to_display = $db_welcome_text;
    }
}

// --- LOGICA PER TITOLO PAGINA <title> ---
$page_title_from_settings = "Benvenuto"; 
if (isset($mysqli) && $mysqli instanceof mysqli && function_exists('get_site_setting')) {
     $dynamic_site_name_for_title = get_site_setting('site_name_dynamic', $mysqli, SITE_NAME);
     if(!empty($dynamic_site_name_for_title)) {
        $page_title = $page_title_from_settings . " su " . $dynamic_site_name_for_title;
     } else {
        $page_title = $page_title_from_settings . " su " . SITE_NAME;
     }
} else {
    $page_title = $page_title_from_settings . " su " . SITE_NAME;
}

// --- LOGICA PER BOX ULTIMI AGGIORNAMENTI (STORIE) ---
$latest_updates = [];
$limit_updates = 5; 
$sql_latest_stories = "
    SELECT s.story_id, s.title AS story_title, s.created_at AS story_added_date, s.first_page_image,
           c.comic_id, c.slug, c.issue_number AS comic_issue_number, c.publication_date AS comic_publication_date, c.cover_image
    FROM stories s JOIN comics c ON s.comic_id = c.comic_id
    WHERE c.publication_date IS NOT NULL AND c.publication_date <= CURDATE() ORDER BY s.created_at DESC LIMIT ?";
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt_latest = $mysqli->prepare($sql_latest_stories);
    if ($stmt_latest) {
        $stmt_latest->bind_param("i", $limit_updates);
        $stmt_latest->execute();
        $result_latest = $stmt_latest->get_result();
        while ($story_row = $result_latest->fetch_assoc()) {
            $latest_updates[] = $story_row;
        }
        $stmt_latest->close();
    }
}

// --- LOGICA PER BOX ULTIMI COMMENTI ---
$latest_comments = [];
$limit_comments = 5;
$sql_latest_comments = "
    SELECT p.id as post_id, p.content, p.created_at, t.id as thread_id, t.title as thread_title,
           u.username, u.avatar_image_path, p.author_name
    FROM forum_posts p
    JOIN forum_threads t ON p.thread_id = t.id
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE p.status = 'approved'
    ORDER BY p.created_at DESC
    LIMIT ?";
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt_comments = $mysqli->prepare($sql_latest_comments);
    if ($stmt_comments) {
        $stmt_comments->bind_param("i", $limit_comments);
        $stmt_comments->execute();
        $result_comments = $stmt_comments->get_result();
        while ($comment_row = $result_comments->fetch_assoc()) {
            $latest_comments[] = $comment_row;
        }
        $stmt_comments->close();
    }
}

// --- LOGICA ORDINAMENTO E PAGINAZIONE ---
$sort_options = ['publication_date_desc' => 'Data Pubblicazione (Più Recenti)', 'publication_date_asc' => 'Data Pubblicazione (Meno Recenti)', 'issue_number_desc' => 'Numero Albo (Decrescente)', 'issue_number_asc' => 'Numero Albo (Crescente)', 'created_at_desc' => 'Data Inserimento (Più Recenti)'];
$default_sort_key = 'publication_date_desc';
$current_sort = $_GET['sort'] ?? $default_sort_key;
if (!array_key_exists($current_sort, $sort_options)) { $current_sort = $default_sort_key; }
$limit_options = [15, 30, 45, 60];
$current_limit = 15;
if (isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options)) { $current_limit = (int)$_GET['limit']; }
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) { $current_page = 1; }
$sort_order_sql = '';
switch ($current_sort) {
    case 'publication_date_desc': $sort_order_sql = 'ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, \' bis\', \'.5\'), \' ter\', \'.7\') AS DECIMAL(10,2)) DESC'; break;
    case 'publication_date_asc': $sort_order_sql = 'ORDER BY c.publication_date ASC, CAST(REPLACE(REPLACE(c.issue_number, \' bis\', \'.5\'), \' ter\', \'.7\') AS DECIMAL(10,2)) ASC'; break;
    case 'issue_number_desc': $sort_order_sql = 'ORDER BY CAST(REPLACE(REPLACE(c.issue_number, \' bis\', \'.5\'), \' ter\', \'.7\') AS DECIMAL(10,2)) DESC, c.publication_date DESC'; break;
    case 'issue_number_asc': $sort_order_sql = 'ORDER BY CAST(REPLACE(REPLACE(c.issue_number, \' bis\', \'.5\'), \' ter\', \'.7\') AS DECIMAL(10,2)) ASC, c.publication_date ASC'; break;
    case 'created_at_desc': $sort_order_sql = 'ORDER BY c.created_at DESC'; break;
    default: $sort_order_sql = 'ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, \' bis\', \'.5\'), \' ter\', \'.7\') AS DECIMAL(10,2)) DESC'; $current_sort = $default_sort_key; break;
}
$total_comics = 0;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $total_comics_sql = "SELECT COUNT(c.comic_id) as total FROM comics c WHERE c.publication_date IS NOT NULL AND c.publication_date <= CURDATE()";
    $total_comics_result = $mysqli->query($total_comics_sql);
    if ($total_comics_result) { $total_comics = $total_comics_result->fetch_assoc()['total'] ?? 0; $total_comics_result->free(); }
}
$total_pages = ceil($total_comics / $current_limit);
if ($current_page > $total_pages && $total_pages > 0) { $current_page = $total_pages; }
$offset = ($current_page - 1) * $current_limit;
$comics = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $query_comics_sql = "SELECT c.comic_id, c.issue_number, c.title, c.slug, c.publication_date, c.cover_image, (SELECT COUNT(p.id) FROM forum_posts p JOIN forum_threads t ON p.thread_id = t.id WHERE t.comic_id = c.comic_id AND p.status = 'approved') AS comment_count FROM comics c WHERE c.publication_date IS NOT NULL AND c.publication_date <= CURDATE() $sort_order_sql LIMIT ? OFFSET ?";
    $stmt_comics = $mysqli->prepare($query_comics_sql);
    if($stmt_comics) { $stmt_comics->bind_param("ii", $current_limit, $offset); $stmt_comics->execute(); $result_comics = $stmt_comics->get_result(); if ($result_comics) { while ($row = $result_comics->fetch_assoc()) { $comics[] = $row; } $result_comics->free(); } $stmt_comics->close(); }
}
$user_collection_comic_ids = [];
if ($user_id_frontend && isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt_user_coll = $mysqli->prepare("SELECT comic_id FROM user_collections WHERE user_id = ?");
    if($stmt_user_coll) { $stmt_user_coll->bind_param("i", $user_id_frontend); $stmt_user_coll->execute(); $result_user_coll = $stmt_user_coll->get_result(); while ($coll_row = $result_user_coll->fetch_assoc()) { $user_collection_comic_ids[] = $coll_row['comic_id']; } $stmt_user_coll->close(); }
}
$collection_message_list = $_SESSION['message'] ?? null;
$collection_message_type_list = $_SESSION['message_type'] ?? null;
if ($collection_message_list) { unset($_SESSION['message']); unset($_SESSION['message_type']); }

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    
    <div class="homepage-welcome-text">
        <?php echo nl2br($homepage_welcome_text_to_display); ?>
    </div>

    <div class="homepage-cta-container">
        <a href="search.php" class="btn btn-cta btn-cta-primary">Cerca nel Catalogo</a>
        <a href="forum.php" class="btn btn-cta btn-cta-forum">Discussioni</a>
        <?php if (isset($_SESSION['user_id_frontend'])): ?>
            <a href="user_dashboard.php" class="btn btn-cta btn-cta-dashboard">La Mia Pagina</a>
        <?php else: ?>
            <a href="register.php" class="btn btn-cta btn-cta-success">Registrati Ora!</a>
            <a href="login.php" class="btn btn-cta btn-cta-info">Login</a>
        <?php endif; ?>
        <a href="info_contatti.php" class="btn btn-cta btn-cta-secondary">Info & Contatti</a>
    </div>

    <div class="homepage-widgets-row">
        <?php if (!empty($latest_updates)): ?>
        <div class="updates-box-container">
            <div class="updates-box">
                <details> <summary class="updates-box-summary">
                        <span class="summary-title">🚀 Novità nel Catalogo</span>
                        <span class="summary-last-update">Ultimo Agg.: <?php echo format_date_italian($latest_updates[0]['story_added_date'], "d M Y"); ?></span>
                    </summary>
                    <div class="updates-box-content">
                        <ul>
                            <?php
                            // PREPARAZIONE DELLE QUERY FUORI DAL CICLO PER EFFICIENZA
                            $stmt_story_authors = $mysqli->prepare("SELECT p.name FROM persons p JOIN story_persons sp ON p.id = sp.person_id WHERE sp.story_id = ? AND sp.role IN ('sceneggiatore', 'disegnatore') LIMIT 2");
                            $stmt_story_chars = $mysqli->prepare("SELECT c.name FROM characters c JOIN story_characters sc ON c.id = sc.character_id WHERE sc.story_id = ? LIMIT 2");

                            foreach ($latest_updates as $update):
                            ?>
                            <li class="update-item">
                                <div class="update-thumbnail">
                                    <a href="comic_detail.php?slug=<?php echo htmlspecialchars($update['slug']); ?>#story-item-<?php echo $update['story_id']; ?>">
                                        <?php if (!empty($update['first_page_image'])): ?>
                                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($update['first_page_image']); ?>" alt="Miniatura">
                                        <?php elseif (!empty($update['cover_image'])): ?>
                                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($update['cover_image']); ?>" alt="Miniatura">
                                        <?php else: ?>
                                            <?php echo generate_comic_placeholder_cover(htmlspecialchars($update['comic_issue_number']), 60, 80, 'placeholder-thumb'); ?>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="update-text">
                                    <strong>Storia:</strong> <a href="comic_detail.php?slug=<?php echo htmlspecialchars($update['slug']); ?>#story-item-<?php echo $update['story_id']; ?>"><?php echo htmlspecialchars($update['story_title']); ?></a><br>
                                    <span class="update-meta-line">In: <a href="comic_detail.php?slug=<?php echo htmlspecialchars($update['slug']); ?>">Topolino #<?php echo htmlspecialchars($update['comic_issue_number']); ?></a></span>
                                    
                                    <div class="update-additional-meta">
                                        <?php
                                        // Data di pubblicazione per intero
                                        if (!empty($update['comic_publication_date'])) {
                                            echo '<span><strong>Data:</strong> ' . format_date_italian($update['comic_publication_date']) . '</span>';
                                        }

                                        // Autori (esegue la query preparata)
                                        $story_authors = [];
                                        if($stmt_story_authors) {
                                            $stmt_story_authors->bind_param("i", $update['story_id']);
                                            $stmt_story_authors->execute();
                                            $result_story_authors = $stmt_story_authors->get_result();
                                            while($author_row = $result_story_authors->fetch_assoc()) {
                                                $story_authors[] = $author_row['name'];
                                            }
                                        }
                                        if (!empty($story_authors)) {
                                            echo '<span><strong>Autori:</strong> ' . htmlspecialchars(implode(', ', $story_authors)) . '</span>';
                                        }

                                        // Personaggi (esegue la query preparata)
                                        $story_characters = [];
                                        if($stmt_story_chars) {
                                            $stmt_story_chars->bind_param("i", $update['story_id']);
                                            $stmt_story_chars->execute();
                                            $result_story_chars = $stmt_story_chars->get_result();
                                            while($char_row = $result_story_chars->fetch_assoc()) {
                                                $story_characters[] = $char_row['name'];
                                            }
                                        }
                                        if (!empty($story_characters)) {
                                            echo '<span><strong>Personaggi:</strong> ' . htmlspecialchars(implode(', ', $story_characters)) . '</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </li>
                            <?php 
                            endforeach; 
                            
                            // Chiusura delle query preparate dopo il ciclo
                            if($stmt_story_authors) { $stmt_story_authors->close(); }
                            if($stmt_story_chars) { $stmt_story_chars->close(); }
                            ?>
                        </ul>
                    </div>
                </details>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($latest_comments)): ?>
        <div class="updates-box-container">
            <div class="updates-box">
                <details>
                    <summary class="updates-box-summary">
                        <span class="summary-title">💬 Ultimi Commenti dal Forum</span>
                         <span class="summary-last-update">Ultimo: <?php echo format_date_italian($latest_comments[0]['created_at'], "d M Y"); ?></span>
                    </summary>
                    <div class="updates-box-content">
                        <ul>
                            <?php foreach ($latest_comments as $comment): ?>
                            <li class="update-item">
                                <div class="update-thumbnail">
                                    <?php if (!empty($comment['avatar_image_path'])): ?>
                                        <img src="<?php echo UPLOADS_URL . htmlspecialchars($comment['avatar_image_path']); ?>" alt="Avatar di <?php echo htmlspecialchars($comment['username']); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo generate_image_placeholder(htmlspecialchars($comment['username'] ?? $comment['author_name']), 60, 60, 'placeholder-thumb'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="update-text">
                                    <div class="comment-snippet">"<?php echo htmlspecialchars(substr($comment['content'], 0, 70)); ?>..."</div>
                                    <div class="comment-meta">
                                        Da: <strong><?php echo htmlspecialchars($comment['username'] ?? $comment['author_name']); ?></strong><br>
                                        In: <a href="thread.php?id=<?php echo $comment['thread_id']; ?>#post-<?php echo $comment['post_id']; ?>"><?php echo htmlspecialchars($comment['thread_title']); ?></a>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </details>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($collection_message_list): ?>
        <div class="message <?php echo htmlspecialchars($collection_message_type_list); ?>">
            <?php echo htmlspecialchars($collection_message_list); ?>
        </div>
    <?php endif; ?>

    <h2 class="content-section-title">Catalogo Completo</h2>

    <div class="controls-bar">
        <form action="index.php" method="GET" id="sortLimitForm" style="display: flex; align-items: center; gap: 10px; flex-grow: 1;">
            <div>
                <label for="sort">Ordina per:</label>
                <select name="sort" id="sort" onchange="this.form.submit()">
                    <?php foreach ($sort_options as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($current_sort === $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($value); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="limit">Mostra:</label>
                <select name="limit" id="limit" onchange="this.form.submit()">
                    <?php foreach ($limit_options as $limit_val): ?>
                        <option value="<?php echo $limit_val; ?>" <?php echo ($current_limit == $limit_val) ? 'selected' : ''; ?>>
                            <?php echo $limit_val; ?> albi
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-sm btn-secondary">Applica</button></noscript>
        </form>
        <?php if($total_comics > 0): ?>
        <div class="results-info" style="font-size:0.9em; color: #555;">
            Mostrati <?php echo count($comics); ?> di <?php echo $total_comics; ?> albi
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($comics)): ?>
        <div class="comic-grid">
            <?php foreach ($comics as $comic_item): ?>
                <div class="comic-card">
                    <a href="comic_detail.php?slug=<?php echo htmlspecialchars($comic_item['slug']); ?>">
                        <?php if ($comic_item['cover_image']): ?>
                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($comic_item['cover_image']); ?>" alt="Copertina <?php echo htmlspecialchars($comic_item['issue_number']); ?>" loading="lazy">
                        <?php else: ?>
                            <?php echo generate_comic_placeholder_cover(htmlspecialchars($comic_item['issue_number']), 190, 260); ?>
                        <?php endif; ?>
                        <h3>Topolino #<?php echo htmlspecialchars($comic_item['issue_number']); ?></h3>
                        <?php if (!empty($comic_item['title'])): ?>
                            <p class="comic-subtitle"><em><?php echo htmlspecialchars($comic_item['title']); ?></em></p>
                        <?php endif; ?>
                        <div class="comic-card-footer">
                            <p class="comic-date"><?php echo $comic_item['publication_date'] ? format_date_italian($comic_item['publication_date']) : 'Data N/D'; ?></p>
                            <?php if (isset($comic_item['comment_count']) && $comic_item['comment_count'] > 0): ?>
                                <a href="thread.php?id=<?php echo $comic_item['thread_id'] ?? '#'; ?>" class="comic-comment-count" title="<?php echo $comic_item['comment_count']; ?> commenti">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat-fill" viewBox="0 0 16 16"><path d="M8 15c4.418 0 8-3.134 8-7s-3.582-7-8-7-8 3.134-8 7c0 1.76.743 3.37 1.97 4.6-.097 1.016-.417 2.13-.771 2.966-.079.186.074.394.273.362 2.256-.37 3.597-.938 4.18-1.234A9.06 9.06 0 0 0 8 15z"/></svg>
                                    <?php echo $comic_item['comment_count']; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php if ($user_id_frontend): ?>
                        <div class="comic-card-actions">
                            <?php if (in_array($comic_item['comic_id'], $user_collection_comic_ids)): ?>
                                <form action="collection_actions.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="comic_id" value="<?php echo $comic_item['comic_id']; ?>"><input type="hidden" name="action" value="remove_from_collection"><input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(BASE_URL . 'index.php?sort='.$current_sort.'&limit='.$current_limit.'&page='.$current_page); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger btn-collection-action" title="Rimuovi dalla collezione">✓ Nella Collezione</button>
                                </form>
                            <?php else: ?>
                                <form action="collection_actions.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="comic_id" value="<?php echo $comic_item['comic_id']; ?>"><input type="hidden" name="action" value="add_to_collection"><input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(BASE_URL . 'index.php?sort='.$current_sort.'&limit='.$current_limit.'&page='.$current_page); ?>">
                                    <button type="submit" class="btn btn-sm btn-success btn-collection-action" title="Aggiungi alla collezione">+ Aggiungi</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-controls">
            <?php $base_pagination_url = BASE_URL . "index.php?sort=" . urlencode($current_sort) . "&limit=" . urlencode($current_limit); ?>
            <?php if ($current_page > 1): ?>
                <a href="<?php echo $base_pagination_url; ?>&page=<?php echo $current_page - 1; ?>">« Precedente</a>
            <?php else: ?><span class="disabled">« Precedente</span><?php endif; ?>
            <?php
            $num_links_edges = 2; $num_links_around_current = 2;
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i == $current_page): echo '<span class="current-page">'.$i.'</span>';
                elseif ($i <= $num_links_edges || $i > $total_pages - $num_links_edges || ($i >= $current_page - $num_links_around_current && $i <= $current_page + $num_links_around_current)): echo '<a href="'.$base_pagination_url.'&page='.$i.'">'.$i.'</a>';
                elseif (($i == $num_links_edges + 1 && $current_page > $num_links_edges + $num_links_around_current + 1) || ($i == $total_pages - $num_links_edges && $current_page < $total_pages - $num_links_edges - $num_links_around_current)):
                    $show_dots_after_start = ($i == $num_links_edges + 1 && $current_page > $num_links_edges + $num_links_around_current + 1);
                    $next_block_starts_at = $current_page - $num_links_around_current;
                    $prev_block_ends_at = $num_links_edges;
                    if ($show_dots_after_start && $next_block_starts_at > $prev_block_ends_at + 1) { echo '<span class="disabled">...</span>'; }
                endif;
            endfor;
            ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo $base_pagination_url; ?>&page=<?php echo $current_page + 1; ?>">Successiva »</a>
            <?php else: ?><span class="disabled">Successiva »</span><?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <p style="text-align:center;">Nessun fumetto trovato.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#sort, #limit').forEach(select => {
        select.addEventListener('change', () => {
            document.getElementById('sortLimitForm').submit();
        });
    });
});
</script>

<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
require_once 'includes/footer.php';
?>