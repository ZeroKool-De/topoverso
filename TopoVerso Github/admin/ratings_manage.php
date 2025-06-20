<?php
// topolinolib/admin/ratings_manage.php

require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// --- CONTROLLO ACCESSO - SOLO ADMIN ---
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin_user_id'])) {
    $_SESSION['admin_action_message'] = "Accesso negato. Devi essere un amministratore per eseguire questa azione.";
    $_SESSION['admin_action_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}
// --- FINE CONTROLLO ACCESSO ---

$page_title = "Gestione Voti Utenti e Visitatori";

// --- GESTIONE AZIONE DI CANCELLAZIONE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_rating') {
    $entity_type = $_POST['entity_type'] ?? null;
    $rating_id = filter_input(INPUT_POST, 'rating_id', FILTER_VALIDATE_INT);
    $table_to_use = '';

    if ($entity_type === 'comic') {
        $table_to_use = 'comic_ratings';
        $id_column = 'rating_id';
    } elseif ($entity_type === 'story') {
        $table_to_use = 'story_ratings';
        $id_column = 'rating_id';
    }

    if ($table_to_use && $rating_id) {
        $stmt = $mysqli->prepare("DELETE FROM {$table_to_use} WHERE {$id_column} = ?");
        if ($stmt) {
            $stmt->bind_param("i", $rating_id);
            if ($stmt->execute()) {
                $_SESSION['admin_action_message'] = "Voto #{$rating_id} eliminato con successo.";
                $_SESSION['admin_action_message_type'] = 'success';
            } else {
                $_SESSION['admin_action_message'] = "Errore durante l'eliminazione del voto.";
                $_SESSION['admin_action_message_type'] = 'error';
            }
            $stmt->close();
        }
    } else {
        $_SESSION['admin_action_message'] = "Azione non valida o dati mancanti.";
        $_SESSION['admin_action_message_type'] = 'error';
    }

    // Redirect per evitare reinvio del form
    header('Location: ratings_manage.php');
    exit;
}

// Messaggi di feedback da sessione
$message = $_SESSION['admin_action_message'] ?? null;
$message_type = $_SESSION['admin_action_message_type'] ?? 'info';
if ($message) {
    unset($_SESSION['admin_action_message']);
    unset($_SESSION['admin_action_message_type']);
}

// --- PAGINAZIONE E RECUPERO DATI ---
$items_per_page = 25;

// Paginazione per voti albi
$page_comics = isset($_GET['page_comics']) ? (int)$_GET['page_comics'] : 1;
$offset_comics = ($page_comics - 1) * $items_per_page;
$total_comic_ratings = $mysqli->query("SELECT COUNT(*) FROM comic_ratings")->fetch_row()[0];
$total_pages_comics = ceil($total_comic_ratings / $items_per_page);

// Paginazione per voti storie
$page_stories = isset($_GET['page_stories']) ? (int)$_GET['page_stories'] : 1;
$offset_stories = ($page_stories - 1) * $items_per_page;
$total_story_ratings = $mysqli->query("SELECT COUNT(*) FROM story_ratings")->fetch_row()[0];
$total_pages_stories = ceil($total_story_ratings / $items_per_page);


// Recupero voti albi
$comic_ratings = [];
$sql_comics = "SELECT cr.*, c.issue_number, c.title 
               FROM comic_ratings cr 
               JOIN comics c ON cr.comic_id = c.comic_id 
               ORDER BY cr.voted_at DESC LIMIT ? OFFSET ?";
$stmt_comics = $mysqli->prepare($sql_comics);
$stmt_comics->bind_param("ii", $items_per_page, $offset_comics);
$stmt_comics->execute();
$result_comics = $stmt_comics->get_result();
if ($result_comics) $comic_ratings = $result_comics->fetch_all(MYSQLI_ASSOC);
$stmt_comics->close();

// Recupero voti storie
$story_ratings = [];
$sql_stories = "SELECT sr.*, s.title as story_title, c.issue_number as comic_issue_number, c.comic_id 
                FROM story_ratings sr 
                JOIN stories s ON sr.story_id = s.story_id 
                JOIN comics c ON s.comic_id = c.comic_id
                ORDER BY sr.voted_at DESC LIMIT ? OFFSET ?";
$stmt_stories = $mysqli->prepare($sql_stories);
$stmt_stories->bind_param("ii", $items_per_page, $offset_stories);
$stmt_stories->execute();
$result_stories = $stmt_stories->get_result();
if ($result_stories) $story_ratings = $result_stories->fetch_all(MYSQLI_ASSOC);
$stmt_stories->close();

