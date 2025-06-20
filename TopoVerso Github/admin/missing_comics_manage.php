<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';

// CONTROLLO ACCESSO
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$is_true_admin = isset($_SESSION['admin_user_id']);
$is_contributor = (isset($_SESSION['user_id_frontend']) && isset($_SESSION['user_role_frontend']) && $_SESSION['user_role_frontend'] === 'contributor');

if (!$is_true_admin && !$is_contributor) {
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}
$current_script_name = basename($_SERVER['PHP_SELF']);
$pages_for_contributors_check = [
    'comics_manage.php', 'stories_manage.php', 'series_manage.php',
    'persons_manage.php', 'characters_manage.php', 'missing_comics_manage.php', 'index.php'
];
if ($is_contributor && !$is_true_admin && !in_array($current_script_name, $pages_for_contributors_check)) {
    $_SESSION['admin_action_message'] = "Accesso negato a questa sezione.";
    $_SESSION['admin_action_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
}
// FINE CONTROLLO ACCESSO

$page_title = "Albi Segnalati dagli Utenti (Mancanti)";
require_once ROOT_PATH . 'admin/includes/header_admin.php';

$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;

if ($message) {
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Opzioni di ordinamento
$sort_options_admin = [
    'request_count_desc' => 'Più Richiesti',
    'request_count_asc' => 'Meno Richiesti',
    'issue_number_asc' => 'Numero Albo (Crescente)',
    'issue_number_desc' => 'Numero Albo (Decrescente)',
    'latest_request_desc' => 'Ultima Richiesta (Recenti)',
    'oldest_request_asc' => 'Ultima Richiesta (Meno Recenti)',
];
$current_sort_admin = $_GET['sort'] ?? 'request_count_desc';
if (!array_key_exists($current_sort_admin, $sort_options_admin)) {
    $current_sort_admin = 'request_count_desc';
}

$order_by_sql_admin = "ORDER BY request_count DESC, issue_number_placeholder ASC"; // Default
switch ($current_sort_admin) {
    case 'request_count_asc':
        $order_by_sql_admin = "ORDER BY request_count ASC, issue_number_placeholder ASC";
        break;
    case 'issue_number_asc':
        $order_by_sql_admin = "ORDER BY LENGTH(ucp.issue_number_placeholder) ASC, ucp.issue_number_placeholder ASC";
        break;
    case 'issue_number_desc':
        $order_by_sql_admin = "ORDER BY LENGTH(ucp.issue_number_placeholder) DESC, ucp.issue_number_placeholder DESC";
        break;
    case 'latest_request_desc':
        $order_by_sql_admin = "ORDER BY MAX(ucp.added_at) DESC";
        break;
    case 'oldest_request_asc':
        $order_by_sql_admin = "ORDER BY MAX(ucp.added_at) ASC";
        break;
    case 'request_count_desc':
    default:
        $order_by_sql_admin = "ORDER BY request_count DESC, issue_number_placeholder ASC";
        break;
}


// Recupera i placeholder raggruppati per numero, contando le richieste e l'ultima data di aggiunta
$missing_comics_data = [];
$sql = "
    SELECT 
        ucp.issue_number_placeholder, 
        COUNT(DISTINCT ucp.user_id) as request_count,
        MAX(ucp.added_at) as last_requested_at,
        GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ') as requesting_users
    FROM user_collection_placeholders ucp
    JOIN users u ON ucp.user_id = u.user_id
    WHERE ucp.status = 'pending'
    GROUP BY ucp.issue_number_placeholder
    $order_by_sql_admin
";

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $missing_comics_data[] = $row;
    }
    $result->free();
}

?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?> <?php if ($is_contributor && !$is_true_admin) echo "(Modalità Contributore)"; ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="admin-table-controls" style="margin-bottom: 20px; padding: 10px; background-color: #f8f9fa; border-radius: 4px; border: 1px solid #e9ecef;">
        <form method="GET" action="missing_comics_manage.php" id="sortFormAdminMissing">
            <label for="sort_admin_missing">Ordina per:</label>
            <select name="sort" id="sort_admin_missing" class="form-control" style="display: inline-block; width: auto; vertical-align: middle;" onchange="this.form.submit()">
                <?php foreach ($sort_options_admin as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo ($current_sort_admin === $key) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>


    <?php if (!empty($missing_comics_data)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Numero Albo Segnalato</th>
                    <th>Numero Richieste</th>
                    <th>Utenti Richiedenti</th>
                    <th>Ultima Richiesta</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($missing_comics_data as $item): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($item['issue_number_placeholder']); ?></strong></td>
                    <td><?php echo $item['request_count']; ?></td>
                    <td style="font-size: 0.85em; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($item['requesting_users']); ?>">
                        <?php echo htmlspecialchars($item['requesting_users']); ?>
                    </td>
                    <td><?php echo date("d/m/Y H:i", strtotime($item['last_requested_at'])); ?></td>
                    <td style="white-space: nowrap;">
                        <a href="<?php echo BASE_URL; ?>admin/comics_manage.php?action=add&prefill_issue_number=<?php echo urlencode($item['issue_number_placeholder']); ?>" class="btn btn-sm btn-success" title="Aggiungi questo numero al catalogo">
                            <?php echo ($is_contributor && !$is_true_admin) ? 'Proponi Aggiunta Catalogo' : 'Aggiungi al Catalogo'; ?>
                        </a>
                        </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nessun albo mancante segnalato dagli utenti al momento.</p>
    <?php endif; ?>
</div>

<?php
$mysqli->close();
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
?>