<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php'; // Necessario per format_date_italian

// CONTROLLO ACCESSO - SOLO VERI ADMIN
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_user_id'])) {
    $_SESSION['admin_action_message'] = "Accesso negato a questa sezione.";
    $_SESSION['admin_action_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}
// FINE CONTROLLO ACCESSO

$page_title = "Gestione Eventi Storici Topolino";

$message = $_SESSION['admin_action_message'] ?? null;
$message_type = $_SESSION['admin_action_message_type'] ?? 'info';

if ($message) {
    unset($_SESSION['admin_action_message']);
    unset($_SESSION['admin_action_message_type']);
}

// Funzione helper per gestire l'upload dell'immagine dell'evento
function handle_event_image_upload_hs($file_input_name, $current_image_path, $delete_flag_name) {
    global $message, $message_type; // Variabili per i messaggi globali della pagina
    $image_path_to_return = $current_image_path;
    $base_upload_path = UPLOADS_PATH;
    $target_subdir = 'historical_events';
    $final_upload_dir_relative = rtrim($target_subdir, '/') . '/';

    $upload_dir_absolute = $base_upload_path . $final_upload_dir_relative;
    if (!is_dir($upload_dir_absolute)) {
        if (!mkdir($upload_dir_absolute, 0775, true)) {
            // Usiamo $message e $message_type globali perché questa funzione è chiamata nel contesto della pagina
            $message .= " Errore critico: Impossibile creare la cartella di upload per gli eventi storici: " . htmlspecialchars($upload_dir_absolute) . ".";
            $message_type = 'error';
            return $current_image_path;
        }
    }

    if (isset($_POST[$delete_flag_name]) && $_POST[$delete_flag_name] == '1' && $current_image_path) {
        if (file_exists($base_upload_path . $current_image_path)) {
            @unlink($base_upload_path . $current_image_path);
        }
        $image_path_to_return = null;
        if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] == UPLOAD_ERR_NO_FILE) {
            return $image_path_to_return;
        }
    }

    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $original_filename_event = $_FILES[$file_input_name]['name'];
        $file_size = $_FILES[$file_input_name]['size'];
        $file_extension_event = strtolower(pathinfo($original_filename_event, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_file_size = 5 * 1024 * 1024; // Max 2MB

        if (!in_array($file_extension_event, $allowed_extensions)) {
            $message .= " Formato file non consentito per l'immagine dell'evento. Ammessi: JPG, JPEG, PNG, GIF, WEBP.";
            $message_type = 'error';
            return $image_path_to_return; // Ritorna il path corrente (che potrebbe essere stato impostato a null sopra)
        } elseif ($file_size > $max_file_size) {
            $message .= " Il file immagine è troppo grande (max 2MB).";
            $message_type = 'error';
            return $image_path_to_return;
        }

        $temp_filename_no_ext_event = 'event_' . uniqid() . '_' . time();
        $temp_uploaded_file_absolute_event = $upload_dir_absolute . $temp_filename_no_ext_event . '.' . $file_extension_event;
        
        if (move_uploaded_file($file_tmp_name, $temp_uploaded_file_absolute_event)) {
            $jpeg_quality_event = 75;
            $png_compression_event = 9;
            $convertToWebp_event = true;

            $processed_image_absolute_path_event = compress_and_optimize_image(
                $temp_uploaded_file_absolute_event,
                $temp_uploaded_file_absolute_event,
                $file_extension_event,
                $jpeg_quality_event,
                $png_compression_event,
                $convertToWebp_event
            );

            if ($processed_image_absolute_path_event) {
                $new_final_filename_relative_event = $final_upload_dir_relative . basename($processed_image_absolute_path_event);
                // Cancella l'immagine precedente se era diversa dalla nuova
                if ($current_image_path && $current_image_path !== $new_final_filename_relative_event && file_exists($base_upload_path . $current_image_path)) {
                    @unlink($base_upload_path . $current_image_path);
                }
                $image_path_to_return = $new_final_filename_relative_event;
            } else {
                $message .= " Fallimento compressione per ".htmlspecialchars($file_input_name).". Verrà usata l'immagine originale caricata.";
                $image_path_to_return = $final_upload_dir_relative . basename($temp_uploaded_file_absolute_event);
                error_log("Compressione fallita per $file_input_name (evento storico), usando $image_path_to_return");
            }
        } else {
            $message .= " Errore durante il caricamento del file immagine per l'evento.";
            $message_type = 'error';
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_OK) {
        $message .= " Errore nell'upload dell'immagine dell'evento (Codice: " . $_FILES[$file_input_name]['error'] . ").";
        $message_type = 'error';
    }
    return $image_path_to_return;
}


$event_data_form = [
    'event_title' => '',
    'event_description' => '',
    'event_date_start' => '',
    'event_date_end' => null,
    'category' => '',
    'related_issue_start' => '',
    'related_issue_end' => '',
    'event_image_path' => null,
    'related_story_id' => null
];
$editing_event_id = null;
$stories_for_related_issue_select = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_historical_event'])) {
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date_start = trim($_POST['event_date_start']);
    $event_date_end = !empty(trim($_POST['event_date_end'])) ? trim($_POST['event_date_end']) : null;
    $category = !empty(trim($_POST['category'])) ? trim($_POST['category']) : null;
    $related_issue_start_post = !empty(trim($_POST['related_issue_start'])) ? trim($_POST['related_issue_start']) : null;
    $related_issue_end = !empty(trim($_POST['related_issue_end'])) ? trim($_POST['related_issue_end']) : null;
    $event_id_to_update = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $current_event_image_from_form = $_POST['current_event_image'] ?? null;
    $related_story_id_post = !empty(trim($_POST['related_story_id'])) ? (int)trim($_POST['related_story_id']) : null;

    $event_image_path_to_save = handle_event_image_upload_hs('event_image', $current_event_image_from_form, 'delete_event_image');

    $errors_event = [];
    if (empty($event_title)) $errors_event[] = "Il titolo dell'evento è obbligatorio.";
    if (empty($event_description)) $errors_event[] = "La descrizione dell'evento è obbligatoria.";
    if (empty($event_date_start)) {
        $errors_event[] = "La data di inizio evento è obbligatoria.";
    } else if (!DateTime::createFromFormat('Y-m-d', $event_date_start)) {
        $errors_event[] = "Formato data inizio evento non valido (usare AAAA-MM-GG).";
    }
    if ($event_date_end !== null && !DateTime::createFromFormat('Y-m-d', $event_date_end)) {
        $errors_event[] = "Formato data fine evento non valido (usare AAAA-MM-GG).";
    }
    if ($event_date_end !== null && !empty($event_date_start) && $event_date_start > $event_date_end) {
        $errors_event[] = "La data di fine non può precedere la data di inizio.";
    }
    if ($related_story_id_post && empty($related_issue_start_post)) {
        $errors_event[] = "Per associare una storia, è necessario specificare 'N. Albo Inizio Riferimento'.";
    }

    // Integrazione messaggi da funzione upload
    $temp_message_from_upload = $message; // Salva messaggio da upload
    $temp_message_type_from_upload = $message_type;
    $message = ''; $message_type = ''; // Resetta globali prima di validazione form

    if ($temp_message_type_from_upload === 'error' && !empty($temp_message_from_upload)) {
        $errors_event[] = $temp_message_from_upload;
    }


    if (empty($errors_event)) {
        if ($event_id_to_update) { 
            $stmt = $mysqli->prepare("UPDATE historical_events SET event_title=?, event_description=?, event_date_start=?, event_date_end=?, category=?, related_issue_start=?, related_issue_end=?, event_image_path=?, related_story_id=? WHERE event_id=?");
            $stmt->bind_param("ssssssssii", $event_title, $event_description, $event_date_start, $event_date_end, $category, $related_issue_start_post, $related_issue_end, $event_image_path_to_save, $related_story_id_post, $event_id_to_update);
            $action_msg_verb = "modificato";
        } else { 
            $stmt = $mysqli->prepare("INSERT INTO historical_events (event_title, event_description, event_date_start, event_date_end, category, related_issue_start, related_issue_end, event_image_path, related_story_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssi", $event_title, $event_description, $event_date_start, $event_date_end, $category, $related_issue_start_post, $related_issue_end, $event_image_path_to_save, $related_story_id_post);
            $action_msg_verb = "aggiunto";
        }

        if ($stmt && $stmt->execute()) {
            $_SESSION['admin_action_message'] = "Evento storico " . $action_msg_verb . " con successo!";
            $_SESSION['admin_action_message_type'] = 'success';
        } else {
            $_SESSION['admin_action_message'] = "Errore durante il salvataggio dell'evento: " . ($stmt ? $stmt->error : $mysqli->error);
            $_SESSION['admin_action_message_type'] = 'error';
            if ($event_image_path_to_save && $event_image_path_to_save !== $current_event_image_from_form && file_exists(UPLOADS_PATH . $event_image_path_to_save)) {
                @unlink(UPLOADS_PATH . $event_image_path_to_save);
            }
        }
        if ($stmt) $stmt->close();
        header('Location: historical_events_manage.php');
        exit;

    } else {
        $message = "Errore nel form: <ul><li>" . implode("</li><li>", array_map('htmlspecialchars', $errors_event)) . "</li></ul>";
        $message_type = 'error';
        $event_data_form = $_POST;
        $event_data_form['event_image_path'] = $event_image_path_to_save; 
        $event_data_form['related_issue_start'] = $related_issue_start_post; // Assicura che il valore POSTato sia usato per ripopolare
        $event_data_form['related_story_id'] = $related_story_id_post; // Assicura che il valore POSTato sia usato per ripopolare
        $editing_event_id = $event_id_to_update;

        // Ricarica storie per il dropdown se l'albo di riferimento era stato inviato
        if (!empty($related_issue_start_post)) {
            $stmt_find_comic_id_for_stories_err = $mysqli->prepare("SELECT comic_id FROM comics WHERE issue_number = ? LIMIT 1");
            if ($stmt_find_comic_id_for_stories_err) {
                $stmt_find_comic_id_for_stories_err->bind_param("s", $related_issue_start_post);
                $stmt_find_comic_id_for_stories_err->execute();
                $res_comic_id_err = $stmt_find_comic_id_for_stories_err->get_result();
                if ($comic_found_for_stories_err = $res_comic_id_err->fetch_assoc()) {
                    $related_comic_id_for_stories_err = $comic_found_for_stories_err['comic_id'];
                    $stmt_s_err = $mysqli->prepare("SELECT story_id, title, sequence_in_comic FROM stories WHERE comic_id = ? ORDER BY sequence_in_comic ASC, story_id ASC");
                    if ($stmt_s_err) {
                        $stmt_s_err->bind_param("i", $related_comic_id_for_stories_err);
                        $stmt_s_err->execute();
                        $res_s_err = $stmt_s_err->get_result();
                        while($row_s_err = $res_s_err->fetch_assoc()){
                            $stories_for_related_issue_select[] = $row_s_err;
                        }
                        $stmt_s_err->close();
                    }
                }
                $stmt_find_comic_id_for_stories_err->close();
            }
        }
    }
}


