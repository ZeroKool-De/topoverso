<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 

// --- Logica per accettare sia ID che slug ---
$person_id = null;
$person_slug = null;

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $person_id = (int)$_GET['id'];
} elseif (isset($_GET['slug'])) {
    $person_slug = trim($_GET['slug']);
}

if (empty($person_id) && empty($person_slug)) {
    header('Location: authors_page.php?message=Identificativo autore non valido');
    exit;
}

$query_field = $person_id ? 'person_id' : 'slug';
$query_param = $person_id ?: $person_slug;
$param_type = $person_id ? 'i' : 's';

$stmt_person = $mysqli->prepare("SELECT person_id, name, slug, biography, person_image FROM persons WHERE {$query_field} = ?");
if ($stmt_person === false) {
    die("Errore nella preparazione della query per i dettagli dell'autore: " . $mysqli->error);
}
$stmt_person->bind_param($param_type, $query_param);
$stmt_person->execute();
$result_person = $stmt_person->get_result();

if ($result_person->num_rows === 0) {
    header('Location: authors_page.php?message=Autore non trovato');
    exit;
}
$author = $result_person->fetch_assoc();
$stmt_person->close();

// Assicuriamoci che l'ID sia sempre disponibile per le query successive
$person_id = $author['person_id'];

// --- Recupero collaboratori e personaggi frequenti (MODIFICATO CON LINK) ---
$frequent_collaborators = [];
$sql_collaborators = "
    SELECT p2.person_id, p2.name, p2.slug, COUNT(DISTINCT sp1.story_id) as collaboration_count
    FROM story_persons sp1
    JOIN story_persons sp2 ON sp1.story_id = sp2.story_id AND sp1.person_id != sp2.person_id
    JOIN persons p2 ON sp2.person_id = p2.person_id
    WHERE sp1.person_id = ?
    GROUP BY p2.person_id, p2.name, p2.slug
    ORDER BY collaboration_count DESC
    LIMIT 5";
$stmt_collab = $mysqli->prepare($sql_collaborators);
$stmt_collab->bind_param("i", $person_id);
$stmt_collab->execute();
$result_collab = $stmt_collab->get_result();
while ($row = $result_collab->fetch_assoc()) {
    $frequent_collaborators[] = $row;
}
$stmt_collab->close();

$top_characters_by_author = [];
$sql_top_chars = "
    SELECT c.character_id, c.name, c.slug, c.character_image, COUNT(DISTINCT sc.story_id) AS appearance_count
    FROM story_persons sp
    JOIN story_characters sc ON sp.story_id = sc.story_id
    JOIN characters c ON sc.character_id = c.character_id
    WHERE sp.person_id = ?
    GROUP BY c.character_id, c.name, c.slug, c.character_image
    ORDER BY appearance_count DESC
    LIMIT 5";
$stmt_top_chars = $mysqli->prepare($sql_top_chars);
$stmt_top_chars->bind_param("i", $person_id);
$stmt_top_chars->execute();
$result_top_chars = $stmt_top_chars->get_result();
while ($row = $result_top_chars->fetch_assoc()) {
    $top_characters_by_author[] = $row;
}
$stmt_top_chars->close();


$page_title = "Scheda di " . htmlspecialchars($author['name']);

$role_sort_directions = [];
$contributions_by_role = [];

$sql_comic_contrib_base = "
    SELECT DISTINCT c.comic_id, c.slug, c.issue_number, c.title AS comic_title, c.cover_image, c.publication_date, cp.role
    FROM comic_persons cp
    JOIN comics c ON cp.comic_id = c.comic_id
    WHERE cp.person_id = ?
";

$sql_story_contrib_base = "
    SELECT DISTINCT c.comic_id, c.slug, c.issue_number, c.title AS comic_title, c.cover_image, c.publication_date,
           s.story_id, s.title AS story_title, sp.role, s.first_page_image AS story_first_page_image,
           s.story_title_main, s.part_number, s.total_parts, s.created_at as story_created_at, s.sequence_in_comic
    FROM story_persons sp
    JOIN stories s ON sp.story_id = s.story_id
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE sp.person_id = ?
";

