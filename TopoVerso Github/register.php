<?php
require_once 'config/config.php'; // Contiene session_start()
require_once 'includes/db_connect.php';

$page_title = "Registrazione Utente";
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // Validazione
    if (empty($username)) {
        $errors[] = "Il nome utente è obbligatorio.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = "Il nome utente deve essere tra 3 e 50 caratteri.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Il nome utente può contenere solo lettere, numeri e underscore (_).";
    }

    if (empty($email)) {
        $errors[] = "L'email è obbligatoria.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email non è valida.";
    }

    if (empty($password)) {
        $errors[] = "La password è obbligatoria.";
    } elseif (strlen($password) < 6) {
        $errors[] = "La password deve essere di almeno 6 caratteri.";
    }

    if ($password !== $password_confirm) {
        $errors[] = "Le password non coincidono.";
    }

    // Verifica se username o email esistono già
    if (empty($errors)) {
        $stmt_check = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param("ss", $username, $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $existing_user = $result_check->fetch_assoc();
            // Non specificare quale campo esiste già per sicurezza
            $errors[] = "Nome utente o email già registrati.";
        }
        $stmt_check->close();
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        // is_active è 0 di default come da schema DB

        $stmt_insert = $mysqli->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sss", $username, $email, $password_hash);

        if ($stmt_insert->execute()) {
            // Messaggio di successo e informazione sull'attivazione
            $_SESSION['registration_pending_message'] = "Registrazione completata con successo! Un amministratore dovrà approvare il tuo account. Riceverai una notifica (non via email, ma controlla il sito) o prova a fare login più tardi.";
            header('Location: login.php'); // Reindirizza alla pagina di login con un messaggio
            exit;
        } else {
            $errors[] = "Errore durante la registrazione. Riprova. " . $stmt_insert->error;
        }
        $stmt_insert->close();
    }
}

require_once 'includes/header.php'; // Header del frontend
?>

<div class="container page-content-container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <strong>Errore!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p>Compila i campi sottostanti per registrarti. Dopo la registrazione, un amministratore dovrà attivare il tuo account prima che tu possa accedere.</p>

    <form action="register.php" method="POST" class="user-form">
        <div class="form-group">
            <label for="username">Nome Utente:</label>
            <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            <small>Minimo 3 caratteri, solo lettere, numeri e underscore (_).</small>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" class="form-control" required>
            <small>Minimo 6 caratteri.</small>
        </div>
        <div class="form-group">
            <label for="password_confirm">Conferma Password:</label>
            <input type="password" name="password_confirm" id="password_confirm" class="form-control" required>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Registrati</button>
        </div>
    </form>
    <p style="margin-top: 20px;">Hai già un account? <a href="login.php">Accedi qui</a>.</p>
</div>

<style>
/* Stili base per form utente (puoi spostarli in style.css) */
.user-form .form-control {
    width: 100%;
    padding: 10px;
    margin-bottom: 5px; /* Spazio prima della small */
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}
.user-form .form-group small {
    font-size: 0.85em;
    color: #666;
}
.message.error ul {
    margin-top: 5px;
    margin-bottom: 0;
    padding-left: 20px;
}
</style>

<?php
$mysqli->close();
require_once 'includes/footer.php'; // Footer del frontend
?>