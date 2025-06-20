<?php
require_once '../config/config.php';
// db_connect e functions sono già inclusi da config.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$action = $_POST['action'] ?? 'add_comment';

// Azione per modificare un post esistente
if ($action === 'edit_post') {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $content = trim($_POST['content'] ?? '');
    $redirect_url = $_POST['redirect_url'] ?? 'index.php';

    if (!isset($_SESSION['user_id_frontend'])) {
        $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Devi essere loggato per modificare un commento.'];
        header('Location: ' . BASE_URL . $redirect_url);
        exit;
    }
    $user_id = $_SESSION['user_id_frontend'];

    if (empty($content) || !$post_id) {
        $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Contenuto non valido.'];
        header('Location: ' . BASE_URL . $redirect_url);
        exit;
    }

    $user_role = $_SESSION['user_role_frontend'] ?? 'user';
    $is_admin = ($user_role === 'admin');
    
    $stmt_check = $mysqli->prepare("SELECT user_id FROM forum_posts WHERE id = ?");
    $stmt_check->bind_param("i", $post_id);
    $stmt_check->execute();
    $post_owner = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    $is_owner = ($post_owner && $post_owner['user_id'] == $user_id);

    if (!$is_admin && !$is_owner) {
        $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Non hai i permessi per modificare questo commento.'];
        header('Location: ' . BASE_URL . $redirect_url);
        exit;
    }
    
    $admin_editor_id = ($is_admin && !$is_owner) ? $user_id : NULL;

    $stmt_update = $mysqli->prepare("UPDATE forum_posts SET content = ?, edited_at = CURRENT_TIMESTAMP, edited_by_admin_id = ? WHERE id = ?");
    $stmt_update->bind_param("sii", $content, $admin_editor_id, $post_id);
    
    if ($stmt_update->execute()) {
        $_SESSION['feedback'] = ['type' => 'success', 'message' => 'Commento modificato con successo!'];
    } else {
        $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Errore durante la modifica del commento.'];
    }
    $stmt_update->close();
    
    header('Location: ' . BASE_URL . $redirect_url . '#post-' . $post_id);
    exit;
}