$author_roles = [];
$stmt_roles_comic = $mysqli->prepare("SELECT DISTINCT role FROM comic_persons WHERE person_id = ? AND role IS NOT NULL AND role != '' ORDER BY role ASC");
$stmt_roles_comic->bind_param("i", $person_id);
$stmt_roles_comic->execute();
$res_roles_comic = $stmt_roles_comic->get_result();
while($r_c = $res_roles_comic->fetch_assoc()) {
    $role_key_temp = trim($r_c['role']);
    if (!empty($role_key_temp)) $author_roles[$role_key_temp] = $role_key_temp;
}
$stmt_roles_comic->close();

$stmt_roles_story = $mysqli->prepare("SELECT DISTINCT role FROM story_persons WHERE person_id = ? AND role IS NOT NULL AND role != '' ORDER BY role ASC");
$stmt_roles_story->bind_param("i", $person_id);
$stmt_roles_story->execute();
$res_roles_story = $stmt_roles_story->get_result();
while($r_s = $res_roles_story->fetch_assoc()) {
    $role_key_temp = trim($r_s['role']);
    if (!empty($role_key_temp)) $author_roles[$role_key_temp] = $role_key_temp;
}
$stmt_roles_story->close();
ksort($author_roles);

foreach ($author_roles as $role_key) {
    if (empty($role_key)) continue;

    if (!isset($contributions_by_role[$role_key])) {
        $contributions_by_role[$role_key] = [];
    }
    
    $order_by_comic_sql = "ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC";
    $stmt_comic_contrib = $mysqli->prepare($sql_comic_contrib_base . " AND cp.role = ? " . $order_by_comic_sql);
    if ($stmt_comic_contrib === false) { die("Errore SQL (comic_contrib per $role_key): " . $mysqli->error); }
    $stmt_comic_contrib->bind_param("is", $person_id, $role_key);
    $stmt_comic_contrib->execute();
    $result_comic_contrib = $stmt_comic_contrib->get_result();
    if ($result_comic_contrib) {
        while ($row = $result_comic_contrib->fetch_assoc()) {
            $contributions_by_role[$role_key][] = [
                'type' => 'comic_role', 'comic_id' => $row['comic_id'], 'slug' => $row['slug'], 'issue_number' => $row['issue_number'],
                'comic_title' => $row['comic_title'], 'cover_image' => $row['cover_image'],
                'publication_date' => $row['publication_date'], 'role' => $row['role'],
                'story_id' => null, 'story_title' => null, 'story_first_page_image' => null,
                'story_title_main' => null, 'part_number' => null, 'total_parts' => null
            ];
        } $result_comic_contrib->free();
    } $stmt_comic_contrib->close();

    $order_by_story_sql = "ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC, s.sequence_in_comic ASC";

    $stmt_story_contrib = $mysqli->prepare($sql_story_contrib_base . " AND sp.role = ? " . $order_by_story_sql);
    if ($stmt_story_contrib === false) { die("Errore SQL (story_contrib per $role_key): " . $mysqli->error); }
    $stmt_story_contrib->bind_param("is", $person_id, $role_key);
    $stmt_story_contrib->execute();
    $result_story_contrib = $stmt_story_contrib->get_result();
    if ($result_story_contrib) {
        while ($row = $result_story_contrib->fetch_assoc()) {
             $contributions_by_role[$role_key][] = [
                'type' => 'story_role', 'comic_id' => $row['comic_id'], 'slug' => $row['slug'], 'issue_number' => $row['issue_number'],
                'comic_title' => $row['comic_title'], 'cover_image' => $row['cover_image'],
                'publication_date' => $row['publication_date'], 'role' => $row['role'],
                'story_id' => $row['story_id'], 'story_title' => $row['story_title'],
                'story_first_page_image' => $row['story_first_page_image'],
                'story_title_main' => $row['story_title_main'], 'part_number' => $row['part_number'],
                'total_parts' => $row['total_parts'], 'story_created_at' => $row['story_created_at'],
                'sequence_in_comic' => $row['sequence_in_comic']
            ];
        } $result_story_contrib->free();
    } $stmt_story_contrib->close();
}


