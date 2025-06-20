<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';

// NUOVA LOGICA DI CONTROLLO ACCESSO SPECIFICA PER ADMIN
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin_user_id'])) { // Solo i veri admin possono accedere qui
    // Se un contributore prova ad accedere, puoi reindirizzarlo alla dashboard admin
    // o a una pagina di "accesso negato".
    if (isset($_SESSION['user_id_frontend'])) { // È un utente frontend loggato
         $_SESSION['admin_action_message'] = "Accesso negato a questa sezione.";
         $_SESSION['admin_action_message_type'] = 'error';
         header('Location: ' . BASE_URL . 'admin/index.php'); // Reindirizza alla dashboard admin (che mostrerà solo i link permessi)
         exit;
    }
    header('Location: ' . BASE_URL . 'admin/login.php'); // Non loggato, vai al login admin
    exit;
}

$page_title = "Gestione Utenti Frontend"; // o "Gestione Campi Custom"
require_once ROOT_PATH . 'admin/includes/header_admin.php';

$message = '';
$message_type = ''; // 'success' o 'error'

// Tipi di campo supportati
$supported_field_types = ['text', 'textarea', 'number', 'date', 'checkbox'];

// --- GESTIONE AZIONI ---
$action = $_GET['action'] ?? 'list';
$field_key_to_edit = null;
$field_data = ['field_key' => '', 'field_label' => '', 'field_type' => 'text', 'entity_type' => 'comic']; // Dati di default

// --- LOGICA PER VISUALIZZARE IL FORM DI MODIFICA ---
// Nota: field_key è la Primary Key, quindi non lo rendiamo modificabile nel form di edit.
if ($action === 'edit' && isset($_GET['key'])) {
    $field_key_to_edit = trim($_GET['key']);
    $stmt = $mysqli->prepare("SELECT field_key, field_label, field_type, entity_type FROM custom_field_definitions WHERE field_key = ?");
    $stmt->bind_param("s", $field_key_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $field_data = $result->fetch_assoc();
    } else {
        $message = "Definizione campo non trovata.";
        $message_type = 'error';
        $action = 'list'; // Torna alla lista se la chiave non è valida
    }
    $stmt->close();
}


// --- CODICE PER ELABORARE L'AGGIUNTA (INSERT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_custom_field'])) {
    $field_key = trim($_POST['field_key']);
    $field_label = trim($_POST['field_label']);
    $field_type = trim($_POST['field_type']);
    $entity_type = 'comic'; // Fisso per ora

    if (empty($field_key) || !preg_match('/^[a-z0-9_]+$/', $field_key)) {
        $message = "La 'Chiave Campo' è obbligatoria e può contenere solo lettere minuscole, numeri e underscore (_).";
        $message_type = 'error';
    } elseif (empty($field_label)) {
        // PRIMA CORREZIONE APPLICATA QUI (era linea 56 circa)
        $message = "L'etichetta 'Etichetta Campo' è obbligatoria.";
        $message_type = 'error';
    } elseif (!in_array($field_type, $supported_field_types)) {
        $message = "Tipo di campo non valido.";
        $message_type = 'error';
    } else {
        $stmt = $mysqli->prepare("INSERT INTO custom_field_definitions (field_key, field_label, field_type, entity_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $field_key, $field_label, $field_type, $entity_type);
        if ($stmt->execute()) {
            $message = "Definizione campo personalizzato aggiunta con successo!";
            $message_type = 'success';
            $field_data = ['field_key' => '', 'field_label' => '', 'field_type' => 'text', 'entity_type' => 'comic']; // Reset form
            header('Location: ' . BASE_URL . 'admin/custom_fields_manage.php?action=list&message=' . urlencode($message) . '&message_type=success');
            exit;
        } else {
            if ($mysqli->errno === 1062) { // Errore per duplicato PRIMARY KEY (field_key)
                 $message = "Errore: Esiste già una definizione con questa 'Chiave Campo'.";
            } else {
                 $message = "Errore durante l'aggiunta della definizione: " . $stmt->error;
            }
            $message_type = 'error';
        }
        $stmt->close();
    }
    // Se c'è un errore, mantieni i dati inseriti nel form
    $field_data = ['field_key' => $field_key, 'field_label' => $field_label, 'field_type' => $field_type, 'entity_type' => $entity_type];
}

