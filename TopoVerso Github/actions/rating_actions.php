<?php
// topolinolib/actions/rating_actions.php

require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$entity_type = $_POST['entity_type'] ?? null;
$entity_id = filter_input(INPUT_POST, 'entity_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$redirect_url = $_POST['redirect_url'] ?? BASE_URL . 'index.php';

if (!$entity_id || !$rating || !in_array($entity_type, ['comic', 'story']) || $rating < 1 || $rating > 5) {
    $_SESSION['message'] = "Errore: Dati di voto non validi.";
    $_SESSION['message_type'] = 'error';
    header('Location: ' . $redirect_url);
    exit;
}

$ip_address = $_SERVER['REMOTE_ADDR'];
$cookie_name = "voted_{$entity_type}_{$entity_id}";
$table_name = ($entity_type === 'comic') ? 'comic_ratings' : 'story_ratings';
$id_column = ($entity_type === 'comic') ? 'comic_id' : 'story_id';

// MODIFICA PRINCIPALE: ORA CONTROLLIAMO PRIMA IL DATABASE.
// Questo assicura che se un admin cancella un voto, l'IP corrispondente può votare di nuovo.

$stmt_check = $mysqli->prepare("SELECT rating_id FROM {$table_name} WHERE {$id_column} = ? AND ip_address = ?");
if ($stmt_check) {
    $stmt_check->bind_param("is", $entity_id, $ip_address);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // Il voto esiste nel DB, quindi neghiamo l'accesso e per sicurezza impostiamo/rinnoviamo il cookie.
        setcookie($cookie_name, 'true', time() + (86400 * 365), "/");
        $_SESSION['message'] = "Risulta già un voto da questo indirizzo per questo elemento.";
        $_SESSION['message_type'] = 'info';
        header('Location: ' . $redirect_url);
        exit;
    }
    $stmt_check->close();
} else {
    $_SESSION['message'] = "Errore tecnico (check). Riprova.";
    $_SESSION['message_type'] = 'error';
    error_log("Rating check prepare error: " . $mysqli->error);
    header('Location: ' . $redirect_url);
    exit;
}

// Se siamo arrivati qui, significa che non c'è un voto nel DB per questo IP.
// Ora possiamo anche controllare il cookie come seconda linea di difesa, anche se meno necessaria.
if (isset($_COOKIE[$cookie_name])) {
    $_SESSION['message'] = "Hai già votato per questo elemento.";
    $_SESSION['message_type'] = 'info';
    header('Location: ' . $redirect_url);
    exit;
}

// Inseriamo il nuovo voto
$stmt_insert = $mysqli->prepare("INSERT INTO {$table_name} ({$id_column}, rating, ip_address) VALUES (?, ?, ?)");
if ($stmt_insert) {
    $stmt_insert->bind_param("iis", $entity_id, $rating, $ip_address);
    if ($stmt_insert->execute()) {
        setcookie($cookie_name, 'true', time() + (86400 * 365), "/");
        $_SESSION['message'] = "Grazie per il tuo voto!";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Errore durante la registrazione del voto.";
        $_SESSION['message_type'] = 'error';
        error_log("Rating action error: " . $stmt_insert->error);
    }
    $stmt_insert->close();
} else {
    $_SESSION['message'] = "Errore tecnico (insert). Riprova.";
    $_SESSION['message_type'] = 'error';
    error_log("Rating action prepare error: " . $mysqli->error);
}

$mysqli->close();
header('Location: ' . $redirect_url);
exit;