$latest_inserted_stories = [];
$sql_latest_stories = "
    SELECT DISTINCT
        s.story_id, s.title AS story_title, s.first_page_image AS story_first_page_image,
        s.story_title_main, s.part_number, s.total_parts, s.created_at AS story_created_at,
        c.comic_id, c.slug, c.issue_number, c.title AS comic_title, c.cover_image, c.publication_date,
        GROUP_CONCAT(DISTINCT sp.role ORDER BY sp.role SEPARATOR ', ') as author_roles_in_story
    FROM story_persons sp
    JOIN stories s ON sp.story_id = s.story_id
    JOIN comics c ON s.comic_id = c.comic_id
    WHERE sp.person_id = ?
    GROUP BY s.story_id, c.comic_id 
    ORDER BY s.created_at DESC
    LIMIT 50 
";
$stmt_latest_stories = $mysqli->prepare($sql_latest_stories);
if ($stmt_latest_stories === false) { die("Errore preparazione query ultime storie: " . $mysqli->error); }
$stmt_latest_stories->bind_param("i", $person_id);
$stmt_latest_stories->execute();
$result_latest_stories = $stmt_latest_stories->get_result();
if ($result_latest_stories) {
    while ($row = $result_latest_stories->fetch_assoc()) {
        $latest_inserted_stories[] = $row;
    }
    $result_latest_stories->free();
}
$stmt_latest_stories->close();


// --- RIGA QUERY CORRETTA ---
// Query per le copertine variant realizzate da questa persona
$variant_covers_by_person = [];
$stmt_variants = $mysqli->prepare(
    "SELECT c.comic_id, c.issue_number, c.title as comic_title, c.slug, c.publication_date, 
            cvc.image_path AS variant_cover_image, cvc.caption AS variant_caption
     FROM comic_variant_covers cvc
     JOIN comics c ON cvc.comic_id = c.comic_id
     WHERE cvc.artist_id = ?
     ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC"
);
$stmt_variants->bind_param("i", $person_id);
$stmt_variants->execute();
$result_variants = $stmt_variants->get_result();
while ($row = $result_variants->fetch_assoc()) {
    $variant_covers_by_person[] = $row;
}
$stmt_variants->close();

// >>> INIZIO BLOCCO DA AGGIUNGERE <<<

// Calcoliamo i conteggi corretti di storie/albi unici per ogni ruolo
$unique_counts_by_role = [];
foreach ($contributions_by_role as $role_name => $contributions) {
    if (empty($contributions)) {
        $unique_counts_by_role[$role_name] = 0;
        continue;
    }
    
    $unique_ids = [];
    foreach ($contributions as $item) {
        // Creiamo una chiave unica combinando il tipo (storia/albo) e l'ID
        $key = ($item['story_id'] ? 'story_' . $item['story_id'] : 'comic_' . $item['comic_id']);
        $unique_ids[$key] = true;
    }
    $unique_counts_by_role[$role_name] = count($unique_ids);
}
// >>> FINE BLOCCO DA AGGIUNGERE <<<

