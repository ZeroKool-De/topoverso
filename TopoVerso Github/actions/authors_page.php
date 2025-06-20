<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Autori";
$current_letter = $_GET['letter'] ?? null;
$selected_role = $_GET['role'] ?? '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Flag per determinare se è stata effettuata una ricerca attiva sul nome
$is_active_search = !empty($search_term);

// Recupera tutti i ruoli unici per il filtro
$all_roles = [];
$sql_roles = "(SELECT DISTINCT role FROM comic_persons WHERE role IS NOT NULL AND role != '')
              UNION
              (SELECT DISTINCT role FROM story_persons WHERE role IS NOT NULL AND role != '')
              ORDER BY role ASC";
$result_roles = $mysqli->query($sql_roles);
if ($result_roles) {
    while ($row_role = $result_roles->fetch_assoc()) {
        $all_roles[] = $row_role['role'];
    }
    $result_roles->free();
}

// Opzioni di Ordinamento
$sort_options = [
    'name_asc' => 'Nome (A-Z)',
    'name_desc' => 'Nome (Z-A)',
    'most_stories' => 'Più Contributi',
    'least_stories' => 'Meno Contributi',
];
$current_sort = $_GET['sort'] ?? 'name_asc';
if (!array_key_exists($current_sort, $sort_options)) {
    $current_sort = 'name_asc';
}

// Paginazione
$items_per_page = 50; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// Costruzione Query SQL
// --- MODIFICATO: Aggiunto p.slug ---
$base_query_fields = "SELECT p.person_id, p.name, p.slug, p.person_image, 
                        (SELECT COUNT(DISTINCT story_id) FROM story_persons sp_count WHERE sp_count.person_id = p.person_id) + 
                        (SELECT COUNT(DISTINCT comic_id) FROM comic_persons cp_count WHERE cp_count.person_id = p.person_id) as contribution_count";
$base_query_from = " FROM persons p";
$where_clauses = [];
$params = [];
$types = "";

if ($current_letter && preg_match('/^[A-Z]$/i', $current_letter)) {
    $where_clauses[] = "p.name LIKE ?";
    $params[] = $current_letter . '%';
    $types .= "s";
} elseif ($current_letter === '0-9') {
    $where_clauses[] = "p.name REGEXP '^[0-9]'";
} elseif ($current_letter === 'Altro') {
    $where_clauses[] = "p.name REGEXP '^[^A-Za-z0-9]'";
}

if (!empty($selected_role)) {
    $where_clauses[] = "EXISTS (SELECT 1 FROM story_persons sp_filter WHERE sp_filter.person_id = p.person_id AND sp_filter.role = ?) 
                        OR EXISTS (SELECT 1 FROM comic_persons cp_filter WHERE cp_filter.person_id = p.person_id AND cp_filter.role = ?)";
    $params[] = $selected_role;
    $params[] = $selected_role;
    $types .= "ss";
}

