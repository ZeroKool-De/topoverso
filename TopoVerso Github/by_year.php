<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';

$page_title = "Sfoglia per Anno";
$selected_year = null;
$comics_in_year = [];

// 1. Statistiche globali del database
$total_comics = 0;
$total_stories = 0;

$stats_result = $mysqli->query("SELECT COUNT(*) as total_comics FROM comics");
if ($stats_result) {
    $total_comics = $stats_result->fetch_assoc()['total_comics'];
    $stats_result->free();
}

$stories_result = $mysqli->query("SELECT COUNT(*) as total_stories FROM stories");
if ($stories_result) {
    $total_stories = $stories_result->fetch_assoc()['total_stories'];
    $stories_result->free();
}

// 2. Recupera tutti gli anni distinti con pubblicazioni E le loro statistiche
$years_with_stats = [];
$year_stats_result = $mysqli->query("
    SELECT 
        YEAR(c.publication_date) AS publication_year,
        COUNT(DISTINCT c.comic_id) as comics_count,
        COUNT(DISTINCT s.story_id) as stories_count
    FROM comics c
    LEFT JOIN stories s ON c.comic_id = s.comic_id
    WHERE c.publication_date IS NOT NULL 
    GROUP BY YEAR(c.publication_date)
    ORDER BY publication_year ASC
");
if ($year_stats_result) {
    while ($row = $year_stats_result->fetch_assoc()) {
        $years_with_stats[] = $row;
    }
    $year_stats_result->free();
}

// 3. Controlla se un anno è stato selezionato
if (isset($_GET['year']) && filter_var($_GET['year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => date('Y') + 5]])) {
    $selected_year = (int)$_GET['year'];
    $page_title = "Topolino del " . $selected_year;

    // 4. Recupera i fumetti per l'anno selezionato, ordinati cronologicamente
    $stmt = $mysqli->prepare("
        SELECT comic_id, issue_number, title, publication_date, cover_image, slug
        FROM comics 
        WHERE YEAR(publication_date) = ? 
        ORDER BY publication_date ASC, CAST(REPLACE(REPLACE(issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ASC
    ");
    $stmt->bind_param("i", $selected_year);
    $stmt->execute();
    $result_comics = $stmt->get_result();
    if ($result_comics) {
        while ($row = $result_comics->fetch_assoc()) {
            $comics_in_year[] = $row;
        }
        $result_comics->free();
    }
    $stmt->close();
}

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <!-- Statistiche Globali -->
    <div class="global-stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_comics, 0, ',', '.'); ?></div>
                <div class="stat-label">Albi Totali</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_stories, 0, ',', '.'); ?></div>
                <div class="stat-label">Storie Totali</div>
            </div>
        </div>
        
        <!-- Invito alle Richieste -->
        <div class="request-invitation-box">
            <h3>🚀 Aiutaci a Crescere!</h3>
            <p>Il nostro catalogo è in costante aggiornamento. Non trovi il numero che cerchi?</p>
            <a href="richieste.php" class="btn btn-primary request-btn">
                📝 Richiedi un Numero Mancante
            </a>
            <small>Le tue richieste ci aiutano a dare priorità ai prossimi inserimenti!</small>
        </div>
    </div>

    <?php if (!empty($years_with_stats)): ?>
        <h2>Sfoglia per Anno</h2>
        <div class="year-grid">
            <?php foreach ($years_with_stats as $year_data): ?>
                <div class="year-card <?php echo ($selected_year == $year_data['publication_year']) ? 'active' : ''; ?>">
                    <a href="by_year.php?year=<?php echo $year_data['publication_year']; ?>">
                        <div class="year-title"><?php echo $year_data['publication_year']; ?></div>
                        <div class="year-stats">
                            <span class="year-stat">
                                <strong><?php echo $year_data['comics_count']; ?></strong> albi
                            </span>
                            <span class="year-stat">
                                <strong><?php echo $year_data['stories_count']; ?></strong> storie
                            </span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Nessun anno con pubblicazioni trovato.</p>
    <?php endif; ?>

    <?php if ($selected_year): ?>
        <?php if (!empty($comics_in_year)): ?>
            <h2>Albi del <?php echo $selected_year; ?> (<?php echo count($comics_in_year); ?>)</h2>
            <div class="comic-grid">
                <?php foreach ($comics_in_year as $comic): ?>
                    <div class="comic-card">
                        <?php 
                        $comic_link = !empty($comic['slug']) ? 'comic_detail.php?slug=' . urlencode($comic['slug']) : 'comic_detail.php?id=' . $comic['comic_id'];
                        ?>
                        <a href="<?php echo $comic_link; ?>">
                            <?php if ($comic['cover_image']): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($comic['cover_image']); ?>" alt="Copertina <?php echo htmlspecialchars($comic['issue_number']); ?>">
                            <?php else: ?>
                                <?php echo generate_comic_placeholder_cover(htmlspecialchars($comic['issue_number']), 120, 160, 'comic-card-placeholder'); ?>
                            <?php endif; ?>
                            <h3>Topolino #<?php echo htmlspecialchars($comic['issue_number']); ?></h3>
                            <?php if (!empty($comic['title'])): ?>
                                <p class="comic-subtitle"><em><?php echo htmlspecialchars($comic['title']); ?></em></p>
                            <?php endif; ?>
                            <p class="comic-date"><?php echo $comic['publication_date'] ? format_date_italian($comic['publication_date'], "d F Y") : 'Data N/D'; ?></p>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-comics-message">
                <p>Nessun fumetto trovato per l'anno <?php echo $selected_year; ?>.</p>
                <p><a href="richieste.php?prefill_notes=Richiesta per l'anno <?php echo $selected_year; ?>">Richiedi un numero del <?php echo $selected_year; ?> »</a></p>
            </div>
        <?php endif; ?>
    <?php elseif (!empty($years_with_stats)): ?>
        <p style="text-align:center; margin-top: 20px;">Seleziona un anno per visualizzare i fumetti di quel periodo.</p>
    <?php endif; ?>

</div>

<style>
.global-stats-section {
    margin-bottom: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.stats-grid {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    min-width: 140px;
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #2c5aa0;
    line-height: 1;
}

.stat-label {
    color: #666;
    font-size: 0.9em;
    margin-top: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.request-invitation-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.request-invitation-box h3 {
    margin: 0 0 10px 0;
    font-size: 1.4em;
}

.request-invitation-box p {
    margin: 0 0 20px 0;
    opacity: 0.9;
}

.request-btn {
    display: inline-block;
    background: white;
    color: #667eea;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    transition: transform 0.2s, box-shadow 0.2s;
    margin-bottom: 10px;
}

.request-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    color: #667eea;
}

.request-invitation-box small {
    display: block;
    opacity: 0.8;
    font-size: 0.85em;
}

.year-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.year-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s ease;
    overflow: hidden;
}

.year-card:hover {
    border-color: #2c5aa0;
    box-shadow: 0 4px 12px rgba(44, 90, 160, 0.15);
    transform: translateY(-2px);
}

.year-card.active {
    border-color: #2c5aa0;
    background: #f8f9fa;
}

.year-card a {
    display: block;
    padding: 20px;
    text-decoration: none;
    color: inherit;
}

.year-title {
    font-size: 1.5em;
    font-weight: bold;
    color: #2c5aa0;
    text-align: center;
    margin-bottom: 10px;
}

.year-stats {
    display: flex;
    justify-content: space-between;
    font-size: 0.9em;
    color: #666;
}

.year-stat {
    text-align: center;
}

.year-stat strong {
    color: #2c5aa0;
    display: block;
    font-size: 1.1em;
}

.no-comics-message {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-top: 20px;
}

.no-comics-message a {
    color: #2c5aa0;
    font-weight: bold;
}

@media (max-width: 768px) {
    .stats-grid {
        flex-direction: column;
        align-items: center;
    }
    
    .year-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .year-stats {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<?php
require_once 'includes/footer.php';
$mysqli->close();
?>