// Azione per aggiungere un nuovo commento
if ($action === 'add_comment') {
    $comic_id = filter_input(INPUT_POST, 'comic_id', FILTER_VALIDATE_INT);
    $story_id = filter_input(INPUT_POST, 'story_id', FILTER_VALIDATE_INT) ?: NULL;
    $thread_id = filter_input(INPUT_POST, 'thread_id', FILTER_VALIDATE_INT);
    $content = trim($_POST['content'] ?? '');
    $redirect_url = $_POST['redirect_url'] ?? 'index.php';
    $new_post_id = 0;

    if (empty($content)) {
        $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Il commento non può essere vuoto.'];
        header('Location: ' . BASE_URL . $redirect_url);
        exit;
    }

    $is_logged_in = isset($_SESSION['user_id_frontend']);
    $user_id = $is_logged_in ? $_SESSION['user_id_frontend'] : NULL;
    $username = $_SESSION['username_frontend'] ?? 'Un visitatore';
    $author_name = !$is_logged_in ? trim($_POST['author_name'] ?? 'Visitatore') : NULL;

    if (!$is_logged_in && empty($author_name)) {
        $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Devi inserire un nome per commentare come visitatore.'];
        header('Location: ' . BASE_URL . $redirect_url);
        exit;
    }

    $mysqli->begin_transaction();

    try {
        if (empty($thread_id)) {
            if (empty($comic_id)) { 
                throw new Exception("ID fumetto mancante per creare una nuova discussione."); 
            }
            
            $thread_sql = "SELECT id FROM forum_threads WHERE comic_id = ? AND " . ($story_id ? "story_id = ?" : "story_id IS NULL");
            $stmt_find = $mysqli->prepare($thread_sql);
            if ($story_id) { 
                $stmt_find->bind_param('ii', $comic_id, $story_id); 
            } else { 
                $stmt_find->bind_param('i', $comic_id); 
            }
            $stmt_find->execute();
            $thread = $stmt_find->get_result()->fetch_assoc();
            $stmt_find->close();
    
            if ($thread) {
                $thread_id = $thread['id'];
            } else {
                $comic_info_stmt = $mysqli->prepare("SELECT issue_number, title FROM comics WHERE comic_id = ?");
                $comic_info_stmt->bind_param('i', $comic_id); 
                $comic_info_stmt->execute();
                $comic_info = $comic_info_stmt->get_result()->fetch_assoc(); 
                $comic_info_stmt->close();
                
                $thread_title = "Discussione su Topolino #" . ($comic_info['issue_number'] ?? $comic_id);
                if (!empty($comic_info['title'])) { $thread_title .= " - " . $comic_info['title']; }
                
                if ($story_id) {
                    $story_info_stmt = $mysqli->prepare("SELECT title FROM stories WHERE story_id = ?");
                    $story_info_stmt->bind_param('i', $story_id); 
                    $story_info_stmt->execute();
                    $story_info = $story_info_stmt->get_result()->fetch_assoc(); 
                    $story_info_stmt->close();
                    if ($story_info) { $thread_title .= ' (Storia: ' . $story_info['title'] . ')'; }
                }
                
                $stmt_create_thread = $mysqli->prepare("INSERT INTO forum_threads (section_id, comic_id, story_id, user_id, title) VALUES (1, ?, ?, ?, ?)");
                $stmt_create_thread->bind_param('iiis', $comic_id, $story_id, $user_id, $thread_title);
                $stmt_create_thread->execute();
                $thread_id = $stmt_create_thread->insert_id;
                $stmt_create_thread->close();
            }
        }
        
        $status = $is_logged_in ? 'approved' : 'pending';
        $stmt_insert_post = $mysqli->prepare("INSERT INTO forum_posts (thread_id, user_id, author_name, content, status) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert_post->bind_param('iisss', $thread_id, $user_id, $author_name, $content, $status);
        $stmt_insert_post->execute();
        $new_post_id = $mysqli->insert_id;
        $stmt_insert_post->close();

        $stmt_update_thread = $mysqli->prepare("UPDATE forum_threads SET last_post_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt_update_thread->bind_param('i', $thread_id);
        $stmt_update_thread->execute();
        $stmt_update_thread->close();

        // --- INIZIO BLOCCO NOTIFICHE ---
        // La logica qui è già corretta e non necessita di modifiche.
        // Gestisce sia le notifiche per gli utenti che quelle per l'admin.
        if ($status === 'approved') {
            $poster_display_name = $is_logged_in ? $username : ($author_name ?: 'Visitatore');
            send_thread_notifications($thread_id, $new_post_id, $user_id, $poster_display_name, $mysqli);
        } elseif ($status === 'pending') {
            $visitor_name = $author_name ?: 'Visitatore';
            send_visitor_comment_notification($thread_id, $new_post_id, $visitor_name, $mysqli);
        }
        // --- FINE BLOCCO NOTIFICHE ---

        $mysqli->commit();
        $_SESSION['feedback'] = ['type' => 'success', 'message' => ($status === 'pending' ? 'Grazie! Il tuo commento sarà visibile dopo l\'approvazione.' : 'Commento aggiunto con successo!')];

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Comment Action Error: " . $e->getMessage());
        $_SESSION['feedback'] = ['type' => 'error', 'message' => 'Si è verificato un errore. Riprova.'];
    }
    
    // --- RIGA DI REINDIRIZZAMENTO CORRETTA ---
    // La versione che mi hai fornito non aveva ancora questa modifica.
    // Aggiungo BASE_URL per creare un percorso assoluto e corretto.
    $final_redirect_url = $redirect_url;
    // Se la discussione è nuova, dobbiamo reindirizzare alla pagina del thread, non a quella del fumetto
    if (empty($_POST['thread_id']) && $thread_id > 0) {
        $final_redirect_url = 'thread.php?id=' . $thread_id;
    }

    header('Location: ' . BASE_URL . $final_redirect_url . '#post-' . $new_post_id);
    exit;
}