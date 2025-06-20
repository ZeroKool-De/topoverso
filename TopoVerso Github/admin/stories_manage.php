<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';

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

$message = '';
$message_type = '';
$action = $_GET['action'] ?? 'list';

$url_comic_id = isset($_GET['comic_id']) ? filter_var($_GET['comic_id'], FILTER_VALIDATE_INT) : false;
$comic_id_context = 0; 
$comic_info = null;
$source_comic_id_for_breadcrumb = 0; 

if ($url_comic_id) {
    $comic_id_context = (int)$url_comic_id;
    $source_comic_id_for_breadcrumb = $comic_id_context;

    $stmt_comic_check = $mysqli->prepare("SELECT issue_number, title FROM comics WHERE comic_id = ?");
    $stmt_comic_check->bind_param("i", $comic_id_context);
    $stmt_comic_check->execute();
    $result_comic_check = $stmt_comic_check->get_result();
    if ($result_comic_check->num_rows === 0) {
        if ($action !== 'duplicate_to_other_comic') { //
            $_SESSION['message'] = "Il fumetto con ID " . $comic_id_context . " non è stato trovato."; //
            $_SESSION['message_type'] = 'error'; //
            header('Location: ' . BASE_URL . 'admin/comics_manage.php'); //
            exit; //
        }
    } else {
        $comic_info = $result_comic_check->fetch_assoc(); //
    }
    $stmt_comic_check->close();

} elseif ($action !== 'duplicate_to_other_comic' && $action !== 'add_story' && $action !== 'list') { //
     if (!(isset($_GET['message']) && isset($_GET['message_type']))) {  //
        $_SESSION['message'] = 'ID fumetto non valido o mancante per questa operazione.'; //
        $_SESSION['message_type'] = 'error'; //
        header('Location: ' . BASE_URL . 'admin/comics_manage.php'); //
        exit; //
     }
}


if ($comic_info) {
    $page_title_base = "Gestione Storie per Topolino #" . htmlspecialchars($comic_info['issue_number']) . (empty($comic_info['title']) ? '' : ' - ' . htmlspecialchars($comic_info['title'])); //
} else if ($action === 'duplicate_to_other_comic' && $url_comic_id) { //
    $stmt_src_comic_info = $mysqli->prepare("SELECT issue_number, title FROM comics WHERE comic_id = ?"); //
    $stmt_src_comic_info->bind_param("i", $url_comic_id); //
    $stmt_src_comic_info->execute(); //
    $res_src_info = $stmt_src_comic_info->get_result(); //
    if ($src_info_row = $res_src_info->fetch_assoc()) { //
         $page_title_base = "Duplica Storia da Topolino #" . htmlspecialchars($src_info_row['issue_number']) . (empty($src_info_row['title']) ? '' : ' - ' . htmlspecialchars($src_info_row['title'])); //
         $source_comic_id_for_breadcrumb = (int)$url_comic_id;  //
    } else {
        $page_title_base = "Duplica Storia su Altro Albo"; //
        $source_comic_id_for_breadcrumb = 0;  //
    }
    $stmt_src_comic_info->close(); //
} else {
    $page_title_base = "Gestione Storie"; //
}
$page_title = $page_title_base; //
$page_title_suffix = ''; //

require_once ROOT_PATH . 'admin/includes/header_admin.php';

$persons = [];
$result_persons = $mysqli->query("SELECT person_id, name FROM persons ORDER BY name ASC");
if ($result_persons) { while ($row = $result_persons->fetch_assoc()) { $persons[] = $row; } $result_persons->free(); }

$characters = [];
$result_characters = $mysqli->query("SELECT character_id, name, character_image FROM characters ORDER BY name ASC");
if ($result_characters) { while ($row = $result_characters->fetch_assoc()) { $characters[] = $row; } $result_characters->free(); }

$story_series_list = [];
$result_series = $mysqli->query("SELECT series_id, title FROM story_series ORDER BY title ASC");
if ($result_series) { while ($row = $result_series->fetch_assoc()) { $story_series_list[] = $row; } $result_series->free(); }

$main_sagas_for_dropdown = [];
$result_main_sagas = $mysqli->query("SELECT story_id, story_title_main FROM stories WHERE story_group_id = story_id AND story_title_main IS NOT NULL AND story_title_main != '' ORDER BY story_title_main ASC");
if ($result_main_sagas) { while ($row_saga = $result_main_sagas->fetch_assoc()) { $main_sagas_for_dropdown[] = $row_saga; } $result_main_sagas->free(); }

$all_comics_for_select_target = [];
$all_comics_list_result_dropdown = $mysqli->query("SELECT comic_id, issue_number, title FROM comics ORDER BY CAST(REPLACE(REPLACE(issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC, publication_date DESC");
if ($all_comics_list_result_dropdown) {
    while ($comic_row_sel = $all_comics_list_result_dropdown->fetch_assoc()) {
        $all_comics_for_select_target[] = $comic_row_sel;
    }
    $all_comics_list_result_dropdown->free();
}

$story_id_to_edit_original = null; // ID della storia originale se si sta modificando
$story_data = [
    'title' => '', 'first_page_image' => null, 'sequence_in_comic' => 0, 'notes' => '',
    'is_ministory' => 0, // Aggiunto nuovo campo con valore di default
    'series_id' => null, 'series_episode_number' => null, 'story_persons' => [], 'story_characters' => [],
    'story_title_main' => '', 'part_number' => '', 'total_parts' => null, 'story_group_id' => null,
    'is_new_saga_starter' => false
];
$is_duplicating_to_other_comic_flag = false;