if ($is_active_search) {
    $where_clauses[] = "p.name LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= "s";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Conta il totale per la paginazione
$count_sql = "SELECT COUNT(DISTINCT p.person_id) as total FROM persons p" . $where_sql;
$stmt_total = $mysqli->prepare($count_sql);
if (!empty($params)) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total_authors = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_total->close();

$total_pages = ceil($total_authors / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

// Ordinamento
$order_by_sql = "";
switch ($current_sort) {
    case 'name_desc': $order_by_sql = " ORDER BY p.name DESC"; break;
    case 'most_stories': $order_by_sql = " ORDER BY contribution_count DESC, p.name ASC"; break;
    case 'least_stories': $order_by_sql = " ORDER BY contribution_count ASC, p.name ASC"; break;
    case 'name_asc': default: $order_by_sql = " ORDER BY p.name ASC"; break;
}

// Recupera i dati per la pagina corrente
$authors_query_sql = $base_query_fields . $base_query_from . $where_sql . " GROUP BY p.person_id" . $order_by_sql . " LIMIT ? OFFSET ?";
$stmt_authors = $mysqli->prepare($authors_query_sql);
$current_params_data = $params;
$current_types_data = $types;
$current_params_data[] = $items_per_page;
$current_types_data .= "i";
$current_params_data[] = $offset;
$current_types_data .= "i";

if (!empty($current_types_data)) {
    $stmt_authors->bind_param($current_types_data, ...$current_params_data);
}
$stmt_authors->execute();
$result_authors = $stmt_authors->get_result();
$authors = $result_authors ? $result_authors->fetch_all(MYSQLI_ASSOC) : [];
$stmt_authors->close();

// Logica di reindirizzamento
if ($is_active_search && empty($current_letter) && empty($selected_role) && count($authors) === 1) {
    $author_slug_to_redirect = $authors[0]['slug'];
    header('Location: ' . BASE_URL . 'author_detail.php?slug=' . urlencode($author_slug_to_redirect));
    exit;
}

// Genera navigazione alfabetica mantenendo i filtri
$nav_params_for_alphabet = [];
if ($current_sort !== 'name_asc') $nav_params_for_alphabet['sort'] = $current_sort;
if (!empty($selected_role)) $nav_params_for_alphabet['role'] = $selected_role;
if (!empty($search_term)) $nav_params_for_alphabet['search'] = $search_term;
$letters_nav = generate_alphabetical_nav_with_params('authors_page.php', 'persons', 'name', $current_letter, $nav_params_for_alphabet);

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <?php echo $letters_nav; ?>

    <div class="controls-bar">
        <form method="GET" action="authors_page.php" id="filterSortFormAuthors" class="form-inline" style="flex-grow: 1; display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
            <?php if ($current_letter): ?>
                <input type="hidden" name="letter" value="<?php echo htmlspecialchars($current_letter); ?>">
            <?php endif; ?>
            
            <div class="filter-group">
                <label for="search_author">Cerca:</label>
                <input type="text" name="search" id="search_author" placeholder="Nome o bio..." value="<?php echo htmlspecialchars($search_term); ?>" style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ced4da;">
            </div>
            
            <div class="filter-group">
                <label for="role_filter">Ruolo:</label>
                <select name="role" id="role_filter" class="form-control">
                    <option value="">-- Tutti i Ruoli --</option>
                    <?php foreach ($all_roles as $role_option): ?>
                        <option value="<?php echo htmlspecialchars($role_option); ?>" <?php echo ($selected_role === $role_option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($role_option)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="sort_authors">Ordina per:</label>
                <select name="sort" id="sort_authors" class="form-control">
                    <?php foreach ($sort_options as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($current_sort === $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($value); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Applica</button>
            <a href="authors_page.php" class="btn btn-sm btn-outline-secondary">Resetta</a>
        </form>
        <?php if ($total_authors > 0): ?>
        <span class="results-info">
            Trovati <?php echo $total_authors; ?> autori
        </span>
        <?php endif; ?>
    </div>


    <?php if (!empty($authors)): ?>
        <div class="author-grid">
            <?php foreach ($authors as $author_item): ?>
                <div class="author-card">
                    <?php // --- LINK MODIFICATO: Usa lo slug se disponibile ---
                    $author_link = !empty($author_item['slug']) ? 'author_detail.php?slug=' . urlencode($author_item['slug']) : 'author_detail.php?id=' . $author_item['person_id'];
                    ?>
                    <a href="<?php echo $author_link; ?>">
                        <?php if ($author_item['person_image']): ?>
                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($author_item['person_image']); ?>" alt="<?php echo htmlspecialchars($author_item['name']); ?>" class="author-image">
                        <?php else: ?>
                            <?php echo generate_image_placeholder(htmlspecialchars($author_item['name']), 150, 150, 'author-image placeholder-person-img'); ?>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($author_item['name']); ?></h3>
                        <p class="story-count">Contributi: <?php echo $author_item['contribution_count']; ?></p>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-controls">
            <?php 
                $base_pag_url_params = [];
                if ($current_sort !== 'name_asc') $base_pag_url_params['sort'] = $current_sort;
                if ($current_letter) $base_pag_url_params['letter'] = $current_letter;
                if (!empty($selected_role)) $base_pag_url_params['role'] = $selected_role;
                if (!empty($search_term)) $base_pag_url_params['search'] = $search_term;
                
                $base_pag_url = "authors_page.php?" . http_build_query($base_pag_url_params);
                $separator_pag = empty($base_pag_url_params) ? '?' : '&';
            ?>
            <?php if ($current_page > 1): ?>
                <a href="<?php echo rtrim($base_pag_url, '&?') . $separator_pag; ?>page=<?php echo $current_page - 1; ?>">« Precedente</a>
            <?php else: ?>
                <span class="disabled">« Precedente</span>
            <?php endif; ?>

            <?php 
            $num_links_edges = 2; $num_links_vicino = 2; 
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i == $current_page): ?> <span class="current-page"><?php echo $i; ?></span>
                <?php elseif (
                    $i <= $num_links_edges || 
                    $i > $total_pages - $num_links_edges || 
                    ($i >= $current_page - $num_links_vicino && $i <= $current_page + $num_links_vicino)
                ): ?>
                    <a href="<?php echo rtrim($base_pag_url, '&?') . $separator_pag; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php elseif (
                    ($i == $num_links_edges + 1 && $current_page > $num_links_edges + $num_links_vicino + 1) ||
                    ($i == $total_pages - $num_links_edges && $current_page < $total_pages - $num_links_edges - $num_links_vicino)
                 ):
                    if (!isset($dots_shown) || $dots_shown < 2) {
                        echo '<span class="disabled">...</span>';
                        $dots_shown = isset($dots_shown) ? $dots_shown + 1 : 1;
                    }
                ?>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo rtrim($base_pag_url, '&?') . $separator_pag; ?>page=<?php echo $current_page + 1; ?>">Successiva »</a>
            <?php else: ?>
                <span class="disabled">Successiva »</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php elseif ($current_letter || !empty($selected_role)): ?>
        <p style="text-align:center; margin-top:30px;">
            Nessun autore trovato per i criteri selezionati 
            <?php if ($current_letter) echo " (lettera '" . htmlspecialchars($current_letter) . "')"; ?>
            <?php if (!empty($selected_role)) echo " (ruolo '" . htmlspecialchars(ucfirst($selected_role)) . "')"; ?>.
        </p>
    <?php else: ?>
        <p style="text-align:center; margin-top:30px;">Nessun autore trovato nel database.</p>
    <?php endif; ?>
</div>

<style>
    /* Stili specifici (puoi spostarli in style.css) */
    .author-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; margin-top: 20px; }
    .author-card { background-color: #fff; border: 1px solid #e0e0e0; border-radius: 5px; text-align: center; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: box-shadow 0.2s; }
    .author-card:hover { box-shadow: 0 3px 7px rgba(0,0,0,0.1); }
    .author-card a { text-decoration: none; color: inherit; }
    .author-image, .placeholder-person-img svg { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; border: 3px solid #f0f0f0; }
    .placeholder-person-img svg { background-color: #e9ecef; color: #adb5bd; }
    .author-card h3 { font-size: 1.1em; margin-bottom: 5px; color: #2c3e50; }
    .story-count { font-size: 0.9em; color: #6c757d; }
    .controls-bar { background-color: #f8f9fa; padding: 10px 15px; border-radius: 4px; border: 1px solid #dee2e6; }
    .controls-bar label { margin-right: 8px; font-weight: 500; }
    .controls-bar select.form-control { padding: 6px 10px; border-radius: 4px; border: 1px solid #ced4da; font-size: 0.95em; height: auto; }
    .results-info { font-size: 0.9em; color: #495057; }
</style>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>