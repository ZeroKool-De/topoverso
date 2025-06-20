<?php
// topolinolib/includes/functions.php

// Funzione standard per notificare i partecipanti a un thread
if (!function_exists('send_thread_notifications')) {
    function send_thread_notifications($thread_id, $new_post_id, $poster_user_id, $poster_display_name, $db_connection) {
        if (!$thread_id || !$db_connection) {
            error_log("send_thread_notifications: Chiamata fallita - thread_id o connessione DB mancanti.");
            return;
        }

        $stmt_thread_info = $db_connection->prepare("SELECT user_id, title FROM forum_threads WHERE id = ?");
        if (!$stmt_thread_info) { return; }
        $stmt_thread_info->bind_param("i", $thread_id);
        $stmt_thread_info->execute();
        $thread_info = $stmt_thread_info->get_result()->fetch_assoc();
        $stmt_thread_info->close();

        if (!$thread_info) {
            error_log("send_thread_notifications: Thread con ID $thread_id non trovato.");
            return;
        }
        
        $thread_starter_id = $thread_info['user_id'];
        $thread_title = $thread_info['title'];

        $stmt_participants = $db_connection->prepare("SELECT DISTINCT user_id FROM forum_posts WHERE thread_id = ? AND user_id IS NOT NULL");
        if (!$stmt_participants) { return; }
        $stmt_participants->bind_param("i", $thread_id);
        $stmt_participants->execute();
        $result_participants = $stmt_participants->get_result();
        $recipient_ids = [];
        while ($row = $result_participants->fetch_assoc()) {
            $recipient_ids[$row['user_id']] = true;
        }
        $stmt_participants->close();
        
        if ($thread_starter_id) {
            $recipient_ids[$thread_starter_id] = true;
        }
        
        if ($poster_user_id !== null && isset($recipient_ids[$poster_user_id])) {
            unset($recipient_ids[$poster_user_id]);
        }

        if (!empty($recipient_ids)) {
            $notif_message = "<strong>" . htmlspecialchars($poster_display_name) . "</strong> ha risposto nella discussione: \"" . htmlspecialchars(substr($thread_title, 0, 50)) . "...\"";
            $notif_link = "thread.php?id=" . $thread_id . "#post-" . $new_post_id;
            
            $stmt_notif = $db_connection->prepare("INSERT INTO notifications (user_id, message, link_url) VALUES (?, ?, ?)");
            if (!$stmt_notif) { 
                error_log("send_thread_notifications: Errore preparazione query INSERT notifiche.");
                return; 
            }
            
            error_log("Invio notifiche per thread $thread_id. Postatore: $poster_user_id. Destinatari: " . implode(', ', array_keys($recipient_ids)));

            foreach (array_keys($recipient_ids) as $recipient_id) {
                $stmt_notif->bind_param("iss", $recipient_id, $notif_message, $notif_link);
                $stmt_notif->execute();
            }
            $stmt_notif->close();
        } else {
             error_log("send_thread_notifications: Nessun destinatario per la notifica nel thread $thread_id (Postatore: $poster_user_id).");
        }
    }
}

// --- INIZIO BLOCCO MODIFICATO ---
/**
 * Funzione modificata: Invia una notifica per un commento di un visitatore SOLO all'admin principale.
 */
if (!function_exists('send_visitor_comment_notification')) {
    function send_visitor_comment_notification($thread_id, $post_id, $visitor_name, $db_connection) {
        if (!$thread_id || !$db_connection) {
            error_log("send_visitor_comment_notification: Chiamata fallita - dati mancanti.");
            return;
        }

        // 1. Recupera il titolo del thread per dare contesto
        $stmt_thread = $db_connection->prepare("SELECT title FROM forum_threads WHERE id = ?");
        $stmt_thread->bind_param("i", $thread_id);
        $stmt_thread->execute();
        $thread_title = $stmt_thread->get_result()->fetch_assoc()['title'] ?? 'una discussione';
        $stmt_thread->close();

        // 2. Il destinatario della notifica è SOLO l'admin principale (user_id = 1)
        $admin_user_id = 1;

        // 3. Prepara messaggio e link per il pannello di amministrazione
        $message = "Nuovo commento da <strong>" . htmlspecialchars($visitor_name) . "</strong> in attesa di approvazione in: \"" . htmlspecialchars(substr($thread_title, 0, 40)) . "...\"";
        $link = 'admin/forum_manage.php';

        // 4. Inserisci la notifica per il solo admin
        $stmt_insert = $db_connection->prepare("INSERT INTO notifications (user_id, message, link_url) VALUES (?, ?, ?)");
        if ($stmt_insert) {
            $stmt_insert->bind_param("iss", $admin_user_id, $message, $link);
            $stmt_insert->execute();
            $stmt_insert->close();
            error_log("send_visitor_comment_notification: Inviata notifica per post #$post_id all'admin (user_id: $admin_user_id).");
        } else {
            error_log("send_visitor_comment_notification: Errore preparazione query per notifica admin.");
        }
    }
}
// --- FINE BLOCCO MODIFICATO ---


