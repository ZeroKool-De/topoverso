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

$page_title = "Gestione Personaggi";
require_once ROOT_PATH . 'admin/includes/header_admin.php';

$message = '';
$message_type = '';

$all_comics_for_select = [];
$result_comics_list = $mysqli->query("SELECT comic_id, issue_number, title FROM comics ORDER BY CAST(REPLACE(REPLACE(issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ASC, publication_date ASC");
if ($result_comics_list) {
    while ($row = $result_comics_list->fetch_assoc()) {
        $all_comics_for_select[] = $row;
    }
    $result_comics_list->free();
}

$action = $_GET['action'] ?? 'list';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$character_id_to_edit_original = null; // ID originale se in modifica

$character_data = [
    'name' => '',
    'description' => '',
    'character_image' => null,
    'first_appearance_comic_id' => null,
    'first_appearance_story_id' => null,
    'first_appearance_date' => null,
    'first_appearance_notes' => '',
    'is_first_appearance_verified' => 0
];
$stories_for_selected_comic = [];

if ($action === 'edit' && isset($_GET['id'])) {
    $character_id_to_edit_original = (int)$_GET['id'];
    $stmt = $mysqli->prepare("SELECT name, description, character_image, first_appearance_comic_id, first_appearance_story_id, first_appearance_date, first_appearance_notes, is_first_appearance_verified FROM characters WHERE character_id = ?");
    $stmt->bind_param("i", $character_id_to_edit_original);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $db_data = $result->fetch_assoc();
        $character_data = array_merge($character_data, $db_data);
        if ($character_data['first_appearance_comic_id']) {
            $stmt_stories = $mysqli->prepare("SELECT story_id, title, sequence_in_comic FROM stories WHERE comic_id = ? ORDER BY sequence_in_comic ASC, story_id ASC");
            $stmt_stories->bind_param("i", $character_data['first_appearance_comic_id']);
            $stmt_stories->execute();
            $result_s = $stmt_stories->get_result();
            while($row_s = $result_s->fetch_assoc()){
                $stories_for_selected_comic[] = $row_s;
            }
            $stmt_stories->close();
        }
    } else {
        $_SESSION['message'] = "Personaggio non trovato."; $_SESSION['message_type'] = 'error';
        header('Location: characters_manage.php?action=list'); exit;
    }
    $stmt->close();
}

