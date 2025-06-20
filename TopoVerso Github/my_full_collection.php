<?php
require_once 'config/config.php'; // Contiene session_start()
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 

if (!isset($_SESSION['user_id_frontend'])) {
    header('Location: login.php?message=Devi essere loggato per visualizzare la tua collezione completa.');
    exit;
}

$user_id = $_SESSION['user_id_frontend'];
$username = $_SESSION['username_frontend'] ?? 'Utente';
$page_title = "La Mia Collezione Completa";

$sort_options = [
    'added_at_desc' => 'Data Aggiunta (Più Recenti)',
    'added_at_asc' => 'Data Aggiunta (Meno Recenti)',
    'publication_date_desc' => 'Data Pubblicazione (Più Recenti)',
    'publication_date_asc' => 'Data Pubblicazione (Meno Recenti)',
    'is_read_first' => 'Letti per Primi',
    'is_unread_first' => 'Non Letti per Primi',
    'rating_desc' => 'Voto (Decrescente)',
    'rating_asc' => 'Voto (Crescente)',
    'issue_number_desc' => 'Numero Albo (Decrescente)',
    'issue_number_asc' => 'Numero Albo (Crescente)',
];
$current_sort = $_GET['sort'] ?? 'added_at_desc';
if (!array_key_exists($current_sort, $sort_options)) {
    $current_sort = 'added_at_desc';
}

$all_collection_items = [];

// 1. Recupera i fumetti reali dalla collezione
$sql_real_comics = "
    SELECT 
        c.comic_id, c.issue_number, c.title AS comic_title, c.cover_image, c.publication_date,
        uc.is_read, uc.rating, uc.added_at,
        'real' as item_type, NULL as placeholder_id, NULL as issue_number_placeholder
    FROM user_collections uc
    JOIN comics c ON uc.comic_id = c.comic_id
    WHERE uc.user_id = ?
";
$stmt_real = $mysqli->prepare($sql_real_comics);
$stmt_real->bind_param("i", $user_id);
$stmt_real->execute();
$result_real = $stmt_real->get_result();
if ($result_real) {
    while ($row = $result_real->fetch_assoc()) {
        $all_collection_items[] = $row;
    }
    $result_real->free();
}
$stmt_real->close();

// 2. Recupera i placeholder PENDING della collezione
$sql_placeholders = "
    SELECT 
        NULL as comic_id, NULL as issue_number, NULL as comic_title, NULL as cover_image, NULL as publication_date,
        NULL as is_read, NULL as rating, ucp.added_at,
        'placeholder' as item_type, ucp.placeholder_id, ucp.issue_number_placeholder
    FROM user_collection_placeholders ucp
    WHERE ucp.user_id = ? AND ucp.status = 'pending' 
";
$stmt_placeholder = $mysqli->prepare($sql_placeholders);
$stmt_placeholder->bind_param("i", $user_id);
$stmt_placeholder->execute();
$result_placeholder = $stmt_placeholder->get_result();
if ($result_placeholder) {
    while ($row_placeholder = $result_placeholder->fetch_assoc()) {
        $all_collection_items[] = $row_placeholder;
    }
    $result_placeholder->free();
}
$stmt_placeholder->close();


