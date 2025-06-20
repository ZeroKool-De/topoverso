<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Personaggi";
$current_letter = $_GET['letter'] ?? null;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Flag per determinare se è stata effettuata una ricerca attiva sul nome
$is_active_search = !empty($search_term);

$sort_options_char = [
    'name_asc' => 'Nome (A-Z)',
    'name_desc' => 'Nome (Z-A)',
    'most_stories' => 'Più Apparizioni',
    'least_stories' => 'Meno Apparizioni',
    'first_appearance_asc' => 'Prima Apparizione (Più Vecchie)',
    'first_appearance_desc' => 'Prima Apparizione (Più Nuove)',
    'latest_added' => 'Ultimi inseriti', // <-- NUOVA OPZIONE
];
$current_sort_char = $_GET['sort'] ?? 'name_asc';
if (!array_key_exists($current_sort_char, $sort_options_char)) {
    $current_sort_char = 'name_asc';
}

$items_per_page_char = 50;
$current_page_char = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page_char < 1) {
    $current_page_char = 1;
}

// Costruzione Query
// --- MODIFICATO: Aggiunto ch.slug ---
$base_query_fields_char = "SELECT ch.character_id, ch.name, ch.slug, ch.description, ch.character_image, 
                            COUNT(DISTINCT sc.story_id) as story_count,
                            ch.first_appearance_comic_id, ch.first_appearance_date,
                            c_app.issue_number AS first_appearance_issue_number";
$base_query_from_char = " FROM characters ch 
                          LEFT JOIN story_characters sc ON ch.character_id = sc.character_id
                          LEFT JOIN comics c_app ON ch.first_appearance_comic_id = c_app.comic_id";
$base_query_group_by_char = " GROUP BY ch.character_id, ch.name, ch.first_appearance_date";

$where_clauses_char = [];
$params_char = [];
$types_char = "";

if ($current_letter && preg_match('/^[A-Z]$/i', $current_letter)) {
    $where_clauses_char[] = "ch.name LIKE ?";
    $params_char[] = $current_letter . '%';
    $types_char .= "s";
} elseif ($current_letter === '0-9') {
    $where_clauses_char[] = "ch.name REGEXP '^[0-9]'";
} elseif ($current_letter === 'Altro') {
    $where_clauses_char[] = "ch.name REGEXP '^[^A-Za-z0-9]'";
}

if ($is_active_search) {
    $where_clauses_char[] = "ch.name LIKE ?";
    $search_like = "%" . $search_term . "%";
    $params_char[] = $search_like;
    $types_char .= "s";
}

$where_sql_char = "";
if (!empty($where_clauses_char)) {
    $where_sql_char = " WHERE " . implode(" AND ", $where_clauses_char);
}

// Conteggio totale
$total_chars_query_sql = "SELECT COUNT(DISTINCT ch.character_id) as total FROM characters ch" . $where_sql_char;
$stmt_total_char = $mysqli->prepare($total_chars_query_sql);
if (!empty($params_char)) {
    $stmt_total_char->bind_param($types_char, ...$params_char);
}
$stmt_total_char->execute();
$total_characters = $stmt_total_char->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_total_char->close();

$total_pages_char = ceil($total_characters / $items_per_page_char);
if ($current_page_char > $total_pages_char && $total_pages_char > 0) $current_page_char = $total_pages_char;
$offset_char = ($current_page_char - 1) * $items_per_page_char;

// Ordinamento
$order_by_sql_char = "";
switch ($current_sort_char) {
    case 'name_desc':
        $order_by_sql_char = " ORDER BY ch.name DESC";
        break;
    case 'most_stories':
        $order_by_sql_char = " ORDER BY story_count DESC, ch.name ASC";
        break;
    case 'least_stories':
        $order_by_sql_char = " ORDER BY story_count ASC, ch.name ASC";
        break;
    case 'first_appearance_asc':
        $order_by_sql_char = " ORDER BY CASE WHEN YEAR(ch.first_appearance_date) = 0 THEN 1 ELSE 0 END, ch.first_appearance_date ASC, ch.name ASC";
        break;
    case 'first_appearance_desc':
        $order_by_sql_char = " ORDER BY CASE WHEN YEAR(ch.first_appearance_date) = 0 THEN 1 ELSE 0 END, ch.first_appearance_date DESC, ch.name ASC";
        break;
    case 'latest_added':
        $order_by_sql_char = " ORDER BY ch.character_id DESC";
        break;
    case 'name_asc':
    default:
        $order_by_sql_char = " ORDER BY ch.name ASC";
        break;
}

