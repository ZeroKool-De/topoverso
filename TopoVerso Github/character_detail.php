<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 

// --- Logica per accettare sia ID che slug ---
$character_id = null;
$character_slug = null;

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $character_id = (int)$_GET['id'];
} elseif (isset($_GET['slug'])) {
    $character_slug = trim($_GET['slug']);
}

if (empty($character_id) && empty($character_slug)) {
    header('Location: characters_page.php?message=Identificativo personaggio non valido');
    exit;
}

$query_field = $character_id ? 'ch.character_id' : 'ch.slug';
$query_param = $character_id ?: $character_slug;
$param_type = $character_id ? 'i' : 's';

$stmt_char = $mysqli->prepare("
    SELECT ch.character_id, ch.name, ch.slug, ch.description, ch.character_image, 
           ch.first_appearance_comic_id, ch.first_appearance_story_id, 
           ch.first_appearance_date, ch.first_appearance_notes, ch.is_first_appearance_verified,
           c_app.issue_number AS first_app_comic_issue,
           c_app.slug AS first_app_comic_slug,
           s_app.title AS first_app_story_title
    FROM characters ch
    LEFT JOIN comics c_app ON ch.first_appearance_comic_id = c_app.comic_id
    LEFT JOIN stories s_app ON ch.first_appearance_story_id = s_app.story_id
    WHERE {$query_field} = ?
");
$stmt_char->bind_param($param_type, $query_param);
$stmt_char->execute();
$result_char = $stmt_char->get_result();

if ($result_char->num_rows === 0) {
    header('Location: characters_page.php?message=Personaggio non trovato');
    exit;
}
$character = $result_char->fetch_assoc();
$stmt_char->close();

// Assicuriamoci che l'ID sia sempre disponibile per le query successive
$character_id = $character['character_id'];

// --- Recupero creatori del personaggio ---
$creators = [];
if (!empty($character['first_appearance_story_id'])) {
    $sql_creators = "
        SELECT p.person_id, p.name, p.slug, sp.role
        FROM story_persons sp
        JOIN persons p ON sp.person_id = p.person_id
        WHERE sp.story_id = ?
        ORDER BY FIELD(sp.role, 'Soggetto', 'Sceneggiatura', 'Disegni', 'Disegnatore', 'Testi', 'Matite'), p.name";
    
    $stmt_creators = $mysqli->prepare($sql_creators);
    $stmt_creators->bind_param("i", $character['first_appearance_story_id']);
    $stmt_creators->execute();
    $result_creators = $stmt_creators->get_result();
    while ($creator_row = $result_creators->fetch_assoc()) {
        $creators[] = $creator_row;
    }
    $stmt_creators->close();
}

// --- Recupero co-protagonisti frequenti (MODIFICATO CON LINK) ---
$frequent_costars = [];
$sql_costars = "
    SELECT c2.character_id, c2.name, c2.slug, c2.character_image, COUNT(sc1.story_id) AS co_appearance_count
    FROM story_characters sc1
    JOIN story_characters sc2 ON sc1.story_id = sc2.story_id AND sc1.character_id != sc2.character_id
    JOIN characters c2 ON sc2.character_id = c2.character_id
    WHERE sc1.character_id = ?
    GROUP BY c2.character_id, c2.name, c2.slug, c2.character_image
    ORDER BY co_appearance_count DESC, c2.name ASC
    LIMIT 5";
$stmt_costars = $mysqli->prepare($sql_costars);
$stmt_costars->bind_param("i", $character_id);
$stmt_costars->execute();
$result_costars = $stmt_costars->get_result();
while ($row = $result_costars->fetch_assoc()) {
    $frequent_costars[] = $row;
}
$stmt_costars->close();

// --- Recupero autori più frequenti per questo personaggio (CORREZIONE CONTEGGIO) ---
$top_authors_for_character = [];
$sql_top_authors = "
    SELECT p.person_id, p.name, p.slug, COUNT(DISTINCT sp.story_id) as story_count
    FROM story_characters sc
    JOIN story_persons sp ON sc.story_id = sp.story_id
    JOIN persons p ON sp.person_id = p.person_id
    WHERE sc.character_id = ?
    GROUP BY p.person_id, p.name, p.slug
    ORDER BY story_count DESC, p.name ASC
    LIMIT 5";
$stmt_top_authors = $mysqli->prepare($sql_top_authors);
$stmt_top_authors->bind_param("i", $character_id);
$stmt_top_authors->execute();
$result_top_authors = $stmt_top_authors->get_result();
while ($row = $result_top_authors->fetch_assoc()) {
    $top_authors_for_character[] = $row;
}
$stmt_top_authors->close();

$page_title = htmlspecialchars($character['name']);

// OPZIONI DI ORDINAMENTO E PAGINAZIONE
$sort_options_char_detail = [ 
    'date_desc' => 'Più Recenti (Pubblicazione)',
    'date_asc' => 'Più Vecchie (Pubblicazione)',
    'story_created_at_desc' => 'Le Ultime Inserite (Storie)',
    'year_desc' => 'Per Anno (Recenti prima)' 
];
$current_sort_char_detail = $_GET['sort'] ?? 'date_desc';
if (!array_key_exists($current_sort_char_detail, $sort_options_char_detail)) {
    $current_sort_char_detail = 'date_desc'; 
}

$items_per_page = 50;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// CONTEGGIO TOTALE APPARIZIONI PER LA PAGINAZIONE
$sql_count = "SELECT COUNT(DISTINCT s.story_id) as total 
              FROM story_characters sc
              JOIN stories s ON sc.story_id = s.story_id
              WHERE sc.character_id = ?";
$stmt_count = $mysqli->prepare($sql_count);
$stmt_count->bind_param("i", $character_id);
$stmt_count->execute();
$total_appearances = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_count->close();

$total_pages = ceil($total_appearances / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;


// COSTRUZIONE QUERY CON ORDINAMENTO E PAGINAZIONE
$order_by_sql_char_detail = ""; 
switch ($current_sort_char_detail) {
    case 'date_asc':
        $order_by_sql_char_detail = "ORDER BY c.publication_date ASC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ASC, s.sequence_in_comic ASC";
        break;
    case 'year_desc': 
        $order_by_sql_char_detail = "ORDER BY YEAR(c.publication_date) DESC, c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC, s.sequence_in_comic ASC";
        break;
    case 'story_created_at_desc':
        $order_by_sql_char_detail = "ORDER BY s.created_at DESC, c.publication_date DESC, s.sequence_in_comic ASC";
        break;
    case 'date_desc':
    default:
        $order_by_sql_char_detail = "ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC, s.sequence_in_comic ASC";
        break;
}

$appearances = [];
$sql_app = "
    SELECT DISTINCT c.comic_id, c.slug, c.issue_number, c.title AS comic_title, c.cover_image, c.publication_date,
           s.story_id, s.title AS story_title, s.sequence_in_comic,
           s.story_title_main, s.part_number, s.total_parts, s.created_at as story_created_at
    FROM story_characters sc
    JOIN stories s ON sc.story_id = s.story_id
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE sc.character_id = ?
    $order_by_sql_char_detail
    LIMIT ? OFFSET ?
";
$stmt_app = $mysqli->prepare($sql_app);
$stmt_app->bind_param("iii", $character_id, $items_per_page, $offset);
$stmt_app->execute();
$result_app = $stmt_app->get_result();
if ($result_app) {
    while ($row = $result_app->fetch_assoc()) {
        $appearances[] = $row;
    }
    $result_app->free();
}
$stmt_app->close();

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <div class="character-detail-header">
        <?php if ($character['character_image']): ?>
            <img src="<?php echo UPLOADS_URL . htmlspecialchars($character['character_image']); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>" class="character-image-main">
        <?php else: ?>
            <?php echo generate_image_placeholder(htmlspecialchars($character['name']), 180, 180, 'character-image-main img-placeholder'); ?>
        <?php endif; ?>
        <div class="character-info">
            <h1><?php echo htmlspecialchars($character['name']); ?></h1>
            <?php if (!empty($character['description'])): ?>
                <p class="description"><?php echo nl2br(htmlspecialchars($character['description'])); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($creators)): ?>
            <div class="character-creators-info">
                <strong>Creato da:</strong>
                <?php
                    $creator_links = [];
                    foreach ($creators as $creator) {
                        $creator_link_url = !empty($creator['slug']) ? 'author_detail.php?slug=' . urlencode($creator['slug']) : 'author_detail.php?id=' . $creator['person_id'];
                        $creator_links[] = '<a href="' . $creator_link_url . '">' . htmlspecialchars($creator['name']) . '</a> (' . htmlspecialchars($creator['role']) . ')';
                    }
                    echo implode(', ', $creator_links);
                ?>
            </div>
            <?php endif; ?>

            <?php if ($character['first_appearance_comic_id']): ?>
            <div class="first-appearance-info">
                <strong>Prima Apparizione Conosciuta:</strong>
                <?php
                $comic_link_fa = !empty($character['first_app_comic_slug']) 
                    ? "comic_detail.php?slug=" . urlencode($character['first_app_comic_slug']) 
                    : "comic_detail.php?id=" . $character['first_appearance_comic_id'];
                
                $link_text = "Topolino #" . htmlspecialchars($character['first_app_comic_issue']);
                if ($character['first_appearance_date']) {
                    $link_text .= " del " . format_date_italian($character['first_appearance_date']);
                }
                $link_url = BASE_URL . $comic_link_fa;
                if ($character['first_appearance_story_id']) { $link_url .= "#story-item-" . $character['first_appearance_story_id']; }
                ?>
                <p>
                    <?php if ($character['first_appearance_story_id'] && $character['first_app_story_title']): ?>
                        Storia: <a href="<?php echo $link_url; ?>"><?php echo htmlspecialchars($character['first_app_story_title']); ?></a><br>
                    <?php endif; ?>
                    In: <a href="<?php echo $link_url; ?>"><?php echo $link_text; ?></a>
                </p>
                <?php if ($character['first_appearance_notes']): ?><p><small><em>Note: <?php echo htmlspecialchars($character['first_appearance_notes']); ?></em></small></p><?php endif; ?>
                <?php if ($character['is_first_appearance_verified']): ?><p><small><em>(Verificata)</em></small></p><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="author-related-info-grid">
        <?php if (!empty($frequent_costars)): ?>
            <div class="left-column-section related-info-box">
                <h4>Appare più spesso con:</h4>
                <ul>
                    <?php foreach ($frequent_costars as $costar): ?>
                        <li>
                            <?php $costar_link = !empty($costar['slug']) ? 'character_detail.php?slug=' . urlencode($costar['slug']) : 'character_detail.php?id=' . $costar['character_id']; ?>
                            <a href="<?php echo $costar_link; ?>" title="Vedi scheda di <?php echo htmlspecialchars($costar['name']); ?>">
                                <?php if ($costar['character_image']): ?>
                                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($costar['character_image']); ?>" class="related-info-thumb">
                                <?php else: ?>
                                    <?php echo generate_image_placeholder($costar['name'], 24, 24, 'related-info-thumb'); ?>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($costar['name']); ?>
                            </a>
                            <small>
                                (<a href="character_costar_stories.php?character1=<?php echo $character_id; ?>&character2=<?php echo $costar['character_id']; ?>" 
                                   title="Vedi le <?php echo $costar['co_appearance_count']; ?> storie insieme">
                                   <?php echo $costar['co_appearance_count']; ?> storie
                                </a>)
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($top_authors_for_character)): ?>
            <div class="left-column-section related-info-box">
                <h4>Autori che lo usano di più:</h4>
                <ul>
                    <?php foreach ($top_authors_for_character as $author_item): ?>
                        <li>
                            <?php $author_link = !empty($author_item['slug']) ? 'author_detail.php?slug=' . urlencode($author_item['slug']) : 'author_detail.php?id=' . $author_item['person_id']; ?>
                            <a href="<?php echo $author_link; ?>" title="Vedi scheda di <?php echo htmlspecialchars($author_item['name']); ?>">
                                <?php echo htmlspecialchars($author_item['name']); ?>
                            </a>
                            <small>
                                (<a href="character_author_stories.php?character=<?php echo $character_id; ?>&person=<?php echo $author_item['person_id']; ?>" 
                                   title="Vedi le <?php echo $author_item['story_count']; ?> storie con questo autore">
                                   <?php echo $author_item['story_count']; ?> storie
                                </a>)
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="appearances-section">
        <h2>Apparizioni nei Fumetti (<?php echo $total_appearances; ?>)</h2>
        <nav id="characterAppearancesTabsNav" class="tabs-nav">
            <ul>
                <?php foreach ($sort_options_char_detail as $key => $label): ?>
                    <li>
                        <?php 
                        $link_sort = !empty($character['slug']) ? "character_detail.php?slug=" . urlencode($character['slug']) : "character_detail.php?id=" . $character_id;
                        $link_sort .= "&sort=" . $key;
                        ?>
                        <a href="<?php echo $link_sort; ?>"
                           class="<?php echo ($current_sort_char_detail === $key) ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php if (!empty($appearances)): ?>
            <?php foreach ($appearances as $appearance): ?>
                <?php $comic_link_app = !empty($appearance['slug']) ? 'comic_detail.php?slug=' . urlencode($appearance['slug']) : 'comic_detail.php?id=' . $appearance['comic_id']; ?>
                <div class="appearance-item">
                    <div class="appearance-cover">
                        <a href="<?php echo $comic_link_app; ?>">
                        <?php if ($appearance['cover_image']): ?>
                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($appearance['cover_image']); ?>" alt="Topolino #<?php echo htmlspecialchars($appearance['issue_number']); ?>">
                        <?php else: ?>
                            <?php echo generate_comic_placeholder_cover(htmlspecialchars($appearance['issue_number']), 65, 90, 'comic-list-placeholder'); ?>
                        <?php endif; ?>
                        </a>
                    </div>
                    <div class="appearance-details">
                        <h4>
                            <a href="<?php echo $comic_link_app; ?>">
                                Topolino #<?php echo htmlspecialchars($appearance['issue_number']); ?>
                                <?php if(!empty($appearance['comic_title'])): ?> - <?php echo htmlspecialchars($appearance['comic_title']); endif; ?>
                            </a>
                        </h4>
                        <p><strong>Storia:</strong> <a href="<?php echo $comic_link_app; ?>#story-item-<?php echo $appearance['story_id']; ?>">
                            <?php
                            $display_story_title_char_app = '';
                            if (!empty($appearance['story_title_main'])) {
                                $display_story_title_char_app = '<strong>' . htmlspecialchars($appearance['story_title_main']) . '</strong>';
                                if (!empty($appearance['part_number'])) {
                                    $part_specific_title_char_app = htmlspecialchars($appearance['story_title']);
                                    $expected_part_title_char_app = 'Parte ' . htmlspecialchars($appearance['part_number']);
                                     if ($part_specific_title_char_app !== htmlspecialchars($appearance['story_title_main']) && $part_specific_title_char_app !== $expected_part_title_char_app && strtolower($part_specific_title_char_app) !== strtolower($expected_part_title_char_app) ) {
                                        $display_story_title_char_app .= ': ' . $part_specific_title_char_app . ' <em>(Parte ' . htmlspecialchars($appearance['part_number']) . ')</em>';
                                    } else {
                                        $display_story_title_char_app .= ' - Parte ' . htmlspecialchars($appearance['part_number']);
                                    }
                                } elseif (!empty($appearance['story_title']) && strtolower($appearance['story_title']) !== strtolower($appearance['story_title_main'])) {
                                    $display_story_title_char_app .= ': ' . htmlspecialchars($appearance['story_title']);
                                }
                                if ($appearance['total_parts']) {
                                    $display_story_title_char_app .= ' (di ' . htmlspecialchars($appearance['total_parts']) . ')';
                                }
                            } else {
                                $display_story_title_char_app = htmlspecialchars($appearance['story_title']);
                            }
                            echo $display_story_title_char_app;
                            ?>
                            <?php if ($character['first_appearance_story_id'] == $appearance['story_id'] && $character['first_appearance_comic_id'] == $appearance['comic_id']): ?>
                                <span class="first-appearance-badge" title="Prima apparizione di <?php echo htmlspecialchars($character['name']); ?>">1ª App.</span>
                            <?php endif; ?>
                        </a></p>
                        <p><small>Pubblicazione Albo: <?php echo $appearance['publication_date'] ? format_date_italian($appearance['publication_date']) : 'N/D'; ?></small></p>
                        <?php if ($current_sort_char_detail === 'story_created_at_desc' && isset($appearance['story_created_at'])): ?>
                            <p class="story-insertion-date"><small>Storia Inserita il: <?php echo format_date_italian($appearance['story_created_at'], "d MMMM Y, H:i"); ?></small></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-controls">
                <?php
                $base_pag_url_params = [];
                if (!empty($character['slug'])) { $base_pag_url_params['slug'] = $character['slug']; } else { $base_pag_url_params['id'] = $character_id; }
                if ($current_sort_char_detail !== 'date_desc') $base_pag_url_params['sort'] = $current_sort_char_detail;
                
                $base_pag_url_for_pagination = BASE_URL . "character_detail.php?" . http_build_query($base_pag_url_params);
                $separator_for_pagination = '&';
                ?>

                <?php if ($current_page > 1): ?>
                    <a href="<?php echo $base_pag_url_for_pagination . $separator_for_pagination; ?>page=<?php echo $current_page - 1; ?>#characterAppearancesTabsNav" title="Pagina precedente">&laquo; Prec.</a>
                <?php endif; ?>

                <?php
                $num_links_edges = 2; $num_links_around = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == $current_page):
                        echo '<span class="current-page">'.$i.'</span>';
                    elseif ($i <= $num_links_edges || $i > $total_pages - $num_links_edges || ($i >= $current_page - $num_links_around && $i <= $current_page + $num_links_around)):
                        echo '<a href="'.$base_pag_url_for_pagination . $separator_for_pagination.'page='.$i.'#characterAppearancesTabsNav">'.$i.'</a>';
                    elseif (
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
                    <a href="<?php echo $base_pag_url_for_pagination . $separator_for_pagination; ?>page=<?php echo $current_page + 1; ?>#characterAppearancesTabsNav" title="Pagina successiva">Succ. &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
             <p style="padding-top: 15px;"><?php echo htmlspecialchars($character['name']); ?> non ha apparizioni catalogate secondo il criterio "<?php echo htmlspecialchars($sort_options_char_detail[$current_sort_char_detail]); ?>".</p>
        <?php endif; ?>
    </div>
    
    <p style="margin-top:30px;"><a href="characters_page.php" class="btn btn-secondary">&laquo; Torna all'elenco personaggi</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const characterTabsNav = document.getElementById('characterAppearancesTabsNav');
    if (window.location.hash && window.location.hash === '#characterAppearancesTabsNav' && characterTabsNav) {
        const header = document.querySelector('header'); 
        const headerHeight = header ? header.offsetHeight : 0;
        const tabsNavTopPosition = characterTabsNav.getBoundingClientRect().top + window.pageYOffset - headerHeight - 10;
        
        setTimeout(() => {
            window.scrollTo({ 
                top: tabsNavTopPosition, 
                behavior: 'auto'
            });
        }, 100);
    }
});
</script>

<?php
require_once 'includes/footer.php';
$mysqli->close();
?>
                