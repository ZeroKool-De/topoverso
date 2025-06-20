<?php
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// CONTROLLO ACCESSO
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$is_true_admin = isset($_SESSION['admin_user_id']);
$is_contributor = (isset($_SESSION['user_id_frontend']) && isset($_SESSION['user_role_frontend']) && $_SESSION['user_role_frontend'] === 'contributor');
$current_user_id_for_proposal = $_SESSION['user_id_frontend'] ?? 0;
if (!$is_true_admin && !$is_contributor) {
    $_SESSION['message'] = "Accesso non autorizzato.";
    $_SESSION['message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}

$message = '';
$message_type = '';

// Funzione helper per gestire l'upload delle immagini (invariata)
function handle_image_upload_action($file_input_name, $current_image_path, $delete_flag_name, $target_subdir, $filename_prefix, $issue_num_for_filename, $is_contributor_upload) {
    global $message, $message_type;
    $image_path_to_return = $current_image_path;
    $base_upload_path = UPLOADS_PATH;
    $final_upload_dir_relative = rtrim($target_subdir, '/') . '/';
    $pending_upload_dir_relative = 'pending_images/' . rtrim($target_subdir, '/') . '/';

    if (isset($_POST[$delete_flag_name]) && $_POST[$delete_flag_name] == '1' && $current_image_path) {
        if (!$is_contributor_upload && file_exists($base_upload_path . $current_image_path) && strpos($current_image_path, 'pending_images/') === false) {
            @unlink($base_upload_path . $current_image_path);
        }
        $image_path_to_return = null;
        if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] == UPLOAD_ERR_NO_FILE) {
            return $image_path_to_return;
        }
    }

    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $upload_dir_actual_relative = $is_contributor_upload ? $pending_upload_dir_relative : $final_upload_dir_relative;
        $upload_dir_absolute = $base_upload_path . $upload_dir_actual_relative;

        if (!is_dir($upload_dir_absolute)) {
            if (!mkdir($upload_dir_absolute, 0775, true)) {
                $message .= " Errore: Impossibile creare la cartella di upload: " . htmlspecialchars($upload_dir_absolute) . ".";
                $message_type = 'error';
                return $image_path_to_return;
            }
        }

        $original_filename = $_FILES[$file_input_name]['name'];
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_extension, $allowed_extensions)) {
            $message .= " Formato file non valido per ".htmlspecialchars($file_input_name).".";
            $message_type = 'error';
            return $image_path_to_return;
        }

        $safe_issue_number = preg_replace('/[^a-zA-Z0-9_-]/', '_', $issue_num_for_filename);
        $temp_filename_no_ext = $filename_prefix . $safe_issue_number . '_' . uniqid();
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
                if (!$is_contributor_upload && $current_image_path && file_exists($base_upload_path . $current_image_path) && strpos($current_image_path, 'pending_images/') === false && $current_image_path !== $new_final_filename_relative) {
                    @unlink($base_upload_path . $current_image_path);
                }
                $image_path_to_return = $new_final_filename_relative;
            } else {
                $message .= " Fallimento compressione per ".htmlspecialchars($file_input_name).".";
                $image_path_to_return = $upload_dir_actual_relative . basename($temp_uploaded_file_absolute);
            }
        } else {
            $message .= " Errore durante l'upload del file ".htmlspecialchars($file_input_name).".";
            $message_type = 'error';
        }
    }
    return $image_path_to_return;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_comic']) || isset($_POST['edit_comic']))) {
    $is_editing_action = isset($_POST['edit_comic']);
    $comic_id_original_for_proposal = $is_editing_action ? (int)$_POST['comic_id'] : null;

    $issue_number = trim($_POST['issue_number']);
    $title = trim($_POST['title']);
    $publication_date = !empty($_POST['publication_date']) ? trim($_POST['publication_date']) : null;
    $description = trim($_POST['description']);
    $editor = trim($_POST['editor']);
    $pages = trim($_POST['pages']);
    $price = trim($_POST['price']);
    $periodicity = trim($_POST['periodicity']);
    
    // BLOCCO AGGIUNTO: Gestione degli artisti di copertina e retrocopertina
    $cover_artists_json = null;
    $back_cover_artists_json = null;

    // Processa gli artisti della copertina
    if (isset($_POST['cover_artists']) && is_array($_POST['cover_artists'])) {
        $cover_artists = array_filter($_POST['cover_artists'], function($id) {
            return !empty($id) && is_numeric($id);
        });
        if (!empty($cover_artists)) {
            $cover_artists_json = json_encode(array_values($cover_artists));
        }
    }

    // Processa gli artisti della retrocopertina
    if (isset($_POST['back_cover_artists']) && is_array($_POST['back_cover_artists'])) {
        $back_cover_artists = array_filter($_POST['back_cover_artists'], function($id) {
            return !empty($id) && is_numeric($id);
        });
        if (!empty($back_cover_artists)) {
            $back_cover_artists_json = json_encode(array_values($back_cover_artists));
        }
    }
    // FINE BLOCCO AGGIUNTO
    
    // Manteniamo i campi legacy per compatibilità (opzionale)
    $cover_artist_id = !empty($_POST['cover_artist_id']) ? (int)$_POST['cover_artist_id'] : NULL;
    $back_cover_artist_id = !empty($_POST['back_cover_artist_id']) ? (int)$_POST['back_cover_artist_id'] : NULL;
    $variant_cover_artist_id = !empty($_POST['variant_cover_artist_id']) ? (int)$_POST['variant_cover_artist_id'] : NULL;
    
    $custom_fields_values = $_POST['custom_fields'] ?? [];
    $temp_custom_field_definitions_for_save = [];
    $result_defs_save = $mysqli->query("SELECT field_key, field_type FROM custom_field_definitions WHERE entity_type = 'comic'");
    if ($result_defs_save) { 
        while ($row_def_save = $result_defs_save->fetch_assoc()) { 
            $temp_custom_field_definitions_for_save[$row_def_save['field_key']] = $row_def_save['field_type'];
        } 
        $result_defs_save->free(); 
    }
    foreach ($temp_custom_field_definitions_for_save as $field_key_save => $field_type_save) {
        if ($field_type_save === 'checkbox') {
            $custom_fields_values[$field_key_save] = isset($custom_fields_values[$field_key_save]) ? "1" : "0";
        }
    }
    $custom_fields_json_proposal = !empty($custom_fields_values) ? json_encode($custom_fields_values) : null;
    $gadget_name = trim($_POST['gadget_name'] ?? '');
 
    $cover_image_path_proposal = $is_editing_action ? ($_POST['current_cover_image'] ?? null) : null;
    $back_cover_image_path_proposal = $is_editing_action ? ($_POST['current_back_cover_image'] ?? null) : null;
    $gadget_image_path_db_proposal = $is_editing_action ? ($_POST['current_gadget_image'] ?? null) : null;
    
    $cover_image_path_proposal = handle_image_upload_action('cover_image', $cover_image_path_proposal, 'delete_cover_image', 'covers', 'topolino_', $issue_number, $is_contributor && !$is_true_admin);
    $back_cover_image_path_proposal = handle_image_upload_action('back_cover_image', $back_cover_image_path_proposal, 'delete_back_cover_image', 'covers/backcovers', 'topolino_back_', $issue_number, $is_contributor && !$is_true_admin);
    $gadget_image_path_db_proposal = handle_image_upload_action('gadget_image', $gadget_image_path_db_proposal, 'delete_gadget_image', 'gadgets', 'gadget_', $issue_number, $is_contributor && !$is_true_admin);

    $staff_proposal_json = null;
    // BLOCCO MODIFICATO: Rimuoviamo 'Copertinista' dalla gestione staff tradizionale poiché ora è gestito separatamente
    $comic_staff_roles_config_from_post = [
        'Direttore Responsabile' => 'staff_director_responsible',
        'Direttore Editoriale' => 'staff_director_editorial', 
        'Redattore Capo' => 'staff_editor_in_chief',
        'Supervisore Artistico' => 'staff_art_supervisor', 
        'Coordinamento Editoriale' => 'staff_editorial_coordinator',
    ];
    $staff_data_for_json = [];
    foreach ($comic_staff_roles_config_from_post as $role_name_in_db => $post_field_key) {
        if (isset($_POST[$post_field_key]) && is_array($_POST[$post_field_key])) {
            foreach ($_POST[$post_field_key] as $person_id_from_post) {
                $person_id_int = (int)$person_id_from_post;
                if ($person_id_int > 0) {
                    $staff_data_for_json[] = ['person_id' => $person_id_int, 'role' => $role_name_in_db];
                }
            }
        }
    }
    if (!empty($staff_data_for_json)) {
        $staff_proposal_json = json_encode($staff_data_for_json);
    }

    $variant_covers_proposal_json = null;
    $variant_proposals_for_json = [];
    if ($is_editing_action && isset($_POST['variant_captions']) && is_array($_POST['variant_captions'])) {
        $original_comic_variants = [];
        if($comic_id_original_for_proposal) {
            $stmt_orig_vc = $mysqli->prepare("SELECT variant_cover_id, image_path, artist_id FROM comic_variant_covers WHERE comic_id = ?");
            $stmt_orig_vc->bind_param("i", $comic_id_original_for_proposal);
            $stmt_orig_vc->execute();
            $res_orig_vc = $stmt_orig_vc->get_result();
            while($v_row = $res_orig_vc->fetch_assoc()){
                $original_comic_variants[$v_row['variant_cover_id']] = ['image_path' => $v_row['image_path'], 'artist_id' => $v_row['artist_id']];
            }
            $stmt_orig_vc->close();
        }

        foreach ($_POST['variant_captions'] as $variant_id_str => $caption) {
            $variant_id_original = (int)$variant_id_str;
            $sort_order_proposal = isset($_POST['variant_sort_order'][$variant_id_original]) ? (int)$_POST['variant_sort_order'][$variant_id_original] : 0;
            $delete_proposal_flag = isset($_POST['delete_variant_covers']) && in_array((string)$variant_id_original, $_POST['delete_variant_covers']);
            
            $artist_id_proposal = isset($_POST['variant_artists'][$variant_id_original]) ? (int)$_POST['variant_artists'][$variant_id_original] : NULL;
            if ($artist_id_proposal === 0) $artist_id_proposal = NULL;

            $current_variant_image_path = $original_comic_variants[$variant_id_original]['image_path'] ?? null;

            $variant_proposals_for_json[] = [
                'variant_cover_id_original' => $variant_id_original,
                'image_path_proposal' => $current_variant_image_path,
                'caption_proposal' => trim($caption),
                'artist_id_proposal' => $artist_id_proposal,
                'sort_order_proposal' => $sort_order_proposal,
                'action' => $delete_proposal_flag ? 'delete_existing' : 'edit_existing'
            ];
        }
    }
    if (isset($_FILES['variant_covers_upload'])) {
        $variant_target_subdir = 'covers/variants';
        $variant_pending_subdir_relative = 'pending_images/' . $variant_target_subdir . '/';
        $variant_final_subdir_relative = $variant_target_subdir . '/';

        foreach ($_FILES['variant_covers_upload']['name'] as $key => $name) {
            if ($_FILES['variant_covers_upload']['error'][$key] == UPLOAD_ERR_OK) {
                $upload_dir_variant_actual_relative = ($is_contributor && !$is_true_admin) ? $variant_pending_subdir_relative : $variant_final_subdir_relative;
                $upload_dir_variant_absolute = UPLOADS_PATH . $upload_dir_variant_actual_relative;
                if (!is_dir($upload_dir_variant_absolute)) mkdir($upload_dir_variant_absolute, 0775, true);

                $tmp_name_variant = $_FILES['variant_covers_upload']['tmp_name'][$key];
                $file_extension_vc = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $safe_issue_number_vc = preg_replace('/[^a-zA-Z0-9_-]/', '_', $issue_number);
                $variant_filename_fs_no_ext = 'topolino_' . $safe_issue_number_vc . '_variant_' . uniqid();
                $variant_target_file_fs_absolute = $upload_dir_variant_absolute . $variant_filename_fs_no_ext . '.' . $file_extension_vc;
                
                if (move_uploaded_file($tmp_name_variant, $variant_target_file_fs_absolute)) {
                    $processed_variant_path = compress_and_optimize_image($variant_target_file_fs_absolute, $variant_target_file_fs_absolute, $file_extension_vc);
                    if ($processed_variant_path) {
                        $variant_image_path_proposal_db = $upload_dir_variant_actual_relative . basename($processed_variant_path);
                        
                        $new_artist_id = isset($_POST['new_variant_artists'][$key]) ? (int)$_POST['new_variant_artists'][$key] : NULL;
                        if ($new_artist_id === 0) $new_artist_id = NULL;

                        $variant_proposals_for_json[] = [
                            'variant_cover_id_original' => null,
                            'image_path_proposal' => $variant_image_path_proposal_db,
                            'caption_proposal' => (isset($_POST['new_variant_captions'][$key]) ? trim($_POST['new_variant_captions'][$key]) : ""),
                            'artist_id_proposal' => $new_artist_id,
                            'sort_order_proposal' => 0,
                            'action' => 'add_new'
                        ];
                    } else {
                        $message .= " Errore compressione file variant: " . htmlspecialchars($name) . "."; $message_type = 'error';
                    }
                } else {
                     $message .= " Errore upload file variant: " . htmlspecialchars($name) . "."; $message_type = 'error';
                }
            } elseif ($_FILES['variant_covers_upload']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                 $message .= " Errore nel file variant " . htmlspecialchars($name) . " (codice: " . $_FILES['variant_covers_upload']['error'][$key] . ")."; $message_type = 'error';
            }
        }
    }
    if (!empty($variant_proposals_for_json)) {
        $variant_covers_proposal_json = json_encode($variant_proposals_for_json);
    }
    
    $gadget_images_proposal_json = null;
    $gadget_gallery_proposals_for_json = [];

    if ($is_editing_action && isset($_POST['existing_gadget_image_ids']) && is_array($_POST['existing_gadget_image_ids'])) {
        $original_gadget_images = [];
        if($comic_id_original_for_proposal) {
            $stmt_orig_gi = $mysqli->prepare("SELECT gadget_image_id, image_path FROM comic_gadget_images WHERE comic_id = ?");
            $stmt_orig_gi->bind_param("i", $comic_id_original_for_proposal);
            $stmt_orig_gi->execute();
            $res_orig_gi = $stmt_orig_gi->get_result();
            while($gi_row = $res_orig_gi->fetch_assoc()){
                $original_gadget_images[$gi_row['gadget_image_id']] = $gi_row['image_path'];
            }
            $stmt_orig_gi->close();
        }
        foreach ($_POST['existing_gadget_image_ids'] as $gadget_image_id_from_post) {
            $gadget_image_id_original = (int)$gadget_image_id_from_post;
            $caption_proposal = $_POST['existing_gadget_image_captions'][$gadget_image_id_original] ?? '';
            $sort_order_proposal = isset($_POST['existing_gadget_image_sort_orders'][$gadget_image_id_original]) ? (int)$_POST['existing_gadget_image_sort_orders'][$gadget_image_id_original] : 0;
            $delete_gadget_img_flag = isset($_POST['delete_gadget_images']) && in_array((string)$gadget_image_id_original, $_POST['delete_gadget_images']);
            $current_gadget_gallery_image_path = $original_gadget_images[$gadget_image_id_original] ?? null;

            $gadget_gallery_proposals_for_json[] = [
                'gadget_image_id_original' => $gadget_image_id_original,
                'image_path_proposal' => $current_gadget_gallery_image_path,
                'caption_proposal' => trim($caption_proposal),
                'sort_order_proposal' => $sort_order_proposal,
                'action' => $delete_gadget_img_flag ? 'delete_existing' : 'edit_existing'
            ];
        }
    }

    if (isset($_FILES['new_gadget_images_upload'])) {
        $gadget_gallery_target_subdir = 'gadgets';
        $gadget_gallery_pending_subdir = 'pending_images/' . $gadget_gallery_target_subdir . '/';
        $gadget_gallery_final_subdir = $gadget_gallery_target_subdir . '/';

        foreach ($_FILES['new_gadget_images_upload']['name'] as $key => $name) {
            if ($_FILES['new_gadget_images_upload']['error'][$key] == UPLOAD_ERR_OK) {
                $upload_dir_gadget_gallery_actual = ($is_contributor && !$is_true_admin) ? $gadget_gallery_pending_subdir : $gadget_gallery_final_subdir;
                $upload_dir_gadget_gallery_absolute = UPLOADS_PATH . $upload_dir_gadget_gallery_actual;
                if (!is_dir($upload_dir_gadget_gallery_absolute)) mkdir($upload_dir_gadget_gallery_absolute, 0775, true);

                $tmp_name_gadget_gallery = $_FILES['new_gadget_images_upload']['tmp_name'][$key];
                $file_extension_gg = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $safe_issue_number_gg = preg_replace('/[^a-zA-Z0-9_-]/', '_', $issue_number);
                $gadget_gallery_filename_no_ext = 'gadget_img_' . $safe_issue_number_gg . '_' . uniqid();
                $gadget_gallery_target_file_absolute = $upload_dir_gadget_gallery_absolute . $gadget_gallery_filename_no_ext . '.' . $file_extension_gg;
                
                if (move_uploaded_file($tmp_name_gadget_gallery, $gadget_gallery_target_file_absolute)) {
                    $processed_gadget_path = compress_and_optimize_image($gadget_gallery_target_file_absolute, $gadget_gallery_target_file_absolute, $file_extension_gg);
                    if ($processed_gadget_path) {
                        $gadget_gallery_path_for_db = $upload_dir_gadget_gallery_actual . basename($processed_gadget_path);
                        $gadget_gallery_proposals_for_json[] = [
                            'gadget_image_id_original' => null,
                            'image_path_proposal' => $gadget_gallery_path_for_db,
                            'caption_proposal' => '', 
                            'sort_order_proposal' => 0,
                            'action' => 'add_new'
                        ];
                    } else {
                        $message .= " Errore compressione file galleria gadget: " . htmlspecialchars($name) . "."; $message_type = 'error';
                    }
                } else {
                     $message .= " Errore upload file galleria gadget: " . htmlspecialchars($name) . "."; $message_type = 'error';
                }
            } elseif ($_FILES['new_gadget_images_upload']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                 $message .= " Errore nel file galleria gadget " . htmlspecialchars($name) . " (codice: " . $_FILES['new_gadget_images_upload']['error'][$key] . ")."; $message_type = 'error';
            }
        }
    }
    if (!empty($gadget_gallery_proposals_for_json)) {
        $gadget_images_proposal_json = json_encode($gadget_gallery_proposals_for_json);
    }

    if (empty($issue_number)) {
        $message .= " Il 'Numero Albo' è obbligatorio.";
        $message_type = 'error';
    }

    if ($message_type !== 'error') {
        $mysqli->begin_transaction();
        try {
            if ($is_contributor && !$is_true_admin) {
                $action_type_proposal = $is_editing_action ? 'edit' : 'add';
                
                // Approccio semplificato: costruiamo la query solo con i campi base che sicuramente esistono
                $fields = [
                    'comic_id_original', 'proposer_user_id', 'action_type', 
                    'issue_number', 'title', 'publication_date', 'description', 
                    'cover_image_proposal', 'back_cover_image_proposal', 
                    'editor', 'pages', 'price', 'periodicity', 
                    'custom_fields_proposal', 'gadget_name_proposal', 'gadget_image_proposal'
                ];
                
                $values = [
                    $comic_id_original_for_proposal, $current_user_id_for_proposal, $action_type_proposal,
                    $issue_number, $title, $publication_date, $description,
                    $cover_image_path_proposal, $back_cover_image_path_proposal,
                    $editor, $pages, $price, $periodicity,
                    $custom_fields_json_proposal, $gadget_name, $gadget_image_path_db_proposal
                ];
                
                // Per ora usiamo solo i campi base - 2 interi + 14 stringhe
                $types = "iissssssssssssss";
                
                error_log("DEBUG SEMPLIFICATO - Campi: " . count($fields) . " | Valori: " . count($values) . " | Tipi: " . strlen($types));
                
                $fields_string = implode(', ', $fields);
                $placeholders = str_repeat('?, ', count($fields) - 1) . '?';
                
                $stmt_pending = $mysqli->prepare("INSERT INTO pending_comics ($fields_string) VALUES ($placeholders)");
                
                if (!$stmt_pending) {
                    throw new Exception("Errore preparazione query pending_comics: " . $mysqli->error);
                }
                
                // Controllo finale
                if (strlen($types) !== count($values) || count($fields) !== count($values)) {
                    throw new Exception("Errore parametri SEMPLIFICATO - Campi: " . count($fields) . ", Valori: " . count($values) . ", Tipi: " . strlen($types));
                }
                
                $stmt_pending->bind_param($types, ...$values);

                if (!$stmt_pending->execute()) { 
                    throw new Exception("Errore DB (insert pending_comic): " . $stmt_pending->error); 
                }
                $stmt_pending->close();
                $_SESSION['message'] = "Proposta inviata con successo per revisione!";
                $_SESSION['message_type'] = 'success';
                $mysqli->commit();
                header('Location: ' . BASE_URL . 'admin/comics_manage.php?action=list&message=' . urlencode($_SESSION['message']) . '&message_type=success');
                exit;

            } else { // Logica per gli Admin
                $slug = generate_slug(!empty($title) ? $title : ('topolino-' . $issue_number), $mysqli, 'comics', 'slug', $is_editing_action ? $comic_id_original_for_proposal : 0, 'comic_id');
                
                $current_comic_id_for_processing = null;
                if (!$is_editing_action) {
                    // QUERY MODIFICATA: Aggiungiamo i campi JSON degli artisti
                    $stmt = $mysqli->prepare("INSERT INTO comics (issue_number, title, slug, publication_date, description, cover_image, back_cover_image, editor, pages, price, periodicity, custom_fields, gadget_name, gadget_image, cover_artists_json, back_cover_artists_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssssssssssssss", $issue_number, $title, $slug, $publication_date, $description, $cover_image_path_proposal, $back_cover_image_path_proposal, $editor, $pages, $price, $periodicity, $custom_fields_json_proposal, $gadget_name, $gadget_image_path_db_proposal, $cover_artists_json, $back_cover_artists_json);
                    if (!$stmt->execute()) {
                        if ($mysqli->errno == 1062) throw new Exception("Errore: Esiste già un albo con numero \"".htmlspecialchars($issue_number)."\" o con uno slug simile.");
                        else throw new Exception("Errore DB (insert comic): " . $stmt->error);
                    }
                    $current_comic_id_for_processing = $mysqli->insert_id;
                    $stmt->close();
                    $_SESSION['message'] = "Fumetto aggiunto con successo!";
                } else {
                    $current_comic_id_for_processing = $comic_id_original_for_proposal;
                    // QUERY MODIFICATA: Aggiungiamo i campi JSON degli artisti
                    $stmt = $mysqli->prepare("UPDATE comics SET issue_number = ?, title = ?, slug = ?, publication_date = ?, description = ?, cover_image = ?, back_cover_image = ?, editor = ?, pages = ?, price = ?, periodicity = ?, custom_fields = ?, gadget_name = ?, gadget_image = ?, cover_artists_json = ?, back_cover_artists_json = ? WHERE comic_id = ?");
                    $stmt->bind_param("ssssssssssssssssi", $issue_number, $title, $slug, $publication_date, $description, $cover_image_path_proposal, $back_cover_image_path_proposal, $editor, $pages, $price, $periodicity, $custom_fields_json_proposal, $gadget_name, $gadget_image_path_db_proposal, $cover_artists_json, $back_cover_artists_json, $current_comic_id_for_processing);
                     if (!$stmt->execute()) {
                        if ($mysqli->errno == 1062) throw new Exception("Errore: Il numero albo \"".htmlspecialchars($issue_number)."\" o lo slug generato sono già usati da un altro fumetto.");
                        else throw new Exception("Errore DB (update comic): " . $stmt->error);
                    }
                    $stmt->close();
                    $_SESSION['message'] = "Fumetto modificato con successo!";
                }

                if ($current_comic_id_for_processing && $is_true_admin) {
                     // BLOCCO MODIFICATO: Aggiungiamo la gestione degli artisti di copertina nella tabella comic_persons
                     if (!empty($staff_proposal_json) || ($is_editing_action && $staff_proposal_json === null) ) {
                        // Rimuoviamo prima tutti i ruoli tradizionali (non copertinisti)
                        $stmt_delete_staff_direct = $mysqli->prepare("DELETE FROM comic_persons WHERE comic_id = ? AND role != 'Copertinista'");
                        $stmt_delete_staff_direct->bind_param("i", $current_comic_id_for_processing);
                        $stmt_delete_staff_direct->execute();
                        $stmt_delete_staff_direct->close();

                        // Decodifichiamo i dati dello staff dal JSON
                        $staff_data_decoded = $staff_proposal_json ? json_decode($staff_proposal_json, true) : [];
                        if(!empty($staff_data_decoded)){
                            $stmt_insert_staff_direct = $mysqli->prepare("INSERT INTO comic_persons (comic_id, person_id, role) VALUES (?, ?, ?)");
                            foreach($staff_data_decoded as $staff_entry) {
                                $stmt_insert_staff_direct->bind_param("iis", $current_comic_id_for_processing, $staff_entry['person_id'], $staff_entry['role']);
                                if (!$stmt_insert_staff_direct->execute()) throw new Exception("Errore DB (insert staff): " . $stmt_insert_staff_direct->error);
                            }
                            $stmt_insert_staff_direct->close();
                        }
                    }

                    // BLOCCO AGGIUNTO: Gestione degli artisti di copertina nella tabella comic_persons
                    // Rimuoviamo tutti i copertinisti esistenti
                    $stmt_delete_cover_artists = $mysqli->prepare("DELETE FROM comic_persons WHERE comic_id = ? AND role = 'Copertinista'");
                    $stmt_delete_cover_artists->bind_param("i", $current_comic_id_for_processing);
                    $stmt_delete_cover_artists->execute();
                    $stmt_delete_cover_artists->close();
                    
                    // Aggiungiamo i nuovi copertinisti (sia copertina che retrocopertina)
                    $all_cover_artists = [];
                    
                    if ($cover_artists_json) {
                        $cover_artists_array = json_decode($cover_artists_json, true);
                        if ($cover_artists_array) {
                            $all_cover_artists = array_merge($all_cover_artists, $cover_artists_array);
                        }
                    }
                    
                    if ($back_cover_artists_json) {
                        $back_cover_artists_array = json_decode($back_cover_artists_json, true);
                        if ($back_cover_artists_array) {
                            $all_cover_artists = array_merge($all_cover_artists, $back_cover_artists_array);
                        }
                    }
                    
                    // Rimuoviamo duplicati
                    $all_cover_artists = array_unique($all_cover_artists);
                    
                    // Inserisci nella tabella comic_persons
                    if (!empty($all_cover_artists)) {
                        $stmt_insert_cover = $mysqli->prepare("INSERT INTO comic_persons (comic_id, person_id, role) VALUES (?, ?, 'Copertinista')");
                        foreach ($all_cover_artists as $artist_id) {
                            $stmt_insert_cover->bind_param("ii", $current_comic_id_for_processing, $artist_id);
                            $stmt_insert_cover->execute();
                        }
                        $stmt_insert_cover->close();
                    }
                    // FINE BLOCCO AGGIUNTO

                    $variant_proposals_data = $variant_covers_proposal_json ? json_decode($variant_covers_proposal_json, true) : [];
                    if (!empty($variant_proposals_data)) {
                        $stmt_ins_vc_admin = $mysqli->prepare("INSERT INTO comic_variant_covers (comic_id, image_path, caption, artist_id, sort_order) VALUES (?, ?, ?, ?, ?)");
                        $stmt_upd_vc_admin = $mysqli->prepare("UPDATE comic_variant_covers SET caption = ?, artist_id = ?, sort_order = ? WHERE variant_cover_id = ? AND comic_id = ?");
                        $stmt_del_vc_admin_db = $mysqli->prepare("DELETE FROM comic_variant_covers WHERE variant_cover_id = ? AND comic_id = ?");
                        $stmt_get_img_vc_admin = $mysqli->prepare("SELECT image_path FROM comic_variant_covers WHERE variant_cover_id = ? AND comic_id = ?");
                        foreach ($variant_proposals_data as $vp_action) {
                            $v_action_type = $vp_action['action'] ?? null;
                            $v_id_original_for_action = $vp_action['variant_cover_id_original'] ?? null;
                            $v_image_path_for_action = $vp_action['image_path_proposal'] ?? null;
                            $v_caption_for_action = $vp_action['caption_proposal'] ?? '';
                            $v_artist_id_for_action = $vp_action['artist_id_proposal'] ?? null;
                            $v_sort_order_for_action = $vp_action['sort_order_proposal'] ?? 0;

                            if ($v_action_type === 'delete_existing' && $v_id_original_for_action) {
                                $stmt_get_img_vc_admin->bind_param("ii", $v_id_original_for_action, $current_comic_id_for_processing);
                                $stmt_get_img_vc_admin->execute();
                                if ($img_path_del_row = $stmt_get_img_vc_admin->get_result()->fetch_assoc()) {
                                    if ($img_path_del_row['image_path'] && file_exists(UPLOADS_PATH . $img_path_del_row['image_path'])) {
                                        @unlink(UPLOADS_PATH . $img_path_del_row['image_path']);
                                    }
                                }
                                $stmt_del_vc_admin_db->bind_param("ii", $v_id_original_for_action, $current_comic_id_for_processing);
                                if (!$stmt_del_vc_admin_db->execute()) throw new Exception("Errore DB (delete variant by admin): " . $stmt_del_vc_admin_db->error);
                            } elseif ($v_action_type === 'edit_existing' && $v_id_original_for_action) {
                                $stmt_upd_vc_admin->bind_param("siiii", $v_caption_for_action, $v_artist_id_for_action, $v_sort_order_for_action, $v_id_original_for_action, $current_comic_id_for_processing);
                                if (!$stmt_upd_vc_admin->execute()) throw new Exception("Errore DB (update variant by admin): " . $stmt_upd_vc_admin->error);
                            } elseif ($v_action_type === 'add_new' && !empty($v_image_path_for_action)) {
                                $stmt_ins_vc_admin->bind_param("issii", $current_comic_id_for_processing, $v_image_path_for_action, $v_caption_for_action, $v_artist_id_for_action, $v_sort_order_for_action);
                                if (!$stmt_ins_vc_admin->execute()) throw new Exception("Errore DB (insert new variant by admin): " . $stmt_ins_vc_admin->error);
                            }
                        }
                        $stmt_ins_vc_admin->close(); $stmt_upd_vc_admin->close(); $stmt_del_vc_admin_db->close(); $stmt_get_img_vc_admin->close();
                    }

                    $gadget_gallery_data = $gadget_images_proposal_json ? json_decode($gadget_images_proposal_json, true) : [];
                    if (!empty($gadget_gallery_data)) {
                        $stmt_ins_gi_admin = $mysqli->prepare("INSERT INTO comic_gadget_images (comic_id, image_path, caption, sort_order) VALUES (?, ?, ?, ?)");
                        $stmt_upd_gi_admin = $mysqli->prepare("UPDATE comic_gadget_images SET caption = ?, sort_order = ? WHERE gadget_image_id = ? AND comic_id = ?");
                        $stmt_del_gi_admin_db = $mysqli->prepare("DELETE FROM comic_gadget_images WHERE gadget_image_id = ? AND comic_id = ?");
                        $stmt_get_img_gi_admin = $mysqli->prepare("SELECT image_path FROM comic_gadget_images WHERE gadget_image_id = ? AND comic_id = ?");
                        
                        foreach ($gadget_gallery_data as $gg_action) {
                            $gg_action_type = $gg_action['action'] ?? null;
                            $gg_id_original = $gg_action['gadget_image_id_original'] ?? null;
                            $gg_image_path = $gg_action['image_path_proposal'] ?? null;
                            $gg_caption = $gg_action['caption_proposal'] ?? '';
                            $gg_sort_order = $gg_action['sort_order_proposal'] ?? 0;

                            if ($gg_action_type === 'delete_existing' && $gg_id_original) {
                                $stmt_get_img_gi_admin->bind_param("ii", $gg_id_original, $current_comic_id_for_processing);
                                $stmt_get_img_gi_admin->execute();
                                if ($img_path_del_row = $stmt_get_img_gi_admin->get_result()->fetch_assoc()) {
                                    if ($img_path_del_row['image_path'] && file_exists(UPLOADS_PATH . $img_path_del_row['image_path'])) {
                                        @unlink(UPLOADS_PATH . $img_path_del_row['image_path']);
                                    }
                                }
                                $stmt_del_gi_admin_db->bind_param("ii", $gg_id_original, $current_comic_id_for_processing);
                                if (!$stmt_del_gi_admin_db->execute()) throw new Exception("Errore DB (delete gadget image by admin): " . $stmt_del_gi_admin_db->error);
                            } elseif ($gg_action_type === 'edit_existing' && $gg_id_original) {
                                $stmt_upd_gi_admin->bind_param("siii", $gg_caption, $gg_sort_order, $gg_id_original, $current_comic_id_for_processing);
                                if (!$stmt_upd_gi_admin->execute()) throw new Exception("Errore DB (update gadget image by admin): " . $stmt_upd_gi_admin->error);
                            } elseif ($gg_action_type === 'add_new' && !empty($gg_image_path)) {
                                $stmt_ins_gi_admin->bind_param("issi", $current_comic_id_for_processing, $gg_image_path, $gg_caption, $gg_sort_order);
                                if (!$stmt_ins_gi_admin->execute()) throw new Exception("Errore DB (insert new gadget image by admin): " . $stmt_ins_gi_admin->error);
                            }
                        }
                        $stmt_ins_gi_admin->close(); $stmt_upd_gi_admin->close(); $stmt_del_gi_admin_db->close(); $stmt_get_img_gi_admin->close();
                    }
                }
                
                if ($current_comic_id_for_processing && !empty($issue_number) && $is_true_admin && !$is_editing_action) {
                    $placeholders_to_migrate = [];
                    $stmt_find_placeholders = $mysqli->prepare("SELECT placeholder_id, user_id FROM user_collection_placeholders WHERE issue_number_placeholder = ? AND status = 'pending'");
                    $stmt_find_placeholders->bind_param("s", $issue_number); $stmt_find_placeholders->execute(); $result_placeholders = $stmt_find_placeholders->get_result();
                    while ($ph_row = $result_placeholders->fetch_assoc()) $placeholders_to_migrate[] = $ph_row;
                    $stmt_find_placeholders->close();
                    if (!empty($placeholders_to_migrate)) {
                        $migrated_count = 0;
                        $stmt_add_to_real_collection = $mysqli->prepare("INSERT INTO user_collections (user_id, comic_id, added_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE comic_id = VALUES(comic_id)");
                        $stmt_delete_placeholder = $mysqli->prepare("DELETE FROM user_collection_placeholders WHERE placeholder_id = ?");
                        foreach ($placeholders_to_migrate as $placeholder) {
                            $stmt_add_to_real_collection->bind_param("ii", $placeholder['user_id'], $current_comic_id_for_processing);
                            if ($stmt_add_to_real_collection->execute()) { $stmt_delete_placeholder->bind_param("i", $placeholder['placeholder_id']); if ($stmt_delete_placeholder->execute()) $migrated_count++; }
                        }
                        $stmt_add_to_real_collection->close(); $stmt_delete_placeholder->close();
                        if ($migrated_count > 0) $_SESSION['message'] .= " ($migrated_count segnalazioni migrate.)";
                    }
                }
                
                $mysqli->commit();
                $_SESSION['message_type'] = 'success';
                
                $redirect_url_params = ['action=edit', 'id=' . $current_comic_id_for_processing];
                header('Location: ' . BASE_URL . 'admin/comics_manage.php?' . implode('&', $redirect_url_params) );
                exit;
            }

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['message'] = "ERRORE TRANSAZIONE: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            error_log("Errore transazione in comics_actions.php: " . $e->getMessage());
        }
    } else {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'error';
    }

    $_SESSION['form_data_comics'] = $_POST;
    $_SESSION['form_data_comics']['cover_image_path_proposal_error_handling'] = $cover_image_path_proposal;
    $_SESSION['form_data_comics']['back_cover_image_path_proposal_error_handling'] = $back_cover_image_path_proposal;
    $_SESSION['form_data_comics']['gadget_image_path_db_proposal_error_handling'] = $gadget_image_path_db_proposal;

    $redirect_to_form_url = BASE_URL . 'admin/comics_manage.php?action=' . ($is_editing_action ? 'edit&id=' . $comic_id_original_for_proposal : 'add');
    header('Location: ' . $redirect_to_form_url);
    exit;

} else {
    $_SESSION['message'] = "Azione non valida.";
    $_SESSION['message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/comics_manage.php');
    exit;
}

$mysqli->close();
?>