// ... il resto delle funzioni (generate_slug, etc.) rimane invariato ...
if (!function_exists('generate_slug')) {
    function generate_slug($text, $mysqli, $table, $slug_column = 'slug', $exclude_id = 0, $id_column = 'id') {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9 -]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        if (empty($slug)) {
            $slug = 'n-a-' . uniqid();
        }
        $original_slug = $slug;
        $counter = 1;
        while (true) {
            $sql_check = "SELECT {$id_column} FROM {$table} WHERE {$slug_column} = ?";
            $params = [$slug];
            $types = "s";
            if ($exclude_id > 0) {
                $sql_check .= " AND {$id_column} != ?";
                $params[] = $exclude_id;
                $types .= "i";
            }
            $stmt = $mysqli->prepare($sql_check);
            if ($stmt === false) {
                error_log("Errore nella preparazione della query per lo slug: " . $mysqli->error);
                return $original_slug . '-' . time();
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $stmt->close();
                return $slug;
            }
            $stmt->close();
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
    }
}

if (!function_exists('generate_image_placeholder')) {
    function generate_image_placeholder($name, $width = 100, $height = 100, $class = '') {
        $initials = '';
        if (!empty($name)) {
            $name_parts = explode(' ', trim($name), 2);
            if (isset($name_parts[0][0])) {
                $initials .= strtoupper(mb_substr($name_parts[0], 0, 1, 'UTF-8'));
            }
            if (isset($name_parts[1][0])) {
                $initials .= strtoupper(mb_substr($name_parts[1], 0, 1, 'UTF-8'));
            }
        }
        if (empty($initials)) {
            $initials = '?';
        } elseif (mb_strlen($initials, 'UTF-8') === 1 && count($name_parts) === 1 && mb_strlen($name_parts[0], 'UTF-8') > 1) {
            if(mb_strlen($name_parts[0], 'UTF-8') >=2 ) {
                 $initials = strtoupper(mb_substr($name_parts[0], 0, 2, 'UTF-8'));
            }
        }
        $hue = crc32(strtolower(trim($name))) % 360;
        $bgColor = "hsl($hue, 60%, 88%)";
        $textColor = "hsl($hue, 60%, 35%)";
        $fontSize = min($width, $height) * 0.45;
        if(mb_strlen($initials, 'UTF-8') === 1) $fontSize = min($width, $height) * 0.55;
        if (is_numeric($name) && mb_strlen($initials, 'UTF-8') > 2) {
            $fontSize = min($width, $height) * 0.35;
             if(mb_strlen($initials, 'UTF-8') > 3) $fontSize = min($width, $height) * 0.3;
        }
        $svg_classes = 'img-placeholder';
        if (!empty($class)) {
            $svg_classes .= ' ' . htmlspecialchars($class);
        }
        $inline_style = "width: {$width}px; height: {$height}px; max-width: {$width}px; max-height: {$height}px; flex-shrink: 0;";
        $svg = '<svg width="'.(int)$width.'" height="'.(int)$height.'" viewBox="0 0 '.(int)$width.' '.(int)$height.'" class="'.$svg_classes.'" style="'.$inline_style.'" xmlns="http://www.w3.org/2000/svg" aria-label="Placeholder per '.htmlspecialchars($name).'">';
        $svg .= '<rect width="100%" height="100%" fill="'.$bgColor.'"/>';
        $svg .= '<text x="50%" y="50%" dy=".35em" font-family="Arial, Helvetica, sans-serif" font-weight="bold" font-size="'.$fontSize.'px" fill="'.$textColor.'" text-anchor="middle">'.htmlspecialchars($initials).'</text>';
        $svg .= '</svg>';
        return $svg;
    }
}

