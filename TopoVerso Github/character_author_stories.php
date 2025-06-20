<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Parametri ID del personaggio e dell'autore (speculare alla pagina autore-personaggio)
$character_id = isset($_GET['character']) ? (int)$_GET['character'] : 0;
$person_id = isset($_GET['person']) ? (int)$_GET['person'] : 0;

if (!$character_id || !$person_id) {
    header('Location: characters_page.php?message=Parametri non validi per la ricerca');
    exit;
}

// Recupero informazioni del personaggio
$stmt_character = $mysqli->prepare("SELECT character_id, name, slug, character_image FROM characters WHERE character_id = ?");
$stmt_character->bind_param("i", $character_id);
$stmt_character->execute();
$character = $stmt_character->get_result()->fetch_assoc();
$stmt_character->close();

// Recupero informazioni dell'autore
$stmt_author = $mysqli->prepare("SELECT person_id, name, slug, person_image FROM persons WHERE person_id = ?");
$stmt_author->bind_param("i", $person_id);
$stmt_author->execute();
$author = $stmt_author->get_result()->fetch_assoc();
$stmt_author->close();

if (!$character || !$author) {
    header('Location: characters_page.php?message=Personaggio o autore non trovato');
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

// Query per le storie con questo personaggio e autore
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

// Conteggio totale storie
$sql_count = "
    SELECT COUNT(DISTINCT s.story_id) as total
    FROM stories s
    JOIN story_characters sc ON s.story_id = sc.story_id
    JOIN story_persons sp ON s.story_id = sp.story_id
    WHERE sc.character_id = ? AND sp.person_id = ?
";
$stmt_count = $mysqli->prepare($sql_count);
$stmt_count->bind_param("ii", $character_id, $person_id);
$stmt_count->execute();
$total_stories = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_count->close();

$total_pages = ceil($total_stories / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

// Query principale per recuperare le storie
$sql_stories = "
    SELECT DISTINCT s.story_id, s.title, s.story_title_main, s.part_number, s.total_parts,
           s.sequence_in_comic, s.created_at as story_created_at, s.first_page_image,
           c.comic_id, c.slug, c.issue_number, c.title as comic_title, c.cover_image, c.publication_date,
           GROUP_CONCAT(DISTINCT sp.role ORDER BY sp.role SEPARATOR ', ') as author_roles
    FROM stories s
    JOIN story_characters sc ON s.story_id = sc.story_id
    JOIN story_persons sp ON s.story_id = sp.story_id
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE sc.character_id = ? AND sp.person_id = ?
    GROUP BY s.story_id, c.comic_id
    $order_by_sql
    LIMIT ? OFFSET ?
";
$stmt_stories = $mysqli->prepare($sql_stories);
$stmt_stories->bind_param("iiii", $character_id, $person_id, $items_per_page, $offset);
$stmt_stories->execute();
$result_stories = $stmt_stories->get_result();
$stories = [];
while ($row = $result_stories->fetch_assoc()) {
    $stories[] = $row;
}
$stmt_stories->close();

$page_title = htmlspecialchars($character['name']) . " e " . htmlspecialchars($author['name']);

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <div class="character-author-header">
        <div class="subjects-showcase">
            <div class="character-card">
                <?php if ($character['character_image']): ?>
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($character['character_image']); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>" class="character-image-main">
                <?php else: ?>
                    <?php echo generate_image_placeholder($character['name'], 120, 120, 'character-image-main'); ?>
                <?php endif; ?>
                <h3>
                    <?php $character_link = !empty($character['slug']) ? 'character_detail.php?slug=' . urlencode($character['slug']) : 'character_detail.php?id=' . $character['character_id']; ?>
                    <a href="<?php echo $character_link; ?>"><?php echo htmlspecialchars($character['name']); ?></a>
                </h3>
                <span class="subject-type">Personaggio</span>
            </div>
            
            <div class="connection-symbol">
                <span>&amp;</span>
            </div>
            
            <div class="author-card">
                <?php if ($author['person_image']): ?>
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($author['person_image']); ?>" alt="<?php echo htmlspecialchars($author['name']); ?>" class="author-image-main">
                <?php else: ?>
                    <?php echo generate_image_placeholder($author['name'], 120, 120, 'author-image-main'); ?>
                <?php endif; ?>
                <h3>
                    <?php $author_link = !empty($author['slug']) ? 'author_detail.php?slug=' . urlencode($author['slug']) : 'author_detail.php?id=' . $author['person_id']; ?>
                    <a href="<?php echo $author_link; ?>"><?php echo htmlspecialchars($author['name']); ?></a>
                </h3>
                <span class="subject-type">Autore</span>
            </div>
        </div>
        
        <h1>Storie di <?php echo $page_title; ?></h1>
        <p class="stories-summary">
            <strong><?php echo $total_stories; ?></strong> 
            storie in cui il personaggio <?php echo htmlspecialchars($character['name']); ?> appare nelle opere di <?php echo htmlspecialchars($author['name']); ?>
        </p>
    </div>

    <?php if ($total_stories > 0): ?>
        <div class="controls-bar">
            <form method="GET" action="character_author_stories.php" class="form-inline">
                <input type="hidden" name="character" value="<?php echo $character_id; ?>">
                <input type="hidden" name="person" value="<?php echo $person_id; ?>">
                
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

        <div class="stories-list">
            <?php foreach ($stories as $story): ?>
                <div class="story-item">
                    <div class="story-cover">
                        <?php $comic_link = !empty($story['slug']) ? 'comic_detail.php?slug=' . urlencode($story['slug']) : 'comic_detail.php?id=' . $story['comic_id']; ?>
                        <a href="<?php echo $comic_link; ?>#story-item-<?php echo $story['story_id']; ?>">
                            <?php if (!empty($story['first_page_image'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($story['first_page_image']); ?>" alt="Storia">
                            <?php elseif (!empty($story['cover_image'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($story['cover_image']); ?>" alt="Topolino #<?php echo htmlspecialchars($story['issue_number']); ?>">
                            <?php else: ?>
                                <?php echo generate_comic_placeholder_cover(htmlspecialchars($story['issue_number']), 70, 100, 'comic-list-placeholder'); ?>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="story-details">
                        <h4>
                            <a href="<?php echo $comic_link; ?>">
                                Topolino #<?php echo htmlspecialchars($story['issue_number']); ?>
                                <?php if (!empty($story['comic_title'])): ?> - <?php echo htmlspecialchars($story['comic_title']); endif; ?>
                            </a>
                        </h4>
                        
                        <p class="story-title-link">
                            <strong>Storia:</strong> 
                            <a href="<?php echo $comic_link; ?>#story-item-<?php echo $story['story_id']; ?>">
                                <?php echo format_story_title($story); ?>
                            </a>
                        </p>
                        
                        <div class="author-roles-info">
                            <strong>Ruolo/i di <?php echo htmlspecialchars($author['name']); ?>:</strong>
                            <?php echo !empty($story['author_roles']) ? htmlspecialchars($story['author_roles']) : 'N/D'; ?>
                        </div>
                        
                        <p class="publication-date">
                            Pubblicazione: <?php echo $story['publication_date'] ? format_date_italian($story['publication_date']) : 'N/D'; ?>
                        </p>
                        
                        <?php if ($current_sort === 'latest_added' && !empty($story['story_created_at'])): ?>
                            <p class="story-insertion-date">
                                <small>Storia inserita il: <?php echo format_date_italian($story['story_created_at'], "d/m/Y H:i"); ?></small>
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
                    'character' => $character_id,
                    'person' => $person_id
                ];
                if ($current_sort !== 'date_desc') {
                    $base_url_params['sort'] = $current_sort;
                }
                
                $base_url = 'character_author_stories.php?' . http_build_query($base_url_params);
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
        <div class="no-stories">
            <p>Nessuna storia trovata in cui il personaggio <?php echo htmlspecialchars($character['name']); ?> appare nelle opere di <?php echo htmlspecialchars($author['name']); ?>.</p>
        </div>
    <?php endif; ?>
    
    <div class="back-links" style="margin-top: 30px;">
        <a href="<?php echo $character_link; ?>" class="btn btn-secondary">« Torna a <?php echo htmlspecialchars($character['name']); ?></a>
        <a href="<?php echo $author_link; ?>" class="btn btn-secondary">« Torna a <?php echo htmlspecialchars($author['name']); ?></a>
        <a href="characters_page.php" class="btn btn-secondary">« Tutti i personaggi</a>
        <a href="authors_page.php" class="btn btn-secondary">« Tutti gli autori</a>
    </div>
</div>

<style>
.character-author-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
    border-radius: 8px;
}

.subjects-showcase {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 30px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.author-card, .character-card {
    text-align: center;
    position: relative;
}

.author-image-main, .character-image-main {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #28a745;
    margin-bottom: 10px;
}

.author-image-main {
    border-color: #007bff;
}

.subject-type {
    display: inline-block;
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: bold;
    margin-top: 5px;
}

.author-card .subject-type {
    background: #007bff;
}

.connection-symbol {
    font-size: 2.5em;
    font-weight: bold;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stories-summary {
    font-size: 1.1em;
    color: #495057;
    margin: 10px 0;
}

.stories-list {
    margin-top: 20px;
}

.story-item {
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

.story-details {
    flex: 1;
}

.story-details h4 {
    margin: 0 0 10px 0;
    color: #007bff;
}

.author-roles-info {
    background: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    margin: 10px 0;
    font-size: 0.9em;
}

.no-stories {
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
    .subjects-showcase {
        flex-direction: column;
        gap: 20px;
    }
    
    .connection-symbol {
        transform: rotate(90deg);
    }
    
    .story-item {
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