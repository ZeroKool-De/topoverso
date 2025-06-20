<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// CONTROLLO ACCESSO - SOLO VERI ADMIN
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_user_id'])) {
    $_SESSION['admin_action_message'] = "Accesso negato a questa sezione.";
    $_SESSION['admin_action_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}
// FINE CONTROLLO ACCESSO

$page_title = "Impostazioni Sito";
$message = $_SESSION['settings_message'] ?? null;
$message_type = $_SESSION['settings_message_type'] ?? 'info';

if ($message) {
    unset($_SESSION['settings_message']);
    unset($_SESSION['settings_message_type']);
}

// Funzione helper per aggiornare un'impostazione
function update_site_setting($key, $value) {
    global $mysqli;
    $stmt = $mysqli->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    if ($stmt) {
        $stmt->bind_param("sss", $key, $value, $value);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

// Gestione del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $current_success_messages = [];
        $current_error_messages = [];

        // Gestione Nome Sito
        if (isset($_POST['site_name_dynamic'])) {
            if (!update_site_setting('site_name_dynamic', trim($_POST['site_name_dynamic']))) {
                 $current_error_messages[] = "Errore aggiornamento nome sito.";
            }
        }

        // Gestione Testo Benvenuto Homepage
        if (isset($_POST['homepage_welcome_text'])) {
            if (!update_site_setting('homepage_welcome_text', trim($_POST['homepage_welcome_text']))) {
                 $current_error_messages[] = "Errore aggiornamento testo di benvenuto homepage.";
            }
        }
        
        // Gestione Contenuto "Il Progetto"
        if (isset($_POST['about_page_content'])) {
            if (!update_site_setting('about_page_content', $_POST['about_page_content'])) {
                 $current_error_messages[] = "Errore aggiornamento contenuto scheda 'Il Progetto'.";
            }
        }
        
        // --- GESTIONE NUOVI CONTENUTI ---
        if (isset($_POST['about_page_who_am_i'])) {
            if (!update_site_setting('about_page_who_am_i', $_POST['about_page_who_am_i'])) {
                 $current_error_messages[] = "Errore aggiornamento contenuto scheda 'Chi Sono'.";
            }
        }
        if (isset($_POST['about_page_how_to_use'])) {
            if (!update_site_setting('about_page_how_to_use', $_POST['about_page_how_to_use'])) {
                 $current_error_messages[] = "Errore aggiornamento contenuto scheda 'Guida all'Uso'.";
            }
        }
        // --- FINE GESTIONE NUOVI CONTENUTI ---

        // Gestione Modalità Manutenzione
        $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
        if (!update_site_setting('maintenance_mode', $maintenance_mode)) {
            $current_error_messages[] = "Errore aggiornamento modalità manutenzione.";
        }
        
        // Gestione Upload Logo (logica invariata)
        // ... (omesso per brevità, il codice per il logo rimane lo stesso)

        if (!empty($current_error_messages)) {
            $_SESSION['settings_message'] = "Si sono verificati alcuni errori: " . implode(" ", $current_error_messages);
            $_SESSION['settings_message_type'] = 'error';
        } else {
            $_SESSION['settings_message'] = "Impostazioni salvate con successo!";
            $_SESSION['settings_message_type'] = 'success';
        }

        header('Location: settings_manage.php');
        exit;
    }
}

// Recupera le impostazioni correnti per visualizzarle nel form
$current_site_name_dynamic = get_site_setting('site_name_dynamic', $mysqli, SITE_NAME);
$current_homepage_welcome_text = get_site_setting('homepage_welcome_text', $mysqli, '');
$current_about_page_content = get_site_setting('about_page_content', $mysqli, '');
// NUOVO: Recupera i nuovi contenuti
$current_about_who_am_i = get_site_setting('about_page_who_am_i', $mysqli, '');
$current_about_how_to_use = get_site_setting('about_page_how_to_use', $mysqli, '');
$current_logo_path = get_site_setting('site_logo_path', $mysqli);
$current_maintenance_mode = get_site_setting('maintenance_mode', $mysqli, '0');

require_once ROOT_PATH . 'admin/includes/header_admin.php';
?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars(trim($message)); ?>
        </div>
    <?php endif; ?>

    <form action="settings_manage.php" method="POST" enctype="multipart/form-data">
        <h3>Impostazioni Generali</h3>
        <div class="form-group">
            <label for="site_name_dynamic">Nome del Sito:</label>
            <input type="text" name="site_name_dynamic" id="site_name_dynamic" class="form-control" value="<?php echo htmlspecialchars($current_site_name_dynamic); ?>">
        </div>

        <hr>
        <h3>Contenuti Pagina Iniziale (Homepage)</h3>
        <div class="form-group">
            <label for="homepage_welcome_text">Testo di Benvenuto:</label>
            <textarea name="homepage_welcome_text" id="homepage_welcome_text" class="form-control" rows="5"><?php echo htmlspecialchars($current_homepage_welcome_text); ?></textarea>
        </div>

        <hr>
        <h3>Contenuti Pagina "Info & Contatti"</h3>
        
        <div class="form-group">
            <label for="about_page_who_am_i">Scheda "Chi Sono":</label>
            <textarea name="about_page_who_am_i" id="about_page_who_am_i" class="form-control" rows="8"><?php echo htmlspecialchars($current_about_who_am_i); ?></textarea>
            <small>Testo che descrive te o i creatori del progetto.</small>
        </div>

        <div class="form-group">
            <label for="about_page_content">Scheda "Il Progetto":</label>
            <textarea name="about_page_content" id="about_page_content" class="form-control" rows="8"><?php echo htmlspecialchars($current_about_page_content); ?></textarea>
            <small>Testo che descrive la mission e la filosofia del sito.</small>
        </div>
        
        <div class="form-group">
            <label for="about_page_how_to_use">Scheda "Guida all'Uso":</label>
            <textarea name="about_page_how_to_use" id="about_page_how_to_use" class="form-control" rows="8"><?php echo htmlspecialchars($current_about_how_to_use); ?></textarea>
            <small>Spiegazioni sulle funzionalità del sito per i visitatori.</small>
        </div>

        <hr>
        <h3>Logo e Manutenzione</h3>
        <div class="form-group">
            <label for="site_logo">Carica Nuovo Logo (opzionale):</label>
            <input type="file" name="site_logo" id="site_logo" class="form-control-file">
            <small>Consigliato formato PNG trasparente o SVG. Max 2MB. Sovrascriverà il logo esistente.</small>
        </div>
        <?php if ($current_logo_path && file_exists(UPLOADS_PATH . $current_logo_path)): ?>
            <div class="form-group">
                <label>Logo Attuale:</label>
                <img src="<?php echo UPLOADS_URL . htmlspecialchars($current_logo_path); ?>?t=<?php echo time(); ?>" alt="Logo Attuale" style="max-height: 70px; background-color: #eee; padding: 5px; border-radius:3px;">
                <br>
                <label class="inline-label" style="margin-top:10px;">
                    <input type="checkbox" name="delete_logo" value="1"> Rimuovi logo attuale
                </label>
            </div>
        <?php endif; ?>

        <hr>
        <h3>Modalità Manutenzione</h3>
        <div class="form-group">
            <label class="inline-label">
                <input type="checkbox" name="maintenance_mode" value="1" <?php echo ($current_maintenance_mode === '1') ? 'checked' : ''; ?>>
                Attiva Modalità Manutenzione
            </label>
            <small>Se attivata, solo gli amministratori loggati potranno visualizzare il sito. Gli altri utenti vedranno una pagina di manutenzione.</small>
        </div>

        <hr>
        <div class="form-group" style="margin-top: 25px;">
            <button type="submit" name="save_settings" class="btn btn-primary">Salva Tutte le Impostazioni</button>
        </div>
    </form>
</div>

<?php
require_once ROOT_PATH . 'admin/includes/footer_admin.php';
if(isset($mysqli)) $mysqli->close();
?>