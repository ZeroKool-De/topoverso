<?php
// topolinolib/prime_apparizioni.php

require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Timeline delle Prime Apparizioni";

// --- RECUPERO COLLEZIONE UTENTE (invariato) ---
$user_collection_ids = [];
if (isset($_SESSION['user_id_frontend'])) {
    $current_user_id = $_SESSION['user_id_frontend'];
    $sql_collection = "SELECT comic_id FROM user_collections WHERE user_id = ?";
    
    if ($stmt_collection = $mysqli->prepare($sql_collection)) {
        $stmt_collection->bind_param("i", $current_user_id);
        $stmt_collection->execute();
        $result_collection = $stmt_collection->get_result();
        while ($row = $result_collection->fetch_assoc()) {
            $user_collection_ids[$row['comic_id']] = true;
        }
        $stmt_collection->close();
    }
}

// --- WIDGET ACCADDE OGGI (invariato) ---
$anniversary_characters = [];
$sql_anniversary = "
    SELECT
        ch.character_id, ch.name, ch.slug, ch.character_image, c.publication_date
    FROM characters ch
    JOIN stories s ON ch.first_appearance_story_id = s.story_id
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE ch.first_appearance_story_id IS NOT NULL
      AND ch.is_first_appearance_verified = 1
      AND MONTH(c.publication_date) = MONTH(CURDATE())
      AND DAY(c.publication_date) = DAY(CURDATE())
    ORDER BY c.publication_date ASC
";
$result_anniversary = $mysqli->query($sql_anniversary);
if ($result_anniversary) {
    $anniversary_characters = $result_anniversary->fetch_all(MYSQLI_ASSOC);
    $result_anniversary->free();
}

// --- RECUPERO DATI (invariato) ---
$first_appearances = [];
$sql_appearances = "
    SELECT
        ch.character_id,
        ch.name AS character_name,
        ch.slug AS character_slug,
        ch.character_image,
        c.comic_id,
        c.slug as comic_slug,
        c.issue_number,
        c.publication_date,
        s_app.title as first_app_story_title,
        (SELECT GROUP_CONCAT(DISTINCT p.name ORDER BY FIELD(sp.role, 'Soggetto', 'Sceneggiatura', 'Testi', 'Disegni', 'Disegnatore') SEPARATOR ', ')
         FROM story_persons sp
         JOIN persons p ON sp.person_id = p.person_id
         WHERE sp.story_id = ch.first_appearance_story_id
        ) as creators
    FROM
        characters ch
    JOIN
        stories s_app ON ch.first_appearance_story_id = s_app.story_id
    JOIN
        comics c ON s_app.comic_id = c.comic_id
    WHERE
        ch.first_appearance_story_id IS NOT NULL
        AND ch.first_appearance_story_id != 0
        AND ch.is_first_appearance_verified = 1
    GROUP BY ch.character_id, ch.name, ch.slug, ch.character_image, c.comic_id, c.issue_number, c.publication_date, s_app.title
    ORDER BY c.publication_date ASC, ch.name ASC";
$result_appearances = $mysqli->query($sql_appearances);
if ($result_appearances) {
    $first_appearances = $result_appearances->fetch_all(MYSQLI_ASSOC);
    $result_appearances->free();
}

