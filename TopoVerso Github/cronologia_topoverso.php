<?php
require_once 'config/config.php';
// db_connect.php e functions.php sono ora inclusi da config.php

$page_title = "Cronologia Storica di Topolino";

// --- IMPOSTAZIONI DI ORDINAMENTO ---
$sort_options_event = [
    'date_desc' => 'Data Evento (Più Recenti)',
    'date_asc' => 'Data Evento (Meno Recenti)',
    'title_asc' => 'Titolo Evento (A-Z)',
    'title_desc' => 'Titolo Evento (Z-A)',
];
$current_sort_event = $_GET['sort'] ?? 'date_desc'; // Default: più recenti prima
if (!array_key_exists($current_sort_event, $sort_options_event)) {
    $current_sort_event = 'date_desc';
}

// --- IMPOSTAZIONI DI PAGINAZIONE ---
$limit_options_event = [15, 30, 50, 100];
$items_per_page = 15; // Default
if (isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options_event)) {
    $items_per_page = (int)$_GET['limit'];
}

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// Filtro Categoria (già presente)
$selected_category = $_GET['category'] ?? 'all';
$event_categories = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $result_cat = $mysqli->query("SELECT DISTINCT category FROM historical_events WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    if ($result_cat) {
        while ($row_cat = $result_cat->fetch_assoc()) {
            $event_categories[] = $row_cat['category'];
        }
        $result_cat->free();
    }
}

// --- COSTRUZIONE QUERY PER CONTEGGIO TOTALE ---
$count_sql = "SELECT COUNT(event_id) as total FROM historical_events";
$where_clauses_event_count = [];
$params_event_count = [];
$types_event_count = "";

if ($selected_category !== 'all' && !empty($selected_category)) {
    $where_clauses_event_count[] = "category = ?";
    $params_event_count[] = $selected_category;
    $types_event_count .= "s";
}
if (!empty($where_clauses_event_count)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_clauses_event_count);
}

$total_events = 0;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt_total_events = $mysqli->prepare($count_sql);
    if ($stmt_total_events) {
        if (!empty($params_event_count)) {
            $stmt_total_events->bind_param($types_event_count, ...$params_event_count);
        }
        $stmt_total_events->execute();
        $total_result = $stmt_total_events->get_result();
        $total_events = $total_result->fetch_assoc()['total'] ?? 0;
        $stmt_total_events->close();
    } else {
        error_log("Errore preparazione conteggio eventi: " . $mysqli->error);
    }
}

