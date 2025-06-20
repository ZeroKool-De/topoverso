<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// CONTROLLO ACCESSO
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$is_true_admin = isset($_SESSION['admin_user_id']);
$is_contributor = (isset($_SESSION['user_id_frontend']) && isset($_SESSION['user_role_frontend']) && $_SESSION['user_role_frontend'] === 'contributor');
$current_user_id_for_proposal = $_SESSION['user_id_frontend'] ?? 0;

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


$page_title = "Gestione Autori/Persone";

// Recupera il termine di ricerca
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';

// Gestione messaggi da sessione o GET
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
} elseif (isset($_GET['message']) && isset($_GET['message_type'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
    $message_type = htmlspecialchars($_GET['message_type']);
}

require_once ROOT_PATH . 'admin/includes/header_admin.php';


$action = $_GET['action'] ?? 'list';
$person_id_to_edit_original = null;
$person_data = ['name' => '', 'biography' => '', 'person_image' => null]; 

if ($action === 'edit' && isset($_GET['id']) && !$_POST) { 
    $person_id_to_edit_original = (int)$_GET['id'];
    $stmt = $mysqli->prepare("SELECT person_id, name, biography, person_image FROM persons WHERE person_id = ?");
    $stmt->bind_param("i", $person_id_to_edit_original);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $person_data = $result->fetch_assoc();
    } else {
        $_SESSION['message'] = "Persona non trovata per la modifica.";
        $_SESSION['message_type'] = 'error';
        header('Location: ' . BASE_URL . 'admin/persons_manage.php?action=list');
        exit;
    }
    $stmt->close();
}

// Funzione helper per gestire l'upload dell'immagine della persona (invariata)
function handle_person_image_upload($file_input_name, $current_image_path, $delete_flag_name, $is_editing_action_flag, $is_contributor_upload_flag) {
    global $message, $message_type;
    $image_path_to_return = $current_image_path;
    $base_upload_path = UPLOADS_PATH;
    $target_subdir = 'persons';
    $final_upload_dir_relative = rtrim($target_subdir, '/') . '/';
    $pending_upload_dir_relative = 'pending_images/' . rtrim($target_subdir, '/') . '/';

    if (isset($_POST[$delete_flag_name]) && $_POST[$delete_flag_name] == '1' && $is_editing_action_flag && $current_image_path) {
        if (!$is_contributor_upload_flag && file_exists($base_upload_path . $current_image_path) && strpos($current_image_path, 'pending_images/') === false) {
            @unlink($base_upload_path . $current_image_path);
        }
        $image_path_to_return = null;
        if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] == UPLOAD_ERR_NO_FILE) {
            return $image_path_to_return;
        }
    }

    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $upload_dir_actual_relative = $is_contributor_upload_flag ? $pending_upload_dir_relative : $final_upload_dir_relative;
        $upload_dir_absolute = $base_upload_path . $upload_dir_actual_relative;

        if (!is_dir($upload_dir_absolute)) {
            if (!mkdir($upload_dir_absolute, 0775, true)) {
                $message .= " Errore: Impossibile creare la cartella di upload per persone: " . htmlspecialchars($upload_dir_absolute) . ".";
                $message_type = 'error';
                return $image_path_to_return;
            }
        }
        
        $original_filename = $_FILES[$file_input_name]['name'];
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_extension, $allowed_extensions)) {
            $message .= " Formato file non permesso per l'immagine persona.";
            $message_type = 'error';
            return $image_path_to_return;
        }

        $temp_filename_no_ext = 'person_' . uniqid('', true) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
        $temp_uploaded_file_absolute = $upload_dir_absolute . $temp_filename_no_ext . '.' . $file_extension;


        if (move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $temp_uploaded_file_absolute)) {
            $jpeg_quality = 75;
            $png_compression = 9;
            $convertToWebp = true;

            $processed_image_absolute_path = compress_and_optimize_image(
                $temp_uploaded_file_absolute,
                $temp_uploaded_file_absolute,
                $file_extension,
                $jpeg_quality,
                $png_compression,
                $convertToWebp
            );

            if ($processed_image_absolute_path) {
                $new_final_filename_relative = $upload_dir_actual_relative . basename($processed_image_absolute_path);
                if (!$is_contributor_upload_flag && $is_editing_action_flag && $current_image_path &&
                    file_exists($base_upload_path . $current_image_path) &&
                    strpos($current_image_path, 'pending_images/') === false &&
                    $current_image_path !== $new_final_filename_relative) {
                    @unlink($base_upload_path . $current_image_path);
                }
                $image_path_to_return = $new_final_filename_relative;
            } else {
                $message .= " Fallimento compressione per ".htmlspecialchars($file_input_name).". Verrà usata l'immagine originale caricata.";
                $image_path_to_return = $upload_dir_actual_relative . basename($temp_uploaded_file_absolute);
                 error_log("Compressione fallita per $file_input_name (persona), usando $image_path_to_return");
            }
        } else {
            $message .= " Errore durante il caricamento dell'immagine persona.";
            $message_type = 'error';
        }
    }
    return $image_path_to_return;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_editing_form_action = isset($_POST['edit_person']);
    $person_id_original_from_post = $is_editing_form_action ? (int)$_POST['person_id'] : null;

    $name_proposal = trim($_POST['name']);
    $biography_proposal = trim($_POST['biography']);
    $current_image_path_on_form = $_POST['current_image_path'] ?? null;
    $person_image_path_proposal = $is_editing_form_action ? $current_image_path_on_form : null;


    if (empty($name_proposal)) {
        $message = "Il nome è obbligatorio.";
        $message_type = 'error';
    } else {
        $person_image_path_proposal = handle_person_image_upload('person_image', $person_image_path_proposal, 'delete_image', $is_editing_form_action, $is_contributor && !$is_true_admin);
        
        if ($message_type !== 'error') { // Se non ci sono stati errori di upload
            if ($is_contributor && !$is_true_admin) {
                // SALVA PROPOSTA PER CONTRIBUTORE (logica invariata)
                $action_type_proposal = $is_editing_form_action ? 'edit' : 'add';
                $stmt_pending = $mysqli->prepare("INSERT INTO pending_persons 
                    (person_id_original, proposer_user_id, action_type, name_proposal, biography_proposal, person_image_proposal)
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_pending->bind_param("iissss",
                    $person_id_original_from_post, $current_user_id_for_proposal, $action_type_proposal,
                    $name_proposal, $biography_proposal, $person_image_path_proposal);

                if ($stmt_pending->execute()) {
                    $_SESSION['message'] = "Proposta per la persona inviata con successo per revisione!";
                    $_SESSION['message_type'] = 'success';
                } else {
                     if ($mysqli->errno === 1062) {
                         $_SESSION['message'] = "Errore: Esiste già una proposta pendente o una persona con questo nome.";
                     } else {
                        $_SESSION['message'] = "Errore SQL invio proposta persona: " . $stmt_pending->error;
                     }
                    $_SESSION['message_type'] = 'error';
                }
                $stmt_pending->close();
                header('Location: ' . BASE_URL . 'admin/persons_manage.php?action=list'); // Contributore torna alla lista
                exit;

            } else { // GESTIONE ADMIN
                
                // --- INIZIO BLOCCO MODIFICATO ---
                // Generazione dello slug per la persona
                $exclude_id_for_slug = $is_editing_form_action ? $person_id_original_from_post : 0;
                $slug = generate_slug($name_proposal, $mysqli, 'persons', 'slug', $exclude_id_for_slug, 'person_id');
                // --- FINE BLOCCO MODIFICATO ---

                if (isset($_POST['add_person'])) {
                    $stmt = $mysqli->prepare("INSERT INTO persons (name, slug, biography, person_image) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name_proposal, $slug, $biography_proposal, $person_image_path_proposal); // Aggiunto slug
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Persona aggiunta con successo! Pronto per inserirne un'altra.";
                        $_SESSION['message_type'] = 'success';
                        header('Location: ' . BASE_URL . 'admin/persons_manage.php?action=add&message=' . urlencode($_SESSION['message']) . '&message_type=success');
                        unset($_SESSION['message']); 
                        unset($_SESSION['message_type']);
                        $stmt->close();
                        $mysqli->close();
                        exit;
                    } else {
                        if ($mysqli->errno === 1062) { 
                             $message = "Errore: Esiste già una persona con questo nome o con uno slug simile.";
                        } else {
                             $message = "Errore durante l'aggiunta della persona: " . htmlspecialchars($stmt->error);
                        }
                        $message_type = 'error';
                        if ($person_image_path_proposal && $person_image_path_proposal !== $current_image_path_on_form && strpos($person_image_path_proposal, 'pending_images/') === false) {
                            if(file_exists(UPLOADS_PATH . $person_image_path_proposal)) @unlink(UPLOADS_PATH . $person_image_path_proposal);
                        }
                        $stmt->close();
                    }
                } elseif (isset($_POST['edit_person'])) {
                    $person_id_update = (int)$_POST['person_id'];
                    $stmt = $mysqli->prepare("UPDATE persons SET name = ?, slug = ?, biography = ?, person_image = ? WHERE person_id = ?");
                    $stmt->bind_param("ssssi", $name_proposal, $slug, $biography_proposal, $person_image_path_proposal, $person_id_update); // Aggiunto slug
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Persona modificata con successo!";
                        $_SESSION['message_type'] = 'success';
                    } else {
                         if ($mysqli->errno === 1062) { 
                             $message = "Errore: Esiste già un'altra persona con questo nome o slug.";
                         } else {
                            $message = "Errore durante la modifica della persona: " . htmlspecialchars($stmt->error);
                         }
                        $message_type = 'error';
                    }
                    $stmt->close();
                    if ($message_type == 'success') { // Se modifica ok, torna alla lista
                        header('Location: ' . BASE_URL . 'admin/persons_manage.php?action=list&message=' . urlencode($_SESSION['message']) . '&message_type=success');
                        unset($_SESSION['message']); 
                        unset($_SESSION['message_type']);
                        exit;
                    }
                }
            }
        }
    }
    // Se c'è un errore (validazione, upload, o DB) e non c'è stato redirect, ripopola i dati per il form
    $person_data = ['name' => $name_proposal, 'biography' => $biography_proposal, 'person_image' => $person_image_path_proposal];
    if(isset($_POST['person_id'])) $person_id_to_edit_original = (int)$_POST['person_id']; // Mantiene l'ID per il form di edit
    $action = isset($_POST['add_person']) ? 'add' : (isset($_POST['edit_person']) ? 'edit' : 'list'); // Rimane sulla stessa action
}


