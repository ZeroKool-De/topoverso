<?php
require_once 'config/config.php';

// Se l'utente è già loggato, lo reindirizzo alla sua dashboard
if (isset($_SESSION['user_id_frontend'])) {
    header('Location: user_dashboard.php');
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'includes/db_connect.php';

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error_message = "Tutti i campi sono obbligatori.";
    } else {
        $stmt = $mysqli->prepare("SELECT user_id, username, password_hash, user_role, is_active, avatar_image_path FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password_hash'])) {
                if ($user['is_active'] == 1) {
                    // --- INIZIO LOGICA DI LOGIN UNIFICATA ---
                    
                    // Imposta la sessione utente standard
                    $_SESSION['user_id_frontend'] = $user['user_id'];
                    $_SESSION['username_frontend'] = $user['username'];
                    $_SESSION['user_role_frontend'] = $user['user_role'];
                    $_SESSION['avatar_frontend'] = $user['avatar_image_path'];

                    // Se l'utente ha il ruolo 'admin', imposta ANCHE la sessione da amministratore
                    if ($user['user_role'] === 'admin') {
                        $_SESSION['admin_user_id'] = $user['user_id'];
                        $_SESSION['admin_username'] = $user['username'];
                    }

                    header("Location: user_dashboard.php");
                    exit;
                    
                    // --- FINE LOGICA DI LOGIN UNIFICATA ---
                } else {
                    $error_message = "Il tuo account non è ancora stato attivato. Attendi l'approvazione di un amministratore.";
                }
            } else {
                $error_message = "Nome utente o password non validi.";
            }
        } else {
            $error_message = "Nome utente o password non validi.";
        }
        $stmt->close();
        $mysqli->close();
    }
}

$page_title = "Login";
require_once 'includes/header.php';
?>

<div class="container page-content-container">
    <h1>Login</h1>
    <p>Accedi al tuo account per gestire la tua collezione e partecipare alle discussioni.</p>

    <?php if (!empty($error_message)): ?>
        <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <form action="login.php" method="post" class="user-form">
        <div class="form-group">
            <label for="username">Nome Utente:</label>
            <input type="text" name="username" id="username" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Accedi</button>
    </form>
    <p style="margin-top: 20px;">Non hai un account? <a href="register.php">Registrati ora</a>!</p>
</div>

<?php require_once 'includes/footer.php'; ?>