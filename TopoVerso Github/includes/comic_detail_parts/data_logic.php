<?php
// topolinolib/includes/comic_detail_parts/data_logic.php

// Inizializzazione della sessione se non già attiva
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inclusione DB Connect e Functions
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    require_once ROOT_PATH . 'includes/db_connect.php';
}
if (!function_exists('format_date_italian')) {
    require_once ROOT_PATH . 'includes/functions.php';
}

// --- Logica per accettare sia ID che slug ---
$comic_id = null;
$comic_slug = null;

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $comic_id = (int)$_GET['id'];
} elseif (isset($_GET['slug'])) {
    $comic_slug = trim($_GET['slug']);
}

if (empty($comic_id) && empty($comic_slug)) {
    header('Location: index.php?message=Identificativo fumetto non valido');
    exit;
}

$user_id_frontend = $_SESSION['user_id_frontend'] ?? null;

// GESTIONE INVIO SEGNALAZIONE
$report_message = '';
$report_message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_error_report'])) {
    $comic_id_report = isset($_POST['comic_id_report']) ? (int)$_POST['comic_id_report'] : 0;
    $story_id_report = (!empty($_POST['story_id_report']) && $_POST['story_id_report'] !== 'general') ? (int)$_POST['story_id_report'] : null;
    $report_text_content = trim($_POST['report_text'] ?? '');
    $reporter_email_content = !empty($_POST['reporter_email']) ? trim($_POST['reporter_email']) : null;
    if ($comic_id_report <= 0) { $report_message = "Errore: ID dell'albo non valido per la segnalazione."; $report_message_type = 'error';
    } elseif (empty($report_text_content)) { $report_message = "La descrizione della segnalazione è obbligatoria."; $report_message_type = 'error';
    } elseif (strlen($report_text_content) < 10) { $report_message = "La descrizione della segnalazione sembra troppo corta. Fornisci più dettagli."; $report_message_type = 'error';
    } elseif ($reporter_email_content && !filter_var($reporter_email_content, FILTER_VALIDATE_EMAIL)) { $report_message = "L'indirizzo email fornito non è valido."; $report_message_type = 'error';
    } else {
        $stmt_insert_report = $mysqli->prepare("INSERT INTO error_reports (comic_id, story_id, report_text, reporter_email) VALUES (?, ?, ?, ?)");
        if ($stmt_insert_report) {
            $stmt_insert_report->bind_param("iiss", $comic_id_report, $story_id_report, $report_text_content, $reporter_email_content);
            if ($stmt_insert_report->execute()) {
                $_SESSION['report_feedback_msg'] = "Grazie! La tua segnalazione per Topolino #".htmlspecialchars($_POST['reported_issue_number_display'] ?? $comic_id_report)." è stata inviata con successo.";
                $_SESSION['report_feedback_type'] = 'success';
            } else { $_SESSION['report_feedback_msg'] = "Errore durante l'invio della segnalazione: " . $stmt_insert_report->error; $_SESSION['report_feedback_type'] = 'error'; }
            $stmt_insert_report->close();
        } else { $_SESSION['report_feedback_msg'] = "Errore di preparazione della query per la segnalazione: " . $mysqli->error; $_SESSION['report_feedback_type'] = 'error'; }
        
        $redirect_url_after_report = BASE_URL . "comic_detail.php?";
        $final_comic_slug = $_POST['reported_slug_display'] ?? ($comic_slug ?? '');
        if ($final_comic_slug) {
             $redirect_url_after_report .= 'slug=' . urlencode($final_comic_slug);
        } else {
            $redirect_url_after_report .= 'id=' . $comic_id_report;
        }
        $redirect_url_after_report .= "#report-feedback-anchor";
        header("Location: " . $redirect_url_after_report);
        exit;
    }
}
if (isset($_SESSION['report_feedback_msg'])) { $report_message = $_SESSION['report_feedback_msg']; $report_message_type = $_SESSION['report_feedback_type']; unset($_SESSION['report_feedback_msg'], $_SESSION['report_feedback_type']); }