if ($action === 'delete' && isset($_GET['id'])) {
    if (!$is_true_admin) {
        $_SESSION['message'] = "Azione non permessa."; $_SESSION['message_type'] = 'error';
        header('Location: ' . BASE_URL . 'admin/persons_manage.php?action=list'); exit;
    }
    $person_id_to_delete = (int)$_GET['id'];
    $stmt_img = $mysqli->prepare("SELECT person_image FROM persons WHERE person_id = ?");
    $stmt_img->bind_param("i", $person_id_to_delete); $stmt_img->execute(); $result_img = $stmt_img->get_result();
    $image_to_delete_on_server = null; if ($result_img->num_rows === 1) $image_to_delete_on_server = $result_img->fetch_assoc()['person_image'];
    $stmt_img->close();

    $stmt = $mysqli->prepare("DELETE FROM persons WHERE person_id = ?");
    $stmt->bind_param("i", $person_id_to_delete);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            if ($image_to_delete_on_server && file_exists(UPLOADS_PATH . $image_to_delete_on_server)) {
                @unlink(UPLOADS_PATH . $image_to_delete_on_server);
            }
            $_SESSION['message'] = "Persona eliminata con successo!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Nessuna persona trovata con questo ID per l'eliminazione.";
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = "Errore durante l'eliminazione della persona: " . htmlspecialchars($stmt->error) . ". Potrebbe essere collegata a fumetti o storie.";
        $_SESSION['message_type'] = 'error';
    }
    $stmt->close();
    header('Location: ' . BASE_URL . 'admin/persons_manage.php?action=list');
    exit;
}

