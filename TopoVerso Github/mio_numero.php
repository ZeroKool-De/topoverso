<?php
require_once 'config/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Il Tuo Numero di Topolino";
$comic_result = null;
$submitted_date = null;
$error_message = ''; // Inizializza per evitare warning se non settato

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['birthdate'])) {
    $submitted_date = $_POST['birthdate'];

    // Validazione base della data
    $date_parts = explode('-', $submitted_date);
    if (count($date_parts) == 3 && checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        
        $sql = "SELECT *, ABS(DATEDIFF(publication_date, ?)) AS date_difference
                FROM comics
                WHERE publication_date IS NOT NULL
                ORDER BY date_difference ASC, publication_date ASC
                LIMIT 1";

        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $submitted_date);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $comic_result = $result->fetch_assoc();
                $result->free();
            }
            $stmt->close();
        } else {
            // Questo errore è più per lo sviluppo, all'utente finale si potrebbe mostrare un messaggio generico
            $error_message = "Errore nella preparazione della query: " . $mysqli->error; 
        }
    } else {
        $error_message = "Per favore, inserisci una data valida (AAAA-MM-GG).";
    }
}

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="page-description">
        Sei curioso di sapere quale numero di Topolino era in edicola il giorno in cui sei nato o in una data speciale?
        Inserisci la data e scopri l'albo più vicino pubblicato!
    </p>

    <div class="birthdate-form-container">
        <form action="mio_numero.php" method="POST" class="birthdate-form">
            <label for="birthdate">Inserisci la data (AAAA-MM-GG):</label>
            <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($submitted_date ?? ''); ?>" required>
            <button type="submit">Trova il Topolino!</button>
        </form>
        <?php if (!empty($error_message)): ?>
            <div class="message error" style="text-align: center; max-width: 500px; margin: 15px auto 0 auto;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($submitted_date && empty($error_message)): // Mostra i risultati solo se è stata inviata una data valida ?>
        <div class="birthdate-result-container">
            <?php if ($comic_result): ?>
                <h2>Il Topolino più vicino alla tua data!</h2>
                <div class="birthdate-result-card">
                    <div class="result-card-image">
                        <a href="<?php echo BASE_URL; ?>comic_detail.php?id=<?php echo $comic_result['comic_id']; ?>">
                            <?php if ($comic_result['cover_image']): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($comic_result['cover_image']); ?>" alt="Copertina di Topolino #<?php echo htmlspecialchars($comic_result['issue_number']); ?>">
                            <?php else: ?>
                                <?php echo generate_comic_placeholder_cover(htmlspecialchars($comic_result['issue_number']), 250, 350, 'result-placeholder'); ?>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="result-card-info">
                        <h3>Topolino N. <?php echo htmlspecialchars($comic_result['issue_number']); ?></h3>
                        <?php if ($comic_result['title']): ?>
                            <p class="comic-title">"<?php echo htmlspecialchars($comic_result['title']); ?>"</p>
                        <?php endif; ?>
                        <p class="publication-date">
                            Data di pubblicazione: <strong><?php echo format_date_italian($comic_result['publication_date'], "d MMMM YYYY"); ?></strong>
                        </p>
                        <p class="date-difference">
                            <?php
                                $user_date_obj = date_create($submitted_date);
                                $comic_date_obj = date_create($comic_result['publication_date']);
                                $diff = date_diff($user_date_obj, $comic_date_obj);
                                $diff_days = (int)$diff->format('%a'); // Differenza assoluta in giorni

                                if ($diff_days == 0) {
                                    echo "Questo numero è uscito esattamente il " . format_date_italian($submitted_date, "d MMMM YYYY") . "!";
                                } else {
                                    $giorni_str = ($diff_days == 1) ? "giorno" : "giorni";
                                    $congiunzione = ($user_date_obj > $comic_date_obj ? 'prima della tua data' : 'dopo la tua data');
                                    echo "Uscito " . $diff_days . " " . $giorni_str . " " . $congiunzione . ".";
                                }
                            ?>
                        </p>
                        <a href="<?php echo BASE_URL; ?>comic_detail.php?id=<?php echo $comic_result['comic_id']; ?>" class="btn-details">Vai ai dettagli dell'albo</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-result-message">
                    <h2>Nessun Risultato Trovato</h2>
                    <p>Ci dispiace, ma non abbiamo trovato un numero di Topolino pubblicato esattamente nella data inserita (<?php echo format_date_italian($submitted_date, "d MMMM YYYY"); ?>) o nelle immediate vicinanze. È possibile che il catalogo per quell'anno debba ancora essere completato o che non siano uscite pubblicazioni in quella data specifica.</p>
                </div>
            <?php endif; ?>

            <div class="request-prompt-section">
                <h4>Non hai trovato il numero che cercavi o ne ricordi uno specifico?</h4>
                <p>
                    Il nostro catalogo è in continua crescita! Se il numero di Topolino che ti aspettavi non è tra i risultati,
                    o se vuoi segnalarci un albo specifico che credi manchi, puoi inviarci una richiesta.
                    Aiutaci a rendere il catalogo più completo!
                </p>
                <a href="<?php echo BASE_URL; ?>richieste.php<?php if ($comic_result === null && !empty($submitted_date)) { echo '?prefill_date=' . htmlspecialchars($submitted_date); } ?>" class="btn-request-specific">
                    Richiedi un Numero Specifico &raquo;
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$mysqli->close();
require_once 'includes/footer.php';
?>