// RECUPERO DATI FUMETTO TRAMITE ID o SLUG
$query_field = $comic_id ? 'comic_id' : 'slug';
$query_param = $comic_id ?: $comic_slug;
$param_type = $comic_id ? 'i' : 's';

$stmt_comic = $mysqli->prepare("SELECT comic_id, issue_number, title, slug, publication_date, description, cover_image, back_cover_image, back_cover_artist_id, back_cover_artists_json, editor, pages, price, periodicity, custom_fields, gadget_name, gadget_image FROM comics WHERE {$query_field} = ?");
$stmt_comic->bind_param($param_type, $query_param);
$stmt_comic->execute();
$result_comic = $stmt_comic->get_result();
if ($result_comic->num_rows === 0) { header('Location: index.php?message=Fumetto non trovato'); exit; }
$comic = $result_comic->fetch_assoc();
$stmt_comic->close();

// Assicuriamoci che comic_id sia sempre valorizzato per le query successive
if (!$comic_id) {
    $comic_id = $comic['comic_id'];
}

$page_title = "Topolino #" . htmlspecialchars($comic['issue_number']) . (!empty($comic['title']) ? ' - ' . htmlspecialchars($comic['title']) : '');

// RECUPERO DATI DISCUSSIONE
$total_posts_for_comic = 0; $last_post_for_comic = null;
$stmt_total_count = $mysqli->prepare("SELECT COUNT(p.id) as count FROM forum_posts p JOIN forum_threads t ON p.thread_id = t.id WHERE t.comic_id = ? AND p.status = 'approved'");
if($stmt_total_count){ $stmt_total_count->bind_param('i', $comic_id); $stmt_total_count->execute(); $total_posts_for_comic = $stmt_total_count->get_result()->fetch_assoc()['count'] ?? 0; $stmt_total_count->close(); }
if ($total_posts_for_comic > 0) {
    $stmt_last_post = $mysqli->prepare("SELECT p.content, p.created_at, p.author_name, u.username, t.id as thread_id, t.title as thread_title FROM forum_posts p JOIN forum_threads t ON p.thread_id = t.id LEFT JOIN users u ON p.user_id = u.user_id WHERE t.comic_id = ? AND p.status = 'approved' ORDER BY p.created_at DESC LIMIT 1");
    if($stmt_last_post){ $stmt_last_post->bind_param('i', $comic_id); $stmt_last_post->execute(); $last_post_for_comic = $stmt_last_post->get_result()->fetch_assoc(); $stmt_last_post->close(); }
}

// RECUPERO DATI NAVIGAZIONE
$prev_comic_data = null; 
$next_comic_data = null; 
$current_comic_publication_date = $comic['publication_date']; 
$current_comic_issue_number_for_query = $comic['issue_number'];

$stmt_prev = $mysqli->prepare("SELECT c.comic_id, c.slug, c.issue_number, c.cover_image FROM comics c WHERE c.publication_date IS NOT NULL AND c.publication_date <= CURDATE() AND (c.publication_date < ? OR (c.publication_date = ? AND CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) < CAST(REPLACE(REPLACE(?, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ) ) ORDER BY c.publication_date DESC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) DESC LIMIT 1");
if ($stmt_prev) { 
    $stmt_prev->bind_param("sss", $current_comic_publication_date, $current_comic_publication_date, $current_comic_issue_number_for_query); 
    $stmt_prev->execute(); 
    $result_prev = $stmt_prev->get_result(); 
    if ($prev_row = $result_prev->fetch_assoc()) { 
        $prev_comic_data = $prev_row; 
    } 
    $stmt_prev->close(); 
}

$stmt_next = $mysqli->prepare("SELECT c.comic_id, c.slug, c.issue_number, c.cover_image FROM comics c WHERE c.publication_date IS NOT NULL AND c.publication_date <= CURDATE() AND (c.publication_date > ? OR (c.publication_date = ? AND CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) > CAST(REPLACE(REPLACE(?, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ) ) ORDER BY c.publication_date ASC, CAST(REPLACE(REPLACE(c.issue_number, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ASC LIMIT 1");
if ($stmt_next) { 
    $stmt_next->bind_param("sss", $current_comic_publication_date, $current_comic_publication_date, $current_comic_issue_number_for_query); 
    $stmt_next->execute(); 
    $result_next = $stmt_next->get_result(); 
    if ($next_row = $result_next->fetch_assoc()) { 
        $next_comic_data = $next_row; 
    } 
    $stmt_next->close(); 
}

