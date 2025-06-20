<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
// Non includere functions.php qui a meno che non sia strettamente necessario per logiche non DB

// CONTROLLO ACCESSO - SOLO VERI ADMIN
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin_user_id'])) {
    $_SESSION['admin_proposal_message'] = "Accesso negato. Devi essere un amministratore per eseguire questa azione.";
    $_SESSION['admin_proposal_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}
$current_admin_reviewer_id = $_SESSION['admin_user_id'];
// FINE CONTROLLO ACCESSO

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['admin_proposal_message'] = "Richiesta non valida.";
    $_SESSION['admin_proposal_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/proposals_manage.php');
    exit;
}

$proposal_type      = $_POST['proposal_type'] ?? null;
$pending_id         = isset($_POST['pending_id']) ? (int)$_POST['pending_id'] : 0;
$action_review      = $_POST['action_review'] ?? null; // 'approve' o 'reject'
$admin_review_notes = trim($_POST['admin_review_notes'] ?? '');

$table_map = [
    'comic' => 'pending_comics', 'story' => 'pending_stories',
    'person' => 'pending_persons', 'character' => 'pending_characters',
    'series' => 'pending_story_series'
];
$original_table_map = [
    'comic' => 'comics', 'story' => 'stories',
    'person' => 'persons', 'character' => 'characters',
    'series' => 'story_series'
];
$id_column_map = [
    'comic' => 'pending_comic_id', 'story' => 'pending_story_id',
    'person' => 'pending_person_id', 'character' => 'pending_character_id',
    'series' => 'pending_series_id'
];
$original_id_column_map = [
    'comic' => 'comic_id_original', 'story' => 'story_id_original',
    'person' => 'person_id_original', 'character' => 'character_id_original',
    'series' => 'series_id_original'
];
$original_main_id_column_map = [
    'comic' => 'comic_id', 'story' => 'story_id',
    'person' => 'person_id', 'character' => 'character_id',
    'series' => 'series_id'
];

if (!$proposal_type || !$pending_id || !isset($table_map[$proposal_type]) || !in_array($action_review, ['approve', 'reject'])) {
    $_SESSION['admin_proposal_message'] = "Dati della richiesta mancanti o non validi.";
    $_SESSION['admin_proposal_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/proposals_manage.php');
    exit;
}

$pending_table = $table_map[$proposal_type];
$pending_id_column = $id_column_map[$proposal_type];

$stmt_fetch_proposal = $mysqli->prepare("SELECT * FROM {$pending_table} WHERE {$pending_id_column} = ? AND status = 'pending'");
if (!$stmt_fetch_proposal) {
    $_SESSION['admin_proposal_message'] = "Errore preparazione query recupero proposta: " . $mysqli->error;
    $_SESSION['admin_proposal_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/proposals_manage.php');
    exit;
}
$stmt_fetch_proposal->bind_param("i", $pending_id);
$stmt_fetch_proposal->execute();
$result_proposal_data = $stmt_fetch_proposal->get_result();
if ($result_proposal_data->num_rows !== 1) {
    $_SESSION['admin_proposal_message'] = "Proposta non trovata o già processata.";
    $_SESSION['admin_proposal_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/proposals_manage.php');
    exit;
}
$proposal = $result_proposal_data->fetch_assoc();
$stmt_fetch_proposal->close();


