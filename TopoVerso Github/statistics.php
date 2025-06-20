<?php
require_once 'config/config.php'; // Contiene session_start()
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 

$page_title = "Approfondimenti e Statistiche";

// --- STATISTICHE GENERALI ---
$total_comics = 0;
$result_comics_count = $mysqli->query("SELECT COUNT(*) AS total FROM comics");
if ($result_comics_count) {
    $total_comics = $result_comics_count->fetch_assoc()['total'] ?? 0;
    $result_comics_count->free();
}

$total_stories = 0;
$result_stories_count = $mysqli->query("SELECT COUNT(*) AS total FROM stories");
if ($result_stories_count) {
    $total_stories = $result_stories_count->fetch_assoc()['total'] ?? 0;
    $result_stories_count->free();
}

$total_characters = 0;
$result_characters_count = $mysqli->query("SELECT COUNT(*) AS total FROM characters");
if ($result_characters_count) {
    $total_characters = $result_characters_count->fetch_assoc()['total'] ?? 0;
    $result_characters_count->free();
}

$total_persons = 0;
$result_persons_count = $mysqli->query("SELECT COUNT(*) AS total FROM persons");
if ($result_persons_count) {
    $total_persons = $result_persons_count->fetch_assoc()['total'] ?? 0;
    $result_persons_count->free();
}

$total_series = 0;
$result_series_count = $mysqli->query("SELECT COUNT(*) AS total FROM story_series");
if ($result_series_count) {
    $total_series = $result_series_count->fetch_assoc()['total'] ?? 0;
    $result_series_count->free();
}

$total_comics_with_gadgets = 0;
$result_gadgets = $mysqli->query("SELECT COUNT(*) AS total FROM comics WHERE gadget_name IS NOT NULL AND gadget_name != ''");
if ($result_gadgets) {
    $total_comics_with_gadgets = $result_gadgets->fetch_assoc()['total'] ?? 0;
    $result_gadgets->free();
}

// --- CLASSIFICHE "TOP N" ---
$limit_top_n = 5; 

// 1. Autore/i con più storie (contributi a storie)
$top_authors = [];
$sql_top_authors = "
    SELECT p.person_id, p.name, COUNT(DISTINCT sp.story_id) AS contribution_count 
    FROM persons p
    JOIN story_persons sp ON p.person_id = sp.person_id
    GROUP BY p.person_id, p.name
    ORDER BY contribution_count DESC, p.name ASC
    LIMIT ?";
$stmt_top_authors = $mysqli->prepare($sql_top_authors);
if ($stmt_top_authors) {
    $stmt_top_authors->bind_param("i", $limit_top_n);
    $stmt_top_authors->execute();
    $result_top_authors = $stmt_top_authors->get_result();
    if ($result_top_authors) {
        $top_authors = $result_top_authors->fetch_all(MYSQLI_ASSOC);
        $result_top_authors->free();
    }
    $stmt_top_authors->close();
}

// 2. Personaggio/i con più apparizioni
$top_characters_appearances = [];
$sql_top_characters = "
    SELECT ch.character_id, ch.name, COUNT(DISTINCT sc.story_id) AS appearance_count
    FROM characters ch
    JOIN story_characters sc ON ch.character_id = sc.character_id
    GROUP BY ch.character_id, ch.name
    ORDER BY appearance_count DESC, ch.name ASC
    LIMIT ?";
$stmt_top_characters = $mysqli->prepare($sql_top_characters);
if ($stmt_top_characters) {
    $stmt_top_characters->bind_param("i", $limit_top_n);
    $stmt_top_characters->execute();
    $result_top_characters = $stmt_top_characters->get_result();
    if ($result_top_characters) {
        $top_characters_appearances = $result_top_characters->fetch_all(MYSQLI_ASSOC);
        $result_top_characters->free();
    }
    $stmt_top_characters->close();
}

// 3. Serie con più episodi (numero di storie collegate)
$top_series_episodes = [];
$sql_top_series = "
    SELECT ss.series_id, ss.title, COUNT(DISTINCT s.story_id) AS episode_count
    FROM story_series ss
    JOIN stories s ON ss.series_id = s.series_id
    GROUP BY ss.series_id, ss.title
    ORDER BY episode_count DESC, ss.title ASC
    LIMIT ?";
$stmt_top_series = $mysqli->prepare($sql_top_series);
if ($stmt_top_series) {
    $stmt_top_series->bind_param("i", $limit_top_n);
    $stmt_top_series->execute();
    $result_top_series = $stmt_top_series->get_result();
    if ($result_top_series) {
        $top_series_episodes = $result_top_series->fetch_all(MYSQLI_ASSOC);
        $result_top_series->free();
    }
    $stmt_top_series->close();
}

// 4. Anni con più albi
$top_years_comics = [];
$sql_top_years = "
    SELECT YEAR(publication_date) AS publication_year, COUNT(comic_id) AS comic_count
    FROM comics
    WHERE publication_date IS NOT NULL
    GROUP BY publication_year
    ORDER BY comic_count DESC, publication_year DESC
    LIMIT ?";
$stmt_top_years = $mysqli->prepare($sql_top_years);
if ($stmt_top_years) {
    $stmt_top_years->bind_param("i", $limit_top_n);
    $stmt_top_years->execute();
    $result_top_years = $stmt_top_years->get_result();
    if ($result_top_years) {
        $top_years_comics = $result_top_years->fetch_all(MYSQLI_ASSOC);
        $result_top_years->free();
    }
    $stmt_top_years->close();
}

// Decadi con più albi
$top_decades_comics = [];
$sql_top_decades = "
    SELECT (FLOOR(YEAR(publication_date) / 10) * 10) AS decade_start, COUNT(comic_id) AS comic_count
    FROM comics
    WHERE publication_date IS NOT NULL
    GROUP BY decade_start
    ORDER BY comic_count DESC, decade_start DESC
    LIMIT ?";