if ($action === 'edit_story' && isset($_GET['story_id']) && $comic_id_context > 0) {
    $story_id_to_edit_original = (int)$_GET['story_id'];
    $stmt_story = $mysqli->prepare("SELECT story_id, title, first_page_image, sequence_in_comic, notes, is_ministory, series_id, series_episode_number, story_title_main, part_number, total_parts, story_group_id FROM stories WHERE story_id = ? AND comic_id = ?");
    $stmt_story->bind_param("ii", $story_id_to_edit_original, $comic_id_context);
    $stmt_story->execute();
    $result_story_edit = $stmt_story->get_result();
    if ($result_story_edit->num_rows === 1) {
        $fetched_story_data = $result_story_edit->fetch_assoc();
        $story_data = array_merge($story_data, $fetched_story_data);
        if ($story_data['story_id'] == $story_data['story_group_id'] && !empty($story_data['story_title_main'])) {
            $story_data['is_new_saga_starter'] = true;
        }
        $stmt_sp = $mysqli->prepare("SELECT person_id, role FROM story_persons WHERE story_id = ?"); $stmt_sp->bind_param("i", $story_id_to_edit_original); $stmt_sp->execute(); $result_sp = $stmt_sp->get_result(); while($sp_row = $result_sp->fetch_assoc()) $story_data['story_persons'][] = ['person_id' => $sp_row['person_id'], 'role' => $sp_row['role']]; $stmt_sp->close();
        $stmt_sc = $mysqli->prepare("SELECT character_id FROM story_characters WHERE story_id = ?"); $stmt_sc->bind_param("i", $story_id_to_edit_original); $stmt_sc->execute(); $result_sc = $stmt_sc->get_result(); while($sc_row = $result_sc->fetch_assoc()) $story_data['story_characters'][] = $sc_row['character_id']; $stmt_sc->close();
    } else {
        $_SESSION['message'] = "Storia non trovata o non appartenente a questo fumetto."; $_SESSION['message_type'] = 'error';
        header('Location: ' . BASE_URL . 'admin/stories_manage.php?comic_id=' . $comic_id_context); exit;
    }
    $stmt_story->close();
} elseif (($action === 'duplicate_story' || $action === 'duplicate_to_other_comic') && isset($_GET['source_story_id'])) { 
    $source_story_id = (int)$_GET['source_story_id']; 
    $query_source_comic_id = $comic_id_context;  

    if ($query_source_comic_id <= 0){  
        $_SESSION['message'] = "ID fumetto sorgente non specificato per la duplicazione."; $_SESSION['message_type'] = 'error';
        header('Location: ' . BASE_URL . 'admin/comics_manage.php'); exit; 
    }
    
    $stmt_source_story = $mysqli->prepare("SELECT * FROM stories WHERE story_id = ? AND comic_id = ?"); 
    $stmt_source_story->bind_param("ii", $source_story_id, $query_source_comic_id); 
    $stmt_source_story->execute(); 
    $result_source_story = $stmt_source_story->get_result(); 

    if ($result_source_story->num_rows === 1) { 
        $source_data = $result_source_story->fetch_assoc(); 
        $original_title_for_page_display = $source_data['title']; 
        $story_data = array_merge($story_data, $source_data); 

        $story_data['first_page_image'] = null; $story_data['story_id'] = null; $story_data['sequence_in_comic'] = 0; 

        if ($action === 'duplicate_story') { 
            $story_data['title'] = htmlspecialchars($source_data['title']) . " (Copia)"; 
            if (!empty($story_data['part_number']) && is_numeric($story_data['part_number'])) { 
                $story_data['part_number'] = (int)$story_data['part_number'] + 1; 
            } elseif (!empty($story_data['part_number'])) { $story_data['part_number'] .= " (Nuova Parte)"; } 
            $story_data['is_new_saga_starter'] = false; 
            $page_title_suffix = " - Duplica da: " . htmlspecialchars($original_title_for_page_display); 
        } else { 
            $is_duplicating_to_other_comic_flag = true; 
            $story_data['title'] = htmlspecialchars($source_data['title']); 
            if ($source_data['story_id'] == $source_data['story_group_id'] && !empty($source_data['story_title_main'])) { 
                $story_data['is_new_saga_starter'] = true; 
            } else { $story_data['is_new_saga_starter'] = false; } 
            $page_title_suffix = " - Duplica Storia su Altro Albo (da: " . htmlspecialchars($original_title_for_page_display) . ")"; 
        }
        $page_title = $page_title_base . $page_title_suffix; 

        $stmt_sp_source = $mysqli->prepare("SELECT person_id, role FROM story_persons WHERE story_id = ?"); $stmt_sp_source->bind_param("i", $source_story_id); $stmt_sp_source->execute(); $result_sp_source = $stmt_sp_source->get_result(); while($sp_row_source = $result_sp_source->fetch_assoc()) $story_data['story_persons'][] = ['person_id' => $sp_row_source['person_id'], 'role' => $sp_row_source['role']]; $stmt_sp_source->close(); 
        $stmt_sc_source = $mysqli->prepare("SELECT character_id FROM story_characters WHERE story_id = ?"); $stmt_sc_source->bind_param("i", $source_story_id); $stmt_sc_source->execute(); $result_sc_source = $stmt_sc_source->get_result(); while($sc_row_source = $result_sc_source->fetch_assoc()) $story_data['story_characters'][] = $sc_row_source['character_id']; $stmt_sc_source->close(); 
        $action = 'add_story'; 
    } else {
        $_SESSION['message'] = "Storia sorgente (ID: $source_story_id) non trovata nel fumetto specificato (ID: $query_source_comic_id)."; 
        $_SESSION['message_type'] = 'error'; 
        header('Location: ' . BASE_URL . 'admin/stories_manage.php?comic_id=' . $query_source_comic_id); exit; 
    }
    $stmt_source_story->close(); 
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_story']) || isset($_POST['edit_story']))) {
    $is_editing_form_action = isset($_POST['edit_story']); 
    $story_id_original_from_post = $is_editing_form_action ? (int)$_POST['story_id'] : null;

    $story_title = trim($_POST['story_title']);
    $sequence = isset($_POST['sequence_in_comic']) && $_POST['sequence_in_comic'] !== '' ? (int)$_POST['sequence_in_comic'] : 0;
    if ($sequence < 0) $sequence = 0;
    $notes = trim($_POST['story_notes']);
    $is_ministory_post = isset($_POST['is_ministory']) ? 1 : 0; // Recupera il valore del nuovo checkbox
    $series_id_post = !empty($_POST['series_id']) ? (int)$_POST['series_id'] : null;
    $series_episode_number_post = !empty($_POST['series_episode_number']) ? (int)$_POST['series_episode_number'] : null;
    $story_title_main_post = isset($_POST['story_title_main']) ? trim($_POST['story_title_main']) : null;
    $part_number_post = isset($_POST['part_number']) ? trim($_POST['part_number']) : null;
    $total_parts_post = isset($_POST['total_parts']) && $_POST['total_parts'] !== '' ? (int)$_POST['total_parts'] : null;
    $selected_story_group_id = isset($_POST['story_group_id_select']) && $_POST['story_group_id_select'] !== '' ? (int)$_POST['story_group_id_select'] : null;
    $is_new_saga_starter_post = isset($_POST['is_new_saga_starter']) ? true : false;
    
    $story_person_ids = $_POST['story_person_ids'] ?? [];
    $story_person_roles = $_POST['story_person_roles'] ?? [];
    $story_character_ids = $_POST['story_character_ids'] ?? [];

    $authors_proposal_json = null;
    if (!empty($story_person_ids)) {
        $temp_authors = [];
        for ($i = 0; $i < count($story_person_ids); $i++) {
            if (!empty($story_person_ids[$i]) && !empty($story_person_roles[$i])) {
                $temp_authors[] = ['person_id' => (int)$story_person_ids[$i], 'role' => trim($story_person_roles[$i])];
            }
        }
        if (!empty($temp_authors)) $authors_proposal_json = json_encode($temp_authors);
    }
    $characters_proposal_json = null;
    if (!empty($story_character_ids)) {
        $temp_chars = [];
        foreach ($story_character_ids as $char_id) {
            if (!empty($char_id)) $temp_chars[] = (int)$char_id;
        }
        if (!empty($temp_chars)) $characters_proposal_json = json_encode($temp_chars);
    }


    $comic_id_for_operation = $comic_id_context; 
    $target_comic_id_from_form = 0; 

    if (isset($_POST['add_story']) && isset($_POST['is_duplicating_to_other_comic_form']) && $_POST['is_duplicating_to_other_comic_form'] == '1') {
        if (empty($_POST['target_comic_id'])) {
            $message = "È necessario selezionare un fumetto di destinazione."; $message_type = 'error';
        } else {
            $target_comic_id_from_form = (int)$_POST['target_comic_id'];
            $stmt_target_check = $mysqli->prepare("SELECT comic_id FROM comics WHERE comic_id = ?");
            $stmt_target_check->bind_param("i", $target_comic_id_from_form); $stmt_target_check->execute();
            if ($stmt_target_check->get_result()->num_rows === 0) {
                $message = "Il fumetto di destinazione selezionato non è valido."; $message_type = 'error';
            } else { $comic_id_for_operation = $target_comic_id_from_form; }
            $stmt_target_check->close();
        }
    }
    
    $final_story_group_id = null;
    if ($is_new_saga_starter_post && !empty($story_title_main_post)) { $final_story_group_id = 0; }
    elseif ($selected_story_group_id) { $final_story_group_id = $selected_story_group_id; }
    elseif (!empty($story_title_main_post) && empty($selected_story_group_id) && !$is_new_saga_starter_post) { $final_story_group_id = 0; }

    $current_image_path_proposal = $_POST['current_first_page_image'] ?? ($story_data['first_page_image'] ?? null); 
    if (isset($_POST['add_story']) && !$is_editing_form_action) { 
        $current_image_path_proposal = null; 
    }
    
    function handle_story_image_upload($file_input_name, $current_image_path, $delete_flag_name, $is_editing_action_flag, $is_contributor_upload_flag) {
        global $message, $message_type; 
        $image_path_to_return = $current_image_path;
        $base_upload_path = UPLOADS_PATH;
        $target_subdir = 'first_pages'; 
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
                    $message .= " Errore: Impossibile creare la cartella di upload: " . htmlspecialchars($upload_dir_absolute). ".";
                    $message_type = 'error';
                    return $image_path_to_return;
                }
            }

            $original_filename = $_FILES[$file_input_name]['name'];
            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($file_extension, $allowed_extensions)) {
                $message .= " Formato file non permesso per l'immagine prima pagina.";
                $message_type = 'error';
                return $image_path_to_return;
            }

            
            $temp_filename_no_ext = uniqid('fp_', true) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
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
                    error_log("Compressione fallita per $file_input_name (storia), usando $image_path_to_return");
                }
            } else {
                $message .= " Errore upload immagine prima pagina.";
                $message_type = 'error';
            }
        }
        return $image_path_to_return;
    }

    $current_image_path_proposal = handle_story_image_upload('first_page_image', $current_image_path_proposal, 'delete_first_page_image', $is_editing_form_action, $is_contributor && !$is_true_admin);


    if (empty($story_title)) { $message = "Il titolo della storia è obbligatorio."; $message_type = 'error'; }
    if ($is_new_saga_starter_post && empty($story_title_main_post)) { $message = "Se questa è la prima parte di una nuova saga, il 'Titolo Principale Saga' è obbligatorio."; $message_type = 'error'; }
    if ($comic_id_for_operation <= 0 && isset($_POST['add_story']) && !($is_duplicating_to_other_comic_flag && $target_comic_id_from_form > 0)) { 
        $message = "ID fumetto per l'operazione non valido."; $message_type = 'error'; 
    }


    if ($message_type !== 'error') {
        $mysqli->begin_transaction();
        try {
            if ($is_contributor && !$is_true_admin) {
                // SALVA PROPOSTA PER CONTRIBUTORE
                $action_type_proposal_story = $is_editing_form_action ? 'edit' : 'add';
                $final_comic_id_for_proposal = ($is_duplicating_to_other_comic_flag && $target_comic_id_from_form > 0) ? $target_comic_id_from_form : $comic_id_context;

                $stmt_pending_story = $mysqli->prepare("INSERT INTO pending_stories 
                    (story_id_original, comic_id_context, proposer_user_id, action_type, 
                     series_id_proposal, title_proposal, story_title_main_proposal, part_number_proposal, total_parts_proposal, 
                     story_group_id_proposal, first_page_image_proposal, sequence_in_comic_proposal, 
                     series_episode_number_proposal, notes_proposal, is_ministory_proposal, authors_proposal_json, characters_proposal_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt_pending_story->bind_param("iiisssssisisiisss",
                    $story_id_original_from_post, $final_comic_id_for_proposal, $current_user_id_for_proposal, $action_type_proposal_story,
                    $series_id_post, $story_title, $story_title_main_post, $part_number_post, $total_parts_post,
                    $final_story_group_id, $current_image_path_proposal, $sequence,
                    $series_episode_number_post, $notes, $is_ministory_post, $authors_proposal_json, $characters_proposal_json
                );

                if (!$stmt_pending_story->execute()) {
                    throw new Exception("Errore DB (insert pending_story): " . $stmt_pending_story->error);
                }
                $stmt_pending_story->close();
                $message = "Proposta storia inviata con successo per revisione!";
                $_SESSION['message'] = $message; $_SESSION['message_type'] = 'success';
                $mysqli->commit();
                $redirect_final_comic_id = $final_comic_id_for_proposal > 0 ? $final_comic_id_for_proposal : $source_comic_id_for_breadcrumb;
                header('Location: ' . BASE_URL . 'admin/stories_manage.php?comic_id=' . $redirect_final_comic_id . '&message=' . urlencode($message) . '&message_type=success');
                exit;

            } else { 
                $current_story_id_processed = null; 
                if (isset($_POST['add_story'])) {
                    $stmt_ins = $mysqli->prepare("INSERT INTO stories (comic_id, title, first_page_image, sequence_in_comic, notes, is_ministory, series_id, series_episode_number, story_title_main, part_number, total_parts, story_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_ins->bind_param("issisiissssi", $comic_id_for_operation, $story_title, $current_image_path_proposal, $sequence, $notes, $is_ministory_post, $series_id_post, $series_episode_number_post, $story_title_main_post, $part_number_post, $total_parts_post, $final_story_group_id);
                    if (!$stmt_ins->execute()) { throw new Exception("Errore DB (insert storia): " . $stmt_ins->error); }
                    $current_story_id_processed = $mysqli->insert_id; $stmt_ins->close(); $message = "Storia aggiunta con successo!";
                } elseif (isset($_POST['edit_story']) && isset($_POST['story_id'])) {
                    $current_story_id_processed = (int)$_POST['story_id'];
                    $stmt_upd = $mysqli->prepare("UPDATE stories SET title = ?, first_page_image = ?, sequence_in_comic = ?, notes = ?, is_ministory = ?, series_id = ?, series_episode_number = ?, story_title_main = ?, part_number = ?, total_parts = ?, story_group_id = ? WHERE story_id = ? AND comic_id = ?");
                    $stmt_upd->bind_param("ssisiisssiiii", $story_title, $current_image_path_proposal, $sequence, $notes, $is_ministory_post, $series_id_post, $series_episode_number_post, $story_title_main_post, $part_number_post, $total_parts_post, $final_story_group_id, $current_story_id_processed, $comic_id_context);
                    if (!$stmt_upd->execute()) { throw new Exception("Errore DB (update storia): " . $stmt_upd->error); }
                    $stmt_upd->close(); $message = "Storia modificata con successo!";
                    $mysqli->query("DELETE FROM story_persons WHERE story_id = $current_story_id_processed");
                    $mysqli->query("DELETE FROM story_characters WHERE story_id = $current_story_id_processed");
                } else { throw new Exception("Azione non valida per la storia."); }

                if ($final_story_group_id === 0 && $current_story_id_processed > 0) {
                    $stmt_update_group_id = $mysqli->prepare("UPDATE stories SET story_group_id = ? WHERE story_id = ?");
                    $stmt_update_group_id->bind_param("ii", $current_story_id_processed, $current_story_id_processed);
                    if (!$stmt_update_group_id->execute()) { throw new Exception("Errore DB (update story_group_id): " . $stmt_update_group_id->error); }
                    $stmt_update_group_id->close();
                }
                
                if ($current_story_id_processed) { 
                    if (!empty($authors_proposal_json)) {
                        $authors_data = json_decode($authors_proposal_json, true);
                        $stmt_sp_add = $mysqli->prepare("INSERT INTO story_persons (story_id, person_id, role) VALUES (?, ?, ?)");
                        foreach($authors_data as $author_entry) {
                            $stmt_sp_add->bind_param("iis", $current_story_id_processed, $author_entry['person_id'], $author_entry['role']);
                            if (!$stmt_sp_add->execute()) throw new Exception("Errore DB (insert autori storia): " . $stmt_sp_add->error);
                        }
                        $stmt_sp_add->close();
                    }
                    if (!empty($characters_proposal_json)) {
                        $characters_data = json_decode($characters_proposal_json, true);
                        $stmt_sc_add = $mysqli->prepare("INSERT INTO story_characters (story_id, character_id) VALUES (?, ?)");
                        foreach ($characters_data as $char_id_int) {
                            $stmt_sc_add->bind_param("ii", $current_story_id_processed, $char_id_int);
                            if (!$stmt_sc_add->execute()) throw new Exception("Errore DB (insert personaggi storia): " . $stmt_sc_add->error);
                        }
                        $stmt_sc_add->close();
                    }
                }
                $mysqli->commit(); $message_type = 'success';
                $redirect_final_comic_id = $comic_id_for_operation > 0 ? $comic_id_for_operation : ($source_comic_id_for_breadcrumb > 0 ? $source_comic_id_for_breadcrumb : '');
                header('Location: ' . BASE_URL . 'admin/stories_manage.php?comic_id=' . $redirect_final_comic_id . '&message=' . urlencode($message) . '&message_type=' . $message_type); exit;
            }
        } catch (Exception $e) {
            $mysqli->rollback(); $message = "Errore operazioni storia: " . $e->getMessage(); $message_type = 'error';
        }
    }
    // Ripopolamento dati form in caso di errore
    $story_data['title'] = $story_title; $story_data['sequence_in_comic'] = $sequence; $story_data['notes'] = $notes;
    $story_data['is_ministory'] = $is_ministory_post; // Aggiunto per ripopolamento
    $story_data['first_page_image'] = $current_image_path_proposal; $story_data['series_id'] = $series_id_post;
    $story_data['series_episode_number'] = $series_episode_number_post; $story_data['story_title_main'] = $story_title_main_post;
    $story_data['part_number'] = $part_number_post; $story_data['total_parts'] = $total_parts_post;
    $story_data['story_group_id'] = $selected_story_group_id; $story_data['is_new_saga_starter'] = $is_new_saga_starter_post;
    $story_data['story_persons'] = []; if (!empty($authors_proposal_json)) $story_data['story_persons'] = json_decode($authors_proposal_json, true);
    $story_data['story_characters'] = []; if (!empty($characters_proposal_json)) $story_data['story_characters'] = json_decode($characters_proposal_json, true);
    
    if (isset($_POST['is_duplicating_to_other_comic_form']) && $_POST['is_duplicating_to_other_comic_form'] == '1') {
        $is_duplicating_to_other_comic_flag = true;
    }
}

if ($action === 'delete_story' && isset($_GET['story_id']) && $comic_id_context > 0) {
    if (!$is_true_admin) {
        $_SESSION['message'] = "Azione non permessa."; $_SESSION['message_type'] = 'error';
        header('Location: ' . BASE_URL . 'admin/stories_manage.php?comic_id=' . $comic_id_context); exit;
    }

    $story_id_to_delete = (int)$_GET['story_id'];
    $stmt_fp_img = $mysqli->prepare("SELECT first_page_image FROM stories WHERE story_id = ? AND comic_id = ?");
    $stmt_fp_img->bind_param("ii", $story_id_to_delete, $comic_id_context); $stmt_fp_img->execute(); $result_fp_img = $stmt_fp_img->get_result();
    $fp_image_to_delete = null; if($result_fp_img->num_rows === 1) $fp_image_to_delete = $result_fp_img->fetch_assoc()['first_page_image'];
    $stmt_fp_img->close();
    $stmt_del_story = $mysqli->prepare("DELETE FROM stories WHERE story_id = ? AND comic_id = ?");
    $stmt_del_story->bind_param("ii", $story_id_to_delete, $comic_id_context);
    if ($stmt_del_story->execute()) {
        if ($stmt_del_story->affected_rows > 0) { if ($fp_image_to_delete && file_exists(UPLOADS_PATH . $fp_image_to_delete)) unlink(UPLOADS_PATH . $fp_image_to_delete); $message = "Storia eliminata!"; $message_type = 'success'; }
        else { $message = "Nessuna storia trovata."; $message_type = 'error'; }
    } else { $message = "Errore eliminazione storia: " . $stmt_del_story->error; $message_type = 'error'; }
    $stmt_del_story->close();
    header('Location: ' . BASE_URL . 'admin/stories_manage.php?comic_id=' . $comic_id_context . '&message=' . urlencode($message) . '&message_type=' . $message_type); exit;
}

if (isset($_GET['message'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
    $message_type = isset($_GET['message_type']) ? htmlspecialchars($_GET['message_type']) : 'info';
}

$stories_list = [];
if ($comic_id_context > 0) {
    $stmt_stories_list = $mysqli->prepare("
        SELECT s.story_id, s.title, s.sequence_in_comic, s.first_page_image, s.series_id, s.series_episode_number, s.is_ministory,
               s.story_title_main, s.part_number, s.total_parts, s.story_group_id, ss.title AS series_title
        FROM stories s LEFT JOIN story_series ss ON s.series_id = ss.series_id WHERE s.comic_id = ?
        ORDER BY CASE WHEN s.sequence_in_comic > 0 THEN s.sequence_in_comic ELSE 999999 END ASC, s.story_id ASC");
    $stmt_stories_list->bind_param("i", $comic_id_context); $stmt_stories_list->execute(); $result_stories_list = $stmt_stories_list->get_result();
    while ($row = $result_stories_list->fetch_assoc()) {
        $row['authors'] = []; $stmt_auth = $mysqli->prepare("SELECT p.name, sp.role FROM story_persons sp JOIN persons p ON sp.person_id = p.person_id WHERE sp.story_id = ? ORDER BY p.name"); $stmt_auth->bind_param("i", $row['story_id']); $stmt_auth->execute(); $res_auth = $stmt_auth->get_result(); while($auth = $res_auth->fetch_assoc()) $row['authors'][] = $auth; $stmt_auth->close();
        $row['characters_in_story'] = []; $stmt_char = $mysqli->prepare("SELECT c.name, c.character_image FROM story_characters sc JOIN characters c ON sc.character_id = c.character_id WHERE sc.story_id = ? ORDER BY c.name"); $stmt_char->bind_param("i", $row['story_id']); $stmt_char->execute(); $res_char = $stmt_char->get_result(); while($char = $res_char->fetch_assoc()) $row['characters_in_story'][] = $char; $stmt_char->close();
        $stories_list[] = $row;
    } $stmt_stories_list->close();
}
?>
<style>
    .saga-fields-group { padding: 15px; margin-bottom: 15px; background-color: #f9f9f9; border: 1px solid #eee; border-radius: 4px; }
    .saga-fields-group h5 { margin-top: 0; margin-bottom: 10px; font-size: 1.1em; color: #333; }
    .form-top-actions { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #dee2e6; display: flex; gap: 10px; }
    .person-role-pair .select2-container { 
        width: 100% !important; 
        box-sizing: border-box;
        margin-bottom: 10px; 
    }
    #story-characters-wrapper .select2-container { 
         width: 100% !important; 
        box-sizing: border-box;
    }
</style>

<div class="container admin-container">
    <p><a href="<?php echo BASE_URL; ?>admin/comics_manage.php?action=edit&id=<?php echo $source_comic_id_for_breadcrumb > 0 ? $source_comic_id_for_breadcrumb : ''; ?>" class="btn btn-secondary btn-sm">&laquo; Torna al fumetto <?php echo ($is_duplicating_to_other_comic_flag || ($action === 'add_story' && isset($_GET['source_story_id']))) ? "sorgente" : "corrente"; ?></a></p>
    <h2><?php echo $page_title; ?> <?php if ($is_contributor && !$is_true_admin) echo "(Modalità Contributore)"; ?></h2>

    <?php if ($message): ?><div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <hr>

    <?php if ($action === 'list' || $action === 'add_story' || ($action === 'edit_story' && $story_id_to_edit_original !== null)): ?>
    <h3><?php
        if ($action === 'edit_story' && $story_id_to_edit_original) { echo ($is_contributor && !$is_true_admin) ? 'Proponi Modifiche alla Storia' : 'Modifica Storia Esistente'; }
        elseif ($is_duplicating_to_other_comic_flag) { echo 'Crea Nuova Storia su Altro Albo (da ID Storia: ' . ((int)($_GET['source_story_id'] ?? 0)) . ')'; }
        elseif ($action === 'add_story' && isset($_GET['source_story_id'])) { echo 'Crea Nuova Storia (Duplicata da ID: ' . (int)$_GET['source_story_id'] . ')'; }
        else { echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuova Storia' : 'Aggiungi Nuova Storia'; }
    ?></h3>

    <?php if ($is_contributor && !$is_true_admin): ?>
        <p class="message info" style="font-size:0.9em;">
            <strong>Nota per i Contributori:</strong> Le tue proposte verranno inviate per approvazione. Le immagini caricate saranno temporanee.
        </p>
    <?php endif; ?>

    <div class="form-top-actions">
        <button type="submit" form="storyForm" class="btn btn-success">
            <?php 
                if ($action === 'edit_story' && $story_id_to_edit_original) {
                    echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Modifiche' : 'Salva Modifiche';
                } else {
                    echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Storia' : 'Aggiungi Storia';
                }
            ?>
        </button>
        <?php if ($action === 'edit_story' || $action === 'add_story' ): ?>
            <a href="stories_manage.php?comic_id=<?php echo $comic_id_context; ?>" class="btn btn-secondary">Annulla</a>
        <?php endif; ?>
    </div>

    <form id="storyForm" action="stories_manage.php?comic_id=<?php echo $comic_id_context; ?><?php echo ($action === 'edit_story' && $story_id_to_edit_original) ? '&action=edit_story&story_id='.$story_id_to_edit_original : ''; ?>" method="POST" enctype="multipart/form-data">
        <?php if ($action === 'edit_story' && $story_id_to_edit_original): ?>
            <input type="hidden" name="edit_story" value="1"><input type="hidden" name="story_id" value="<?php echo $story_id_to_edit_original; ?>"><input type="hidden" name="current_first_page_image" value="<?php echo htmlspecialchars($story_data['first_page_image'] ?? ''); ?>">
        <?php else: ?>
            <input type="hidden" name="add_story" value="1">
            <?php if ($is_duplicating_to_other_comic_flag): ?>
                <input type="hidden" name="is_duplicating_to_other_comic_form" value="1">
                <?php if(isset($_GET['source_story_id'])) : ?><input type="hidden" name="source_story_id_audit" value="<?php echo (int)$_GET['source_story_id']; ?>"><?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($is_duplicating_to_other_comic_flag): ?>
            <div class="form-group">
                <label for="target_comic_id">Seleziona Fumetto di Destinazione:</label>
                <select name="target_comic_id" id="target_comic_id" class="form-control" required>
                    <option value="">-- Seleziona un Fumetto --</option>
                    <?php foreach ($all_comics_for_select_target as $comic_row_select): ?>
                        <option value="<?php echo $comic_row_select['comic_id']; ?>"><?php echo htmlspecialchars($comic_row_select['issue_number'] . (!empty($comic_row_select['title']) ? ' - ' . $comic_row_select['title'] : '')); ?></option>
                    <?php endforeach; ?>
                </select><small>La storia verrà aggiunta a questo fumetto.</small>
            </div><hr>
        <?php endif; ?>
        
        <div class="form-group"><label for="story_title">Titolo Specifico di Questa Parte/Storia:</label><input type="text" id="story_title" name="story_title" class="form-control" value="<?php echo htmlspecialchars($story_data['title']); ?>" required></div>
        <div class="form-group"><label for="sequence_in_comic">Ordine nel fumetto (opzionale):</label><input type="number" id="sequence_in_comic" name="sequence_in_comic" class="form-control" value="<?php echo htmlspecialchars($story_data['sequence_in_comic']); ?>" min="0"></div>
        <div class="form-group"><label for="story_notes">Note sulla storia (opzionale):</label><textarea id="story_notes" name="story_notes" class="form-control" rows="3"><?php echo htmlspecialchars($story_data['notes']); ?></textarea></div>
        
        <div class="form-group">
            <input type="checkbox" name="is_ministory" id="is_ministory" value="1" <?php echo !empty($story_data['is_ministory']) ? 'checked' : ''; ?>>
            <label for="is_ministory" class="inline-label">È una Mini-Storia (gag)?</label>
            <small>Spunta questa casella se si tratta di una storia breve, una gag o una tavola singola.</small>
        </div>
        <div class="saga-fields-group"><h5>Gestione Storie Multi-Parte (Saga)</h5>
            <div class="form-group"><label for="story_title_main">Titolo Principale Saga (opzionale):</label><input type="text" id="story_title_main" name="story_title_main" class="form-control" value="<?php echo htmlspecialchars($story_data['story_title_main'] ?? ''); ?>" placeholder="Es. La Saga della Spada di Ghiaccio"></div>
            <div class="form-group"><label for="part_number">Numero/Nome Parte (opzionale):</label><input type="text" id="part_number" name="part_number" class="form-control" value="<?php echo htmlspecialchars($story_data['part_number'] ?? ''); ?>" placeholder="Es. 1, 2, Prologo, Epilogo..."></div>
            <div class="form-group"><label for="total_parts">Numero Totale Parti (opzionale):</label><input type="number" id="total_parts" name="total_parts" class="form-control" value="<?php echo htmlspecialchars($story_data['total_parts'] ?? ''); ?>" min="1" placeholder="Es. 3"></div>
            <div class="form-group"><label for="story_group_id_select">Appartiene alla Saga Esistente (opzionale):</label>
                <select name="story_group_id_select" id="story_group_id_select" class="form-control">
                    <option value="">-- Nessuna / Nuova Saga (vedi sotto) --</option>
                    <?php foreach ($main_sagas_for_dropdown as $saga_item): ?><option value="<?php echo $saga_item['story_id']; ?>" <?php echo (isset($story_data['story_group_id']) && $story_data['story_group_id'] == $saga_item['story_id'] && !$story_data['is_new_saga_starter']) ? 'selected' : ''; ?>>ID <?php echo $saga_item['story_id']; ?>: <?php echo htmlspecialchars($saga_item['story_title_main']); ?></option><?php endforeach; ?>
                </select></div>
             <div class="form-group"><input type="checkbox" name="is_new_saga_starter" id="is_new_saga_starter" value="1" <?php echo (!empty($story_data['is_new_saga_starter'])) ? 'checked' : ''; ?>><label for="is_new_saga_starter" class="inline-label">Questa è la prima parte di una <strong>nuova</strong> saga?</label></div>
        </div>

        <div class="form-group"><label for="series_id">Appartiene alla Serie Editoriale (opzionale):</label>
            <select name="series_id" id="series_id" class="form-control">
                <option value="">-- Nessuna Serie Editoriale --</option>
                <?php foreach ($story_series_list as $series_item): ?><option value="<?php echo $series_item['series_id']; ?>" <?php echo (isset($story_data['series_id']) && $story_data['series_id'] == $series_item['series_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($series_item['title']); ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="series_episode_number">Numero Episodio nella Serie Editoriale (opzionale):</label><input type="number" id="series_episode_number" name="series_episode_number" class="form-control" value="<?php echo htmlspecialchars($story_data['series_episode_number'] ?? ''); ?>" min="1"></div>

         <div class="form-group">
            <label for="first_page_image">Immagine Prima Pagina (opzionale):</label>
            <input type="file" id="first_page_image" name="first_page_image" class="form-control-file">
            <?php if (!empty($story_data['first_page_image']) && ($action === 'edit_story' || ($action === 'add_story' && (isset($_GET['source_story_id']) || $is_duplicating_to_other_comic_flag)) ) ): ?>
                <p style="margin-top:10px;">
                    <?php 
                    if ($action === 'edit_story') echo 'Immagine attuale:';
                    else echo 'Immagine storia originale (non verrà copiata automaticamente se sei un Contributore e duplichi; sarà una proposta):';
                    ?>
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($story_data['first_page_image']); ?>" alt="Immagine" style="width: 100px; height: auto; margin-top: 5px;">
                </p>
                <?php if ($action === 'edit_story'): ?>
                    <input type="checkbox" name="delete_first_page_image" value="1" id="delete_fp_image_cb"> 
                    <label for="delete_fp_image_cb" class="inline-label">Cancella immagine attuale (se ne carichi una nuova, questa verrà sostituita)</label>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <hr><h4>Autori/Artisti della Storia</h4>
        <datalist id="common_story_roles">
            <option value="Soggetto">
            <option value="Scrittore">
            <option value="Sceneggiatura">
            <option value="Disegnatore">
            <option value="Chine">
            <option value="Colori">
            <option value="Lettering">
            <option value="Traduzione">
            <option value="Copertina">
            <option value="Matite">
            <option value="Inchiostri">
            <option value="Trama">
        </datalist>
        <div id="story-persons-wrapper">
            <?php 
            $num_person_slots = max(1, count($story_data['story_persons'])); 
            for ($i = 0; $i < $num_person_slots; $i++): 
                $selected_person_id = $story_data['story_persons'][$i]['person_id'] ?? null; 
                $selected_role = $story_data['story_persons'][$i]['role'] ?? ''; 
            ?>
            <div class="person-role-pair">
                <div class="form-group">
                    <label for="story_person_id_<?php echo $i; ?>">Persona:</label>
                    <select name="story_person_ids[]" id="story_person_id_<?php echo $i; ?>" class="form-control person-select-searchable"> <option value="">-- Seleziona --</option>
                        <?php foreach ($persons as $person): ?>
                            <option value="<?php echo $person['person_id']; ?>" <?php echo ($selected_person_id == $person['person_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($person['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="story_person_role_<?php echo $i; ?>">Ruolo:</label>
                    <input type="text" name="story_person_roles[]" id="story_person_role_<?php echo $i; ?>" value="<?php echo htmlspecialchars($selected_role); ?>" class="form-control" placeholder="Es. Disegnatore" list="common_story_roles">
                </div>
                <?php if (($num_person_slots > 1 && $i > 0) || ($i === 0 && $num_person_slots > 1)): ?>
                    <button type="button" class="btn btn-sm btn-danger remove-person-btn" style="margin-bottom:10px;">- Rimuovi</button>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
        <button type="button" id="add-another-person-btn" class="btn btn-sm btn-secondary" style="margin-bottom:15px;">+ Aggiungi Autore/Artista</button>

        <hr><h4>Personaggi Presenti</h4>
        <div class="form-group" id="story-characters-wrapper">
            <label for="story_character_ids">Seleziona Personaggi:</label>
            <select name="story_character_ids[]" id="story_character_ids" class="form-control characters-select-searchable" multiple="multiple" data-placeholder="Seleziona uno o più personaggi...">
                <?php foreach ($characters as $character): ?>
                    <option value="<?php echo $character['character_id']; ?>" <?php echo in_array($character['character_id'], $story_data['story_characters']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($character['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!empty($story_data['story_characters'])): ?>
        <p><strong>Personaggi selezionati/copiati:</strong></p>
        <ul class="story-characters-list">
            <?php foreach($story_data['story_characters'] as $sc_id) { 
                foreach($characters as $char_obj) { 
                    if ($char_obj['character_id'] == $sc_id) { 
                        echo "<li>"; 
                        if($char_obj['character_image']) echo "<img src='" . UPLOADS_URL . htmlspecialchars($char_obj['character_image']) . "' alt='".htmlspecialchars($char_obj['name'])."' title='".htmlspecialchars($char_obj['name'])."'>"; 
                        else echo "<i>(no img)</i> "; 
                        echo htmlspecialchars($char_obj['name']) . "</li>"; 
                        break;
                    }
                }
            } ?>
        </ul>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <hr style="margin-top:30px; margin-bottom:20px;">
    <h3>Elenco Storie del Fumetto <?php if ($comic_info) echo "(Topolino #" . htmlspecialchars($comic_info['issue_number']).")"; elseif($is_duplicating_to_other_comic_flag && $source_comic_id_for_breadcrumb > 0) { /* Potrebbe mostrare il fumetto sorgente */ } ?></h3>
    <?php if (!empty($stories_list)): ?>
    <table class="table">
        <thead><tr><th>Ord.</th><th>Prima Pag.</th><th>Titolo Storia (e Saga)</th><th>Autori/Artisti</th><th>Personaggi</th><th>Azioni</th></tr></thead>
        <tbody><?php foreach ($stories_list as $story): ?><tr>
            <td><?php echo ($story['sequence_in_comic'] > 0) ? htmlspecialchars($story['sequence_in_comic']) : '<i>(auto)</i>'; ?></td>
            <td><?php if ($story['first_page_image']): ?><img src="<?php echo UPLOADS_URL . htmlspecialchars($story['first_page_image']); ?>" alt="Prima pagina" style="width: 50px; height: auto;"><?php else: ?> N/A <?php endif; ?></td>
            <td>
                <?php if ($story['is_ministory'] == 1): ?>
                    <span class="badge" style="background-color: #6c757d; color: white; font-size: 0.7em; padding: 2px 5px; border-radius: 3px; vertical-align: middle; margin-right: 5px;">MINI</span>
                <?php endif; ?>
                <?php $display_title_list = ''; if (!empty($story['story_title_main'])) { $display_title_list = '<strong>' . htmlspecialchars($story['story_title_main']) . '</strong>'; if (!empty($story['part_number'])) { $part_specific_title_list = htmlspecialchars($story['title']); $expected_part_title_list = 'Parte ' . htmlspecialchars($story['part_number']); if ($part_specific_title_list !== htmlspecialchars($story['story_title_main']) && $part_specific_title_list !== $expected_part_title_list && strtolower($part_specific_title_list) !== strtolower($expected_part_title_list) ) { $display_title_list .= ': ' . $part_specific_title_list . ' <em>(Parte ' . htmlspecialchars($story['part_number']) . ')</em>'; } else { $display_title_list .= ' - Parte ' . htmlspecialchars($story['part_number']); } } elseif (!empty($story['title']) && strtolower($story['title']) !== strtolower($story['story_title_main'])) { $display_title_list .= ': ' . htmlspecialchars($story['title']); } if ($story['total_parts']) { $display_title_list .= ' (di ' . htmlspecialchars($story['total_parts']) . ')'; }} else { $display_title_list = htmlspecialchars($story['title']); } echo $display_title_list; ?>
                <?php if ($story['series_id'] && $story['series_title']): ?><div class="story-series-info">(Serie Ed: <?php echo htmlspecialchars($story['series_title']); if ($story['series_episode_number']): echo ' - Ep. ' . htmlspecialchars($story['series_episode_number']); endif; ?>)</div><?php endif; ?>
                <?php if ($story['story_group_id']): ?><div style="font-size:0.8em; color: #6c757d;">(ID Gruppo Saga: <?php echo $story['story_group_id']; ?>)</div><?php endif; ?></td>
            <td><?php if (!empty($story['authors'])): ?><ul class="story-authors-list"><?php foreach($story['authors'] as $author): ?><li><?php echo htmlspecialchars($author['name']); ?> (<?php echo htmlspecialchars($author['role']); ?>)</li><?php endforeach; ?></ul><?php else: echo "N/D"; endif; ?></td>
            <td><?php if (!empty($story['characters_in_story'])): ?><ul class="story-characters-list"><?php foreach($story['characters_in_story'] as $char_is): ?><li><?php if($char_is['character_image']): ?> <img src="<?php echo UPLOADS_URL . htmlspecialchars($char_is['character_image']); ?>" alt="<?php echo htmlspecialchars($char_is['name']); ?>" title="<?php echo htmlspecialchars($char_is['name']); ?>"> <?php endif; ?><?php echo htmlspecialchars($char_is['name']); ?></li><?php endforeach; ?></ul><?php else: echo "N/D"; endif; ?></td>
            <td style="white-space: nowrap;">
                <a href="?comic_id=<?php echo $comic_id_context; ?>&action=edit_story&story_id=<?php echo $story['story_id']; ?>" class="btn btn-sm btn-warning">
                     <?php echo ($is_contributor && !$is_true_admin) ? 'Prop. Mod.' : 'Modifica'; ?>
                </a>
                <a href="?comic_id=<?php echo $comic_id_context; ?>&action=duplicate_story&source_story_id=<?php echo $story['story_id']; ?>" class="btn btn-sm btn-info">Duplica Qui</a>
                <a href="?comic_id=<?php echo $comic_id_context; ?>&action=duplicate_to_other_comic&source_story_id=<?php echo $story['story_id']; ?>" class="btn btn-sm btn-primary" title="Duplica questa storia e assegnala a un altro albo">Duplica su Altro...</a>
                <?php if ($is_true_admin): ?>
                    <a href="?comic_id=<?php echo $comic_id_context; ?>&action=delete_story&story_id=<?php echo $story['story_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa storia?');">Elimina</a>
                <?php endif; ?>
            </td></tr><?php endforeach; ?>
        </tbody></table>
    <?php elseif ($comic_id_context > 0): ?>
    <p>Nessuna storia ancora aggiunta per questo fumetto.</p>
    <?php elseif (!$is_duplicating_to_other_comic_flag && $action !== 'add_story'): ?>
    <p>Seleziona un fumetto per vedere le sue storie o aggiungerne di nuove.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function initializeSelect2(selector, placeholderText) {
        if (jQuery.fn.select2) { 
            $(selector).select2({
                placeholder: placeholderText,
                allowClear: true,
                width: '100%'
            });
        }
    }
    $('.person-select-searchable').each(function() {
        initializeSelect2(this, '-- Seleziona Persona --');
    });
    initializeSelect2('.characters-select-searchable', 'Seleziona uno o più personaggi...');

    const wrapper = document.getElementById('story-persons-wrapper');
    const addBtn = document.getElementById('add-another-person-btn');
    let personCounter = <?php 
        $existing_pairs = 0; 
        if (isset($story_data['story_persons']) && is_array($story_data['story_persons'])) { 
            $existing_pairs = count($story_data['story_persons']); 
        } 
        echo max(1, $existing_pairs); 
    ?>;

    if (addBtn && wrapper) {
        addBtn.addEventListener('click', function() {
            const newPairOuter = document.createElement('div'); 
            newPairOuter.classList.add('person-role-pair');
            
            let optionsHtml = '<option value="">-- Seleziona --</option>';
            <?php foreach ($persons as $person): ?>
                optionsHtml += '<option value="<?php echo $person['person_id']; ?>"><?php echo htmlspecialchars(addslashes($person['name'])); ?></option>';
            <?php endforeach; ?>
            
            const newPairInnerHtml = `
                <div class="form-group">
                    <label for="story_person_id_${personCounter}">Persona:</label>
                    <select name="story_person_ids[]" id="story_person_id_${personCounter}" class="form-control person-select-searchable">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="form-group">
                    <label for="story_person_role_${personCounter}">Ruolo:</label>
                    <input type="text" name="story_person_roles[]" id="story_person_role_${personCounter}" class="form-control" placeholder="Es. Disegnatore" list="common_story_roles">
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-person-btn" style="margin-bottom:10px;">- Rimuovi</button>
            `;
            newPairOuter.innerHTML = newPairInnerHtml; 
            wrapper.appendChild(newPairOuter);
            initializeSelect2($(newPairOuter).find('.person-select-searchable'), '-- Seleziona Persona --');
            personCounter++;
        });

        wrapper.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-person-btn')) {
                const pairToRemove = event.target.closest('.person-role-pair');
                if (wrapper.querySelectorAll('.person-role-pair').length > 1) { 
                    if (jQuery.fn.select2) {
                        $(pairToRemove).find('.person-select-searchable').select2('destroy');
                    }
                    pairToRemove.remove(); 
                } else { 
                    const selectField = pairToRemove.querySelector('select.person-select-searchable'); 
                    const inputField = pairToRemove.querySelector('input[type="text"]'); 
                    if(selectField) {
                        if (jQuery.fn.select2) {
                            $(selectField).val(null).trigger('change'); 
                        } else {
                            selectField.value = ""; 
                        }
                    }
                    if(inputField) inputField.value = ""; 
                }
            }
        });
    }

    const isNewSagaStarterCheckbox = document.getElementById('is_new_saga_starter');
    const storyGroupIdSelect = document.getElementById('story_group_id_select');
    if (isNewSagaStarterCheckbox && storyGroupIdSelect) {
        function toggleSagaSelect() { 
            storyGroupIdSelect.disabled = isNewSagaStarterCheckbox.checked; 
            if (isNewSagaStarterCheckbox.checked) storyGroupIdSelect.value = ""; 
        }
        isNewSagaStarterCheckbox.addEventListener('change', toggleSagaSelect); 
        toggleSagaSelect(); 
    }
});
</script>
<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
if ($mysqli) { $mysqli->close(); } 
?>