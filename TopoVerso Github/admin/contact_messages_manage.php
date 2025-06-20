<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// CONTROLLO ACCESSO - SOLO VERI ADMIN
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_user_id'])) {
    $_SESSION['admin_action_message'] = "Accesso negato a questa sezione.";
    $_SESSION['admin_action_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}
$current_admin_id = $_SESSION['admin_user_id'];
// FINE CONTROLLO ACCESSO

$page_title = "Gestione Messaggi di Contatto";

$message_feedback = $_SESSION['admin_action_message'] ?? null;
$message_feedback_type = $_SESSION['admin_action_message_type'] ?? null;
if ($message_feedback) {
    unset($_SESSION['admin_action_message']);
    unset($_SESSION['admin_action_message_type']);
}

// Gestione azioni (segna come letto/non letto, elimina, aggiungi nota admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id_action'])) {
    $message_id_to_act_on = (int)$_POST['message_id_action'];
    $action_to_take = $_POST['action_type'] ?? null;
    $admin_notes_for_message = isset($_POST['admin_notes_message']) ? trim($_POST['admin_notes_message']) : null;

    if ($message_id_to_act_on > 0) {
        if ($action_to_take === 'toggle_read') {
            $stmt = $mysqli->prepare("UPDATE contact_messages SET is_read = NOT is_read WHERE message_id = ?");
            $stmt->bind_param("i", $message_id_to_act_on);
            if ($stmt->execute()) {
                $_SESSION['admin_action_message'] = "Stato lettura messaggio #{$message_id_to_act_on} aggiornato.";
                $_SESSION['admin_action_message_type'] = 'success';
            } else { /* ... gestione errore ... */ }
            $stmt->close();
        } elseif ($action_to_take === 'delete_message') {
            $stmt = $mysqli->prepare("DELETE FROM contact_messages WHERE message_id = ?");
            $stmt->bind_param("i", $message_id_to_act_on);
            if ($stmt->execute()) {
                $_SESSION['admin_action_message'] = "Messaggio #{$message_id_to_act_on} eliminato.";
                $_SESSION['admin_action_message_type'] = 'success';
            } else { /* ... gestione errore ... */ }
            $stmt->close();
        } elseif ($action_to_take === 'update_admin_notes_msg' && $admin_notes_for_message !== null) {
             $stmt = $mysqli->prepare("UPDATE contact_messages SET admin_notes = ? WHERE message_id = ?");
             $stmt->bind_param("si", $admin_notes_for_message, $message_id_to_act_on);
             if ($stmt->execute()) {
                $_SESSION['admin_action_message'] = "Note admin per messaggio #{$message_id_to_act_on} aggiornate.";
                $_SESSION['admin_action_message_type'] = 'success';
            } else { /* ... gestione errore ... */ }
            $stmt->close();
        }
    }
    header('Location: ' . BASE_URL . 'admin/contact_messages_manage.php');
    exit;
}


// Recupera i messaggi
$filter_read_status = $_GET['filter_read'] ?? 'all'; // 'all', 'read', 'unread'
$contact_messages_list = [];
$sql_messages = "SELECT * FROM contact_messages";
$where_msg_clauses = [];
if ($filter_read_status === 'read') {
    $where_msg_clauses[] = "is_read = 1";
} elseif ($filter_read_status === 'unread') {
    $where_msg_clauses[] = "is_read = 0";
}
if(!empty($where_msg_clauses)){
    $sql_messages .= " WHERE " . implode(" AND ", $where_msg_clauses);
}
$sql_messages .= " ORDER BY submitted_at DESC";

$result_messages = $mysqli->query($sql_messages);
if ($result_messages) {
    while ($row = $result_messages->fetch_assoc()) {
        $contact_messages_list[] = $row;
    }
    $result_messages->free();
}