if (!function_exists('generate_comic_placeholder_cover')) {
    function generate_comic_placeholder_cover($issue_number, $width = 190, $height = 260, $class = 'comic-placeholder-cover') {
        $bgColor = "#e0e0e0";
        $borderColor = "#a0a0a0";
        $textColor = "#333333";
        $rectBgColor = "#f7f7f7";

        $issue_text = empty(trim($issue_number)) ? "N/D" : $issue_number;
        $font_size_issue = $height * 0.1;

        $svg = '<svg width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" class="'.htmlspecialchars($class).'" xmlns="http://www.w3.org/2000/svg" aria-label="Copertina placeholder per Topolino #'.htmlspecialchars($issue_text).'">';
        $svg .= '<rect x="0" y="0" width="'.$width.'" height="'.$height.'" fill="'.$bgColor.'" />';
        $svg .= '<rect x="5" y="5" width="'.($width-10).'" height="'.($height-10).'" fill="'.$rectBgColor.'" stroke="'.$borderColor.'" stroke-width="2" />';
        $font_size_title = $height * 0.08;
        $svg .= '<text x="'.($width/2).'" y="'.($height*0.2).'" dy=".3em" font-family="Impact, Arial Black, sans-serif" font-size="'.$font_size_title.'px" fill="#d9534f" text-anchor="middle" style="text-transform:uppercase;">Topolino</text>';
        $svg .= '<text x="'.($width/2).'" y="'.($height*0.55).'" dy=".3em" font-family="Arial, Helvetica, sans-serif" font-weight="bold" font-size="'.$font_size_issue.'px" fill="'.$textColor.'" text-anchor="middle"># '.htmlspecialchars($issue_text).'</text>';
        $font_size_missing = $height * 0.06;
        $svg .= '<text x="'.($width/2).'" y="'.($height*0.85).'" dy=".3em" font-family="Arial, Helvetica, sans-serif" font-size="'.$font_size_missing.'px" fill="#777" text-anchor="middle">(Cercami!)</text>';
        $svg .= '</svg>';
        return $svg;
    }
}

if (!function_exists('generate_alphabetical_nav_with_params')) {
    function generate_alphabetical_nav_with_params($base_page, $table_name, $column_name, $current_letter, $extra_params = []) {
        global $mysqli;

        $active_initials = [];
        $sql_initials = "
            SELECT DISTINCT
                CASE
                    WHEN LEFT(UPPER(t.$column_name), 1) BETWEEN 'A' AND 'Z' THEN LEFT(UPPER(t.$column_name), 1)
                    WHEN LEFT(t.$column_name, 1) BETWEEN '0' AND '9' THEN '0-9'
                    ELSE 'Altro'
                END AS initial
            FROM $table_name t "; 

        $role_filter_for_initials_params = [];
        $role_filter_for_initials_types = "";

        if ($table_name === 'persons' && isset($extra_params['role']) && !empty($extra_params['role'])) {
            $current_selected_role_for_initials = $extra_params['role'];
            $sql_initials .= " WHERE (EXISTS (SELECT 1 FROM story_persons sp_f_init WHERE sp_f_init.person_id = t.person_id AND sp_f_init.role = ?)
                                   OR EXISTS (SELECT 1 FROM comic_persons cp_f_init WHERE cp_f_init.person_id = t.person_id AND cp_f_init.role = ?)) ";
            $role_filter_for_initials_params[] = $current_selected_role_for_initials;
            $role_filter_for_initials_params[] = $current_selected_role_for_initials;
            $role_filter_for_initials_types = "ss";
        } else {
             $sql_initials .= " WHERE t.$column_name IS NOT NULL AND t.$column_name != '' ";
        }
        $sql_initials .= " ORDER BY initial ASC";


        $stmt_initials = $mysqli->prepare($sql_initials);
        if (!empty($role_filter_for_initials_params)){
             $stmt_initials->bind_param($role_filter_for_initials_types, ...$role_filter_for_initials_params);
        }
        $stmt_initials->execute();
        $result_initials = $stmt_initials->get_result();

        if ($result_initials) {
            while ($row = $result_initials->fetch_assoc()) {
                $active_initials[] = $row['initial'];
            }
            $result_initials->free();
        }
        $stmt_initials->close();

        $sorted_active_initials = [];
        $letters_part = []; $numeric_part = null; $other_part = null;
        foreach ($active_initials as $init) {
            if ($init === '0-9') $numeric_part = $init;
            elseif ($init === 'Altro') $other_part = $init;
            else $letters_part[] = $init;
        }
        sort($letters_part);
        if ($numeric_part) $sorted_active_initials[] = $numeric_part;
        $sorted_active_initials = array_merge($sorted_active_initials, $letters_part);
        if ($other_part) $sorted_active_initials[] = $other_part;

        $nav_html = '<nav class="alphabet-nav">';
        $link_all_params = $extra_params;
        $nav_html .= '<a href="' . htmlspecialchars($base_page . (!empty($link_all_params) ? '?' . http_build_query($link_all_params) : '')) . '" class="' . ($current_letter === null ? 'active' : '') . '">Tutti</a>';

        $all_possible_links = array_merge(['0-9'], range('A', 'Z'), ['Altro']);

        foreach ($all_possible_links as $char_alpha) {
            $is_active_db = in_array($char_alpha, $sorted_active_initials);
            $class = '';
            if ($current_letter !== null && strtoupper($current_letter) == $char_alpha) {
                $class = 'active';
            } elseif (!$is_active_db) {
                $class = 'disabled';
            }

            $link_params_letter = $extra_params;
            $link_params_letter['letter'] = $char_alpha;
            $href = $is_active_db ? htmlspecialchars($base_page . '?' . http_build_query($link_params_letter)) : '#';
            $nav_html .= '<a href="' . $href . '" class="' . $class . '">' . $char_alpha . '</a>';
        }
        $nav_html .= '</nav>';
        return $nav_html;
    }
}


