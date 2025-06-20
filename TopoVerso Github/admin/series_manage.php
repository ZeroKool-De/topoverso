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


$page_title = "Gestione Serie Storie";
require_once ROOT_PATH . 'admin/includes/header_admin.php';

$message = '';
$message_type = '';

$action = $_GET['action'] ?? 'list';
$series_id_to_edit_original = null; // ID originale se in modifica
$series_data = ['title' => '', 'description' => '', 'image_path' => null, 'start_date' => ''];

if ($action === 'edit' && isset($_GET['id'])) {
    $series_id_to_edit_original = (int)$_GET['id'];
    $stmt = $mysqli->prepare("SELECT series_id, title, description, image_path, start_date FROM story_series WHERE series_id = ?");
    $stmt->bind_param("i", $series_id_to_edit_original);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $series_data = $result->fetch_assoc();
    } else {
        $_SESSION['message'] = "Serie non trovata.";
        $_SESSION['message_type'] = 'error';
        header('Location: ' . BASE_URL . 'admin/series_manage.php');
        exit;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_editing_form_action = isset($_POST['edit_series']);
    $series_id_original_from_post = $is_editing_form_action ? (int)$_POST['series_id'] : null;

    $title_proposal = trim($_POST['title']);
    $description_proposal = trim($_POST['description']);
    $start_date_proposal = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
    $current_image_path_on_form = $_POST['current_image_path'] ?? null;
    $image_path_proposal = $is_editing_form_action ? $current_image_path_on_form : null;


    if (empty($title_proposal)) {
        $message = "Il titolo della serie è obbligatorio.";
        $message_type = 'error';
    } else {
        // Funzione helper per upload immagine serie (simile alle altre)
        function handle_series_image_upload($file_input_name, $current_image_path, $delete_flag_name, $is_editing_action_flag, $is_contributor_upload_flag) {
            global $message, $message_type; // Assicurati che queste siano accessibili
            $image_path_to_return = $current_image_path;
            $base_upload_path = UPLOADS_PATH;
            $target_subdir = 'series_images'; // Sottocartella specifica per le immagini delle serie
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
                        $message .= " Errore: Impossibile creare la cartella di upload per serie: " . htmlspecialchars($upload_dir_absolute) . ".";
                        $message_type = 'error';
                        return $image_path_to_return;
                    }
                }
                $original_filename = $_FILES[$file_input_name]['name'];
                $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($file_extension, $allowed_extensions)) {
                    $message .= " Formato file non permesso per l'immagine della serie.";
                    $message_type = 'error';
                    return $image_path_to_return;
                }

                $temp_filename_no_ext = 'series_' . uniqid('', true) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
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
                        error_log("Compressione fallita per $file_input_name (serie), usando $image_path_to_return");
                    }
                } else {
                    $message .= " Errore durante il caricamento dell'immagine della serie.";
                    $message_type = 'error';
                }
            }
            return $image_path_to_return;
        }
        
        $image_path_proposal = handle_series_image_upload('series_image', $image_path_proposal, 'delete_series_image', $is_editing_form_action, $is_contributor && !$is_true_admin);


        if ($message_type !== 'error') {
            if ($is_contributor && !$is_true_admin) {
                // SALVA PROPOSTA PER CONTRIBUTORE
                $action_type_proposal = $is_editing_form_action ? 'edit' : 'add';
                $stmt_pending = $mysqli->prepare("INSERT INTO pending_story_series 
                    (series_id_original, proposer_user_id, action_type, title_proposal, description_proposal, image_path_proposal, start_date_proposal)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_pending->bind_param("iisssss", 
                    $series_id_original_from_post, $current_user_id_for_proposal, $action_type_proposal,
                    $title_proposal, $description_proposal, $image_path_proposal, $start_date_proposal);
                
                if ($stmt_pending->execute()) {
                    $_SESSION['message'] = "Proposta per la serie inviata con successo per revisione!";
                    $_SESSION['message_type'] = 'success';
                } else {
                    if ($mysqli->errno == 1062 && str_contains(strtolower($stmt_pending->error), 'title')) {
                         $_SESSION['message'] = "Errore: Esiste già una proposta pendente o una serie con questo titolo.";
                    } else {
                        $_SESSION['message'] = "Errore SQL invio proposta: " . $stmt_pending->error;
                    }
                    $_SESSION['message_type'] = 'error';
                }
                $stmt_pending->close();
                header('Location: ' . BASE_URL . 'admin/series_manage.php?action=list'); // Reindirizza alla lista
                exit;

            } else { // GESTIONE ADMIN
                // --- INIZIO BLOCCO MODIFICATO ---
                $exclude_id_for_slug = $is_editing_form_action ? $series_id_original_from_post : 0;
                $slug = generate_slug($title_proposal, $mysqli, 'story_series', 'slug', $exclude_id_for_slug, 'series_id');
                // --- FINE BLOCCO MODIFICATO ---
                
                if (isset($_POST['add_series'])) {
                    $stmt = $mysqli->prepare("INSERT INTO story_series (title, slug, description, image_path, start_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $title_proposal, $slug, $description_proposal, $image_path_proposal, $start_date_proposal); // Aggiunto slug
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Serie aggiunta con successo!";
                        $_SESSION['message_type'] = 'success';
                    } else {
                        if ($mysqli->errno == 1062) { 
                            $_SESSION['message'] = "Errore: Esiste già una serie con questo titolo o slug.";
                        } else {
                            $_SESSION['message'] = "Errore SQL: " . $stmt->error;
                        }
                        $_SESSION['message_type'] = 'error';
                        if ($image_path_proposal && $image_path_proposal !== $current_image_path_on_form) { 
                            if(file_exists(UPLOADS_PATH . $image_path_proposal)) unlink(UPLOADS_PATH . $image_path_proposal);
                        }
                    }
                    $stmt->close();
                } elseif (isset($_POST['edit_series']) && isset($_POST['series_id'])) {
                    $series_id_update = (int)$_POST['series_id'];
                    $stmt = $mysqli->prepare("UPDATE story_series SET title = ?, slug = ?, description = ?, image_path = ?, start_date = ? WHERE series_id = ?");
                    $stmt->bind_param("sssssi", $title_proposal, $slug, $description_proposal, $image_path_proposal, $start_date_proposal, $series_id_update); // Aggiunto slug
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Serie modificata con successo!";
                        $_SESSION['message_type'] = 'success';
                    } else {
                         if ($mysqli->errno == 1062) {
                            $_SESSION['message'] = "Errore: Esiste già un'altra serie con questo titolo o slug.";
                        } else {
                            $_SESSION['message'] = "Errore SQL: " . $stmt->error;
                        }
                        $_SESSION['message_type'] = 'error';
                    }
                    $stmt->close();
                }
                header('Location: ' . BASE_URL . 'admin/series_manage.php');
                exit;
            }
        }
    }
    // Se errore, ripopola dati per il form
    $series_data = ['title' => $title_proposal, 'description' => $description_proposal, 'image_path' => $image_path_proposal, 'start_date' => $start_date_proposal];
    if(isset($_POST['series_id'])) $series_id_to_edit_original = (int)$_POST['series_id']; 
}

