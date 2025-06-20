<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Ricerca Avanzata nel Catalogo";

// Recupero parametri di ricerca e paginazione
$search_query_display = trim($_GET['q'] ?? '');
$search_type = $_GET['search_type'] ?? 'all';
$search_year_start = isset($_GET['year_start']) && trim($_GET['year_start']) !== '' ? filter_var(trim($_GET['year_start']), FILTER_SANITIZE_NUMBER_INT) : '';
$search_year_end = isset($_GET['year_end']) && trim($_GET['year_end']) !== '' ? filter_var(trim($_GET['year_end']), FILTER_SANITIZE_NUMBER_INT) : '';
$search_author_name = isset($_GET['author_name']) ? trim($_GET['author_name']) : '';
$search_character_name = isset($_GET['character_name']) ? trim($_GET['character_name']) : '';
$search_with_gadget = isset($_GET['with_gadget']) ? '1' : '';
$submit_search_flag = isset($_GET['submit_search']) ? true : false;

$items_per_page = 50;
$current_page_comics = isset($_GET['page_c']) ? max(1, (int)$_GET['page_c']) : 1;
$current_page_stories = isset($_GET['page_s']) ? max(1, (int)$_GET['page_s']) : 1;

$search_results_comics = [];
$search_results_stories = [];
$total_comics_found = 0;
$total_stories_found = 0;
$total_pages_comics = 0;
$total_pages_stories = 0;
$searched = false;

// Messaggi speciali (da jump_to_issue.php)
$special_message = $_SESSION['search_special_message'] ?? null;
$special_message_type = $_SESSION['search_special_message_type'] ?? 'info';
$term_for_request_link = $_SESSION['search_term_for_request'] ?? null;

if ($special_message) {
    unset($_SESSION['search_special_message']);
    unset($_SESSION['search_special_message_type']);
    unset($_SESSION['search_term_for_request']);
}

// Determina se è stata effettuata una ricerca
if ($submit_search_flag || !empty($search_query_display) || !empty($search_year_start) || !empty($search_year_end) || !empty($search_author_name) || !empty($search_character_name) || !empty($search_with_gadget)) {
    $searched = true;
}