require_once ROOT_PATH . 'admin/includes/header_admin.php';
?>
<style>
    .filter-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px; padding: 10px 0;}
    .filter-bar span { font-weight: 500; margin-right: 5px; }
    .filter-bar a { text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; color: #007bff; background-color: #f0f8ff; border: 1px solid #add8e6; }
    .filter-bar a.active, .filter-bar a:hover { background-color: #007bff; color: white; border-color: #007bff;}
    .table .message-text { max-height: 100px; overflow-y: auto; display: block; white-space: pre-wrap; word-wrap: break-word;}
    .table .admin-notes-textarea-msg { width: 100%; min-height: 50px; font-size: 0.9em; margin-bottom: 3px; }
</style>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($message_feedback): ?>
        <div class="message <?php echo htmlspecialchars($message_feedback_type); ?>">
            <?php echo htmlspecialchars($message_feedback); ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <span>Filtra per stato lettura:</span>
        <a href="?filter_read=all" class="<?php echo ($filter_read_status == 'all') ? 'active' : ''; ?>">Tutti</a>
        <a href="?filter_read=unread" class="<?php echo ($filter_read_status == 'unread') ? 'active' : ''; ?>">Non Letti</a>
        <a href="?filter_read=read" class="<?php echo ($filter_read_status == 'read') ? 'active' : ''; ?>">Letti</a>
    </div>

    <?php if (!empty($contact_messages_list)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:5%">ID</th>
                    <th style="width:15%">Nome</th>
                    <th style="width:15%">Email</th>
                    <th style="width:15%">Oggetto</th>
                    <th style="width:25%">Messaggio</th>
                    <th style="width:10%">Data Invio</th>
                    <th style="width:15%">Note Admin</th>
                    <th style="width:10%">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contact_messages_list as $msg): ?>
                    <tr style="<?php echo $msg['is_read'] ? '' : 'font-weight:bold; background-color: #fffbea;';?>">
                        <td><?php echo $msg['message_id']; ?></td>
                        <td><?php echo htmlspecialchars($msg['contact_name']); ?></td>
                        <td><a href="mailto:<?php echo htmlspecialchars($msg['contact_email']); ?>"><?php echo htmlspecialchars($msg['contact_email']); ?></a></td>
                        <td><?php echo htmlspecialchars($msg['contact_subject']); ?></td>
                        <td><div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div></td>
                        <td><?php echo format_date_italian($msg['submitted_at'], 'd/m/Y H:i'); ?></td>
                        <td>
                             <form action="contact_messages_manage.php" method="POST">
                                <input type="hidden" name="message_id_action" value="<?php echo $msg['message_id']; ?>">
                                <input type="hidden" name="action_type" value="update_admin_notes_msg">
                                <textarea name="admin_notes_message" class="admin-notes-textarea-msg" placeholder="Aggiungi nota..."><?php echo htmlspecialchars($msg['admin_notes'] ?? ''); ?></textarea>
                                <button type="submit" class="btn btn-xs btn-secondary">Salva Nota</button>
                            </form>
                        </td>
                        <td style="white-space: nowrap;">
                            <form action="contact_messages_manage.php" method="POST" style="display:inline-block; margin-bottom:5px;">
                                <input type="hidden" name="message_id_action" value="<?php echo $msg['message_id']; ?>">
                                <input type="hidden" name="action_type" value="toggle_read">
                                <button type="submit" class="btn btn-sm <?php echo $msg['is_read'] ? 'btn-warning' : 'btn-success'; ?>">
                                    <?php echo $msg['is_read'] ? 'Segna Non Letto' : 'Segna Letto'; ?>
                                </button>
                            </form>
                            <form action="contact_messages_manage.php" method="POST" style="display:inline-block;">
                                <input type="hidden" name="message_id_action" value="<?php echo $msg['message_id']; ?>">
                                <input type="hidden" name="action_type" value="delete_message">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo messaggio?');">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nessun messaggio di contatto trovato <?php if($filter_read_status !== 'all') echo "con stato '".htmlspecialchars($filter_read_status)."'"; ?>.</p>
    <?php endif; ?>
</div>

<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
?>