function finalize_image($proposed_path_relative, $original_path_relative, $final_target_subdir, $filename_prefix, $identifier_for_filename) {
    global $mysqli; 

    if (empty($proposed_path_relative)) {
        if ($original_path_relative && file_exists(UPLOADS_PATH . $original_path_relative) && strpos($original_path_relative, 'pending_images/') === false) {
            @unlink(UPLOADS_PATH . $original_path_relative);
        }
        return null; 
    }

    if (strpos($proposed_path_relative, 'pending_images/') === false) {
        if ($original_path_relative && $original_path_relative !== $proposed_path_relative &&
            file_exists(UPLOADS_PATH . $original_path_relative) && strpos($original_path_relative, 'pending_images/') === false) {
            @unlink(UPLOADS_PATH . $original_path_relative); 
        }
        return $proposed_path_relative;
    }

    $source_absolute_path = UPLOADS_PATH . $proposed_path_relative;
    if (!file_exists($source_absolute_path)) {
        error_log("finalize_image: File proposto (pending) non trovato: " . $source_absolute_path . ". Si tenterà di usare l'originale se presente.");
        return $original_path_relative;
    }

    $final_dir_absolute = UPLOADS_PATH . rtrim($final_target_subdir, '/') . '/';
    if (!is_dir($final_dir_absolute)) {
        if (!mkdir($final_dir_absolute, 0775, true)) {
            error_log("finalize_image: Impossibile creare la cartella finale: " . $final_dir_absolute . ". Il file pending non verrà spostato.");
            return $original_path_relative;
        }
    }

    $file_extension = strtolower(pathinfo($proposed_path_relative, PATHINFO_EXTENSION));
    $safe_identifier = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$identifier_for_filename);
    $new_final_filename = $filename_prefix . $safe_identifier . '_' . uniqid() . '.' . $file_extension;
    $destination_absolute_path = $final_dir_absolute . $new_final_filename;

    if (rename($source_absolute_path, $destination_absolute_path)) {
        if ($original_path_relative &&
            $original_path_relative !== (rtrim($final_target_subdir, '/') . '/' . $new_final_filename) &&
            file_exists(UPLOADS_PATH . $original_path_relative) &&
            strpos($original_path_relative, 'pending_images/') === false) {
            @unlink(UPLOADS_PATH . $original_path_relative);
        }
        return rtrim($final_target_subdir, '/') . '/' . $new_final_filename;
    } else {
        error_log("finalize_image: Errore nello spostare l'immagine approvata: da {$source_absolute_path} a {$destination_absolute_path}");
        return $original_path_relative;
    }
}

function delete_pending_image($pending_image_path_relative) {
    if ($pending_image_path_relative && strpos($pending_image_path_relative, 'pending_images/') !== false && file_exists(UPLOADS_PATH . $pending_image_path_relative)) {
        unlink(UPLOADS_PATH . $pending_image_path_relative);
    }
}


