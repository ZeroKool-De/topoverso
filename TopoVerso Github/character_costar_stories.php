<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Parametri ID dei personaggi
$character1_id = isset($_GET['character1']) ? (int)$_GET['character1'] : 0;
$character2_id = isset($_GET['character2']) ? (int)$_GET['character2'] : 0;

if (!$character1_id || !$character2_id || $character1_id === $character2_id) {
    header('Location: characters_page.php?message=Parametri non validi per la co-apparizione');
    exit;
}

// Recupero informazioni dei personaggi
$stmt_char1 = $mysqli->prepare("SELECT character_id, name, slug, character_image FROM characters WHERE character_id = ?");
$stmt_char1->bind_param("i", $character1_id);
$stmt_char1->execute();
$character1 = $stmt_char1->get_result()->fetch_assoc();
$stmt_char1->close();

$stmt_char2 = $mysqli->prepare("SELECT character_id, name, slug, character_image FROM characters WHERE character_id = ?");
$stmt_char2->bind_param("i", $character2_id);
$stmt_char2->execute();
$character2 = $stmt_char2->get_result()->fetch_assoc();
$stmt_char2->close();

if (!$character1 || !$character2) {
    header('Location: characters_page.php?message=Uno o entrambi i personaggi non sono stati trovati');
    exit;
}

// Opzioni di ordinamento
$sort_options = [
    'date_desc' => 'Più Recenti (Pubblicazione)',
    'date_asc' => 'Più Vecchie (Pubblicazione)',
    'latest_added' => 'Ultime Storie Inserite'
];
$current_sort = $_GET['sort'] ?? 'date_desc';
if (!array_key_exists($current_sort, $sort_options)) {
    $current_sort = 'date_desc';
}

// Paginazione
$items_per_page = 50;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Query per le storie con co-apparizioni
$order_by_sql = "";
switch ($current_sort) {
    case 'date_asc':
        $order_by_sql = "ORDER BY c.publication_date ASC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ASC, s.sequence_in_comic ASC";
        break;
    case 'latest_added':
        $order_by_sql = "ORDER BY s.created_at DESC, c.publication_date DESC, s.sequence_in_comic ASC";
        break;
    case 'date_desc':
    default:
        $order_by_sql = "ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC, s.sequence_in_comic ASC";
        break;
}

// Conteggio totale storie con co-apparizioni
$sql_count = "
    SELECT COUNT(DISTINCT s.story_id) as total
    FROM stories s
    JOIN story_characters sc1 ON s.story_id = sc1.story_id
    JOIN story_characters sc2 ON s.story_id = sc2.story_id
    WHERE sc1.character_id = ? AND sc2.character_id = ?
";
$stmt_count = $mysqli->prepare($sql_count);
$stmt_count->bind_param("ii", $character1_id, $character2_id);
$stmt_count->execute();
$total_coappearances = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_count->close();