// Recupero dati pagina
$chars_query_sql = $base_query_fields_char . $base_query_from_char . $where_sql_char . $base_query_group_by_char . $order_by_sql_char . " LIMIT ? OFFSET ?";
$stmt_chars = $mysqli->prepare($chars_query_sql);
if ($stmt_chars === false) {
    die("ERRORE MYSQL: (" . $mysqli->errno . ") " . $mysqli->error . "<br><br>QUERY: " . htmlspecialchars($chars_query_sql));
}
$current_params_char_data = $params_char;
$current_types_char_data = $types_char;
$current_params_char_data[] = $items_per_page_char;
$current_types_char_data .= "i";
$current_params_char_data[] = $offset_char;
$current_types_char_data .= "i";
if (!empty($current_types_char_data)) {
    $stmt_chars->bind_param($current_types_char_data, ...$current_params_char_data);
}
$stmt_chars->execute();
$characters = $stmt_chars->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_chars->close();

// Logica di reindirizzamento
if ($is_active_search && empty($current_letter) && count($characters) === 1) {
    $character_slug_to_redirect = $characters[0]['slug'];
    header('Location: ' . BASE_URL . 'character_detail.php?slug=' . urlencode($character_slug_to_redirect));
    exit;
}


// Navigazione alfabetica
$nav_params_for_alphabet_char = [];
if ($current_sort_char !== 'name_asc') $nav_params_for_alphabet_char['sort'] = $current_sort_char;
if (!empty($search_term)) $nav_params_for_alphabet_char['search'] = $search_term;
$letters_nav_char = generate_alphabetical_nav_with_params('characters_page.php', 'characters', 'name', $current_letter, $nav_params_for_alphabet_char);

require_once 'includes/header.php';
?>