// --- INIZIO BLOCCO MODIFICATO ---
// SEPARAZIONE STAFF E RECUPERO COPERTINISTI VARIANT
$all_comic_persons_data = [];
$stmt_cp = $mysqli->prepare("SELECT p.person_id, p.name, p.slug, cp.role FROM comic_persons cp JOIN persons p ON cp.person_id = p.person_id WHERE cp.comic_id = ? ORDER BY FIELD(cp.role, 'Direttore Responsabile', 'Direttore Editoriale', 'Direttore', 'Copertinista'), cp.role, p.name");
$stmt_cp->bind_param("i", $comic_id);
$stmt_cp->execute();
$result_cp = $stmt_cp->get_result();
while ($row_cp = $result_cp->fetch_assoc()){
    $all_comic_persons_data[] = $row_cp;
}
$stmt_cp->close();

$directors_list = []; 
$cover_artists_list = []; 
$other_staff_list = [];

$director_role_keywords = ['Direttore', 'Direttore Responsabile', 'Direttore Editoriale'];
foreach ($all_comic_persons_data as $person_role) {
    $is_director_role = false;
    foreach ($director_role_keywords as $keyword) { if (strcasecmp(trim($person_role['role']), trim($keyword)) == 0) { $is_director_role = true; break; } }
    if ($is_director_role) { $directors_list[] = $person_role; } 
    elseif (strcasecmp(trim($person_role['role']), 'Copertinista') == 0) { $cover_artists_list[] = $person_role; } 
    else { $other_staff_list[] = $person_role; }
}

