<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php'; // Per format_date_italian, se serve

// CONTROLLO ACCESSO - SOLO VERI ADMIN
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin_user_id'])) {
    $_SESSION['admin_action_message'] = "Accesso negato. Devi essere un amministratore per eseguire questa azione.";
    $_SESSION['admin_action_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}
$current_admin_id = $_SESSION['admin_user_id'];
// FINE CONTROLLO ACCESSO

$page_title = "Gestione Segnalazioni Utenti";
require_once ROOT_PATH . 'admin/includes/header_admin.php';

$message_from_action = $_SESSION['admin_action_message'] ?? null;
$message_type_from_action = $_SESSION['admin_action_message_type'] ?? null;
if ($message_from_action) {
    unset($_SESSION['admin_action_message']);
    unset($_SESSION['admin_action_message_type']);
}

// Gestione delle azioni (cambio stato, eliminazione, aggiunta note)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id_action'])) {
    $report_id_to_act_on = (int)$_POST['report_id_action'];
    $action_to_take = $_POST['action_type'] ?? null;
    $admin_notes_update = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : null;

    if ($report_id_to_act_on > 0) {
        if ($action_to_take === 'update_status' && isset($_POST['new_status'])) {
            $new_status = $_POST['new_status'];
            $allowed_statuses = ['new', 'viewed', 'in_progress', 'resolved', 'rejected'];
            if (in_array($new_status, $allowed_statuses)) {
                $stmt = $mysqli->prepare("UPDATE error_reports SET status = ?, admin_id_reviewer = ?, reviewed_at = NOW(), admin_notes = COALESCE(?, admin_notes) WHERE report_id = ?");
                $stmt->bind_param("sisi", $new_status, $current_admin_id, $admin_notes_update, $report_id_to_act_on);
                if ($stmt->execute()) {
                    $_SESSION['admin_action_message'] = "Stato della segnalazione #{$report_id_to_act_on} aggiornato a '{$new_status}'.";
                    $_SESSION['admin_action_message_type'] = 'success';
                } else {
                    $_SESSION['admin_action_message'] = "Errore aggiornamento stato: " . $stmt->error;
                    $_SESSION['admin_action_message_type'] = 'error';
                }
                $stmt->close();
            } else {
                $_SESSION['admin_action_message'] = "Stato non valido.";
                $_SESSION['admin_action_message_type'] = 'error';
            }
        } elseif ($action_to_take === 'delete_report') {
            $stmt = $mysqli->prepare("DELETE FROM error_reports WHERE report_id = ?");
            $stmt->bind_param("i", $report_id_to_act_on);
            if ($stmt->execute()) {
                $_SESSION['admin_action_message'] = "Segnalazione #{$report_id_to_act_on} eliminata.";
                $_SESSION['admin_action_message_type'] = 'success';
            } else {
                $_SESSION['admin_action_message'] = "Errore eliminazione: " . $stmt->error;
                $_SESSION['admin_action_message_type'] = 'error';
            }
            $stmt->close();
        } elseif ($action_to_take === 'update_admin_notes' && $admin_notes_update !== null) {
             $stmt = $mysqli->prepare("UPDATE error_reports SET admin_notes = ?, admin_id_reviewer = ?, reviewed_at = NOW() WHERE report_id = ?");
             $stmt->bind_param("sii", $admin_notes_update, $current_admin_id, $report_id_to_act_on);
             if ($stmt->execute()) {
                $_SESSION['admin_action_message'] = "Note admin per segnalazione #{$report_id_to_act_on} aggiornate.";
                $_SESSION['admin_action_message_type'] = 'success';
            } else {
                $_SESSION['admin_action_message'] = "Errore aggiornamento note admin: " . $stmt->error;
                $_SESSION['admin_action_message_type'] = 'error';
            }
            $stmt->close();
        }
    }
    header('Location: ' . BASE_URL . 'admin/error_reports_manage.php?' . http_build_query(['filter_status' => $_POST['current_filter_status'] ?? 'all']));
    exit;
}