// 3. Ordina l'array combinato
usort($all_collection_items, function($a, $b) use ($current_sort) {
    $typeA = $a['item_type'];
    $typeB = $b['item_type'];

    $dateA_val = ($typeA === 'real') ? ($a['publication_date'] ?? null) : null;
    $dateB_val = ($typeB === 'real') ? ($b['publication_date'] ?? null) : null;
    
    $addedA_val = $a['added_at'] ?? null;
    $addedB_val = $b['added_at'] ?? null;

    $readA_val = ($typeA === 'real') ? ($a['is_read'] ?? 0) : -1; 
    $readB_val = ($typeB === 'real') ? ($b['is_read'] ?? 0) : -1;

    $ratingA_val = ($typeA === 'real') ? ($a['rating'] ?? -1) : -1; 
    $ratingB_val = ($typeB === 'real') ? ($b['rating'] ?? -1) : -1;

    $issueA_val_str = ($typeA === 'real') ? $a['issue_number'] : $a['issue_number_placeholder'];
    $issueB_val_str = ($typeB === 'real') ? $b['issue_number'] : $b['issue_number_placeholder'];

    switch ($current_sort) {
        case 'publication_date_asc':
            $valA = $dateA_val ? strtotime($dateA_val) : PHP_INT_MAX;
            $valB = $dateB_val ? strtotime($dateB_val) : PHP_INT_MAX;
            if ($valA === $valB) return strnatcmp($issueA_val_str, $issueB_val_str);
            return $valA <=> $valB;
        case 'publication_date_desc':
            $valA = $dateA_val ? strtotime($dateA_val) : 0;
            $valB = $dateB_val ? strtotime($dateB_val) : 0;
            if ($valA === $valB) return strnatcmp($issueB_val_str, $issueA_val_str);
            return $valB <=> $valA;
        case 'added_at_asc':
            return strtotime($addedA_val) <=> strtotime($addedB_val);
        case 'is_read_first': 
            if ($readA_val === $readB_val) return strtotime($addedB_val) <=> strtotime($addedA_val);
            return $readB_val <=> $readA_val; 
        case 'is_unread_first': 
            if ($readA_val === $readB_val) return strtotime($addedB_val) <=> strtotime($addedA_val);
            return $readA_val <=> $readB_val; 
        case 'rating_desc': 
            if ($ratingA_val === $ratingB_val) return strtotime($addedB_val) <=> strtotime($addedA_val);
            return $ratingB_val <=> $ratingA_val;
        case 'rating_asc': 
            if ($ratingA_val === $ratingB_val) return strtotime($addedB_val) <=> strtotime($addedA_val);
            return $ratingA_val <=> $ratingB_val;
        case 'issue_number_desc':
            return strnatcmp($issueB_val_str, $issueA_val_str);
        case 'issue_number_asc':
            return strnatcmp($issueA_val_str, $issueB_val_str);
        case 'added_at_desc':
        default:
            return strtotime($addedB_val) <=> strtotime($addedA_val);
    }
});


$items_per_page = 50; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$total_items = count($all_collection_items);
$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

$paginated_items = array_slice($all_collection_items, $offset, $items_per_page);