// --- RAGGRUPPAMENTO DATI E CALCOLO DECENNI ---
$appearances_by_year = [];
$decades = [];
foreach ($first_appearances as $appearance) {
    $year = date('Y', strtotime($appearance['publication_date']));
    if (!isset($appearances_by_year[$year])) {
        $appearances_by_year[$year] = [];
    }
    
    // Aggiungiamo il flag 'in_collection'
    $comic_id_of_appearance = $appearance['comic_id'];
    $appearance['in_collection'] = isset($user_collection_ids[$comic_id_of_appearance]);
    $appearances_by_year[$year][] = $appearance;

    // Aggiungiamo il decennio all'elenco, se non già presente
    $decade = floor($year / 10) * 10;
    if (!in_array($decade, $decades)) {
        $decades[] = $decade;
    }
}
sort($decades); // Ordiniamo i decenni
// --- FINE BLOCCO ---

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <?php if (!empty($anniversary_characters)): ?>
    <div class="anniversary-widget">
        <h2>🎂 Buon Compleanno TopoVerso!</h2>
        <p>Oggi festeggiamo la prima apparizione di questi personaggi:</p>
        <div class="anniversary-characters-list">
            <?php foreach($anniversary_characters as $char): ?>
                <?php
                    $anniversary_link = !empty($char['slug'])
                        ? 'character_detail.php?slug=' . urlencode($char['slug'])
                        : 'character_detail.php?id=' . $char['character_id'];
                ?>
                <a href="<?php echo $anniversary_link; ?>" class="anniversary-character-item" title="Vedi <?php echo htmlspecialchars($char['name']); ?>">
                    <?php if ($char['character_image']): ?>
                        <img src="<?php echo UPLOADS_URL . htmlspecialchars($char['character_image']); ?>" alt="Immagine di <?php echo htmlspecialchars($char['name']); ?>">
                    <?php else: ?>
                        <?php echo generate_image_placeholder(htmlspecialchars($char['name']), 50, 50); ?>
                    <?php endif; ?>
                    <div class="anniversary-character-info">
                        <strong><?php echo htmlspecialchars($char['name']); ?></strong>
                        <span>(anno <?php echo date('Y', strtotime($char['publication_date'])); ?>)</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <p class="page-description">
        Esplora la storia del TopoVerso. Clicca su un decennio per filtrare la timeline, poi su un anno per scoprire i personaggi che hanno fatto il loro debutto.
    </p>

    <?php if (!empty($appearances_by_year)): ?>
        <div class="timeline-container">
            
            <div class="timeline-decade-filters">
                <button class="decade-filter active" data-decade="all">Tutti i decenni</button>
                <?php foreach ($decades as $decade): ?>
                    <button class="decade-filter" data-decade="<?php echo $decade; ?>">
                        Anni '<?php echo substr($decade, 2, 2); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="timeline-navigation-wrapper">
                <div class="timeline-navigation">
                    <?php foreach ($appearances_by_year as $year => $characters): ?>
                        <div class="timeline-year-marker" data-year="<?php echo $year; ?>">
                            <span><?php echo $year; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="timeline-content">
                <?php foreach ($appearances_by_year as $year => $characters_in_year): ?>
                    <div class="timeline-year-group" id="year-<?php echo $year; ?>">
                        <h2 class="timeline-year-title">Prime Apparizioni del <?php echo $year; ?></h2>
                        <div class="appearance-grid">
                            <?php foreach ($characters_in_year as $appearance): ?>
                                <div class="appearance-card <?php echo ($appearance['in_collection']) ? 'in-collection' : ''; ?>">
                                    <?php if ($appearance['in_collection']): ?>
                                        <div class="in-collection-badge" title="Possiedi l'albo di questa prima apparizione">✓</div>
                                    <?php endif; ?>
                                    <a href="<?php echo !empty($appearance['character_slug']) ? 'character_detail.php?slug=' . urlencode($appearance['character_slug']) : 'character_detail.php?id=' . $appearance['character_id']; ?>" title="Vedi dettagli per <?php echo htmlspecialchars($appearance['character_name']); ?>">
                                        <div class="appearance-card-image-wrapper">
                                            <?php if ($appearance['character_image']): ?>
                                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($appearance['character_image']); ?>" alt="Immagine di <?php echo htmlspecialchars($appearance['character_name']); ?>" loading="lazy">
                                            <?php else: ?>
                                                <?php echo generate_image_placeholder(htmlspecialchars($appearance['character_name']), 200, 200, 'appearance-placeholder'); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="appearance-card-info">
                                            <h3><?php echo htmlspecialchars($appearance['character_name']); ?></h3>
                                            <?php if (!empty($appearance['creators'])): ?>
                                                <p class="first-appearance-creator">
                                                    Creato da: <strong><?php echo htmlspecialchars($appearance['creators']); ?></strong>
                                                </p>
                                            <?php endif; ?>
                                            <p class="first-appearance-text">
                                                Apparso in: <em>"<?php echo htmlspecialchars($appearance['first_app_story_title']); ?>"</em>
                                                <br>su Topolino #<?php echo htmlspecialchars($appearance['issue_number']);?>
                                                <?php if($appearance['publication_date']): ?>
                                                    (<?php echo format_date_italian($appearance['publication_date'], 'd/m/Y'); ?>)
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <p style="text-align: center; margin-top: 20px;">Nessuna prima apparizione verificata trovata nel catalogo.</p>
    <?php endif; ?>
</div>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>