// Funzione helper per upload immagine personaggio
function handle_character_image_upload($file_input_name, $current_image_path, $delete_flag_name, $is_editing_action_flag, $is_contributor_upload_flag) {
    global $message, $message_type;
    $image_path_to_return = $current_image_path;
    $base_upload_path = UPLOADS_PATH;
    $target_subdir = 'characters'; // Sottocartella specifica
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
                $message .= " Errore: Impossibile creare la cartella di upload per personaggi: " . htmlspecialchars($upload_dir_absolute) . ".";
                $message_type = 'error';
                return $image_path_to_return;
            }
        }
        
        $original_filename = $_FILES[$file_input_name]['name'];
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_extension, $allowed_extensions)) {
            $message .= " Formato file non permesso per l'immagine personaggio.";
            $message_type = 'error';
            return $image_path_to_return;
        }

        $temp_filename_no_ext = 'character_' . uniqid('', true) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
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
                 error_log("Compressione fallita per $file_input_name (personaggio), usando $image_path_to_return");
            }
        } else {
            $message .= " Errore durante il caricamento dell'immagine personaggio.";
            $message_type = 'error';
        }
    }
    return $image_path_to_return;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_character']) || isset($_POST['edit_character']))) {
    $is_editing_form_action = isset($_POST['edit_character']);
    $character_id_original_from_post = $is_editing_form_action ? (int)$_POST['character_id'] : null;

    $name_proposal = trim($_POST['name']);
    $description_proposal = trim($_POST['description']);
    $first_app_comic_id_proposal = !empty($_POST['first_appearance_comic_id']) ? (int)$_POST['first_appearance_comic_id'] : null;
    $first_app_story_id_proposal = !empty($_POST['first_appearance_story_id']) ? (int)$_POST['first_appearance_story_id'] : null;
    $first_app_notes_proposal = trim($_POST['first_appearance_notes']);
    $is_verified_proposal = isset($_POST['is_first_appearance_verified']) ? 1 : 0;

    $first_app_date_to_store = null;
    if($first_app_comic_id_proposal){
        $stmt_date_calc = $mysqli->prepare("SELECT publication_date FROM comics WHERE comic_id = ?");
        $stmt_date_calc->bind_param("i", $first_app_comic_id_proposal);
        $stmt_date_calc->execute();
        $res_date_calc = $stmt_date_calc->get_result();
        if($row_date_calc = $res_date_calc->fetch_assoc()){
            $first_app_date_to_store = $row_date_calc['publication_date'];
        }
        $stmt_date_calc->close();
    }

    $current_image_path_on_form = $_POST['current_image_path'] ?? null;
    $character_image_path_proposal = $is_editing_form_action ? $current_image_path_on_form : null;

    if (empty($name_proposal)) {
        $message = "Il nome del personaggio è obbligatorio.";
        $message_type = 'error';
    } else {
        $character_image_path_proposal = handle_character_image_upload('character_image', $character_image_path_proposal, 'delete_image', $is_editing_form_action, $is_contributor && !$is_true_admin);

        if ($message_type !== 'error') {
            if ($is_contributor && !$is_true_admin) {
                // SALVA PROPOSTA PER CONTRIBUTORE
                $action_type_proposal = $is_editing_form_action ? 'edit' : 'add';
                 $stmt_pending = $mysqli->prepare("INSERT INTO pending_characters
                    (character_id_original, proposer_user_id, action_type, name_proposal, description_proposal, character_image_proposal,
                     first_appearance_comic_id_proposal, first_appearance_story_id_proposal, first_appearance_notes_proposal, is_first_appearance_verified_proposal)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_pending->bind_param("iisssssiis",
                    $character_id_original_from_post, $current_user_id_for_proposal, $action_type_proposal,
                    $name_proposal, $description_proposal, $character_image_path_proposal,
                    $first_app_comic_id_proposal, $first_app_story_id_proposal, $first_app_notes_proposal, $is_verified_proposal
                );
                if ($stmt_pending->execute()) {
                    $_SESSION['message'] = "Proposta per il personaggio inviata con successo per revisione!";
                    $_SESSION['message_type'] = 'success';
                } else {
                     if ($mysqli->errno === 1062) {
                        $_SESSION['message'] = "Errore: Esiste già una proposta pendente o un personaggio con questo nome.";
                     } else {
                        $_SESSION['message'] = "Errore SQL invio proposta personaggio: " . $stmt_pending->error;
                     }
                    $_SESSION['message_type'] = 'error';
                }
                $stmt_pending->close();
                header('Location: ' . BASE_URL . 'admin/characters_manage.php?action=list');
                exit;

            } else { // GESTIONE ADMIN
                
                // --- INIZIO BLOCCO MODIFICATO ---
                $exclude_id_for_slug = $is_editing_form_action ? $character_id_original_from_post : 0;
                $slug = generate_slug($name_proposal, $mysqli, 'characters', 'slug', $exclude_id_for_slug, 'character_id');
                // --- FINE BLOCCO MODIFICATO ---

                if (isset($_POST['add_character'])) {
                    $stmt = $mysqli->prepare("INSERT INTO characters (name, slug, description, character_image, first_appearance_comic_id, first_appearance_story_id, first_appearance_date, first_appearance_notes, is_first_appearance_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssiissi", $name_proposal, $slug, $description_proposal, $character_image_path_proposal, $first_app_comic_id_proposal, $first_app_story_id_proposal, $first_app_date_to_store, $first_app_notes_proposal, $is_verified_proposal);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Personaggio aggiunto con successo! Pronto per inserirne un altro.";
                        $_SESSION['message_type'] = 'success';
                        header('Location: ' . BASE_URL . 'admin/characters_manage.php?action=add'); exit;
                    } else {
                        $message = ($mysqli->errno == 1062) ? "Errore: Esiste già un personaggio con questo nome o slug." : "Errore SQL: " . $stmt->error;
                        $message_type = 'error';
                        if ($character_image_path_proposal && $character_image_path_proposal !== $current_image_path_on_form) {
                            if(file_exists(UPLOADS_PATH . $character_image_path_proposal)) unlink(UPLOADS_PATH . $character_image_path_proposal);
                        }
                    }
                    $stmt->close();
                } elseif (isset($_POST['edit_character']) && isset($_POST['character_id'])) {
                    $character_id_update = (int)$_POST['character_id'];
                    $stmt = $mysqli->prepare("UPDATE characters SET name = ?, slug = ?, description = ?, character_image = ?, first_appearance_comic_id = ?, first_appearance_story_id = ?, first_appearance_date = ?, first_appearance_notes = ?, is_first_appearance_verified = ? WHERE character_id = ?");
                    $stmt->bind_param("ssssiissii", $name_proposal, $slug, $description_proposal, $character_image_path_proposal, $first_app_comic_id_proposal, $first_app_story_id_proposal, $first_app_date_to_store, $first_app_notes_proposal, $is_verified_proposal, $character_id_update);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Personaggio modificato!"; $_SESSION['message_type'] = 'success';
                         header('Location: ' . BASE_URL . 'admin/characters_manage.php?action=edit&id=' . $character_id_update . '&message=' . urlencode($_SESSION['message']) . '&message_type=' . $_SESSION['message_type']); exit;
                    } else {
                        $message = ($mysqli->errno == 1062) ? "Errore: Esiste già un personaggio con questo nome o slug." : "Errore SQL: " . $stmt->error;
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
            }
        }
    }
    // Ripopolamento dati form in caso di errore
    $character_data['name'] = $name_proposal;
    $character_data['description'] = $description_proposal;
    $character_data['character_image'] = $character_image_path_proposal;
    $character_data['first_appearance_comic_id'] = $first_app_comic_id_proposal;
    $character_data['first_appearance_story_id'] = $first_app_story_id_proposal;
    $character_data['first_appearance_notes'] = $first_app_notes_proposal;
    $character_data['is_first_appearance_verified'] = $is_verified_proposal;
    if (isset($_POST['edit_character']) && isset($_POST['character_id'])) $character_id_to_edit_original = (int)$_POST['character_id'];
    $action = isset($_POST['add_character']) ? 'add' : (isset($_POST['edit_character']) ? 'edit' : 'list');
}


