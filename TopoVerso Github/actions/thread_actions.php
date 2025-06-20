<?php
require_once '../config/config.php';
require_once ROOT_PATH . 'includes/db_connect.php';

// Sicurezza: Solo gli admin possono usare questo file di azione
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- MODIFICA CHIAVE 1: Controllo accesso aggiornato e unificato ---
// Ora verifichiamo il ruolo dalla sessione utente standard.
$user_role_check = $_SESSION['user_role_frontend'] ?? 'user';
if ($user_role_check !== 'admin') {
    $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Accesso non autorizzato.'];
    header('Location: ../forum.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['create_thread'])) {
    header('Location: ../forum.php');
    exit;
}

// 1. Recupera e valida i dati
$section_id = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

// --- MODIFICA CHIAVE 2: Usiamo l'ID utente dalla sessione frontend ---
// Poiché il login è unificato, l'admin avrà sempre un user_id_frontend.
$current_user_id = $_SESSION['user_id_frontend']; 
$current_username = $_SESSION['username_frontend'] ?? 'Amministrazione';

$errors = [];
if (empty($section_id) || $section_id == 1) { $errors[] = "Devi selezionare una sezione valida del forum."; }
if (empty($title) || strlen($title) < 5) { $errors[] = "Il titolo deve essere di almeno 5 caratteri."; }
if (empty($content) || strlen($content) < 10) { $errors[] = "Il contenuto del messaggio deve essere di almeno 10 caratteri."; }

// Verifica che la sezione esista
if ($section_id) {
    $stmt_sec = $mysqli->prepare("SELECT id FROM forum_sections WHERE id = ?");
    $stmt_sec->bind_param('i', $section_id);
    $stmt_sec->execute();
    if ($stmt_sec->get_result()->num_rows === 0) {
        $errors[] = "La sezione selezionata non è valida.";
    }
    $stmt_sec->close();
}

if (!empty($errors)) {
    $_SESSION['feedback'] = ['type' => 'error', 'message' => implode('<br>', $errors)];
    header('Location: ../new_thread.php'); // Torna al form
    exit;
}

// 2. Inizia la transazione
$mysqli->begin_transaction();

try {
    // --- MODIFICA CHIAVE 3: Inserisce il thread con il corretto ID utente ---
    $stmt_thread = $mysqli->prepare("INSERT INTO forum_threads (section_id, user_id, title) VALUES (?, ?, ?)");
    $stmt_thread->bind_param('iis', $section_id, $current_user_id, $title);
    $stmt_thread->execute();
    $new_thread_id = $mysqli->insert_id;
    if ($new_thread_id === 0) {
        throw new Exception("Creazione della discussione fallita.");
    }
    $stmt_thread->close();

    // --- MODIFICA CHIAVE 4: Inserisce il post con il corretto ID utente e senza author_name ---
    $post_status = 'approved'; // I post degli admin sono sempre approvati
    
    $stmt_post = $mysqli->prepare("INSERT INTO forum_posts (thread_id, user_id, author_name, content, status) VALUES (?, ?, NULL, ?, ?)");
    $stmt_post->bind_param('iiss', $new_thread_id, $current_user_id, $content, $post_status);
    $stmt_post->execute();
    if ($mysqli->affected_rows === 0) {
         throw new Exception("Creazione del post di apertura fallita.");
    }
    $stmt_post->close();

    // 5. Commit della transazione
    $mysqli->commit();

    $_SESSION['feedback'] = ['type' => 'success', 'message' => 'Nuova discussione creata con successo!'];
    header('Location: ../thread.php?id=' . $new_thread_id);
    exit;

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Forum thread creation error: " . $e->getMessage());
    $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Si è verificato un errore tecnico. Riprova.'];
    header('Location: ../new_thread.php');
    exit;
}