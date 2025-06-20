<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 

$page_title = "Elenco Serie Storie";

// IMPOSTAZIONI DI ORDINAMENTO AGGIORNATE
$sort_options = [
    'title_asc' => 'Titolo (A-Z)',
    'title_desc' => 'Titolo (Z-A)',
    'most_episodes' => 'Più Episodi',
    'start_date_desc' => 'Data Inizio (Più Recenti)',
    'start_date_asc' => 'Data Inizio (Meno Recenti)',
    'latest_added' => 'Ultime Inserite nel Catalogo',
];
$current_sort = $_GET['sort'] ?? 'latest_added';
if (!array_key_exists($current_sort, $sort_options)) {
    $current_sort = 'title_asc';
}

// IMPOSTAZIONI DI PAGINAZIONE E LIMIT
$limit_options = [16, 32, 48, 64];
$items_per_page = 16; // Default
if (isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options)) {
    $items_per_page = (int)$_GET['limit'];
}

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// CONTEGGIO TOTALE SERIE
$total_series_result = $mysqli->query("SELECT COUNT(*) as total FROM story_series");
$total_series = $total_series_result ? $total_series_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_series / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

// COSTRUZIONE QUERY SQL
$order_by_sql = "";
switch ($current_sort) {
    case 'title_desc':
        $order_by_sql = "ORDER BY ss.title DESC";
        break;
    case 'most_episodes':
        $order_by_sql = "ORDER BY episode_count DESC, ss.title ASC";
        break;
    case 'latest_added':
        $order_by_sql = "ORDER BY ss.created_at DESC";
        break;
    case 'start_date_desc':
        $order_by_sql = "ORDER BY ss.start_date DESC, ss.title ASC";
        break;
    case 'start_date_asc':
        $order_by_sql = "ORDER BY ss.start_date ASC, ss.title ASC";
        break;
    case 'title_asc':
    default:
        $order_by_sql = "ORDER BY ss.title ASC";
        break;
}

$series_list = [];
// --- MODIFICATO: Aggiunto ss.slug alla select ---
$sql = "SELECT ss.series_id, ss.title, ss.slug, ss.description, ss.image_path, ss.start_date, COUNT(s.story_id) as episode_count
        FROM story_series ss
        LEFT JOIN stories s ON ss.series_id = s.series_id
        GROUP BY ss.series_id, ss.title, ss.description, ss.image_path, ss.start_date
        $order_by_sql
        LIMIT ? OFFSET ?";
        
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $items_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $series_list[] = $row;
    }
    $result->free();
}
$stmt->close();

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="controls-bar" style="margin-bottom: 25px;">
        <form action="series_list.php" method="GET" id="filterSortFormSeries" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
            <div>
                <label for="sort">Ordina per:</label>
                <select name="sort" id="sort" onchange="this.form.submit()">
                    <?php foreach ($sort_options as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($current_sort === $key) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="limit">Mostra:</label>
                <select name="limit" id="limit" onchange="this.form.submit()">
                    <?php foreach ($limit_options as $limit_value): ?>
                    <option value="<?php echo $limit_value; ?>" <?php echo ($items_per_page === $limit_value) ? 'selected' : ''; ?>>
                        <?php echo $limit_value; ?> per pagina
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-sm">Applica</button></noscript>
        </form>
    </div>

    <?php if (!empty($series_list)): ?>
        <div class="series-grid">
            <?php foreach ($series_list as $series): ?>
                <div class="series-card">
                    <?php // --- MODIFICATO: Logica link con slug ---
                    $series_link = !empty($series['slug']) ? 'series_detail.php?slug=' . urlencode($series['slug']) : 'series_detail.php?id=' . $series['series_id'];
                    ?>
                    <a href="<?php echo $series_link; ?>">
                        <?php if ($series['image_path']): ?>
                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($series['image_path']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="series-card-image">
                        <?php else: ?>
                            <?php echo generate_image_placeholder(htmlspecialchars($series['title']), 100, 100, 'series-card-image series-list-placeholder'); ?>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($series['title']); ?></h3>
                        <p class="story-count"><?php echo $series['episode_count']; ?> Storie</p>
                        <?php if(!empty($series['start_date'])): ?>
                            <p class="comic-date" style="font-size: 0.85em; color: #777;">Iniziata il: <?php echo format_date_italian($series['start_date']); ?></p>
                        <?php endif; ?>
                        <?php if(!empty($series['description'])): ?>
                            <p class="short-desc"><?php echo htmlspecialchars(substr(strip_tags($series['description']), 0, 120)); ?>...</p>
                        <?php endif; ?>
                        <span class="read-more">Vedi dettagli serie &raquo;</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-controls">
            <?php
            $base_url_params = [];
            if ($current_sort !== 'title_asc') $base_url_params['sort'] = $current_sort;
            if ($items_per_page !== 16) $base_url_params['limit'] = $items_per_page;
            
            $base_pag_url = "series_list.php?" . http_build_query($base_url_params);
            $separator = empty($base_url_params) ? '?' : '&';
            ?>
            <?php if ($current_page > 1): ?>
                <a href="<?php echo $base_pag_url . $separator; ?>page=<?php echo $current_page - 1; ?>">&laquo; Prec.</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo $base_pag_url . $separator; ?>page=<?php echo $i; ?>" class="<?php echo ($current_page == $i) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo $base_pag_url . $separator; ?>page=<?php echo $current_page + 1; ?>">Succ. &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <p>Nessuna serie di storie è stata ancora definita.</p>
        <?php if (isset($_SESSION['admin_user_id'])): ?>
            <p>Puoi <a href="<?php echo BASE_URL; ?>admin/series_manage.php?action=add">aggiungere una nuova serie</a> dall'area di amministrazione.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
$mysqli->close();
?>