?>

<div class="container admin-container">
    <h2><?php echo $page_title; ?> <?php if ($is_contributor && !$is_true_admin) echo "(Modalità Contributore)"; ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo $message; // $message può contenere HTML (lista errori) o essere passato con htmlspecialchars da GET ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="?action=add" class="btn btn-primary">
                <?php echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuova Persona' : 'Aggiungi Nuova Persona'; ?>
            </a>
            <form action="persons_manage.php" method="GET" class="form-inline" style="display: flex; gap: 5px;">
                <input type="hidden" name="action" value="list">
                <input type="text" name="search" class="form-control" placeholder="Cerca nome, biografia..." value="<?php echo htmlspecialchars($search_term); ?>" style="min-width: 250px;">
                <button type="submit" class="btn btn-info">Cerca</button>
                <?php if (!empty($search_term)): ?>
                    <a href="persons_manage.php?action=list" class="btn btn-secondary">Resetta</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($search_term)): ?>
            <p>Risultati della ricerca per: <strong>"<?php echo htmlspecialchars($search_term); ?>"</strong></p>
        <?php endif; ?>
        <h3>Elenco Persone</h3>
        <?php
        $sql_list = "SELECT person_id, name, biography, person_image FROM persons";
        $params_list_persons = [];
        $types_list_persons = "";

        if (!empty($search_term)) {
            $search_like = "%" . $search_term . "%";
            $sql_list .= " WHERE (name LIKE ? OR biography LIKE ?)";
            $params_list_persons[] = $search_like;
            $params_list_persons[] = $search_like;
            $types_list_persons .= "ss";
        }
        $sql_list .= " ORDER BY name ASC";
        
        $stmt_list = $mysqli->prepare($sql_list);
        $result_list = null; // Inizializza a null
        if ($stmt_list) {
            if (!empty($params_list_persons)) {
                $stmt_list->bind_param($types_list_persons, ...$params_list_persons);
            }
            $stmt_list->execute();
            $result_list = $stmt_list->get_result();
        } else {
            echo "<div class='message error'>Errore nella preparazione della query di ricerca: " . $mysqli->error . "</div>";
        }


        if ($result_list && $result_list->num_rows > 0):
        ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Immagine</th>
                        <th>Nome</th>
                        <th>Biografia (inizio)</th>
                        <th style="width: 250px;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result_list->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if ($row['person_image']): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($row['person_image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width:50px; height:auto; border-radius:3px;">
                            <?php elseif (function_exists('generate_image_placeholder')): ?>
                                <?php echo generate_image_placeholder($row['name'], 50, 50, 'admin-table-placeholder'); ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['biography'] ?? '', 0, 100)); ?>...</td>
                        <td style="white-space: nowrap;">
                            <a href="?action=edit&id=<?php echo $row['person_id']; ?>" class="btn btn-sm btn-warning" title="Modifica">
                                <?php echo ($is_contributor && !$is_true_admin) ? 'Prop. Mod.' : 'Modifica'; ?>
                            </a>
                            <a href="<?php echo BASE_URL; ?>author_detail.php?id=<?php echo $row['person_id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Visualizza Scheda Pubblica">Scheda</a>
                            <?php if ($is_true_admin): ?>
                            <a href="?action=delete&id=<?php echo $row['person_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa persona?');" title="Elimina">Elimina</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nessuna persona trovata <?php if(!empty($search_term)) echo "per la ricerca '".htmlspecialchars($search_term)."'"; ?>. 
            <?php if(empty($search_term)): ?>
                <a href="?action=add">
                     <?php echo ($is_contributor && !$is_true_admin) ? 'Proponine una!' : 'Aggiungine una!'; ?>
                </a>
            <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php 
        if($result_list) $result_list->free(); 
        if(isset($stmt_list) && $stmt_list) $stmt_list->close();
        ?>

    <?php elseif ($action === 'add' || ($action === 'edit' && $person_id_to_edit_original)): ?>
        <h3>
            <?php 
            if ($action === 'add') echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuova Persona' : 'Aggiungi Nuova Persona';
            else echo ($is_contributor && !$is_true_admin) ? 'Proponi Modifiche Persona' : 'Modifica Persona';
            ?>
        </h3>
         <?php if ($is_contributor && !$is_true_admin): ?>
            <p class="message info" style="font-size:0.9em;">
                <strong>Nota per i Contributori:</strong> Le tue proposte verranno inviate per approvazione. Le immagini caricate saranno temporanee.
            </p>
        <?php endif; ?>

        <form action="persons_manage.php<?php echo ($action === 'edit' && $person_id_to_edit_original) ? '?action=edit&id='.$person_id_to_edit_original : '?action=add'; ?>" method="POST" enctype="multipart/form-data">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="edit_person" value="1">
                <input type="hidden" name="person_id" value="<?php echo $person_id_to_edit_original; ?>">
                <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($person_data['person_image'] ?? ''); ?>">
            <?php else: ?>
                 <input type="hidden" name="add_person" value="1">
            <?php endif; ?>

            <div class="form-group">
                <label for="name">Nome:</label>
                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($person_data['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="biography">Biografia:</label>
                <textarea id="biography" name="biography" class="form-control" rows="5"><?php echo htmlspecialchars($person_data['biography'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="person_image">Immagine Persona:</label>
                <input type="file" id="person_image" name="person_image" class="form-control-file">
                <?php if ($action === 'edit' && !empty($person_data['person_image'])): ?>
                    <p style="margin-top:10px;">Immagine attuale: 
                        <img src="<?php echo UPLOADS_URL . htmlspecialchars($person_data['person_image']); ?>" alt="Immagine attuale" style="width: 100px; height: auto; margin-top: 5px; vertical-align: middle; border:1px solid #ccc; padding:2px;">
                    </p>
                    <label class="inline-label"><input type="checkbox" name="delete_image" value="1" id="delete_image_cb"> Cancella immagine attuale</label>
                <?php elseif ($action === 'edit' && function_exists('generate_image_placeholder')): ?>
                     <p style="margin-top:10px;">Nessuna immagine attuale.</p>
                <?php endif; ?>
                <small class="form-text">Lascia vuoto per non modificare l'immagine esistente (in modifica) o per non caricare nessuna immagine (in aggiunta).</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-success">
                    <?php 
                        if ($action === 'add') echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Persona' : 'Aggiungi Persona';
                        else echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Modifiche' : 'Salva Modifiche';
                    ?>
                </button>
                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                <?php if ($action === 'edit' && $person_id_to_edit_original): ?>
                    <a href="<?php echo BASE_URL; ?>author_detail.php?id=<?php echo $person_id_to_edit_original; ?>" class="btn btn-info" target="_blank" style="margin-left: 10px;">Visualizza Scheda Autore</a>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

</div>
<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
if (isset($mysqli) && $mysqli instanceof mysqli) { $mysqli->close(); }
?>