if ($searched && isset($mysqli)) {
    $search_term_like = empty($search_query_display) ? null : "%" . $mysqli->real_escape_string($search_query_display) . "%";
    $author_name_like = empty($search_author_name) ? null : "%" . $mysqli->real_escape_string($search_author_name) . "%";
    $character_name_like = empty($search_character_name) ? null : "%" . $mysqli->real_escape_string($search_character_name) . "%";

    // --- LOGICA DI BASE PER WHERE E PARAMS ---
    function build_search_query_parts($type, $search_term_like, $search_year_start, $search_year_end, $author_name_like, $character_name_like, $search_with_gadget) {
        $joins = '';
        $conditions = [];
        $params = [];
        $types = '';

        // Condizioni comuni
        if (!empty($search_year_start)) { $conditions[] = "YEAR(c.publication_date) >= ?"; $params[] = $search_year_start; $types .= "i"; }
        if (!empty($search_year_end)) { $conditions[] = "YEAR(c.publication_date) <= ?"; $params[] = $search_year_end; $types .= "i"; }

        if ($type === 'comics') {
            $conditions[] = "(c.publication_date IS NOT NULL AND c.publication_date <= CURDATE())";
            if ($search_term_like) { $conditions[] = "(c.issue_number LIKE ? OR c.title LIKE ? OR c.description LIKE ?)"; array_push($params, $search_term_like, $search_term_like, $search_term_like); $types .= "sss"; }
            if ($author_name_like) { $joins .= " LEFT JOIN comic_persons cp_filter ON c.comic_id = cp_filter.comic_id LEFT JOIN persons p_filter_c ON cp_filter.person_id = p_filter_c.person_id "; $conditions[] = "p_filter_c.name LIKE ?"; $params[] = $author_name_like; $types .= "s"; }
            if (!empty($search_with_gadget)) { $conditions[] = "(c.gadget_name IS NOT NULL AND c.gadget_name != '')"; }
        } elseif ($type === 'stories') {
            $joins .= " LEFT JOIN story_persons sp_filter ON s.story_id = sp_filter.story_id LEFT JOIN persons p_filter_s ON sp_filter.person_id = p_filter_s.person_id LEFT JOIN story_characters sc_filter ON s.story_id = sc_filter.story_id LEFT JOIN characters char_filter ON sc_filter.character_id = char_filter.character_id ";
            if ($search_term_like) { $conditions[] = "(s.title LIKE ? OR s.notes LIKE ? OR s.story_title_main LIKE ?)"; array_push($params, $search_term_like, $search_term_like, $search_term_like); $types .= "sss"; }
            if ($author_name_like) { $conditions[] = "p_filter_s.name LIKE ?"; $params[] = $author_name_like; $types .= "s"; }
            if ($character_name_like) { $conditions[] = "char_filter.name LIKE ?"; $params[] = $character_name_like; $types .= "s"; }
        }
        
        $where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        return ['joins' => $joins, 'where' => $where_sql, 'params' => $params, 'types' => $types];
    }

    // --- RICERCA ALBI ---
    if ($search_type === 'all' || $search_type === 'comics') {
        $comic_query_parts = build_search_query_parts('comics', $search_term_like, $search_year_start, $search_year_end, $author_name_like, $character_name_like, $search_with_gadget);
        
        // Conteggio totale
        $sql_count_comics = "SELECT COUNT(DISTINCT c.comic_id) as total FROM comics c " . $comic_query_parts['joins'] . " " . $comic_query_parts['where'];
        $stmt_count_c = $mysqli->prepare($sql_count_comics);
        if ($stmt_count_c) { if (!empty($comic_query_parts['types'])) $stmt_count_c->bind_param($comic_query_parts['types'], ...$comic_query_parts['params']); $stmt_count_c->execute(); $total_comics_found = $stmt_count_c->get_result()->fetch_assoc()['total'] ?? 0; $stmt_count_c->close(); }
        $total_pages_comics = ceil($total_comics_found / $items_per_page);
        if ($current_page_comics > $total_pages_comics) $current_page_comics = max(1, $total_pages_comics);
        $offset_comics = ($current_page_comics - 1) * $items_per_page;

        // Recupero dati pagina
        $sql_comics = "SELECT DISTINCT c.comic_id, c.slug, c.issue_number, c.title, c.publication_date, c.cover_image, (SELECT COUNT(p.id) FROM forum_posts p JOIN forum_threads t ON p.thread_id = t.id WHERE t.comic_id = c.comic_id AND p.status = 'approved') AS comment_count FROM comics c " . $comic_query_parts['joins'] . " " . $comic_query_parts['where'] . " ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC LIMIT ? OFFSET ?";
        $stmt_comics = $mysqli->prepare($sql_comics);
        if ($stmt_comics) { $params_comics = array_merge($comic_query_parts['params'], [$items_per_page, $offset_comics]); $types_comics = $comic_query_parts['types'] . "ii"; if (!empty($types_comics)) $stmt_comics->bind_param($types_comics, ...$params_comics); $stmt_comics->execute(); $result_c = $stmt_comics->get_result(); if ($result_c) { while ($row = $result_c->fetch_assoc()) $search_results_comics[] = $row; $result_c->free(); } $stmt_comics->close(); }
    }

    // --- RICERCA STORIE ---
    if ($search_type === 'all' || $search_type === 'stories') {
        $story_query_parts = build_search_query_parts('stories', $search_term_like, $search_year_start, $search_year_end, $author_name_like, $character_name_like, $search_with_gadget);

        // Conteggio totale
        $sql_count_stories = "SELECT COUNT(DISTINCT s.story_id) as total FROM stories s JOIN comics c ON s.comic_id = c.comic_id " . $story_query_parts['joins'] . " " . $story_query_parts['where'];
        $stmt_count_s = $mysqli->prepare($sql_count_stories);
        if($stmt_count_s) { if(!empty($story_query_parts['types'])) $stmt_count_s->bind_param($story_query_parts['types'], ...$story_query_parts['params']); $stmt_count_s->execute(); $total_stories_found = $stmt_count_s->get_result()->fetch_assoc()['total'] ?? 0; $stmt_count_s->close(); }
        $total_pages_stories = ceil($total_stories_found / $items_per_page);
        if ($current_page_stories > $total_pages_stories) $current_page_stories = max(1, $total_pages_stories);
        $offset_stories = ($current_page_stories - 1) * $items_per_page;
        
        // Recupero dati pagina
        $sql_stories = "SELECT DISTINCT s.story_id, s.title as story_title, s.first_page_image, s.story_title_main, s.part_number, s.total_parts, c.comic_id, c.slug, c.issue_number, c.title as comic_title, c.publication_date, s.sequence_in_comic FROM stories s JOIN comics c ON s.comic_id = c.comic_id " . $story_query_parts['joins'] . " " . $story_query_parts['where'] . " ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC, s.sequence_in_comic ASC LIMIT ? OFFSET ?";
        $stmt_stories = $mysqli->prepare($sql_stories);
        if ($stmt_stories) { $params_stories = array_merge($story_query_parts['params'], [$items_per_page, $offset_stories]); $types_stories = $story_query_parts['types'] . "ii"; if(!empty($types_stories)) $stmt_stories->bind_param($types_stories, ...$params_stories); $stmt_stories->execute(); $result_s = $stmt_stories->get_result(); if ($result_s) { while ($row = $result_s->fetch_assoc()) $search_results_stories[] = $row; $result_s->free(); } $stmt_stories->close(); }
    }
}

