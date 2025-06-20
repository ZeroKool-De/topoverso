<?php
// topolinolib/includes/db_connect.php

// Includi il file di configurazione solo se non è già stato incluso
if (!defined('DB_HOST')) { 
    // Se questo file viene incluso da una sottocartella (es. admin/),
    // il percorso a config.php deve essere relativo alla posizione di db_connect.php
    if (file_exists(dirname(__DIR__) . '/config/config.php')) { // Se db_connect.php è in /includes/
        require_once dirname(__DIR__) . '/config/config.php';
    } elseif (file_exists(dirname(dirname(__DIR__)) . '/config/config.php')) { // Se db_connect.php è in /admin/includes/ (non il nostro caso attuale ma per robustezza)
        require_once dirname(dirname(__DIR__)) . '/config/config.php';
    } else {
        // Fallback se la struttura cambia o il file è chiamato da un percorso inaspettato
        // Questo assume che config.php sia una cartella sopra la cartella corrente di db_connect.php
        // Per la nostra struttura attuale (db_connect in /includes), il primo if è sufficiente.
        @include_once '../config/config.php'; 
    }
}


// Creazione della connessione MySQLi
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Controllo della connessione
if ($mysqli->connect_error) {
    // In un'applicazione reale, loggheresti l'errore e mostreresti una pagina di errore generica.
    // Per lo sviluppo, possiamo morire qui per vedere l'errore.
    error_log("Errore connessione MySQLi: " . $mysqli->connect_error); // Logga l'errore
    die("Errore di connessione al database. Si prega di riprovare più tardi."); // Messaggio generico per l'utente
}

// Imposta il charset a utf8mb4 (consigliato)
if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Errore nel caricamento del set di caratteri utf8mb4: " . $mysqli->error);
    // Non fatale, ma è bene saperlo.
}

// === MODIFICA CHIAVE QUI ===
// Disabilita il reporting degli errori MySQLi che lancia eccezioni per le query.
// Questo permette al nostro codice PHP di controllare manualmente gli errori
// (es. con if (!$stmt->execute()) ) e gestire $mysqli->errno e $stmt->error.
mysqli_report(MYSQLI_REPORT_OFF); 
// === FINE MODIFICA CHIAVE ===

// Non chiudere la connessione $mysqli qui.
// Verrà usata dagli script che includono questo file.
// PHP la chiuderà automaticamente alla fine dello script, 
// oppure possiamo chiuderla esplicitamente alla fine di ogni pagina che la usa.
?>