// QUERY PER GLI ARTISTI DELLE VARIANT COVER
$variant_cover_artists_list = [];
$stmt_vc_artists = $mysqli->prepare("
    SELECT DISTINCT p.person_id, p.name, p.slug
    FROM comic_variant_covers cvc
    JOIN persons p ON cvc.artist_id = p.person_id
    WHERE cvc.comic_id = ? AND cvc.artist_id IS NOT NULL
    ORDER BY p.name
");
$stmt_vc_artists->bind_param("i", $comic_id);
$stmt_vc_artists->execute();
$result_vc_artists = $stmt_vc_artists->get_result();
while ($vc_artist_row = $result_vc_artists->fetch_assoc()) {
    $variant_cover_artists_list[] = $vc_artist_row;
}
$stmt_vc_artists->close();

// RECUPERO RETROCOPERTINISTI (dalla tabella comics)
$back_cover_artists_list = [];
if (!empty($comic['back_cover_image'])) {
    // Controlla prima se c'è un retrocopertinista singolo
    if (!empty($comic['back_cover_artist_id'])) {
        $stmt_single_bc = $mysqli->prepare("SELECT person_id, name, slug FROM persons WHERE person_id = ?");
        if ($stmt_single_bc) {
            $stmt_single_bc->bind_param("i", $comic['back_cover_artist_id']);
            $stmt_single_bc->execute();
            $result_single_bc = $stmt_single_bc->get_result();
            if ($single_bc_row = $result_single_bc->fetch_assoc()) {
                $back_cover_artists_list[] = $single_bc_row;
            }
            $stmt_single_bc->close();
        }
    }
    
    // Se non c'è retrocopertinista singolo, controlla il JSON
    if (empty($back_cover_artists_list) && !empty($comic['back_cover_artists_json'])) {
        $back_cover_artists_json = json_decode($comic['back_cover_artists_json'], true);
        if (is_array($back_cover_artists_json)) {
            foreach ($back_cover_artists_json as $bc_artist_id) {
                if (is_numeric($bc_artist_id)) {
                    $stmt_json_bc = $mysqli->prepare("SELECT person_id, name, slug FROM persons WHERE person_id = ?");
                    if ($stmt_json_bc) {
                        $stmt_json_bc->bind_param("i", $bc_artist_id);
                        $stmt_json_bc->execute();
                        $result_json_bc = $stmt_json_bc->get_result();
                        if ($json_bc_row = $result_json_bc->fetch_assoc()) {
                            $back_cover_artists_list[] = $json_bc_row;
                        }
                        $stmt_json_bc->close();
                    }
                }
            }
        }
    }
}
// --- FINE BLOCCO MODIFICATO ---

// RECUPERO ALTRI DATI
$related_historical_events_period = [];
if ($comic['publication_date']) {
    $sql_events_period = "SELECT * FROM historical_events he WHERE ( ? BETWEEN he.event_date_start AND he.event_date_end ) OR ( he.event_date_end IS NULL AND he.event_date_start = ? ) OR ( he.related_issue_start IS NOT NULL AND he.related_issue_start != '' AND CAST(REPLACE(REPLACE(?, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) BETWEEN CAST(REPLACE(REPLACE(he.related_issue_start, ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) AND CAST(REPLACE(REPLACE(IFNULL(he.related_issue_end, he.related_issue_start), ' bis', '.5'), ' ter', '.7') AS DECIMAL(10,2)) ) ORDER BY he.event_date_start ASC";
    $stmt_events_period = $mysqli->prepare($sql_events_period);
    if ($stmt_events_period) { $comic_pub_date = $comic['publication_date']; $comic_issue_num_for_event = $comic['issue_number']; $stmt_events_period->bind_param("sss", $comic_pub_date, $comic_pub_date, $comic_issue_num_for_event); $stmt_events_period->execute(); $result_events_period = $stmt_events_period->get_result(); while ($event_row_period = $result_events_period->fetch_assoc()) { $related_historical_events_period[] = $event_row_period; } $stmt_events_period->close(); }
}

$custom_fields_data = [];
if (!empty($comic['custom_fields'])) { $decoded_custom_fields = json_decode($comic['custom_fields'], true); if (is_array($decoded_custom_fields)) { $custom_field_keys = array_map(function($key) use ($mysqli) { return $mysqli->real_escape_string($key); }, array_keys($decoded_custom_fields)); if (!empty($custom_field_keys)) { $keys_string = "'" . implode("','", $custom_field_keys) . "'"; $defs_result = $mysqli->query("SELECT field_key, field_label, field_type FROM custom_field_definitions WHERE field_key IN ($keys_string) AND entity_type = 'comic'"); $labels_map = []; if ($defs_result) { while ($def_row = $defs_result->fetch_assoc()) { $labels_map[$def_row['field_key']] = ['label' => $def_row['field_label'], 'type' => $def_row['field_type']]; } $defs_result->free(); } foreach ($decoded_custom_fields as $key => $value) { if (isset($value) && $value !== '' && isset($labels_map[$key])) { $display_value = $value; if ($labels_map[$key]['type'] == 'checkbox') { $display_value = ($value === '1' || $value === 1 || $value === true || strtolower($value) === 'sì' || strtolower($value) === 'si') ? 'Sì' : 'No'; } $custom_fields_data[] = ['label' => $labels_map[$key]['label'], 'value' => $display_value]; } } } } }

// RECUPERO STORIE
$stories_data = []; 
$ministories_data = []; 
$sql_stories_with_event_info = "SELECT s.story_id, s.title, s.story_title_main, s.part_number, s.total_parts, s.story_group_id, s.first_page_image, s.sequence_in_comic, s.notes, s.is_ministory, s.series_id, s.series_episode_number, ss.title AS series_title, ss.slug AS series_slug, he.event_id AS related_historical_event_id, he.event_title AS related_historical_event_title, he.category AS related_historical_event_category, ft.id as story_thread_id, (SELECT COUNT(*) FROM forum_posts fp WHERE fp.thread_id = ft.id AND fp.status = 'approved') AS story_comment_count FROM stories s LEFT JOIN story_series ss ON s.series_id = ss.series_id LEFT JOIN historical_events he ON s.story_id = he.related_story_id LEFT JOIN forum_threads ft ON s.story_id = ft.story_id WHERE s.comic_id = ? ORDER BY CASE WHEN s.sequence_in_comic > 0 THEN s.sequence_in_comic ELSE 999999 END ASC, s.story_id ASC";
$stmt_stories = $mysqli->prepare($sql_stories_with_event_info); 
$stmt_stories->bind_param("i", $comic_id); 
$stmt_stories->execute(); 
$result_stories = $stmt_stories->get_result();

while ($story_row = $result_stories->fetch_assoc()) { 
    $current_story_id = $story_row['story_id']; 
    $story_row['authors'] = []; 
    $stmt_s_auth = $mysqli->prepare("SELECT p.person_id, p.name, p.slug, sp.role FROM story_persons sp JOIN persons p ON sp.person_id = p.person_id WHERE sp.story_id = ? ORDER BY FIELD(sp.role, 'Soggetto', 'Sceneggiatura', 'Testi', 'Matite', 'Disegni', 'Disegnatore'), p.name"); 
    $stmt_s_auth->bind_param("i", $current_story_id); 
    $stmt_s_auth->execute(); 
    $result_s_auth = $stmt_s_auth->get_result(); 
    while ($s_auth_row = $result_s_auth->fetch_assoc()) $story_row['authors'][] = $s_auth_row; 
    $stmt_s_auth->close();
    
    $story_row['characters_in_story'] = []; 
    $stmt_s_char = $mysqli->prepare("SELECT c.character_id, c.name, c.slug, c.character_image, c.first_appearance_comic_id, c.first_appearance_story_id FROM story_characters sc JOIN characters c ON sc.character_id = c.character_id WHERE sc.story_id = ? ORDER BY c.name"); 
    $stmt_s_char->bind_param("i", $current_story_id); 
    $stmt_s_char->execute(); 
    $result_s_char = $stmt_s_char->get_result(); 
    while ($s_char_row = $result_s_char->fetch_assoc()) $story_row['characters_in_story'][] = $s_char_row; 
    $stmt_s_char->close();
    
    $story_row['other_parts'] = []; 
    if (!empty($story_row['story_group_id'])) { 
        $stmt_other_parts = $mysqli->prepare("SELECT s_other.story_id, s_other.title AS other_story_part_title, s_other.part_number AS other_part_number, s_other.comic_id AS other_comic_id, c_other.issue_number AS other_comic_issue_number, c_other.title AS other_comic_title, c_other.slug AS other_comic_slug FROM stories s_other JOIN comics c_other ON s_other.comic_id = c_other.comic_id WHERE s_other.story_group_id = ? AND s_other.story_id != ? ORDER BY CAST(s_other.part_number AS UNSIGNED) ASC, c_other.publication_date ASC, c_other.comic_id ASC, s_other.sequence_in_comic ASC, s_other.story_id ASC"); 
        $stmt_other_parts->bind_param("ii", $story_row['story_group_id'], $current_story_id); 
        $stmt_other_parts->execute(); 
        $result_other_parts = $stmt_other_parts->get_result(); 
        while($other_part_row = $result_other_parts->fetch_assoc()) { 
            $story_row['other_parts'][] = $other_part_row; 
        } 
        $stmt_other_parts->close(); 
    }
    
    $stories_data[] = $story_row; 
    if ($story_row['is_ministory'] == 1) { 
        $ministories_data[] = $story_row;
    }
}
$stmt_stories->close();

$comic_rating_info = ['avg' => 0, 'count' => 0];
$stmt_comic_rating = $mysqli->prepare("SELECT AVG(rating) as avg_r, COUNT(rating) as count_r FROM comic_ratings WHERE comic_id = ?"); 
if ($stmt_comic_rating) { 
    $stmt_comic_rating->bind_param("i", $comic_id); 
    $stmt_comic_rating->execute(); 
    $res = $stmt_comic_rating->get_result()->fetch_assoc(); 
    if ($res && $res['count_r'] > 0) { 
        $comic_rating_info['avg'] = round($res['avg_r'], 1); 
        $comic_rating_info['count'] = (int)$res['count_r']; 
    } 
    $stmt_comic_rating->close(); 
}

$stories_ratings_info = [];
$all_stories_on_page = array_merge($stories_data, $ministories_data);
$story_ids_on_page = array_map(function($story) { return $story['story_id']; }, $all_stories_on_page);
if (!empty($story_ids_on_page)) { $placeholders = implode(',', array_fill(0, count($story_ids_on_page), '?')); $sql_stories_ratings = "SELECT story_id, AVG(rating) as avg_r, COUNT(rating) as count_r FROM story_ratings WHERE story_id IN ($placeholders) GROUP BY story_id"; $stmt_stories_ratings = $mysqli->prepare($sql_stories_ratings); if ($stmt_stories_ratings) { $stmt_stories_ratings->bind_param(str_repeat('i', count($story_ids_on_page)), ...$story_ids_on_page); $stmt_stories_ratings->execute(); $result_ratings = $stmt_stories_ratings->get_result(); while ($row = $result_ratings->fetch_assoc()) { $stories_ratings_info[$row['story_id']] = ['avg' => round($row['avg_r'], 1), 'count' => (int)$row['count_r']]; } $stmt_stories_ratings->close(); } }

$all_covers_for_js = []; $initial_cover_path = BASE_URL . 'assets/images/placeholder_cover.png'; $initial_cover_alt = 'Copertina non disponibile'; $initial_cover_caption = 'Nessuna copertina principale disponibile'; if ($comic['cover_image']) { $all_covers_for_js[] = ['path' => UPLOADS_URL . htmlspecialchars($comic['cover_image']), 'caption' => 'Copertina Principale' . (!empty($comic['title']) ? ': ' . htmlspecialchars($comic['title']) : ''), 'alt' => 'Copertina Principale ' . htmlspecialchars($comic['issue_number'])]; $initial_cover_path = UPLOADS_URL . htmlspecialchars($comic['cover_image']); $initial_cover_alt = 'Copertina Principale ' . htmlspecialchars($comic['issue_number']); $initial_cover_caption = 'Copertina Principale' . (!empty($comic['title']) ? ': ' . htmlspecialchars($comic['title']) : ''); } if ($comic['back_cover_image']) { $all_covers_for_js[] = ['path' => UPLOADS_URL . htmlspecialchars($comic['back_cover_image']), 'caption' => 'Retrocopertina', 'alt' => 'Retrocopertina ' . htmlspecialchars($comic['issue_number'])]; if (strpos($initial_cover_path, 'placeholder_cover.png') !== false) { $initial_cover_path = UPLOADS_URL . htmlspecialchars($comic['back_cover_image']); $initial_cover_alt = 'Retrocopertina ' . htmlspecialchars($comic['issue_number']); $initial_cover_caption = 'Retrocopertina'; }} $variant_cover_present = false; $stmt_variants = $mysqli->prepare("SELECT image_path, caption FROM comic_variant_covers WHERE comic_id = ? ORDER BY sort_order ASC, variant_cover_id ASC"); $stmt_variants->bind_param("i", $comic_id); $stmt_variants->execute(); $result_variants = $stmt_variants->get_result(); while ($variant = $result_variants->fetch_assoc()) { $variant_cover_present = true; $all_covers_for_js[] = ['path' => UPLOADS_URL . htmlspecialchars($variant['image_path']), 'caption' => !empty($variant['caption']) ? htmlspecialchars($variant['caption']) : 'Copertina Variant', 'alt' => 'Variant: ' . (!empty($variant['caption']) ? htmlspecialchars($variant['caption']) : htmlspecialchars($comic['issue_number']))]; if (strpos($initial_cover_path, 'placeholder_cover.png') !== false) { $initial_cover_path = UPLOADS_URL . htmlspecialchars($variant['image_path']); $initial_cover_alt = 'Variant: ' . (!empty($variant['caption']) ? htmlspecialchars($variant['caption']) : htmlspecialchars($comic['issue_number'])); $initial_cover_caption = !empty($variant['caption']) ? htmlspecialchars($variant['caption']) : 'Copertina Variant'; } } $stmt_variants->close(); if (empty($all_covers_for_js)) { $all_covers_for_js[] = ['path' => $initial_cover_path, 'caption' => $initial_cover_caption, 'alt' => $initial_cover_alt]; }

$all_gadget_images_for_js = []; $initial_gadget_image_path = BASE_URL . 'assets/images/placeholder_image.png'; $initial_gadget_image_alt = 'Nessuna immagine per il gadget'; $initial_gadget_image_caption = !empty($comic['gadget_name']) ? htmlspecialchars($comic['gadget_name']) : ''; $gadget_images_data_from_db = []; if ($comic_id > 0) { $stmt_gadget_imgs = $mysqli->prepare("SELECT gadget_image_id, image_path, caption FROM comic_gadget_images WHERE comic_id = ? ORDER BY sort_order ASC, gadget_image_id ASC"); if ($stmt_gadget_imgs) { $stmt_gadget_imgs->bind_param("i", $comic_id); $stmt_gadget_imgs->execute(); $result_gadget_imgs = $stmt_gadget_imgs->get_result(); while ($g_img = $result_gadget_imgs->fetch_assoc()) { if (!empty($g_img['image_path'])) { $gadget_images_data_from_db[] = $g_img; }} $stmt_gadget_imgs->close(); } else { error_log("Errore preparazione query comic_gadget_images: " . $mysqli->error);}} if (empty($gadget_images_data_from_db) && !empty($comic['gadget_image'])) { $gadget_images_data_from_db[] = [ 'image_path' => $comic['gadget_image'], 'caption' => !empty($comic['gadget_name']) ? htmlspecialchars($comic['gadget_name']) : 'Gadget Allegato' ];} if (!empty($gadget_images_data_from_db)) { foreach ($gadget_images_data_from_db as $index => $g_image) { $img_path_gadget = UPLOADS_URL . htmlspecialchars($g_image['image_path']); $img_caption_gadget = !empty($g_image['caption']) ? htmlspecialchars($g_image['caption']) : (!empty($comic['gadget_name']) ? htmlspecialchars($comic['gadget_name']) : ('Immagine Gadget ' . ($index + 1))); $img_alt_gadget = $img_caption_gadget; $all_gadget_images_for_js[] = [ 'path' => $img_path_gadget, 'caption' => $img_caption_gadget, 'alt' => $img_alt_gadget ]; if ($index === 0) { $initial_gadget_image_path = $img_path_gadget; $initial_gadget_image_alt = $img_alt_gadget; $initial_gadget_image_caption = $img_caption_gadget; }}} elseif (!empty($comic['gadget_name'])) { $all_gadget_images_for_js[] = [ 'path' => $initial_gadget_image_path, 'caption' => htmlspecialchars($comic['gadget_name']), 'alt' => htmlspecialchars($comic['gadget_name']) ];}

$comic_in_collection = null; if ($user_id_frontend) { $stmt_collection_check = $mysqli->prepare("SELECT is_read, rating FROM user_collections WHERE user_id = ? AND comic_id = ?"); $stmt_collection_check->bind_param("ii", $user_id_frontend, $comic_id); $stmt_collection_check->execute(); $result_collection_check = $stmt_collection_check->get_result(); if ($result_collection_check->num_rows > 0) { $comic_in_collection = $result_collection_check->fetch_assoc(); } $stmt_collection_check->close(); }

$average_rating = null; $rating_count = 0; $stmt_avg_rating = $mysqli->prepare("SELECT AVG(rating) as avg_r, COUNT(rating) as count_r FROM comic_ratings WHERE comic_id = ?"); $stmt_avg_rating->bind_param("i", $comic_id); $stmt_avg_rating->execute(); $result_avg_rating = $stmt_avg_rating->get_result(); if ($avg_data = $result_avg_rating->fetch_assoc()) { if ($avg_data['count_r'] > 0) { $average_rating = round($avg_data['avg_r'], 1); $rating_count = (int)$avg_data['count_r']; }} $stmt_avg_rating->close();

$url_fragment_raw = parse_url($_SERVER['REQUEST_URI'], PHP_URL_FRAGMENT); $active_hash_target_name = $url_fragment_raw ? ltrim((string)$url_fragment_raw, '#') : '';

$collection_message = $_SESSION['message'] ?? null; $collection_message_type = $_SESSION['message_type'] ?? null; if ($collection_message) { unset($_SESSION['message']); unset($_SESSION['message_type']);}
?>