$mysqli->begin_transaction();
try {
    if ($action_review === 'approve') {
        $original_id = $proposal[$original_id_column_map[$proposal_type]] ?? null;
        $new_main_id = null; 

        if ($proposal_type === 'comic') {
            $original_comic_data_for_images = null;
            if ($proposal['action_type'] === 'edit' && $original_id) {
                 $res_orig_img = $mysqli->query("SELECT comic_id, issue_number, cover_image, back_cover_image, gadget_image FROM comics WHERE comic_id = $original_id");
                 if ($res_orig_img) $original_comic_data_for_images = $res_orig_img->fetch_assoc();
            }
            $identifier_for_filename = $proposal['issue_number'] ?: ($original_comic_data_for_images['issue_number'] ?? 'new_comic');

            $final_cover_image = finalize_image($proposal['cover_image_proposal'], $original_comic_data_for_images['cover_image'] ?? null, 'covers', 'topolino_', $identifier_for_filename);
            $final_back_cover_image = finalize_image($proposal['back_cover_image_proposal'], $original_comic_data_for_images['back_cover_image'] ?? null, 'covers/backcovers', 'topolino_back_', $identifier_for_filename);
            $final_gadget_image = finalize_image($proposal['gadget_image_proposal'], $original_comic_data_for_images['gadget_image'] ?? null, 'gadgets', 'gadget_', $identifier_for_filename);

            if ($proposal['action_type'] === 'add') {
                $stmt = $mysqli->prepare("INSERT INTO comics (issue_number, title, publication_date, description, cover_image, back_cover_image, editor, pages, price, periodicity, custom_fields, gadget_name, gadget_image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssssssssssss", 
                    $proposal['issue_number'], $proposal['title'], $proposal['publication_date'], $proposal['description'], 
                    $final_cover_image, $final_back_cover_image, $proposal['editor'], $proposal['pages'], $proposal['price'], 
                    $proposal['periodicity'], $proposal['custom_fields_proposal'], $proposal['gadget_name_proposal'], $final_gadget_image);
            } elseif ($proposal['action_type'] === 'edit' && $original_id) {
                $stmt = $mysqli->prepare("UPDATE comics SET issue_number = ?, title = ?, publication_date = ?, description = ?, cover_image = ?, back_cover_image = ?, editor = ?, pages = ?, price = ?, periodicity = ?, custom_fields = ?, gadget_name = ?, gadget_image = ? WHERE comic_id = ?");
                $stmt->bind_param("sssssssssssssi", 
                    $proposal['issue_number'], $proposal['title'], $proposal['publication_date'], $proposal['description'], 
                    $final_cover_image, $final_back_cover_image, $proposal['editor'], $proposal['pages'], $proposal['price'], 
                    $proposal['periodicity'], $proposal['custom_fields_proposal'], $proposal['gadget_name_proposal'], $final_gadget_image,
                    $original_id);
            }
            if (isset($stmt)) {
                if (!$stmt->execute()) throw new Exception("Errore DB approvazione fumetto: " . $stmt->error);
                $new_main_id = ($proposal['action_type'] === 'add') ? $mysqli->insert_id : $original_id;
                $stmt->close();

                if ($new_main_id && !empty($proposal['staff_proposal_json'])) {
                    $staff_data = json_decode($proposal['staff_proposal_json'], true);
                    if (is_array($staff_data)) {
                        $mysqli->query("DELETE FROM comic_persons WHERE comic_id = $new_main_id");
                        $stmt_staff = $mysqli->prepare("INSERT INTO comic_persons (comic_id, person_id, role) VALUES (?, ?, ?)");
                        foreach ($staff_data as $staff_member) {
                            if (isset($staff_member['person_id'], $staff_member['role'])) {
                                $stmt_staff->bind_param("iis", $new_main_id, $staff_member['person_id'], $staff_member['role']);
                                if(!$stmt_staff->execute()) throw new Exception("Errore DB staff fumetto: " . $stmt_staff->error);
                            }
                        }
                        $stmt_staff->close();
                    }
                }
                // --- INIZIO BLOCCO MODIFICATO ---
                if ($new_main_id && !empty($proposal['variant_covers_proposal_json'])) {
                    $variants_data = json_decode($proposal['variant_covers_proposal_json'], true);
                    if (is_array($variants_data)) {
                        $stmt_vc_add = $mysqli->prepare("INSERT INTO comic_variant_covers (comic_id, image_path, caption, artist_id, sort_order) VALUES (?, ?, ?, ?, ?)");
                        $stmt_vc_update = $mysqli->prepare("UPDATE comic_variant_covers SET image_path = ?, caption = ?, artist_id = ?, sort_order = ? WHERE variant_cover_id = ? AND comic_id = ?");
                        $stmt_vc_delete = $mysqli->prepare("DELETE FROM comic_variant_covers WHERE variant_cover_id = ? AND comic_id = ?");
                        $stmt_vc_get_img = $mysqli->prepare("SELECT image_path FROM comic_variant_covers WHERE variant_cover_id = ? AND comic_id = ?");

                        foreach ($variants_data as $v_proposal) {
                            $v_action = $v_proposal['action'] ?? null;
                            $v_original_id = $v_proposal['variant_cover_id_original'] ?? null;
                            $v_proposed_path_temp = $v_proposal['image_path_proposal'] ?? null; 
                            $v_caption = $v_proposal['caption_proposal'] ?? '';
                            $v_artist_id = $v_proposal['artist_id_proposal'] ?? null; // Recupera l'artist_id
                            $v_sort_order = $v_proposal['sort_order_proposal'] ?? 0;
                            
                            $v_original_image_path_for_finalize = null;
                            if ($v_action === 'edit_existing' && $v_original_id) {
                                $stmt_vc_get_img->bind_param("ii", $v_original_id, $new_main_id); 
                                $stmt_vc_get_img->execute(); $res_img_v = $stmt_vc_get_img->get_result();
                                if($img_data_v = $res_img_v->fetch_assoc()) $v_original_image_path_for_finalize = $img_data_v['image_path'];
                                $res_img_v->free();
                            }

                            $v_final_image_path = finalize_image($v_proposed_path_temp, $v_original_image_path_for_finalize, 'covers/variants', 'variant_', $new_main_id . '_' . uniqid());

                            if ($v_action === 'add_new' && $v_final_image_path) {
                                 $stmt_vc_add->bind_param("issii", $new_main_id, $v_final_image_path, $v_caption, $v_artist_id, $v_sort_order);
                                 if(!$stmt_vc_add->execute()) throw new Exception("Errore DB aggiunta variant: " . $stmt_vc_add->error);
                            } elseif ($v_action === 'edit_existing' && $v_original_id) {
                                $stmt_vc_update->bind_param("ssiiii", $v_final_image_path, $v_caption, $v_artist_id, $v_sort_order, $v_original_id, $new_main_id);
                                if(!$stmt_vc_update->execute()) throw new Exception("Errore DB modifica variant: " . $stmt_vc_update->error);
                            } elseif ($v_action === 'delete_existing' && $v_original_id) {
                                // La logica di cancellazione non ha bisogno di modifiche perché cancella l'intera riga
                                $stmt_vc_delete->bind_param("ii", $v_original_id, $new_main_id);
                                if(!$stmt_vc_delete->execute()) throw new Exception("Errore DB cancellazione variant: " . $stmt_vc_delete->error);
                            }
                            if (str_starts_with((string)$v_proposed_path_temp, 'pending_images/') && $v_proposed_path_temp !== $v_final_image_path) { 
                                delete_pending_image($v_proposed_path_temp); 
                            }
                        }
                        $stmt_vc_add->close(); $stmt_vc_update->close(); $stmt_vc_delete->close(); $stmt_vc_get_img->close();
                    }
                }
                // --- FINE BLOCCO MODIFICATO ---

                if ($proposal['action_type'] === 'add' && $new_main_id && !empty($proposal['issue_number'])) {
                    $placeholders_to_migrate = [];
                    $stmt_find_placeholders = $mysqli->prepare("SELECT placeholder_id, user_id FROM user_collection_placeholders WHERE issue_number_placeholder = ? AND status = 'pending'");
                    $stmt_find_placeholders->bind_param("s", $proposal['issue_number']); $stmt_find_placeholders->execute(); $result_placeholders = $stmt_find_placeholders->get_result();
                    while ($ph_row = $result_placeholders->fetch_assoc()) $placeholders_to_migrate[] = $ph_row;
                    $stmt_find_placeholders->close();
                    if (!empty($placeholders_to_migrate)) {
                        $migrated_count = 0;
                        $stmt_add_to_real_collection = $mysqli->prepare("INSERT INTO user_collections (user_id, comic_id, added_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE comic_id = VALUES(comic_id)");
                        $stmt_delete_placeholder = $mysqli->prepare("DELETE FROM user_collection_placeholders WHERE placeholder_id = ?");
                        foreach ($placeholders_to_migrate as $placeholder_mig) {
                            $stmt_add_to_real_collection->bind_param("ii", $placeholder_mig['user_id'], $new_main_id);
                            if ($stmt_add_to_real_collection->execute()) { $stmt_delete_placeholder->bind_param("i", $placeholder_mig['placeholder_id']); if ($stmt_delete_placeholder->execute()) $migrated_count++; }
                        }
                        $stmt_add_to_real_collection->close(); $stmt_delete_placeholder->close();
                    }
                }
            }
        } elseif ($proposal_type === 'story') {
            $original_story_data_for_img = null;
            if ($proposal['action_type'] === 'edit' && $original_id) {
                 $res_orig_story_img = $mysqli->query("SELECT first_page_image FROM stories WHERE story_id = $original_id");
                 if ($res_orig_story_img) $original_story_data_for_img = $res_orig_story_img->fetch_assoc();
            }
            $identifier_story_filename = $proposal['comic_id_context'] . '_' . ($original_id ?: 'new_story');
            $final_first_page_image = finalize_image($proposal['first_page_image_proposal'], $original_story_data_for_img['first_page_image'] ?? null, 'first_pages', 'fp_', $identifier_story_filename);
            
            $final_story_group_id = $proposal['story_group_id_proposal'];

            if ($proposal['action_type'] === 'add') {
                $stmt = $mysqli->prepare("INSERT INTO stories (comic_id, title, first_page_image, sequence_in_comic, notes, is_ministory, series_id, series_episode_number, story_title_main, part_number, total_parts, story_group_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("issisiissssi", 
                    $proposal['comic_id_context'], $proposal['title_proposal'], $final_first_page_image, $proposal['sequence_in_comic_proposal'], 
                    $proposal['notes_proposal'], $proposal['is_ministory_proposal'], $proposal['series_id_proposal'], $proposal['series_episode_number_proposal'], 
                    $proposal['story_title_main_proposal'], $proposal['part_number_proposal'], $proposal['total_parts_proposal'], $final_story_group_id);
            } elseif ($proposal['action_type'] === 'edit' && $original_id) {
                $stmt = $mysqli->prepare("UPDATE stories SET comic_id = ?, title = ?, first_page_image = ?, sequence_in_comic = ?, notes = ?, is_ministory = ?, series_id = ?, series_episode_number = ?, story_title_main = ?, part_number = ?, total_parts = ?, story_group_id = ? WHERE story_id = ?");
                $stmt->bind_param("issisiisssiii", 
                    $proposal['comic_id_context'], $proposal['title_proposal'], $final_first_page_image, $proposal['sequence_in_comic_proposal'], 
                    $proposal['notes_proposal'], $proposal['is_ministory_proposal'], $proposal['series_id_proposal'], $proposal['series_episode_number_proposal'], 
                    $proposal['story_title_main_proposal'], $proposal['part_number_proposal'], $proposal['total_parts_proposal'], $final_story_group_id,
                    $original_id);
            }
             if (isset($stmt)) {
                if (!$stmt->execute()) throw new Exception("Errore DB approvazione storia: " . $stmt->error);
                $new_main_id = ($proposal['action_type'] === 'add') ? $mysqli->insert_id : $original_id;
                $stmt->close();

                if ($final_story_group_id === 0 && $new_main_id > 0) { 
                    $mysqli->query("UPDATE stories SET story_group_id = {$new_main_id} WHERE story_id = {$new_main_id}");
                }

                if ($new_main_id) {
                    $mysqli->query("DELETE FROM story_persons WHERE story_id = $new_main_id");
                    if (!empty($proposal['authors_proposal_json'])) {
                        $authors_data = json_decode($proposal['authors_proposal_json'], true);
                        if (is_array($authors_data)) {
                            $stmt_s_auth = $mysqli->prepare("INSERT INTO story_persons (story_id, person_id, role) VALUES (?, ?, ?)");
                            foreach ($authors_data as $author) {
                                if(isset($author['person_id'], $author['role'])){
                                   $stmt_s_auth->bind_param("iis", $new_main_id, $author['person_id'], $author['role']);
                                   if(!$stmt_s_auth->execute()) throw new Exception("Errore DB autori storia: " . $stmt_s_auth->error);
                                }
                            }
                            $stmt_s_auth->close();
                        }
                    }
                    $mysqli->query("DELETE FROM story_characters WHERE story_id = $new_main_id");
                     if (!empty($proposal['characters_proposal_json'])) {
                        $chars_data = json_decode($proposal['characters_proposal_json'], true);
                        if (is_array($chars_data)) {
                            $stmt_s_char = $mysqli->prepare("INSERT INTO story_characters (story_id, character_id) VALUES (?, ?)");
                            foreach ($chars_data as $char_id) { 
                                if(is_numeric($char_id)){
                                    $stmt_s_char->bind_param("ii", $new_main_id, $char_id);
                                    if(!$stmt_s_char->execute()) throw new Exception("Errore DB personaggi storia: " . $stmt_s_char->error);
                                }
                            }
                            $stmt_s_char->close();
                        }
                    }
                }
            }
        } elseif ($proposal_type === 'person') {
            $original_person_data_img = null;
            if ($proposal['action_type'] === 'edit' && $original_id) {
                 $res_orig_p_img = $mysqli->query("SELECT person_image FROM persons WHERE person_id = $original_id");
                 if ($res_orig_p_img) $original_person_data_img = $res_orig_p_img->fetch_assoc();
            }
            $final_person_image = finalize_image($proposal['person_image_proposal'], $original_person_data_img['person_image'] ?? null, 'persons', 'person_', $proposal['name_proposal'] ?: ($original_id ?: 'new_person'));

            if ($proposal['action_type'] === 'add') {
                $stmt = $mysqli->prepare("INSERT INTO persons (name, biography, person_image, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("sss", $proposal['name_proposal'], $proposal['biography_proposal'], $final_person_image);
            } elseif ($proposal['action_type'] === 'edit' && $original_id) {
                $stmt = $mysqli->prepare("UPDATE persons SET name = ?, biography = ?, person_image = ? WHERE person_id = ?");
                $stmt->bind_param("sssi", $proposal['name_proposal'], $proposal['biography_proposal'], $final_person_image, $original_id);
            }
            if (isset($stmt)) {
                if (!$stmt->execute()) throw new Exception("Errore DB approvazione persona: " . $stmt->error . " (Nome Proposto: ".$proposal['name_proposal'].")");
                $stmt->close();
            }
        } elseif ($proposal_type === 'character') {
             $original_char_data_img = null;
            if ($proposal['action_type'] === 'edit' && $original_id) {
                 $res_orig_c_img = $mysqli->query("SELECT character_image FROM characters WHERE character_id = $original_id");
                 if ($res_orig_c_img) $original_char_data_img = $res_orig_c_img->fetch_assoc();
            }
            $final_character_image = finalize_image($proposal['character_image_proposal'], $original_char_data_img['character_image'] ?? null, 'characters', 'character_', $proposal['name_proposal'] ?: ($original_id ?: 'new_character'));
            
            $first_app_date_final_char = null;
            if ($proposal['first_appearance_comic_id_proposal']) {
                $comic_id_for_date_char = (int)$proposal['first_appearance_comic_id_proposal'];
                $res_date_char = $mysqli->query("SELECT publication_date FROM comics WHERE comic_id = $comic_id_for_date_char");
                if ($res_date_char && $date_row_char = $res_date_char->fetch_assoc()) {
                    $first_app_date_final_char = $date_row_char['publication_date'];
                }
            }

            if ($proposal['action_type'] === 'add') {
                $stmt = $mysqli->prepare("INSERT INTO characters (name, description, character_image, first_appearance_comic_id, first_appearance_story_id, first_appearance_date, first_appearance_notes, is_first_appearance_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssiissi", $proposal['name_proposal'], $proposal['description_proposal'], $final_character_image, 
                                  $proposal['first_appearance_comic_id_proposal'], $proposal['first_appearance_story_id_proposal'], $first_app_date_final_char,
                                  $proposal['first_appearance_notes_proposal'], $proposal['is_first_appearance_verified_proposal']);
            } elseif ($proposal['action_type'] === 'edit' && $original_id) {
                $stmt = $mysqli->prepare("UPDATE characters SET name = ?, description = ?, character_image = ?, first_appearance_comic_id = ?, first_appearance_story_id = ?, first_appearance_date = ?, first_appearance_notes = ?, is_first_appearance_verified = ? WHERE character_id = ?");
                $stmt->bind_param("sssiissii", $proposal['name_proposal'], $proposal['description_proposal'], $final_character_image, 
                                   $proposal['first_appearance_comic_id_proposal'], $proposal['first_appearance_story_id_proposal'], $first_app_date_final_char,
                                   $proposal['first_appearance_notes_proposal'], $proposal['is_first_appearance_verified_proposal'], 
                                   $original_id);
            }
             if (isset($stmt)) {
                if (!$stmt->execute()) throw new Exception("Errore DB approvazione personaggio: " . $stmt->error . " (Nome Proposto: ".$proposal['name_proposal'].")");
                $stmt->close();
            }
        } elseif ($proposal_type === 'series') {
            $original_series_data_img = null;
            if ($proposal['action_type'] === 'edit' && $original_id) {
                 $res_orig_s_img = $mysqli->query("SELECT image_path, start_date FROM story_series WHERE series_id = $original_id");
                 if ($res_orig_s_img) $original_series_data_img = $res_orig_s_img->fetch_assoc();
            }
            $final_series_image = finalize_image($proposal['image_path_proposal'], $original_series_data_img['image_path'] ?? null, 'series_images', 'series_', $proposal['title_proposal'] ?: ($original_id ?: 'new_series'));

            if ($proposal['action_type'] === 'add') {
                $stmt = $mysqli->prepare("INSERT INTO story_series (title, description, image_path, start_date, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssss", $proposal['title_proposal'], $proposal['description_proposal'], $final_series_image, $proposal['start_date_proposal']);
            } elseif ($proposal['action_type'] === 'edit' && $original_id) {
                $stmt = $mysqli->prepare("UPDATE story_series SET title = ?, description = ?, image_path = ?, start_date = ? WHERE series_id = ?");
                $stmt->bind_param("ssssi", $proposal['title_proposal'], $proposal['description_proposal'], $final_series_image, $proposal['start_date_proposal'], $original_id);
            }
             if (isset($stmt)) {
                if (!$stmt->execute()) throw new Exception("Errore DB approvazione serie: " . $stmt->error . " (Titolo Proposto: ".$proposal['title_proposal'].")");
                $stmt->close();
            }
        }
        
        $stmt_update_pending = $mysqli->prepare("UPDATE {$pending_table} SET status = 'approved', reviewed_at = NOW(), reviewer_admin_id = ?, admin_notes = ? WHERE {$pending_id_column} = ?");
        $stmt_update_pending->bind_param("isi", $current_admin_reviewer_id, $admin_review_notes, $pending_id);
        $stmt_update_pending->execute();
        $stmt_update_pending->close();

        $_SESSION['admin_proposal_message'] = "Proposta ({$proposal_type} ID: {$pending_id}) approvata con successo!";
        $_SESSION['admin_proposal_message_type'] = 'success';

    } elseif ($action_review === 'reject') {
        if ($proposal_type === 'comic') {
            delete_pending_image($proposal['cover_image_proposal']);
            delete_pending_image($proposal['back_cover_image_proposal']);
            delete_pending_image($proposal['gadget_image_proposal']);
            if (!empty($proposal['variant_covers_proposal_json'])) {
                $variants_data = json_decode($proposal['variant_covers_proposal_json'], true);
                if(is_array($variants_data)) {
                    foreach($variants_data as $v_prop) {
                        if (($v_prop['action'] ?? null) === 'add_new') { 
                           delete_pending_image($v_prop['image_path_proposal'] ?? null);
                        }
                    }
                }
            }
        } elseif ($proposal_type === 'story') {
            delete_pending_image($proposal['first_page_image_proposal']);
        } elseif ($proposal_type === 'person') {
            delete_pending_image($proposal['person_image_proposal']);
        } elseif ($proposal_type === 'character') {
            delete_pending_image($proposal['character_image_proposal']);
        } elseif ($proposal_type === 'series') {
            delete_pending_image($proposal['image_path_proposal']);
        }

        $stmt_update_pending = $mysqli->prepare("UPDATE {$pending_table} SET status = 'rejected', reviewed_at = NOW(), reviewer_admin_id = ?, admin_notes = ? WHERE {$pending_id_column} = ?");
        $stmt_update_pending->bind_param("isi", $current_admin_reviewer_id, $admin_review_notes, $pending_id);
        $stmt_update_pending->execute();
        $stmt_update_pending->close();

        $_SESSION['admin_proposal_message'] = "Proposta ({$proposal_type} ID: {$pending_id}) rifiutata.";
        $_SESSION['admin_proposal_message_type'] = 'success'; 
    }
    $mysqli->commit();
} catch (Exception $e) {
    $mysqli->rollback();
    $_SESSION['admin_proposal_message'] = "Errore durante l'elaborazione della proposta ({$proposal_type} ID: {$pending_id}): " . $e->getMessage();
    $_SESSION['admin_proposal_message_type'] = 'error';
    error_log("Errore in proposal_actions.php: " . $e->getMessage() . " | Proposta: " . json_encode($proposal));
}

$mysqli->close();
header('Location: ' . BASE_URL . 'admin/proposals_manage.php');
exit;
?>