$total_pages = ceil($total_events / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

// --- COSTRUZIONE QUERY PER RECUPERARE I DATI ---
$historical_events = [];
$query_events_sql = "SELECT event_id, event_title, event_description, event_date_start, event_date_end, category, related_issue_start, related_issue_end, event_image_path, related_story_id FROM historical_events"; // Seleziona i campi necessari
$where_clauses_event_data = [];
$params_event_data = [];
$types_event_data = "";

if ($selected_category !== 'all' && !empty($selected_category)) {
    $where_clauses_event_data[] = "category = ?";
    $params_event_data[] = $selected_category;
    $types_event_data .= "s";
}
if (!empty($where_clauses_event_data)) {
    $query_events_sql .= " WHERE " . implode(" AND ", $where_clauses_event_data);
}

// Applica l'ordinamento SQL
switch ($current_sort_event) {
    case 'date_asc':
        $query_events_sql .= " ORDER BY event_date_start ASC, event_title ASC";
        break;
    case 'title_asc':
        $query_events_sql .= " ORDER BY event_title ASC, event_date_start DESC";
        break;
    case 'title_desc':
        $query_events_sql .= " ORDER BY event_title DESC, event_date_start DESC";
        break;
    case 'date_desc':
    default:
        $query_events_sql .= " ORDER BY event_date_start DESC, event_title ASC";
        break;
}
$query_events_sql .= " LIMIT ? OFFSET ?";

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt_events = $mysqli->prepare($query_events_sql);
    if ($stmt_events) {
        $current_params_event_data = $params_event_data;
        $current_types_event_data = $types_event_data;

        $current_params_event_data[] = $items_per_page;
        $current_types_event_data .= "i";
        $current_params_event_data[] = $offset;
        $current_types_event_data .= "i";

        if (!empty($current_types_event_data)) {
            $stmt_events->bind_param($current_types_event_data, ...$current_params_event_data);
        }
        
        $stmt_events->execute();
        $result_h_events = $stmt_events->get_result();
        if ($result_h_events) {
            while ($row = $result_h_events->fetch_assoc()) {
                $row['direct_comic_id_link'] = null;
                if (!empty($row['related_issue_start']) && (empty($row['related_issue_end']) || $row['related_issue_start'] === $row['related_issue_end'])) {
                    $stmt_find_comic = $mysqli->prepare("SELECT comic_id FROM comics WHERE issue_number = ? LIMIT 1");
                    if ($stmt_find_comic) {
                        $stmt_find_comic->bind_param("s", $row['related_issue_start']);
                        $stmt_find_comic->execute();
                        $res_find_comic = $stmt_find_comic->get_result();
                        if ($comic_found = $res_find_comic->fetch_assoc()) {
                            $row['direct_comic_id_link'] = $comic_found['comic_id'];
                        }
                        $stmt_find_comic->close();
                    }
                }
                $historical_events[] = $row;
            }
            $result_h_events->free();
        }
        $stmt_events->close();
    } else {
        error_log("Errore preparazione query eventi: " . $mysqli->error);
    }
}

require_once 'includes/header.php';
?>

<style>
    .page-description { /* Già presente in style.css, ma si può sovrascrivere/aggiungere qui se necessario */
        text-align: center;
        font-size: 1.1em;
        color: #555;
        max-width: 700px; /* Aumentato leggermente per questa pagina */
        margin: 0 auto 30px auto;
    }
    .timeline-filter-bar {
        margin-bottom: 25px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
        border: 1px solid #e9ecef;
        display: flex;
        gap: 15px; 
        align-items: center;
        flex-wrap: wrap;
    }
    .timeline-filter-bar .filter-group {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .timeline-filter-bar label {
        font-weight: 500;
    }
    .timeline-filter-bar select, .timeline-filter-bar button {
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #ced4da;
        font-size: 0.95em; 
    }
    .timeline-filter-bar button {
        background-color: #007bff; /* Coerente con i bottoni primari */
        color: white;
        cursor: pointer;
        border-color: #007bff;
        transition: background-color 0.2s;
    }
    .timeline-filter-bar button:hover {
        background-color: #0056b3;
    }

    .historical-event-item {
        background-color: #fff;
        border: 1px solid #e0e0e0;
        border-left: 5px solid #007bff; 
        border-radius: 8px; /* Aumentato per un look più "card" */
        padding: 20px 25px; /* Aumentato padding laterale */
        margin-bottom: 30px; /* Più spazio tra gli item */
        box-shadow: 0 3px 7px rgba(0,0,0,0.08); /* Ombra leggermente più definita */
    }
    .historical-event-item h3 {
        margin-top: 0;
        margin-bottom: 10px; /* Leggermente ridotto se c'è il bordo sotto */
        color: #0056b3; 
        font-size: 1.7em; /* Titolo più grande */
        padding-bottom: 12px; /* Spazio per la linea */
        border-bottom: 2px solid #007bff; /* Linea solida sotto il titolo */
    }
    .event-date-display {
        font-size: 0.9em; /* Leggermente più piccolo */
        color: #666; /* Grigio più scuro */
        margin-bottom: 12px;
        margin-top: -8px; /* Avvicina la data al titolo se il titolo ha padding-bottom */
        font-style: italic;
        display: block; /* Assicura che vada a capo se necessario */
    }
    .event-category-badge {
        display: inline-block;
        background-color: #6c757d; 
        color: white;
        padding: 4px 10px; 
        font-size: 0.85em; 
        border-radius: 15px; 
        margin-bottom: 15px; 
        text-decoration: none; /* Rimuove sottolineatura se è un link */
        transition: background-color 0.2s;
    }
    .event-category-badge:hover {
        background-color: #545b62;
        color: white; /* Assicura che il testo rimanga bianco */
    }
    .event-description {
        line-height: 1.75; /* Leggermente aumentato per leggibilità */
        color: #343a40; /* Testo più scuro */
        margin-bottom: 15px; 
    }
    .event-related-issues {
        margin-top: 15px;
        padding-top: 10px; /* Aggiungi spazio sopra */
        border-top: 1px dashed #e0e0e0; /* Leggera linea di separazione */
        font-size: 0.9em;
        color: #495057;
    }
    .event-related-issues strong {
        color: #007bff;
    }
    .event-related-issues a {
        color: #0056b3;
        text-decoration: none;
        font-weight: 500;
    }
    .event-related-issues a:hover {
        text-decoration: underline;
    }
    .pagination-controls { /* Definizioni già presenti, verifica coerenza */
        margin-top: 30px;
        margin-bottom: 20px;
        text-align: center;
    }
    .pagination-controls a, 
    .pagination-controls span {
        margin: 0 4px;
        padding: 8px 14px; 
        text-decoration: none;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        color: #007bff;
        background-color: #fff;
        transition: background-color 0.2s, color 0.2s, border-color 0.2s;
        font-size: 0.9em;
    }
    .pagination-controls a:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
    }
    .pagination-controls span.current-page {
        background-color: #007bff;
        color: white;
        border-color: #007bff;
        font-weight: bold;
        cursor: default;
    }
    .pagination-controls span.disabled {
        color: #adb5bd;
        border-color: #e9ecef;
        background-color: #f8f9fa;
        cursor: default;
    }