require_once 'includes/header.php';
?>
<div class="container page-content-container">
    <div class="author-detail-header">
        <?php if ($author['person_image']): ?>
            <img src="<?php echo UPLOADS_URL . htmlspecialchars($author['person_image']); ?>" alt="<?php echo htmlspecialchars($author['name']); ?>" class="author-image-main">
        <?php else: ?>
            <?php echo generate_image_placeholder($author['name'], 180, 180, 'author-image-main author-detail-placeholder'); ?>
        <?php endif; ?>
        <div class="author-info">
            <h1><?php echo htmlspecialchars($author['name']); ?></h1>
            <?php if (!empty($author['biography'])): ?>
                <p class="biography"><?php echo nl2br(htmlspecialchars($author['biography'])); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="author-related-info-grid">
        <?php if (!empty($frequent_collaborators)): ?>
            <div class="left-column-section related-info-box">
                <h4>Collaboratori Frequenti</h4>
                <ul>
                    <?php foreach ($frequent_collaborators as $collaborator): ?>
                        <li>
                            <?php $collaborator_link = !empty($collaborator['slug']) ? 'author_detail.php?slug=' . urlencode($collaborator['slug']) : 'author_detail.php?id=' . $collaborator['person_id']; ?>
                            <a href="<?php echo $collaborator_link; ?>" title="Vedi scheda di <?php echo htmlspecialchars($collaborator['name']); ?>">
                                <?php echo htmlspecialchars($collaborator['name']); ?>
                            </a>
                            <small>
                                (<a href="author_collaborations.php?person1=<?php echo $person_id; ?>&person2=<?php echo $collaborator['person_id']; ?>" 
                                   title="Vedi le <?php echo $collaborator['collaboration_count']; ?> storie in collaborazione">
                                   <?php echo $collaborator['collaboration_count']; ?> storie
                                </a>)
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($top_characters_by_author)): ?>
            <div class="left-column-section related-info-box">
                <h4>Personaggi più Utilizzati</h4>
                <ul>
                    <?php foreach ($top_characters_by_author as $character): ?>
                        <li>
                            <?php $character_link = !empty($character['slug']) ? 'character_detail.php?slug=' . urlencode($character['slug']) : 'character_detail.php?id=' . $character['character_id']; ?>
                            <a href="<?php echo $character_link; ?>" title="Vedi scheda di <?php echo htmlspecialchars($character['name']); ?>">
                                <?php if ($character['character_image']): ?>
                                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($character['character_image']); ?>" class="related-info-thumb">
                                <?php else: ?>
                                    <?php echo generate_image_placeholder($character['name'], 24, 24, 'related-info-thumb'); ?>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($character['name']); ?>
                            </a>
                            <small>
                                (<a href="author_character_stories.php?person=<?php echo $person_id; ?>&character=<?php echo $character['character_id']; ?>" 
                                   title="Vedi le <?php echo $character['appearance_count']; ?> storie con questo personaggio">
                                   <?php echo $character['appearance_count']; ?> storie
                                </a>)
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($contributions_by_role) || !empty($latest_inserted_stories) || !empty($variant_covers_by_person)): ?>
        <div class="contributions-section">
            <h2 style="font-size: 1.9em; color: #007bff; border-bottom: 2px solid #e0e0e0; padding-bottom: 12px; margin-bottom: 0;">
                Contributi Editoriali
            </h2>

            <nav class="author-roles-tabs-nav">
                <ul>
                    <li>
                        <a href="#latest-inserted" 
                           class="author-role-tab-link active" data-tab-target="latest-inserted">Ultime Storie Inserite (<?php echo count($latest_inserted_stories); ?>)</a>
                    </li>
                    <?php
                    if (!empty($variant_covers_by_person)):
                        $role_id_variant = 'role-variant';
                    ?>
                        <li>
                            <a href="#<?php echo $role_id_variant; ?>" class="author-role-tab-link" data-tab-target="<?php echo $role_id_variant; ?>">
                                Copertine Variant (<?php echo count($variant_covers_by_person); ?>)
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php foreach ($contributions_by_role as $role_name => $contributions_list): 
                        if (empty($role_name) || empty($contributions_list)) continue;
                        $role_id = 'role-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($role_name)); ?>
                        <li>
                            <a href="#<?php echo $role_id; ?>" 
                               class="author-role-tab-link" 
                               data-tab-target="<?php echo $role_id; ?>">
    <?php echo htmlspecialchars(ucfirst($role_name)); ?> (<?php echo $unique_counts_by_role[$role_name] ?? 0; ?>)
