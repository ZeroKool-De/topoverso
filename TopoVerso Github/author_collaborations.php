<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Parametri ID degli autori
$person1_id = isset($_GET['person1']) ? (int)$_GET['person1'] : 0;
$person2_id = isset($_GET['person2']) ? (int)$_GET['person2'] : 0;

if (!$person1_id || !$person2_id || $person1_id === $person2_id) {
    header('Location: authors_page.php?message=Parametri non validi per la collaborazione');
    exit;
}

// Recupero informazioni degli autori
$stmt_author1 = $mysqli->prepare("SELECT person_id, name, slug, person_image FROM persons WHERE person_id = ?");
$stmt_author1->bind_param("i", $person1_id);
$stmt_author1->execute();
$author1 = $stmt_author1->get_result()->fetch_assoc();
$stmt_author1->close();

$stmt_author2 = $mysqli->prepare("SELECT person_id, name, slug, person_image FROM persons WHERE person_id = ?");
$stmt_author2->bind_param("i", $person2_id);
$stmt_author2->execute();
$author2 = $stmt_author2->get_result()->fetch_assoc();
$stmt_author2->close();

if (!$author1 || !$author2) {
    header('Location: authors_page.php?message=Uno o entrambi gli autori non sono stati trovati');
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

// Query per le storie collaborative
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

// Conteggio totale storie collaborative
$sql_count = "
    SELECT COUNT(DISTINCT s.story_id) as total
    FROM stories s
    JOIN story_persons sp1 ON s.story_id = sp1.story_id
    JOIN story_persons sp2 ON s.story_id = sp2.story_id
    WHERE sp1.person_id = ? AND sp2.person_id = ?
";
$stmt_count = $mysqli->prepare($sql_count);
$stmt_count->bind_param("ii", $person1_id, $person2_id);
$stmt_count->execute();
$total_collaborations = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_count->close();

$total_pages = ceil($total_collaborations / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

// Query principale per recuperare le storie collaborative
$sql_collaborations = "
    SELECT DISTINCT s.story_id, s.title, s.story_title_main, s.part_number, s.total_parts,
           s.sequence_in_comic, s.created_at as story_created_at, s.first_page_image,
           c.comic_id, c.slug, c.issue_number, c.title as comic_title, c.cover_image, c.publication_date,
           GROUP_CONCAT(DISTINCT CONCAT(sp1.role, ':', sp1.person_id) ORDER BY sp1.role SEPARATOR '|') as author1_roles,
           GROUP_CONCAT(DISTINCT CONCAT(sp2.role, ':', sp2.person_id) ORDER BY sp2.role SEPARATOR '|') as author2_roles
    FROM stories s
    JOIN story_persons sp1 ON s.story_id = sp1.story_id
    JOIN story_persons sp2 ON s.story_id = sp2.story_id
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE sp1.person_id = ? AND sp2.person_id = ?
    GROUP BY s.story_id, c.comic_id
    $order_by_sql
    LIMIT ? OFFSET ?
";
$stmt_collaborations = $mysqli->prepare($sql_collaborations);
$stmt_collaborations->bind_param("iiii", $person1_id, $person2_id, $items_per_page, $offset);
$stmt_collaborations->execute();
$result_collaborations = $stmt_collaborations->get_result();
$collaborations = [];
while ($row = $result_collaborations->fetch_assoc()) {
    // Processa i ruoli degli autori
    $author1_roles = [];
    $author2_roles = [];
    
    if (!empty($row['author1_roles'])) {
        $roles1 = explode('|', $row['author1_roles']);
        foreach ($roles1 as $role_info) {
            list($role, $pid) = explode(':', $role_info);
            if ($pid == $person1_id) $author1_roles[] = $role;
        }
    }
    
    if (!empty($row['author2_roles'])) {
        $roles2 = explode('|', $row['author2_roles']);
        foreach ($roles2 as $role_info) {
            list($role, $pid) = explode(':', $role_info);
            if ($pid == $person2_id) $author2_roles[] = $role;
        }
    }
    
    $row['author1_roles_list'] = array_unique($author1_roles);
    $row['author2_roles_list'] = array_unique($author2_roles);
    $collaborations[] = $row;
}
$stmt_collaborations->close();

$page_title = "Collaborazioni tra " . htmlspecialchars($author1['name']) . " e " . htmlspecialchars($author2['name']);

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <div class="collaboration-header">
        <div class="authors-showcase">
            <div class="author-card">
                <?php if ($author1['person_image']): ?>
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($author1['person_image']); ?>" alt="<?php echo htmlspecialchars($author1['name']); ?>" class="author-image-collab">
                <?php else: ?>
                    <?php echo generate_image_placeholder($author1['name'], 120, 120, 'author-image-collab'); ?>
                <?php endif; ?>
                <h3>
                    <?php $author1_link = !empty($author1['slug']) ? 'author_detail.php?slug=' . urlencode($author1['slug']) : 'author_detail.php?id=' . $author1['person_id']; ?>
                    <a href="<?php echo $author1_link; ?>"><?php echo htmlspecialchars($author1['name']); ?></a>
                </h3>
            </div>
            
            <div class="collaboration-symbol">
                <span>&times;</span>
            </div>
            
            <div class="author-card">
                <?php if ($author2['person_image']): ?>
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($author2['person_image']); ?>" alt="<?php echo htmlspecialchars($author2['name']); ?>" class="author-image-collab">
                <?php else: ?>
                    <?php echo generate_image_placeholder($author2['name'], 120, 120, 'author-image-collab'); ?>
                <?php endif; ?>
                <h3>
                    <?php $author2_link = !empty($author2['slug']) ? 'author_detail.php?slug=' . urlencode($author2['slug']) : 'author_detail.php?id=' . $author2['person_id']; ?>
                    <a href="<?php echo $author2_link; ?>"><?php echo htmlspecialchars($author2['name']); ?></a>
                </h3>
            </div>
        </div>
        
        <h1><?php echo $page_title; ?></h1>
        <p class="collaboration-summary">
            <strong><?php echo $total_collaborations; ?></strong> 
            storie in cui questi autori hanno collaborato insieme
        </p>
    </div>

    <?php if ($total_collaborations > 0): ?>
        <div class="controls-bar">
            <form method="GET" action="author_collaborations.php" class="form-inline">
                <input type="hidden" name="person1" value="<?php echo $person1_id; ?>">
                <input type="hidden" name="person2" value="<?php echo $person2_id; ?>">
                
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

        <div class="collaborations-list">
            <?php foreach ($collaborations as $collab): ?>
                <div class="collaboration-item">
                    <div class="story-cover">
                        <?php $comic_link = !empty($collab['slug']) ? 'comic_detail.php?slug=' . urlencode($collab['slug']) : 'comic_detail.php?id=' . $collab['comic_id']; ?>
                        <a href="<?php echo $comic_link; ?>#story-item-<?php echo $collab['story_id']; ?>">
                            <?php if (!empty($collab['first_page_image'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($collab['first_page_image']); ?>" alt="Storia">
                            <?php elseif (!empty($collab['cover_image'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($collab['cover_image']); ?>" alt="Topolino #<?php echo htmlspecialchars($collab['issue_number']); ?>">
                            <?php else: ?>
                                <?php echo generate_comic_placeholder_cover(htmlspecialchars($collab['issue_number']), 70, 100, 'comic-list-placeholder'); ?>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="collaboration-details">
                        <h4>
                            <a href="<?php echo $comic_link; ?>">
                                Topolino #<?php echo htmlspecialchars($collab['issue_number']); ?>
                                <?php if (!empty($collab['comic_title'])): ?> - <?php echo htmlspecialchars($collab['comic_title']); endif; ?>
                            </a>
                        </h4>
                        
                        <p class="story-title-link">
                            <strong>Storia:</strong> 
                            <a href="<?php echo $comic_link; ?>#story-item-<?php echo $collab['story_id']; ?>">
                                <?php echo format_story_title($collab); ?>
                            </a>
                        </p>
                        
                        <div class="authors-roles">
                            <div class="author-role">
                                <strong><?php echo htmlspecialchars($author1['name']); ?>:</strong>
                                <?php echo !empty($collab['author1_roles_list']) ? implode(', ', array_map('htmlspecialchars', $collab['author1_roles_list'])) : 'N/D'; ?>
                            </div>
                            <div class="author-role">
                                <strong><?php echo htmlspecialchars($author2['name']); ?>:</strong>
                                <?php echo !empty($collab['author2_roles_list']) ? implode(', ', array_map('htmlspecialchars', $collab['author2_roles_list'])) : 'N/D'; ?>
                            </div>
                        </div>
                        
                        <p class="publication-date">
                            Pubblicazione: <?php echo $collab['publication_date'] ? format_date_italian($collab['publication_date']) : 'N/D'; ?>
                        </p>
                        
                        <?php if ($current_sort === 'latest_added' && !empty($collab['story_created_at'])): ?>
                            <p class="story-insertion-date">
                                <small>Storia inserita il: <?php echo format_date_italian($collab['story_created_at'], "d/m/Y H:i"); ?></small>
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
                    'person1' => $person1_id,
                    'person2' => $person2_id
                ];
                if ($current_sort !== 'date_desc') {
                    $base_url_params['sort'] = $current_sort;
                }
                
                $base_url = 'author_collaborations.php?' . http_build_query($base_url_params);
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
        <div class="no-collaborations">
            <p>Nessuna collaborazione diretta trovata tra questi autori nelle storie catalogate.</p>
        </div>
    <?php endif; ?>
    
    <div class="back-links" style="margin-top: 30px;">
        <a href="<?php echo $author1_link; ?>" class="btn btn-secondary">« Torna a <?php echo htmlspecialchars($author1['name']); ?></a>
        <a href="<?php echo $author2_link; ?>" class="btn btn-secondary">« Torna a <?php echo htmlspecialchars($author2['name']); ?></a>
        <a href="authors_page.php" class="btn btn-secondary">« Tutti gli autori</a>
    </div>
</div>

<style>
.collaboration-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
}

.authors-showcase {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 30px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.author-card {
    text-align: center;
}

.author-image-collab {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #007bff;
    margin-bottom: 10px;
}

.collaboration-symbol {
    font-size: 2.5em;
    font-weight: bold;
    color: #007bff;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.collaboration-summary {
    font-size: 1.1em;
    color: #495057;
    margin: 10px 0;
}

.collaborations-list {
    margin-top: 20px;
}

.collaboration-item {
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

.collaboration-details {
    flex: 1;
}

.collaboration-details h4 {
    margin: 0 0 10px 0;
    color: #007bff;
}

.authors-roles {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}

.author-role {
    margin-bottom: 5px;
    font-size: 0.9em;
}

.author-role:last-child {
    margin-bottom: 0;
}

.no-collaborations {
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
    .authors-showcase {
        flex-direction: column;
        gap: 20px;
    }
    
    .collaboration-symbol {
        transform: rotate(90deg);
    }
    
    .collaboration-item {
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