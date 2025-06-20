<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Richiedi un Numero Mancante";
$message = '';
$message_type = '';
$prefill_issue_number = ''; // Variabile per precompilare il numero
$prefill_notes = '';        // Variabile per precompilare le note

// Verifica se è stato passato un numero da precompilare da search.php o jump_to_issue.php
if (isset($_GET['issue_number_request'])) {
    $prefill_issue_number = htmlspecialchars(trim($_GET['issue_number_request']));
}

// Verifica se è stata passata una data da precompilare da mio_numero.php
if (isset($_GET['prefill_date'])) {
    $date_from_mio_numero = htmlspecialchars(trim($_GET['prefill_date']));
    // Aggiungiamo la data nelle note, senza sovrascrivere il numero albo se già presente
    $prefill_notes = "Richiesta proveniente dalla funzionalità 'Il Tuo Numero' per la data: " . format_date_italian($date_from_mio_numero, "d/m/Y") . ". ";
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $issue_number = trim($_POST['issue_number'] ?? '');
    $visitor_email = trim($_POST['visitor_email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($issue_number)) {
        $message = "Per favore, specifica il numero dell'albo che desideri richiedere.";
        $message_type = 'error';
    } elseif (!empty($visitor_email) && !filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Per favore, inserisci un indirizzo email valido.";
        $message_type = 'error';
    } else {
        $sql = "INSERT INTO comic_requests (issue_number, visitor_email, notes) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sss", $issue_number, $visitor_email, $notes);
            if ($stmt->execute()) {
                $message = "Grazie! La tua richiesta per Topolino #" . htmlspecialchars($issue_number) . " è stata inviata con successo. Faremo del nostro meglio per aggiungerlo al catalogo.";
                $message_type = 'success';
                // Resetta i campi (inclusi quelli precompilati) dopo l'invio
                $prefill_issue_number = '';
                $prefill_notes = '';
                $_POST['notes'] = ''; // Svuota anche il POST per evitare che si ripresenti al refresh
            } else {
                $message = "Si è verificato un errore durante l'invio della richiesta. Riprova più tardi.";
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Errore di preparazione della richiesta. Contatta l'amministratore.";
            $message_type = 'error';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="page-description">
        Non hai trovato un numero di Topolino che stavi cercando? Usa questo modulo per richiederlo!
        Le richieste ci aiutano a dare la priorità ai prossimi inserimenti nel catalogo.
    </p>

    <div class="request-form-container">
        <?php if ($message): ?>
            <div class="form-message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form action="richieste.php" method="POST" class="request-form">
            <div class="form-group">
                <label for="issue_number">Numero di Topolino Richiesto</label>
                <input type="text" id="issue_number" name="issue_number" value="<?php echo htmlspecialchars($prefill_issue_number); ?>" placeholder="Es. 1234, 1500bis, Speciale Natale..." required>
                <small>Specifica il numero o il nome dell'albo che cerchi.</small>
            </div>
            <div class="form-group">
                <label for="visitor_email">La tua Email (Opzionale)</label>
                <input type="email" id="visitor_email" name="visitor_email" placeholder="nome@esempio.com">
                <small>Se vuoi, possiamo avvisarti quando il numero sarà disponibile.</small>
            </div>
            <div class="form-group">
                <label for="notes">Note Aggiuntive (Opzionale)</label>
                <textarea id="notes" name="notes" rows="4" placeholder="Es. con gadget, copertina variant, edizione specifica, ecc."><?php echo htmlspecialchars($_POST['notes'] ?? $prefill_notes); ?></textarea>
            </div>
            <div class="form-group">
                <button type="submit">Invia Richiesta</button>
            </div>
        </form>
    </div>
</div>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>