</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="author-roles-tab-content-panels">
                <div id="latest-inserted" class="author-role-tab-content active">
                    <div class="tab-items-container">
                        <?php if (!empty($latest_inserted_stories)): ?>
                            <?php foreach ($latest_inserted_stories as $contrib): ?>
                                <div class="contribution-item">
                                    <div class="contribution-cover">
                                        <?php $link_href_latest = !empty($contrib['slug']) ? "comic_detail.php?slug=" . urlencode($contrib['slug']) : "comic_detail.php?id=" . $contrib['comic_id']; ?>
                                        <a href="<?php echo $link_href_latest; ?>#story-item-<?php echo $contrib['story_id']; ?>">
                                        <?php 
                                        $display_image_latest = null;
                                        if (!empty($contrib['story_first_page_image'])) {
                                            $display_image_latest = UPLOADS_URL . htmlspecialchars($contrib['story_first_page_image']);
                                        } elseif (!empty($contrib['cover_image'])) {
                                            $display_image_latest = UPLOADS_URL . htmlspecialchars($contrib['cover_image']);
                                        }
                                        if ($display_image_latest): ?>
                                            <img src="<?php echo $display_image_latest; ?>" alt="Immagine per Topolino #<?php echo htmlspecialchars($contrib['issue_number']); ?>">
                                        <?php else: echo generate_image_placeholder(htmlspecialchars($contrib['issue_number']), 70, 100, 'comic-list-placeholder'); endif; ?>
                                        </a>
                                    </div>
                                    <div class="contribution-details">
                                        <h4><a href="<?php echo $link_href_latest; ?>#story-item-<?php echo $contrib['story_id']; ?>">Topolino #<?php echo htmlspecialchars($contrib['issue_number']); if(!empty($contrib['comic_title'])): ?> - <?php echo htmlspecialchars($contrib['comic_title']); endif; ?></a></h4>
                                        <p class="story-title-link"><strong>Storia:</strong> <a href="<?php echo $link_href_latest; ?>#story-item-<?php echo $contrib['story_id']; ?>">
                                            <?php
                                            $display_story_title_latest = '';
                                            if (!empty($contrib['story_title_main'])) {
                                                $display_story_title_latest = '<strong>' . htmlspecialchars($contrib['story_title_main']) . '</strong>';
                                                if (!empty($contrib['part_number'])) {
                                                    $part_specific_title_latest = htmlspecialchars($contrib['story_title']);
                                                    $expected_part_title_latest = 'Parte ' . htmlspecialchars($contrib['part_number']);
                                                    if ($part_specific_title_latest !== htmlspecialchars($contrib['story_title_main']) && $part_specific_title_latest !== $expected_part_title_latest && strtolower($part_specific_title_latest) !== strtolower($expected_part_title_latest) ) {
                                                        $display_story_title_latest .= ': ' . $part_specific_title_latest . ' <em>(Parte ' . htmlspecialchars($contrib['part_number']) . ')</em>';
                                                    } else { $display_story_title_latest .= ' - Parte ' . htmlspecialchars($contrib['part_number']); }
                                                } elseif (!empty($contrib['story_title']) && strtolower($contrib['story_title']) !== strtolower($contrib['story_title_main'])) {
                                                    $display_story_title_latest .= ': ' . htmlspecialchars($contrib['story_title']);
                                                }
                                                if ($contrib['total_parts']) { $display_story_title_latest .= ' (di ' . htmlspecialchars($contrib['total_parts']) . ')'; }
                                            } else { $display_story_title_latest = htmlspecialchars($contrib['story_title']); }
                                            echo $display_story_title_latest;
                                            ?>
                                        </a></p>
                                        <?php if (!empty($contrib['author_roles_in_story'])): ?>
                                            <p class="role">Ruolo/i in questa storia: <?php echo htmlspecialchars($contrib['author_roles_in_story']); ?></p>
                                        <?php endif; ?>
                                        <p class="publication-date">Pubblicazione albo: <?php echo $contrib['publication_date'] ? date("d/m/Y", strtotime($contrib['publication_date'])) : 'N/D'; ?></p>
                                        <p class="story-insertion-date"><small>Storia Inserita il: <?php echo $contrib['story_created_at'] ? date("d/m/Y H:i", strtotime($contrib['story_created_at'])) : 'N/D'; ?></small></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Nessuna storia recentemente inserita trovata per questo autore.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($variant_covers_by_person)):
                    $role_id_variant = 'role-variant';
                ?>
                <div id="<?php echo $role_id_variant; ?>" class="author-role-tab-content">
                    <div class="tab-items-container">
                        <?php foreach ($variant_covers_by_person as $contrib): ?>
                            <div class="contribution-item">
                                <div class="contribution-cover">
                                    <?php $link_href = !empty($contrib['slug']) ? "comic_detail.php?slug=" . urlencode($contrib['slug']) : "comic_detail.php?id=" . $contrib['comic_id']; ?>
                                    <a href="<?php echo $link_href; ?>" title="<?php echo htmlspecialchars($contrib['variant_caption'] ?? 'Copertina Variant'); ?>">
                                    <?php if ($contrib['variant_cover_image']): ?>
                                        <img src="<?php echo UPLOADS_URL . htmlspecialchars($contrib['variant_cover_image']); ?>" alt="Variant per Topolino #<?php echo htmlspecialchars($contrib['issue_number']); ?>">
                                    <?php else: echo generate_image_placeholder(htmlspecialchars($contrib['issue_number']), 70, 100, 'comic-list-placeholder'); endif; ?>
                                    </a>
                                </div>
                                <div class="contribution-details">
                                    <h4><a href="<?php echo $link_href; ?>">Topolino #<?php echo htmlspecialchars($contrib['issue_number']); if(!empty($contrib['comic_title'])): ?> - <?php echo htmlspecialchars($contrib['comic_title']); endif; ?></a></h4>
                                    <?php if (!empty($contrib['variant_caption'])): ?>
                                        <p class="story-title-link"><strong>Dettagli Variant:</strong> <?php echo htmlspecialchars($contrib['variant_caption']); ?></p>
                                    <?php endif; ?>
                                    <p class="publication-date">Data pubblicazione albo: <?php echo $contrib['publication_date'] ? date("d/m/Y", strtotime($contrib['publication_date'])) : 'N/D'; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php foreach ($contributions_by_role as $role_name => $contributions_list): 
                    if (empty($role_name) || empty($contributions_list)) continue;
                    $panel_id = 'role-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($role_name));
                    ?>
                    <div id="<?php echo $panel_id; ?>" class="author-role-tab-content">
                        <div class="tab-items-container">
                            <?php if (!empty($contributions_list)): ?>
                                <?php foreach ($contributions_list as $contrib): ?>
                                    <div class="contribution-item">
                                        <div class="contribution-cover">
                                            <?php $link_href = !empty($contrib['slug']) ? "comic_detail.php?slug=" . urlencode($contrib['slug']) : "comic_detail.php?id=" . $contrib['comic_id']; ?>
                                            <a href="<?php echo $link_href; ?><?php if ($contrib['type'] === 'story_role' && $contrib['story_id']) echo '#story-item-' . $contrib['story_id']; ?>">
                                            <?php 
                                            $display_image = null;
                                            if ($contrib['type'] === 'story_role' && !empty($contrib['story_first_page_image'])) {
                                                $display_image = UPLOADS_URL . htmlspecialchars($contrib['story_first_page_image']);
                                            } elseif (!empty($contrib['cover_image'])) {
                                                $display_image = UPLOADS_URL . htmlspecialchars($contrib['cover_image']);
                                            }
                                            if ($display_image): ?>
                                                <img src="<?php echo $display_image; ?>" alt="Immagine per Topolino #<?php echo htmlspecialchars($contrib['issue_number']); ?>">
                                            <?php else: echo generate_image_placeholder(htmlspecialchars($contrib['issue_number']), 70, 100, 'comic-list-placeholder'); endif; ?>
                                            </a>
                                        </div>
                                        <div class="contribution-details">
                                            <h4><a href="<?php echo $link_href; ?><?php if ($contrib['type'] === 'story_role' && $contrib['story_id']) echo '#story-item-' . $contrib['story_id']; ?>">Topolino #<?php echo htmlspecialchars($contrib['issue_number']); if(!empty($contrib['comic_title'])): ?> - <?php echo htmlspecialchars($contrib['comic_title']); endif; ?></a></h4>
                                            <?php if ($contrib['type'] === 'story_role' && !empty($contrib['story_title'])): ?>
                                                <p class="story-title-link"><strong>Storia:</strong> <a href="<?php echo $link_href; ?>#story-item-<?php echo $contrib['story_id']; ?>">
                                                    <?php
                                                    $display_story_title_author = '';
                                                    if (!empty($contrib['story_title_main'])) {
                                                        $display_story_title_author = '<strong>' . htmlspecialchars($contrib['story_title_main']) . '</strong>';
                                                        if (!empty($contrib['part_number'])) {
                                                            $part_specific_title_author = htmlspecialchars($contrib['story_title']);
                                                            $expected_part_title_author = 'Parte ' . htmlspecialchars($contrib['part_number']);
                                                            if ($part_specific_title_author !== htmlspecialchars($contrib['story_title_main']) && $part_specific_title_author !== $expected_part_title_author && strtolower($part_specific_title_author) !== strtolower($expected_part_title_author) ) {
                                                                $display_story_title_author .= ': ' . $part_specific_title_author . ' <em>(Parte ' . htmlspecialchars($contrib['part_number']) . ')</em>';
                                                            } else { $display_story_title_author .= ' - Parte ' . htmlspecialchars($contrib['part_number']); }
                                                        } elseif (!empty($contrib['story_title']) && strtolower($contrib['story_title']) !== strtolower($contrib['story_title_main'])) {
                                                            $display_story_title_author .= ': ' . htmlspecialchars($contrib['story_title']);
                                                        }
                                                        if ($contrib['total_parts']) { $display_story_title_author .= ' (di ' . htmlspecialchars($contrib['total_parts']) . ')'; }
                                                    } else { $display_story_title_author = htmlspecialchars($contrib['story_title']); }
                                                    echo $display_story_title_author;
                                                    ?>
                                                </a></p>
                                            <?php endif; ?>
                                            <p class="publication-date">Data pubblicazione albo: <?php echo $contrib['publication_date'] ? date("d/m/Y", strtotime($contrib['publication_date'])) : 'N/D'; ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Nessun contributo trovato per il ruolo "<?php echo htmlspecialchars(ucfirst($role_name)); ?>".</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <p><?php echo htmlspecialchars($author['name']); ?> non ha contributi catalogati.</p>
    <?php endif; ?>
    
    <p style="margin-top:30px;"><a href="authors_page.php" class="btn btn-secondary">&laquo; Torna all'elenco autori</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemsPerPage = 50;
    
    function setupClientPagination(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const itemsContainer = container.querySelector('.tab-items-container');
        if (!itemsContainer) return;

        const items = Array.from(itemsContainer.getElementsByClassName('contribution-item'));
        const totalItems = items.length;

        if (totalItems <= itemsPerPage) {
            return;
        }

        const totalPages = Math.ceil(totalItems / itemsPerPage);
        let currentPage = 1;

        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'client-pagination';
        container.appendChild(paginationContainer);

        function showPage(page) {
            const startIndex = (page - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            
            items.forEach((item, index) => {
                item.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
            });

            updatePaginationControls(page);
        }

        function updatePaginationControls(page) {
            paginationContainer.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = 'client-pagination-btn';
                if (i === page) {
                    pageBtn.classList.add('active');
                }
                pageBtn.addEventListener('click', function() {
                    currentPage = i;
                    showPage(currentPage);
                    const tabsNav = document.querySelector('.author-roles-tabs-nav');
                    if (tabsNav) {
                        const headerHeight = document.querySelector('header').offsetHeight;
                        const targetScrollPos = tabsNav.offsetTop - headerHeight - 10;
                        window.scrollTo({ top: targetScrollPos, behavior: 'smooth' });
                    }
                });
                paginationContainer.appendChild(pageBtn);
            }
        }

        showPage(1);
    }
    
    document.querySelectorAll('.author-role-tab-content').forEach(panel => {
        setupClientPagination(panel.id);
    });

    const tabLinksAuthorRoles = document.querySelectorAll('.author-roles-tabs-nav .author-role-tab-link');
    const tabContentPanelsAuthorRoles = document.querySelectorAll('.author-roles-tab-content-panels .author-role-tab-content');
    
    const authorIdForJs = <?php echo json_encode($person_id); ?>;
    const authorSlugForJs = <?php echo json_encode($author['slug']); ?>;

    function activateAuthorRoleTab(targetId) {
        let panelToShow = null;
        tabContentPanelsAuthorRoles.forEach(panel => {
            if (panel.id === targetId) {
                panel.classList.add('active');
                panelToShow = panel;
            } else {
                panel.classList.remove('active');
            }
        });
        tabLinksAuthorRoles.forEach(link => {
            if (link.dataset.tabTarget === targetId) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
        return panelToShow; 
    }

    tabLinksAuthorRoles.forEach(link => {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const targetId = this.dataset.tabTarget;
            activateAuthorRoleTab(targetId);
            
            let newUrl = `author_detail.php?`;
            if (authorSlugForJs) {
                newUrl += `slug=${encodeURIComponent(authorSlugForJs)}`;
            } else {
                newUrl += `id=${authorIdForJs}`;
            }
            newUrl += '#' + targetId;

            if (history.pushState) {
                history.pushState(null, null, newUrl);
            } else {
                window.location.href = newUrl;
            }
        });
    });

    let initialAuthorRoleTabId = 'latest-inserted';
    if (window.location.hash) { 
        const hashTargetId = window.location.hash.substring(1);
        if (document.getElementById(hashTargetId)) {
             initialAuthorRoleTabId = hashTargetId;
        }
    }
    activateAuthorRoleTab(initialAuthorRoleTabId);
});
</script>

<?php
require_once 'includes/footer.php';
$mysqli->close();
?>