if ($action === 'delete' && isset($_GET['id'])) {
    if (!$is_true_admin) {
        $_SESSION['message'] = "Azione non permessa."; $_SESSION['message_type'] = 'error';
        header('Location: characters_manage.php?action=list'); exit;
    }

    $character_id_to_delete = (int)$_GET['id'];
    $stmt_img = $mysqli->prepare("SELECT character_image FROM characters WHERE character_id = ?");
    $stmt_img->bind_param("i", $character_id_to_delete); $stmt_img->execute(); $result_img = $stmt_img->get_result();
    $image_to_delete = null; if ($row_img = $result_img->fetch_assoc()) $image_to_delete = $row_img['character_image'];
    $stmt_img->close();

    $stmt = $mysqli->prepare("DELETE FROM characters WHERE character_id = ?");
    $stmt->bind_param("i", $character_id_to_delete);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            if ($image_to_delete && file_exists(UPLOADS_PATH . $image_to_delete)) unlink(UPLOADS_PATH . $image_to_delete);
            $_SESSION['message'] = "Personaggio eliminato!"; $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Personaggio non trovato."; $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = "Errore eliminazione: " . $stmt->error . ". Potrebbe essere collegato a delle storie.";
        $_SESSION['message_type'] = 'error';
    }
    $stmt->close();
    header('Location: characters_manage.php?action=list'); exit;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']); unset($_SESSION['message_type']);
} elseif (isset($_GET['message'])) {
     $message = htmlspecialchars(urldecode($_GET['message']));
     $message_type = isset($_GET['message_type']) ? htmlspecialchars($_GET['message_type']) : 'info';
}
?>

