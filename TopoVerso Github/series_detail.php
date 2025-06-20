<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// --- INIZIO BLOCCO MODIFICATO ---
$series_id = null;
$series_slug = null;

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $series_id = (int)$_GET['id'];
} elseif (isset($_GET['slug'])) {
    $series_slug = trim($_GET['slug']);
}

if (empty($series_id) && empty($series_slug)) {
    header('Location: series_list.php?message=ID serie non valido');
    exit;
}

$query_field = $series_id ? 'series_id' : 'slug';
$query_param = $series_id ?: $series_slug;
$param_type = $series_id ? 'i' : 's';

$stmt_series = $mysqli->prepare("SELECT series_id, title, slug, description, image_path FROM story_series WHERE {$query_field} = ?");
$stmt_series->bind_param($param_type, $query_param);
$stmt_series->execute();
$result_series_info = $stmt_series->get_result();

if ($result_series_info->num_rows === 0) {
    header('Location: series_list.php?message=Serie non trovata');
    exit;
}
$series_info = $result_series_info->fetch_assoc();
$stmt_series->close();

// Assicuriamoci che l'ID sia sempre disponibile per le query successive
$series_id = $series_info['series_id'];
// --- FINE BLOCCO MODIFICATO ---


$page_title = "Serie: " . htmlspecialchars($series_info['title']);

// Recupera le storie appartenenti a questa serie
// Ordina per numero episodio nella serie (se presente), altrimenti per data pubblicazione del fumetto, e poi per sequenza nel fumetto
$stories_in_series = [];
// --- MODIFICATO: Aggiunto c.slug alla select ---
$stmt_stories = $mysqli->prepare("
    SELECT 
        s.story_id, s.title AS story_title, s.series_episode_number, s.first_page_image,
        c.comic_id, c.slug, c.issue_number, c.title AS comic_title, c.publication_date, c.cover_image
    FROM stories s
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE s.series_id = ?
    ORDER BY s.series_episode_number ASC, c.publication_date ASC, s.sequence_in_comic ASC
");
$stmt_stories->bind_param("i", $series_id);
$stmt_stories->execute();
$result_stories_list = $stmt_stories->get_result();
if ($result_stories_list) {
    while ($row = $result_stories_list->fetch_assoc()) {
        $stories_in_series[] = $row;
    }
    $result_stories_list->free();
}
$stmt_stories->close();

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <div class="series-detail-header"> <?php // Stile simile a .author-detail-header o .character-detail-header ?>
        <?php if ($series_info['image_path']): ?>
            <img src="<?php echo UPLOADS_URL . htmlspecialchars($series_info['image_path']); ?>" alt="<?php echo htmlspecialchars($series_info['title']); ?>" class="series-image-main">
        <?php else: ?>
            <?php echo generate_image_placeholder(htmlspecialchars($series_info['title']), 180, 180, 'series-image-main img-placeholder'); ?>
        <?php endif; ?>
        <div class="series-info">
            <h1><?php echo htmlspecialchars($series_info['title']); ?></h1>
            <?php if (!empty($series_info['description'])): ?>
                <p class="description"><?php echo nl2br(htmlspecialchars($series_info['description'])); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($stories_in_series)): ?>
        <div class="series-stories-list-section">
            <h2>Storie in Questa Serie</h2>
            <?php foreach ($stories_in_series as $story_item): ?>
                <div class="story-in-series-item"> <?php // Stile simile a .appearance-item o .contribution-item ?>
                    <div class="story-in-series-cover">
                        <?php // --- MODIFICATO: Logica link con slug ---
                        $comic_detail_link = !empty($story_item['slug']) ? "comic_detail.php?slug=" . urlencode($story_item['slug']) : "comic_detail.php?id=" . $story_item['comic_id'];
                        ?>
                         <a href="<?php echo $comic_detail_link; ?>#story-item-<?php echo $story_item['story_id']; ?>">
                        <?php if ($story_item['first_page_image']): ?>
                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($story_item['first_page_image']); ?>" alt="Prima pagina di <?php echo htmlspecialchars($story_item['story_title']); ?>">
                        <?php elseif ($story_item['cover_image']): // Fallback alla copertina del fumetto se manca quella della storia ?>
                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($story_item['cover_image']); ?>" alt="Copertina Topolino #<?php echo htmlspecialchars($story_item['issue_number']); ?>">
                        <?php else: ?>
                            <?php echo generate_image_placeholder(htmlspecialchars($story_item['story_title']), 70, 100, 'story-list-placeholder'); ?>
                        <?php endif; ?>
                        </a>
                    </div>
                    <div class="story-in-series-details">
                        <h4>
                            <a href="<?php echo $comic_detail_link; ?>#story-item-<?php echo $story_item['story_id']; ?>">
                                <?php echo htmlspecialchars($story_item['story_title']); ?>
                            </a>
                            <?php if ($story_item['series_episode_number']): ?>
                                <span class="episode-number">(Episodio <?php echo htmlspecialchars($story_item['series_episode_number']); ?>)</span>
                            <?php endif; ?>
                        </h4>
                        <p class="comic-info">
                            Pubblicata in: 
                            <a href="<?php echo $comic_detail_link; ?>">
                                Topolino #<?php echo htmlspecialchars($story_item['issue_number']); ?>
                                <?php if(!empty($story_item['comic_title'])): ?> - <?php echo htmlspecialchars($story_item['comic_title']); endif; ?>
                            </a>
                        </p>
                        <p><small>Data pubblicazione albo: <?php echo $story_item['publication_date'] ? date("d/m/Y", strtotime($story_item['publication_date'])) : 'N/D'; ?></small></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Nessuna storia attualmente associata a questa serie.</p>
    <?php endif; ?>
    
    <p style="margin-top:30px;"><a href="series_list.php" class="btn btn-secondary">&laquo; Torna all'elenco delle serie</a></p>
</div>

<?php
require_once 'includes/footer.php';
$mysqli->close();
?>