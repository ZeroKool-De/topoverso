<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php'; // <-- FILE MANCANTE AGGIUNTO QUI
require_once 'includes/functions.php';

// Sicurezza: Solo gli amministratori possono creare discussioni direttamente
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_user_id'])) {
    // Reindirizza al forum se non è un admin loggato
    header("Location: forum.php?message=Accesso non autorizzato.");
    exit;
}

$page_title = "Crea Nuova Discussione";

// Recupera le sezioni del forum per il menu a tendina
$sections = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $sections_result = $mysqli->query("SELECT id, name FROM forum_sections ORDER BY name ASC");
    if ($sections_result) {
        while ($section = $sections_result->fetch_assoc()) {
            $sections[] = $section;
        }
        $sections_result->free();
    }
}

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <p><a href="forum.php" class="btn btn-sm btn-secondary">&laquo; Torna al Forum</a></p>

    <?php if (isset($_SESSION['feedback'])): ?>
        <div class="message <?php echo $_SESSION['feedback']['type']; ?>">
            <?php echo $_SESSION['feedback']['message']; ?>
        </div>
        <?php unset($_SESSION['feedback']); ?>
    <?php endif; ?>

    <form action="actions/thread_actions.php" method="POST" class="user-form" style="max-width: 700px; margin: 20px auto;">
        <div class="form-group">
            <label for="section_id">Sezione del Forum:</label>
            <select name="section_id" id="section_id" class="form-control" required>
                <option value="">-- Seleziona una sezione --</option>
                <?php foreach ($sections as $section): ?>
                    <?php if ($section['id'] != 1): // Esclude la sezione "Discussioni Albi" che è automatica ?>
                    <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['name']); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
             <small>Non puoi creare manualmente discussioni nella sezione "Discussioni Albi", che è gestita in automatico.</small>
        </div>
        <div class="form-group">
            <label for="title">Titolo della Discussione:</label>
            <input type="text" name="title" id="title" class="form-control" required minlength="5" placeholder="Un titolo chiaro e descrittivo...">
        </div>
        <div class="form-group">
            <label for="content">Messaggio di apertura:</label>
            <textarea name="content" id="content" rows="10" class="form-control" required minlength="10" placeholder="Scrivi qui il primo messaggio della discussione..."></textarea>
            <small>Questo sarà il primo post della discussione.</small>
        </div>
        <div class="form-group">
            <button type="submit" name="create_thread" class="btn btn-primary">Crea Discussione</button>
        </div>
    </form>
</div>

<?php
require_once 'includes/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close(); // Chiudi la connessione
}
?>