if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && !$_POST) {
    $editing_event_id = (int)$_GET['id'];
    $stmt_edit = $mysqli->prepare("SELECT event_id, event_title, event_description, event_date_start, event_date_end, category, related_issue_start, related_issue_end, event_image_path, related_story_id FROM historical_events WHERE event_id = ?");
    if ($stmt_edit) {
        $stmt_edit->bind_param("i", $editing_event_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($data = $result_edit->fetch_assoc()) {
            $event_data_form = $data;
            if (!empty($event_data_form['related_issue_start'])) {
                $stmt_find_comic_id_edit = $mysqli->prepare("SELECT comic_id FROM comics WHERE issue_number = ? LIMIT 1");
                if ($stmt_find_comic_id_edit) {
                    $stmt_find_comic_id_edit->bind_param("s", $event_data_form['related_issue_start']);
                    $stmt_find_comic_id_edit->execute();
                    $res_comic_id_edit = $stmt_find_comic_id_edit->get_result();
                    if ($comic_found_edit = $res_comic_id_edit->fetch_assoc()) {
                        $related_comic_id_edit = $comic_found_edit['comic_id'];
                        $stmt_s_edit = $mysqli->prepare("SELECT story_id, title, sequence_in_comic FROM stories WHERE comic_id = ? ORDER BY sequence_in_comic ASC, story_id ASC");
                        if ($stmt_s_edit) {
                            $stmt_s_edit->bind_param("i", $related_comic_id_edit);
                            $stmt_s_edit->execute();
                            $res_s_edit = $stmt_s_edit->get_result();
                            while($row_s_edit = $res_s_edit->fetch_assoc()){
                                $stories_for_related_issue_select[] = $row_s_edit;
                            }
                            $stmt_s_edit->close();
                        }
                    }
                    $stmt_find_comic_id_edit->close();
                }
            }
        } else {
            $_SESSION['admin_action_message'] = "Evento non trovato per la modifica.";
            $_SESSION['admin_action_message_type'] = 'error';
            header('Location: historical_events_manage.php');
            exit;
        }
        $stmt_edit->close();
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $event_id_to_delete = (int)$_GET['id'];
    $image_to_delete_path_del = null;
    $stmt_get_img_del = $mysqli->prepare("SELECT event_image_path FROM historical_events WHERE event_id = ?");
    if ($stmt_get_img_del) {
        $stmt_get_img_del->bind_param("i", $event_id_to_delete);
        $stmt_get_img_del->execute();
        $result_img_del = $stmt_get_img_del->get_result();
        if ($img_data_del = $result_img_del->fetch_assoc()) {
            $image_to_delete_path_del = $img_data_del['event_image_path'];
        }
        $stmt_get_img_del->close();
    }

    $stmt_delete = $mysqli->prepare("DELETE FROM historical_events WHERE event_id = ?");
    if ($stmt_delete) {
        $stmt_delete->bind_param("i", $event_id_to_delete);
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0 && $image_to_delete_path_del && file_exists(UPLOADS_PATH . $image_to_delete_path_del)) {
                @unlink(UPLOADS_PATH . $image_to_delete_path_del); 
            }
            $_SESSION['admin_action_message'] = "Evento storico eliminato con successo.";
            $_SESSION['admin_action_message_type'] = 'success';
        } else {
            $_SESSION['admin_action_message'] = "Errore durante l'eliminazione dell'evento: " . $stmt_delete->error;
            $_SESSION['admin_action_message_type'] = 'error';
        }
        $stmt_delete->close();
        header('Location: historical_events_manage.php');
        exit;
    }
}

