<?php
// topolinolib/first_appearances_list.php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Elenco Prime Apparizioni";

// --- LOGICA PAGINAZIONE, FILTRI E RICERCA ---

// Paginazione
$allowed_per_page = [50, 100, 250];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowed_per_page) ? (int)$_GET['per_page'] : 50;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
if ($current_page < 1) $current_page = 1;

// Filtri
$filter_letter = isset($_GET['letter']) ? substr(trim($_GET['letter']), 0, 1) : null;
if ($filter_letter && !ctype_alpha($filter_letter)) { $filter_letter = null; }
$filter_decade = isset($_GET['decade']) && is_numeric($_GET['decade']) ? (int)$_GET['decade'] : null;

// Ordinamento
$valid_sorts = ['name_asc', 'name_desc', 'date_asc', 'date_desc'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $valid_sorts) ? $_GET['sort'] : 'date_asc';

// ### INIZIO BLOCCO RICERCA ###
// Recupera il termine di ricerca
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
// ### FINE BLOCCO RICERCA ###

// Costruzione query
$params = [];
$types = '';
$where_clauses = [];

if ($filter_letter) {
    $where_clauses[] = "char_table.name LIKE ?";
    $params[] = $filter_letter . '%';
    $types .= 's';
}

if ($filter_decade) {
    $where_clauses[] = "YEAR(cfa.calculated_publication_date) BETWEEN ? AND ?";
    $params[] = $filter_decade;
    $params[] = $filter_decade + 9;
    $types .= 'ii';
}

// ### INIZIO BLOCCO RICERCA (WHERE) ###
if (!empty($search_query)) {
    $search_term_like = "%" . $search_query . "%";
    $where_clauses[] = "(char_table.name LIKE ? OR story_table.title LIKE ? OR comic_table.title LIKE ?)";
    $params[] = $search_term_like;
    $params[] = $search_term_like;
    $params[] = $search_term_like;
    $types .= 'sss';
}
// ### FINE BLOCCO RICERCA (WHERE) ###

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Query per il conteggio totale dei risultati (per la paginazione)
$count_sql = "
    SELECT COUNT(cfa.id) 
    FROM calculated_first_appearances cfa 
    JOIN characters char_table ON cfa.character_id = char_table.character_id
    LEFT JOIN stories story_table ON cfa.calculated_story_id = story_table.story_id
    LEFT JOIN comics comic_table ON cfa.calculated_comic_id = comic_table.comic_id
    $where_sql";
