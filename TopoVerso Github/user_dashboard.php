<?php
require_once 'config/config.php'; // Contiene session_start()
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; 

if (!isset($_SESSION['user_id_frontend'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id_frontend'];
$username = $_SESSION['username_frontend'] ?? 'Utente';
$user_role = $_SESSION['user_role_frontend'] ?? 'user';
$page_title = "La Mia Pagina";

$stmt_user = $mysqli->prepare("SELECT username, email, avatar_image_path FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();
$stmt_user->close();

if ($user_data && $user_data['username'] !== $_SESSION['username_frontend']) {
    $_SESSION['username_frontend'] = $user_data['username'];
    $username = $user_data['username'];
}
if ($user_data && isset($user_data['avatar_image_path']) && (!isset($_SESSION['avatar_frontend']) || $user_data['avatar_image_path'] !== $_SESSION['avatar_frontend'])) {
    $_SESSION['avatar_frontend'] = $user_data['avatar_image_path'];
}

$collection_preview_limit = 10; 
$preview_items = [];

$sql_real_preview = "
    SELECT 
        c.comic_id, c.slug, c.issue_number, c.title AS comic_title, c.cover_image, c.publication_date,
        uc.is_read, uc.rating, uc.added_at,
        'real' as item_type, NULL as placeholder_id, NULL as issue_number_placeholder
    FROM user_collections uc
    JOIN comics c ON uc.comic_id = c.comic_id
    WHERE uc.user_id = ? 
    ORDER BY uc.added_at DESC 
    LIMIT ? 
";
$stmt_real_preview = $mysqli->prepare($sql_real_preview);
$stmt_real_preview->bind_param("ii", $user_id, $collection_preview_limit);
$stmt_real_preview->execute();
$result_real_preview = $stmt_real_preview->get_result();
if ($result_real_preview) {
    while ($row = $result_real_preview->fetch_assoc()) {
        $preview_items[] = $row;
    }
    $result_real_preview->free();
}
$stmt_real_preview->close();

$remaining_limit = $collection_preview_limit - count($preview_items);
if ($remaining_limit > 0) {
    $sql_placeholder_preview = "
        SELECT 
            NULL as comic_id, NULL as slug, NULL as issue_number, NULL as comic_title, NULL as cover_image, NULL as publication_date,
            NULL as is_read, NULL as rating, ucp.added_at,
            'placeholder' as item_type, ucp.placeholder_id, ucp.issue_number_placeholder
        FROM user_collection_placeholders ucp
        WHERE ucp.user_id = ? AND ucp.status = 'pending'
        ORDER BY ucp.added_at DESC
        LIMIT ?
    ";
    $stmt_placeholder_preview = $mysqli->prepare($sql_placeholder_preview);
    $stmt_placeholder_preview->bind_param("ii", $user_id, $remaining_limit);
    $stmt_placeholder_preview->execute();
    $result_placeholder_preview = $stmt_placeholder_preview->get_result();
    if ($result_placeholder_preview) {
        while ($row = $result_placeholder_preview->fetch_assoc()) {
            $preview_items[] = $row;
        }
        $result_placeholder_preview->free();
    }
    $stmt_placeholder_preview->close();
}

usort($preview_items, function($a, $b) {
    return strtotime($b['added_at']) - strtotime($a['added_at']);
});

if (count($preview_items) > $collection_preview_limit) {
    $preview_items = array_slice($preview_items, 0, $collection_preview_limit);
}

$total_real_items_in_collection = 0;
$stmt_total_real_coll = $mysqli->prepare("SELECT COUNT(collection_id) as total FROM user_collections WHERE user_id = ?");
$stmt_total_real_coll->bind_param("i", $user_id);
$stmt_total_real_coll->execute();
$total_real_items_result = $stmt_total_real_coll->get_result();
$total_real_items_in_collection = ($total_real_items_result->fetch_assoc()['total']) ?? 0;
$stmt_total_real_coll->close();

$total_pending_placeholders = 0;
$stmt_total_placeholders = $mysqli->prepare("SELECT COUNT(placeholder_id) as total FROM user_collection_placeholders WHERE user_id = ? AND status = 'pending'");
$stmt_total_placeholders->bind_param("i", $user_id);
$stmt_total_placeholders->execute();
$total_placeholders_result = $stmt_total_placeholders->get_result();
$total_pending_placeholders = ($total_placeholders_result->fetch_assoc()['total']) ?? 0;
$stmt_total_placeholders->close();

$grand_total_collection_display = $total_real_items_in_collection + $total_pending_placeholders;

// --- LOGICA PER NOTIFICHE ---
$notifications = [];
$stmt_notif = $mysqli->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
if($stmt_notif){
    $stmt_notif->bind_param("i", $user_id);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    while ($row = $result_notif->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt_notif->close();
}
// --- FINE LOGICA NOTIFICHE ---

// Recupero proposte inviate dall'utente (solo se è un contributore)
$user_proposals = [];
if ($user_role === 'contributor') {
    // Proposte Fumetti
    $stmt_pc = $mysqli->prepare("SELECT pending_comic_id, issue_number, title, action_type, status, proposed_at, reviewed_at, admin_notes FROM pending_comics WHERE proposer_user_id = ? ORDER BY proposed_at DESC LIMIT 20");
    $stmt_pc->bind_param("i", $user_id);
    $stmt_pc->execute();
    $res_pc = $stmt_pc->get_result();
    while ($row = $res_pc->fetch_assoc()) {
        $row['proposal_link_id'] = 'comic_'.$row['pending_comic_id'];
        $row['type_label'] = 'Albo';
        $row['identifier'] = !empty($row['issue_number']) ? 'N. '.$row['issue_number'] : ($row['title'] ?: 'Nuovo Albo');
        $user_proposals[] = $row;
    }
    $stmt_pc->close();

    // Proposte Storie
    $stmt_ps = $mysqli->prepare("SELECT ps.pending_story_id, ps.title_proposal, ps.action_type, ps.status, ps.proposed_at, ps.reviewed_at, ps.admin_notes, c.issue_number as comic_context_issue FROM pending_stories ps LEFT JOIN comics c ON ps.comic_id_context = c.comic_id WHERE ps.proposer_user_id = ? ORDER BY ps.proposed_at DESC LIMIT 20");
    $stmt_ps->bind_param("i", $user_id);
    $stmt_ps->execute();
    $res_ps = $stmt_ps->get_result();
    while ($row = $res_ps->fetch_assoc()) {
        $row['proposal_link_id'] = 'story_'.$row['pending_story_id'];
        $row['type_label'] = 'Storia';
        $row['identifier'] = $row['title_proposal'] ?: 'Nuova Storia';
        if($row['comic_context_issue']) $row['identifier'] .= ' (per Albo #'.$row['comic_context_issue'].')';
        $user_proposals[] = $row;
    }
    $stmt_ps->close();

    // Proposte Persone
    $stmt_pp = $mysqli->prepare("SELECT pending_person_id, name_proposal, action_type, status, proposed_at, reviewed_at, admin_notes FROM pending_persons WHERE proposer_user_id = ? ORDER BY proposed_at DESC LIMIT 20");
    $stmt_pp->bind_param("i", $user_id);
    $stmt_pp->execute();
    $res_pp = $stmt_pp->get_result();
    while ($row = $res_pp->fetch_assoc()) {
        $row['proposal_link_id'] = 'person_'.$row['pending_person_id'];
        $row['type_label'] = 'Persona';
        $row['identifier'] = $row['name_proposal'] ?: 'Nuova Persona';
        $user_proposals[] = $row;
    }
    $stmt_pp->close();

    // Proposte Personaggi
    $stmt_pchar = $mysqli->prepare("SELECT pending_character_id, name_proposal, action_type, status, proposed_at, reviewed_at, admin_notes FROM pending_characters WHERE proposer_user_id = ? ORDER BY proposed_at DESC LIMIT 20");
    $stmt_pchar->bind_param("i", $user_id);
    $stmt_pchar->execute();
    $res_pchar = $stmt_pchar->get_result();
    while ($row = $res_pchar->fetch_assoc()) {
        $row['proposal_link_id'] = 'character_'.$row['pending_character_id'];
        $row['type_label'] = 'Personaggio';
        $row['identifier'] = $row['name_proposal'] ?: 'Nuovo Personaggio';
        $user_proposals[] = $row;
    }
    $stmt_pchar->close();

    // Proposte Serie
    $stmt_pss = $mysqli->prepare("SELECT pending_series_id, title_proposal, action_type, status, proposed_at, reviewed_at, admin_notes FROM pending_story_series WHERE proposer_user_id = ? ORDER BY proposed_at DESC LIMIT 20");
    $stmt_pss->bind_param("i", $user_id);
    $stmt_pss->execute();
    $res_pss = $stmt_pss->get_result();
    while ($row = $res_pss->fetch_assoc()) {
        $row['proposal_link_id'] = 'series_'.$row['pending_series_id'];
        $row['type_label'] = 'Serie';
        $row['identifier'] = $row['title_proposal'] ?: 'Nuova Serie';
        $user_proposals[] = $row;
    }
    $stmt_pss->close();
    
    usort($user_proposals, function($a, $b) {
        return strtotime($b['proposed_at']) - strtotime($a['proposed_at']);
    });
    $user_proposals = array_slice($user_proposals, 0, 50);
}


$message = $_SESSION['message'] ?? $_SESSION['profile_message'] ?? null;
$message_type = $_SESSION['message_type'] ?? $_SESSION['profile_message_type'] ?? null;
if ($message) {
    unset($_SESSION['message']); unset($_SESSION['message_type']);
    unset($_SESSION['profile_message']); unset($_SESSION['profile_message_type']);
}

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <div class="user-dashboard-header">
        <?php if (!empty($user_data['avatar_image_path'])): ?>
            <img src="<?php echo UPLOADS_URL . htmlspecialchars($user_data['avatar_image_path']); ?>" alt="Avatar di <?php echo htmlspecialchars($user_data['username']); ?>" class="user-avatar-large">
        <?php else: ?>
            <div class="user-avatar-large placeholder"><?php echo strtoupper(substr($user_data['username'], 0, 1)); ?></div>
        <?php endif; ?>
        <div>
            <h1><?php echo htmlspecialchars($user_data['username']); ?> 
                <?php if ($user_role === 'contributor'): ?>
                    <span style="font-size: 0.6em; color: #007bff; vertical-align: middle; background-color: #e7f3ff; padding: 3px 7px; border-radius: 5px; border: 1px solid #b3d7ff;">Contributore</span>
                <?php endif; ?>
            </h1>
            <p class="user-email"><?php echo htmlspecialchars($user_data['email']); ?></p>
            <p>
                <a href="logout_user.php">Logout</a>
                <?php if ($user_role === 'contributor'): ?>
                    <span style="margin-left: 15px;">|</span> <a href="<?php echo BASE_URL; ?>admin/index.php" title="Vai all'area contributi">Area Contributi</a>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <nav class="user-dashboard-tabs">
        <ul>
            <li><a href="#collezione" data-tab-target="collezione" class="user-tab-link active-user-tab">La Mia Collezione</a></li>
            <li><a href="#notifiche" data-tab-target="notifiche" class="user-tab-link">Notifiche</a></li>
            <?php if ($user_role === 'contributor'): ?>
            <li><a href="#proposte" data-tab-target="proposte" class="user-tab-link">Le Mie Proposte</a></li>
            <?php endif; ?>
            <li><a href="#profilo" data-tab-target="profilo" class="user-tab-link">Modifica Profilo</a></li>
        </ul>
    </nav>

    <div class="user-tab-content-panels">
        <div id="tab-content-collezione" class="user-tab-content active-user-content">
            <h2>Anteprima Collezione <small>(Ultimi aggiunti - Totale in lista: <?php echo $grand_total_collection_display; ?>)</small></h2>
            
            <div style="background-color: #eef7ff; border-left: 5px solid #2196F3; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <p style="margin: 0;"><strong>Possiedi un numero non presente nel catalogo?</strong><br>
                Puoi <a href="<?php echo BASE_URL; ?>my_full_collection.php#add-placeholder-form" style="font-weight: bold;">segnalarlo direttamente dalla tua pagina collezione completa</a>. Lo aggiungeremo alla tua lista come promemoria!</p>
            </div>
            <?php if (!empty($preview_items)): ?>
                <div class="collection-list">
                    <?php foreach ($preview_items as $item): ?>
                        <div class="collection-item <?php echo $item['item_type'] === 'placeholder' ? 'placeholder-item' : ''; ?>">
                            <div class="collection-item-cover">
                                <?php if ($item['item_type'] === 'real'): ?>
                                    <a href="comic_detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>">
                                    <?php if ($item['cover_image']): ?>
                                        <img src="<?php echo UPLOADS_URL . htmlspecialchars($item['cover_image']); ?>" alt="Cop. <?php echo htmlspecialchars($item['issue_number']); ?>">
                                    <?php else: echo generate_image_placeholder(htmlspecialchars($item['issue_number']), 70, 105, 'placeholder-img'); endif; ?>
                                    </a>
                                <?php else: ?>
                                    <a><?php echo generate_comic_placeholder_cover(htmlspecialchars($item['issue_number_placeholder']), 70, 105); ?></a>
                                <?php endif; ?>
                            </div>
                            <div class="collection-item-details">
                                <h3>
                                    <?php if ($item['item_type'] === 'real'): ?>
                                        <a href="comic_detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>">Topolino #<?php echo htmlspecialchars($item['issue_number']); ?><?php if (!empty($item['comic_title'])): ?> - <?php echo htmlspecialchars($item['comic_title']); endif; ?></a>
                                    <?php else: ?>
                                        <a>Topolino #<?php echo htmlspecialchars($item['issue_number_placeholder']); ?> (Non in catalogo)</a>
                                    <?php endif; ?>
                                </h3>
                                <p class="collection-item-info">
                                    <?php if ($item['item_type'] === 'real'): ?>
                                        Data Pubblicazione: <?php echo $item['publication_date'] ? date("d/m/Y", strtotime($item['publication_date'])) : 'N/D'; ?><br>
                                    <?php endif; ?>
                                    Aggiunto il: <?php echo date("d/m/Y H:i", strtotime($item['added_at'])); ?>
                                </p>
                                
                                <div class="collection-actions">
                                    <?php if ($item['item_type'] === 'real'): ?>
                                        <form action="collection_actions.php" method="POST"><input type="hidden" name="comic_id" value="<?php echo $item['comic_id']; ?>"><input type="hidden" name="action" value="update_read_status"><input type="hidden" name="redirect_url" value="user_dashboard.php#collezione"><label for="is_read_dash_<?php echo $item['comic_id']; ?>">Letto:</label><input type="checkbox" name="is_read" id="is_read_dash_<?php echo $item['comic_id']; ?>" value="1" <?php echo $item['is_read'] ? 'checked' : ''; ?> onchange="this.form.submit()"></form>
                                        <form action="collection_actions.php" method="POST"><input type="hidden" name="comic_id" value="<?php echo $item['comic_id']; ?>"><input type="hidden" name="action" value="update_rating"><input type="hidden" name="redirect_url" value="user_dashboard.php#collezione"><label for="rating_dash_<?php echo $item['comic_id']; ?>">Voto:</label><select name="rating" id="rating_dash_<?php echo $item['comic_id']; ?>" onchange="this.form.submit()"><option value="0" <?php echo is_null($item['rating']) ? 'selected' : ''; ?>>N/D</option><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?php echo $i; ?>" <?php echo ($item['rating'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?> ★</option><?php endfor; ?></select></form>
                                        <form action="collection_actions.php" method="POST" style="margin-top:5px;"><input type="hidden" name="comic_id" value="<?php echo $item['comic_id']; ?>"><input type="hidden" name="action" value="remove_from_collection"><input type="hidden" name="redirect_url" value="user_dashboard.php#collezione"><button type="submit" class="btn-remove-collection" onclick="return confirm('Rimuovere dalla collezione?');">Rimuovi</button></form>
                                    <?php else: ?>
                                        <form action="collection_actions.php" method="POST" style="display:inline;"><input type="hidden" name="placeholder_id" value="<?php echo $item['placeholder_id']; ?>"><input type="hidden" name="action" value="remove_placeholder_from_collection"><input type="hidden" name="redirect_url" value="user_dashboard.php#collezione"><button type="submit" class="btn-remove-collection" onclick="return confirm('Rimuovere questa segnalazione?');">Rimuovi Segnalazione</button></form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($grand_total_collection_display > 0): ?>
                    <div class="view-all-collection-link"><a href="my_full_collection.php">Vedi tutta la collezione (<?php echo $grand_total_collection_display; ?> elementi) &raquo;</a></div>
                <?php endif; ?>
            <?php else: ?>
                <p>La tua collezione è vuota.</p>
            <?php endif; ?>
             <p style="margin-top:20px;"><a href="<?php echo BASE_URL; ?>index.php" class="btn btn-primary btn-sm">Cerca fumetti da aggiungere alla collezione</a></p>
        </div>

        <div id="tab-content-notifiche" class="user-tab-content">
            <h2>Le Tue Notifiche</h2>
            <?php if (!empty($notifications)): ?>
                <ul class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <li class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                            <a href="<?php echo BASE_URL . htmlspecialchars($notification['link_url']); ?>" class="notification-link">
                                <span class="notification-message"><?php echo $notification['message']; ?></span>
                                <span class="notification-date"><?php echo format_date_italian($notification['created_at'], "d F Y, H:i"); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Non hai nessuna notifica al momento.</p>
            <?php endif; ?>
        </div>

        <?php if ($user_role === 'contributor'): ?>
        <div id="tab-content-proposte" class="user-tab-content">
             <h2>Le Mie Proposte Recenti <small>(Ultime 50)</small></h2>
            <?php if (!empty($user_proposals)): ?>
                <table class="proposals-table">
                    <thead><tr><th>Tipo</th><th>Identificativo Proposta</th><th>Azione</th><th>Data Invio</th><th>Stato</th><th>Note Admin</th></tr></thead>
                    <tbody>
                        <?php foreach ($user_proposals as $prop): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($prop['type_label']); ?></td><td><?php echo htmlspecialchars($prop['identifier']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($prop['action_type'])); ?></td><td><?php echo date("d/m/Y H:i", strtotime($prop['proposed_at'])); ?></td>
                                <td class="status-<?php echo htmlspecialchars($prop['status']); ?>"><?php switch($prop['status']) { case 'pending': echo 'In Attesa'; break; case 'approved': echo 'Approvata'; break; case 'rejected': echo 'Rifiutata'; break; default: echo htmlspecialchars(ucfirst($prop['status'])); } ?></td>
                                <td><?php if(!empty($prop['admin_notes'])): ?><span class="admin-notes-display"><?php echo nl2br(htmlspecialchars($prop['admin_notes'])); ?></span><?php else: echo '<em>Nessuna nota</em>'; endif; ?><?php if($prop['status'] !== 'pending' && $prop['reviewed_at']): ?><small class="admin-notes-display">(Revisionata il: <?php echo date("d/m/Y H:i", strtotime($prop['reviewed_at'])); ?>)</small><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Non hai ancora inviato nessuna proposta di contributo. Puoi iniziare dall'<a href="<?php echo BASE_URL; ?>admin/index.php">Area Contributi</a>.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div id="tab-content-profilo" class="user-tab-content">
            <h2>Modifica Profilo</h2>
            <div class="profile-form-panel"> 
                <form action="profile_actions.php" method="POST"><input type="hidden" name="action" value="update_username"><div class="form-group"><label for="new_username">Nome Utente:</label><input type="text" name="new_username" id="new_username" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" required><small>Min 3 caratteri, solo lettere, numeri, underscore (_).</small></div><button type="submit" class="btn-submit-profile">Salva Nome Utente</button></form>
                <hr style="margin: 25px 0;">
                <form action="profile_actions.php" method="POST"><input type="hidden" name="action" value="update_password"><div class="form-group"><label for="current_password">Password Attuale:</label><input type="password" name="current_password" id="current_password" class="form-control" required></div><div class="form-group"><label for="new_password">Nuova Password:</label><input type="password" name="new_password" id="new_password" class="form-control" required><small>Minimo 6 caratteri.</small></div><div class="form-group"><label for="confirm_new_password">Conferma Nuova Password:</label><input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control" required></div><button type="submit" class="btn-submit-profile">Cambia Password</button></form>
                <hr style="margin: 25px 0;">
                <form action="profile_actions.php" method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="update_avatar"><div class="form-group"><label>Il Mio Avatar:</label><?php if (!empty($user_data['avatar_image_path'])): ?><div class="current-avatar-display"><img src="<?php echo UPLOADS_URL . htmlspecialchars($user_data['avatar_image_path']); ?>" alt="Avatar attuale"><label style="display:block; margin-top:5px;"><input type="checkbox" name="delete_avatar" value="1"> Rimuovi avatar attuale</label></div><?php else: ?><p><small>Nessun avatar impostato.</small></p><?php endif; ?><label for="avatar_image_upload" style="margin-top:10px; display:block;">Carica/Modifica Avatar (max 2MB):</label><input type="file" name="avatar_image" id="avatar_image_upload" class="form-control-file"></div><button type="submit" class="btn-submit-profile">Aggiorna Avatar</button></form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabLinks = document.querySelectorAll('.user-dashboard-tabs .user-tab-link');
    const tabContents = document.querySelectorAll('.user-tab-content-panels .user-tab-content');

    function activateTab(targetId) {
        let foundTarget = false;
        tabLinks.forEach(link => { 
            if (link.dataset.tabTarget === targetId) { 
                link.classList.add('active-user-tab'); 
                foundTarget = true;
            } else { 
                link.classList.remove('active-user-tab'); 
            } 
        });
        tabContents.forEach(content => { 
            if (content.id === 'tab-content-' + targetId) { 
                content.classList.add('active-user-content'); 
            } else { 
                content.classList.remove('active-user-content'); 
            } 
        });
        
        // --- BLOCCO JAVASCRIPT MODIFICATO ---
        // Se il tab attivato è quello delle notifiche, e se ci sono notifiche non lette...
        if (targetId === 'notifiche' && document.querySelector('.notification-item.unread')) {
            // ...chiama lo script PHP per segnarle come lette.
            fetch('<?php echo BASE_URL; ?>actions/mark_notifications_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    // Rimuovi il badge rosso dall'header
                    const badge = document.querySelector('#notification-bell-container .notification-badge');
                    if (badge) {
                        badge.remove();
                    }
                    // Rimuovi lo stile "non letto" dagli elementi nella lista
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                }
            }).catch(error => console.error('Errore durante la marcatura delle notifiche:', error));
        }
        // --- FINE BLOCCO MODIFICATO ---

        return foundTarget;
    }

    tabLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            const targetId = this.dataset.tabTarget;
            if (activateTab(targetId)) { 
                if (history.pushState) { 
                    // Non cambiare l'URL se si clicca sul tab già attivo
                    if ('#' + targetId !== window.location.hash) {
                         history.pushState(null, null, '#' + targetId);
                    }
                } else { 
                    window.location.hash = '#' + targetId; 
                } 
            }
        });
    });

    let initialTab = 'collezione'; 
    if (window.location.hash) {
        const hashTarget = window.location.hash.substring(1);
        let targetTabExists = Array.from(tabLinks).some(link => link.dataset.tabTarget === hashTarget);
        if (targetTabExists) { initialTab = hashTarget; }
    }
    activateTab(initialTab);
});
</script>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>