require_once 'includes/header_admin.php';
?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <nav class="user-dashboard-tabs">
        <ul>
            <li><a href="#voti-albi" data-tab-target="voti-albi" class="user-tab-link active-user-tab">Voti Albi (<?php echo $total_comic_ratings; ?>)</a></li>
            <li><a href="#voti-storie" data-tab-target="voti-storie" class="user-tab-link">Voti Storie (<?php echo $total_story_ratings; ?>)</a></li>
        </ul>
    </nav>

    <div class="user-tab-content-panels">
        <div id="tab-content-voti-albi" class="user-tab-content active-user-content">
            <h3>Elenco Voti per gli Albi</h3>
            <?php if (!empty($comic_ratings)): ?>
                <table class="table">
                    <thead><tr><th>ID Voto</th><th>Albo</th><th>Voto</th><th>Indirizzo IP</th><th>Data</th><th>Azione</th></tr></thead>
                    <tbody>
                        <?php foreach ($comic_ratings as $rating): ?>
                            <tr>
                                <td><?php echo $rating['rating_id']; ?></td>
                                <td><a href="../comic_detail.php?id=<?php echo $rating['comic_id']; ?>" target="_blank">#<?php echo htmlspecialchars($rating['issue_number']); ?> - <?php echo htmlspecialchars($rating['title']); ?></a></td>
                                <td><span style="color: #fdcc0d; font-size:1.2em;"><?php echo str_repeat('★', $rating['rating']) . str_repeat('☆', 5 - $rating['rating']); ?></span> (<?php echo $rating['rating']; ?>/5)</td>
                                <td><?php echo htmlspecialchars($rating['ip_address']); ?></td>
                                <td><?php echo format_date_italian($rating['voted_at'], 'd/m/Y H:i'); ?></td>
                                <td>
                                    <form action="ratings_manage.php" method="POST" onsubmit="return confirm('Sei sicuro di voler eliminare questo voto?');">
                                        <input type="hidden" name="action" value="delete_rating">
                                        <input type="hidden" name="entity_type" value="comic">
                                        <input type="hidden" name="rating_id" value="<?php echo $rating['rating_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Elimina</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php // (La funzione di paginazione andrebbe creata in functions.php per pulizia, ma per ora la inseriamo qui) ?>
                <div class="pagination-controls">
                    <?php for ($i = 1; $i <= $total_pages_comics; $i++): ?>
                        <a href="?page_comics=<?php echo $i; ?>&page_stories=<?php echo $page_stories; ?>#voti-albi" class="<?php echo ($i == $page_comics) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php else: ?>
                <p>Nessun voto per gli albi trovato.</p>
            <?php endif; ?>
        </div>

        <div id="tab-content-voti-storie" class="user-tab-content">
            <h3>Elenco Voti per le Storie</h3>
             <?php if (!empty($story_ratings)): ?>
                <table class="table">
                    <thead><tr><th>ID Voto</th><th>Storia</th><th>Voto</th><th>Indirizzo IP</th><th>Data</th><th>Azione</th></tr></thead>
                    <tbody>
                        <?php foreach ($story_ratings as $rating): ?>
                            <tr>
                                <td><?php echo $rating['rating_id']; ?></td>
                                <td>
                                    <a href="../comic_detail.php?id=<?php echo $rating['comic_id']; ?>#story-item-<?php echo $rating['story_id']; ?>" target="_blank">
                                        <?php echo htmlspecialchars($rating['story_title']); ?>
                                    </a><br>
                                    <small>(in Albo #<?php echo htmlspecialchars($rating['comic_issue_number']); ?>)</small>
                                </td>
                                <td><span style="color: #fdcc0d; font-size:1.2em;"><?php echo str_repeat('★', $rating['rating']) . str_repeat('☆', 5 - $rating['rating']); ?></span> (<?php echo $rating['rating']; ?>/5)</td>
                                <td><?php echo htmlspecialchars($rating['ip_address']); ?></td>
                                <td><?php echo format_date_italian($rating['voted_at'], 'd/m/Y H:i'); ?></td>
                                <td>
                                    <form action="ratings_manage.php" method="POST" onsubmit="return confirm('Sei sicuro di voler eliminare questo voto?');">
                                        <input type="hidden" name="action" value="delete_rating">
                                        <input type="hidden" name="entity_type" value="story">
                                        <input type="hidden" name="rating_id" value="<?php echo $rating['rating_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Elimina</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                 <div class="pagination-controls">
                    <?php for ($i = 1; $i <= $total_pages_stories; $i++): ?>
                        <a href="?page_comics=<?php echo $page_comics; ?>&page_stories=<?php echo $i; ?>#voti-storie" class="<?php echo ($i == $page_stories) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php else: ?>
                <p>Nessun voto per le storie trovato.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Script per la gestione dei tab (simile a user_dashboard.php)
document.addEventListener('DOMContentLoaded', function() {
    const tabLinks = document.querySelectorAll('.user-dashboard-tabs .user-tab-link');
    const tabContents = document.querySelectorAll('.user-tab-content-panels .user-tab-content');

    function activateTab(targetId) {
        tabLinks.forEach(link => {
            if (link.dataset.tabTarget === targetId) {
                link.classList.add('active-user-tab');
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
    }

    tabLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            const targetId = this.dataset.tabTarget;
            activateTab(targetId);
            if (history.pushState) {
                const url = new URL(window.location);
                url.hash = targetId;
                history.pushState({}, '', url);
            } else {
                window.location.hash = targetId;
            }
        });
    });

    let initialTab = 'voti-albi';
    if (window.location.hash) {
        const hashTarget = window.location.hash.substring(1);
        if (document.getElementById('tab-content-' + hashTarget)) {
            initialTab = hashTarget;
        }
    }
    activateTab(initialTab);
});
</script>

<?php
$mysqli->close();
require_once 'includes/footer_admin.php';
?>