<?php
require_once '../config/config.php'; 
require_once ROOT_PATH . 'includes/db_connect.php';

header('Content-Type: application/json');

// Autenticazione Admin
if (!isset($_SESSION['admin_user_id']) && !(isset($_SESSION['user_id_frontend']) && isset($_SESSION['user_role_frontend']) && $_SESSION['user_role_frontend'] === 'contributor')) {
    echo json_encode(['success' => false, 'message' => 'Accesso negato.']);
    exit;
}


$comic_id_ajax = isset($_GET['comic_id']) ? (int)$_GET['comic_id'] : 0;
$issue_number_ajax = isset($_GET['issue_number']) ? trim($_GET['issue_number']) : null; // NUOVO: per historical_events

$response_stories = [];
$success_ajax = false;
$message_ajax = '';

// NUOVO: Se è fornito issue_number ma non comic_id, cerca comic_id
if ($comic_id_ajax <= 0 && !empty($issue_number_ajax)) {
    $stmt_find_id = $mysqli->prepare("SELECT comic_id FROM comics WHERE issue_number = ? LIMIT 1");
    if ($stmt_find_id) {
        $stmt_find_id->bind_param("s", $issue_number_ajax);
        $stmt_find_id->execute();
        $res_id = $stmt_find_id->get_result();
        if ($comic_f = $res_id->fetch_assoc()) {
            $comic_id_ajax = (int)$comic_f['comic_id'];
        } else {
            $message_ajax = "Nessun albo trovato con numero '".htmlspecialchars($issue_number_ajax)."'.";
        }
        $stmt_find_id->close();
    } else {
        $message_ajax = "Errore preparazione query per trovare ID albo: " . $mysqli->error;
    }
}


if ($comic_id_ajax > 0) {
    $stmt = $mysqli->prepare("SELECT story_id, title, sequence_in_comic FROM stories WHERE comic_id = ? ORDER BY sequence_in_comic ASC, story_id ASC");
    if ($stmt) {
        $stmt->bind_param("i", $comic_id_ajax);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $response_stories[] = $row;
            }
            $success_ajax = true;
            // Se non ci sono storie, $response_stories sarà vuoto, il che è corretto.
            // $message_ajax può rimanere vuoto se la query ha successo, anche senza risultati.
        } else {
            $message_ajax = "Errore esecuzione query storie: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message_ajax = "Errore preparazione query storie: " . $mysqli->error;
    }
} else {
    if (empty($message_ajax)) { // Se non c'è già un messaggio di errore dal lookup dell'issue_number
        $message_ajax = "ID Albo o Numero Albo di riferimento non valido o non fornito.";
    }
}

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
echo json_encode(['success' => $success_ajax, 'stories' => $response_stories, 'message' => $message_ajax]);
?>