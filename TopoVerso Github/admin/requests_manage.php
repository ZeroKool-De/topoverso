<?php
// admin/requests_manage.php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php'; // Assicurati che ROOT_PATH sia definito correttamente in config.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Protezione della pagina: solo per amministratori loggati
if (!isset($_SESSION['admin_user_id'])) {
    $_SESSION['admin_action_message'] = "Accesso negato a questa sezione."; // Messaggio più generico
    $_SESSION['admin_action_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}

$message_from_action = $_SESSION['admin_action_message'] ?? null;
$message_type_from_action = $_SESSION['admin_action_message_type'] ?? null;
if ($message_from_action) {
    unset($_SESSION['admin_action_message']);
    unset($_SESSION['admin_action_message_type']);
}


// Gestione delle azioni (cambio stato, eliminazione)
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    $allowed_statuses = ['new', 'viewed', 'fulfilled', 'rejected'];
    $current_filter_for_redirect = $_GET['filter_status'] ?? 'all'; // Mantieni il filtro dopo l'azione

    $redirect_url_with_filter = 'requests_manage.php?filter_status=' . urlencode($current_filter_for_redirect);


    if ($action == 'delete' && $id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM comic_requests WHERE request_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['admin_action_message'] = "Richiesta #{$id} eliminata con successo.";
            $_SESSION['admin_action_message_type'] = 'success';
        } else {
            $_SESSION['admin_action_message'] = "Errore durante l'eliminazione della richiesta #{$id}: " . $stmt->error;
            $_SESSION['admin_action_message_type'] = 'error';
        }
        $stmt->close();
    } elseif (in_array($action, $allowed_statuses) && $id > 0) {
        $stmt = $mysqli->prepare("UPDATE comic_requests SET status = ? WHERE request_id = ?");
        $stmt->bind_param("si", $action, $id);
        if ($stmt->execute()) {
             $_SESSION['admin_action_message'] = "Stato della richiesta #{$id} aggiornato a '{$action}'.";
             $_SESSION['admin_action_message_type'] = 'success';
        } else {
            $_SESSION['admin_action_message'] = "Errore durante l'aggiornamento dello stato per la richiesta #{$id}: " . $stmt->error;
            $_SESSION['admin_action_message_type'] = 'error';
        }
        $stmt->close();
    } else {
        $_SESSION['admin_action_message'] = "Azione non valida o ID mancante.";
        $_SESSION['admin_action_message_type'] = 'error';
    }
    header('Location: ' . $redirect_url_with_filter);
    exit;
}

// Recupera tutte le richieste dal database
$filter_status = $_GET['filter_status'] ?? 'all';
$sql = "SELECT * FROM comic_requests";
$where_clauses = [];
if ($filter_status !== 'all' && in_array($filter_status, ['new', 'viewed', 'fulfilled', 'rejected'])) {
    $where_clauses[] = "status = '" . $mysqli->real_escape_string($filter_status) . "'";
}
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY request_date DESC";
$requests_result = $mysqli->query($sql);

$page_title = "Gestione Richieste Numeri Mancanti";
require_once 'includes/header_admin.php'; // Include l'header dell'admin
?>

