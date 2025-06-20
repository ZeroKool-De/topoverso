<?php
// config/config.php

// Impostazioni Database
define('DB_HOST', 'localhost'); // o l'host del tuo DB, es. 127.0.0.1
define('DB_USER', 'root');      // Il tuo username del DB
define('DB_PASS', 'root');          // La tua password del DB
define('DB_NAME', 'topolino_catalog'); // Il nome del database creato prima

// Percorsi dell'applicazione
define('ROOT_PATH', dirname(__DIR__) . '/'); // Percorso radice del progetto
define('BASE_URL', 'http://localhost:8888/topolinolib/'); // URL base del tuo sito. CAMBIALO!

// Impostazioni per gli upload
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('UPLOADS_URL', BASE_URL . 'uploads/'); // URL per accedere ai file caricati

// Impostazioni varie
define('SITE_NAME', 'Catalogo Topolino'); // Questo sarà usato come fallback se il nome dinamico non è settato

// Abilita la visualizzazione degli errori PHP durante lo sviluppo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Imposta il timezone, se necessario
date_default_timezone_set('Europe/Rome');

// Imposta la locale per le date in italiano
if (defined('LC_TIME')) {
    setlocale(LC_TIME, 'it_IT.UTF-8', 'it_IT.utf8', 'it_IT', 'ita_ITA', 'italian');
}

// Inizializza la sessione
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// === INCLUDI QUI LE DIPENDENZE FONDAMENTALI ===
// 1. Connessione al Database
if (file_exists(ROOT_PATH . 'includes/db_connect.php')) {
    require_once ROOT_PATH . 'includes/db_connect.php'; // Stabilisce $mysqli
} else {
    // Se db_connect non è trovato, la modalità manutenzione (e il sito) non funzioneranno correttamente.
    // Potresti voler terminare l'esecuzione o gestire l'errore.
    error_log("Errore critico: Impossibile trovare includes/db_connect.php da config.php");
    // Non fare die() qui se vuoi tentare di mostrare una pagina di errore più avanti
    // o se hai un meccanismo di fallback, ma sappi che il DB non sarà disponibile.
}

// 2. Funzioni Helper (inclusa get_site_setting)
if (file_exists(ROOT_PATH . 'includes/functions.php')) {
    require_once ROOT_PATH . 'includes/functions.php';
} else {
    error_log("Errore critico: Impossibile trovare includes/functions.php da config.php");
}
// === FINE DIPENDENZE FONDAMENTALI ===


// --- LOGICA MODALITÀ MANUTENZIONE ---
// Assicurati che $mysqli sia stato definito e la connessione sia riuscita
if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->connect_error === null && function_exists('get_site_setting')) {
    $maintenance_mode_status = get_site_setting('maintenance_mode', $mysqli, '0');

    if ($maintenance_mode_status === '1') { // Modalità manutenzione ATTIVA
        $is_admin_logged_in = isset($_SESSION['admin_user_id']);
        $script_name = basename($_SERVER['PHP_SELF']);
        $is_admin_path = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false);

        // Pagine sempre accessibili in modalità manutenzione (per tutti)
        $always_accessible_scripts = ['maintenance.php'];
        if ($is_admin_path && $script_name === 'login.php') { // Pagina di login dell'admin
            $always_accessible_scripts[] = 'login.php';
        }
        // Pagine di login/registrazione frontend
        if (!$is_admin_path && in_array($script_name, ['login.php', 'register.php', 'logout_user.php'])) {
             $always_accessible_scripts[] = $script_name;
        }
        // Aggiungi qui altre pagine pubbliche essenziali se necessario (es. 'password_reset.php')

        if (!$is_admin_logged_in) { // Se l'utente NON è un admin loggato
            // Verifica se l'utente sta cercando di accedere a una pagina non permessa
            $is_on_always_accessible_page = false;
            if (in_array($script_name, $always_accessible_scripts)) {
                // Per 'login.php', dobbiamo distinguere tra admin e frontend se hanno lo stesso nome file ma path diversi.
                // La condizione $is_admin_path gestisce questo implicitamente per il login admin.
                // Per il login frontend, la condizione !$is_admin_path e l'inclusione in $always_accessible_scripts lo permette.
                $is_on_always_accessible_page = true;
            }

            if (!$is_on_always_accessible_page) {
                // Se non è un admin E non è su una pagina permessa, reindirizza a maintenance.php
                // Questo copre tutte le pagine frontend e anche tentativi di accesso diretto a pagine admin interne.
                header('HTTP/1.1 503 Service Temporarily Unavailable');
                header('Location: ' . BASE_URL . 'maintenance.php');
                exit();
            }
        }
        // Se l'utente è un admin loggato ($is_admin_logged_in è true), questa logica di redirect viene saltata
        // e l'admin può navigare liberamente sia nel frontend che nel backend.
    }
} else {
    // Questo blocco viene eseguito se $mysqli non è disponibile o get_site_setting non esiste.
    // Potrebbe essere un errore di connessione al DB o un file mancante.
    // In questo scenario, la modalità manutenzione non può essere verificata correttamente.
    // Decidi come gestire: mostrare un errore generico, loggare, o tentare di continuare
    // con il rischio che il sito non funzioni pienamente.
    // Per ora, logghiamo l'errore se $mysqli non è disponibile ma NON mostriamo un errore fatale sulla pagina,
    // per evitare di bloccare tutto il sito se il DB ha solo un problema temporaneo e la pagina richiesta
    // non dipende strettamente dal DB per il suo contenuto principale (improbabile per questo progetto).
    if (!isset($mysqli) || !($mysqli instanceof mysqli) || $mysqli->connect_error !== null) {
        error_log("Config.php: Connessione al DB non disponibile o fallita. Controllo modalità manutenzione saltato.");
    }
    if (!function_exists('get_site_setting')) {
        error_log("Config.php: Funzione get_site_setting non trovata. Controllo modalità manutenzione saltato.");
    }
}
// --- FINE LOGICA MODALITÀ MANUTENZIONE ---
?>