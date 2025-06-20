<?php
require_once 'config/config.php'; // Contiene session_start()
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id_frontend'])) {
    $_SESSION['profile_message'] = "Devi essere loggato per modificare il profilo.";
    $_SESSION['profile_message_type'] = 'error';
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id_frontend'];
$redirect_url = 'user_dashboard.php'; // Pagina a cui tornare

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Recupera i dati attuali dell'utente per controlli e percorsi file
    $stmt_user_current = $mysqli->prepare("SELECT username, password_hash, avatar_image_path FROM users WHERE user_id = ?");
    $stmt_user_current->bind_param("i", $user_id);
    $stmt_user_current->execute();
    $result_user_current = $stmt_user_current->get_result();
    $current_user_data = $result_user_current->fetch_assoc();
    $stmt_user_current->close();

    if (!$current_user_data) {
        $_SESSION['profile_message'] = "Utente non trovato.";
        $_SESSION['profile_message_type'] = 'error';
        header('Location: ' . $redirect_url);
        exit;
    }

    if ($action === 'update_username') {
        $new_username = trim($_POST['new_username']);
        if (empty($new_username)) {
            $_SESSION['profile_message'] = "Il nuovo nome utente non può essere vuoto.";
            $_SESSION['profile_message_type'] = 'error';
        } elseif ($new_username === $current_user_data['username']) {
            $_SESSION['profile_message'] = "Il nuovo nome utente è uguale a quello attuale.";
            $_SESSION['profile_message_type'] = 'info';
        } elseif (strlen($new_username) < 3 || strlen($new_username) > 50) {
            $_SESSION['profile_message'] = "Il nome utente deve essere tra 3 e 50 caratteri.";
            $_SESSION['profile_message_type'] = 'error';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
            $_SESSION['profile_message'] = "Il nome utente può contenere solo lettere, numeri e underscore (_).";
            $_SESSION['profile_message_type'] = 'error';
        } else {
            // Verifica unicità nuovo username (escludendo l'utente stesso)
            $stmt_check = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt_check->bind_param("si", $new_username, $user_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $_SESSION['profile_message'] = "Questo nome utente è già in uso.";
                $_SESSION['profile_message_type'] = 'error';
            } else {
                $stmt_update = $mysqli->prepare("UPDATE users SET username = ? WHERE user_id = ?");
                $stmt_update->bind_param("si", $new_username, $user_id);
                if ($stmt_update->execute()) {
                    $_SESSION['username_frontend'] = $new_username; // Aggiorna la sessione
                    $_SESSION['profile_message'] = "Nome utente aggiornato con successo!";
                    $_SESSION['profile_message_type'] = 'success';
                } else {
                    $_SESSION['profile_message'] = "Errore durante l'aggiornamento del nome utente: " . $stmt_update->error;
                    $_SESSION['profile_message_type'] = 'error';
                }
                $stmt_update->close();
            }
            $stmt_check->close();
        }
    } elseif ($action === 'update_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            $_SESSION['profile_message'] = "Tutti i campi password sono obbligatori.";
            $_SESSION['profile_message_type'] = 'error';
        } elseif (!password_verify($current_password, $current_user_data['password_hash'])) {
            $_SESSION['profile_message'] = "La password attuale non è corretta.";
            $_SESSION['profile_message_type'] = 'error';
        } elseif (strlen($new_password) < 6) {
            $_SESSION['profile_message'] = "La nuova password deve essere di almeno 6 caratteri.";
            $_SESSION['profile_message_type'] = 'error';
        } elseif ($new_password !== $confirm_new_password) {
            $_SESSION['profile_message'] = "Le nuove password non coincidono.";
            $_SESSION['profile_message_type'] = 'error';
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt_update->bind_param("si", $new_password_hash, $user_id);
            if ($stmt_update->execute()) {
                $_SESSION['profile_message'] = "Password aggiornata con successo!";
                $_SESSION['profile_message_type'] = 'success';
            } else {
                $_SESSION['profile_message'] = "Errore durante l'aggiornamento della password: " . $stmt_update->error;
                $_SESSION['profile_message_type'] = 'error';
            }
            $stmt_update->close();
        }
    } elseif ($action === 'update_avatar') {
        $avatar_image_path_db = $current_user_data['avatar_image_path']; // Path DB corrente
        $upload_dir_relative = 'avatars/';
        $upload_dir_absolute = UPLOADS_PATH . $upload_dir_relative;

        if (!is_dir($upload_dir_absolute)) {
            if (!mkdir($upload_dir_absolute, 0775, true)) {
                $_SESSION['profile_message'] = "Errore: Impossibile creare la cartella per gli avatar.";
                $_SESSION['profile_message_type'] = 'error';
                header('Location: ' . $redirect_url);
                exit;
            }
        }

        if (isset($_POST['delete_avatar']) && $_POST['delete_avatar'] == '1') {
            if ($avatar_image_path_db && file_exists(UPLOADS_PATH . $avatar_image_path_db)) {
                @unlink(UPLOADS_PATH . $avatar_image_path_db);
            }
            $avatar_image_path_db = null;
            // Aggiorna il DB per riflettere la rimozione
            $stmt_remove_avatar = $mysqli->prepare("UPDATE users SET avatar_image_path = NULL WHERE user_id = ?");
            if ($stmt_remove_avatar) {
                $stmt_remove_avatar->bind_param("i", $user_id);
                $stmt_remove_avatar->execute();
                $stmt_remove_avatar->close();
                $_SESSION['avatar_frontend'] = null; // Aggiorna sessione
                if (!isset($_FILES['avatar_image']) || $_FILES['avatar_image']['error'] == UPLOAD_ERR_NO_FILE) {
                     $_SESSION['profile_message'] = "Avatar rimosso."; // Messaggio solo se non si carica nulla di nuovo
                     $_SESSION['profile_message_type'] = 'success';
                }
            } else {
                $_SESSION['profile_message'] = "Errore DB rimozione avatar.";
                $_SESSION['profile_message_type'] = 'error';
                 header('Location: ' . $redirect_url); exit;
            }
            // Se si cancella e non si carica, esci.
            if (!isset($_FILES['avatar_image']) || $_FILES['avatar_image']['error'] == UPLOAD_ERR_NO_FILE) {
                 header('Location: ' . $redirect_url); exit;
            }
        }

        if (isset($_FILES['avatar_image']) && $_FILES['avatar_image']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_name = $_FILES['avatar_image']['tmp_name'];
            $original_filename_avatar = $_FILES['avatar_image']['name'];
            $file_size = $_FILES['avatar_image']['size'];
            // $file_type = $_FILES['avatar_image']['type']; // Non strettamente necessario se si valida l'estensione
            $file_extension_avatar = strtolower(pathinfo($original_filename_avatar, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_file_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($file_extension_avatar, $allowed_extensions)) {
                $_SESSION['profile_message'] = "Formato file non consentito. Ammessi: JPG, JPEG, PNG, GIF, WEBP.";
                $_SESSION['profile_message_type'] = 'error';
            } elseif ($file_size > $max_file_size) {
                $_SESSION['profile_message'] = "Il file è troppo grande. Massimo 2MB.";
                $_SESSION['profile_message_type'] = 'error';
            } else {
                // Nome file temporaneo senza estensione per l'avatar
                $temp_avatar_filename_no_ext = 'avatar_' . $user_id . '_' . uniqid();
                $temp_avatar_uploaded_absolute = $upload_dir_absolute . $temp_avatar_filename_no_ext . '.' . $file_extension_avatar;

                if (move_uploaded_file($file_tmp_name, $temp_avatar_uploaded_absolute)) {
                    $jpeg_quality_avatar = 70;
                    $png_compression_avatar = 9;
                    $convertToWebp_avatar = true;

                    $processed_avatar_absolute_path = compress_and_optimize_image(
                        $temp_avatar_uploaded_absolute,
                        $temp_avatar_uploaded_absolute,
                        $file_extension_avatar,
                        $jpeg_quality_avatar,
                        $png_compression_avatar,
                        $convertToWebp_avatar
                    );

                    if ($processed_avatar_absolute_path) {
                        $final_avatar_filename_db = $upload_dir_relative . basename($processed_avatar_absolute_path);

                        // Elimina vecchio avatar DB se presente e diverso dal nuovo
                        if ($current_user_data['avatar_image_path'] && $current_user_data['avatar_image_path'] !== $final_avatar_filename_db && file_exists(UPLOADS_PATH . $current_user_data['avatar_image_path'])) {
                            @unlink(UPLOADS_PATH . $current_user_data['avatar_image_path']);
                        }
                        $avatar_image_path_db = $final_avatar_filename_db; // Aggiorna il path da salvare

                        $stmt_update_avatar = $mysqli->prepare("UPDATE users SET avatar_image_path = ? WHERE user_id = ?");
                        $stmt_update_avatar->bind_param("si", $avatar_image_path_db, $user_id);
                        if ($stmt_update_avatar->execute()) {
                            $_SESSION['avatar_frontend'] = $avatar_image_path_db; // Aggiorna sessione
                            $_SESSION['profile_message'] = "Avatar aggiornato con successo!";
                            $_SESSION['profile_message_type'] = 'success';
                        } else {
                            $_SESSION['profile_message'] = "Errore durante l'aggiornamento dell'avatar nel database: " . $stmt_update_avatar->error;
                            $_SESSION['profile_message_type'] = 'error';
                            if (file_exists($processed_avatar_absolute_path)) @unlink($processed_avatar_absolute_path);
                        }
                        $stmt_update_avatar->close();
                    } else {
                        $_SESSION['profile_message'] = "Errore durante la compressione dell'avatar. L'upload originale (non compresso) potrebbe essere stato salvato se la compressione non ha potuto sovrascriverlo.";
                        $_SESSION['profile_message_type'] = 'error';
                        if (file_exists($temp_avatar_uploaded_absolute)) @unlink($temp_avatar_uploaded_absolute); // Cancella il file temporaneo non processato
                    }
                } else {
                    $_SESSION['profile_message'] = "Errore durante il caricamento del file avatar.";
                    $_SESSION['profile_message_type'] = 'error';
                }
            }
        } elseif (isset($_FILES['avatar_image']) && $_FILES['avatar_image']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['avatar_image']['error'] != UPLOAD_ERR_OK) {
             $_SESSION['profile_message'] = "Errore upload avatar (codice: " . $_FILES['avatar_image']['error'] . ").";
             $_SESSION['profile_message_type'] = 'error';
        }
        // Se non c'è un messaggio di errore dall'upload e non è stata spuntata solo la cancellazione,
        // ma non è stato neanche inviato un file, non fare nulla (o aggiungi un messaggio 'info').
        // L'importante è che l'eliminazione (se checkata) sia già stata processata.

    } else {
        $_SESSION['profile_message'] = "Azione non riconosciuta.";
        $_SESSION['profile_message_type'] = 'error';
    }
} else {
    // Se non è POST o manca l'azione
    $_SESSION['profile_message'] = "Richiesta non valida.";
    $_SESSION['profile_message_type'] = 'error';
}

$mysqli->close();
header('Location: ' . $redirect_url);
exit;
?>