if (!function_exists('format_date_italian')) {
    function format_date_italian($date_string, $format = "d F Y") {
    if (empty($date_string) || $date_string === '0000-00-00') {
        return 'N/D';
    }

    try {
        $date_obj = new DateTime($date_string);

        $english_months_full = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $italian_months_full = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        
        $english_months_short = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $italian_months_short = ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];
        
        $english_days_full = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $italian_days_full = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];

        $english_days_short = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $italian_days_short = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];

        $formatted_string = $date_obj->format($format);

        $formatted_string = str_replace($english_months_full, $italian_months_full, $formatted_string);
        $formatted_string = str_replace($english_months_short, $italian_months_short, $formatted_string);
        $formatted_string = str_replace($english_days_full, $italian_days_full, $formatted_string);
        $formatted_string = str_replace($english_days_short, $italian_days_short, $formatted_string);

        return $formatted_string;

    } catch (Exception $e) {
        return $date_string;
    }
}
}


if (!function_exists('get_site_setting')) {
    function get_site_setting($key, $db_connection, $default = null) {
        static $settings_cache = null; 

        if ($settings_cache === null) { 
            $settings_cache = [];
            if ($db_connection && $db_connection->connect_error === null) {
                $result = $db_connection->query("SELECT setting_key, setting_value FROM site_settings");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $settings_cache[$row['setting_key']] = $row['setting_value'];
                    }
                    $result->free();
                } else {
                    error_log("Errore nel recuperare le impostazioni del sito: " . $db_connection->error);
                }
            } else {
                 error_log("Connessione DB non valida o non disponibile in get_site_setting per chiave: " . $key);
            }
        }

        if (array_key_exists($key, $settings_cache)) {
            return $settings_cache[$key];
        }
        return $default;
    }
}
if (!function_exists('compress_and_optimize_image')) {
    function compress_and_optimize_image($source_path, $destination_path, $original_extension, $quality = 75, $png_compression = 9, $convertToWebp = true) {
        if (!file_exists($source_path) || !is_readable($source_path)) {
            error_log("Compress_and_optimize_image: File sorgente non trovato o non leggibile: " . $source_path);
            return false;
        }

        $destination_dir = dirname($destination_path);
        if (!is_dir($destination_dir)) {
            if (!mkdir($destination_dir, 0775, true)) {
                error_log("Compress_and_optimize_image: Impossibile creare la cartella di destinazione: " . $destination_dir);
                return false;
            }
        }
        if (!is_writable($destination_dir)) {
             error_log("Compress_and_optimize_image: Cartella di destinazione non scrivibile: " . $destination_dir);
            return false;
        }

        $image_info = @getimagesize($source_path);
        if (!$image_info) {
            error_log("Compress_and_optimize_image: Impossibile ottenere informazioni sull'immagine (getimagesize fallito): " . $source_path);
            return false;
        }
        $mime_type = $image_info['mime'];
        $new_extension = strtolower($original_extension);
        $output_path = $destination_path;

        $canConvertToWebp = $convertToWebp && function_exists('imagewebp') && ($mime_type !== 'image/gif');

        if ($canConvertToWebp) {
            $new_extension = 'webp';
            $path_parts = pathinfo($destination_path);
            $filename_without_ext = $path_parts['filename'];
            if (strtolower($path_parts['extension']) === 'webp') {
                 $output_path = $destination_path;
            } else {
                 $output_path = $path_parts['dirname'] . '/' . $filename_without_ext . '.' . $new_extension;
            }
        }

        $success = false;
        $image_resource = null;

        try {
            switch ($mime_type) {
                case 'image/jpeg':
                case 'image/jpg':
                    $image_resource = @imagecreatefromjpeg($source_path);
                    if ($image_resource) {
                        if ($canConvertToWebp) {
                            $success = @imagewebp($image_resource, $output_path, $quality);
                        } else {
                            $success = @imagejpeg($image_resource, $output_path, $quality);
                        }
                    }
                    break;
                case 'image/png':
                    $image_resource = @imagecreatefrompng($source_path);
                    if ($image_resource) {
                        imagealphablending($image_resource, false);
                        imagesavealpha($image_resource, true);
                        if ($canConvertToWebp) {
                            $success = @imagewebp($image_resource, $output_path, $quality);
                        } else {
                            $success = @imagepng($image_resource, $output_path, $png_compression);
                        }
                    }
                    break;
                case 'image/gif':
                    if ($source_path !== $output_path) {
                        $success = copy($source_path, $output_path);
                    } else {
                        $success = true;
                    }
                    $new_extension = 'gif';
                    $output_path = $destination_path;
                    break;
                case 'image/webp':
                    if ($source_path !== $output_path) {
                       $success = copy($source_path, $output_path);
                    } else {
                       $success = true;
                    }
                    $new_extension = 'webp';
                    $output_path = $destination_path;
                    break;
                default:
                    error_log("Compress_and_optimize_image: Tipo di file non supportato: " . $mime_type . " per " . $source_path);
                    if ($source_path !== $destination_path) {
                        $success = copy($source_path, $destination_path);
                         if($success) return $destination_path;
                    }
                    return false;
            }

            if ($image_resource) {
                imagedestroy($image_resource);
            }

            if ($success) {
                if ($canConvertToWebp && strtolower($original_extension) !== 'webp' && $source_path !== $output_path && file_exists($source_path)) {
                    @unlink($source_path);
                }
                return $output_path;
            } else {
                error_log("Compress_and_optimize_image: Fallimento nella creazione/scrittura dell'immagine ottimizzata per: " . $source_path . " a " . $output_path);
                if ($source_path !== $destination_path && file_exists($source_path)) {
                    if (@copy($source_path, $destination_path)) {
                        return $destination_path;
                    }
                }
                return false;
            }
        } catch (Exception $e) {
            error_log("Compress_and_optimize_image: Eccezione: " . $e->getMessage() . " per " . $source_path);
            if ($image_resource) {
                imagedestroy($image_resource);
            }
            return false;
        }
    }
}
if (!function_exists('format_post_content')) {
    function format_post_content($content) {
        $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $pattern = '/\[quote=([^\]]+)\](.*?)\[\/quote\]/s';
        $replacement = '<blockquote class="quoted-post"><p class="quoted-author"><strong>Citazione da $1:</strong></p><div>$2</div></blockquote>';
        $formatted_content = $safe_content;
        for ($i = 0; $i < 5; $i++) {
            $new_content = preg_replace($pattern, $replacement, $formatted_content);
            if ($new_content === $formatted_content) {
                break;
            }
            $formatted_content = $new_content;
        }
        return nl2br($formatted_content);
    }
}
if (!function_exists('generate_pagination')) {
    /**
     * Genera l'HTML per la navigazione a pagine.
     *
     * @param int $current_page La pagina corrente.
     * @param int $total_pages Il numero totale di pagine.
     * @param int $pages_to_show Il numero di link di pagina da mostrare attorno a quella corrente.
     * @return string L'HTML della paginazione.
     */
    function generate_pagination($current_page, $total_pages, $pages_to_show = 2) {
        if ($total_pages <= 1) {
            return '';
        }

        $pagination_html = '<nav aria-label="Navigazione pagine"><ul class="pagination">';
        
        // Mantiene i parametri GET esistenti (filtri, ordinamento, etc.)
        $query_params = $_GET;
        
        // Link "Precedente"
        if ($current_page > 1) {
            $query_params['page'] = $current_page - 1;
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">&laquo; Precedente</a></li>';
        } else {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">&laquo; Precedente</span></li>';
        }

        // Link alla prima pagina e puntini se necessario
        if ($current_page > $pages_to_show + 1) {
            $query_params['page'] = 1;
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">1</a></li>';
            if ($current_page > $pages_to_show + 2) {
                 $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Link delle pagine numerate
        for ($i = max(1, $current_page - $pages_to_show); $i <= min($total_pages, $current_page + $pages_to_show); $i++) {
            $query_params['page'] = $i;
            if ($i == $current_page) {
                $pagination_html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">' . $i . '</a></li>';
            }
        }
        
        // Link all'ultima pagina e puntini se necessario
        if ($current_page < $total_pages - $pages_to_show) {
             if ($current_page < $total_pages - $pages_to_show - 1) {
                $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $query_params['page'] = $total_pages;
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">' . $total_pages . '</a></li>';
        }

        // Link "Successivo"
        if ($current_page < $total_pages) {
            $query_params['page'] = $current_page + 1;
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">Successivo &raquo;</a></li>';
        } else {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">Successivo &raquo;</span></li>';
        }

        $pagination_html .= '</ul></nav>';
        return $pagination_html;
    }
}
// --- FUNZIONI AGGIUNTE PER GESTIRE I LINK DI COLLABORAZIONE ---

if (!function_exists('get_person_name')) {
    /**
     * Recupera il nome di un autore dal suo ID.
     * @param object $mysqli Connessione al database.
     * @param int $person_id ID dell'autore.
     * @return string Il nome dell'autore o un testo di default.
     */
    function get_person_name($mysqli, $person_id) {
        $stmt = $mysqli->prepare("SELECT name FROM persons WHERE person_id = ?");
        $stmt->bind_param("i", $person_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['name'];
        }
        $stmt->close();
        return 'Autore non trovato';
    }
}

if (!function_exists('get_character_name')) {
    /**
     * Recupera il nome di un personaggio dal suo ID.
     * @param object $mysqli Connessione al database.
     * @param int $character_id ID del personaggio.
     * @return string Il nome del personaggio o un testo di default.
     */
    function get_character_name($mysqli, $character_id) {
        $stmt = $mysqli->prepare("SELECT name FROM characters WHERE character_id = ?");
        $stmt->bind_param("i", $character_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['name'];
        }
        $stmt->close();
        return 'Personaggio non trovato';
    }
}
/**
 * Formatta il titolo di una storia gestendo saghe e episodi.
 *
 * @param array $story Un array associativo che rappresenta la storia.
 * Deve contenere 'story_title_main', 'title', 'part_number', 'total_parts'.
 * @return string Il titolo formattato in HTML.
 */
function format_story_title($story) {
    $display_title = '';

    // Controlla se è parte di una saga (ha un titolo principale)
    if (!empty($story['story_title_main'])) {
        $display_title = '<strong>' . htmlspecialchars($story['story_title_main']) . '</strong>';

        // Se è un episodio specifico di una saga
        if (!empty($story['part_number'])) {
            $part_specific_title = !empty($story['title']) ? htmlspecialchars($story['title']) : '';
            $expected_part_title = 'Parte ' . htmlspecialchars($story['part_number']);

            // Aggiunge il titolo dell'episodio se è significativo
            if (!empty($part_specific_title) && 
                $part_specific_title !== htmlspecialchars($story['story_title_main']) && 
                strtolower($part_specific_title) !== strtolower($expected_part_title)) {
                $display_title .= ': ' . $part_specific_title . ' <em>(Parte ' . htmlspecialchars($story['part_number']) . ')</em>';
            } else {
                $display_title .= ' - Parte ' . htmlspecialchars($story['part_number']);
            }
        }
        // Se ha un sottotitolo ma non è un episodio numerato
        elseif (!empty($story['title']) && strtolower($story['title']) !== strtolower($story['story_title_main'])) {
            $display_title .= ': ' . htmlspecialchars($story['title']);
        }

        // Aggiunge il numero totale di parti se disponibile
        if (!empty($story['total_parts'])) {
            $display_title .= ' (di ' . htmlspecialchars($story['total_parts']) . ')';
        }
    } 
    // Altrimenti, è una storia singola
    else {
        $display_title = !empty($story['title']) ? htmlspecialchars($story['title']) : 'Titolo non disponibile';
    }

    return $display_title;
}