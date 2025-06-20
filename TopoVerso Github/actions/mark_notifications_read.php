<?php
// topolinolib/actions/mark_notifications_read.php
require_once '../config/config.php';

// Inizializza la sessione se non è già attiva
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Rispondi solo a richieste POST e solo se l'utente è loggato
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id_frontend'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato.']);
    exit;
}

require_once '../includes/db_connect.php';

$user_id = $_SESSION['user_id_frontend'];
$response = ['success' => false];

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $response['success'] = true;
        } else {
            error_log("Errore DB in mark_notifications_read: " . $stmt->error);
        }
        $stmt->close();
    }
    $mysqli->close();
}

header('Content-Type: application/json');
echo json_encode($response);
exit;