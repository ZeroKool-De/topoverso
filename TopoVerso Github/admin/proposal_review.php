<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// CONTROLLO ACCESSO - SOLO VERI ADMIN
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin_user_id'])) {
    $_SESSION['admin_action_message'] = "Accesso negato.";
    $_SESSION['admin_action_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}
$current_admin_reviewer_id = $_SESSION['admin_user_id'];
// FINE CONTROLLO ACCESSO

$page_title = "Revisione Proposta";
require_once ROOT_PATH . 'admin/includes/header_admin.php';

$proposal_type = $_GET['type'] ?? null;
$pending_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$proposal_data = null;
$original_data = null;
$error_message = '';

// ... (mappe tabelle come prima) ...
$table_map = [
    'comic' => 'pending_comics',
    'story' => 'pending_stories',
    'person' => 'pending_persons',
    'character' => 'pending_characters',
    'series' => 'pending_story_series'
];
$original_table_map = [
    'comic' => 'comics',
    'story' => 'stories',
    'person' => 'persons',
    'character' => 'characters',
    'series' => 'story_series'
];
$id_column_map = [
    'comic' => 'pending_comic_id',
    'story' => 'pending_story_id',
    'person' => 'pending_person_id',
    'character' => 'pending_character_id',
    'series' => 'pending_series_id'
];
$original_id_column_map = [
    'comic' => 'comic_id_original',
    'story' => 'story_id_original',
    'person' => 'person_id_original',
    'character' => 'character_id_original',
    'series' => 'series_id_original'
];
$original_main_id_column_map = [
    'comic' => 'comic_id',
    'story' => 'story_id',
    'person' => 'person_id',
    'character' => 'character_id',
    'series' => 'series_id'
];


if (!$proposal_type || !$pending_id || !isset($table_map[$proposal_type])) {
    $error_message = "Tipo di proposta o ID mancante o non valido.";
} else {
    $pending_table = $table_map[$proposal_type];
    $pending_id_column = $id_column_map[$proposal_type];

    $stmt_proposal = $mysqli->prepare("SELECT p.*, u.username AS proposer_username 
                                       FROM {$pending_table} p 
                                       JOIN users u ON p.proposer_user_id = u.user_id
                                       WHERE p.{$pending_id_column} = ? AND p.status = 'pending'");
    if ($stmt_proposal) {
        $stmt_proposal->bind_param("i", $pending_id);
        $stmt_proposal->execute();
        $result_proposal = $stmt_proposal->get_result();
        if ($result_proposal->num_rows === 1) {
            $proposal_data = $result_proposal->fetch_assoc();
            $original_id_val_for_fetch = $proposal_data[$original_id_column_map[$proposal_type]] ?? null;

            if ($proposal_data['action_type'] === 'edit' && !empty($original_id_val_for_fetch)) {
                $original_table = $original_table_map[$proposal_type];
                $original_main_id_col = $original_main_id_column_map[$proposal_type];

                $stmt_original = $mysqli->prepare("SELECT * FROM {$original_table} WHERE {$original_main_id_col} = ?");
                if ($stmt_original) {
                    $stmt_original->bind_param("i", $original_id_val_for_fetch);
                    $stmt_original->execute();
                    $result_original = $stmt_original->get_result();
                    if ($result_original->num_rows === 1) {
                        $original_data = $result_original->fetch_assoc();
                        // Decodifica JSON per dati originali se necessario
                        if ($proposal_type === 'comic') {
                            if (isset($original_data['custom_fields'])) {
                                $original_data['custom_fields_decoded'] = json_decode($original_data['custom_fields'] ?: '[]', true) ?: [];
                            }
                            // Carica staff originale per confronto
                            $original_data['staff_array'] = [];
                            $stmt_orig_staff = $mysqli->prepare("SELECT cp.person_id, cp.role, p.name as person_name FROM comic_persons cp JOIN persons p ON cp.person_id = p.person_id WHERE cp.comic_id = ? ORDER BY cp.role, p.name");
                            $stmt_orig_staff->bind_param("i", $original_id_val_for_fetch);
                            $stmt_orig_staff->execute();
                            $res_os = $stmt_orig_staff->get_result();
                            while($r_os = $res_os->fetch_assoc()) $original_data['staff_array'][] = ['person_id' => $r_os['person_id'], 'role' => $r_os['role'], 'person_name' => $r_os['person_name']];
                            $stmt_orig_staff->close();
                            
                            // --- BLOCCO MODIFICATO ---
                            // Carica variant originali per confronto, incluso l'artista
                            $original_data['variants_array'] = [];
                            $stmt_orig_var = $mysqli->prepare("
                                SELECT vc.variant_cover_id, vc.image_path, vc.caption, vc.sort_order, vc.artist_id, p.name AS artist_name 
                                FROM comic_variant_covers vc 
                                LEFT JOIN persons p ON vc.artist_id = p.person_id
                                WHERE vc.comic_id = ? ORDER BY vc.sort_order
                            ");
                            $stmt_orig_var->bind_param("i", $original_id_val_for_fetch);
                            $stmt_orig_var->execute();
                            $res_ov = $stmt_orig_var->get_result();
                            while($r_ov = $res_ov->fetch_assoc()) $original_data['variants_array'][] = $r_ov;
                            $stmt_orig_var->close();
                            // --- FINE BLOCCO MODIFICATO ---
                        }
                        if ($proposal_type === 'story') {
                            $original_data['authors_array'] = [];
                            $stmt_orig_auth = $mysqli->prepare("SELECT sp.person_id, sp.role, p.name as person_name FROM story_persons sp JOIN persons p ON sp.person_id = p.person_id WHERE sp.story_id = ? ORDER BY sp.role, p.name");
                            $stmt_orig_auth->bind_param("i", $original_id_val_for_fetch); $stmt_orig_auth->execute(); $res_oa = $stmt_orig_auth->get_result();
                            while($r_oa = $res_oa->fetch_assoc()) $original_data['authors_array'][] = ['person_id' => $r_oa['person_id'], 'role' => $r_oa['role'], 'person_name' => $r_oa['person_name']];
                            $stmt_orig_auth->close();

                            $original_data['characters_array'] = [];
                            $stmt_orig_char = $mysqli->prepare("SELECT sc.character_id, c.name as character_name FROM story_characters sc JOIN characters c ON sc.character_id = c.character_id WHERE sc.story_id = ? ORDER BY c.name");
                            $stmt_orig_char->bind_param("i", $original_id_val_for_fetch); $stmt_orig_char->execute(); $res_oc = $stmt_orig_char->get_result();
                            while($r_oc = $res_oc->fetch_assoc()) $original_data['characters_array'][] = ['character_id' => $r_oc['character_id'], 'character_name' => $r_oc['character_name']];
                            $stmt_orig_char->close();
                        }
                    }
                    $stmt_original->close();
                } else {
                    $error_message = "Errore nel preparare la query per i dati originali: " . $mysqli->error;
                }
            }
        } else {
            $error_message = "Proposta non trovata o non più in attesa di revisione.";
        }
        $stmt_proposal->close();
    } else {
        $error_message = "Errore nel preparare la query per la proposta: " . $mysqli->error;
    }
}

// --- FUNZIONE MODIFICATA ---
function enrich_proposal_data_with_names($json_string, $type) {
    global $mysqli;
    if (empty($json_string)) return [];
    $data_array = json_decode($json_string, true);
    if (!is_array($data_array)) return [];

    $enriched_array = [];
    if ($type === 'staff' || $type === 'authors') { 
        foreach ($data_array as $item) {
            $person_id = $item['person_id'] ?? null;
            $role = $item['role'] ?? 'N/D';
            $person_name = 'Sconosciuto (ID: ' . $person_id . ')';
            if ($person_id) {
                $stmt = $mysqli->prepare("SELECT name FROM persons WHERE person_id = ?");
                $stmt->bind_param("i", $person_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($p_row = $res->fetch_assoc()) {
                    $person_name = $p_row['name'];
                }
                $stmt->close();
            }
            $enriched_array[] = ['person_name' => $person_name, 'role' => $role, 'person_id' => $person_id];
        }
    } elseif ($type === 'characters') { 
        foreach ($data_array as $character_id) {
            $char_name = 'Sconosciuto (ID: ' . $character_id . ')';
            if ($character_id) {
                $stmt = $mysqli->prepare("SELECT name FROM characters WHERE character_id = ?");
                $stmt->bind_param("i", $character_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($c_row = $res->fetch_assoc()) {
                    $char_name = $c_row['name'];
                }
                $stmt->close();
            }
            $enriched_array[] = ['character_name' => $char_name, 'character_id' => $character_id];
        }
    } elseif ($type === 'variants') { 
        // Logica di arricchimento per le variant
        foreach ($data_array as $variant_item) {
            $enriched_item = $variant_item;
            $artist_id = $variant_item['artist_id_proposal'] ?? null;
            $artist_name = 'Non specificato';
            if ($artist_id) {
                $stmt = $mysqli->prepare("SELECT name FROM persons WHERE person_id = ?");
                $stmt->bind_param("i", $artist_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($p_row = $res->fetch_assoc()) {
                    $artist_name = $p_row['name'];
                }
                $stmt->close();
            }
            $enriched_item['artist_name'] = $artist_name;
            $enriched_array[] = $enriched_item;
        }
    } else { 
        return $data_array;
    }
    return $enriched_array;
}
// --- FINE FUNZIONE MODIFICATA ---


function display_diff($original_value, $proposed_value, $label) {
    // ... (la funzione display_diff rimane quasi identica, ma ora riceverà array già arricchiti per staff/autori/personaggi)
    // Assicuriamoci che gestisca bene gli array per la visualizzazione
    $original_value_display = $original_value;
    $proposed_value_display = $proposed_value;

    // Se è un array, formattalo come JSON per la visualizzazione leggibile
    if (is_array($original_value)) $original_value_display = json_encode($original_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (is_array($proposed_value)) $proposed_value_display = json_encode($proposed_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $original_text = htmlspecialchars((string)($original_value_display ?: 'Non presente'));
    $proposed_text = htmlspecialchars((string)($proposed_value_display ?: 'Non presente/Rimosso'));
    
    $row_class = '';
    $are_different = false;

    if (is_array($original_value) || is_array($proposed_value)) {
        // Un confronto semplice per array (può essere migliorato per differenze più granulari)
        if (json_encode($original_value) !== json_encode($proposed_value)) {
             $are_different = true;
        }
    } else {
         if (trim((string)$original_value) !== trim((string)$proposed_value)) {
            $are_different = true;
        }
    }

    if ($are_different) {
        $row_class = 'diff-row';
        $td_original = "<td class='diff-original'>" . (is_array($original_value) || strpos($original_text, '[{') === 0 || strpos($original_text, '{') === 0 ? "<pre>{$original_text}</pre>" : $original_text) . "</td>";
        $td_proposed = "<td class='diff-proposed'>" . (is_array($proposed_value) || strpos($proposed_text, '[{') === 0 || strpos($proposed_text, '{') === 0 ? "<pre>{$proposed_text}</pre>" : $proposed_text) . "</td>";
        return "<tr class='{$row_class}'><td><strong>" . htmlspecialchars($label) . ":</strong></td>{$td_original}{$td_proposed}</tr>";
    }
    
    $colspan_val = isset($GLOBALS['original_data']) && $GLOBALS['original_data'] !== null ? 2 : 1;
    return "<tr><td><strong>" . htmlspecialchars($label) . ":</strong></td><td colspan='{$colspan_val}'>" . (is_array($proposed_value) || strpos($proposed_text, '[{') === 0 || strpos($proposed_text, '{') === 0 ? "<pre>{$proposed_text}</pre>" : $proposed_text) . "</td></tr>";
}

// ... (display_image_diff come prima) ...
function display_image_diff($original_image_path, $proposed_image_path, $label, $is_pending_path) {
    $html = "<tr><td><strong>" . htmlspecialchars($label) . ":</strong></td>";
    $original_display = "<em>Nessuna immagine</em>";
    if ($original_image_path && file_exists(UPLOADS_PATH . $original_image_path)) {
        $original_display = "<img src='" . UPLOADS_URL . htmlspecialchars($original_image_path) . "' alt='Originale' style='max-width:100px; max-height:150px; display:block;'> (" . htmlspecialchars($original_image_path) . ")";
    } elseif ($original_image_path) {
        $original_display = "<em>Path originale: " . htmlspecialchars($original_image_path) . " (file non trovato)</em>";
    }
    
    $proposed_display = "<em>Nessuna immagine proposta / Rimozione</em>";
    if ($proposed_image_path) {
        $image_source_path = UPLOADS_PATH . $proposed_image_path;
        $image_url_path = UPLOADS_URL . $proposed_image_path;

        if (file_exists($image_source_path)) {
            $proposed_display = "<img src='" . htmlspecialchars($image_url_path) . "' alt='Proposta' style='max-width:100px; max-height:150px; display:block;'> (" . htmlspecialchars($proposed_image_path) .")";
        } else {
            $proposed_display = "<em>Path proposto: " . htmlspecialchars($proposed_image_path) . " (file non trovato o in attesa)</em>";
        }
    }

    if ($original_image_path !== $proposed_image_path) { 
        $html .= "<td class='diff-original'>" . $original_display . "</td><td class='diff-proposed'>" . $proposed_display . "</td>";
    } else {
        $html .= "<td colspan='2'>" . $proposed_display . "</td>";
    }
    $html .= "</tr>";
    return $html;
}

?>

<style>
    .proposal-details table { width: 100%; border-collapse: collapse; }
    .proposal-details th, .proposal-details td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
    .proposal-details th { background-color: #f2f2f2; }
    .proposal-actions { margin-top: 20px; }
    .diff-row td { /* background-color: #fff3cd; */ } 
    .diff-original { background-color: #ffeaea !important; } 
    .diff-proposed { background-color: #eaffea !important; } 
    .proposal-section { margin-bottom: 30px; padding:15px; border: 1px solid #eee; border-radius:5px; background-color:#fdfdfd;}
    .proposal-section h3 { margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; }
    .proposal-details pre { white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; padding: 5px; border-radius:3px; border:1px solid #eee; font-size:0.9em;}
</style>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <p><a href="proposals_manage.php" class="btn btn-secondary btn-sm">&laquo; Torna alla Lista Proposte</a></p>

    <?php if ($error_message): ?>
        <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($proposal_data): ?>
        <div class="proposal-section">
            <h3>Dettagli Proposta</h3>
            <table class="table">
                <tr><td style="width:25%;"><strong>ID Proposta:</strong></td><td><?php echo htmlspecialchars($proposal_type . '_' . $pending_id); ?></td></tr>
                <tr><td><strong>Tipo Entità:</strong></td><td><?php echo htmlspecialchars($proposal_data['proposal_type_label'] ?? ucfirst($proposal_type)); ?></td></tr>
                <tr><td><strong>Azione Proposta:</strong></td><td><?php echo htmlspecialchars(ucfirst($proposal_data['action_type'])); ?></td></tr>
                <tr><td><strong>Proposto da:</strong></td><td><?php echo htmlspecialchars($proposal_data['proposer_username']); ?> (ID: <?php echo $proposal_data['proposer_user_id']; ?>)</td></tr>
                <tr><td><strong>Data Proposta:</strong></td><td><?php echo date("d/m/Y H:i:s", strtotime($proposal_data['proposed_at'])); ?></td></tr>
                <?php if ($proposal_data['action_type'] === 'edit' && ($proposal_data[$original_id_column_map[$proposal_type]] ?? null)): ?>
                    <tr><td><strong>ID Elemento Originale:</strong></td><td><?php echo htmlspecialchars($proposal_data[$original_id_column_map[$proposal_type]]); ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="proposal-section">
            <h3>Dati Proposti <?php if ($original_data) echo "/ Confronto con Originale"; ?></h3>
            <table class="table proposal-details">
                <thead>
                    <tr>
                        <th style="width:25%;">Campo</th>
                        <?php if ($original_data): ?><th style="width:37.5%;">Originale</th><?php endif; ?>
                        <th style="<?php echo $original_data ? 'width:37.5%;' : 'width:75%;'; ?>">Proposto</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($proposal_type === 'comic') {
                    echo display_diff($original_data['issue_number'] ?? null, $proposal_data['issue_number'], 'Numero Albo');
                    echo display_diff($original_data['title'] ?? null, $proposal_data['title'], 'Titolo Albo');
                    echo display_diff($original_data['publication_date'] ?? null, $proposal_data['publication_date'], 'Data Pubblicazione');
                    echo display_diff($original_data['description'] ?? null, $proposal_data['description'], 'Descrizione');
                    echo display_image_diff($original_data['cover_image'] ?? null, $proposal_data['cover_image_proposal'], 'Copertina', true);
                    echo display_image_diff($original_data['back_cover_image'] ?? null, $proposal_data['back_cover_image_proposal'], 'Retrocopertina', true);
                    echo display_diff($original_data['editor'] ?? null, $proposal_data['editor'], 'Editore');
                    echo display_diff($original_data['pages'] ?? null, $proposal_data['pages'], 'Pagine');
                    echo display_diff($original_data['price'] ?? null, $proposal_data['price'], 'Prezzo');
                    echo display_diff($original_data['periodicity'] ?? null, $proposal_data['periodicity'], 'Periodicità');
                    echo display_diff($original_data['gadget_name'] ?? null, $proposal_data['gadget_name_proposal'], 'Nome Gadget');
                    echo display_image_diff($original_data['gadget_image'] ?? null, $proposal_data['gadget_image_proposal'], 'Immagine Gadget', true);
                    
                    $original_cf_arr = $original_data['custom_fields_decoded'] ?? [];
                    $proposed_cf_arr = enrich_proposal_data_with_names($proposal_data['custom_fields_proposal'] ?? '[]', 'custom_fields');
                    echo display_diff($original_cf_arr, $proposed_cf_arr, 'Campi Custom');

                    $original_staff_arr = $original_data['staff_array'] ?? [];
                    $proposed_staff_arr = enrich_proposal_data_with_names($proposal_data['staff_proposal_json'] ?? '[]', 'staff');
                    echo display_diff($original_staff_arr, $proposed_staff_arr, 'Staff Albo');
                    
                    // --- BLOCCO MODIFICATO ---
                    $original_variants_arr = $original_data['variants_array'] ?? [];
                    // Arricchiamo anche le variant proposte con il nome dell'artista
                    $proposed_variants_arr = enrich_proposal_data_with_names($proposal_data['variant_covers_proposal_json'] ?? '[]', 'variants');
                    echo display_diff($original_variants_arr, $proposed_variants_arr, 'Copertine Variant');
                    // --- FINE BLOCCO MODIFICATO ---

                } elseif ($proposal_type === 'story') {
                    $comic_context_issue = $proposal_data['comic_issue_number'] ?? ($original_data['issue_number'] ?? 'N/D'); // issue_number per il contesto albo
                    echo "<tr><td><strong>Contesto Albo:</strong></td><td colspan='".($original_data ? 2:1)."'>Topolino #".htmlspecialchars($comic_context_issue)." (ID Albo: ".htmlspecialchars($proposal_data['comic_id_context']).")</td></tr>";
                    echo display_diff($original_data['title'] ?? null, $proposal_data['title_proposal'], 'Titolo Storia');
                    echo display_diff($original_data['story_title_main'] ?? null, $proposal_data['story_title_main_proposal'], 'Titolo Principale Saga');
                    echo display_diff($original_data['part_number'] ?? null, $proposal_data['part_number_proposal'], 'Numero Parte');
                    echo display_diff($original_data['total_parts'] ?? null, $proposal_data['total_parts_proposal'], 'Parti Totali');
                    echo display_image_diff($original_data['first_page_image'] ?? null, $proposal_data['first_page_image_proposal'], 'Immagine Prima Pagina', true);
                    echo display_diff($original_data['sequence_in_comic'] ?? null, $proposal_data['sequence_in_comic_proposal'], 'Ordine nel Fumetto');
                    echo display_diff($original_data['series_id'] ?? null, $proposal_data['series_id_proposal'], 'Serie ID');
                    echo display_diff($original_data['series_episode_number'] ?? null, $proposal_data['series_episode_number_proposal'], 'Episodio Serie');
                    echo display_diff($original_data['notes'] ?? null, $proposal_data['notes_proposal'], 'Note');
                    
                    $original_authors_arr = $original_data['authors_array'] ?? [];
                    $proposed_authors_arr = enrich_proposal_data_with_names($proposal_data['authors_proposal_json'] ?? '[]', 'authors');
                    echo display_diff($original_authors_arr, $proposed_authors_arr, 'Autori');
                    
                    $original_characters_arr = $original_data['characters_array'] ?? []; // Questo è un array di ID
                    // Per visualizzare i nomi, la funzione enrich li recupererà
                    $proposed_characters_enriched_arr = enrich_proposal_data_with_names($proposal_data['characters_proposal_json'] ?? '[]', 'characters');
                    // Per i dati originali, dovremmo fare lo stesso se $original_data['characters_array'] contiene solo ID
                    $original_characters_enriched_arr = [];
                    if(!empty($original_data['characters_array'])){
                        foreach($original_data['characters_array'] as $char_id_orig){
                            $char_name_orig = 'Sconosciuto (ID: ' . $char_id_orig . ')';
                            $stmt_char_orig_name = $mysqli->prepare("SELECT name FROM characters WHERE character_id = ?");
                            $stmt_char_orig_name->bind_param("i", $char_id_orig);
                            $stmt_char_orig_name->execute();
                            $res_char_orig = $stmt_char_orig_name->get_result();
                            if($r_co = $res_char_orig->fetch_assoc()) $char_name_orig = $r_co['name'];
                            $stmt_char_orig_name->close();
                            $original_characters_enriched_arr[] = ['character_name' => $char_name_orig, 'character_id' => $char_id_orig];
                        }
                    }
                    echo display_diff($original_characters_enriched_arr, $proposed_characters_enriched_arr, 'Personaggi');

                } elseif ($proposal_type === 'person') {
                    echo display_diff($original_data['name'] ?? null, $proposal_data['name_proposal'], 'Nome Persona');
                    echo display_diff($original_data['biography'] ?? null, $proposal_data['biography_proposal'], 'Biografia');
                    echo display_image_diff($original_data['person_image'] ?? null, $proposal_data['person_image_proposal'], 'Immagine Persona', true);
                
                } elseif ($proposal_type === 'character') {
                    echo display_diff($original_data['name'] ?? null, $proposal_data['name_proposal'], 'Nome Personaggio');
                    echo display_diff($original_data['description'] ?? null, $proposal_data['description_proposal'], 'Descrizione');
                    echo display_image_diff($original_data['character_image'] ?? null, $proposal_data['character_image_proposal'], 'Immagine Personaggio', true);
                    echo display_diff($original_data['first_appearance_comic_id'] ?? null, $proposal_data['first_appearance_comic_id_proposal'], 'ID Albo 1a App.');
                    echo display_diff($original_data['first_appearance_story_id'] ?? null, $proposal_data['first_appearance_story_id_proposal'], 'ID Storia 1a App.');
                    echo display_diff($original_data['first_appearance_notes'] ?? null, $proposal_data['first_appearance_notes_proposal'], 'Note 1a App.');
                    echo display_diff($original_data['is_first_appearance_verified'] ?? '0', $proposal_data['is_first_appearance_verified_proposal'] == 1 ? 'Sì (1)' : 'No (0)', 'Verificata 1a App.');

                } elseif ($proposal_type === 'series') {
                    echo display_diff($original_data['title'] ?? null, $proposal_data['title_proposal'], 'Titolo Serie');
                    echo display_diff($original_data['description'] ?? null, $proposal_data['description_proposal'], 'Descrizione Serie');
                    echo display_diff($original_data['start_date'] ?? null, $proposal_data['start_date_proposal'], 'Data Inizio Serie');
                    echo display_image_diff($original_data['image_path'] ?? null, $proposal_data['image_path_proposal'], 'Immagine Serie', true);
                }
                ?>
                </tbody>
            </table>
        </div>

        <div class="proposal-actions">
            <h3>Azioni di Revisione</h3>
            <form action="proposal_actions.php" method="POST">
                <input type="hidden" name="proposal_type" value="<?php echo htmlspecialchars($proposal_type); ?>">
                <input type="hidden" name="pending_id" value="<?php echo $pending_id; ?>">
                
                <div class="form-group">
                    <label for="admin_review_notes">Note dell'Admin (opzionali):</label>
                    <textarea name="admin_review_notes" id="admin_review_notes" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" name="action_review" value="approve" class="btn btn-success" onclick="return confirm('Approvare questa proposta? L\'azione modificherà i dati principali.');">Approva Proposta</button>
                <button type="submit" name="action_review" value="reject" class="btn btn-danger" onclick="return confirm('Rifiutare questa proposta?');">Rifiuta Proposta</button>
            </form>
        </div>

    <?php else: ?>
        <p>Impossibile caricare i dettagli della proposta.</p>
    <?php endif; ?>
</div>

<?php
$mysqli->close();
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
?>