$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
if ($message) {
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

require_once 'includes/header.php';
?>

<style>
    .collection-controls { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap:10px; }
    .collection-controls label { margin-right: 5px; font-weight: bold; }
    .collection-controls select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
    .pagination-controls { margin-top: 30px; text-align: center; }
    .pagination-controls a, .pagination-controls span { margin: 0 5px; padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; border-radius: 4px; color: #007bff; }
    .pagination-controls a:hover { background-color: #f0f0f0; }
    .pagination-controls span.current-page { background-color: #007bff; color: white; border-color: #007bff; font-weight: bold;}
    .pagination-controls span.disabled { color: #ccc; border-color: #eee; }

    .add-placeholder-form-container { background-color: #f9f9f9; padding: 20px; border-radius: 5px; border: 1px solid #e0e0e0; margin-bottom: 30px; }
    .add-placeholder-form-container h3 { margin-top: 0; margin-bottom: 15px; font-size: 1.3em; color: #333; }
    .add-placeholder-form-container .form-group { display: flex; align-items: center; gap: 10px; }
    .add-placeholder-form-container input[type="text"] { padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; flex-grow: 1; max-width: 250px; }
    .add-placeholder-form-container button { padding: 8px 15px; }
    
    .collection-item.placeholder-item .collection-item-details h3 {
        font-style: italic;
        color: #555;
    }
     .collection-item.placeholder-item .collection-item-details h3 a {
        pointer-events: none; 
        color: #555 !important; 
        text-decoration: none !important;
    }
    .collection-item.placeholder-item .collection-item-cover a {
        pointer-events: none; 
        cursor: default;
    }
</style>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?> <small>(Totale: <?php echo $total_items; ?>)</small></h1>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div id="add-placeholder-form" class="add-placeholder-form-container">
        <h3>Possiedi un numero non ancora in catalogo?</h3>
        <p><small>Aggiungilo qui come promemoria. Apparirà nella tua collezione con una copertina generica finché non verrà aggiunto ufficialmente al catalogo.</small></p>
        <form action="collection_actions.php" method="POST" class="form-inline">
            <input type="hidden" name="action" value="add_placeholder_to_collection">
            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(BASE_URL . 'my_full_collection.php?sort='.$current_sort.'&page='.$current_page); ?>">
            <div class="form-group">
                <label for="placeholder_issue_number">Numero Albo Mancante:</label>
                <input type="text" name="placeholder_issue_number" id="placeholder_issue_number" placeholder="Es. 1234 o 567bis" required>
                <button type="submit" class="btn btn-secondary btn-sm">Aggiungi Segnalazione</button>
            </div>
        </form>
    </div>

    <div class="collection-controls">
        <form method="GET" action="my_full_collection.php">
            <label for="sort">Ordina per:</label>
            <select name="sort" id="sort" onchange="this.form.submit()">
                <?php foreach ($sort_options as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo ($current_sort === $key) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if(isset($_GET['page']) && $current_page > 1): ?>
                 <input type="hidden" name="page" value="<?php echo $current_page; ?>">
            <?php endif; ?>
        </form>
        <p><a href="user_dashboard.php" class="btn btn-secondary btn-sm">&laquo; Torna alla Dashboard</a></p>
    </div>

    <?php if (!empty($paginated_items)): ?>
        <div class="collection-list">
            <?php foreach ($paginated_items as $item): ?>
                <div class="collection-item <?php echo $item['item_type'] === 'placeholder' ? 'placeholder-item' : ''; ?>">
                    <div class="collection-item-cover">
                        <?php if ($item['item_type'] === 'real'): ?>
                            <a href="comic_detail.php?id=<?php echo $item['comic_id']; ?>">
                            <?php if ($item['cover_image']): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($item['cover_image']); ?>" alt="Copertina <?php echo htmlspecialchars($item['issue_number']); ?>">
                            <?php else: echo generate_image_placeholder(htmlspecialchars($item['issue_number']), 70, 105, 'placeholder-img'); endif; ?>
                            </a>
                        <?php else: // E' un placeholder (solo pending ora) ?>
                            <a> 
                                <?php echo generate_comic_placeholder_cover(htmlspecialchars($item['issue_number_placeholder']), 70, 105); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="collection-item-details">
                        <h3>
                            <?php if ($item['item_type'] === 'real'): ?>
                                <a href="comic_detail.php?id=<?php echo $item['comic_id']; ?>">
                                    Topolino #<?php echo htmlspecialchars($item['issue_number']); ?>
                                    <?php if (!empty($item['comic_title'])): ?> - <?php echo htmlspecialchars($item['comic_title']); endif; ?>
                                </a>
                            <?php else: // Placeholder ?>
                                <a>Topolino #<?php echo htmlspecialchars($item['issue_number_placeholder']); ?> (Non in catalogo)</a>
                            <?php endif; ?>
                        </h3>
                        <p class="collection-item-info">
                            <?php if ($item['item_type'] === 'real'): ?>
                                Data Pubblicazione: <?php echo $item['publication_date'] ? date("d/m/Y", strtotime($item['publication_date'])) : 'N/D'; ?><br>
                            <?php endif; ?>
                            Aggiunto il: <?php echo date("d/m/Y H:i", strtotime($item['added_at'])); ?>
                        </p>
                        
                        <div class="collection-actions">
                            <?php if ($item['item_type'] === 'real'): ?>
                                <form action="collection_actions.php" method="POST">
                                    <input type="hidden" name="comic_id" value="<?php echo $item['comic_id']; ?>">
                                    <input type="hidden" name="action" value="update_read_status">
                                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(BASE_URL . 'my_full_collection.php?sort='.$current_sort.'&page='.$current_page); ?>">
                                    <label for="is_read_<?php echo $item['comic_id']; ?>">Letto:</label>
                                    <input type="checkbox" name="is_read" id="is_read_<?php echo $item['comic_id']; ?>" value="1" <?php echo $item['is_read'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                </form>
                                <form action="collection_actions.php" method="POST">
                                    <input type="hidden" name="comic_id" value="<?php echo $item['comic_id']; ?>">
                                    <input type="hidden" name="action" value="update_rating">
                                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(BASE_URL . 'my_full_collection.php?sort='.$current_sort.'&page='.$current_page); ?>">
                                    <label for="rating_<?php echo $item['comic_id']; ?>">Voto:</label>
                                    <select name="rating" id="rating_<?php echo $item['comic_id']; ?>" onchange="this.form.submit()">
                                        <option value="0" <?php echo is_null($item['rating']) ? 'selected' : ''; ?>>N/D</option>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($item['rating'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?> ★</option>
                                        <?php endfor; ?>
                                    </select>
                                </form>
                                <form action="collection_actions.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="comic_id" value="<?php echo $item['comic_id']; ?>">
                                    <input type="hidden" name="action" value="remove_from_collection">
                                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(BASE_URL . 'my_full_collection.php?sort='.$current_sort.'&page='.$current_page); ?>">
                                    <button type="submit" class="btn-remove-collection" onclick="return confirm('Rimuovere dalla collezione?');">Rimuovi</button>
                                </form>
                            <?php elseif ($item['item_type'] === 'placeholder'): // Solo placeholder PENDING ?>
                                <form action="collection_actions.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="placeholder_id" value="<?php echo $item['placeholder_id']; ?>">
                                    <input type="hidden" name="action" value="remove_placeholder_from_collection">
                                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(BASE_URL . 'my_full_collection.php?sort='.$current_sort.'&page='.$current_page); ?>">
                                    <button type="submit" class="btn-remove-collection" onclick="return confirm('Rimuovere questa segnalazione dalla tua lista?');">Rimuovi Segnalazione</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-controls">
             <?php if ($current_page > 1): ?>
                <a href="?sort=<?php echo $current_sort; ?>&page=<?php echo $current_page - 1; ?>">« Precedente</a>
            <?php else: ?>
                <span class="disabled">« Precedente</span>
            <?php endif; ?>
            <?php 
            $num_links_edges = 2; $num_links_vicino = 2; 
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i == $current_page): ?> <span class="current-page"><?php echo $i; ?></span>
                <?php elseif ($i <= $num_links_edges || $i > $total_pages - $num_links_edges || ($i >= $current_page - $num_links_vicino && $i <= $current_page + $num_links_vicino) ): ?>
                    <a href="?sort=<?php echo $current_sort; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php elseif (($i == $num_links_edges + 1 && $current_page > $num_links_edges + $num_links_vicino + 1) || ($i == $total_pages - $num_links_edges && $current_page < $total_pages - $num_links_edges - $num_links_vicino) ):
                    $show_dots_after_start = ($i == $num_links_edges + 1 && $current_page > $num_links_edges + $num_links_vicino + 1);
                    $show_dots_before_end = ($i == $total_pages - $num_links_edges && $current_page < $total_pages - $num_links_edges - $num_links_vicino);
                    $next_block_start = $current_page - $num_links_vicino; $prev_block_end = $current_page + $num_links_vicino;
                    if (($show_dots_after_start && $next_block_start > $num_links_edges + 1) || ($show_dots_before_end && $prev_block_end < $total_pages - $num_links_edges)) {
                         echo '<span class="disabled">...</span>';
                    }
                ?>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="?sort=<?php echo $current_sort; ?>&page=<?php echo $current_page + 1; ?>">Successiva »</a>
            <?php else: ?>
                <span class="disabled">Successiva »</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <p>La tua collezione è vuota. Inizia ad <a href="index.php">aggiungere fumetti</a> oppure segnala un numero mancante usando il form qui sopra!</p>
    <?php endif; ?>
</div>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>