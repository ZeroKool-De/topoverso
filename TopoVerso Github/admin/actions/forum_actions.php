<?php
require_once '../../config/config.php';
// db_connect.php e functions.php sono ora inclusi da config.php

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Verifica che l'utente sia un admin
if (!isset($_SESSION['admin_user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$action = $_POST['action'] ?? '';

// --- INIZIO BLOCCO MODIFICATO ---
if ($action === 'approve_post') {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    if ($post_id) {
        $mysqli->begin_transaction();
        try {
            // 1. Aggiorna lo stato del post a 'approved'
            $stmt_approve = $mysqli->prepare("UPDATE forum_posts SET status = 'approved' WHERE id = ?");
            $stmt_approve->bind_param('i', $post_id);
            $stmt_approve->execute();
            $stmt_approve->close();

            // 2. Recupera i dettagli necessari per la notifica
            $stmt_details = $mysqli->prepare("
                SELECT p.thread_id, p.user_id as post_author_user_id, p.author_name as post_author_visitor_name
                FROM forum_posts p
                WHERE p.id = ?");
            $stmt_details->bind_param('i', $post_id);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_assoc();
            $stmt_details->close();

            if ($details) {
                // Questo commento era di un visitatore, quindi post_author_user_id è NULL
                // e usiamo il nome che ha inserito.
                $poster_name = $details['post_author_visitor_name'] ?? 'Visitatore';
                
                // 3. Chiama la funzione di notifica standard per avvisare TUTTI i partecipanti al thread
                send_thread_notifications(
                    $details['thread_id'], 
                    $post_id, 
                    $details['post_author_user_id'], // Sarà NULL, è corretto
                    $poster_name, 
                    $mysqli
                );
            }
             $mysqli->commit();
             $_SESSION['admin_feedback'] = ['type' => 'success', 'message' => 'Commento approvato e notifiche inviate ai partecipanti.'];
        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Errore durante approvazione post e invio notifiche: " . $e->getMessage());
            $_SESSION['admin_feedback'] = ['type' => 'error', 'message' => 'Errore durante l\'approvazione del commento.'];
        }
    }
    header('Location: ../forum_manage.php');
    exit;
}
// --- FINE BLOCCO MODIFICATO ---


if ($action === 'delete_post') {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $redirect_thread_id = filter_input(INPUT_POST, 'redirect_thread_id', FILTER_VALIDATE_INT);

    if ($post_id) {
        $stmt = $mysqli->prepare("DELETE FROM forum_posts WHERE id = ?");
        $stmt->bind_param('i', $post_id);
        $stmt->execute();
        $stmt->close();
    }
    if ($redirect_thread_id) {
        header('Location: ../../thread.php?id=' . $redirect_thread_id . '&message=Commento eliminato.');
    } else {
        header('Location: ../forum_manage.php?message=Commento eliminato.');
    }
    exit;
}

if ($action === 'delete_thread') {
    $thread_id = filter_input(INPUT_POST, 'thread_id', FILTER_VALIDATE_INT);
    if ($thread_id) {
        $mysqli->begin_transaction();
        try {
            // Elimina prima i post associati
            $stmt_posts = $mysqli->prepare("DELETE FROM forum_posts WHERE thread_id = ?");
            $stmt_posts->bind_param('i', $thread_id);
            $stmt_posts->execute();
            $stmt_posts->close();
            // Poi elimina il thread
            $stmt_thread = $mysqli->prepare("DELETE FROM forum_threads WHERE id = ?");
            $stmt_thread->bind_param('i', $thread_id);
            $stmt_thread->execute();
            $stmt_thread->close();
            $mysqli->commit();
            $_SESSION['feedback'] = ['type' => 'success', 'message' => 'Discussione eliminata con successo.'];
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Errore durante l\'eliminazione della discussione.'];
        }
    }
    header('Location: ../../forum.php');
    exit;
}

if ($action === 'add_section') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    if (!empty($name)) {
        $stmt = $mysqli->prepare("INSERT INTO forum_sections (name, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $name, $description);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: ../forum_manage.php?view=sections&message=Sezione aggiunta.');
    exit;
}

if ($action === 'update_section') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    if ($id && !empty($name)) {
        $stmt = $mysqli->prepare("UPDATE forum_sections SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param('ssi', $name, $description, $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: ../forum_manage.php?view=sections&message=Sezione aggiornata.');
    exit;
}

if ($action === 'delete_section') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id && $id != 1) { // Protezione per non cancellare la sezione di default
        $stmt = $mysqli->prepare("DELETE FROM forum_sections WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: ../forum_manage.php?view=sections&message=Sezione eliminata.');
    exit;
}

header('Location: ../index.php');
exit;