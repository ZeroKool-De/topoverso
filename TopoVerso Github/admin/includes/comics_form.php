<?php
// topolinolib/admin/includes/comics_form.php
// (codice iniziale del file invariato)
?>
<h3>
    <?php
    if ($action === 'add') {
        echo ($is_contributor && !$is_true_admin) ? 'Proponi Nuovo Fumetto' : 'Aggiungi Nuovo Fumetto';
    } else { // 'edit'
        echo ($is_contributor && !$is_true_admin) ? 'Proponi Modifiche al Fumetto' : 'Modifica Fumetto';
        if ($comic_id_to_edit) { 
            echo ": #" . htmlspecialchars($comic_data['issue_number'] ?? $comic_id_to_edit);
            if (!empty($comic_data['title'])) {
                echo " - <em>" . htmlspecialchars($comic_data['title']) . "</em>";
            }
        }
    }
    ?>
</h3>
<div class="form-top-actions" style="margin-bottom: 20px; padding-bottom:15px; border-bottom: 1px solid #dee2e6;">
    <button type="submit" form="comicForm" class="btn btn-success">
        <?php
        if ($action === 'add') {
            echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Aggiunta' : 'Aggiungi Fumetto';
        } else {
            echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Modifiche' : 'Salva Modifiche';
        }
        ?>
    </button>
    <a href="comics_manage.php?action=list" class="btn btn-secondary">Annulla</a>
    <?php if ($action === 'edit' && $comic_id_to_edit): ?>
        <a href="<?php echo BASE_URL; ?>comic_detail.php?id=<?php echo $comic_id_to_edit; ?>" target="_blank" class="btn btn-info" style="margin-left:10px;">Scheda Pubblica</a>
    <?php endif; ?>
</div>

<?php if ($is_contributor && !$is_true_admin): ?>
    <p class="message info" style="font-size:0.9em;">
        <strong>Nota per i Contributori:</strong> Le tue modifiche o aggiunte verranno inviate come proposte e dovranno essere approvate da un amministratore prima di diventare visibili pubblicamente. Le immagini verranno caricate in un'area temporanea e finalizzate dall'admin.
    </p>
<?php endif; ?>

