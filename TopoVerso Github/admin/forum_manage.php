<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// Controllo sessione admin
if (!isset($_SESSION['admin_user_id'])) {
    header("Location: login.php");
    exit;
}

$page_title = "Gestione Forum";
$message = '';
$message_type = '';

// --- LOGICA AZIONI ---
$action = $_GET['action'] ?? 'list';
$editing_section_id = null;
$section_data = ['name' => '', 'description' => ''];

// Gestione approvazione/rifiuto commenti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_action'])) {
    $post_id = (int)($_POST['post_id'] ?? 0);
    if ($post_id > 0) {
        if ($_POST['comment_action'] === 'approve') {
            $stmt = $mysqli->prepare("UPDATE forum_posts SET status = 'approved' WHERE id = ?");
            $stmt->bind_param('i', $post_id);
            $stmt->execute();
            $message = "Commento approvato."; $message_type = 'success';
        } elseif ($_POST['comment_action'] === 'reject') {
            $stmt = $mysqli->prepare("DELETE FROM forum_posts WHERE id = ?");
            $stmt->bind_param('i', $post_id);
            $stmt->execute();
            $message = "Commento rifiutato ed eliminato."; $message_type = 'success';
        }
    }
}

// Gestione sezioni (Aggiungi/Modifica)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section_action'])) {
    $section_id = (int)($_POST['section_id'] ?? 0);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if (empty($name)) {
        $message = "Il nome della sezione è obbligatorio.";
        $message_type = 'error';
    } else {
        if ($_POST['section_action'] === 'add') {
            $stmt = $mysqli->prepare("INSERT INTO forum_sections (name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $name, $description);
            if ($stmt->execute()) {
                $message = "Sezione aggiunta con successo."; $message_type = 'success';
            } else {
                $message = "Errore: " . $stmt->error; $message_type = 'error';
            }
        } elseif ($_POST['section_action'] === 'edit' && $section_id > 0) {
            $stmt = $mysqli->prepare("UPDATE forum_sections SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param('ssi', $name, $description, $section_id);
             if ($stmt->execute()) {
                $message = "Sezione modificata con successo."; $message_type = 'success';
            } else {
                $message = "Errore: " . $stmt->error; $message_type = 'error';
            }
        }
        $stmt->close();
    }
}

// Gestione sezioni (Elimina)
if ($action === 'delete_section' && isset($_GET['id'])) {
    $section_id = (int)$_GET['id'];
    if ($section_id === 1) { // Protezione per non cancellare la sezione di default
        $message = "Impossibile eliminare la sezione predefinita per le discussioni degli albi.";
        $message_type = 'error';
    } else {
        // La foreign key con ON DELETE CASCADE cancellerà anche tutti i thread e i post associati
        $stmt = $mysqli->prepare("DELETE FROM forum_sections WHERE id = ?");
        $stmt->bind_param('i', $section_id);
        if ($stmt->execute()) {
            $message = "Sezione (e tutte le sue discussioni) eliminata con successo.";
            $message_type = 'success';
        } else {
            $message = "Errore: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
    $action = 'list'; // Torna alla lista
}

// Pre-carica dati per il form di modifica
if ($action === 'edit_section' && isset($_GET['id'])) {
    $editing_section_id = (int)$_GET['id'];
    $stmt = $mysqli->prepare("SELECT name, description FROM forum_sections WHERE id = ?");
    $stmt->bind_param('i', $editing_section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $section_data = $result->fetch_assoc();
    $stmt->close();
}


// --- RECUPERO DATI PER VISUALIZZAZIONE ---
// Recupera commenti in attesa
$pending_posts = $mysqli->query("SELECT p.id, p.content, p.author_name, p.created_at, u.username, t.title as thread_title, t.comic_id, t.story_id
                                 FROM forum_posts p
                                 LEFT JOIN users u ON p.user_id = u.user_id
                                 JOIN forum_threads t ON p.thread_id = t.id
                                 WHERE p.status = 'pending' ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Recupera tutte le sezioni
$sections = $mysqli->query("SELECT * FROM forum_sections ORDER BY name")->fetch_all(MYSQLI_ASSOC);


require_once 'includes/header_admin.php';
?>

<div class="container admin-container">
    <h2><?php echo $page_title; ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <h3>Commenti in attesa di approvazione (<?php echo count($pending_posts); ?>)</h3>
    <div class="table-responsive">
        <?php if (!empty($pending_posts)): ?>
            <table class="table table-bordered">
                <thead><tr><th>Autore</th><th>Commento</th><th>Discussione di Riferimento</th><th>Azioni</th></tr></thead>
                <tbody>
                    <?php foreach ($pending_posts as $post): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($post['username'] ?? $post['author_name']); ?></td>
                            <td style="max-width: 400px;"><?php echo nl2br(htmlspecialchars($post['content'])); ?></td>
                            <td>
                                <a href="../thread.php?id=<?php echo $post['id']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($post['thread_title']); ?>
                                </a>
                            </td>
                            <td style="white-space:nowrap;">
                                <form action="forum_manage.php" method="POST" class="d-inline">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" name="comment_action" value="approve" class="btn btn-success btn-sm">Approva</button>
                                </form>
                                <form action="forum_manage.php" method="POST" class="d-inline">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" name="comment_action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Sei sicuro di voler eliminare questo commento?');">Rifiuta</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Non ci sono commenti in attesa di approvazione.</p>
        <?php endif; ?>
    </div>
    
    <hr class="my-4">

    <h3>Gestione Sezioni Forum</h3>
    <div class="row">
        <div class="col-md-8">
            <h4>Sezioni Esistenti</h4>
            <table class="table">
                <thead><tr><th>Nome</th><th>Descrizione</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($sections as $section): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($section['name']); ?></td>
                        <td><?php echo htmlspecialchars($section['description']); ?></td>
                        <td style="white-space:nowrap;">
                            <a href="?action=edit_section&id=<?php echo $section['id']; ?>" class="btn btn-warning btn-sm">Modifica</a>
                            <?php if ($section['id'] != 1): // Protezione sezione default ?>
                            <a href="?action=delete_section&id=<?php echo $section['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('ATTENZIONE: Eliminando questa sezione, verranno eliminate anche TUTTE le discussioni e i commenti al suo interno. Sei sicuro?');">Elimina</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="col-md-4">
            <h4><?php echo $editing_section_id ? 'Modifica Sezione' : 'Aggiungi Nuova Sezione'; ?></h4>
            <form action="forum_manage.php" method="POST">
                <input type="hidden" name="section_action" value="<?php echo $editing_section_id ? 'edit' : 'add'; ?>">
                <?php if ($editing_section_id): ?>
                    <input type="hidden" name="section_id" value="<?php echo $editing_section_id; ?>">
                <?php endif; ?>
                <div class="form-group mb-3">
                    <label for="name">Nome Sezione</label>
                    <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($section_data['name']); ?>" required>
                </div>
                <div class="form-group mb-3">
                    <label for="description">Descrizione</label>
                    <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($section_data['description']); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo $editing_section_id ? 'Salva Modifiche' : 'Aggiungi Sezione'; ?></button>
                <?php if ($editing_section_id): ?>
                    <a href="forum_manage.php" class="btn btn-secondary">Annulla</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<?php
$mysqli->close();
require_once 'includes/footer_admin.php';
?>