$total_pages = ceil($total_coappearances / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

// Query principale per recuperare le storie con co-apparizioni
$sql_coappearances = "
    SELECT DISTINCT s.story_id, s.title, s.story_title_main, s.part_number, s.total_parts,
           s.sequence_in_comic, s.created_at as story_created_at, s.first_page_image,
           c.comic_id, c.slug, c.issue_number, c.title as comic_title, c.cover_image, c.publication_date
    FROM stories s
    JOIN story_characters sc1 ON s.story_id = sc1.story_id
    JOIN story_characters sc2 ON s.story_id = sc2.story_id
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE sc1.character_id = ? AND sc2.character_id = ?
    $order_by_sql
    LIMIT ? OFFSET ?
";
$stmt_coappearances = $mysqli->prepare($sql_coappearances);
$stmt_coappearances->bind_param("iiii", $character1_id, $character2_id, $items_per_page, $offset);
$stmt_coappearances->execute();
$result_coappearances = $stmt_coappearances->get_result();
$coappearances = [];
while ($row = $result_coappearances->fetch_assoc()) {
    $coappearances[] = $row;
}
$stmt_coappearances->close();

$page_title = "Co-apparizioni di " . htmlspecialchars($character1['name']) . " e " . htmlspecialchars($character2['name']);

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <div class="coappearance-header">
        <div class="characters-showcase">
            <div class="character-card">
                <?php if ($character1['character_image']): ?>
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($character1['character_image']); ?>" alt="<?php echo htmlspecialchars($character1['name']); ?>" class="character-image-coapp">
                <?php else: ?>
                    <?php echo generate_image_placeholder($character1['name'], 120, 120, 'character-image-coapp'); ?>
                <?php endif; ?>
                <h3>
                    <?php $character1_link = !empty($character1['slug']) ? 'character_detail.php?slug=' . urlencode($character1['slug']) : 'character_detail.php?id=' . $character1['character_id']; ?>
                    <a href="<?php echo $character1_link; ?>"><?php echo htmlspecialchars($character1['name']); ?></a>
                </h3>
            </div>
            
            <div class="coappearance-symbol">
                <span>&amp;</span>
            </div>
            
            <div class="character-card">
                <?php if ($character2['character_image']): ?>
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($character2['character_image']); ?>" alt="<?php echo htmlspecialchars($character2['name']); ?>" class="character-image-coapp">
                <?php else: ?>
                    <?php echo generate_image_placeholder($character2['name'], 120, 120, 'character-image-coapp'); ?>
                <?php endif; ?>
                <h3>
                    <?php $character2_link = !empty($character2['slug']) ? 'character_detail.php?slug=' . urlencode($character2['slug']) : 'character_detail.php?id=' . $character2['character_id']; ?>
                    <a href="<?php echo $character2_link; ?>"><?php echo htmlspecialchars($character2['name']); ?></a>
                </h3>
            </div>
        </div>
        
        <h1><?php echo $page_title; ?></h1>
        <p class="coappearance-summary">
            <strong><?php echo $total_coappearances; ?></strong> 
            storie in cui questi personaggi appaiono insieme
        </p>
    </div>

    <?php if ($total_coappearances > 0): ?>
        <div class="controls-bar">
            <form method="GET" action="character_costar_stories.php" class="form-inline">
                <input type="hidden" name="character1" value="<?php echo $character1_id; ?>">
                <input type="hidden" name="character2" value="<?php echo $character2_id; ?>">
                
                <div class="filter-group">
                    <label for="sort">Ordina per:</label>
                    <select name="sort" id="sort" class="form-control">
                        <?php foreach ($sort_options as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($current_sort === $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($value); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Applica</button>
            </form>
        </div>

        <div class="coappearances-list">
            <?php foreach ($coappearances as $coapp): ?>
                <div class="coappearance-item">
                    <div class="story-cover">
                        <?php $comic_link = !empty($coapp['slug']) ? 'comic_detail.php?slug=' . urlencode($coapp['slug']) : 'comic_detail.php?id=' . $coapp['comic_id']; ?>
                        <a href="<?php echo $comic_link; ?>#story-item-<?php echo $coapp['story_id']; ?>">
                            <?php if (!empty($coapp['first_page_image'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($coapp['first_page_image']); ?>" alt="Storia">
                            <?php elseif (!empty($coapp['cover_image'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($coapp['cover_image']); ?>" alt="Topolino #<?php echo htmlspecialchars($coapp['issue_number']); ?>">
                            <?php else: ?>
                                <?php echo generate_comic_placeholder_cover(htmlspecialchars($coapp['issue_number']), 70, 100, 'comic-list-placeholder'); ?>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="coappearance-details">
                        <h4>
                            <a href="<?php echo $comic_link; ?>">
                                Topolino #<?php echo htmlspecialchars($coapp['issue_number']); ?>
                                <?php if (!empty($coapp['comic_title'])): ?> - <?php echo htmlspecialchars($coapp['comic_title']); endif; ?>
                            </a>
                        </h4>
                        
                        <p class="story-title-link">
                            <strong>Storia:</strong> 
                            <a href="<?php echo $comic_link; ?>#story-item-<?php echo $coapp['story_id']; ?>">
                                <?php echo format_story_title($coapp); ?>
                            </a>
                        </p>
                        
                        <p class="publication-date">
                            Pubblicazione: <?php echo $coapp['publication_date'] ? format_date_italian($coapp['publication_date']) : 'N/D'; ?>
                        </p>
                        
                        <?php if ($current_sort === 'latest_added' && !empty($coapp['story_created_at'])): ?>
                            <p class="story-insertion-date">
                                <small>Storia inserita il: <?php echo format_date_italian($coapp['story_created_at'], "d/m/Y H:i"); ?></small>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-controls">
                <?php
                $base_url_params = [
                    'character1' => $character1_id,
                    'character2' => $character2_id
                ];
                if ($current_sort !== 'date_desc') {
                    $base_url_params['sort'] = $current_sort;
                }
                
                $base_url = 'character_costar_stories.php?' . http_build_query($base_url_params);
                $separator = '&';
                ?>
                
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo $base_url . $separator; ?>page=<?php echo $current_page - 1; ?>">« Precedente</a>
                <?php else: ?>
                    <span class="disabled">« Precedente</span>
                <?php endif; ?>
                
                <?php
                $num_links_edges = 2;
                $num_links_around = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == $current_page): ?>
                        <span class="current-page"><?php echo $i; ?></span>
                    <?php elseif ($i <= $num_links_edges || $i > $total_pages - $num_links_edges || ($i >= $current_page - $num_links_around && $i <= $current_page + $num_links_around)): ?>
                        <a href="<?php echo $base_url . $separator; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php elseif (
                        ($i == $num_links_edges + 1 && $current_page > $num_links_edges + $num_links_around + 1) ||
                        ($i == $total_pages - $num_links_edges && $current_page < $total_pages - $num_links_edges - $num_links_around)
                    ):
                        if (!isset($dots_shown) || $dots_shown < 2) {
                            echo '<span class="disabled">...</span>';
                            $dots_shown = isset($dots_shown) ? $dots_shown + 1 : 1;
                        }
                    endif;
                endfor;
                ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo $base_url . $separator; ?>page=<?php echo $current_page + 1; ?>">Successiva »</a>
                <?php else: ?>
                    <span class="disabled">Successiva »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="no-coappearances">
            <p>Nessuna co-apparizione trovata tra questi personaggi nelle storie catalogate.</p>
        </div>
    <?php endif; ?>
    
    <div class="back-links" style="margin-top: 30px;">
        <a href="<?php echo $character1_link; ?>" class="btn btn-secondary">« Torna a <?php echo htmlspecialchars($character1['name']); ?></a>
        <a href="<?php echo $character2_link; ?>" class="btn btn-secondary">« Torna a <?php echo htmlspecialchars($character2['name']); ?></a>
        <a href="characters_page.php" class="btn btn-secondary">« Tutti i personaggi</a>
    </div>
</div>

<style>
.coappearance-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
    border-radius: 8px;
}

.characters-showcase {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 30px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.character-card {
    text-align: center;
}

.character-image-coapp {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #28a745;
    margin-bottom: 10px;
}

.coappearance-symbol {
    font-size: 2.5em;
    font-weight: bold;
    color: #28a745;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.coappearance-summary {
    font-size: 1.1em;
    color: #495057;
    margin: 10px 0;
}

.coappearances-list {
    margin-top: 20px;
}

.coappearance-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    margin-bottom: 15px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.story-cover img {
    width: 70px;
    height: 100px;
    object-fit: cover;
    border-radius: 4px;
}

.coappearance-details {
    flex: 1;
}

.coappearance-details h4 {
    margin: 0 0 10px 0;
    color: #28a745;
}

.no-coappearances {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #6c757d;
}

.back-links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .characters-showcase {
        flex-direction: column;
        gap: 20px;
    }
    
    .coappearance-symbol {
        transform: rotate(90deg);
    }
    
    .coappearance-item {
        flex-direction: column;
        text-align: center;
    }
    
    .back-links {
        flex-direction: column;
    }
}
</style>

<?php
require_once 'includes/footer.php';
$mysqli->close();
?>