</style>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="page-description">
        Scopri i momenti salienti, le curiosità e i cambiamenti che hanno segnato la storia della testata Topolino nel corso degli anni.
    </p>

    <div class="timeline-filter-bar">
        <form action="cronologia_topoverso.php" method="GET" id="eventFiltersForm" style="display: contents;">
            <div class="filter-group">
                <label for="category_filter">Categoria:</label>
                <select name="category" id="category_filter">
                    <option value="all" <?php echo ($selected_category === 'all') ? 'selected' : ''; ?>>Tutte le Categorie</option>
                    <?php foreach ($event_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($selected_category === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="sort_event">Ordina per:</label>
                <select name="sort" id="sort_event">
                    <?php foreach ($sort_options_event as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($current_sort_event === $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="limit_event">Eventi per Pagina:</label>
                <select name="limit" id="limit_event">
                    <?php foreach ($limit_options_event as $limit_val): ?>
                        <option value="<?php echo $limit_val; ?>" <?php echo ($items_per_page == $limit_val) ? 'selected' : ''; ?>>
                            <?php echo $limit_val; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php // Mantiene la pagina corrente nei link se i filtri vengono cambiati via GET
            if (isset($_GET['page']) && (int)$_GET['page'] > 1) {
                 echo '<input type="hidden" name="page" value="' . (int)$_GET['page'] . '">';
            }
            ?>
            <button type="submit">Applica</button>
        </form>
    </div>


    <?php if (!empty($historical_events)): ?>
        <?php foreach ($historical_events as $event): ?>
            <div class="historical-event-item" id="event-item-<?php echo $event['event_id']; ?>">
                <?php if (!empty($event['event_image_path'])): ?>
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($event['event_image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($event['event_title']); ?>" 
                         style="max-width: 250px; width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto; border:1px solid #ddd;">
                <?php endif; ?>
                <h3><?php echo htmlspecialchars($event['event_title']); ?></h3>
                <p class="event-date-display">
                    <?php echo format_date_italian($event['event_date_start'], "d F Y"); ?>
                    <?php if ($event['event_date_end'] && $event['event_date_end'] !== $event['event_date_start']): ?>
                        - <?php echo format_date_italian($event['event_date_end'], "d F Y"); ?>
                    <?php endif; ?>
                </p>
                <?php if ($event['category']): ?>
                    <a href="cronologia_topoverso.php?category=<?php echo urlencode($event['category']); ?>" class="event-category-badge" title="Filtra per categoria: <?php echo htmlspecialchars($event['category']); ?>"><?php echo htmlspecialchars($event['category']); ?></a>
                <?php endif; ?>
                <div class="event-description">
                    <?php echo nl2br(htmlspecialchars($event['event_description'])); ?>
                </div>

                <?php if ($event['related_issue_start'] || $event['related_issue_end']): ?>
                    <div class="event-related-issues">
                        <strong>Riferimento Albi:</strong>
                        <?php
                        $links_html = "";
                        if ($event['direct_comic_id_link']) {
                            $links_html = "<a href='" . BASE_URL . "comic_detail.php?id=" . $event['direct_comic_id_link'] . "#event-item-" . $event['event_id'] . "' title='Vedi albo #" . htmlspecialchars($event['related_issue_start']) . "'>Topolino #" . htmlspecialchars($event['related_issue_start']) . "</a>";
                        } else {
                            $issue_links = [];
                            if ($event['related_issue_start']) {
                                $issue_links[] = "<a href='" . BASE_URL . "search.php?q=" . urlencode($event['related_issue_start']) . "&search_type=comics&submit_search=1' title='Cerca albo #" . htmlspecialchars($event['related_issue_start']) . "'>#" . htmlspecialchars($event['related_issue_start']) . "</a>";
                            }
                            if ($event['related_issue_end'] && $event['related_issue_end'] !== $event['related_issue_start']) {
                                $issue_links[] = "<a href='" . BASE_URL . "search.php?q=" . urlencode($event['related_issue_end']) . "&search_type=comics&submit_search=1' title='Cerca albo #" . htmlspecialchars($event['related_issue_end']) . "'>#" . htmlspecialchars($event['related_issue_end']) . "</a>";
                            }
                            $links_html = implode(' - ', $issue_links);
                        }
                        echo $links_html;
                        ?>
                        <?php // Link alla storia specifica se presente
                        if ($event['related_story_id'] && $event['direct_comic_id_link']) {
                            echo " (Storia specifica: <a href='" . BASE_URL . "comic_detail.php?id=" . $event['direct_comic_id_link'] . "#story-item-" . $event['related_story_id'] . "' title='Vedi storia correlata'>Dettagli Storia</a>)";
                        } elseif ($event['related_story_id']) {
                            echo " (ID Storia: " . htmlspecialchars($event['related_story_id']). " - Albo di riferimento per dettagli storia: " . htmlspecialchars($event['related_issue_start']) . ")";
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-controls">
                <?php
                $base_pag_url_params = [];
                if ($selected_category !== 'all') $base_pag_url_params['category'] = $selected_category;
                if ($current_sort_event !== 'date_desc') $base_pag_url_params['sort'] = $current_sort_event;
                if ($items_per_page !== 15) $base_pag_url_params['limit'] = $items_per_page;
                
                $base_pag_url_for_pagination = BASE_URL . "cronologia_topoverso.php?" . http_build_query($base_pag_url_params);
                $separator_for_pagination = empty($base_pag_url_params) ? '?' : '&';
                ?>

                <?php if ($current_page > 1): ?>
                    <a href="<?php echo rtrim($base_pag_url_for_pagination, '&?') . $separator_for_pagination; ?>page=1#event-item-<?php echo $historical_events[0]['event_id'] ?? ''; ?>" title="Prima pagina">&laquo;&laquo;</a>
                    <a href="<?php echo rtrim($base_pag_url_for_pagination, '&?') . $separator_for_pagination; ?>page=<?php echo $current_page - 1; ?>#event-item-<?php echo $historical_events[0]['event_id'] ?? ''; ?>" title="Pagina precedente">&laquo; Prec.</a>
                <?php else: ?>
                    <span class="disabled">&laquo;&laquo;</span>
                    <span class="disabled">&laquo; Prec.</span>
                <?php endif; ?>

                <?php
                $num_links_edges = 1; $num_links_around = 1;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == $current_page): ?>
                        <span class="current-page"><?php echo $i; ?></span>
                    <?php elseif (
                        $i <= $num_links_edges ||
                        $i > $total_pages - $num_links_edges ||
                        ($i >= $current_page - $num_links_around && $i <= $current_page + $num_links_around)
                    ): ?>
                        <a href="<?php echo rtrim($base_pag_url_for_pagination, '&?') . $separator_for_pagination; ?>page=<?php echo $i; ?>#event-item-<?php echo $historical_events[0]['event_id'] ?? ''; ?>"><?php echo $i; ?></a>
                    <?php elseif (
                        ($i == $num_links_edges + 1 && $current_page > $num_links_edges + $num_links_around + 1 && ($current_page - $num_links_around > $num_links_edges +1) ) ||
                        ($i == $total_pages - $num_links_edges && $current_page < $total_pages - $num_links_edges - $num_links_around && ($current_page + $num_links_around < $total_pages - $num_links_edges) )
                     ):
                         echo '<span class="disabled">...</span>';
                    ?>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo rtrim($base_pag_url_for_pagination, '&?') . $separator_for_pagination; ?>page=<?php echo $current_page + 1; ?>#event-item-<?php echo $historical_events[0]['event_id'] ?? ''; ?>" title="Pagina successiva">Succ. &raquo;</a>
                    <a href="<?php echo rtrim($base_pag_url_for_pagination, '&?') . $separator_for_pagination; ?>page=<?php echo $total_pages; ?>#event-item-<?php echo $historical_events[0]['event_id'] ?? ''; ?>" title="Ultima pagina">&raquo;&raquo;</a>
                <?php else: ?>
                    <span class="disabled">Succ. &raquo;</span>
                    <span class="disabled">&raquo;&raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <p style="text-align:center; margin-top:30px;">Nessun evento storico trovato <?php if ($selected_category !== 'all') echo "per la categoria '".htmlspecialchars($selected_category)."'"; ?>.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventFiltersForm = document.getElementById('eventFiltersForm');
    if (eventFiltersForm) {
        const selects = eventFiltersForm.querySelectorAll('select');
        // Rimuoviamo l'event listener per il submit automatico se vogliamo usare il pulsante
        /*
        selects.forEach(select => {
            select.addEventListener('change', function() {
                const pageInput = eventFiltersForm.querySelector('input[name="page"]');
                if (pageInput) { // Se si cambia un filtro, torna alla prima pagina
                    pageInput.value = '1';
                }
                eventFiltersForm.submit();
            });
        });
        */
    }
    
    // Script per lo scroll all'ancora, se presente (modificato per essere più robusto)
    if (window.location.hash) {
        const hash = window.location.hash;
        // Tentativo di scrollare all'elemento DOPO che la pagina è completamente caricata,
        // specialmente se ci sono immagini che potrebbero alterare il layout.
        // window.onload è più affidabile di DOMContentLoaded per questo.
        window.addEventListener('load', function() {
            try {
                const targetElement = document.querySelector(hash);
                if (targetElement) {
                    const header = document.querySelector('header');
                    const headerHeight = header ? header.offsetHeight : 0;
                    const elementPosition = targetElement.getBoundingClientRect().top + window.pageYOffset;
                    const offsetPosition = elementPosition - headerHeight - 20; // 20px di margine

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            } catch (e) {
                console.warn("Errore nello scroll all'ancora (selettore non valido?):", hash, e);
            }
        });
    }
});
</script>

<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
require_once 'includes/footer.php';
?>