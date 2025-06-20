<?php
// topolinolib/classifica.php

require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Classifiche";

// --- IMPOSTAZIONI E FILTRI ---

// Anno selezionato (default: l'anno corrente)
$current_year = date('Y');
$selected_year = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int)$_GET['year'] : $current_year;
$page_title .= " per l'Anno " . $selected_year;

// Numero di risultati per pagina
$items_per_page = 100;

// Conteggio minimo di voti per entrare in classifica (puoi cambiarlo se vuoi)
$min_votes_threshold = 3;

// Paginazione per gli albi (usa 'page_c' per evitare conflitti)
$page_comics = isset($_GET['page_c']) ? max(1, (int)$_GET['page_c']) : 1;
$offset_comics = ($page_comics - 1) * $items_per_page;

// Paginazione per le storie (usa 'page_s' per evitare conflitti)
$page_stories = isset($_GET['page_s']) ? max(1, (int)$_GET['page_s']) : 1;
$offset_stories = ($page_stories - 1) * $items_per_page;


// --- RECUPERO DATI CLASSIFICA ALBI ---

// Conteggio totale albi che soddisfano i criteri per la paginazione
$stmt_count_comics = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM (
        SELECT 1 FROM comic_ratings cr
        JOIN comics c ON cr.comic_id = c.comic_id
        WHERE YEAR(c.publication_date) = ?
        GROUP BY cr.comic_id
        HAVING COUNT(cr.rating_id) >= ?
    ) AS qualifying_comics
");
$stmt_count_comics->bind_param("ii", $selected_year, $min_votes_threshold);
$stmt_count_comics->execute();
$total_comics = $stmt_count_comics->get_result()->fetch_row()[0] ?? 0;
$total_pages_comics = ceil($total_comics / $items_per_page);
$stmt_count_comics->close();

// Query per ottenere la classifica degli albi
$ranked_comics = [];
$sql_ranked_comics = "
    SELECT 
        c.comic_id, c.issue_number, c.title, c.publication_date, c.cover_image,
        AVG(cr.rating) AS average_rating,
        COUNT(cr.rating_id) AS vote_count
    FROM comics c
    JOIN comic_ratings cr ON c.comic_id = cr.comic_id
    WHERE YEAR(c.publication_date) = ?
    GROUP BY c.comic_id
    HAVING vote_count >= ?
    ORDER BY average_rating DESC, vote_count DESC, c.publication_date ASC
    LIMIT ? OFFSET ?
";
$stmt_comics = $mysqli->prepare($sql_ranked_comics);
$stmt_comics->bind_param("iiii", $selected_year, $min_votes_threshold, $items_per_page, $offset_comics);
$stmt_comics->execute();
$result_comics = $stmt_comics->get_result();
if ($result_comics) $ranked_comics = $result_comics->fetch_all(MYSQLI_ASSOC);
$stmt_comics->close();


// --- RECUPERO DATI CLASSIFICA STORIE ---

// Conteggio totale storie che soddisfano i criteri
$stmt_count_stories = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM (
        SELECT 1 FROM story_ratings sr
        JOIN stories s ON sr.story_id = s.story_id
        JOIN comics c ON s.comic_id = c.comic_id
        WHERE YEAR(c.publication_date) = ?
        GROUP BY sr.story_id
        HAVING COUNT(sr.rating_id) >= ?
    ) AS qualifying_stories
");
$stmt_count_stories->bind_param("ii", $selected_year, $min_votes_threshold);
$stmt_count_stories->execute();
$total_stories = $stmt_count_stories->get_result()->fetch_row()[0] ?? 0;
$total_pages_stories = ceil($total_stories / $items_per_page);
$stmt_count_stories->close();


// Query per ottenere la classifica delle storie
$ranked_stories = [];
$sql_ranked_stories = "
    SELECT 
        s.story_id, s.title AS story_title, s.first_page_image,
        c.comic_id, c.issue_number, c.title AS comic_title, c.publication_date, c.cover_image,
        AVG(sr.rating) AS average_rating,
        COUNT(sr.rating_id) AS vote_count
    FROM stories s
    JOIN story_ratings sr ON s.story_id = sr.story_id
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE YEAR(c.publication_date) = ?
    GROUP BY s.story_id
    HAVING vote_count >= ?
    ORDER BY average_rating DESC, vote_count DESC, c.publication_date ASC
    LIMIT ? OFFSET ?
";
$stmt_stories = $mysqli->prepare($sql_ranked_stories);
$stmt_stories->bind_param("iiii", $selected_year, $min_votes_threshold, $items_per_page, $offset_stories);
$stmt_stories->execute();
$result_stories = $stmt_stories->get_result();
if ($result_stories) $ranked_stories = $result_stories->fetch_all(MYSQLI_ASSOC);
$stmt_stories->close();


// Recupera tutti gli anni disponibili per il filtro dropdown
$available_years = [];
$year_result = $mysqli->query("SELECT DISTINCT YEAR(publication_date) AS year FROM comics WHERE publication_date IS NOT NULL ORDER BY year DESC");
if ($year_result) $available_years = $year_result->fetch_all(MYSQLI_ASSOC);