<div class="container admin-container">
    <h2><?php echo $page_title; ?> <?php if ($is_contributor && !$is_true_admin) echo "(Modalità Contributore)"; ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="?action=add" class="btn btn-primary">
                <?php echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuovo Personaggio' : 'Aggiungi Nuovo Personaggio'; ?>
            </a>

            <form action="characters_manage.php" method="GET" class="form-inline" style="display: flex; gap: 5px;">
                <input type="hidden" name="action" value="list">
                <input type="text" name="search" class="form-control" placeholder="Cerca per nome o descrizione..." value="<?php echo htmlspecialchars($search_term); ?>" style="min-width: 250px;">
                <button type="submit" class="btn btn-info">Cerca</button>
                <?php if (!empty($search_term)): ?>
                    <a href="characters_manage.php?action=list" class="btn btn-secondary">Resetta</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($search_term)): ?>
            <p>Risultati della ricerca per: <strong>"<?php echo htmlspecialchars($search_term); ?>"</strong></p>
        <?php endif; ?>

        <h3>Elenco Personaggi</h3>
        <?php
        $sql_list = "SELECT character_id, name, description, character_image, first_appearance_comic_id FROM characters";
        $params_list = [];
        $types_list = "";

        if (!empty($search_term)) {
            $search_like = "%" . $search_term . "%";
            $sql_list .= " WHERE (name LIKE ? OR description LIKE ?)";
            $params_list[] = $search_like;
            $params_list[] = $search_like;
            $types_list .= "ss";
        }
        $sql_list .= " ORDER BY name ASC";

        $stmt_list = $mysqli->prepare($sql_list);
        if (!empty($params_list)) {
            $stmt_list->bind_param($types_list, ...$params_list);
        }
        $stmt_list->execute();
        $result_list = $stmt_list->get_result();

        if ($result_list && $result_list->num_rows > 0):
        ?>
            <table class="table">
                <thead><tr><th>Immagine</th><th>Nome</th><th>Descrizione (inizio)</th><th>1a App.</th><th>Azioni</th></tr></thead>
                <tbody>
                    <?php while ($row = $result_list->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if ($row['character_image']): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($row['character_image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 50px; height: auto; border-radius:3px;">
                            <?php elseif (function_exists('generate_image_placeholder')): ?>
                                <?php echo generate_image_placeholder(htmlspecialchars($row['name']), 50, 50, 'admin-table-placeholder'); ?>
                            <?php else: echo "N/A"; endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 70)); ?>...</td>
                        <td>
                            <?php
                            if($row['first_appearance_comic_id']){
                                $stmt_fc = $mysqli->prepare("SELECT issue_number FROM comics WHERE comic_id = ?");
                                $stmt_fc->bind_param("i", $row['first_appearance_comic_id']);
                                $stmt_fc->execute();
                                $res_fc = $stmt_fc->get_result();
                                if($fc_row = $res_fc->fetch_assoc()){
                                    echo "<a href='comics_manage.php?action=edit&id=".$row['first_appearance_comic_id']."' title='Vai all&#39;albo #".$fc_row['issue_number']."'>#".htmlspecialchars($fc_row['issue_number'])."</a>";
                                } else { echo "N/D"; }
                                $stmt_fc->close();
                            } else { echo "N/D"; }
                            ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <a href="?action=edit&id=<?php echo $row['character_id']; ?>" class="btn btn-sm btn-warning">
                                <?php echo ($is_contributor && !$is_true_admin) ? 'Prop. Mod.' : 'Modifica'; ?>
                            </a>
                            <a href="<?php echo BASE_URL; ?>character_detail.php?id=<?php echo $row['character_id']; ?>" class="btn btn-sm btn-info" target="_blank">Scheda</a>
                             <?php if ($is_true_admin): // Solo Admin può eliminare ?>
                            <a href="?action=delete&id=<?php echo $row['character_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo personaggio?');">Elimina</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nessun personaggio trovato <?php if(!empty($search_term)) echo "per la ricerca '".htmlspecialchars($search_term)."'"; ?>. <?php if(empty($search_term)): ?><a href="?action=add">Aggiungine uno!</a><?php endif; ?></p>
        <?php endif; if($result_list) $result_list->free(); $stmt_list->close(); ?>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <h3>
            <?php
            if ($action === 'add') echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuovo Personaggio' : 'Aggiungi Nuovo Personaggio';
            else echo ($is_contributor && !$is_true_admin) ? 'Proponi Modifiche Personaggio' : 'Modifica Personaggio';
            ?>
        </h3>
        <?php if ($is_contributor && !$is_true_admin): ?>
            <p class="message info" style="font-size:0.9em;">
                <strong>Nota per i Contributori:</strong> Le tue proposte verranno inviate per approvazione. Le immagini caricate saranno temporanee.
            </p>
        <?php endif; ?>

        <form action="characters_manage.php<?php echo ($action === 'edit' && $character_id_to_edit_original) ? '?action=edit&id='.$character_id_to_edit_original : ''; ?>" method="POST" enctype="multipart/form-data">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="edit_character" value="1">
                <input type="hidden" name="character_id" value="<?php echo $character_id_to_edit_original; ?>">
                <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($character_data['character_image'] ?? ''); ?>">
            <?php else: ?>
                 <input type="hidden" name="add_character" value="1">
            <?php endif; ?>

            <div class="form-group">
                <label for="name">Nome Personaggio:</label>
                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($character_data['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Descrizione:</label>
                <textarea id="description" name="description" class="form-control" rows="5"><?php echo htmlspecialchars($character_data['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="character_image">Immagine Personaggio:</label>
                <input type="file" id="character_image" name="character_image" class="form-control-file">
                <?php if ($action === 'edit' && $character_data['character_image']): ?>
                    <p style="margin-top:10px;">Immagine attuale: <img src="<?php echo UPLOADS_URL . htmlspecialchars($character_data['character_image']); ?>" alt="Immagine attuale" style="max-width: 100px; height: auto; margin-top: 5px; border:1px solid #ccc; padding:2px;"></p>
                    <label class="inline-label"><input type="checkbox" name="delete_image" value="1"> Cancella immagine attuale</label>
                <?php endif; ?>
            </div>

            <hr>
            <h4>Prima Apparizione</h4>
            <div class="form-group">
                <label for="first_appearance_comic_id">Albo della Prima Apparizione (opzionale):</label>
                <select name="first_appearance_comic_id" id="first_appearance_comic_id" class="form-control">
                    <option value="">-- Seleziona Albo --</option>
                    <?php foreach($all_comics_for_select as $comic_opt): ?>
                        <option value="<?php echo $comic_opt['comic_id']; ?>" <?php echo (isset($character_data['first_appearance_comic_id']) && $character_data['first_appearance_comic_id'] == $comic_opt['comic_id']) ? 'selected' : ''; ?>>
                            #<?php echo htmlspecialchars($comic_opt['issue_number']); ?> <?php echo !empty($comic_opt['title']) ? ' - ' . htmlspecialchars($comic_opt['title']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="first_appearance_story_id">Storia della Prima Apparizione (opzionale):</label>
                <select name="first_appearance_story_id" id="first_appearance_story_id" class="form-control" <?php if(empty($stories_for_selected_comic) && empty($character_data['first_appearance_comic_id'])) echo 'disabled';?>>
                    <option value="">-- Seleziona Storia (dopo aver scelto l'albo) --</option>
                    <?php foreach($stories_for_selected_comic as $story_opt): ?>
                        <option value="<?php echo $story_opt['story_id']; ?>" <?php echo (isset($character_data['first_appearance_story_id']) && $character_data['first_appearance_story_id'] == $story_opt['story_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($story_opt['title']); ?> (Ord. <?php echo $story_opt['sequence_in_comic'] > 0 ? $story_opt['sequence_in_comic'] : 'auto'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                 <small>La data di prima apparizione verrà recuperata automaticamente dall'albo. La lista storie si aggiorna via AJAX o al salvataggio.</small>
            </div>
             <div class="form-group">
                <label for="first_appearance_notes">Note sulla Prima Apparizione (opzionale):</label>
                <textarea name="first_appearance_notes" id="first_appearance_notes" class="form-control" rows="2"><?php echo htmlspecialchars($character_data['first_appearance_notes'] ?? ''); ?></textarea>
                <small>Es. "Prima apparizione nel Topolino libretto", "Cameo".</small>
            </div>
            <div class="form-group">
                <input type="checkbox" name="is_first_appearance_verified" id="is_first_appearance_verified" value="1" <?php echo (isset($character_data['is_first_appearance_verified']) && $character_data['is_first_appearance_verified'] == 1) ? 'checked' : ''; ?>>
                <label for="is_first_appearance_verified" class="inline-label">Prima apparizione verificata manualmente</label>
            </div>

            <div class="form-group" style="margin-top:20px;">
                <button type="submit" class="btn btn-success">
                     <?php
                        if ($action === 'add') echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Personaggio' : 'Aggiungi Personaggio';
                        else echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Modifiche' : 'Salva Modifiche';
                    ?>
                </button>
                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                <?php if ($action === 'edit' && $character_id_to_edit_original): ?>
                    <a href="<?php echo BASE_URL; ?>character_detail.php?id=<?php echo $character_id_to_edit_original; ?>" class="btn btn-info" target="_blank" style="margin-left: 10px;">Visualizza Scheda</a>
                <?php endif; ?>
            </div>
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const comicSelect = document.getElementById('first_appearance_comic_id');
                const storySelect = document.getElementById('first_appearance_story_id');

                if (comicSelect && storySelect) {
                    function populateStories(selectedComicId, currentStoryId) {
                        storySelect.innerHTML = '<option value="">-- Caricamento storie... --</option>';
                        storySelect.disabled = true;

                        if (selectedComicId) {
                            fetch('<?php echo BASE_URL; ?>admin/ajax_get_stories.php?comic_id=' + selectedComicId)
                                .then(response => response.json())
                                .then(data => {
                                    storySelect.innerHTML = '<option value="">-- Seleziona Storia --</option>';
                                    if (data.success && data.stories.length > 0) {
                                        data.stories.forEach(story => {
                                            const option = document.createElement('option');
                                            option.value = story.story_id;
                                            let storyTitleText = story.title;
                                            if (story.sequence_in_comic > 0) {
                                                storyTitleText += ' (Ord. ' + story.sequence_in_comic + ')';
                                            } else {
                                                storyTitleText += ' (Ord. auto)';
                                            }
                                            option.textContent = storyTitleText;
                                            if (story.story_id == currentStoryId) {
                                                option.selected = true;
                                            }
                                            storySelect.appendChild(option);
                                        });
                                        storySelect.disabled = false;
                                    } else if (data.success && data.stories.length === 0) {
                                        storySelect.innerHTML = '<option value="">-- Nessuna storia per questo albo --</option>';
                                    } else {
                                        storySelect.innerHTML = '<option value="">-- Errore caricamento storie --</option>';
                                        console.error('Errore AJAX:', data.message || 'Errore sconosciuto');
                                    }
                                })
                                .catch(error => {
                                    storySelect.innerHTML = '<option value="">-- Errore richiesta storie --</option>';
                                    console.error('Errore fetch:', error);
                                });
                        } else {
                             storySelect.innerHTML = '<option value="">-- Seleziona Storia (dopo aver scelto l&#39;albo) --</option>';
                             storySelect.disabled = true;
                        }
                    }

                    comicSelect.addEventListener('change', function() {
                        populateStories(this.value, null);
                    });

                    const initialComicId = <?php echo json_encode($character_data['first_appearance_comic_id'] ?? null); ?>;
                    const initialStoryId = <?php echo json_encode($character_data['first_appearance_story_id'] ?? null); ?>;

                    if (initialComicId && storySelect.options.length <= 1 && <?php echo json_encode(empty($stories_for_selected_comic)); ?>) {
                        populateStories(initialComicId, initialStoryId);
                    } else if (initialComicId && storySelect.options.length > 1){
                         storySelect.disabled = false;
                         if(initialStoryId) storySelect.value = initialStoryId;
                    }
                }
            });
        </script>
    <?php endif; ?>

</div><?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
$mysqli->close();
?>