<style>
    .character-grid { margin-top: 20px; }
    .controls-bar { margin-top: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 4px; border: 1px solid #dee2e6;}
    .controls-bar label { margin-right: 5px; font-weight: 500;}
    .controls-bar select { padding: 6px 10px; border-radius: 4px; border: 1px solid #ced4da;}
    .results-info { font-size: 0.9em; color: #495057; }
    .first-appearance-badge { font-size: 0.7em; padding: 2px 5px; background-color: #007bff; color: white; border-radius: 3px; margin-left: 5px; font-weight: bold; vertical-align: middle; }
    .character-card .first-app-info { font-size: 0.8em; color: #6c757d; margin-top: 3px; }
    .character-card img.character-image, 
    .character-card .character-image.placeholder-char-img svg { width: 120px; height: 120px; } /* Coerenza con il PHP */

</style>
<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <?php echo $letters_nav_char; ?>

    <div class="controls-bar">
        <form method="GET" action="characters_page.php" id="sortFormChars" class="form-inline" style="flex-grow: 1; display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
            <?php if ($current_letter): ?>
                <input type="hidden" name="letter" value="<?php echo htmlspecialchars($current_letter); ?>">
            <?php endif; ?>

            <div class="filter-group">
                <label for="search_char">Cerca:</label>
                <input type="text" name="search" id="search_char" placeholder="Nome o descrizione..." value="<?php echo htmlspecialchars($search_term); ?>" style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ced4da;">
            </div>

            <div class="filter-group">
                <label for="sort_chars">Ordina per:</label>
                <select name="sort" id="sort_chars" class="form-control">
                    <?php foreach ($sort_options_char as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($current_sort_char === $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($value); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Applica</button>
            <a href="characters_page.php" class="btn btn-sm btn-outline-secondary">Resetta</a>
        </form>

        <div class="action-buttons">
            <a href="prime_apparizioni.php" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-git-commit"><circle cx="12" cy="12" r="4"></circle><line x1="1.05" y1="12" x2="7" y2="12"></line><line x1="17.01" y1="12" x2="22.96" y2="12"></line></svg>
                Timeline Apparizioni
            </a>
        </div>
        <?php if ($total_characters > 0): ?>
        <span class="results-info">
            Trovati <?php echo $total_characters; ?> personaggi
        </span>
        <?php endif; ?>
    </div>
    <?php if (!empty($characters)): ?>
        <div class="character-grid">
            <?php foreach ($characters as $character): ?>
                <div class="character-card">
                    <?php // --- LINK MODIFICATO: Usa lo slug se disponibile ---
                    $character_link = !empty($character['slug']) ? 'character_detail.php?slug=' . urlencode($character['slug']) : 'character_detail.php?id=' . $character['character_id'];
                    ?>
                    <a href="<?php echo $character_link; ?>">
                        <?php if ($character['character_image']): ?>
                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($character['character_image']); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>" class="character-image">
                        <?php else: ?>
                            <?php echo generate_image_placeholder(htmlspecialchars($character['name']), 120, 120, 'character-image placeholder-char-img'); ?>
                        <?php endif; ?>
                        <h3>
                            <?php echo htmlspecialchars($character['name']); ?>
                        </h3>
                        <p class="story-count">Apparizioni: <?php echo $character['story_count']; ?></p>
                        <?php if ($character['first_appearance_issue_number']): ?>
                            <p class="first-app-info">
                                1ª App: #<?php echo htmlspecialchars($character['first_appearance_issue_number']); ?>
                                <?php if($character['first_appearance_date']): ?>
                                    (<?php echo date("Y", strtotime($character['first_appearance_date'])); ?>)
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                         <span class="read-more">Vedi scheda &raquo;</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages_char > 1): ?>
        <div class="pagination-controls">
            <?php
                $base_url_params_for_pagination_char = [];
                if ($current_sort_char !== 'name_asc') {
                    $base_url_params_for_pagination_char['sort'] = $current_sort_char;
                }
                if ($current_letter) {
                    $base_url_params_for_pagination_char['letter'] = $current_letter;
                }
                if (!empty($search_term)) {
                    $base_url_params_for_pagination_char['search'] = $search_term;
                }

                $base_url_filename_char = 'characters_page.php';
                $query_string_for_page_char = http_build_query($base_url_params_for_pagination_char);
                
                $base_link_char = $base_url_filename_char;
                if (!empty($query_string_for_page_char)) {
                    $base_link_char .= '?' . $query_string_for_page_char;
                    $separator_for_page_char = '&';
                } else {
                    $separator_for_page_char = '?';
                }
            ?>
            <?php if ($current_page_char > 1): ?>
                <a href="<?php echo rtrim($base_link_char, '&?') . $separator_for_page_char; ?>page=<?php echo $current_page_char - 1; ?>">« Precedente</a>
            <?php else: ?>
                <span class="disabled">« Precedente</span>
            <?php endif; ?>
            <?php 
            $num_links_edges_char = 2; $num_links_vicino_char = 2; 
            for ($i = 1; $i <= $total_pages_char; $i++):
                if ($i == $current_page_char): ?> <span class="current-page"><?php echo $i; ?></span>
                <?php elseif ($i <= $num_links_edges_char || $i > $total_pages_char - $num_links_edges_char || ($i >= $current_page_char - $num_links_vicino_char && $i <= $current_page_char + $num_links_vicino_char) ): ?>
                    <a href="<?php echo rtrim($base_link_char, '&?') . $separator_for_page_char; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php elseif (
                    ($i == $num_links_edges_char + 1 && $current_page_char > $num_links_edges_char + $num_links_vicino_char + 1) ||
                    ($i == $total_pages_char - $num_links_edges_char && $current_page_char < $total_pages_char - $num_links_edges_char - $num_links_vicino_char)
                 ):
                    if (!isset($dots_shown) || $dots_shown < 2) {
                        echo '<span class="disabled">...</span>';
                        $dots_shown = isset($dots_shown) ? $dots_shown + 1 : 1;
                    }
                ?>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($current_page_char < $total_pages_char): ?>
                <a href="<?php echo rtrim($base_link_char, '&?') . $separator_for_page_char; ?>page=<?php echo $current_page_char + 1; ?>">Successiva »</a>
            <?php else: ?>
                <span class="disabled">Successiva »</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php elseif ($current_letter): ?>
        <p style="text-align:center; margin-top:30px;">Nessun personaggio trovato che inizia con la lettera '<?php echo htmlspecialchars($current_letter); ?>'.</p>
    <?php else: ?>
        <p style="text-align:center; margin-top:30px;">Nessun personaggio trovato nel database.</p>
    <?php endif; ?>
</div>
<?php
$mysqli->close();
require_once 'includes/footer.php';
?>