if ($action === 'delete' && isset($_GET['id'])) {
    // SOLO ADMIN possono eliminare
    if (!$is_true_admin) {
        $_SESSION['message'] = "Azione non permessa."; $_SESSION['message_type'] = 'error';
        header('Location: ' . BASE_URL . 'admin/series_manage.php'); exit;
    }

    $series_id_to_delete = (int)$_GET['id'];
    $stmt_img = $mysqli->prepare("SELECT image_path FROM story_series WHERE series_id = ?");
    $stmt_img->bind_param("i", $series_id_to_delete); $stmt_img->execute(); $result_img = $stmt_img->get_result();
    $img_to_delete = null; if($row_img = $result_img->fetch_assoc()) $img_to_delete = $row_img['image_path'];
    $stmt_img->close();

    $stmt_del = $mysqli->prepare("DELETE FROM story_series WHERE series_id = ?");
    $stmt_del->bind_param("i", $series_id_to_delete);
    if ($stmt_del->execute()) {
        if ($stmt_del->affected_rows > 0) {
            if ($img_to_delete && file_exists(UPLOADS_PATH . $img_to_delete)) {
                unlink(UPLOADS_PATH . $img_to_delete);
            }
            $_SESSION['message'] = "Serie eliminata con successo. Le storie precedentemente collegate non appartengono più a questa serie.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Nessuna serie trovata con questo ID.";
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = "Errore durante l'eliminazione della serie: " . $stmt_del->error;
        $_SESSION['message_type'] = 'error';
    }
    $stmt_del->close();
    header('Location: ' . BASE_URL . 'admin/series_manage.php');
    exit;
}


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

?>
<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?> <?php if ($is_contributor && !$is_true_admin) echo "(Modalità Contributore)"; ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <h3>
            <?php 
            if ($action === 'add') echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuova Serie' : 'Aggiungi Nuova Serie';
            else echo ($is_contributor && !$is_true_admin) ? 'Proponi Modifiche alla Serie' : 'Modifica Serie';
            ?>
        </h3>
        <?php if ($is_contributor && !$is_true_admin): ?>
            <p class="message info" style="font-size:0.9em;">
                <strong>Nota per i Contributori:</strong> Le tue proposte verranno inviate per approvazione. Le immagini caricate saranno temporanee.
            </p>
        <?php endif; ?>

        <form action="series_manage.php<?php echo $action === 'edit' ? '?action=edit&id='.$series_id_to_edit_original : ''; ?>" method="POST" enctype="multipart/form-data">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="edit_series" value="1">
                <input type="hidden" name="series_id" value="<?php echo $series_id_to_edit_original; ?>">
                <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($series_data['image_path'] ?? ''); ?>">
            <?php else: ?>
                <input type="hidden" name="add_series" value="1">
            <?php endif; ?>

            <div class="form-group">
                <label for="title">Titolo Serie:</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($series_data['title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="start_date">Data Inizio Serie (opzionale):</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($series_data['start_date'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="description">Descrizione (opzionale):</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($series_data['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="series_image">Immagine Rappresentativa (opzionale):</label>
                <input type="file" id="series_image" name="series_image" class="form-control-file">
                <?php if ($action === 'edit' && !empty($series_data['image_path'])): ?>
                    <p style="margin-top:10px;">Immagine attuale: 
                        <img src="<?php echo UPLOADS_URL . htmlspecialchars($series_data['image_path']); ?>" alt="Immagine serie" style="width: 100px; height: auto; margin-top: 5px; border:1px solid #ccc;">
                    </p>
                    <label class="inline-label"><input type="checkbox" name="delete_series_image" value="1"> Cancella immagine attuale</label>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-success">
                     <?php 
                        if ($action === 'add') echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Serie' : 'Aggiungi Serie';
                        else echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Modifiche' : 'Salva Modifiche';
                    ?>
                </button>
                <a href="series_manage.php" class="btn btn-secondary">Annulla</a>
            </div>
        </form>
    <?php else: ?>
        <p><a href="?action=add" class="btn btn-primary">
            <?php echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuova Serie' : 'Aggiungi Nuova Serie'; ?>
        </a></p>
        <h3>Elenco Serie</h3>
        <?php
        $result_list = $mysqli->query("SELECT series_id, title, LEFT(description, 100) as short_desc, image_path, created_at, start_date FROM story_series ORDER BY title ASC");
        if ($result_list && $result_list->num_rows > 0):
        ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Immagine</th>
                        <th>Titolo</th>
                        <th>Descrizione (inizio)</th>
                        <th>Data Inizio</th>
                        <th>Data Creazione</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result_list->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if ($row['image_path']): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($row['image_path']); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>" style="width: 50px; height: auto; border-radius:3px;">
                            <?php else: echo generate_image_placeholder(htmlspecialchars($row['title']), 50, 50); endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['short_desc']); ?>...</td>
                        <td><?php echo $row['start_date'] ? format_date_italian($row['start_date'], "d/m/Y") : 'N/D'; ?></td>
                        <td><?php echo format_date_italian($row['created_at'], "d/m/Y H:i"); ?></td>
                        <td style="white-space: nowrap;">
                            <a href="?action=edit&id=<?php echo $row['series_id']; ?>" class="btn btn-sm btn-warning">
                                <?php echo ($is_contributor && !$is_true_admin) ? 'Prop. Mod.' : 'Modifica'; ?>
                            </a>
                            <?php if ($is_true_admin): // Solo Admin può eliminare ?>
                            <a href="?action=delete&id=<?php echo $row['series_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa serie? Le storie associate non verranno eliminate ma scollegate dalla serie.');">Elimina</a>
                            <?php endif; ?>
                            </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nessuna serie definita. <a href="?action=add">Aggiungine una!</a></p>
        <?php endif; ?>
        <?php if($result_list) $result_list->free(); ?>
    <?php endif; ?>
</div>
<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
$mysqli->close();
?>