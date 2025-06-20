<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';

// Controllo Accesso: solo gli admin con sessione unificata possono accedere
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Con il nuovo sistema unificato, basta controllare il ruolo.
$user_role_check = $_SESSION['user_role_frontend'] ?? 'user';
if ($user_role_check !== 'admin') {
    die("Accesso negato. Questa sezione è riservata agli amministratori.");
}

$page_title = "Gestione Utenti Frontend";
require_once ROOT_PATH . 'admin/includes/header_admin.php';

$message = '';
$message_type = '';

// --- INIZIO BLOCCO CORRETTO ---
// Aggiungo 'admin' all'array dei ruoli disponibili per la visualizzazione e la modifica.
$available_roles = [
    'user' => 'Utente Standard',
    'contributor' => 'Contributore',
    'admin' => 'Amministratore' // Aggiunto ruolo Admin
];
// --- FINE BLOCCO CORRETTO ---

// Azioni: activate, deactivate, delete
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id_action = (int)$_GET['user_id'];
    $action_type = $_GET['action'];

    if ($action_type === 'activate') {
        $stmt = $mysqli->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_action);
        if ($stmt->execute()) {
            $_SESSION['admin_action_message'] = "Utente attivato con successo.";
            $_SESSION['admin_action_message_type'] = 'success';
        } else {
            $_SESSION['admin_action_message'] = "Errore durante l'attivazione: " . $stmt->error;
            $_SESSION['admin_action_message_type'] = 'error';
        }
        $stmt->close();
    } elseif ($action_type === 'deactivate') {
        $stmt = $mysqli->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_action);
         if ($stmt->execute()) {
            $_SESSION['admin_action_message'] = "Utente disattivato con successo.";
            $_SESSION['admin_action_message_type'] = 'success';
        } else {
            $_SESSION['admin_action_message'] = "Errore durante la disattivazione: " . $stmt->error;
            $_SESSION['admin_action_message_type'] = 'error';
        }
        $stmt->close();
    } elseif ($action_type === 'delete') {
        $stmt = $mysqli->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_action);
        if ($stmt->execute()) {
            $_SESSION['admin_action_message'] = "Utente eliminato con successo.";
            $_SESSION['admin_action_message_type'] = 'success';
        } else {
            $_SESSION['admin_action_message'] = "Errore durante l'eliminazione: " . $stmt->error;
            $_SESSION['admin_action_message_type'] = 'error';
        }
        $stmt->close();
    }
    header('Location: users_manage.php');
    exit;
}

// Gestione note admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notes'])) {
    $user_id_notes = (int)$_POST['user_id_notes'];
    $admin_notes = trim($_POST['admin_notes']);

    $stmt_notes = $mysqli->prepare("UPDATE users SET notes_admin = ? WHERE user_id = ?");
    $stmt_notes->bind_param("si", $admin_notes, $user_id_notes);
    if ($stmt_notes->execute()) {
        $_SESSION['admin_action_message'] = "Note admin aggiornate.";
        $_SESSION['admin_action_message_type'] = 'success';
    } else {
        $_SESSION['admin_action_message'] = "Errore aggiornamento note: " . $stmt_notes->error;
        $_SESSION['admin_action_message_type'] = 'error';
    }
    $stmt_notes->close();
    header('Location: users_manage.php');
    exit;
}

// Gestione aggiornamento ruolo utente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id_role_update = (int)$_POST['user_id_role_update'];
    $new_role = trim($_POST['user_role']);

    if (array_key_exists($new_role, $available_roles)) {
        $stmt_role = $mysqli->prepare("UPDATE users SET user_role = ? WHERE user_id = ?");
        $stmt_role->bind_param("si", $new_role, $user_id_role_update);
        if ($stmt_role->execute()) {
            // Se l'utente che sto modificando è quello attualmente loggato, aggiorno la sessione
            if (isset($_SESSION['user_id_frontend']) && $_SESSION['user_id_frontend'] == $user_id_role_update) {
                $_SESSION['user_role_frontend'] = $new_role;
            }
            $_SESSION['admin_action_message'] = "Ruolo utente aggiornato con successo.";
            $_SESSION['admin_action_message_type'] = 'success';
        } else {
            $_SESSION['admin_action_message'] = "Errore durante l'aggiornamento del ruolo: " . $stmt_role->error;
            $_SESSION['admin_action_message_type'] = 'error';
        }
        $stmt_role->close();
    } else {
        $_SESSION['admin_action_message'] = "Ruolo selezionato non valido.";
        $_SESSION['admin_action_message_type'] = 'error';
    }
    header('Location: users_manage.php');
    exit;
}


if (isset($_SESSION['admin_action_message'])) {
    $message = $_SESSION['admin_action_message'];
    $message_type = $_SESSION['admin_action_message_type'];
    unset($_SESSION['admin_action_message']);
    unset($_SESSION['admin_action_message_type']);
}

$users_list = [];
$result = $mysqli->query("SELECT user_id, username, email, is_active, created_at, notes_admin, user_role FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users_list[] = $row;
    }
    $result->free();
}
?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($users_list)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Registrato il</th>
                    <th>Stato</th>
                    <th>Ruolo</th>
                    <th>Note Admin</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users_list as $user_item): ?>
                <tr>
                    <td><?php echo $user_item['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($user_item['username']); ?></td>
                    <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                    <td><?php echo date("d/m/Y H:i", strtotime($user_item['created_at'])); ?></td>
                    <td>
                        <?php if ($user_item['is_active'] == 1): ?>
                            <span style="color: green; font-weight: bold;">Attivo</span>
                        <?php else: ?>
                            <span style="color: orange; font-weight: bold;">In Attesa</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form action="users_manage.php" method="POST" style="display:inline-block; min-width:180px;">
                            <input type="hidden" name="user_id_role_update" value="<?php echo $user_item['user_id']; ?>">
                            <select name="user_role" class="form-control form-control-sm" onchange="this.form.submit()" title="Cambia ruolo utente">
                                <?php foreach ($available_roles as $role_key => $role_name): ?>
                                    <option value="<?php echo htmlspecialchars($role_key); ?>" <?php echo ($user_item['user_role'] === $role_key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_role" value="1">
                        </form>
                    </td>
                    <td>
                        <form action="users_manage.php" method="POST" style="display:inline-block; width:100%;">
                            <input type="hidden" name="user_id_notes" value="<?php echo $user_item['user_id']; ?>">
                            <textarea name="admin_notes" rows="1" class="form-control form-control-sm" placeholder="Aggiungi nota..."><?php echo htmlspecialchars($user_item['notes_admin'] ?? ''); ?></textarea>
                            <button type="submit" name="update_notes" class="btn btn-sm btn-secondary" style="font-size:0.75em; padding: 2px 5px; margin-top:3px;">Salva Nota</button>
                        </form>
                    </td>
                    <td style="white-space: nowrap;">
                        <?php if ($user_item['is_active'] == 0): ?>
                            <a href="?action=activate&user_id=<?php echo $user_item['user_id']; ?>" class="btn btn-sm btn-success">Attiva</a>
                        <?php else: ?>
                            <a href="?action=deactivate&user_id=<?php echo $user_item['user_id']; ?>" class="btn btn-sm btn-warning">Disattiva</a>
                        <?php endif; ?>
                        <a href="?action=delete&user_id=<?php echo $user_item['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo utente? L\'azione è irreversibile.');">Elimina</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nessun utente registrato al momento.</p>
    <?php endif; ?>
</div>

<?php
$mysqli->close();
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
?>