// --- CODICE PER ELABORARE LA MODIFICA (UPDATE) ---
// Stiamo permettendo la modifica di label e type, ma non della key.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_custom_field']) && isset($_POST['original_field_key'])) {
    $original_field_key = trim($_POST['original_field_key']); // Chiave originale non modificabile
    $field_label = trim($_POST['field_label']);
    $field_type = trim($_POST['field_type']);

    if (empty($field_label)) {
        // SECONDA CORREZIONE APPLICATA QUI (era linea 82 circa)
        $message = "L'etichetta 'Etichetta Campo' è obbligatoria.";
        $message_type = 'error';
    } elseif (!in_array($field_type, $supported_field_types)) {
        $message = "Tipo di campo non valido.";
        $message_type = 'error';
    } else {
        $stmt = $mysqli->prepare("UPDATE custom_field_definitions SET field_label = ?, field_type = ? WHERE field_key = ?");
        $stmt->bind_param("sss", $field_label, $field_type, $original_field_key);
        if ($stmt->execute()) {
            $message = "Definizione campo personalizzato modificata con successo!";
            $message_type = 'success';
            header('Location: ' . BASE_URL . 'admin/custom_fields_manage.php?action=list&message=' . urlencode($message) . '&message_type=success');
            exit;
        } else {
            $message = "Errore durante la modifica della definizione: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
    // Se c'è un errore, ricarica i dati del form con i valori POSTati
    $field_data = ['field_key' => $original_field_key, 'field_label' => $field_label, 'field_type' => $field_type, 'entity_type' => 'comic'];
    $field_key_to_edit = $original_field_key; // Mantiene la chiave per il form di modifica
}


// --- CODICE PER ELABORARE L'ELIMINAZIONE (DELETE) ---
if ($action === 'delete' && isset($_GET['key'])) {
    $field_key_to_delete = trim($_GET['key']);

    $stmt = $mysqli->prepare("DELETE FROM custom_field_definitions WHERE field_key = ?");
    $stmt->bind_param("s", $field_key_to_delete);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $message = "Definizione campo personalizzato eliminata con successo!";
            $message_type = 'success';
        } else {
            $message = "Nessuna definizione trovata con questa chiave per l'eliminazione.";
            $message_type = 'error';
        }
    } else {
        $message = "Errore durante l'eliminazione della definizione: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
    header('Location: ' . BASE_URL . 'admin/custom_fields_manage.php?action=list&message=' . urlencode($message) . '&message_type=' . $message_type);
    exit;
}


// Recupera i messaggi passati via GET (dopo redirect)
if (isset($_GET['message'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
    $message_type = isset($_GET['message_type']) ? htmlspecialchars($_GET['message_type']) : 'info';
}

?>

<div class="container admin-container">
    <h2><?php echo $page_title; ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <p><a href="?action=add" class="btn btn-primary">Aggiungi Nuova Definizione Campo</a></p>

        <h3>Elenco Definizioni Campi Personalizzati</h3>
        <?php
        $result = $mysqli->query("SELECT field_key, field_label, field_type, entity_type FROM custom_field_definitions ORDER BY field_label ASC");
        if ($result && $result->num_rows > 0):
        ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Chiave Campo (Key)</th>
                        <th>Etichetta (Label)</th>
                        <th>Tipo Campo</th>
                        <th>Entità</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['field_key']); ?></td>
                        <td><?php echo htmlspecialchars($row['field_label']); ?></td>
                        <td><?php echo htmlspecialchars($row['field_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['entity_type']); ?></td>
                        <td>
                            <a href="?action=edit&key=<?php echo urlencode($row['field_key']); ?>" class="btn btn-sm btn-warning">Modifica</a>
                            <a href="?action=delete&key=<?php echo urlencode($row['field_key']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa definizione? L\'eliminazione non rimuoverà i dati già inseriti nei fumetti che usano questa chiave.');">Elimina</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nessuna definizione di campo personalizzato trovata. <a href="?action=add">Aggiungine una!</a></p>
        <?php endif; ?>
        <?php if($result) $result->free(); ?>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <h3><?php echo ($action === 'add') ? 'Aggiungi Nuova Definizione' : 'Modifica Definizione'; ?></h3>
        <form action="custom_fields_manage.php" method="POST">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="original_field_key" value="<?php echo htmlspecialchars($field_data['field_key']); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="field_key">Chiave Campo (Key):</label>
                <input type="text" id="field_key" name="field_key" class="form-control"
                       value="<?php echo htmlspecialchars($field_data['field_key']); ?>"
                       <?php echo ($action === 'edit') ? 'readonly' : 'required'; ?>
                       pattern="[a-z0-9_]+"
                       title="Solo lettere minuscole, numeri e underscore (_). Es: tipo_gadget">
                <?php if ($action === 'add'): ?>
                    <small>Solo lettere minuscole, numeri e underscore (_). Es: <code>tipo_gadget</code>, <code>note_edizione</code>. Non modificabile dopo la creazione.</small>
                <?php else: ?>
                    <small>La Chiave Campo non è modificabile.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="field_label">Etichetta Campo (Label):</label>
                <input type="text" id="field_label" name="field_label" class="form-control" value="<?php echo htmlspecialchars($field_data['field_label']); ?>" required>
                <small>L'etichetta che verrà visualizzata nel form di inserimento fumetto. Es: "Tipo di Gadget", "Note sull'Edizione".</small>
            </div>

            <div class="form-group">
                <label for="field_type">Tipo di Campo:</label>
                <select id="field_type" name="field_type" class="form-control" required>
                    <?php foreach ($supported_field_types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo ($field_data['field_type'] === $type) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type); // Rende la prima lettera maiuscola per la visualizzazione ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Determina come il campo apparirà nel form di inserimento fumetto.</small>
            </div>

            <div class="form-group">
                <?php if ($action === 'add'): ?>
                    <button type="submit" name="add_custom_field" class="btn btn-success">Aggiungi Definizione</button>
                <?php else: ?>
                    <button type="submit" name="edit_custom_field" class="btn btn-success">Salva Modifiche</button>
                <?php endif; ?>
                <a href="?action=list" class="btn btn-secondary">Annulla</a>
            </div>
        </form>
    <?php endif; ?>

</div><?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
$mysqli->close();
?>