require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="page-description">
        Scopri gli albi e le storie più apprezzati dalla community, anno per anno.
        Le classifiche sono basate sui voti di tutti i lettori e richiedono un minimo di <?php echo $min_votes_threshold; ?> voti per essere valide.
    </p>

    <div class="ranking-filters">
        <form action="classifica.php" method="GET">
            <label for="year">Seleziona un Anno:</label>
            <select name="year" id="year" onchange="this.form.submit()">
                <?php foreach ($available_years as $year_row): ?>
                    <option value="<?php echo $year_row['year']; ?>" <?php echo ($selected_year == $year_row['year']) ? 'selected' : ''; ?>>
                        <?php echo $year_row['year']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-primary">Vai</button></noscript>
        </form>
    </div>

    <div class="tabs-container">
        <div class="tab-nav">
            <button class="tab-link active" onclick="openTab(event, 'Albi')">Classifica Albi</button>
            <button class="tab-link" onclick="openTab(event, 'Storie')">Classifica Storie</button>
        </div>

        <div id="Albi" class="tab-pane active">
            <h2>Migliori Albi del <?php echo $selected_year; ?></h2>
            <?php if (!empty($ranked_comics)): ?>
                <div class="ranking-grid">
                    <?php foreach ($ranked_comics as $comic): ?>
                        <a href="comic_detail.php?id=<?php echo $comic['comic_id']; ?>" class="comic-card ranking-card">
                            <?php if ($comic['cover_image']): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($comic['cover_image']); ?>" alt="Copertina <?php echo htmlspecialchars($comic['issue_number']); ?>">
                            <?php else: echo generate_comic_placeholder_cover(htmlspecialchars($comic['issue_number']), 200, 260); endif; ?>
                            <h3>Topolino #<?php echo htmlspecialchars($comic['issue_number']); ?></h3>
                            <p class="comic-date"><?php echo format_date_italian($comic['publication_date']); ?></p>
                            <div class="rating-info">
                                <span class="stars"><?php echo str_repeat('★', round($comic['average_rating'])) . str_repeat('☆', 5 - round($comic['average_rating'])); ?></span><br>
                                <small>Media: <?php echo number_format($comic['average_rating'], 2); ?>/5 (<?php echo $comic['vote_count']; ?> voti)</small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                 <?php if ($total_pages_comics > 1): ?>
                <div class="pagination-controls">
                    <?php for ($i = 1; $i <= $total_pages_comics; $i++): ?>
                        <a href="?year=<?php echo $selected_year; ?>&page_c=<?php echo $i; ?>#Albi" class="<?php echo ($i == $page_comics) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <p>Nessun albo con sufficienti voti trovato per il <?php echo $selected_year; ?>.</p>
            <?php endif; ?>
        </div>

        <div id="Storie" class="tab-pane">
            <h2>Migliori Storie del <?php echo $selected_year; ?></h2>
            <?php if (!empty($ranked_stories)): ?>
                <div class="story-ranking-list">
                    <?php foreach ($ranked_stories as $story): ?>
                        <div class="contribution-item">
                             <div class="contribution-cover">
                                <a href="comic_detail.php?id=<?php echo $story['comic_id']; ?>#story-item-<?php echo $story['story_id']; ?>">
                                <?php $image_to_show = !empty($story['first_page_image']) ? $story['first_page_image'] : $story['cover_image']; ?>
                                <?php if ($image_to_show): ?>
                                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($image_to_show); ?>" alt="Immagine per <?php echo htmlspecialchars($story['story_title']); ?>">
                                <?php else: echo generate_image_placeholder(htmlspecialchars($story['issue_number']), 70, 100, 'comic-list-placeholder'); endif; ?>
                                </a>
                            </div>
                            <div class="contribution-details">
                                <h4><a href="comic_detail.php?id=<?php echo $story['comic_id']; ?>#story-item-<?php echo $story['story_id']; ?>"><?php echo htmlspecialchars($story['story_title']); ?></a></h4>
                                <p class="comic-info">Da: <a href="comic_detail.php?id=<?php echo $story['comic_id']; ?>">Topolino #<?php echo htmlspecialchars($story['issue_number']); ?></a></p>
                                <div class="rating-info">
                                    <span class="stars"><?php echo str_repeat('★', round($story['average_rating'])) . str_repeat('☆', 5 - round($story['average_rating'])); ?></span>
                                    <small>Media: <?php echo number_format($story['average_rating'], 2); ?>/5 (<?php echo $story['vote_count']; ?> voti)</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                 <?php if ($total_pages_stories > 1): ?>
                <div class="pagination-controls">
                    <?php for ($i = 1; $i <= $total_pages_stories; $i++): ?>
                        <a href="?year=<?php echo $selected_year; ?>&page_s=<?php echo $i; ?>#Storie" class="<?php echo ($i == $page_stories) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <p>Nessuna storia con sufficienti voti trovata per il <?php echo $selected_year; ?>.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Script per la gestione dei tab
function openTab(evt, tabName) {
    let i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-pane");
    for (i = 0; i < tabcontent.length; i++) { tabcontent[i].classList.remove("active"); }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].classList.remove("active"); }
    const targetPane = document.getElementById(tabName);
    if (targetPane) { targetPane.classList.add("active"); }
    if (evt && evt.currentTarget) { evt.currentTarget.classList.add("active"); }
    
    // Aggiorna l'URL con l'hash per mantenere lo stato del tab
    if (history.pushState) {
        history.pushState(null, null, '#' + tabName);
    } else {
        window.location.hash = tabName;
    }
}

// Attiva il tab corretto al caricamento della pagina in base all'hash
document.addEventListener('DOMContentLoaded', function() {
    let initialTab = 'Albi'; // Tab di default
    if (window.location.hash) {
        const hashTarget = window.location.hash.substring(1);
        if (document.getElementById(hashTarget)) {
            initialTab = hashTarget;
        }
    }
    const initialButton = Array.from(document.getElementsByClassName("tab-link")).find(
        (btn) => btn.getAttribute('onclick').includes("'" + initialTab + "'")
    );
    if (initialButton) {
        openTab({ currentTarget: initialButton }, initialTab);
    }
});
</script>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>