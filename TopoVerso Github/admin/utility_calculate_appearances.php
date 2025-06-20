<?php
// topolinolib/admin/utility_calculate_appearances.php

require_once '../config/config.php'; // Contiene session_start()
require_once ROOT_PATH . 'includes/db_connect.php';
require_once ROOT_PATH . 'includes/functions.php';

// Sicurezza: Solo gli admin possono eseguire questo script
if (!isset($_SESSION['user_id_frontend']) || !isset($_SESSION['user_role_frontend']) || $_SESSION['user_role_frontend'] !== 'admin') {
    $_SESSION['admin_message'] = "Accesso negato. Non hai i permessi per eseguire questa operazione.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
}

// Verifica che la richiesta sia POST per prevenire l'esecuzione accidentale tramite URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
}

$start_time = microtime(true);

// ### INIZIO BLOCCO QUERY CORRETTO ###
// La query ora unisce correttamente la tabella `comics` (alias c) per ottenere c.publication_date
$sql = "
    INSERT INTO calculated_first_appearances (character_id, calculated_story_id, calculated_comic_id, calculated_publication_date)
    SELECT
        t.character_id,
        t.story_id,
        t.comic_id,
        t.min_publication_date
    FROM (
        SELECT
            sc.character_id,
            s.story_id,
            c.comic_id,
            c.publication_date AS min_publication_date,
            ROW_NUMBER() OVER(PARTITION BY sc.character_id ORDER BY c.publication_date ASC, s.story_id ASC) as rn
        FROM
            story_characters sc
        JOIN
            stories s ON sc.story_id = s.story_id
        JOIN
            comics c ON s.comic_id = c.comic_id
        WHERE
            c.publication_date IS NOT NULL AND c.publication_date > '1900-01-01'
    ) AS t
    WHERE
        t.rn = 1
    ON DUPLICATE KEY UPDATE
        calculated_story_id = VALUES(calculated_story_id),
        calculated_comic_id = VALUES(calculated_comic_id),
        calculated_publication_date = VALUES(calculated_publication_date);
";
// ### FINE BLOCCO QUERY CORRETTO ###

if ($stmt = $mysqli->prepare($sql)) {
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        $_SESSION['admin_message'] = "Calcolo delle prime apparizioni completato con successo in {$execution_time} secondi. Righe elaborate (inserite/aggiornate): {$affected_rows}.";
        $_SESSION['admin_message_type'] = 'success';
    } else {
        $_SESSION['admin_message'] = "Errore durante l'esecuzione del calcolo: " . $stmt->error;
        $_SESSION['admin_message_type'] = 'error';
        error_log("Errore in utility_calculate_appearances.php: " . $stmt->error);
    }
    $stmt->close();
} else {
    $_SESSION['admin_message'] = "Errore nella preparazione della query: " . $mysqli->error;
    $_SESSION['admin_message_type'] = 'error';
    error_log("Errore preparazione query in utility_calculate_appearances.php: " . $mysqli->error);
}

$mysqli->close();

// Reindirizza alla dashboard admin con il messaggio
header('Location: ' . BASE_URL . 'admin/index.php');
exit;

?>