$stmt_top_decades = $mysqli->prepare($sql_top_decades);
if ($stmt_top_decades) {
    $stmt_top_decades->bind_param("i", $limit_top_n);
    $stmt_top_decades->execute();
    $result_top_decades = $stmt_top_decades->get_result();
    if ($result_top_decades) {
        $top_decades_comics = $result_top_decades->fetch_all(MYSQLI_ASSOC);
        $result_top_decades->free();
    }
    $stmt_top_decades->close();
}

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="statistics-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
            </div>
            <h3>Albi Presenti</h3>
            <p class="count"><?php echo number_format($total_comics); ?></p>
            <a href="<?php echo BASE_URL; ?>index.php" class="stat-link">Vedi Albi &raquo;</a>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            </div>
            <h3>Storie Catalogate</h3>
            <p class="count"><?php echo number_format($total_stories); ?></p>
            <a href="<?php echo BASE_URL; ?>search.php" class="stat-link">Cerca Storie &raquo;</a>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                 <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
            </div>
            <h3>Personaggi Presenti</h3>
            <p class="count"><?php echo number_format($total_characters); ?></p>
            <a href="<?php echo BASE_URL; ?>characters_page.php" class="stat-link">Vedi Personaggi &raquo;</a>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <h3>Autori Registrati</h3>
            <p class="count"><?php echo number_format($total_persons); ?></p>
            <a href="<?php echo BASE_URL; ?>authors_page.php" class="stat-link">Vedi Autori &raquo;</a>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            </div>
            <h3>Serie di Storie</h3>
            <p class="count"><?php echo number_format($total_series); ?></p>
            <a href="<?php echo BASE_URL; ?>series_list.php" class="stat-link">Vedi Serie &raquo;</a>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
            </div>
            <h3>Albi con Gadget</h3>
            <p class="count"><?php echo number_format($total_comics_with_gadgets); ?></p>
            <a href="<?php echo BASE_URL; ?>search.php?q=gadget" class="stat-link">Cerca Albi con Gadget &raquo;</a>
        </div>
    </div>

    <hr style="margin-top: 30px; margin-bottom: 30px;">
    
    <div class="additional-stats-section">
        <h2>Approfondimenti e Classifiche</h2>
        <div class="stats-row">
            <div class="stat-list-card">
                <h3>Autori Più Prolifici <small>(Top <?php echo $limit_top_n; ?> per contributi a storie)</small></h3>
                <?php if (!empty($top_authors)): ?>
                    <ul>
                        <?php foreach ($top_authors as $author): ?>
                            <li>
                                <a href="<?php echo BASE_URL; ?>author_detail.php?id=<?php echo $author['person_id']; ?>">
                                    <?php echo htmlspecialchars($author['name']); ?>
                                </a> (<?php echo $author['contribution_count']; ?> storie)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Nessun dato sugli autori disponibile.</p>
                <?php endif; ?>
            </div>

            <div class="stat-list-card">
                <h3>Personaggi con Più Apparizioni <small>(Top <?php echo $limit_top_n; ?>)</small></h3>
                <?php if (!empty($top_characters_appearances)): ?>
                    <ul>
                        <?php foreach ($top_characters_appearances as $character): ?>
                            <li>
                                <a href="<?php echo BASE_URL; ?>character_detail.php?id=<?php echo $character['character_id']; ?>">
                                    <?php echo htmlspecialchars($character['name']); ?>
                                </a> (<?php echo $character['appearance_count']; ?> apparizioni)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Nessun dato sui personaggi disponibile.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-list-card">
                <h3>Serie con Più Storie <small>(Top <?php echo $limit_top_n; ?>)</small></h3>
                <?php if (!empty($top_series_episodes)): ?>
                    <ul>
                        <?php foreach ($top_series_episodes as $series_item): ?>
                            <li>
                                <a href="<?php echo BASE_URL; ?>series_detail.php?id=<?php echo $series_item['series_id']; ?>">
                                    <?php echo htmlspecialchars($series_item['title']); ?>
                                </a> (<?php echo $series_item['episode_count']; ?> storie)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Nessun dato sulle serie disponibile.</p>
                <?php endif; ?>
            </div>
            
            <div class="stat-list-card">
                <h3>Anni con Più Uscite <small>(Top <?php echo $limit_top_n; ?>)</small></h3>
                <?php if (!empty($top_years_comics)): ?>
                    <ul>
                        <?php foreach ($top_years_comics as $year_data): ?>
                            <li>
                                <a href="<?php echo BASE_URL; ?>by_year.php?year=<?php echo $year_data['publication_year']; ?>">
                                    Anno <?php echo htmlspecialchars($year_data['publication_year']); ?>
                                </a> (<?php echo $year_data['comic_count']; ?> albi)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Nessun dato sugli anni di pubblicazione disponibile.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stats-row">
             <div class="stat-list-card">
                <h3>Decadi con Più Uscite <small>(Top <?php echo $limit_top_n; ?>)</small></h3>
                <?php if (!empty($top_decades_comics)): ?>
                    <ul>
                        <?php foreach ($top_decades_comics as $decade_data): ?>
                            <li>
                                Anni <?php echo htmlspecialchars($decade_data['decade_start']); ?>-<?php echo htmlspecialchars($decade_data['decade_start'] + 9); ?>
                                 (<?php echo $decade_data['comic_count']; ?> albi)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Nessun dato sulle decadi di pubblicazione disponibile.</p>
                <?php endif; ?>
            </div>
            <div class="stat-list-card"> </div> 
        </div>
    </div>
</div>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>