// --- Logica per i Suggerimenti Visivi ---
$popular_characters = [];
$popular_authors = [];
$popular_decades_links = [];
if (!$searched && isset($mysqli)) {
    $limit_suggestions = 10;
    $sql_pop_chars = "SELECT ch.character_id, ch.name, ch.slug, ch.character_image, COUNT(sc.story_id) as story_count FROM characters ch LEFT JOIN story_characters sc ON ch.character_id = sc.character_id GROUP BY ch.character_id, ch.name, ch.character_image ORDER BY story_count DESC, ch.name ASC LIMIT ?";
    $stmt_pop_chars = $mysqli->prepare($sql_pop_chars);
    if($stmt_pop_chars) { $stmt_pop_chars->bind_param("i", $limit_suggestions); $stmt_pop_chars->execute(); $res_pop_chars = $stmt_pop_chars->get_result(); if ($res_pop_chars) { while($row_char = $res_pop_chars->fetch_assoc()) $popular_characters[] = $row_char; $res_pop_chars->free(); } $stmt_pop_chars->close(); }
    $sql_pop_auth = "SELECT p.person_id, p.name, p.slug, p.person_image, COUNT(sp.story_id) as story_count FROM persons p LEFT JOIN story_persons sp ON p.person_id = sp.person_id GROUP BY p.person_id, p.name, p.person_image ORDER BY story_count DESC, p.name ASC LIMIT ?";
    $stmt_pop_auth = $mysqli->prepare($sql_pop_auth);
    if($stmt_pop_auth) { $stmt_pop_auth->bind_param("i", $limit_suggestions); $stmt_pop_auth->execute(); $res_pop_auth = $stmt_pop_auth->get_result(); if ($res_pop_auth) { while($row_auth = $res_pop_auth->fetch_assoc()) $popular_authors[] = $row_auth; $res_pop_auth->free(); } $stmt_pop_auth->close(); }
    $sql_decades = "SELECT DISTINCT (FLOOR(YEAR(publication_date) / 10) * 10) AS decade_start FROM comics WHERE publication_date IS NOT NULL AND publication_date <= CURDATE() AND YEAR(publication_date) >= 1940 ORDER BY decade_start ASC";
    $res_decades = $mysqli->query($sql_decades);
    if($res_decades){ while($decade_row = $res_decades->fetch_assoc()){ $popular_decades_links[] = ['label' => "Anni '" . substr($decade_row['decade_start'], -2), 'year_start' => $decade_row['decade_start'], 'year_end' => $decade_row['decade_start'] + 9 ]; } $res_decades->free(); if(count($popular_decades_links) > 7) { $popular_decades_links = array_slice($popular_decades_links, -7); } }
}

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <form action="search.php" method="GET" class="search-form-advanced">
        <div class="main-search-bar">
            <input type="text" name="q" placeholder="Cerca titoli, numeri albo, descrizioni..." value="<?php echo htmlspecialchars($search_query_display); ?>">
        </div>
        
        <div class="advanced-filters">
            <div class="filter-group">
                <label for="search_type_filter">Cerca in:</label>
                <select name="search_type" id="search_type_filter">
                    <option value="all" <?php echo ($search_type === 'all') ? 'selected' : ''; ?>>Tutto (Albi e Storie)</option>
                    <option value="comics" <?php echo ($search_type === 'comics') ? 'selected' : ''; ?>>Solo Albi</option>
                    <option value="stories" <?php echo ($search_type === 'stories') ? 'selected' : ''; ?>>Solo Storie</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="year_start_form">Anno Da:</label>
                <input type="number" name="year_start" id="year_start_form" placeholder="Es. 1990" value="<?php echo htmlspecialchars($search_year_start); ?>" min="1900" max="<?php echo date('Y') + 5; ?>">
            </div>
            <div class="filter-group">
                <label for="year_end_form">Anno A:</label>
                <input type="number" name="year_end" id="year_end_form" placeholder="Es. 1999" value="<?php echo htmlspecialchars($search_year_end); ?>" min="1900" max="<?php echo date('Y') + 5; ?>">
            </div>
            <div class="filter-group">
                <label for="author_name_filter">Autore:</label>
                <input type="text" name="author_name" id="author_name_filter" placeholder="Nome autore..." value="<?php echo htmlspecialchars($search_author_name); ?>">
            </div>
            <div class="filter-group">
                <label for="character_name_filter">Personaggio:</label>
                <input type="text" name="character_name" id="character_name_filter" placeholder="Nome personaggio..." value="<?php echo htmlspecialchars($search_character_name); ?>">
            </div>
            <div class="filter-group">
                <label for="with_gadget_filter">
                    <input type="checkbox" name="with_gadget" id="with_gadget_filter" value="1" <?php echo !empty($search_with_gadget) ? 'checked' : ''; ?>>
                    Solo albi con Gadget
                </label>
            </div>
        </div>
         <div style="text-align:right; margin-top:20px; display:flex; justify-content: flex-end; gap:10px;">
            <button type="submit" name="submit_search" value="1" class="btn btn-primary">Applica Filtri</button>
            <?php if ($searched): ?>
                <a href="search.php" class="btn btn-secondary">Nuova Ricerca</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($special_message): ?>
        <div class="message <?php echo htmlspecialchars($special_message_type); ?> request-prompt">
            <?php echo $special_message; ?>
            <?php if ($term_for_request_link): ?>
                <p>
                    <a href="<?php echo BASE_URL; ?>richieste.php?issue_number_request=<?php echo urlencode($term_for_request_link); ?>" class="btn btn-primary btn-sm">
                        Clicca qui per richiederne l'inserimento
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($searched): ?>
        <?php 
        $active_filters_display_list = [];
        if(!empty($search_query_display)) $active_filters_display_list[] = "Testo: \"" . htmlspecialchars($search_query_display) . "\"";
        if(!empty($search_year_start) && !empty($search_year_end)) { $active_filters_display_list[] = "Anni: " . htmlspecialchars($search_year_start) . "-" . htmlspecialchars($search_year_end);
        } elseif(!empty($search_year_start)) { $active_filters_display_list[] = "A partire dall'anno: " . htmlspecialchars($search_year_start);
        } elseif(!empty($search_year_end)) { $active_filters_display_list[] = "Fino all'anno: " . htmlspecialchars($search_year_end); }
        if(!empty($search_author_name)) $active_filters_display_list[] = "Autore: " . htmlspecialchars($search_author_name);
        if(!empty($search_character_name)) $active_filters_display_list[] = "Personaggio: " . htmlspecialchars($search_character_name);
        if(!empty($search_with_gadget)) $active_filters_display_list[] = "Con Gadget";
        if($search_type !== 'all') $active_filters_display_list[] = "Cerca solo in: " . ($search_type === 'comics' ? 'Albi' : 'Storie');
        ?>
        <?php if (!empty($active_filters_display_list) && !$special_message): ?>
            <p class="results-info">Risultati per <small><?php echo implode("; ", $active_filters_display_list); ?></small></p>
        <?php elseif (!$special_message && empty($active_filters_display_list) && !empty($search_query_display)): ?>
             <p class="results-info">Risultati per la ricerca testuale: "<?php echo htmlspecialchars($search_query_display); ?>"</p>
        <?php elseif (!$special_message && empty($active_filters_display_list) && empty($search_query_display)): ?>
             <p class="results-info">Nessun filtro specifico applicato. Visualizzazione di tutti i risultati.</p>
        <?php endif; ?>

        <div class="search-results-tabs">
            <?php 
            $show_comics_tab = ($search_type === 'all' || $search_type === 'comics');
            $show_stories_tab = ($search_type === 'all' || $search_type === 'stories');
            $comics_tab_active_class = ''; $stories_tab_active_class = '';
            if (isset($_GET['page_s'])) { $stories_tab_active_class = 'active'; $comics_tab_active_class = ''; } 
            elseif (isset($_GET['page_c'])) { $comics_tab_active_class = 'active'; $stories_tab_active_class = ''; } 
            else {
                if ($show_comics_tab && ($search_type === 'comics' || ($search_type === 'all' && $total_comics_found > 0))) { $comics_tab_active_class = 'active'; } 
                elseif ($show_stories_tab) { $stories_tab_active_class = 'active'; }
                if ($comics_tab_active_class === 'active' && $stories_tab_active_class === 'active' && $total_comics_found === 0) { $comics_tab_active_class = ''; }
            }
            ?>
            <?php if ($show_comics_tab): ?>
            <button class="tab-link-search <?php echo $comics_tab_active_class; ?>" onclick="openSearchResultsTab(event, 'ComicsResults')">
                Albi <span class="result-count-badge"><?php echo $total_comics_found; ?></span>
            </button>
            <?php endif; ?>
            <?php if ($show_stories_tab): ?>
            <button class="tab-link-search <?php echo $stories_tab_active_class; ?>" onclick="openSearchResultsTab(event, 'StoriesResults')">
                Storie <span class="result-count-badge"><?php echo $total_stories_found; ?></span>
            </button>
            <?php endif; ?>
        </div>
        
        <?php if ($show_comics_tab): ?>
        <div id="ComicsResults" class="search-tab-pane <?php echo $comics_tab_active_class; ?>">
            <?php if (!empty($search_results_comics)): ?>
                <div class="comic-grid">
                    <?php foreach ($search_results_comics as $comic): ?>
                        <div class="comic-card">
                            <a href="comic_detail.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>">
                                <?php if ($comic['cover_image']): ?>
                                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($comic['cover_image']); ?>" alt="Copertina <?php echo htmlspecialchars($comic['issue_number']); ?>" loading="lazy">
                                <?php else: ?>
                                    <?php echo generate_comic_placeholder_cover(htmlspecialchars($comic['issue_number']), 190, 260); ?>
                                <?php endif; ?>
                                <h3>Topolino #<?php echo htmlspecialchars($comic['issue_number']); ?></h3>
                                <?php if (!empty($comic['title'])): ?>
                                    <p class="comic-subtitle"><em><?php echo htmlspecialchars($comic['title']); ?></em></p>
                                <?php endif; ?>
                                <div class="comic-card-footer">
                                     <p class="comic-date"><?php echo $comic['publication_date'] ? format_date_italian($comic['publication_date']) : 'Data N/D'; ?></p>
                                     <?php if (isset($comic['comment_count']) && $comic['comment_count'] > 0): ?>
                                         <span class="comic-comment-count" title="<?php echo $comic['comment_count']; ?> commenti">
                                             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15c4.418 0 8-3.134 8-7s-3.582-7-8-7-8 3.134-8 7c0 1.76.743 3.37 1.97 4.6-.097 1.016-.417 2.13-.771 2.966-.079.186.074.394.273.362 2.256-.37 3.597-.938 4.18-1.234A9.06 9.06 0 0 0 8 15z"/></svg>
                                             <?php echo $comic['comment_count']; ?>
                                         </span>
                                     <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($total_pages_comics > 1): ?>
                <div class="pagination-controls">
                    <?php $params_pag_c = $_GET; unset($params_pag_c['page_c'], $params_pag_c['page_s']); $base_url_pag_c = 'search.php?' . http_build_query($params_pag_c); ?>
                    <?php for ($i = 1; $i <= $total_pages_comics; $i++): ?>
                        <a href="<?php echo $base_url_pag_c . '&page_c=' . $i; ?>#ComicsResults" class="<?php echo ($i == $current_page_comics) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php else: ?><p>Nessun albo trovato per i criteri specificati.</p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($show_stories_tab): ?>
        <div id="StoriesResults" class="search-tab-pane <?php echo $stories_tab_active_class; ?>">
            <?php if (!empty($search_results_stories)): ?>
                 <ul class="story-result-list">
                    <?php foreach ($search_results_stories as $story): ?>
                        <li class="story-result-item">
                             <?php if ($story['first_page_image']): ?>
                                <div class="story-result-image">
                                    <a href="comic_detail.php?slug=<?php echo htmlspecialchars($story['slug']); ?>#story-item-<?php echo $story['story_id']; ?>"><img src="<?php echo UPLOADS_URL . htmlspecialchars($story['first_page_image']); ?>" alt="Prima pagina di <?php echo htmlspecialchars($story['story_title']); ?>" loading="lazy"></a>
                                </div>
                            <?php else: ?>
                                <div class="story-result-image">
                                     <a href="comic_detail.php?slug=<?php echo htmlspecialchars($story['slug']); ?>#story-item-<?php echo $story['story_id']; ?>"><?php echo generate_image_placeholder(htmlspecialchars($story['story_title']), 80, 110, 'story-list-placeholder'); ?></a>
                                </div>
                            <?php endif; ?>
                            <div class="story-result-details">
                                <h4><a href="comic_detail.php?slug=<?php echo htmlspecialchars($story['slug']); ?>#story-item-<?php echo $story['story_id']; ?>"><?php echo htmlspecialchars($story['story_title']); ?></a></h4>
                                <?php if(!empty($story['story_title_main'])): ?><p class="story-part-info">Saga: <?php echo htmlspecialchars($story['story_title_main']); if(!empty($story['part_number'])) echo ' (Parte ' . htmlspecialchars($story['part_number']).')'; ?></p><?php endif; ?>
                                <p class="comic-info">In: <a href="comic_detail.php?slug=<?php echo htmlspecialchars($story['slug']); ?>">Topolino #<?php echo htmlspecialchars($story['issue_number']); ?></a></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                 </ul>
                <?php if ($total_pages_stories > 1): ?>
                <div class="pagination-controls">
                    <?php $params_pag_s = $_GET; unset($params_pag_s['page_c'], $params_pag_s['page_s']); $base_url_pag_s = 'search.php?' . http_build_query($params_pag_s); ?>
                    <?php for ($i = 1; $i <= $total_pages_stories; $i++): ?>
                        <a href="<?php echo $base_url_pag_s . '&page_s=' . $i; ?>#StoriesResults" class="<?php echo ($i == $current_page_stories) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php else: ?><p>Nessuna storia trovata per i criteri specificati.</p><?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <p style="text-align:center; margin-top: 20px; margin-bottom: 30px;">
            Utilizza i campi sopra per una ricerca mirata oppure esplora il catalogo tramite i suggerimenti qui sotto.
        </p>
        <?php if ( !empty($popular_characters) || !empty($popular_authors) || !empty($popular_decades_links) ): ?>
            <div class="search-suggestions-container">
                <?php if (!empty($popular_characters)): ?>
                <section class="search-suggestion-section">
                    <h3>Esplora per Personaggio <small>(I più presenti nelle storie)</small></h3>
                    <div class="suggestion-items-grid character-suggestion-grid">
                        <?php foreach ($popular_characters as $char): ?>
                            <a href="search.php?character_name=<?php echo urlencode($char['name']); ?>&submit_search=1" class="suggestion-item" title="Cerca storie con <?php echo htmlspecialchars($char['name']); ?>">
                                 <?php if ($char['character_image']): ?>
                                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($char['character_image']); ?>" alt="<?php echo htmlspecialchars($char['name']); ?>" loading="lazy">
                                <?php else: ?>
                                    <?php echo generate_image_placeholder(htmlspecialchars($char['name']), 70, 70, 'suggestion-placeholder'); ?>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($char['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                <?php if (!empty($popular_authors)): ?>
                <section class="search-suggestion-section">
                    <h3>Esplora per Autore <small>(I più prolifici)</small></h3>
                    <div class="suggestion-items-grid author-suggestion-grid">
                        <?php foreach ($popular_authors as $author): ?>
                             <a href="search.php?author_name=<?php echo urlencode($author['name']); ?>&submit_search=1" class="suggestion-item" title="Cerca storie di <?php echo htmlspecialchars($author['name']); ?>">
                                <?php if ($author['person_image']): ?>
                                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($author['person_image']); ?>" alt="<?php echo htmlspecialchars($author['name']); ?>" loading="lazy">
                                <?php else: ?>
                                    <?php echo generate_image_placeholder(htmlspecialchars($author['name']), 70, 70, 'suggestion-placeholder'); ?>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($author['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                <?php if (!empty($popular_decades_links)): ?>
                <section class="search-suggestion-section">
                    <h3>Esplora per Decennio</h3>
                    <div class="suggestion-decade-links">
                        <?php foreach ($popular_decades_links as $decade): ?>
                            <a href="search.php?year_start=<?php echo $decade['year_start']; ?>&year_end=<?php echo $decade['year_end']; ?>&submit_search=1" class="btn btn-secondary btn-sm"> 
                                <?php echo htmlspecialchars($decade['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<script>
function openSearchResultsTab(evt, tabName) {
    let i, tabPanes, tabLinks;
    tabPanes = document.getElementsByClassName("search-tab-pane");
    for (i = 0; i < tabPanes.length; i++) { tabPanes[i].classList.remove("active"); }
    tabLinks = document.getElementsByClassName("tab-link-search");
    for (i = 0; i < tabLinks.length; i++) { tabLinks[i].classList.remove("active"); }
    const targetPane = document.getElementById(tabName);
    if (targetPane) { targetPane.classList.add("active"); }
    if (evt && evt.currentTarget) { evt.currentTarget.classList.add("active"); }
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($searched): ?>
        let defaultTabToOpen = document.querySelector('.search-tab-pane.active')?.id;
        if (!defaultTabToOpen) {
            defaultTabToOpen = document.querySelector('.search-tab-pane')?.id || null;
        }
        if (defaultTabToOpen) {
            const buttonToActivate = document.querySelector(`.search-results-tabs .tab-link-search[onclick*="'${defaultTabToOpen}'"]`);
            if (buttonToActivate) {
                openSearchResultsTab({currentTarget: buttonToActivate}, defaultTabToOpen);
            }
        }
    <?php endif; ?>
});
</script>

<?php
require_once 'includes/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) { $mysqli->close(); }
?>