$total_results = 0;
$stmt_count = $mysqli->prepare($count_sql);
if ($stmt_count) { if (!empty($types)) { $stmt_count->bind_param($types, ...$params); } $stmt_count->execute(); $stmt_count->bind_result($total_results); $stmt_count->fetch(); $stmt_count->close(); }
$total_pages = ceil($total_results / $per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $per_page;

$order_by_clause = '';
switch ($sort) {
    case 'name_asc': $order_by_clause = 'char_table.name ASC'; break;
    case 'name_desc': $order_by_clause = 'char_table.name DESC'; break;
    case 'date_desc': $order_by_clause = 'cfa.calculated_publication_date DESC, char_table.name ASC'; break;
    case 'date_asc': default: $order_by_clause = 'cfa.calculated_publication_date ASC, char_table.name ASC'; break;
}

// Query principale per ottenere i dati della pagina corrente
$sql = "
    SELECT 
        cfa.calculated_publication_date,
        char_table.name AS character_name,
        char_table.slug AS character_slug,
        char_table.character_image,
        story_table.story_id,
        story_table.title AS story_title,
        comic_table.comic_id,
        comic_table.issue_number AS comic_issue_number,
        comic_table.title AS comic_title,
        comic_table.slug AS comic_slug,
        (SELECT GROUP_CONCAT(DISTINCT p.name ORDER BY FIELD(sp.role, 'Soggetto', 'Sceneggiatura', 'Testi', 'Disegni', 'Disegnatore') SEPARATOR ', ')
         FROM story_persons sp
         JOIN persons p ON sp.person_id = p.person_id
         WHERE sp.story_id = cfa.calculated_story_id
        ) as creators
    FROM calculated_first_appearances cfa
    JOIN characters char_table ON cfa.character_id = char_table.character_id
    LEFT JOIN stories story_table ON cfa.calculated_story_id = story_table.story_id
    LEFT JOIN comics comic_table ON cfa.calculated_comic_id = comic_table.comic_id
    $where_sql
    ORDER BY $order_by_clause
    LIMIT ? OFFSET ?
";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';
$appearances = [];
$stmt = $mysqli->prepare($sql);
if ($stmt) { $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); if ($result) { while ($row = $result->fetch_assoc()) { $appearances[] = $row; } $result->free(); } $stmt->close(); }

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <div class="page-header-with-action">
        <h1><i class="fa-solid fa-star-of-life" style="margin-right: 10px; color: #ffc107;"></i> Elenco Prime Apparizioni</h1>
        <a href="prime_apparizioni.php" class="btn btn-secondary-outline"><i class="fa-solid fa-timeline"></i> Vai alla Timeline Curata</a>
    </div>
    
    <div class="page-explanation">
        <h3><i class="fa-solid fa-circle-info"></i> Come funziona questa pagina?</h3>
        <p>Questo elenco è <strong>calcolato automaticamente</strong> e mostra la <strong>prima apparizione in assoluto</strong> di ogni personaggio, basandosi sulla storia con la data di pubblicazione più antica presente nel nostro database.</p>
        <p>A differenza della pagina "Timeline Apparizioni", che presenta una sequenza cronologica curata delle prime apparizioni più significative, questo elenco è uno strumento completo e in costante aggiornamento. Se oggi viene aggiunta una storia di Zio Paperone più vecchia di quelle già presenti, la sua prima apparizione qui si <strong>aggiornerà da sola</strong> per riflettere il nuovo dato!</p>
    </div>

    <div class="view-controls">
        <div class="filter-nav">
            <strong>Filtra per lettera:</strong>
            <?php foreach (range('A', 'Z') as $letter): ?><a href="?letter=<?php echo $letter; ?>" class="<?php echo ($filter_letter === $letter) ? 'active' : ''; ?>"><?php echo $letter; ?></a><?php endforeach; ?>
            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="clear-filter" title="Rimuovi filtro">Tutti</a>
        </div>
        <div class="filter-nav">
            <strong>Filtra per decennio:</strong>
            <?php for ($decade = 1930; $decade <= date('Y'); $decade += 10): ?><a href="?decade=<?php echo $decade; ?>" class="<?php echo ($filter_decade === $decade) ? 'active' : ''; ?>"><?php echo $decade; ?>s</a><?php endfor; ?>
            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="clear-filter" title="Rimuovi filtro">Tutti</a>
        </div>
        
        <?php // ### INIZIO BLOCCO HTML RICERCA ### ?>
        <div class="search-bar-container">
            <form method="GET" action="">
                <?php foreach ($_GET as $key => $value) { if ($key != 'q' && $key != 'page') echo "<input type='hidden' name='$key' value='" . htmlspecialchars($value) . "'>"; } ?>
                <div class="search-bar-group">
                    <input type="text" name="q" placeholder="Cerca personaggio, storia, titolo..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit">Cerca</button>
                </div>
            </form>
        </div>
        <?php // ### FINE BLOCCO HTML RICERCA ### ?>
        
        <div class="sort-and-view-controls">
             <div class="per-page-selector">
                <form method="GET" action="">
                    <?php foreach ($_GET as $key => $value) { if ($key != 'per_page' && $key != 'page') echo "<input type='hidden' name='$key' value='" . htmlspecialchars($value) . "'>"; } ?>
                    <label for="per_page">Mostra per pagina:</label>
                    <select name="per_page" id="per_page" onchange="this.form.submit()"><?php foreach ($allowed_per_page as $option): ?><option value="<?php echo $option; ?>" <?php echo ($per_page == $option) ? 'selected' : ''; ?>><?php echo $option; ?></option><?php endforeach; ?></select>
                </form>
            </div>
            <div class="sort-links">
                <strong>Ordina per:</strong>
                <a href="?sort=date_asc" class="<?php echo $sort === 'date_asc' ? 'active' : ''; ?>">Data</a>
                <a href="?sort=name_asc" class="<?php echo $sort === 'name_asc' ? 'active' : ''; ?>">Nome</a>
            </div>
            <button id="view-toggle-btn" class="view-toggle-btn" title="Cambia visualizzazione"><i class="fa-solid fa-grip"></i> Vista Griglia</button>
        </div>
    </div>
    
    <div class="results-summary">Visualizzando <strong><?php echo count($appearances); ?></strong> risultati (da <?php echo $offset + 1; ?> a <?php echo $offset + count($appearances); ?>) di <strong><?php echo $total_results; ?></strong> totali.</div>

    <?php if (!empty($appearances)): ?>
        <div id="grid-view" class="appearance-grid">
            <?php foreach ($appearances as $appearance): ?>
                <div class="appearance-card">
                    <a href="<?php echo !empty($appearance['character_slug']) ? 'character_detail.php?slug=' . urlencode($appearance['character_slug']) : '#'; ?>" title="Vedi dettagli per <?php echo htmlspecialchars($appearance['character_name']); ?>">
                        <div class="appearance-card-image-wrapper">
                            <?php if ($appearance['character_image']): ?><img src="<?php echo UPLOADS_URL . htmlspecialchars($appearance['character_image']); ?>" alt="Immagine di <?php echo htmlspecialchars($appearance['character_name']); ?>" loading="lazy"><?php else: ?><?php echo generate_image_placeholder(htmlspecialchars($appearance['character_name']), 200, 200, 'appearance-placeholder'); ?><?php endif; ?>
                        </div>
                        <div class="appearance-card-info">
                            <h3><?php echo htmlspecialchars($appearance['character_name']); ?></h3>
                            <?php if (!empty($appearance['creators'])): ?><p class="first-appearance-creator">Creato da: <strong><?php echo htmlspecialchars($appearance['creators']); ?></strong></p><?php endif; ?>
                            <p class="first-appearance-text">
                                Apparso in: <em>"<?php echo htmlspecialchars($appearance['story_title'] ?: 'Titolo non disponibile'); ?>"</em>
                                <br>su Topolino #<?php echo htmlspecialchars($appearance['comic_issue_number'] ?: 'N/A');?>
                                <?php if($appearance['calculated_publication_date']): ?> (<?php echo format_date_italian($appearance['calculated_publication_date'], 'd/m/Y'); ?>) <?php endif; ?>
                            </p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="list-view" class="hidden">
            <table class="table appearances-table">
                <thead><tr><th>Personaggio</th><th>Prima Storia</th><th>Albo di Apparizione</th><th class="text-center">Data Pubblicazione</th></tr></thead>
                <tbody><?php foreach ($appearances as $appearance): ?><tr>
                    <td class="character-cell"><a href="<?php echo BASE_URL; ?>character_detail.php?slug=<?php echo htmlspecialchars($appearance['character_slug']); ?>"><?php if (!empty($appearance['character_image'])): ?><img src="<?php echo UPLOADS_URL . htmlspecialchars($appearance['character_image']); ?>" alt="<?php echo htmlspecialchars($appearance['character_name']); ?>" class="character-avatar-small"><?php else: ?><div class="character-avatar-small placeholder"><?php echo generate_image_placeholder($appearance['character_name'], 40, 40); ?></div><?php endif; ?><span><?php echo htmlspecialchars($appearance['character_name']); ?></span></a></td>
                    <td><?php if ($appearance['comic_slug']): ?><a href="<?php echo BASE_URL; ?>comic_detail.php?slug=<?php echo htmlspecialchars($appearance['comic_slug']); ?>#story-item-<?php echo $appearance['story_id']; ?>"><?php echo htmlspecialchars($appearance['story_title']); ?></a><?php else: ?><span>Dato non disponibile</span><?php endif; ?></td>
                    <td><?php if ($appearance['comic_slug']): ?><a href="<?php echo BASE_URL; ?>comic_detail.php?slug=<?php echo htmlspecialchars($appearance['comic_slug']); ?>">Topolino #<?php echo htmlspecialchars($appearance['comic_issue_number']); ?></a><?php else: ?><span>Dato non disponibile</span><?php endif; ?></td>
                    <td class="text-center"><?php echo format_date_italian($appearance['calculated_publication_date'], 'd M Y'); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table>
        </div>
        <?php echo generate_pagination($current_page, $total_pages); ?>
    <?php else: ?>
        <div class="message info">Nessun personaggio trovato con i filtri correnti. Prova a <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>">rimuovere i filtri</a>.</div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('view-toggle-btn');
    const gridView = document.getElementById('grid-view');
    const listView = document.getElementById('list-view');
    if (toggleBtn && gridView && listView) {
        const icon = toggleBtn.querySelector('i');
        function setView(view) { if (view === 'list') { gridView.classList.add('hidden'); listView.classList.remove('hidden'); if(icon) icon.className = 'fa-solid fa-list-ul'; toggleBtn.innerHTML = '<i class="fa-solid fa-list-ul"></i> Vista Elenco'; localStorage.setItem('appearancesView', 'list'); } else { listView.classList.add('hidden'); gridView.classList.remove('hidden'); if(icon) icon.className = 'fa-solid fa-grip'; toggleBtn.innerHTML = '<i class="fa-solid fa-grip"></i> Vista Griglia'; localStorage.setItem('appearancesView', 'grid'); } }
        const preferredView = localStorage.getItem('appearancesView') || 'grid';
        setView(preferredView);
        toggleBtn.addEventListener('click', function () { const currentView = localStorage.getItem('appearancesView') || 'grid'; setView(currentView === 'grid' ? 'list' : 'grid'); });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>