<style>
    /* Stili per la barra dei filtri */
    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding: 12px 15px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }

    .filter-bar span {
        font-weight: 600;
        color: #495057;
        margin-right: 5px;
    }

    .filter-bar a {
        text-decoration: none;
        padding: 6px 12px;
        margin: 0 2px;
        border-radius: 4px;
        font-size: 0.875em;
        line-height: 1.5;
        color: #007bff;
        background-color: #fff;
        border: 1px solid #007bff;
        transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out, border-color 0.15s ease-in-out;
    }

    .filter-bar a:hover {
        background-color: #007bff;
        color: #fff;
    }

    .filter-bar a.active {
        background-color: #007bff;
        color: #fff;
        font-weight: 500;
    }

    /* Stili per i badge di stato nella tabella admin (.table è definita in admin_style.css) */
    .table .status-badge {
        padding: .25em .6em;
        font-size: 0.8em;
        font-weight: 600;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: .25rem; /* Bootstrap-like radius */
        color: #fff;
        text-transform: capitalize;
    }

    .table .status-new .status-badge { background-color: #007bff; } /* Blu */
    .table .status-viewed .status-badge { background-color: #ffc107; color: #212529 !important; } /* Giallo, testo scuro */
    .table .status-fulfilled .status-badge { background-color: #28a745; } /* Verde */
    .table .status-rejected .status-badge { background-color: #dc3545; } /* Rosso */
    
    /* Stili per la cella delle azioni */
    .table .actions-cell {
        white-space: nowrap; /* Mantiene i bottoni sulla stessa riga */
        text-align: left; /* o right, o center a seconda della preferenza */
    }

    .table .actions-cell .dropdown {
        position: relative; /* Necessario per il posizionamento assoluto del contenuto dropdown */
        display: inline-block; /* Allinea il bottone con altri elementi inline */
        margin-right: 5px;
    }
    
    /* Il bottone dropdown userà le classi .btn .btn-sm .btn-secondary */
    /* Stili per il contenuto del dropdown */
    .table .actions-cell .dropdown-content {
        display: none; /* Nascosto di default */
        position: absolute; /* Posizionato rispetto al genitore .dropdown */
        background-color: #fff;
        min-width: 160px; /* Larghezza minima */
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.15); /* Ombra più definita */
        z-index: 1001; /* Sopra gli altri elementi della tabella */
        border-radius: .25rem;
        border: 1px solid #ddd; /* Bordo più leggero */
        padding: .5rem 0; /* Padding verticale */
        margin-top: 2px; /* Piccolo stacco dal bottone */
    }

    .table .actions-cell .dropdown-content a {
        color: #343a40; /* Colore testo scuro */
        padding: .375rem 1rem; /* Padding per le voci */
        text-decoration: none;
        display: block;
        font-size: 0.875em; /* Dimensione testo voci */
        clear: both;
        font-weight: 400;
        white-space: nowrap; /* Evita che il testo vada a capo */
    }

    .table .actions-cell .dropdown-content a:hover {
        background-color: #e9ecef; /* Sfondo al hover */
        color: #007bff;
    }

    /* Mostra il dropdown quando si passa sopra al contenitore .dropdown */
    .table .actions-cell .dropdown:hover .dropdown-content {
        display: block;
    }
    
    /* Il bottone "Elimina" userà le classi .btn .btn-sm .btn-danger */
</style>

<div class="container admin-container"> <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <?php if ($message_from_action): ?>
        <div class="message <?php echo htmlspecialchars($message_type_from_action); ?>">
            <?php echo htmlspecialchars($message_from_action); ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <span>Filtra per stato:</span>
        <a href="?filter_status=all" class="<?php echo ($filter_status == 'all') ? 'active' : ''; ?>">Tutti</a>
        <a href="?filter_status=new" class="<?php echo ($filter_status == 'new') ? 'active' : ''; ?>">Nuovi</a>
        <a href="?filter_status=viewed" class="<?php echo ($filter_status == 'viewed') ? 'active' : ''; ?>">Visti</a>
        <a href="?filter_status=fulfilled" class="<?php echo ($filter_status == 'fulfilled') ? 'active' : ''; ?>">Soddisfatti</a>
        <a href="?filter_status=rejected" class="<?php echo ($filter_status == 'rejected') ? 'active' : ''; ?>">Rifiutati</a>
    </div>

    <table class="table"> <thead>
            <tr>
                <th>Numero Richiesto</th>
                <th>Email Visitatore</th>
                <th>Note</th>
                <th>Data Richiesta</th>
                <th>Stato</th>
                <th style="min-width: 200px;">Azioni</th> </tr>
        </thead>
        <tbody>
            <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                <?php while ($request = $requests_result->fetch_assoc()): ?>
                    <tr class="status-<?php echo htmlspecialchars($request['status']); ?>">
                        <td><strong><?php echo htmlspecialchars($request['issue_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($request['visitor_email'] ?: '-'); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($request['notes'] ?: '-')); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($request['request_date'])); ?></td>
                        <td class="status-<?php echo htmlspecialchars($request['status']); ?>"><span class="status-badge"><?php echo htmlspecialchars($request['status']); ?></span></td>
                        <td class="actions-cell">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-info dropbtn">Cambia Stato</button> <div class="dropdown-content">
                                    <a href="?action=new&id=<?php echo $request['request_id']; ?>&filter_status=<?php echo htmlspecialchars($filter_status); ?>">Nuovo</a>
                                    <a href="?action=viewed&id=<?php echo $request['request_id']; ?>&filter_status=<?php echo htmlspecialchars($filter_status); ?>">Visto</a>
                                    <a href="?action=fulfilled&id=<?php echo $request['request_id']; ?>&filter_status=<?php echo htmlspecialchars($filter_status); ?>">Soddisfatto</a>
                                    <a href="?action=rejected&id=<?php echo $request['request_id']; ?>&filter_status=<?php echo htmlspecialchars($filter_status); ?>">Rifiutato</a>
                                </div>
                            </div>
                            <a href="?action=delete&id=<?php echo $request['request_id']; ?>&filter_status=<?php echo htmlspecialchars($filter_status); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa richiesta?');">Elimina</a> </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Nessuna richiesta trovata<?php if ($filter_status !== 'all') echo ' per lo stato "' . htmlspecialchars($filter_status) . '"'; ?>.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
if ($requests_result) {
    $requests_result->free();
}
$mysqli->close();
require_once 'includes/footer_admin.php'; // Include il footer dell'admin
?>