// Filtri e ordinamento
$filter_status = $_GET['filter_status'] ?? 'new'; // Default a 'new'
$sort_options_reports = [
    'report_date_desc' => 'Data Segnalazione (Recenti)',
    'report_date_asc' => 'Data Segnalazione (Meno Recenti)',
    'comic_issue_asc' => 'Numero Albo (Crescente)',
];
$current_sort_reports = $_GET['sort'] ?? 'report_date_desc';
if (!array_key_exists($current_sort_reports, $sort_options_reports)) {
    $current_sort_reports = 'report_date_desc';
}

$sql_reports = "
    SELECT er.*, c.issue_number as comic_issue_number, s.title as story_title
    FROM error_reports er
    JOIN comics c ON er.comic_id = c.comic_id
    LEFT JOIN stories s ON er.story_id = s.story_id
";
$where_clauses_reports = [];
$params_reports = [];
$types_reports = "";

if ($filter_status !== 'all') {
    $where_clauses_reports[] = "er.status = ?";
    $params_reports[] = $filter_status;
    $types_reports .= "s";
}

if (!empty($where_clauses_reports)) {
    $sql_reports .= " WHERE " . implode(" AND ", $where_clauses_reports);
}

switch ($current_sort_reports) {
    case 'report_date_asc': $sql_reports .= " ORDER BY er.report_date ASC"; break;
    case 'comic_issue_asc': $sql_reports .= " ORDER BY CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ASC, er.report_date DESC"; break;
    case 'report_date_desc':
    default: $sql_reports .= " ORDER BY er.report_date DESC"; break;
}

$stmt_get_reports = $mysqli->prepare($sql_reports);
if ($stmt_get_reports) {
    if (!empty($types_reports)) {
        $stmt_get_reports->bind_param($types_reports, ...$params_reports);
    }
    $stmt_get_reports->execute();
    $result_reports = $stmt_get_reports->get_result();
} else {
    $message_from_action = "Errore preparazione query per recuperare le segnalazioni: " . $mysqli->error;
    $message_type_from_action = 'error';
    $result_reports = false;
}

$all_statuses_for_filter = ['all', 'new', 'viewed', 'in_progress', 'resolved', 'rejected'];
$statuses_for_dropdown = ['new' => 'Nuova', 'viewed' => 'Vista', 'in_progress' => 'In Lavorazione', 'resolved' => 'Risolta', 'rejected' => 'Rifiutata'];

