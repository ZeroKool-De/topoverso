<?php
require_once 'config/config.php'; // Contiene session_start()
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id_frontend'])) {
    $_SESSION['message'] = "Devi essere loggato per gestire la tua collezione.";
    $_SESSION['message_type'] = 'error';
    $fallback_redirect = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'login.php');
    header('Location: ' . $fallback_redirect);
    exit;
}
$user_id = $_SESSION['user_id_frontend'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $comic_id = isset($_POST['comic_id']) ? (int)$_POST['comic_id'] : null;
    $placeholder_issue_number = isset($_POST['placeholder_issue_number']) ? trim($_POST['placeholder_issue_number']) : null;
    $placeholder_id = isset($_POST['placeholder_id']) ? (int)$_POST['placeholder_id'] : null; // NUOVO per rimozione placeholder

    $redirect_url = $_POST['redirect_url'] ?? 'user_dashboard.php'; 

    if ($action !== 'add_placeholder_to_collection' && $action !== 'remove_placeholder_from_collection' && !$comic_id) {
        $_SESSION['message'] = "ID fumetto mancante.";
        $_SESSION['message_type'] = 'error';
        header('Location: ' . $redirect_url);
        exit;
    }
    
    if ($action !== 'add_placeholder_to_collection' && $action !== 'remove_placeholder_from_collection' && $comic_id) {
        $stmt_check_comic = $mysqli->prepare("SELECT comic_id FROM comics WHERE comic_id = ?");
        $stmt_check_comic->bind_param("i", $comic_id);
        $stmt_check_comic->execute();
        $result_check_comic = $stmt_check_comic->get_result();
        if ($result_check_comic->num_rows === 0) {
            $_SESSION['message'] = "Fumetto non valido.";
            $_SESSION['message_type'] = 'error';
            header('Location: ' . $redirect_url);
            exit;
        }
        $stmt_check_comic->close();
    }


    if ($action === 'add_to_collection') {
        // ... (codice esistente invariato)
        $stmt = $mysqli->prepare("INSERT INTO user_collections (user_id, comic_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE comic_id=comic_id"); 
        $stmt->bind_param("ii", $user_id, $comic_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Fumetto aggiunto alla tua collezione!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Errore durante l'aggiunta: " . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
    } elseif ($action === 'remove_from_collection') {
        // ... (codice esistente invariato)
        $stmt = $mysqli->prepare("DELETE FROM user_collections WHERE user_id = ? AND comic_id = ?");
        $stmt->bind_param("ii", $user_id, $comic_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Fumetto rimosso dalla collezione.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Errore durante la rimozione: " . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
    } elseif ($action === 'update_read_status') {
        // ... (codice esistente invariato)
        $is_read = isset($_POST['is_read']) ? 1 : 0;
        $stmt = $mysqli->prepare("UPDATE user_collections SET is_read = ? WHERE user_id = ? AND comic_id = ?");
        $stmt->bind_param("iii", $is_read, $user_id, $comic_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Stato di lettura aggiornato.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Errore aggiornamento stato lettura: " . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
    } elseif ($action === 'update_rating') {
        // ... (codice esistente invariato)
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
        if ($rating !== null && ($rating < 0 || $rating > 5)) { 
            $rating = null; 
        }
        if ($rating === 0) $rating = null; 

        $stmt = $mysqli->prepare("UPDATE user_collections SET rating = ? WHERE user_id = ? AND comic_id = ?");
        $stmt->bind_param("iii", $rating, $user_id, $comic_id); 
        if ($stmt->execute()) {
            $_SESSION['message'] = "Voto aggiornato.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Errore aggiornamento voto: " . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
    } elseif ($action === 'add_placeholder_to_collection') {
        // ... (codice della Fase 2 invariato)
        if (empty($placeholder_issue_number)) {
            $_SESSION['message'] = "Il numero dell'albo mancante è obbligatorio.";
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt_check_placeholder = $mysqli->prepare("SELECT placeholder_id FROM user_collection_placeholders WHERE user_id = ? AND issue_number_placeholder = ? AND status = 'pending'");
            $stmt_check_placeholder->bind_param("is", $user_id, $placeholder_issue_number);
            $stmt_check_placeholder->execute();
            $result_check_placeholder = $stmt_check_placeholder->get_result();

            if ($result_check_placeholder->num_rows > 0) {
                $_SESSION['message'] = "Hai già segnalato il numero '".htmlspecialchars($placeholder_issue_number)."' come mancante.";
                $_SESSION['message_type'] = 'info';
            } else {
                $comic_id_real = null;
                $stmt_check_real_comic = $mysqli->prepare("SELECT comic_id FROM comics WHERE issue_number = ?");
                $stmt_check_real_comic->bind_param("s", $placeholder_issue_number);
                $stmt_check_real_comic->execute();
                $result_real_comic = $stmt_check_real_comic->get_result();
                if ($real_comic_row = $result_real_comic->fetch_assoc()) {
                    $comic_id_real = $real_comic_row['comic_id'];
                }
                $stmt_check_real_comic->close();

                if ($comic_id_real) {
                    $stmt_check_user_has_real = $mysqli->prepare("SELECT collection_id FROM user_collections WHERE user_id = ? AND comic_id = ?");
                    $stmt_check_user_has_real->bind_param("ii", $user_id, $comic_id_real);
                    $stmt_check_user_has_real->execute();
                    if ($stmt_check_user_has_real->get_result()->num_rows > 0) {
                        $_SESSION['message'] = "Il numero '".htmlspecialchars($placeholder_issue_number)."' è già presente nel catalogo e nella tua collezione.";
                        $_SESSION['message_type'] = 'info';
                    } else {
                        $stmt_add_real = $mysqli->prepare("INSERT INTO user_collections (user_id, comic_id) VALUES (?, ?)");
                        $stmt_add_real->bind_param("ii", $user_id, $comic_id_real);
                        if ($stmt_add_real->execute()) {
                            $_SESSION['message'] = "Il numero '".htmlspecialchars($placeholder_issue_number)."' esiste già nel catalogo ed è stato aggiunto alla tua collezione!";
                            $_SESSION['message_type'] = 'success';
                        } else {
                            $_SESSION['message'] = "Errore: Impossibile aggiungere il fumetto '".htmlspecialchars($placeholder_issue_number)."' alla tua collezione. " . $stmt_add_real->error;
                            $_SESSION['message_type'] = 'error';
                        }
                        $stmt_add_real->close();
                    }
                    $stmt_check_user_has_real->close();
                } else {
                    $stmt_insert_placeholder = $mysqli->prepare("INSERT INTO user_collection_placeholders (user_id, issue_number_placeholder) VALUES (?, ?)");
                    $stmt_insert_placeholder->bind_param("is", $user_id, $placeholder_issue_number);
                    if ($stmt_insert_placeholder->execute()) {
                        $_SESSION['message'] = "Numero '".htmlspecialchars($placeholder_issue_number)."' aggiunto come segnalazione alla tua lista!";
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = "Errore durante l'aggiunta della segnalazione: " . $stmt_insert_placeholder->error;
                        $_SESSION['message_type'] = 'error';
                    }
                    $stmt_insert_placeholder->close();
                }
            }
            $stmt_check_placeholder->close();
        }
    // NUOVA AZIONE: remove_placeholder_from_collection
    } elseif ($action === 'remove_placeholder_from_collection') {
        if (!$placeholder_id) {
            $_SESSION['message'] = "ID segnalazione mancante.";
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt_delete_placeholder = $mysqli->prepare("DELETE FROM user_collection_placeholders WHERE placeholder_id = ? AND user_id = ?");
            $stmt_delete_placeholder->bind_param("ii", $placeholder_id, $user_id);
            if ($stmt_delete_placeholder->execute()) {
                if ($stmt_delete_placeholder->affected_rows > 0) {
                    $_SESSION['message'] = "Segnalazione rimossa dalla tua lista.";
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = "Segnalazione non trovata o non appartenente a te.";
                    $_SESSION['message_type'] = 'error';
                }
            } else {
                $_SESSION['message'] = "Errore durante la rimozione della segnalazione: " . $stmt_delete_placeholder->error;
                $_SESSION['message_type'] = 'error';
            }
            $stmt_delete_placeholder->close();
        }
    // FINE NUOVA AZIONE
    } else {
        $_SESSION['message'] = "Azione non valida.";
        $_SESSION['message_type'] = 'error';
    }

    $mysqli->close();
    header('Location: ' . $redirect_url);
    exit;
} else {
    $fallback_redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $fallback_redirect);
    exit;
}
?>