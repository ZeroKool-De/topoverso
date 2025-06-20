<?php
require_once 'config/config.php';
// db_connect.php e functions.php sono ora inclusi da config.php

$page_title = "Info & Contatti";

// Recupero dei contenuti per le schede dal database
$content_progetto = get_site_setting('about_page_content', $mysqli, 'Contenuto per "Il Progetto" non ancora impostato. Puoi aggiungerlo da Impostazioni Sito nel pannello admin.');
$content_chisiamo = get_site_setting('about_page_who_am_i', $mysqli, 'Contenuto per "Chi Sono" non ancora impostato. Puoi aggiungerlo da Impostazioni Sito nel pannello admin.');
$content_guida = get_site_setting('about_page_how_to_use', $mysqli, 'Contenuto per la "Guida all\'Uso" non ancora impostato. Puoi aggiungerlo da Impostazioni Sito nel pannello admin.');


// Logica per il form di contatto
$contact_message_display = '';
$contact_message_type_display = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_contact_message'])) {
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_subject = trim($_POST['contact_subject'] ?? 'Messaggio dal sito TopoVerso');
    $contact_text = trim($_POST['contact_text'] ?? '');

    $errors_contact = [];
    if (empty($contact_name)) {
        $errors_contact[] = "Il nome è obbligatorio.";
    }
    if (empty($contact_email)) {
        $errors_contact[] = "L'email è obbligatoria.";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors_contact[] = "L'indirizzo email non è valido.";
    }
    if (empty($contact_text)) {
        $errors_contact[] = "Il testo del messaggio è obbligatorio.";
    } elseif (strlen($contact_text) < 10) {
        $errors_contact[] = "Il messaggio sembra troppo corto (minimo 10 caratteri).";
    }

    if (empty($errors_contact)) {
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            $stmt_contact = $mysqli->prepare("INSERT INTO contact_messages (contact_name, contact_email, contact_subject, message_text) VALUES (?, ?, ?, ?)");
            if ($stmt_contact) {
                $stmt_contact->bind_param("ssss", $contact_name, $contact_email, $contact_subject, $contact_text);
                if ($stmt_contact->execute()) {
                    $contact_message_display = "Grazie! Il tuo messaggio è stato inviato con successo e sarà letto presto.";
                    $contact_message_type_display = 'success';
                    $_POST = array(); // Svuota i campi del form dopo l'invio
                } else {
                    $contact_message_display = "Siamo spiacenti, c'è stato un problema nel salvare il tuo messaggio: " . htmlspecialchars($stmt_contact->error);
                    $contact_message_type_display = 'error';
                    error_log("Contact form DB error: " . $stmt_contact->error);
                }
                $stmt_contact->close();
            } else {
                $contact_message_display = "Errore di preparazione del salvataggio messaggio. Contatta l'amministratore.";
                $contact_message_type_display = 'error';
                error_log("Contact form DB prepare error: " . $mysqli->error);
            }
        } else {
             $contact_message_display = "Errore di connessione al database. Impossibile salvare il messaggio.";
             $contact_message_type_display = 'error';
             error_log("Contact form DB connection error: mysqli object not available.");
        }
    } else {
        $contact_message_display = "Errore nell'invio del messaggio:<br><ul>";
        foreach ($errors_contact as $err) {
            $contact_message_display .= "<li>" . htmlspecialchars($err) . "</li>";
        }
        $contact_message_display .= "</ul>";
        $contact_message_type_display = 'error';
    }
}