$historical_events_list = [];
$result_events = $mysqli->query("SELECT event_id, event_title, event_description, event_date_start, event_date_end, category, related_issue_start, related_issue_end, event_image_path, related_story_id FROM historical_events ORDER BY event_date_start DESC, event_title ASC");
if ($result_events) {
    while ($row = $result_events->fetch_assoc()) {
        if ($row['related_story_id']) {
            $stmt_story_title = $mysqli->prepare("SELECT title FROM stories WHERE story_id = ?");
            if($stmt_story_title){
                $stmt_story_title->bind_param("i", $row['related_story_id']);
                $stmt_story_title->execute();
                $res_story_title = $stmt_story_title->get_result();
                if($story_title_data = $res_story_title->fetch_assoc()){
                    $row['related_story_title_display'] = $story_title_data['title'];
                }
                $stmt_story_title->close();
            }
        }
        $historical_events_list[] = $row;
    }
    $result_events->free();
}

$event_categories_options = [
    '' => '-- Seleziona Categoria (Opzionale) --',
    'Direzione Editoriale' => 'Direzione Editoriale', 'Cambi Formato' => 'Cambi Formato',
    'Cambi Prezzo' => 'Cambi Prezzo', 'Periodicità' => 'Periodicità',
    'Tipologia Coste' => 'Tipologia Coste', 'Contenuti Speciali' => 'Contenuti Speciali',
    'Gadget Memorabili' => 'Gadget Memorabili', 'Censure o Modifiche' => 'Censure o Modifiche',
    'Curiosità Editoriali' => 'Curiosità Editoriali', 'Eventi Esterni Impattanti' => 'Eventi Esterni Impattanti',
    'Innovazioni Grafiche/Narrative' => 'Innovazioni Grafiche/Narrative', 'Altro' => 'Altro', 'Eventi Personaggio' => 'Eventi Personaggio'
];