?>
<style>
    .filter-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px; padding: 12px 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; }
    .filter-bar span { font-weight: 600; color: #495057; margin-right: 5px; }
    .filter-bar a, .filter-bar select, .filter-bar button { margin-right: 5px; }
    .table .status-badge { display: inline-block; padding: .25em .6em; font-size: 0.8em; font-weight: 600; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; color: #fff; text-transform: capitalize; }
    .table .status-new .status-badge { background-color: #007bff; }
    .table .status-viewed .status-badge { background-color: #ffc107; color: #212529 !important; }
    .table .status-in_progress .status-badge { background-color: #17a2b8; }
    .table .status-resolved .status-badge { background-color: #28a745; }
    .table .status-rejected .status-badge { background-color: #dc3545; }
    .admin-notes-textarea { width: 100%; min-height: 60px; font-size: 0.9em; margin-bottom: 5px; }
    .actions-cell form { margin-bottom: 5px; }
</style>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($message_from_action): ?>
        <div class="message <?php echo htmlspecialchars($message_type_from_action); ?>">
            <?php echo htmlspecialchars($message_from_action); ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <form method="GET" action="error_reports_manage.php" style="display:inline-flex; align-items:center; gap:10px;">
            <span>Filtra per stato:</span>
            <select name="filter_status" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php foreach ($all_statuses_for_filter as $status_val): ?>
                <option value="<?php echo $status_val; ?>" <?php echo ($filter_status === $status_val) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucfirst($statuses_for_dropdown[$status_val] ?? $status_val)); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <span>Ordina per:</span>
            <select name="sort" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php foreach ($sort_options_reports as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo ($current_sort_reports === $key) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-secondary">Applica</button></noscript>
        </form>
    </div>

    <?php if ($result_reports && $result_reports->num_rows > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Albo Segnalato</th>
                    <th>Storia Specifica</th>
                    <th>Testo Segnalazione</th>
                    <th>Email Visitatore</th>
                    <th>Data</th>
                    <th>Stato</th>
                    <th>Note Admin</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($report = $result_reports->fetch_assoc()): ?>
                    <tr class="status-<?php echo htmlspecialchars($report['status']); ?>">
                        <td><?php echo $report['report_id']; ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>comic_detail.php?id=<?php echo $report['comic_id']; ?>" target="_blank">
                                Topolino #<?php echo htmlspecialchars($report['comic_issue_number']); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($report['story_id'] && $report['story_title']): ?>
                                <a href="<?php echo BASE_URL; ?>comic_detail.php?id=<?php echo $report['comic_id']; ?>#story-item-<?php echo $report['story_id']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($report['story_title']); ?> (ID: <?php echo $report['story_id']; ?>)
                                </a>
                            <?php else: echo 'N/A'; endif; ?>
                        </td>
                        <td style="max-width: 300px; overflow-wrap: break-word;"><?php echo nl2br(htmlspecialchars($report['report_text'])); ?></td>
                        <td><?php echo htmlspecialchars($report['reporter_email'] ?: '-'); ?></td>
                        <td><?php echo format_date_italian($report['report_date'], "d MMMM yyyy HH:mm"); ?></td>
                        <td><span class="status-badge"><?php echo htmlspecialchars($statuses_for_dropdown[$report['status']] ?? $report['status']); ?></span></td>
                        <td>
                             <form action="error_reports_manage.php" method="POST">
                                <input type="hidden" name="report_id_action" value="<?php echo $report['report_id']; ?>">
                                <input type="hidden" name="action_type" value="update_admin_notes">
                                <input type="hidden" name="current_filter_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                <textarea name="admin_notes" class="admin-notes-textarea" placeholder="Aggiungi o modifica nota..."><?php echo htmlspecialchars($report['admin_notes'] ?? ''); ?></textarea>
                                <button type="submit" class="btn btn-xs btn-secondary">Salva Nota</button>
                            </form>
                             <?php if ($report['reviewed_at'] && $report['admin_id_reviewer']):
                                $stmt_admin_name = $mysqli->prepare("SELECT username FROM admin_users WHERE user_id = ?");
                                $stmt_admin_name->bind_param("i", $report['admin_id_reviewer']);
                                $stmt_admin_name->execute();
                                $res_admin_name = $stmt_admin_name->get_result();
                                $reviewer_name = ($admin_row = $res_admin_name->fetch_assoc()) ? $admin_row['username'] : 'ID: '.$report['admin_id_reviewer'];
                                $stmt_admin_name->close();
                             ?>
                                <small style="display:block; font-size:0.8em; color:#666;">Ultima rev: <?php echo format_date_italian($report['reviewed_at'], "d M H:i"); ?> da <?php echo htmlspecialchars($reviewer_name); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell" style="white-space: nowrap;">
                            <form action="error_reports_manage.php" method="POST" style="display:inline-block;">
                                <input type="hidden" name="report_id_action" value="<?php echo $report['report_id']; ?>">
                                <input type="hidden" name="action_type" value="update_status">
                                <input type="hidden" name="current_filter_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                <select name="new_status" class="form-control form-control-sm" onchange="this.form.submit()" title="Cambia stato segnalazione">
                                    <?php foreach ($statuses_for_dropdown as $status_key => $status_label): ?>
                                        <option value="<?php echo $status_key; ?>" <?php echo ($report['status'] === $status_key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <noscript><button type="submit" class="btn btn-sm btn-primary">Aggiorna Stato</button></noscript>
                            </form>
                            <form action="error_reports_manage.php" method="POST" style="display:inline-block; margin-top:5px;">
                                <input type="hidden" name="report_id_action" value="<?php echo $report['report_id']; ?>">
                                <input type="hidden" name="action_type" value="delete_report">
                                <input type="hidden" name="current_filter_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa segnalazione?');">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nessuna segnalazione trovata per lo stato "<?php echo htmlspecialchars($statuses_for_dropdown[$filter_status] ?? $filter_status); ?>".</p>
    <?php endif; ?>
    <?php if($stmt_get_reports) $stmt_get_reports->close(); ?>
</div>

<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
if (isset($mysqli) && $mysqli instanceof mysqli) { $mysqli->close(); }
?>