require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="tabs-container">
        <div class="tab-nav">
            <button class="tab-link active" onclick="openTab(event, 'IlProgetto')">Il Progetto</button>
            <button class="tab-link" onclick="openTab(event, 'ChiSono')">Chi Sono</button>
            <button class="tab-link" onclick="openTab(event, 'GuidaUso')">Guida all'Uso</button>
            <button class="tab-link" onclick="openTab(event, 'Contatti')">Contatti</button>
        </div>

        <div id="IlProgetto" class="tab-pane active">
            <div class="about-tab-content">
                <h2>Il Progetto "TopoVerso"</h2>
                <?php echo $content_progetto; // MODIFICA: Mostra l'HTML direttamente ?>
            </div>
        </div>

        <div id="ChiSono" class="tab-pane">
            <div class="about-tab-content">
                <h2>Chi Sono</h2>
                <?php echo $content_chisiamo; // MODIFICA: Mostra l'HTML direttamente ?>
            </div>
        </div>
        
        <div id="GuidaUso" class="tab-pane">
            <div class="about-tab-content">
                <h2>Guida all'Uso del Sito</h2>
                <?php echo $content_guida; // MODIFICA: Mostra l'HTML direttamente ?>
            </div>
        </div>

        <div id="Contatti" class="tab-pane">
            <div class="about-tab-content">
                <h2>Contattaci</h2>
                <p>
                    Hai domande, suggerimenti, o vuoi semplicemente metterti in contatto con noi?
                    Compila il modulo sottostante.
                </p>

                <?php if ($contact_message_display): ?>
                    <div class="message <?php echo $contact_message_type_display; ?>" style="margin-bottom: 20px;">
                        <?php echo $contact_message_display; // Il messaggio di stato può contenere HTML (<ul><li>), quindi non va escapato qui ?>
                    </div>
                <?php endif; ?>

                <form action="info_contatti.php#Contatti" method="POST" class="request-form">
                    <input type="hidden" name="send_contact_message" value="1">
                    <div class="form-group">
                        <label for="contact_name">Il Tuo Nome:</label>
                        <input type="text" id="contact_name" name="contact_name" value="<?php echo htmlspecialchars($_POST['contact_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_email">La Tua Email:</label>
                        <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_subject">Oggetto:</label>
                        <input type="text" id="contact_subject" name="contact_subject" value="<?php echo htmlspecialchars($_POST['contact_subject'] ?? 'Messaggio da TopoVerso'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contact_text">Messaggio:</label>
                        <textarea id="contact_text" name="contact_text" rows="6" required minlength="10"><?php echo htmlspecialchars($_POST['contact_text'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Invia Messaggio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Puoi spostare questo stile in style.css se preferisci */
.about-tab-content {
    padding: 25px 30px;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 6px 6px;
    animation: fadeIn 0.5s;
}
.about-tab-content h2 {
    margin-top: 0;
    font-size: 1.8em;
    color: #343a40;
    padding-bottom: 10px;
    margin-bottom: 20px;
    border-bottom: 1px solid #e0e0e0;
}
.about-tab-content p, .about-tab-content div, .about-tab-content ul, .about-tab-content ol { /* Stili più generici */
    line-height: 1.7;
    font-size: 1.05em;
}
</style>

<script>
// Script per la gestione dei tab
function openTab(evt, tabName) {
    let i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-pane");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
        tabcontent[i].classList.remove("active");
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    
    const targetPane = document.getElementById(tabName);
    if (targetPane) {
        targetPane.style.display = "block";
        targetPane.classList.add("active");
    }

    if (evt && evt.currentTarget) {
      evt.currentTarget.classList.add("active");
    }
}

document.addEventListener('DOMContentLoaded', function() {
    function activateTabFromHash() {
        let initialTab = 'IlProgetto'; // Default tab
        if (window.location.hash) {
            let hashTarget = window.location.hash.substring(1);
            if (document.getElementById(hashTarget)) {
                initialTab = hashTarget;
            }
        }
        let initialButton = document.querySelector('.tab-link[onclick*="' + initialTab + '"]');
        openTab({currentTarget: initialButton}, initialTab);
    }

    // Attiva la scheda corretta al caricamento della pagina
    activateTabFromHash();

    // Aggiorna l'URL quando si clicca su un tab
    document.querySelectorAll('.tab-link').forEach(button => {
        button.addEventListener('click', function(event) {
            let tabName = this.getAttribute('onclick').match(/'([^']+)'/)[1];
            if(history.pushState) {
                // Evita di aggiungere una nuova voce alla cronologia se l'hash è già quello corretto
                if ('#' + tabName !== window.location.hash) {
                    history.pushState(null, null, '#' + tabName);
                }
            } else {
                location.hash = '#' + tabName;
            }
        });
    });

    // Gestisce i pulsanti avanti/indietro del browser
    window.addEventListener('popstate', activateTabFromHash);
});
</script>

<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
require_once 'includes/footer.php';
?>