<form id="comicForm" action="<?php echo BASE_URL; ?>admin/actions/comics_actions.php" method="POST" enctype="multipart/form-data">
    <?php if ($action === 'edit'): ?>
        <input type="hidden" name="edit_comic" value="1">
        <input type="hidden" name="comic_id" value="<?php echo $comic_id_to_edit; ?>">
    <?php else: // add ?>
        <input type="hidden" name="add_comic" value="1">
    <?php endif; ?>
    
    <input type="hidden" name="current_cover_image" value="<?php echo htmlspecialchars($comic_data['cover_image'] ?? ''); ?>">
    <input type="hidden" name="current_back_cover_image" value="<?php echo htmlspecialchars($comic_data['back_cover_image'] ?? ''); ?>">
    <input type="hidden" name="current_gadget_image" value="<?php echo htmlspecialchars($comic_data['gadget_image'] ?? ''); ?>">

    <h4>Dati Principali</h4>
    <div class="form-group">
        <label for="issue_number">Numero Albo:</label>
        <input type="text" id="issue_number" name="issue_number" class="form-control" value="<?php echo htmlspecialchars($comic_data['issue_number']); ?>" required>
    </div>
    <div class="form-group">
        <label for="title">Titolo Albo (opzionale):</label>
        <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($comic_data['title'] ?? ''); ?>">
    </div>
    <div class="form-group">
        <label for="publication_date">Data Pubblicazione:</label>
        <input type="date" id="publication_date" name="publication_date" class="form-control" value="<?php echo htmlspecialchars($comic_data['publication_date'] ?? ''); ?>">
    </div>
    <div class="form-group">
        <label for="description">Descrizione:</label>
        <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($comic_data['description'] ?? ''); ?></textarea>
    </div>
    <div class="form-group">
                <label for="editor">Editore:</label>
                <?php
                // Definisci qui o recupera da una configurazione l'array degli editori
                $editori_predefiniti = [
                    "Mondadori",
                    "Walt Disney Italia",
                    "Panini Comics"
                    // Aggiungi altri se necessario
                ];
                $current_publisher = $comic_data['editor'] ?? ''; // 'editor' è la chiave usata nel tuo $comic_data
                ?>
                <select id="editor" name="editor" class="form-control">
                    <option value="">Seleziona un editore</option>
                    <?php foreach ($editori_predefiniti as $editore_option): ?>
                        <option value="<?php echo htmlspecialchars($editore_option); ?>" <?php echo ($current_publisher === $editore_option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($editore_option); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php
                    // Questo blocco serve per mostrare come opzione selezionata un valore preesistente
                    // nel database che non è tra i tuoi editori predefiniti.
                    // È utile se hai dati vecchi o vuoi permettere una transizione graduale.
                    if (!empty($current_publisher) && !in_array($current_publisher, $editori_predefiniti)):
                    ?>
                        <option value="<?php echo htmlspecialchars($current_publisher); ?>" selected>
                            <?php echo htmlspecialchars($current_publisher); ?> (Valore attuale non standard)
                        </option>
                    <?php endif; ?>
                </select>
            </div>
    <div class="form-group">
        <label for="pages">Numero Pagine:</label>
        <input type="text" id="pages" name="pages" class="form-control" value="<?php echo htmlspecialchars($comic_data['pages'] ?? ''); ?>" placeholder="Es. 128 oppure 120+4">
    </div>
    <div class="form-group">
        <label for="price">Prezzo:</label>
        <input type="text" id="price" name="price" class="form-control" value="<?php echo htmlspecialchars($comic_data['price'] ?? ''); ?>" placeholder="Es. 3.50 EUR oppure L. 700">
    </div>
    <div class="form-group">
        <label for="periodicity">Periodicità:</label>
        <select id="periodicity" name="periodicity" class="form-control">
            <?php foreach ($periodicity_options as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (isset($comic_data['periodicity']) && $comic_data['periodicity'] == $value) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <hr>
    <h4>Copertine e Autori</h4>
    
    <!-- === COPERTINA PRINCIPALE === -->
    <div class="cover-section" style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background-color: #f9f9f9;">
        <h5 style="color: #007bff; margin-bottom: 15px;">
            <i class="fas fa-image"></i> Copertina Principale
        </h5>
        
        <div class="form-group">
            <label for="cover_image">File Immagine Copertina:</label>
            <input type="file" id="cover_image" name="cover_image" class="form-control-file">
            <?php if ($action === 'edit' && $comic_data['cover_image']): ?>
                <p style="margin-top:10px;">Attuale: 
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($comic_data['cover_image']); ?>" alt="Copertina attuale" style="max-width: 100px; height: auto; margin-top: 5px; border:1px solid #ccc; padding:2px; vertical-align: middle;">
                    <label class="inline-label" style="margin-left: 10px;"><input type="checkbox" name="delete_cover_image" value="1"> Cancella immagine attuale</label>
                </p>
                <small class="form-text">Caricando una nuova immagine, quella attuale verrà sostituita (se non è un'immagine temporanea di una proposta precedente).</small>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="cover_artists">Artisti Copertina:</label>
            <select name="cover_artists[]" id="cover_artists" class="form-control select2-multiple" multiple="multiple" data-placeholder="Seleziona uno o più artisti per la copertina...">
                <?php 
                // Recupera gli artisti attualmente selezionati per la copertina
                $current_cover_artists = [];
                if ($action === 'edit' && $comic_id_to_edit) {
                    if (!empty($comic_data['cover_artists_json'])) {
                        $current_cover_artists = json_decode($comic_data['cover_artists_json'], true) ?: [];
                    } elseif (!empty($comic_data['cover_artist_id'])) {
                        // Fallback per il campo singolo (migrazione)
                        $current_cover_artists = [$comic_data['cover_artist_id']];
                    }
                }
                
                foreach ($all_persons_list as $person_option): ?>
                    <option value="<?php echo $person_option['person_id']; ?>" <?php echo in_array($person_option['person_id'], $current_cover_artists) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($person_option['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Puoi selezionare più artisti se la copertina è stata realizzata in collaborazione.</small>
        </div>
    </div>
    
    <!-- === RETROCOPERTINA === -->
    <div class="back-cover-section" style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background-color: #f9f9f9;">
        <h5 style="color: #28a745; margin-bottom: 15px;">
            <i class="fas fa-image"></i> Retrocopertina
        </h5>
        
        <div class="form-group">
            <label for="back_cover_image">File Immagine Retrocopertina (opzionale):</label>
            <input type="file" id="back_cover_image" name="back_cover_image" class="form-control-file">
            <?php if ($action === 'edit' && $comic_data['back_cover_image']): ?>
                <p style="margin-top:10px;">Attuale: 
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($comic_data['back_cover_image']); ?>" alt="Retrocopertina attuale" style="max-width: 100px; height: auto; margin-top: 5px; border:1px solid #ccc; padding:2px; vertical-align: middle;">
                     <label class="inline-label" style="margin-left: 10px;"><input type="checkbox" name="delete_back_cover_image" value="1"> Cancella immagine attuale</label>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="back_cover_artists">Artisti Retrocopertina:</label>
            <select name="back_cover_artists[]" id="back_cover_artists" class="form-control select2-multiple" multiple="multiple" data-placeholder="Seleziona uno o più artisti per la retrocopertina...">
                <?php 
                // Recupera gli artisti attualmente selezionati per la retrocopertina
                $current_back_cover_artists = [];
                if ($action === 'edit' && $comic_id_to_edit) {
                    if (!empty($comic_data['back_cover_artists_json'])) {
                        $current_back_cover_artists = json_decode($comic_data['back_cover_artists_json'], true) ?: [];
                    } elseif (!empty($comic_data['back_cover_artist_id'])) {
                        // Fallback per il campo singolo (migrazione)
                        $current_back_cover_artists = [$comic_data['back_cover_artist_id']];
                    }
                }
                
                foreach ($all_persons_list as $person_option): ?>
                    <option value="<?php echo $person_option['person_id']; ?>" <?php echo in_array($person_option['person_id'], $current_back_cover_artists) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($person_option['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Puoi selezionare più artisti se la retrocopertina è stata realizzata in collaborazione.</small>
        </div>
    </div>
    
    <hr>

    <h4>Gadget Allegato</h4>
    <div class="form-group">
        <label for="gadget_name">Nome Gadget Generale (opz.):</label>
        <input type="text" id="gadget_name" name="gadget_name" class="form-control" value="<?php echo htmlspecialchars($comic_data['gadget_name'] ?? ''); ?>">
        <small>Il nome principale del gadget. Le immagini specifiche possono avere didascalie individuali.</small>
    </div>
    
    <div class="form-group">
        <label for="gadget_image">Immagine Principale Gadget (opz.):</label>
        <input type="file" id="gadget_image" name="gadget_image" class="form-control-file">
        <?php if ($action === 'edit' && !empty($comic_data['gadget_image'])): ?>
            <p style="margin-top:10px;">Attuale: 
                <img src="<?php echo UPLOADS_URL . htmlspecialchars($comic_data['gadget_image']); ?>" alt="Immagine principale gadget" style="max-width: 100px; height: auto; margin-top: 5px; border:1px solid #ccc; padding:2px; vertical-align: middle;">
                <label class="inline-label" style="margin-left: 10px;"><input type="checkbox" name="delete_gadget_image" value="1"> Cancella immagine principale attuale</label>
            </p>
        <?php endif; ?>
        <small>Questa è l'immagine singola precedentemente associata al gadget. Sotto puoi aggiungere altre immagini.</small>
    </div>
    <hr style="border-style: dashed;">

    <h5>Galleria Immagini Gadget</h5>
    <div class="form-group">
        <label for="new_gadget_images_upload">Aggiungi Nuove Immagini per il Gadget:</label>
        <input type="file" id="new_gadget_images_upload" name="new_gadget_images_upload[]" class="form-control-file" multiple>
        <small class="form-text">Puoi selezionare più file (JPG, PNG, GIF, WEBP). Per ogni nuova immagine caricata, potrai aggiungere didascalia e ordine dopo il salvataggio iniziale (se admin) o saranno parte della proposta (se contributore).</small>
        </div>

    <?php if ($action === 'edit' && isset($comic_data['gadget_images_list']) && !empty($comic_data['gadget_images_list'])): ?>
        <p style="margin-top:15px;"><strong>Immagini Aggiuntive Gadget Esistenti:</strong></p>
        <div class="existing-gadget-images"> <?php // Classe CSS simile a existing-variant-covers ?>
            <?php foreach($comic_data['gadget_images_list'] as $gadget_img_item): ?>
                <div class="variant-cover-item"> <?php // Riusiamo lo stile delle variant per coerenza ?>
                    <input type="hidden" name="existing_gadget_image_ids[]" value="<?php echo $gadget_img_item['gadget_image_id']; ?>">
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($gadget_img_item['image_path']); ?>" alt="Gadget <?php echo htmlspecialchars($gadget_img_item['caption'] ?? ''); ?>" style="width: 80px; height: auto; max-height: 120px; object-fit: cover; margin-right: 15px; border:1px solid #ccc; padding:2px; border-radius: 3px;">
                    <div style="flex-grow: 1;">
                        <div class="form-group">
                            <label for="existing_gadget_image_caption_<?php echo $gadget_img_item['gadget_image_id']; ?>">Didascalia Img. Gadget:</label>
                            <input type="text" name="existing_gadget_image_captions[<?php echo $gadget_img_item['gadget_image_id']; ?>]" id="existing_gadget_image_caption_<?php echo $gadget_img_item['gadget_image_id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($gadget_img_item['caption'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="existing_gadget_image_sort_order_<?php echo $gadget_img_item['gadget_image_id']; ?>">Ordine:</label>
                            <input type="number" name="existing_gadget_image_sort_orders[<?php echo $gadget_img_item['gadget_image_id']; ?>]" id="existing_gadget_image_sort_order_<?php echo $gadget_img_item['gadget_image_id']; ?>" class="form-control form-control-sm" style="width: 90px;" value="<?php echo htmlspecialchars($gadget_img_item['sort_order'] ?? '0'); ?>">
                        </div>
                         <label class="inline-label">
                            <input type="checkbox" name="delete_gadget_images[]" value="<?php echo $gadget_img_item['gadget_image_id']; ?>"> 
                            Cancella Questa Immagine Gadget
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif($action === 'edit'): ?>
        <p><small>Nessuna immagine aggiuntiva per il gadget attualmente associata a questo albo.</small></p>
    <?php endif; ?>
    <hr>
    <h4>Staff Albo (Altri Ruoli)</h4>
    <p><small>Seleziona uno o più autori/artisti per ogni ruolo. I copertinisti sono già stati selezionati sopra.</small></p>
    <?php
    $comic_staff_roles_config = [
        'Direttore Responsabile' => 'staff_director_responsible',
    ];

    foreach ($comic_staff_roles_config as $role_label => $select_name) {
        $selected_person_ids_for_this_role = [];
        if (isset($comic_data['staff_array']) && is_array($comic_data['staff_array'])) {
            foreach ($comic_data['staff_array'] as $staff_member) {
                if ($staff_member['role'] === $role_label) {
                    $selected_person_ids_for_this_role[] = $staff_member['person_id'];
                }
            }
        } elseif ($action === 'edit' && $comic_id_to_edit) {
            $stmt_get_staff = $mysqli->prepare("SELECT person_id FROM comic_persons WHERE comic_id = ? AND role = ?");
            $stmt_get_staff->bind_param("is", $comic_id_to_edit, $role_label);
            $stmt_get_staff->execute();
            $result_staff = $stmt_get_staff->get_result();
            while ($staff_row = $result_staff->fetch_assoc()) {
                $selected_person_ids_for_this_role[] = $staff_row['person_id'];
            }
            $stmt_get_staff->close();
        }
        ?>
        <div class="form-group">
            <label for="<?php echo $select_name; ?>"><?php echo htmlspecialchars($role_label); ?>:</label>
            <select name="<?php echo $select_name; ?>[]" id="<?php echo $select_name; ?>" class="form-control select2-multiple" multiple="multiple" data-placeholder="Seleziona...">
                <?php foreach ($all_persons_list as $person_option): ?>
                    <option value="<?php echo $person_option['person_id']; ?>" <?php echo in_array($person_option['person_id'], $selected_person_ids_for_this_role) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($person_option['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php } ?>
    <hr>
    
    <h4>Copertine Variant</h4>
    <div id="new-variants-container">
        <div class="form-group">
            <label>Aggiungi Nuove Copertine Variant:</label>
            <div class="new-variant-item" style="display: flex; gap: 15px; align-items: flex-start; margin-bottom: 15px;">
                <div style="flex-basis: 30%;">
                    <label for="variant_covers_upload_0" style="font-size:0.9em; font-weight:normal;">File Immagine:</label>
                    <input type="file" id="variant_covers_upload_0" name="variant_covers_upload[]" class="form-control-file">
                </div>
                <div style="flex-basis: 40%;">
                    <label for="new_variant_artists_0" style="font-size:0.9em; font-weight:normal;">Artista (Opz.):</label>
                    <select name="new_variant_artists[]" id="new_variant_artists_0" class="form-control form-control-sm select2-single" data-placeholder="Seleziona artista...">
                        <option value="">-- Nessun artista --</option>
                        <?php foreach ($all_persons_list as $person_option): ?>
                        <option value="<?php echo $person_option['person_id']; ?>">
                            <?php echo htmlspecialchars($person_option['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex-basis: 30%;">
                    <label for="new_variant_captions_0" style="font-size:0.9em; font-weight:normal;">Didascalia (Opz.):</label>
                    <input type="text" name="new_variant_captions[]" id="new_variant_captions_0" class="form-control form-control-sm">
                </div>
            </div>
        </div>
        <button type="button" id="add-another-variant-btn" class="btn btn-sm btn-secondary" style="margin-bottom:15px;">+ Aggiungi un'altra Variant</button>
    </div>

    <?php if ($action === 'edit' && !empty($comic_data['variant_covers_list'])): ?>
        <p style="margin-top:15px;"><strong>Copertine Variant Esistenti:</strong></p>
        <div class="existing-variant-covers">
            <?php foreach($comic_data['variant_covers_list'] as $variant): ?>
                <div class="variant-cover-item">
                    <img src="<?php echo UPLOADS_URL . htmlspecialchars($variant['image_path']); ?>" alt="Variant <?php echo htmlspecialchars($variant['caption'] ?? ''); ?>">
                    <div style="flex-grow: 1;">
                        <div class="form-group">
                            <label for="variant_caption_<?php echo $variant['variant_cover_id']; ?>">Didascalia:</label>
                            <input type="text" name="variant_captions[<?php echo $variant['variant_cover_id']; ?>]" id="variant_caption_<?php echo $variant['variant_cover_id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($variant['caption'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="variant_artist_<?php echo $variant['variant_cover_id']; ?>">Artista Variant:</label>
                            <select name="variant_artists[<?php echo $variant['variant_cover_id']; ?>]" id="variant_artist_<?php echo $variant['variant_cover_id']; ?>" class="form-control form-control-sm select2-single" data-placeholder="Seleziona artista...">
                                <option value="">-- Nessun artista --</option>
                                <?php foreach ($all_persons_list as $person_option): ?>
                                    <option value="<?php echo $person_option['person_id']; ?>" <?php echo (isset($variant['artist_id']) && $variant['artist_id'] == $person_option['person_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($person_option['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="variant_sort_order_<?php echo $variant['variant_cover_id']; ?>">Ordine:</label>
                            <input type="number" name="variant_sort_order[<?php echo $variant['variant_cover_id']; ?>]" id="variant_sort_order_<?php echo $variant['variant_cover_id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($variant['sort_order'] ?? '0'); ?>">
                        </div>
                         <label class="inline-label">
                            <input type="checkbox" name="delete_variant_covers[]" value="<?php echo $variant['variant_cover_id']; ?>"> 
                            <?php echo ($is_contributor && !$is_true_admin) ? 'Proponi Cancellazione' : 'Cancella Questa Variant'; ?>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif($action === 'edit'): ?>
        <p>Nessuna copertina variant attualmente associata a questo albo.</p>
    <?php endif; ?>
    <hr>
    <h4>Campi Personalizzati</h4>
    <?php if (!empty($custom_field_definitions)): ?>
        <?php foreach ($custom_field_definitions as $def): ?>
            <div class="form-group">
                <label for="custom_field_<?php echo htmlspecialchars($def['field_key']); ?>"><?php echo htmlspecialchars($def['field_label']); ?>:</label>
                <?php $current_custom_value = $comic_data['custom_fields'][$def['field_key']] ?? ''; ?>
                <?php if ($def['field_type'] === 'textarea'): ?>
                    <textarea name="custom_fields[<?php echo htmlspecialchars($def['field_key']); ?>]" id="custom_field_<?php echo htmlspecialchars($def['field_key']); ?>" class="form-control"><?php echo htmlspecialchars($current_custom_value); ?></textarea>
                <?php elseif ($def['field_type'] === 'checkbox'): ?>
                    <input type="hidden" name="custom_fields[<?php echo htmlspecialchars($def['field_key']); ?>]" value="0"> 
                    <input type="checkbox" name="custom_fields[<?php echo htmlspecialchars($def['field_key']); ?>]" id="custom_field_<?php echo htmlspecialchars($def['field_key']); ?>" class="form-check-input" value="1" <?php echo ($current_custom_value == '1') ? 'checked' : ''; ?>>
                <?php else: ?>
                    <input type="<?php echo htmlspecialchars($def['field_type']); ?>" name="custom_fields[<?php echo htmlspecialchars($def['field_key']); ?>]" id="custom_field_<?php echo htmlspecialchars($def['field_key']); ?>" class="form-control" value="<?php echo htmlspecialchars($current_custom_value); ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Nessun campo personalizzato definito per i fumetti. Puoi aggiungerli da "Campi Custom" nella dashboard admin.</p>
    <?php endif; ?>
    
    <div class="form-group" style="margin-top: 20px;">
        <button type="submit" class="btn btn-success">
             <?php
            if ($action === 'add') {
                echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Aggiunta' : 'Aggiungi Fumetto';
            } else {
                echo ($is_contributor && !$is_true_admin) ? 'Invia Proposta Modifiche' : 'Salva Modifiche';
            }
            ?>
        </button>
        <a href="comics_manage.php?action=list" class="btn btn-secondary">Annulla</a>
    </div>
    <?php if ($action === 'edit' && $comic_id_to_edit): ?>
        <hr style="margin-top:30px; margin-bottom:30px;">
        <div class="text-center">
            <a href="<?php echo BASE_URL; ?>admin/stories_manage.php?comic_id=<?php echo $comic_id_to_edit; ?>" class="btn btn-lg btn-info">
                <?php echo ($is_contributor && !$is_true_admin) ? 'Proponi Storie / Modifiche Storie' : 'Gestisci le Storie di Questo Albo'; ?>
            </a>
        </div>
    <?php endif; ?>
</form>

<style>
.cover-section, .back-cover-section {
    transition: all 0.3s ease;
}

.cover-section:hover, .back-cover-section:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.variant-cover-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background-color: #fafafa;
}

.variant-cover-item img {
    width: 80px;
    height: auto;
    max-height: 120px;
    object-fit: cover;
    border: 1px solid #ccc;
    padding: 2px;
    border-radius: 3px;
}

.existing-variant-covers, .existing-gadget-images {
    margin-top: 15px;
}

.new-variant-item {
    border: 1px dashed #ccc;
    padding: 15px;
    border-radius: 5px;
    background-color: #f8f9fa;
    margin-bottom: 10px;
}

.inline-label {
    font-weight: normal;
    margin-left: 10px;
}

.form-control-sm {
    font-size: 0.875rem;
}
</style>

<script>
$(document).ready(function() {
    // Funzione per inizializzare Select2 su un elemento specifico
    function initializeSelect2(selector, isMultiple = false) {
        $(selector).select2({
            placeholder: $(selector).data('placeholder'),
            allowClear: true,
            width: '100%',
            multiple: isMultiple
        });
    }
    
    // Inizializza tutti i Select2 già presenti nella pagina
    $('.select2-multiple').each(function() {
        initializeSelect2(this, true);
    });
    
    $('.select2-single').each(function() {
        initializeSelect2(this, false);
    });

    // Logica per aggiungere dinamicamente nuove sezioni per l'upload di variant
    let variantCounter = 1;
    $('#add-another-variant-btn').on('click', function() {
        const newVariantHtml = `
            <div class="new-variant-item" style="display: flex; gap: 15px; align-items: flex-start; margin-bottom: 15px; border-top: 1px dashed #ccc; padding-top: 15px;">
                <div style="flex-basis: 30%;">
                    <label for="variant_covers_upload_${variantCounter}" style="font-size:0.9em; font-weight:normal;">File Immagine:</label>
                    <input type="file" id="variant_covers_upload_${variantCounter}" name="variant_covers_upload[]" class="form-control-file">
                </div>
                <div style="flex-basis: 40%;">
                    <label for="new_variant_artists_${variantCounter}" style="font-size:0.9em; font-weight:normal;">Artista (Opz.):</label>
                    <select name="new_variant_artists[]" id="new_variant_artists_${variantCounter}" class="form-control form-control-sm select2-single" data-placeholder="Seleziona artista...">
                        <option value="">-- Nessun artista --</option>
                        <?php foreach ($all_persons_list as $person_option): ?>
                        <option value="<?php echo $person_option['person_id']; ?>"><?php echo htmlspecialchars(addslashes($person_option['name'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex-basis: 30%;">
                    <label for="new_variant_captions_${variantCounter}" style="font-size:0.9em; font-weight:normal;">Didascalia (Opz.):</label>
                    <input type="text" name="new_variant_captions[]" id="new_variant_captions_${variantCounter}" class="form-control form-control-sm">
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-variant-btn" title="Rimuovi questo campo">-</button>
            </div>
        `;
        $('#new-variants-container').append(newVariantHtml);
        // Inizializza Select2 sul nuovo elemento
        initializeSelect2('#new_variant_artists_' + variantCounter, false);
        variantCounter++;
    });

    // Logica per rimuovere una sezione di upload variant
    $('#new-variants-container').on('click', '.remove-variant-btn', function() {
        $(this).closest('.new-variant-item').remove();
    });
});
</script>