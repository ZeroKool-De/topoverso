<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// CONTROLLO ACCESSO
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$is_true_admin = isset($_SESSION['admin_user_id']);
$is_contributor = (isset($_SESSION['user_id_frontend']) && isset($_SESSION['user_role_frontend']) && $_SESSION['user_role_frontend'] === 'contributor');
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

$page_title = "Gestione Numeri Topolino";
$message = '';
$message_type = '';
$action = $_GET['action'] ?? 'list';
$search_term_get = isset($_GET['search']) ? trim($_GET['search']) : '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
} elseif (isset($_GET['message'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
    $message_type = isset($_GET['message_type']) ? htmlspecialchars($_GET['message_type']) : 'info';
}

$all_persons_list = [];
$custom_field_definitions = [];
$periodicity_options = [
    '' => '-- Seleziona Periodicità --', 'Settimanale' => 'Settimanale', 'Quindicinale' => 'Quindicinale', 'Mensile' => 'Mensile',
    'Bimestrale' => 'Bimestrale', 'Trimestrale' => 'Trimestrale', 'Semestrale' => 'Semestrale', 'Annuale' => 'Annuale',
    'Altro' => 'Altro', 'Non specificata' => 'Non specificata'
];

if ($action === 'add' || $action === 'edit') {
    $result_defs = $mysqli->query("SELECT field_key, field_label, field_type FROM custom_field_definitions WHERE entity_type = 'comic' ORDER BY field_label ASC");
    if ($result_defs) { while ($row_def = $result_defs->fetch_assoc()) { $custom_field_definitions[] = $row_def; } $result_defs->free(); }

    $result_all_persons = $mysqli->query("SELECT person_id, name FROM persons ORDER BY name ASC");
    if ($result_all_persons) { while ($person_row = $result_all_persons->fetch_assoc()) { $all_persons_list[] = $person_row; } $result_all_persons->free(); }
}

$comic_id_to_edit = null;
$comic_data = [
    'issue_number' => '', 'title' => '', 'publication_date' => '', 'description' => '', 'cover_image' => null,
    'back_cover_image' => null, 'editor' => '', 'pages' => '', 'price' => '', 'periodicity' => '',
    'custom_fields' => [], 'gadget_name' => '', 'gadget_image' => null, 
    'variant_covers_list' => [], 'gadget_images_list' => [],
    'cover_artist_id' => null, 'back_cover_artist_id' => null, // Campi legacy
    'cover_artists_json' => null, 'back_cover_artists_json' => null // Nuovi campi JSON
];

if (isset($_SESSION['form_data_comics'])) {
    $posted_data = $_SESSION['form_data_comics'];
    $comic_data['issue_number'] = $posted_data['issue_number'] ?? '';
    $comic_data['title'] = $posted_data['title'] ?? '';
    $comic_data['publication_date'] = $posted_data['publication_date'] ?? '';
    $comic_data['description'] = $posted_data['description'] ?? '';
    $comic_data['editor'] = $posted_data['editor'] ?? '';
    $comic_data['pages'] = $posted_data['pages'] ?? '';
    $comic_data['price'] = $posted_data['price'] ?? '';
    $comic_data['periodicity'] = $posted_data['periodicity'] ?? '';
    $comic_data['gadget_name'] = $posted_data['gadget_name'] ?? '';
    
    // Ripopola anche i dati degli artisti se presenti nella sessione
    if (isset($posted_data['cover_artists'])) {
        $comic_data['cover_artists_json'] = json_encode($posted_data['cover_artists']);
    }
    if (isset($posted_data['back_cover_artists'])) {
        $comic_data['back_cover_artists_json'] = json_encode($posted_data['back_cover_artists']);
    }
    
    // Gestione immagini da sessione in caso di errore
    if (isset($posted_data['cover_image_path_proposal_error_handling'])) {
        $comic_data['cover_image'] = $posted_data['cover_image_path_proposal_error_handling'];
    }
    if (isset($posted_data['back_cover_image_path_proposal_error_handling'])) {
        $comic_data['back_cover_image'] = $posted_data['back_cover_image_path_proposal_error_handling'];
    }
    if (isset($posted_data['gadget_image_path_db_proposal_error_handling'])) {
        $comic_data['gadget_image'] = $posted_data['gadget_image_path_db_proposal_error_handling'];
    }
    
    unset($_SESSION['form_data_comics']);
} elseif ($action === 'add' && isset($_GET['prefill_issue_number'])) {
    $comic_data['issue_number'] = htmlspecialchars(trim($_GET['prefill_issue_number']));
} elseif ($action === 'edit' && isset($_GET['id'])) {
    $comic_id_to_edit = (int)$_GET['id'];
    // BLOCCO MODIFICATO: Query aggiornata per recuperare i campi JSON degli artisti
    $stmt = $mysqli->prepare("
        SELECT comic_id, issue_number, title, publication_date, description, cover_image, 
               back_cover_image, editor, pages, price, periodicity, custom_fields, gadget_name, 
               gadget_image, cover_artist_id, back_cover_artist_id,
               cover_artists_json, back_cover_artists_json
        FROM comics WHERE comic_id = ?
    ");
    $stmt->bind_param("i", $comic_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $fetched_comic_data = $result->fetch_assoc();
        $comic_data = array_merge($comic_data, $fetched_comic_data);
        $comic_data['custom_fields'] = !empty($comic_data['custom_fields']) ? json_decode($comic_data['custom_fields'], true) : [];
        
        // BLOCCO MODIFICATO: Query aggiornata per recuperare l'artista di ogni variant
        $stmt_vc = $mysqli->prepare("
            SELECT variant_cover_id, image_path, caption, sort_order, artist_id 
            FROM comic_variant_covers 
            WHERE comic_id = ? ORDER BY sort_order ASC, variant_cover_id ASC
        ");
        $stmt_vc->bind_param("i", $comic_id_to_edit);
        $stmt_vc->execute();
        $result_vc = $stmt_vc->get_result();
        while($vc_row = $result_vc->fetch_assoc()){
            $comic_data['variant_covers_list'][] = $vc_row;
        }
        $stmt_vc->close();

        $stmt_gi = $mysqli->prepare("SELECT gadget_image_id, image_path, caption, sort_order FROM comic_gadget_images WHERE comic_id = ? ORDER BY sort_order ASC, gadget_image_id ASC");
        $stmt_gi->bind_param("i", $comic_id_to_edit);
        $stmt_gi->execute();
        $result_gi = $stmt_gi->get_result();
        while($gi_row = $result_gi->fetch_assoc()){
            $comic_data['gadget_images_list'][] = $gi_row;
        }
        $stmt_gi->close();

    } else {
        if (!isset($_SESSION['form_data_comics'])) {
            $_SESSION['message'] = "Fumetto non trovato per la modifica.";
            $_SESSION['message_type'] = 'error';
            header('Location: ' . BASE_URL . 'admin/comics_manage.php?action=list');
            exit;
        }
    }
    $stmt->close();
}

if ($action === 'delete' && isset($_GET['id'])) {
    if (!$is_true_admin) {
        $_SESSION['message'] = "Azione non permessa.";
        $_SESSION['message_type'] = 'error';
        header('Location: ' . BASE_URL . 'admin/comics_manage.php?action=list');
        exit;
    }
    $comic_id_to_delete = (int)$_GET['id'];
    $mysqli->begin_transaction();
    try {
        $stmt_imgs = $mysqli->prepare("SELECT cover_image, back_cover_image, gadget_image FROM comics WHERE comic_id = ?");
        $stmt_imgs->bind_param("i", $comic_id_to_delete); $stmt_imgs->execute(); $result_imgs = $stmt_imgs->get_result();
        if($img_data = $result_imgs->fetch_assoc()){
            if ($img_data['cover_image'] && file_exists(UPLOADS_PATH . $img_data['cover_image'])) unlink(UPLOADS_PATH . $img_data['cover_image']);
            if ($img_data['back_cover_image'] && file_exists(UPLOADS_PATH . $img_data['back_cover_image'])) unlink(UPLOADS_PATH . $img_data['back_cover_image']);
            if ($img_data['gadget_image'] && file_exists(UPLOADS_PATH . $img_data['gadget_image'])) unlink(UPLOADS_PATH . $img_data['gadget_image']);
        } $stmt_imgs->close();

        $stmt_vc_del_all = $mysqli->prepare("SELECT image_path FROM comic_variant_covers WHERE comic_id = ?");
        $stmt_vc_del_all->bind_param("i", $comic_id_to_delete); $stmt_vc_del_all->execute(); $result_vc_paths = $stmt_vc_del_all->get_result();
        while($vc_path_row = $result_vc_paths->fetch_assoc()){ if ($vc_path_row['image_path'] && file_exists(UPLOADS_PATH . $vc_path_row['image_path'])) unlink(UPLOADS_PATH . $vc_path_row['image_path']); } $stmt_vc_del_all->close();
        
        // MODIFICATO: Cancella le immagini multiple dei gadget dal server
        $stmt_gi_del_all = $mysqli->prepare("SELECT image_path FROM comic_gadget_images WHERE comic_id = ?");
        $stmt_gi_del_all->bind_param("i", $comic_id_to_delete); $stmt_gi_del_all->execute(); $result_gi_paths = $stmt_gi_del_all->get_result();
        while($gi_path_row = $result_gi_paths->fetch_assoc()){ if ($gi_path_row['image_path'] && file_exists(UPLOADS_PATH . $gi_path_row['image_path'])) unlink(UPLOADS_PATH . $gi_path_row['image_path']); } $stmt_gi_del_all->close();
        
        $stmt_unlink_placeholders = $mysqli->prepare("UPDATE user_collection_placeholders SET comic_id_linked = NULL, status = 'pending' WHERE comic_id_linked = ?");
        $stmt_unlink_placeholders->bind_param("i", $comic_id_to_delete); $stmt_unlink_placeholders->execute(); $stmt_unlink_placeholders->close();

        $stmt_del_comic = $mysqli->prepare("DELETE FROM comics WHERE comic_id = ?");
        $stmt_del_comic->bind_param("i", $comic_id_to_delete);
        if ($stmt_del_comic->execute()) {
            if ($stmt_del_comic->affected_rows > 0) {
                $_SESSION['message'] = "Fumetto e dati associati eliminati!"; $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Nessun fumetto trovato per l'eliminazione."; $_SESSION['message_type'] = 'error';
            }
        } else {
            throw new Exception("Errore eliminazione fumetto: " . $stmt_del_comic->error);
        }
        $stmt_del_comic->close();
        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = 'error';
        error_log("Errore eliminazione fumetto: " . $e->getMessage());
    }
    header('Location: ' . BASE_URL . 'admin/comics_manage.php?action=list');
    exit;
}

require_once ROOT_PATH . 'admin/includes/header_admin.php';
?>

<div class="container admin-container">
    <h2><?php echo $page_title; ?> <?php if ($is_contributor && !$is_true_admin) echo "(Modalità Contributore)"; ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php
    if ($action === 'list') {
        $search_term_sql = isset($_GET['search']) ? trim($_GET['search']) : '';
        $page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page_num < 1) $page_num = 1;
        $items_per_page = 25;
        $offset_val = ($page_num - 1) * $items_per_page;

        $sql_where_parts = [];
        $sql_params_list = [];
        $sql_param_types_list = "";

        if (!empty($search_term_sql)) {
            $sql_where_parts[] = "(c.issue_number LIKE ? OR c.title LIKE ? OR c.description LIKE ?)";
            $like_term = "%" . $search_term_sql . "%";
            array_push($sql_params_list, $like_term, $like_term, $like_term);
            $sql_param_types_list .= "sss";
        }
        $where_clause_final = count($sql_where_parts) > 0 ? ' WHERE ' . implode(' AND ', $sql_where_parts) : '';

        $sql_total_count = "SELECT COUNT(DISTINCT c.comic_id) as total FROM comics c $where_clause_final";
        $stmt_total = $mysqli->prepare($sql_total_count);
        if (!empty($search_term_sql)) {
            $stmt_total->bind_param($sql_param_types_list, ...$sql_params_list);
        }
        $stmt_total->execute();
        $total_comics_val = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
        $total_pages_val = ceil($total_comics_val / $items_per_page);
        $stmt_total->close();
        
        if ($page_num > $total_pages_val && $total_pages_val > 0) {
            $page_num = $total_pages_val;
            $offset_val = ($page_num - 1) * $items_per_page;
        }

        $sql_list_comics = "
            SELECT c.comic_id, c.issue_number, c.title, c.publication_date, c.cover_image, c.back_cover_image, c.gadget_name,
                   COUNT(vc.variant_cover_id) as variant_count
            FROM comics c
            LEFT JOIN comic_variant_covers vc ON c.comic_id = vc.comic_id
            $where_clause_final
            GROUP BY c.comic_id
            ORDER BY CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC, c.publication_date DESC
            LIMIT ? OFFSET ?
        ";
        $stmt_list_comics = $mysqli->prepare($sql_list_comics);
        
        $current_list_params_data = $sql_params_list;
        $current_list_types_data = $sql_param_types_list;
        array_push($current_list_params_data, $items_per_page, $offset_val);
        $current_list_types_data .= "ii";
        
        if (!empty($search_term_sql)) {
             $stmt_list_comics->bind_param($current_list_types_data, ...$current_list_params_data);
        } else {
             $stmt_list_comics->bind_param("ii", $items_per_page, $offset_val); 
        }
        $stmt_list_comics->execute();
        $result_list_comics = $stmt_list_comics->get_result();
        $searched = !empty($search_term_get); // Definisci questa variabile per la view

        require_once ROOT_PATH . 'admin/includes/comics_list_view.php';
        if($result_list_comics) $result_list_comics->free();
        $stmt_list_comics->close();

    } elseif ($action === 'add' || ($action === 'edit' && $comic_id_to_edit !== null)) {
        require_once ROOT_PATH . 'admin/includes/comics_form.php';
    }
    ?>
</div>

<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
$mysqli->close();
?>