require_once ROOT_PATH . 'admin/includes/header_admin.php';
?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <h3><?php echo $editing_event_id ? 'Modifica Evento Esistente' : 'Aggiungi Nuovo Evento Storico'; ?></h3>
    <form action="historical_events_manage.php" method="POST" enctype="multipart/form-data">
        <?php if ($editing_event_id): ?>
            <input type="hidden" name="event_id" value="<?php echo $editing_event_id; ?>">
        <?php endif; ?>
        
        <input type="hidden" name="current_event_image" value="<?php echo htmlspecialchars($event_data_form['event_image_path'] ?? ''); ?>">

        <div class="form-group">
            <label for="event_title">Titolo Evento:</label>
            <input type="text" name="event_title" id="event_title" class="form-control" value="<?php echo htmlspecialchars($event_data_form['event_title']); ?>" required>
        </div>
        <div class="form-group">
            <label for="event_description">Descrizione Dettagliata:</label>
            <textarea name="event_description" id="event_description" class="form-control" rows="5" required><?php echo htmlspecialchars($event_data_form['event_description']); ?></textarea>
        </div>
        <div class="form-group" style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width:200px;">
                <label for="event_date_start">Data Inizio Evento:</label>
                <input type="date" name="event_date_start" id="event_date_start" class="form-control" value="<?php echo htmlspecialchars($event_data_form['event_date_start']); ?>" required>
            </div>
            <div style="flex: 1; min-width:200px;">
                <label for="event_date_end">Data Fine Evento (opzionale):</label>
                <input type="date" name="event_date_end" id="event_date_end" class="form-control" value="<?php echo htmlspecialchars($event_data_form['event_date_end'] ?? ''); ?>">
            </div>
        </div>
         <div class="form-group">
            <label for="category">Categoria Evento:</label>
            <select name="category" id="category" class="form-control">
                <?php foreach($event_categories_options as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (isset($event_data_form['category']) && $event_data_form['category'] === $value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label for="related_issue_start_form">N. Albo Inizio Riferimento (opz.):</label>
                <input type="text" name="related_issue_start" id="related_issue_start_form" class="form-control" placeholder="Es. 1500" value="<?php echo htmlspecialchars($event_data_form['related_issue_start'] ?? ''); ?>">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label for="related_issue_end">N. Albo Fine Riferimento (opz.):</label>
                <input type="text" name="related_issue_end" id="related_issue_end" class="form-control" placeholder="Es. 1510" value="<?php echo htmlspecialchars($event_data_form['related_issue_end'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="related_story_id">Storia Specifica Correlata (opzionale):</label>
            <select name="related_story_id" id="related_story_id" class="form-control">
                <option value="">-- <?php 
                    if (!empty($event_data_form['related_issue_start']) && empty($stories_for_related_issue_select)) {
                        echo "Nessuna storia per l'albo specificato";
                    } elseif (empty($event_data_form['related_issue_start'])) {
                        echo "Seleziona prima 'N. Albo Inizio Riferimento'";
                    } else {
                        echo "Nessuna storia specifica";
                    }
                ?> --</option>
                <?php foreach($stories_for_related_issue_select as $story_opt): ?>
                    <option value="<?php echo $story_opt['story_id']; ?>" <?php echo (isset($event_data_form['related_story_id']) && $event_data_form['related_story_id'] == $story_opt['story_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($story_opt['title']) . ($story_opt['sequence_in_comic'] > 0 ? ' (Ord. ' . $story_opt['sequence_in_comic'] . ')' : ' (Ord. N/D)'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Seleziona una storia dall'albo specificato in "N. Albo Inizio Riferimento". La lista si aggiorna al cambio del numero albo (richiede interazione o ricaricamento pagina se non usi AJAX qui per il form).</small>
        </div>

        <div class="form-group">
            <label for="event_image">Immagine Evento (opzionale):</label>
            <input type="file" name="event_image" id="event_image" class="form-control-file">
            <?php if ($editing_event_id && !empty($event_data_form['event_image_path'])): ?>
                <p style="margin-top:10px;">Immagine attuale:
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($event_data_form['event_image_path']); ?>" alt="Immagine evento attuale" style="max-width: 200px; max-height:150px; height: auto; margin-top: 5px; border:1px solid #ccc; padding:2px; vertical-align:middle;">
                    <br>
                    <label class="inline-label" style="margin-top:5px;">
                        <input type="checkbox" name="delete_event_image" value="1"> Rimuovi immagine attuale
                    </label>
                </p>
            <?php endif; ?>
            <small>Carica un'immagine rappresentativa per l'evento (max 2MB).</small>
        </div>

        <div class="form-group">
            <button type="submit" name="save_historical_event" class="btn btn-primary">
                <?php echo $editing_event_id ? 'Salva Modifiche Evento' : 'Aggiungi Evento'; ?>
            </button>
            <?php if ($editing_event_id): ?>
                <a href="historical_events_manage.php" class="btn btn-secondary">Annulla Modifica</a>
            <?php endif; ?>
        </div>
    </form>

    <hr style="margin: 30px 0;">
    <h3>Elenco Eventi Storici Inseriti</h3>
    <?php if (!empty($historical_events_list)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:80px;">Immagine</th>
                    <th>Titolo</th>
                    <th>Periodo</th>
                    <th>Categoria</th>
                    <th>Rif. Albi</th>
                    <th>Rif. Storia</th>
                    <th style="min-width:150px;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historical_events_list as $event): ?>
                <tr>
                    <td>
                        <?php if ($event['event_image_path']): ?>
                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($event['event_image_path']); ?>" alt="<?php echo htmlspecialchars($event['event_title']); ?>" style="width: 70px; height: auto; max-height: 70px; object-fit: cover; border-radius:3px;">
                        <?php else: ?>
                            <span style="font-size:0.8em; color:#999;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                    <td>
                        <?php echo date("d/m/Y", strtotime($event['event_date_start'])); ?>
                        <?php if ($event['event_date_end'] && $event['event_date_end'] !== $event['event_date_start']): ?>
                            - <?php echo date("d/m/Y", strtotime($event['event_date_end'])); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($event['category'] ?? 'N/D'); ?></td>
                    <td>
                        <?php
                        if ($event['related_issue_start'] || $event['related_issue_end']) {
                            echo htmlspecialchars($event['related_issue_start'] ?? '?');
                            if ($event['related_issue_end'] && $event['related_issue_end'] !== $event['related_issue_start']) {
                                echo ' - ' . htmlspecialchars($event['related_issue_end']);
                            }
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($event['related_story_id'] && isset($event['related_story_title_display'])): ?>
                            <small><?php echo htmlspecialchars($event['related_story_title_display']); ?> (ID: <?php echo $event['related_story_id']; ?>)</small>
                        <?php elseif ($event['related_story_id']): ?>
                             <small>ID Storia: <?php echo $event['related_story_id']; ?> (Titolo non caricato)</small>
                        <?php else: echo '<span style="font-size:0.8em; color:#999;">N/A</span>'; endif; ?>
                    </td>
                    <td style="white-space: nowrap;">
                        <a href="?action=edit&id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-warning">Modifica</a>
                        <a href="?action=delete&id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo evento?');">Elimina</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nessun evento storico inserito al momento.</p>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const issueStartInput = document.getElementById('related_issue_start_form');
    const storySelect = document.getElementById('related_story_id');

    function fetchStoriesForIssue(issueNumber) {
        if (!issueNumber || !issueNumber.trim()) {
            storySelect.innerHTML = '<option value="">-- Seleziona prima un albo di riferimento --</option>';
            storySelect.disabled = true;
            return;
        }

        storySelect.innerHTML = '<option value="">-- Caricamento storie... --</option>';
        storySelect.disabled = true;

        fetch('<?php echo BASE_URL; ?>admin/ajax_get_stories.php?issue_number=' + encodeURIComponent(issueNumber))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                storySelect.innerHTML = ''; 
                if (data.success && data.stories && data.stories.length > 0) {
                    storySelect.innerHTML += '<option value="">-- Nessuna storia specifica --</option>';
                    data.stories.forEach(story => {
                        const option = document.createElement('option');
                        option.value = story.story_id;
                        let storyTitleText = story.title;
                        if (story.sequence_in_comic > 0) {
                            storyTitleText += ' (Ord. ' + story.sequence_in_comic + ')';
                        } else {
                            storyTitleText += ' (Ord. N/D)';
                        }
                        option.textContent = storyTitleText;
                        storySelect.appendChild(option);
                    });
                    storySelect.disabled = false;
                } else if (data.success && data.stories && data.stories.length === 0) {
                    storySelect.innerHTML = '<option value="">-- Nessuna storia trovata per l\\\'albo ' + htmlspecialchars(issueNumber) + ' --</option>';
                    storySelect.disabled = true;
                } else {
                    storySelect.innerHTML = '<option value="">-- Errore o albo non trovato --</option>';
                    storySelect.disabled = true;
                    console.error('Errore AJAX storie evento:', data.message || 'Albo non trovato o errore sconosciuto.');
                }
            })
            .catch(error => {
                storySelect.innerHTML = '<option value="">-- Errore richiesta storie --</option>';
                storySelect.disabled = true;
                console.error('Errore fetch storie evento:', error);
            });
    }
    
    function htmlspecialchars(str) { // Piccola utility JS per l'escape nell'alert
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return str.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    if (issueStartInput && storySelect) {
        issueStartInput.addEventListener('change', function() {
            fetchStoriesForIssue(this.value);
        });
        // Non è necessario chiamare fetchStoriesForIssue al caricamento qui
        // perché la logica PHP per la modalità 'edit' e per il ripopolamento
        // in caso di errore POST già si occupa di popolare $stories_for_related_issue_select
        // che viene usato per generare le opzioni del select lato server.
        // Lo script JS serve solo per gli aggiornamenti DINAMICI dopo che la pagina è caricata.
    }
});
</script>

<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
if(isset($mysqli) && $mysqli instanceof mysqli) { $mysqli->close(); }
?>