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
// FINE CONTROLLO ACCESSO

$page_title = "Gestione Proposte Contributi";
require_once ROOT_PATH . 'admin/includes/header_admin.php';

$message = $_SESSION['admin_proposal_message'] ?? null;
$message_type = $_SESSION['admin_proposal_message_type'] ?? null;
if ($message) {
    unset($_SESSION['admin_proposal_message']);
    unset($_SESSION['admin_proposal_message_type']);
}

$pending_proposals = [];

// Recupera proposte per Fumetti (Comics)
$sql_pending_comics = "
    SELECT pc.*, u.username AS proposer_username 
    FROM pending_comics pc
    JOIN users u ON pc.proposer_user_id = u.user_id
    WHERE pc.status = 'pending'
    ORDER BY pc.proposed_at ASC
";
$result_pc = $mysqli->query($sql_pending_comics);
if ($result_pc) {
    while ($row = $result_pc->fetch_assoc()) {
        $row['proposal_type_label'] = 'Fumetto (Albo)';
        $row['proposal_identifier'] = $row['issue_number'] ?: '(Nuovo Albo)';
        if ($row['action_type'] === 'edit') {
            $row['proposal_identifier'] = 'Modifica Albo #' . $row['comic_id_original'];
        }
        $pending_proposals[] = $row;
    }
    $result_pc->free();
}

// Recupera proposte per Storie
$sql_pending_stories = "
    SELECT ps.*, u.username AS proposer_username, c.issue_number AS comic_issue_number
    FROM pending_stories ps
    JOIN users u ON ps.proposer_user_id = u.user_id
    JOIN comics c ON ps.comic_id_context = c.comic_id
    WHERE ps.status = 'pending'
    ORDER BY ps.proposed_at ASC
";
$result_ps = $mysqli->query($sql_pending_stories);
if ($result_ps) {
    while ($row = $result_ps->fetch_assoc()) {
        $row['proposal_type_label'] = 'Storia';
        $row['proposal_identifier'] = $row['title_proposal'] ?: '(Nuova Storia)';
        if ($row['action_type'] === 'edit') {
            $row['proposal_identifier'] = 'Modifica Storia ID: ' . $row['story_id_original'];
        }
        $row['proposal_context'] = 'per Albo #' . $row['comic_issue_number'];
        $pending_proposals[] = $row;
    }
    $result_ps->free();
}

// Recupera proposte per Persone (Autori)
$sql_pending_persons = "
    SELECT pp.*, u.username AS proposer_username
    FROM pending_persons pp
    JOIN users u ON pp.proposer_user_id = u.user_id
    WHERE pp.status = 'pending'
    ORDER BY pp.proposed_at ASC
";
$result_pp = $mysqli->query($sql_pending_persons);
if ($result_pp) {
    while ($row = $result_pp->fetch_assoc()) {
        $row['proposal_type_label'] = 'Persona (Autore/Artista)';
        $row['proposal_identifier'] = $row['name_proposal'] ?: '(Nuova Persona)';
        if ($row['action_type'] === 'edit') {
            $row['proposal_identifier'] = 'Modifica Persona ID: ' . $row['person_id_original'];
        }
        $pending_proposals[] = $row;
    }
    $result_pp->free();
}

// Recupera proposte per Personaggi
$sql_pending_characters = "
    SELECT pcg.*, u.username AS proposer_username
    FROM pending_characters pcg
    JOIN users u ON pcg.proposer_user_id = u.user_id
    WHERE pcg.status = 'pending'
    ORDER BY pcg.proposed_at ASC
";
$result_pchar = $mysqli->query($sql_pending_characters);
if ($result_pchar) {
    while ($row = $result_pchar->fetch_assoc()) {
        $row['proposal_type_label'] = 'Personaggio';
        $row['proposal_identifier'] = $row['name_proposal'] ?: '(Nuovo Personaggio)';
        if ($row['action_type'] === 'edit') {
            $row['proposal_identifier'] = 'Modifica Personaggio ID: ' . $row['character_id_original'];
        }
        $pending_proposals[] = $row;
    }
    $result_pchar->free();
}

// Recupera proposte per Serie di Storie
$sql_pending_series = "
    SELECT pss.*, u.username AS proposer_username
    FROM pending_story_series pss
    JOIN users u ON pss.proposer_user_id = u.user_id
    WHERE pss.status = 'pending'
    ORDER BY pss.proposed_at ASC
";
$result_pseries = $mysqli->query($sql_pending_series);
if ($result_pseries) {
    while ($row = $result_pseries->fetch_assoc()) {
        $row['proposal_type_label'] = 'Serie di Storie';
        $row['proposal_identifier'] = $row['title_proposal'] ?: '(Nuova Serie)';
        if ($row['action_type'] === 'edit') {
            $row['proposal_identifier'] = 'Modifica Serie ID: ' . $row['series_id_original'];
        }
        $pending_proposals[] = $row;
    }
    $result_pseries->free();
}

// Ordina tutte le proposte per data di proposta (opzionale, se non già fatto per tipo)
usort($pending_proposals, function($a, $b) {
    return strtotime($a['proposed_at']) - strtotime($b['proposed_at']);
});

?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pending_proposals)): ?>
        <p>Ci sono <?php echo count($pending_proposals); ?> proposte in attesa di revisione.</p>
        <table class="table">
            <thead>
                <tr>
                    <th>ID Proposta</th>
                    <th>Tipo</th>
                    <th>Elemento Proposto / Azione</th>
                    <th>Contesto</th>
                    <th>Proposto Da</th>
                    <th>Data Proposta</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_proposals as $proposal): ?>
                    <?php
                        // Determina l'ID univoco della proposta in base al tipo
                        $unique_pending_id = 0;
                        $type_prefix = '';
                        if (isset($proposal['pending_comic_id'])) { $unique_pending_id = $proposal['pending_comic_id']; $type_prefix = 'comic'; }
                        elseif (isset($proposal['pending_story_id'])) { $unique_pending_id = $proposal['pending_story_id']; $type_prefix = 'story'; }
                        elseif (isset($proposal['pending_person_id'])) { $unique_pending_id = $proposal['pending_person_id']; $type_prefix = 'person'; }
                        elseif (isset($proposal['pending_character_id'])) { $unique_pending_id = $proposal['pending_character_id']; $type_prefix = 'character'; }
                        elseif (isset($proposal['pending_series_id'])) { $unique_pending_id = $proposal['pending_series_id']; $type_prefix = 'series'; }
                    ?>
                    <tr>
                        <td><?php echo $type_prefix . '_' . $unique_pending_id; ?></td>
                        <td><?php echo htmlspecialchars($proposal['proposal_type_label']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($proposal['proposal_identifier']); ?>
                            (<?php echo htmlspecialchars(ucfirst($proposal['action_type'])); ?>)
                        </td>
                        <td><?php echo htmlspecialchars($proposal['proposal_context'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($proposal['proposer_username']); ?> (ID: <?php echo $proposal['proposer_user_id']; ?>)</td>
                        <td><?php echo date("d/m/Y H:i", strtotime($proposal['proposed_at'])); ?></td>
                        <td style="white-space: nowrap;">
                            <a href="proposal_review.php?type=<?php echo $type_prefix; ?>&id=<?php echo $unique_pending_id; ?>" class="btn btn-sm btn-info">Revisiona</a>
                            
                            <?php // Azioni rapide di approva/rifiuta (semplificate, senza conferma per ora) ?>
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nessuna proposta di contributo in attesa di revisione.</p>
    <?php endif; ?>
</div>

<?php
$mysqli->close();
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
?>